<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Inventaris Desa: lapisan sederhana di atas modul aset.
 *
 * Satu "barang" = satu unit fisik (asset_units) dengan QR sendiri. Register, verifikasi,
 * dan tahap draft ditangani otomatis di belakang layar supaya pengelola cukup mengisi satu
 * form. Semua perubahan tetap lewat AssetService sehingga riwayat dan audit log terjaga.
 */
class InventoryService {

	/** Kondisi yang dipakai layar inventaris. */
	const CONDITIONS = array(
		'good' => 'Baik',
		'minor_damage' => 'Rusak ringan',
		'major_damage' => 'Rusak berat',
	);

	/** Status barang yang dipakai layar inventaris (lifecycle aset). */
	const STATUSES = array(
		'active' => 'Aktif',
		'in_maintenance' => 'Sedang diperbaiki',
		'lost' => 'Hilang',
		'disposed' => 'Dihapuskan',
		'draft' => 'Belum aktif',
	);

	/** Batas jumlah barang sekali tambah (masing-masing mendapat kode dan QR). */
	const MAX_QUANTITY = 100;

	/** @var CI_Controller */
	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->library('AssetService', NULL, 'assets');
	}

	// ------------------------------------------------------------------
	// Daftar dan ringkasan
	// ------------------------------------------------------------------

	/** Daftar barang untuk tabel inventaris. */
	public function items(array $filters = array())
	{
		$db = $this->CI->db;
		$db->select('u.*, r.name, r.public_id AS register_public_id, r.acquisition_year, r.description AS register_description,
				c.name AS category_name, l.name AS location_name,
				(SELECT COUNT(*) FROM asset_qr_tokens q WHERE q.asset_unit_id = u.id AND q.status = \'active\') AS qr_active,
				(SELECT COUNT(*) FROM asset_loans lo WHERE lo.asset_unit_id = u.id AND lo.status = \'out\') AS on_loan', FALSE)
			->from('asset_units u')
			->join('asset_registers r', 'r.id = u.register_id')
			->join('asset_categories c', 'c.id = r.category_id')
			->join('asset_locations l', 'l.id = u.location_id', 'left');
		if ( ! empty($filters['kategori']))
		{
			$db->where('r.category_id', (int) $filters['kategori']);
		}
		if ( ! empty($filters['lokasi']))
		{
			$db->where('u.location_id', (int) $filters['lokasi']);
		}
		if ( ! empty($filters['kondisi']) && isset(AssetService::CONDITIONS[$filters['kondisi']]))
		{
			$db->where('u.condition_status', (string) $filters['kondisi']);
		}
		$status = (string) ($filters['status'] ?? '');
		if ($status === '')
		{
			// Bawaan: sembunyikan barang yang sudah dihapuskan.
			$db->where('u.lifecycle_status <>', 'disposed');
		}
		elseif ($status !== 'semua' && isset(AssetService::LIFECYCLE[$status]))
		{
			$db->where('u.lifecycle_status', $status);
		}
		if ( ! empty($filters['q']))
		{
			$db->group_start()->like('r.name', (string) $filters['q'])->or_like('u.asset_tag', (string) $filters['q'])
				->or_like('u.brand', (string) $filters['q'])->or_like('u.model', (string) $filters['q'])->group_end();
		}
		return $db->order_by('u.asset_tag')->limit(2000)->get()->result();
	}

	/** Kartu ringkas di atas daftar. */
	public function summary()
	{
		$rows = $this->CI->db->select('condition_status, lifecycle_status, COUNT(*) AS total', FALSE)
			->where('lifecycle_status <>', 'disposed')->group_by(array('condition_status', 'lifecycle_status'))
			->get('asset_units')->result();
		$out = array('total' => 0, 'good' => 0, 'minor_damage' => 0, 'major_or_lost' => 0, 'busy' => 0);
		foreach ($rows as $row)
		{
			$n = (int) $row->total;
			$out['total'] += $n;
			if ($row->lifecycle_status === 'lost' OR $row->condition_status === 'major_damage')
			{
				$out['major_or_lost'] += $n;
			}
			elseif ($row->condition_status === 'minor_damage')
			{
				$out['minor_damage'] += $n;
			}
			elseif ($row->condition_status === 'good')
			{
				$out['good'] += $n;
			}
			if ($row->lifecycle_status === 'in_maintenance')
			{
				$out['busy'] += $n;
			}
		}
		$out['busy'] += (int) $this->CI->db->where('status', 'out')->count_all_results('asset_loans');
		return $out;
	}

	/** Data lengkap satu barang untuk halaman detail. */
	public function item($public_id)
	{
		$items = $this->CI->db->select('u.*, r.name, r.public_id AS register_public_id, r.category_id, r.acquisition_year,
				r.acquisition_source, r.acquisition_value, r.description AS register_description,
				c.name AS category_name, l.name AS location_name', FALSE)
			->from('asset_units u')
			->join('asset_registers r', 'r.id = u.register_id')
			->join('asset_categories c', 'c.id = r.category_id')
			->join('asset_locations l', 'l.id = u.location_id', 'left')
			->where('u.public_id', (string) $public_id)->get()->result();
		return $items ? $items[0] : NULL;
	}

	public function open_loan($unit)
	{
		return $this->CI->db->where('asset_unit_id', (int) $unit->id)->where('status', 'out')
			->order_by('id', 'DESC')->limit(1)->get('asset_loans')->row();
	}

	public function open_maintenance($unit)
	{
		return $this->CI->db->where('asset_unit_id', (int) $unit->id)->where('status <>', 'completed')
			->order_by('id', 'DESC')->limit(1)->get('asset_maintenance')->row();
	}

	// ------------------------------------------------------------------
	// Tambah dan ubah
	// ------------------------------------------------------------------

	/**
	 * Tambah barang. Jumlah > 1 membuat beberapa barang dengan kode dan QR masing-masing.
	 * @return object[] unit yang dibuat
	 */
	public function create(array $input, $user_id)
	{
		$quantity = (int) ($input['jumlah'] ?? 1);
		if ($quantity < 1 OR $quantity > self::MAX_QUANTITY)
		{
			throw new DomainRuleException('Jumlah barang harus antara 1 dan '.self::MAX_QUANTITY.'.', 422, array('jumlah' => 'Di luar rentang.'));
		}
		$condition = $this->valid_condition($input['kondisi'] ?? 'good');
		$created = array();
		db_transaction(function () use ($input, $user_id, $quantity, $condition, &$created) {
			$register = $this->save_register($this->register_input($input), $user_id);
			db_must($this->CI->db->where('id', (int) $register->id)->update('asset_registers', array(
				'lifecycle_status' => 'active', 'verification_status' => 'verified', 'updated_at' => utc_now(),
			)), 'asset_registers.inventory_active');
			$location_id = $this->resolve_location($input);
			for ($i = 1; $i <= $quantity; $i++)
			{
				$unit = $this->CI->assets->create_unit($register, array(
					'asset_tag' => $this->next_code(),
					'unit_sequence' => $i,
					'brand' => $input['merk'] ?? '',
					'model' => $input['tipe'] ?? '',
					'location_id' => $location_id,
					'public_note' => $input['catatan_publik'] ?? '',
				), $user_id);
				$now = utc_now();
				db_must($this->CI->db->where('id', (int) $unit->id)->update('asset_units', array(
					'lifecycle_status' => 'active', 'condition_status' => $condition,
					'activated_at' => $now, 'updated_at' => $now,
				)), 'asset_units.inventory_active');
				$this->log_event($unit, 'created', 'Barang ditambahkan ke inventaris', $user_id, array(
					'to_lifecycle' => 'active', 'to_condition' => $condition,
				));
				$unit = $this->CI->assets->unit($unit->public_id);
				$this->CI->assets->issue_token($unit, $user_id, 'QR dibuat saat barang ditambahkan');
				$created[] = $unit;
			}
		});
		return $created;
	}

	/** Ubah data barang. Kode dan QR tidak berubah karena mungkin sudah tertempel. */
	public function update($unit, array $input, $user_id)
	{
		$before = $this->item($unit->public_id);
		$register = $this->CI->db->get_where('asset_registers', array('id' => (int) $unit->register_id))->row();
		db_transaction(function () use ($unit, $register, $input, $user_id, $before) {
			$saved = $this->save_register($this->register_input($input, $register), $user_id, $register->public_id);
			// Data yang diisi dari layar inventaris dianggap sudah diperiksa pengelola.
			db_must($this->CI->db->where('id', (int) $saved->id)->update('asset_registers', array(
				'verification_status' => 'verified',
				'lifecycle_status' => $saved->lifecycle_status === 'draft' ? 'active' : $saved->lifecycle_status,
			)), 'asset_registers.inventory_verified');
			$unit_data = array(
				'brand' => mb_substr(trim((string) ($input['merk'] ?? '')), 0, 120) ?: NULL,
				'model' => mb_substr(trim((string) ($input['tipe'] ?? '')), 0, 120) ?: NULL,
				'public_note' => mb_substr(trim((string) ($input['catatan_publik'] ?? '')), 0, 500) ?: NULL,
				'version' => (int) $unit->version + 1,
				'updated_at' => utc_now(),
			);
			db_must($this->CI->db->where('id', (int) $unit->id)->update('asset_units', $unit_data), 'asset_units.inventory_update');

			$after = $this->item($unit->public_id);
			$labels = array('name' => 'nama', 'category_name' => 'kategori', 'brand' => 'merk', 'model' => 'tipe',
				'acquisition_year' => 'tahun', 'acquisition_source' => 'sumber dana', 'acquisition_value' => 'harga',
				'register_description' => 'keterangan', 'public_note' => 'catatan publik');
			$changed = array();
			foreach ($labels as $field => $label)
			{
				if ((string) $before->$field !== (string) $after->$field)
				{
					$changed[] = $label;
				}
			}
			if ($changed)
			{
				$this->log_event($unit, 'updated', 'Data diubah: '.implode(', ', $changed), $user_id);
			}
		});
		return $this->CI->assets->unit($unit->public_id);
	}

	/** Pasang foto utama barang (lewat pipeline Media Library). */
	public function attach_photo($unit, $field, $user_id)
	{
		if (empty($_FILES[$field]['name']) OR (is_array($_FILES[$field]['name']) && empty(array_filter($_FILES[$field]['name']))))
		{
			return FALSE;
		}
		$this->CI->load->library('UploadService', NULL, 'uploads');
		$item = $this->item($unit->public_id);
		$media_id = $this->CI->uploads->store_media_asset($field, array(
			'alt_text' => 'Foto '.$item->name.' ('.$item->asset_tag.')',
			'caption' => $item->name,
			'source_credit' => 'Pemerintah Desa Cihawuk',
			'source_year' => (int) date('Y'),
			'rights_status' => 'owned',
		), $user_id);
		$this->CI->uploads->commit_staged();
		db_must($this->CI->db->where('id', (int) $unit->id)->update('asset_units', array(
			'primary_media_id' => (int) $media_id, 'updated_at' => utc_now(),
		)), 'asset_units.photo');
		$this->log_event($unit, 'updated', 'Foto barang diperbarui', $user_id);
		return TRUE;
	}

	// ------------------------------------------------------------------
	// Aksi cepat
	// ------------------------------------------------------------------

	/** Pindah lokasi langsung (tanpa tahap serah terima). */
	public function move($unit, array $input, $user_id)
	{
		$location_id = $this->resolve_location($input);
		if ($location_id === NULL)
		{
			throw new DomainRuleException('Pilih atau ketik lokasi tujuan.', 422, array('location_id' => 'Wajib diisi.'));
		}
		if ((int) $unit->location_id === $location_id)
		{
			throw new DomainRuleException('Barang sudah berada di lokasi itu.', 409);
		}
		db_transaction(function () use ($unit, $location_id, $input, $user_id) {
			$movement = $this->CI->assets->request_movement($unit, array(
				'to_location_id' => $location_id,
				'reason' => $this->note($input, 'Dipindahkan oleh pengelola inventaris'),
			), $user_id);
			$this->CI->assets->accept_movement($movement, $user_id);
		});
	}

	public function lend($unit, array $input, $user_id)
	{
		if ($unit->lifecycle_status !== 'active')
		{
			throw new DomainRuleException('Hanya barang aktif yang dapat dipinjamkan.', 409);
		}
		$this->CI->assets->checkout($unit, array(
			'borrower_name' => $input['peminjam'] ?? '',
			'borrower_unit' => $input['instansi'] ?? '',
			'purpose' => trim((string) ($input['keperluan'] ?? '')) ?: 'Dipinjam',
			'due_at' => $input['kembali_tanggal'] ?? '',
		), $user_id);
	}

	public function return_item($unit, array $input, $user_id)
	{
		$loan = $this->open_loan($unit);
		if ( ! $loan)
		{
			throw new DomainRuleException('Barang ini tidak sedang dipinjam.', 409);
		}
		$condition = $this->valid_condition($input['kondisi'] ?? $unit->condition_status);
		db_transaction(function () use ($unit, $loan, $condition, $input, $user_id) {
			$this->CI->assets->return_loan($loan, array('return_condition' => $condition), $user_id);
			if ($condition !== $unit->condition_status)
			{
				$this->CI->assets->change_status($unit, array(
					'to_condition' => $condition,
					'reason' => $this->note($input, 'Kondisi dicatat saat barang dikembalikan'),
				), $user_id);
			}
		});
	}

	public function start_repair($unit, array $input, $user_id)
	{
		if ($this->open_maintenance($unit))
		{
			throw new DomainRuleException('Barang ini masih dalam perbaikan.', 409);
		}
		db_transaction(function () use ($unit, $input, $user_id) {
			$this->CI->assets->create_maintenance($unit, array(
				'complaint' => trim((string) ($input['keluhan'] ?? '')) ?: 'Perlu perbaikan',
				'vendor' => $input['bengkel'] ?? '',
			), $user_id);
			if ($unit->lifecycle_status === 'active')
			{
				$this->CI->assets->change_status($unit, array(
					'to_lifecycle' => 'in_maintenance',
					'reason' => 'Mulai diperbaiki: '.mb_substr(trim((string) ($input['keluhan'] ?? '')) ?: 'perlu perbaikan', 0, 400),
				), $user_id);
			}
		});
	}

	public function finish_repair($unit, array $input, $user_id)
	{
		$maintenance = $this->open_maintenance($unit);
		if ( ! $maintenance)
		{
			throw new DomainRuleException('Barang ini tidak sedang diperbaiki.', 409);
		}
		$condition = $this->valid_condition($input['kondisi'] ?? 'good');
		db_transaction(function () use ($unit, $maintenance, $condition, $input, $user_id) {
			$this->CI->assets->complete_maintenance($maintenance, array(
				'action_taken' => trim((string) ($input['tindakan'] ?? '')) ?: 'Perbaikan selesai',
				'condition_after' => $condition,
				'cost' => $input['biaya'] ?? '',
			), $user_id);
			$to_lifecycle = $unit->lifecycle_status === 'in_maintenance' ? 'active' : $unit->lifecycle_status;
			if ($to_lifecycle !== $unit->lifecycle_status OR $condition !== $unit->condition_status)
			{
				$this->CI->assets->change_status($unit, array(
					'to_lifecycle' => $to_lifecycle, 'to_condition' => $condition,
					'reason' => 'Perbaikan selesai: '.mb_substr(trim((string) ($input['tindakan'] ?? '')) ?: 'kondisi dinilai ulang', 0, 400),
				), $user_id);
			}
		});
	}

	/** Ubah kondisi dan/atau status (aktif, hilang, dihapuskan). */
	public function set_status($unit, array $input, $user_id)
	{
		$condition = $this->valid_condition($input['kondisi'] ?? $unit->condition_status, TRUE, $unit->condition_status);
		$status = (string) ($input['status'] ?? $unit->lifecycle_status);
		if ( ! in_array($status, array('active', 'lost', 'disposed', $unit->lifecycle_status), TRUE))
		{
			throw new DomainRuleException('Status tidak dikenal.', 422, array('status' => 'Tidak dikenal.'));
		}
		if ($status === $unit->lifecycle_status && $condition === $unit->condition_status)
		{
			throw new DomainRuleException('Tidak ada perubahan kondisi maupun status.', 409);
		}
		$default = $status === 'disposed' ? 'Barang dihapuskan dari inventaris'
			: ($status === 'lost' ? 'Barang dilaporkan hilang' : 'Kondisi diperbarui oleh pengelola');
		$this->CI->assets->change_status($unit, array(
			'to_lifecycle' => $status, 'to_condition' => $condition, 'reason' => $this->note($input, $default),
		), $user_id);
	}

	// ------------------------------------------------------------------
	// Riwayat
	// ------------------------------------------------------------------

	/**
	 * Satu timeline gabungan, terbaru di atas.
	 * @return array<int, array{at:string, type:string, icon:string, title:string, detail:string, actor:?string}>
	 */
	public function history($unit)
	{
		$db = $this->CI->db;
		$uid = (int) $unit->id;
		$names = array();
		$actor = function ($id) use (&$names, $db) {
			if ( ! $id)
			{
				return NULL;
			}
			if ( ! array_key_exists($id, $names))
			{
				$row = $db->select('display_name')->get_where('users', array('id' => (int) $id))->row();
				$names[$id] = $row ? $row->display_name : NULL;
			}
			return $names[$id];
		};
		$lifecycle = AssetService::LIFECYCLE;
		$conditions = AssetService::CONDITIONS;
		$out = array();

		foreach ($db->where('asset_unit_id', $uid)->get('asset_status_events')->result() as $e)
		{
			if ($e->event_type === 'movement_accepted')
			{
				continue; // ditampilkan dari tabel mutasi, lengkap dengan lokasi asal/tujuan
			}
			if ($e->event_type === 'created')
			{
				$out[] = $this->entry($e->effective_at, 'created', 'fa-plus-circle', 'Barang ditambahkan',
					'Kondisi awal: '.($conditions[$e->to_condition] ?? '-'), $actor($e->actor_user_id));
				continue;
			}
			if ($e->event_type === 'updated')
			{
				$out[] = $this->entry($e->effective_at, 'updated', 'fa-pen', 'Data diubah', $e->reason, $actor($e->actor_user_id));
				continue;
			}
			$changes = array();
			if ($e->from_lifecycle !== NULL && $e->from_lifecycle !== $e->to_lifecycle)
			{
				$changes[] = 'Status: '.($lifecycle[$e->from_lifecycle] ?? $e->from_lifecycle).' → '.($lifecycle[$e->to_lifecycle] ?? $e->to_lifecycle);
			}
			if ($e->from_condition !== NULL && $e->from_condition !== $e->to_condition)
			{
				$changes[] = 'Kondisi: '.($conditions[$e->from_condition] ?? $e->from_condition).' → '.($conditions[$e->to_condition] ?? $e->to_condition);
			}
			$out[] = $this->entry($e->effective_at, 'status', 'fa-exchange-alt', $changes ? implode(' · ', $changes) : 'Status dicatat',
				$e->reason, $actor($e->actor_user_id));
		}

		foreach ($db->select('m.*, lf.name AS from_name, lt.name AS to_name')->from('asset_movements m')
			->join('asset_locations lf', 'lf.id = m.from_location_id', 'left')->join('asset_locations lt', 'lt.id = m.to_location_id', 'left')
			->where('m.asset_unit_id', $uid)->where('m.status', 'accepted')->get()->result() as $m)
		{
			$out[] = $this->entry($m->updated_at, 'move', 'fa-truck', 'Pindah lokasi: '.($m->from_name ?: 'belum ditentukan').' → '.$m->to_name,
				$m->reason, $actor($m->accepted_by ?: $m->requested_by));
		}

		foreach ($db->where('asset_unit_id', $uid)->get('asset_loans')->result() as $l)
		{
			$borrower = '';
			try
			{
				$borrower = (string) $this->CI->crypto->decrypt($l->borrower_name_ciphertext);
			}
			catch (Exception $ex)
			{
				$borrower = '(nama tidak dapat dibaca)';
			}
			$out[] = $this->entry($l->checked_out_at, 'loan', 'fa-hand-holding', 'Dipinjam oleh '.$borrower.($l->borrower_unit ? ' ('.$l->borrower_unit.')' : ''),
				$l->purpose.($l->due_at ? ' · rencana kembali '.format_wib($l->due_at, 'date') : ''), $actor($l->issued_by));
			if ($l->returned_at)
			{
				$out[] = $this->entry($l->returned_at, 'return', 'fa-undo', 'Dikembalikan',
					'Kondisi saat kembali: '.($conditions[$l->return_condition] ?? $l->return_condition), $actor($l->received_by));
			}
		}

		foreach ($db->where('asset_unit_id', $uid)->get('asset_maintenance')->result() as $mt)
		{
			$out[] = $this->entry($mt->created_at, 'repair', 'fa-tools', 'Mulai diperbaiki'.($mt->vendor ? ' di '.$mt->vendor : ''),
				$mt->complaint, $actor($mt->created_by));
			if ($mt->completed_at)
			{
				$out[] = $this->entry($mt->updated_at, 'repair_done', 'fa-check-circle', 'Perbaikan selesai',
					trim((string) $mt->action_taken).($mt->condition_after ? ' · kondisi '.($conditions[$mt->condition_after] ?? $mt->condition_after) : ''), NULL);
			}
		}

		foreach ($db->where('asset_unit_id', $uid)->get('asset_qr_tokens')->result() as $q)
		{
			$out[] = $this->entry($q->issued_at, 'qr', 'fa-qrcode', 'QR dibuat (versi '.(int) $q->token_version.')', '', NULL);
			if ($q->revoked_at)
			{
				$out[] = $this->entry($q->revoked_at, 'qr', 'fa-ban', 'QR versi '.(int) $q->token_version.' tidak berlaku lagi', (string) $q->revoked_reason, NULL);
			}
		}

		foreach ($db->select('f.verified_at, f.observed_condition, f.existence_result, f.note, f.auditor_id')->from('asset_audit_findings f')
			->join('asset_audit_targets t', 't.id = f.audit_target_id')
			->where('t.asset_unit_id', $uid)->where('f.status', 'verified')->get()->result() as $a)
		{
			$out[] = $this->entry($a->verified_at, 'audit', 'fa-clipboard-check', 'Diperiksa saat audit fisik',
				'Kondisi: '.($conditions[$a->observed_condition] ?? $a->observed_condition).($a->note ? ' · '.$a->note : ''), $actor($a->auditor_id));
		}

		// Terbaru di atas; bila waktunya sama (satu detik), urutan logis peristiwa dipakai.
		$rank = array('created' => 0, 'qr' => 1, 'updated' => 2, 'move' => 3, 'loan' => 4, 'repair' => 5,
			'status' => 6, 'return' => 7, 'repair_done' => 8, 'audit' => 9);
		usort($out, function ($a, $b) use ($rank) {
			return array((string) $b['at'], $rank[$b['type']] ?? 5) <=> array((string) $a['at'], $rank[$a['type']] ?? 5);
		});
		return $out;
	}

	// ------------------------------------------------------------------
	// Bantuan
	// ------------------------------------------------------------------

	/** Kode barang berikutnya: INV-<tahun>-<urut 4 digit>, urutan diulang tiap tahun. */
	public function next_code($year = NULL)
	{
		$year = $year ?: (int) date('Y');
		$prefix = sprintf('INV-%04d-', $year);
		$last = $this->CI->db->select('asset_tag')->like('asset_tag', $prefix, 'after')
			->order_by('asset_tag', 'DESC')->limit(1)->get('asset_units')->row('asset_tag');
		$next = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;
		return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
	}

	protected function register_input(array $input, $existing = NULL)
	{
		$category_id = (int) ($input['kategori_id'] ?? 0);
		$new_category = trim((string) ($input['kategori_baru'] ?? ''));
		if ($new_category !== '')
		{
			$category_id = (int) $this->CI->assets->save_category(array(
				'code' => $this->code_from($new_category, 'KAT'), 'name' => $new_category, 'asset_class' => 'equipment',
			))->id;
		}
		$data = array(
			'name' => $input['nama'] ?? '',
			'category_id' => $category_id,
			'description' => $input['keterangan'] ?? '',
			'acquisition_year' => $input['tahun'] ?? '',
			'acquisition_source' => $input['sumber_dana'] ?? '',
			'acquisition_value' => $input['harga'] ?? '',
		);
		if ($existing)
		{
			// Field yang tidak ada di layar inventaris dipertahankan apa adanya.
			$data += array(
				'legacy_asset_code' => (string) $existing->legacy_asset_code,
				'source_volume_raw' => (string) $existing->source_volume_raw,
				'ownership_status' => (string) $existing->ownership_status,
				'source_locator' => (string) $existing->source_locator,
			);
			if ( ! array_key_exists('harga', $input))
			{
				$data['acquisition_value'] = $existing->acquisition_value === NULL ? '' : (string) $existing->acquisition_value;
				$data['acquisition_source'] = (string) $existing->acquisition_source;
			}
		}
		return $data;
	}

	/** Simpan register; pesan validasi dipetakan ke nama field form inventaris. */
	protected function save_register(array $data, $user_id, $public_id = NULL)
	{
		try
		{
			return $this->CI->assets->save_register($data, $user_id, $public_id);
		}
		catch (DomainRuleException $e)
		{
			$map = array('name' => 'nama', 'category_id' => 'kategori_id', 'acquisition_year' => 'tahun', 'acquisition_value' => 'harga');
			$errors = array();
			foreach ($e->errors as $field => $message)
			{
				$errors[$map[$field] ?? $field] = str_replace('Nama register', 'Nama barang', $message);
			}
			throw new DomainRuleException(str_replace('register aset', 'barang', $e->getMessage()), $e->http_status, $errors);
		}
	}

	/** Lokasi dari pilihan (location_id) atau ketikan baru (lokasi_baru). */
	protected function resolve_location(array $input)
	{
		$new = trim((string) ($input['lokasi_baru'] ?? ''));
		if ($new !== '')
		{
			$existing = $this->CI->db->where('name', mb_substr($new, 0, 180))->get('asset_locations')->row();
			if ($existing)
			{
				return (int) $existing->id;
			}
			return (int) $this->CI->assets->save_location(array('code' => $this->code_from($new, 'LOK'), 'name' => $new))->id;
		}
		$id = (int) ($input['location_id'] ?? 0);
		if ($id > 0 && $this->CI->db->where('id', $id)->count_all_results('asset_locations') > 0)
		{
			return $id;
		}
		return NULL;
	}

	/** Kode unik huruf besar dari nama, mis. "Balai Desa" -> LOK-BALAI-DESA. */
	protected function code_from($name, $prefix)
	{
		$slug = strtoupper(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));
		$base = mb_substr($prefix.'-'.($slug ?: 'BARU'), 0, 34);
		$code = $base;
		$table = $prefix === 'KAT' ? 'asset_categories' : 'asset_locations';
		for ($i = 2; $this->CI->db->where('code', $code)->count_all_results($table) > 0; $i++)
		{
			$code = $base.'-'.$i;
		}
		return $code;
	}

	protected function valid_condition($value, $allow_other = FALSE, $current = NULL)
	{
		$value = (string) $value;
		if (isset(self::CONDITIONS[$value]) OR ($allow_other && $value === $current))
		{
			return $value;
		}
		throw new DomainRuleException('Kondisi tidak dikenal.', 422, array('kondisi' => 'Pilih kondisi.'));
	}

	/** Catatan pengguna; bila terlalu pendek dipakai kalimat bawaan agar validasi lama terpenuhi. */
	protected function note(array $input, $default)
	{
		$note = trim((string) ($input['catatan'] ?? ''));
		if ($note === '')
		{
			return $default;
		}
		return mb_strlen($note) < 10 ? $default.': '.$note : $note;
	}

	protected function log_event($unit, $type, $reason, $user_id, array $extra = array())
	{
		$now = utc_now();
		db_must($this->CI->db->insert('asset_status_events', array_merge(array(
			'asset_unit_id' => (int) $unit->id,
			'event_type' => $type,
			'effective_at' => $now,
			'reason' => mb_substr($reason, 0, 500),
			'actor_user_id' => $user_id ? (int) $user_id : NULL,
			'created_at' => $now,
		), $extra)), 'asset_status_events.'.$type);
	}

	protected function entry($at, $type, $icon, $title, $detail, $actor)
	{
		return array('at' => (string) $at, 'type' => $type, 'icon' => $icon, 'title' => $title, 'detail' => (string) $detail, 'actor' => $actor);
	}
}
