<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Aset desa: register administrasi, unit fisik, dan QR (prompt-master 18.4).
 *
 * Register menyimpan identitas keuangan, unit menyimpan barang yang benar-benar ada.
 * Status lifecycle dan kondisi disimpan terpisah, dan setiap perubahan master ditulis
 * bersama historinya dalam satu transaksi.
 *
 * Token QR hanya ada sekali pada saat diterbitkan; yang disimpan hanyalah digestnya.
 */
class AssetService {

	/** @var CI_Controller */
	protected $CI;

	const LIFECYCLE = array(
		'draft' => 'Draft',
		'active' => 'Aktif',
		'in_maintenance' => 'Sedang pemeliharaan',
		'inactive' => 'Tidak aktif',
		'transferred' => 'Dipindahtangankan',
		'disposed' => 'Dihapuskan',
		'lost' => 'Hilang',
	);

	const CONDITIONS = array(
		'not_assessed' => 'Belum dinilai',
		'good' => 'Baik',
		'minor_damage' => 'Rusak ringan',
		'major_damage' => 'Rusak berat',
	);

	const OWNERSHIP = array(
		'owned' => 'Milik desa',
		'grant' => 'Hibah',
		'shared' => 'Pakai bersama',
		'unclear' => 'Belum jelas',
	);

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	// ------------------------------------------------------------------
	// Kategori dan lokasi
	// ------------------------------------------------------------------

	public function categories()
	{
		return $this->CI->db->where('active', 1)->order_by('code')->get('asset_categories')->result();
	}

	public function save_category(array $input)
	{
		$code = strtoupper(mb_substr(trim((string) ($input['code'] ?? '')), 0, 40));
		$name = mb_substr(trim((string) ($input['name'] ?? '')), 0, 180);
		if ($code === '' OR $name === '')
		{
			throw new DomainRuleException('Kode dan nama kategori wajib diisi.', 422,
				array('code' => 'Wajib diisi.', 'name' => 'Wajib diisi.'));
		}
		$now = utc_now();
		$existing = $this->CI->db->get_where('asset_categories', array('code' => $code))->row();
		$data = array(
			'name' => $name,
			'asset_class' => (string) ($input['asset_class'] ?? 'equipment'),
			'parent_id' => empty($input['parent_id']) ? NULL : (int) $input['parent_id'],
			'active' => array_key_exists('active', $input) && empty($input['active']) ? 0 : 1,
			'updated_at' => $now,
		);
		if ($existing)
		{
			db_must($this->CI->db->where('id', (int) $existing->id)->update('asset_categories', $data), 'asset_categories.update');
			return $this->CI->db->get_where('asset_categories', array('id' => (int) $existing->id))->row();
		}
		$data['code'] = $code;
		$data['created_at'] = $now;
		db_must($this->CI->db->insert('asset_categories', $data), 'asset_categories.insert');
		return $this->CI->db->get_where('asset_categories', array('id' => (int) $this->CI->db->insert_id()))->row();
	}

	public function locations()
	{
		return $this->CI->db->where('active', 1)->order_by('code')->get('asset_locations')->result();
	}

	public function save_location(array $input)
	{
		$code = strtoupper(mb_substr(trim((string) ($input['code'] ?? '')), 0, 40));
		$name = mb_substr(trim((string) ($input['name'] ?? '')), 0, 180);
		if ($code === '' OR $name === '')
		{
			throw new DomainRuleException('Kode dan nama lokasi wajib diisi.', 422,
				array('code' => 'Wajib diisi.', 'name' => 'Wajib diisi.'));
		}
		$now = utc_now();
		$existing = $this->CI->db->get_where('asset_locations', array('code' => $code))->row();
		$data = array(
			'name' => $name,
			'location_type' => (string) ($input['location_type'] ?? 'room'),
			'parent_id' => empty($input['parent_id']) ? NULL : (int) $input['parent_id'],
			'address' => mb_substr(trim((string) ($input['address'] ?? '')), 0, 400) ?: NULL,
			'is_sensitive' => empty($input['is_sensitive']) ? 0 : 1,
			'active' => array_key_exists('active', $input) && empty($input['active']) ? 0 : 1,
			'updated_at' => $now,
		);
		if ($existing)
		{
			db_must($this->CI->db->where('id', (int) $existing->id)->update('asset_locations', $data), 'asset_locations.update');
			return $this->CI->db->get_where('asset_locations', array('id' => (int) $existing->id))->row();
		}
		$data['code'] = $code;
		$data['created_at'] = $now;
		db_must($this->CI->db->insert('asset_locations', $data), 'asset_locations.insert');
		return $this->CI->db->get_where('asset_locations', array('id' => (int) $this->CI->db->insert_id()))->row();
	}

	// ------------------------------------------------------------------
	// Register aset
	// ------------------------------------------------------------------

	public function registers(array $filters = array())
	{
		$this->CI->db->select('r.*, c.name AS category_name')->from('asset_registers r')
			->join('asset_categories c', 'c.id = r.category_id');
		if ( ! empty($filters['category_id']))
		{
			$this->CI->db->where('r.category_id', (int) $filters['category_id']);
		}
		if ( ! empty($filters['q']))
		{
			$this->CI->db->group_start()->like('r.name', (string) $filters['q'])
				->or_like('r.legacy_asset_code', (string) $filters['q'])->group_end();
		}
		return $this->CI->db->order_by('r.id', 'DESC')->limit(200)->get()->result();
	}

	public function register($public_id)
	{
		return $this->CI->db->get_where('asset_registers', array('public_id' => (string) $public_id))->row();
	}

	public function save_register(array $input, $user_id, $public_id = NULL)
	{
		$errors = array();
		$name = mb_substr(trim((string) ($input['name'] ?? '')), 0, 220);
		$category_id = (int) ($input['category_id'] ?? 0);
		$legacy = mb_substr(trim((string) ($input['legacy_asset_code'] ?? '')), 0, 60);

		if ($name === '')
		{
			$errors['name'] = 'Nama register wajib diisi.';
		}
		if ( ! $this->CI->db->where('id', $category_id)->count_all_results('asset_categories'))
		{
			$errors['category_id'] = 'Kategori aset tidak ditemukan.';
		}
		$year = empty($input['acquisition_year']) ? NULL : (int) $input['acquisition_year'];
		if ($year !== NULL && ($year < 1900 OR $year > 2100))
		{
			$errors['acquisition_year'] = 'Tahun perolehan tidak wajar.';
		}
		$value = trim((string) ($input['acquisition_value'] ?? ''));
		if ($value !== '' && ! is_numeric(str_replace(array('.', ','), array('', '.'), $value)))
		{
			$errors['acquisition_value'] = 'Nilai perolehan harus berupa angka.';
		}
		if ($errors)
		{
			throw new DomainRuleException('Periksa kembali data register aset.', 422, $errors);
		}

		$now = utc_now();
		$data = array(
			'category_id' => $category_id,
			'name' => $name,
			'description' => mb_substr(trim((string) ($input['description'] ?? '')), 0, 1000) ?: NULL,
			'legacy_asset_code' => $legacy ?: NULL,
			'source_volume_raw' => mb_substr(trim((string) ($input['source_volume_raw'] ?? '')), 0, 120) ?: NULL,
			'acquisition_year' => $year,
			'acquisition_source' => mb_substr(trim((string) ($input['acquisition_source'] ?? '')), 0, 180) ?: NULL,
			'acquisition_value' => $value === '' ? NULL : round((float) str_replace(array('.', ','), array('', '.'), $value), 2),
			'ownership_status' => isset(self::OWNERSHIP[(string) ($input['ownership_status'] ?? '')])
				? (string) $input['ownership_status'] : 'owned',
			'source_locator' => mb_substr(trim((string) ($input['source_locator'] ?? '')), 0, 255) ?: NULL,
			'updated_at' => $now,
		);

		$existing = $public_id ? $this->register($public_id) : NULL;
		if ($existing)
		{
			if ($legacy !== '' && $legacy !== (string) $existing->legacy_asset_code
				&& $this->CI->db->where('legacy_asset_code', $legacy)->where('id <>', (int) $existing->id)
					->count_all_results('asset_registers') > 0)
			{
				throw new DomainRuleException('Kode barang lama itu sudah dipakai register lain.', 409,
					array('legacy_asset_code' => 'Sudah dipakai.'));
			}
			$data['version'] = (int) $existing->version + 1;
			$data['verification_status'] = 'unverified';
			db_must($this->CI->db->where('id', (int) $existing->id)->update('asset_registers', $data), 'asset_registers.update');
			$this->CI->audit->log('assets.register_saved', 'asset_register', $existing->public_id,
				array('name' => $name), FALSE, 'assets');
			return $this->register($existing->public_id);
		}

		if ($legacy !== '' && $this->CI->db->where('legacy_asset_code', $legacy)->count_all_results('asset_registers') > 0)
		{
			throw new DomainRuleException('Kode barang lama itu sudah dipakai register lain.', 409,
				array('legacy_asset_code' => 'Sudah dipakai.'));
		}
		$data['public_id'] = $this->CI->crypto->public_id();
		$data['lifecycle_status'] = 'draft';
		$data['verification_status'] = 'unverified';
		$data['created_by'] = $user_id ? (int) $user_id : NULL;
		$data['created_at'] = $now;
		db_must($this->CI->db->insert('asset_registers', $data), 'asset_registers.insert');
		$this->CI->audit->log('assets.register_created', 'asset_register', $data['public_id'],
			array('name' => $name), FALSE, 'assets');
		return $this->register($data['public_id']);
	}

	public function verify_register($register, $verified, $user_id)
	{
		db_must($this->CI->db->where('id', (int) $register->id)->update('asset_registers', array(
			'verification_status' => $verified ? 'verified' : 'unverified',
			'lifecycle_status' => ($verified && $register->lifecycle_status === 'draft') ? 'active' : $register->lifecycle_status,
			'updated_at' => utc_now(),
		)), 'asset_registers.verify');
		$this->CI->audit->log($verified ? 'assets.register_verified' : 'assets.register_unverified',
			'asset_register', $register->public_id, array('name' => $register->name), FALSE, 'assets');
	}

	// ------------------------------------------------------------------
	// Unit fisik
	// ------------------------------------------------------------------

	public function units($register)
	{
		return $this->CI->db->select('u.*, l.name AS location_name, l.is_sensitive AS location_sensitive')
			->from('asset_units u')->join('asset_locations l', 'l.id = u.location_id', 'left')
			->where('u.register_id', (int) $register->id)
			->order_by('u.unit_sequence')->order_by('u.id')->get()->result();
	}

	public function unit($public_id)
	{
		return $this->CI->db->get_where('asset_units', array('public_id' => (string) $public_id))->row();
	}

	public function unit_by_tag($asset_tag)
	{
		return $this->CI->db->get_where('asset_units', array('asset_tag' => (string) $asset_tag))->row();
	}

	/**
	 * Buat satu unit fisik. Pemecahan banyak unit dilakukan lewat `propose_units()` yang
	 * meminta alasan, bukan dengan mengurai angka volume secara otomatis.
	 */
	public function create_unit($register, array $input, $user_id)
	{
		$tag = strtoupper(mb_substr(trim((string) ($input['asset_tag'] ?? '')), 0, 60));
		if ($tag === '')
		{
			throw new DomainRuleException('Asset tag wajib diisi.', 422, array('asset_tag' => 'Wajib diisi.'));
		}
		if ($this->CI->db->where('asset_tag', $tag)->count_all_results('asset_units') > 0)
		{
			throw new DomainRuleException('Asset tag itu sudah dipakai unit lain.', 409, array('asset_tag' => 'Sudah dipakai.'));
		}
		$now = utc_now();
		$serial = trim((string) ($input['serial_number'] ?? ''));
		$data = array(
			'public_id' => $this->CI->crypto->public_id(),
			'register_id' => (int) $register->id,
			'asset_tag' => $tag,
			'unit_sequence' => empty($input['unit_sequence']) ? NULL : (int) $input['unit_sequence'],
			// Nomor seri dapat menjadi petunjuk pencurian; disimpan terenkripsi.
			'serial_number_ciphertext' => $serial === '' ? NULL : $this->CI->crypto->encrypt(mb_substr($serial, 0, 120)),
			'brand' => mb_substr(trim((string) ($input['brand'] ?? '')), 0, 120) ?: NULL,
			'model' => mb_substr(trim((string) ($input['model'] ?? '')), 0, 120) ?: NULL,
			'location_id' => empty($input['location_id']) ? NULL : (int) $input['location_id'],
			'custodian_unit_id' => empty($input['custodian_unit_id']) ? NULL : (int) $input['custodian_unit_id'],
			'custodian_user_id' => empty($input['custodian_user_id']) ? NULL : (int) $input['custodian_user_id'],
			'lifecycle_status' => 'draft',
			'condition_status' => 'not_assessed',
			'public_note' => mb_substr(trim((string) ($input['public_note'] ?? '')), 0, 500) ?: NULL,
			'created_at' => $now,
			'updated_at' => $now,
		);
		db_must($this->CI->db->insert('asset_units', $data), 'asset_units.insert');
		$this->CI->audit->log('assets.unit_created', 'asset_unit', $data['public_id'],
			array('tag' => $tag, 'register' => $register->public_id), FALSE, 'assets');
		return $this->unit($data['public_id']);
	}

	/**
	 * Usulan pemecahan register menjadi beberapa unit. Wajib disertai alasan agar keputusan
	 * pemecahan dapat ditelusuri; angka volume sumber tidak pernah dipecah otomatis.
	 */
	public function propose_units($register, $count, $reason, $user_id, $tag_prefix = NULL)
	{
		$count = (int) $count;
		if ($count < 1 OR $count > 200)
		{
			throw new DomainRuleException('Jumlah unit harus antara 1 dan 200.', 422, array('count' => 'Di luar rentang.'));
		}
		if (mb_strlen(trim((string) $reason)) < 10)
		{
			throw new DomainRuleException('Tuliskan alasan pemecahan unit minimal 10 karakter.', 422,
				array('reason' => 'Terlalu pendek.'));
		}
		$prefix = strtoupper(mb_substr(trim((string) ($tag_prefix ?: $register->legacy_asset_code ?: 'ASET')), 0, 30));
		$start = (int) $this->CI->db->select_max('unit_sequence')->where('register_id', (int) $register->id)
			->get('asset_units')->row('unit_sequence');

		$created = array();
		db_transaction(function () use ($register, $count, $prefix, $start, $user_id, &$created) {
			for ($i = 1; $i <= $count; $i++)
			{
				$sequence = $start + $i;
				$created[] = $this->create_unit($register, array(
					'asset_tag' => $prefix.'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
					'unit_sequence' => $sequence,
				), $user_id);
			}
		});
		$this->CI->audit->log('assets.units_proposed', 'asset_register', $register->public_id,
			array('count' => $count, 'reason' => mb_substr((string) $reason, 0, 200)), FALSE, 'assets');
		return $created;
	}

	/**
	 * Ubah lifecycle dan/atau kondisi unit. Master dan histori ditulis dalam satu transaksi,
	 * dan alasan wajib diisi sehingga penonaktifan selalu dapat ditelusuri.
	 */
	public function change_status($unit, array $input, $user_id)
	{
		$to_lifecycle = (string) ($input['to_lifecycle'] ?? $unit->lifecycle_status);
		$to_condition = (string) ($input['to_condition'] ?? $unit->condition_status);
		$reason = trim((string) ($input['reason'] ?? ''));

		if ( ! isset(self::LIFECYCLE[$to_lifecycle]))
		{
			throw new DomainRuleException('Status lifecycle tidak dikenal.', 422, array('to_lifecycle' => 'Tidak dikenal.'));
		}
		if ( ! isset(self::CONDITIONS[$to_condition]))
		{
			throw new DomainRuleException('Kondisi tidak dikenal.', 422, array('to_condition' => 'Tidak dikenal.'));
		}
		if (mb_strlen($reason) < 10)
		{
			throw new DomainRuleException('Alasan perubahan status wajib diisi minimal 10 karakter.', 422,
				array('reason' => 'Terlalu pendek.'));
		}
		if ($to_lifecycle === $unit->lifecycle_status && $to_condition === $unit->condition_status)
		{
			throw new DomainRuleException('Tidak ada perubahan status maupun kondisi.', 409);
		}

		$now = utc_now();
		$effective = trim((string) ($input['effective_at'] ?? ''));
		$effective = preg_match('/^\d{4}-\d{2}-\d{2}$/', $effective) ? $effective.' 00:00:00' : $now;

		db_transaction(function () use ($unit, $to_lifecycle, $to_condition, $reason, $user_id, $now, $effective) {
			db_must($this->CI->db->insert('asset_status_events', array(
				'asset_unit_id' => (int) $unit->id,
				'event_type' => 'status_change',
				'from_lifecycle' => $unit->lifecycle_status,
				'to_lifecycle' => $to_lifecycle,
				'from_condition' => $unit->condition_status,
				'to_condition' => $to_condition,
				'effective_at' => $effective,
				'reason' => mb_substr($reason, 0, 500),
				'actor_user_id' => $user_id ? (int) $user_id : NULL,
				'created_at' => $now,
			)), 'asset_status_events.insert');
			db_must($this->CI->db->where('id', (int) $unit->id)->update('asset_units', array(
				'lifecycle_status' => $to_lifecycle,
				'condition_status' => $to_condition,
				'activated_at' => ($to_lifecycle === 'active' && ! $unit->activated_at) ? $now : $unit->activated_at,
				'deactivated_at' => in_array($to_lifecycle, array('inactive', 'disposed', 'transferred', 'lost'), TRUE)
					? $now : NULL,
				'version' => (int) $unit->version + 1,
				'updated_at' => $now,
			)), 'asset_units.status');
		});

		$this->CI->audit->log('assets.status_changed', 'asset_unit', $unit->public_id, array(
			'to_lifecycle' => $to_lifecycle, 'to_condition' => $to_condition,
			'reason' => mb_substr($reason, 0, 200),
		), FALSE, 'assets');
		return $this->unit($unit->public_id);
	}

	public function status_events($unit)
	{
		return $this->CI->db->where('asset_unit_id', (int) $unit->id)
			->order_by('effective_at', 'DESC')->order_by('id', 'DESC')->get('asset_status_events')->result();
	}

	// ------------------------------------------------------------------
	// Mutasi lokasi
	// ------------------------------------------------------------------

	public function movements($unit)
	{
		return $this->CI->db->where('asset_unit_id', (int) $unit->id)
			->order_by('id', 'DESC')->get('asset_movements')->result();
	}

	/** Mengajukan mutasi TIDAK mengubah lokasi; lokasi baru berlaku setelah diterima. */
	public function request_movement($unit, array $input, $user_id)
	{
		$to_location = (int) ($input['to_location_id'] ?? 0);
		$reason = trim((string) ($input['reason'] ?? ''));
		if ( ! $this->CI->db->where('id', $to_location)->count_all_results('asset_locations'))
		{
			throw new DomainRuleException('Lokasi tujuan tidak ditemukan.', 422, array('to_location_id' => 'Tidak ditemukan.'));
		}
		if (mb_strlen($reason) < 10)
		{
			throw new DomainRuleException('Alasan mutasi wajib diisi minimal 10 karakter.', 422, array('reason' => 'Terlalu pendek.'));
		}
		if ($this->CI->db->where('asset_unit_id', (int) $unit->id)->where_in('status', array('requested', 'approved'))
			->count_all_results('asset_movements') > 0)
		{
			throw new DomainRuleException('Unit itu masih punya mutasi yang belum selesai.', 409);
		}

		$now = utc_now();
		$public_id = $this->CI->crypto->public_id();
		db_must($this->CI->db->insert('asset_movements', array(
			'public_id' => $public_id,
			'asset_unit_id' => (int) $unit->id,
			'from_location_id' => $unit->location_id ? (int) $unit->location_id : NULL,
			'to_location_id' => $to_location,
			'from_custodian_unit_id' => $unit->custodian_unit_id ? (int) $unit->custodian_unit_id : NULL,
			'to_custodian_unit_id' => empty($input['to_custodian_unit_id']) ? NULL : (int) $input['to_custodian_unit_id'],
			'moved_at' => $now,
			'reason' => mb_substr($reason, 0, 500),
			'status' => 'requested',
			'requested_by' => $user_id ? (int) $user_id : NULL,
			'created_at' => $now,
			'updated_at' => $now,
		)), 'asset_movements.insert');
		$this->CI->audit->log('assets.movement_requested', 'asset_movement', $public_id,
			array('unit' => $unit->public_id), FALSE, 'assets');
		return $this->CI->db->get_where('asset_movements', array('public_id' => $public_id))->row();
	}

	public function accept_movement($movement, $user_id)
	{
		if ($movement->status === 'accepted')
		{
			throw new DomainRuleException('Mutasi itu sudah diterima.', 409);
		}
		$unit = $this->CI->db->get_where('asset_units', array('id' => (int) $movement->asset_unit_id))->row();
		$now = utc_now();

		db_transaction(function () use ($movement, $unit, $user_id, $now) {
			db_must($this->CI->db->where('id', (int) $movement->id)->update('asset_movements', array(
				'status' => 'accepted', 'accepted_by' => $user_id ? (int) $user_id : NULL, 'updated_at' => $now,
			)), 'asset_movements.accept');
			db_must($this->CI->db->where('id', (int) $unit->id)->update('asset_units', array(
				'location_id' => (int) $movement->to_location_id,
				'custodian_unit_id' => $movement->to_custodian_unit_id ? (int) $movement->to_custodian_unit_id : $unit->custodian_unit_id,
				'version' => (int) $unit->version + 1,
				'updated_at' => $now,
			)), 'asset_units.location');
			db_must($this->CI->db->insert('asset_status_events', array(
				'asset_unit_id' => (int) $unit->id,
				'event_type' => 'movement_accepted',
				'effective_at' => $now,
				'reason' => mb_substr((string) $movement->reason, 0, 500),
				'actor_user_id' => $user_id ? (int) $user_id : NULL,
				'created_at' => $now,
			)), 'asset_status_events.movement');
		});

		$this->CI->audit->log('assets.movement_accepted', 'asset_movement', $movement->public_id,
			array('unit' => $unit->public_id), FALSE, 'assets');
	}

	// ------------------------------------------------------------------
	// Peminjaman dan pemeliharaan
	// ------------------------------------------------------------------

	public function loans($unit)
	{
		return $this->CI->db->where('asset_unit_id', (int) $unit->id)->order_by('id', 'DESC')->get('asset_loans')->result();
	}

	/** Peminjaman tidak mengubah kepemilikan; nama peminjam disimpan terenkripsi. */
	public function checkout($unit, array $input, $user_id)
	{
		$borrower = trim((string) ($input['borrower_name'] ?? ''));
		$purpose = trim((string) ($input['purpose'] ?? ''));
		if ($borrower === '' OR $purpose === '')
		{
			throw new DomainRuleException('Nama peminjam dan tujuan wajib diisi.', 422,
				array('borrower_name' => 'Wajib diisi.', 'purpose' => 'Wajib diisi.'));
		}
		if ($this->CI->db->where('asset_unit_id', (int) $unit->id)->where('status', 'out')
			->count_all_results('asset_loans') > 0)
		{
			throw new DomainRuleException('Unit itu sedang dipinjam dan belum dikembalikan.', 409);
		}
		$now = utc_now();
		$public_id = $this->CI->crypto->public_id();
		db_must($this->CI->db->insert('asset_loans', array(
			'public_id' => $public_id,
			'asset_unit_id' => (int) $unit->id,
			'borrower_name_ciphertext' => $this->CI->crypto->encrypt(mb_substr($borrower, 0, 180)),
			'borrower_unit' => mb_substr(trim((string) ($input['borrower_unit'] ?? '')), 0, 180) ?: NULL,
			'purpose' => mb_substr($purpose, 0, 500),
			'checked_out_at' => $now,
			'due_at' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($input['due_at'] ?? '')) ? $input['due_at'].' 00:00:00' : NULL,
			'checkout_condition' => isset(self::CONDITIONS[(string) ($input['checkout_condition'] ?? '')])
				? (string) $input['checkout_condition'] : $unit->condition_status,
			'status' => 'out',
			'issued_by' => $user_id ? (int) $user_id : NULL,
			'created_at' => $now,
			'updated_at' => $now,
		)), 'asset_loans.insert');
		$this->CI->audit->log('assets.loan_issued', 'asset_loan', $public_id,
			array('unit' => $unit->public_id), FALSE, 'assets');
		return $this->CI->db->get_where('asset_loans', array('public_id' => $public_id))->row();
	}

	public function return_loan($loan, array $input, $user_id)
	{
		if ($loan->status !== 'out')
		{
			throw new DomainRuleException('Peminjaman itu sudah selesai.', 409);
		}
		$condition = (string) ($input['return_condition'] ?? '');
		if ( ! isset(self::CONDITIONS[$condition]))
		{
			throw new DomainRuleException('Kondisi kembali wajib dipilih.', 422, array('return_condition' => 'Wajib dipilih.'));
		}
		$now = utc_now();
		db_must($this->CI->db->where('id', (int) $loan->id)->update('asset_loans', array(
			'status' => 'returned', 'returned_at' => $now, 'return_condition' => $condition,
			'received_by' => $user_id ? (int) $user_id : NULL, 'updated_at' => $now,
		)), 'asset_loans.return');
		$this->CI->audit->log('assets.loan_returned', 'asset_loan', $loan->public_id,
			array('condition' => $condition), FALSE, 'assets');
	}

	public function maintenances($unit)
	{
		return $this->CI->db->where('asset_unit_id', (int) $unit->id)->order_by('id', 'DESC')->get('asset_maintenance')->result();
	}

	public function create_maintenance($unit, array $input, $user_id)
	{
		$complaint = trim((string) ($input['complaint'] ?? ''));
		if ($complaint === '')
		{
			throw new DomainRuleException('Keluhan wajib diisi.', 422, array('complaint' => 'Wajib diisi.'));
		}
		$now = utc_now();
		$public_id = $this->CI->crypto->public_id();
		db_must($this->CI->db->insert('asset_maintenance', array(
			'public_id' => $public_id,
			'asset_unit_id' => (int) $unit->id,
			'complaint' => mb_substr($complaint, 0, 600),
			'vendor' => mb_substr(trim((string) ($input['vendor'] ?? '')), 0, 180) ?: NULL,
			'planned_at' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($input['planned_at'] ?? '')) ? $input['planned_at'] : NULL,
			'status' => 'planned',
			'condition_before' => $unit->condition_status,
			'created_by' => $user_id ? (int) $user_id : NULL,
			'created_at' => $now,
			'updated_at' => $now,
		)), 'asset_maintenance.insert');
		$this->CI->audit->log('assets.maintenance_created', 'asset_maintenance', $public_id,
			array('unit' => $unit->public_id), FALSE, 'assets');
		return $this->CI->db->get_where('asset_maintenance', array('public_id' => $public_id))->row();
	}

	/**
	 * Menyelesaikan pemeliharaan TIDAK otomatis membuat kondisi menjadi baik; kondisi
	 * sesudahnya harus dinilai dan dicatat terpisah lewat `change_status()`.
	 */
	public function complete_maintenance($maintenance, array $input, $user_id)
	{
		$action = trim((string) ($input['action_taken'] ?? ''));
		if ($action === '')
		{
			throw new DomainRuleException('Tindakan yang dilakukan wajib diisi.', 422, array('action_taken' => 'Wajib diisi.'));
		}
		$condition_after = (string) ($input['condition_after'] ?? '');
		if ( ! isset(self::CONDITIONS[$condition_after]))
		{
			throw new DomainRuleException('Kondisi setelah pemeliharaan wajib dinilai.', 422,
				array('condition_after' => 'Wajib dipilih.'));
		}
		$now = utc_now();
		db_must($this->CI->db->where('id', (int) $maintenance->id)->update('asset_maintenance', array(
			'action_taken' => mb_substr($action, 0, 600),
			'condition_after' => $condition_after,
			'completed_at' => date('Y-m-d'),
			'status' => 'completed',
			'cost' => trim((string) ($input['cost'] ?? '')) === '' ? NULL : round((float) str_replace(',', '.', (string) $input['cost']), 2),
			'updated_at' => $now,
		)), 'asset_maintenance.complete');
		$this->CI->audit->log('assets.maintenance_completed', 'asset_maintenance', $maintenance->public_id,
			array('condition_after' => $condition_after), FALSE, 'assets');
	}

	// ------------------------------------------------------------------
	// QR dan label
	// ------------------------------------------------------------------

	/**
	 * Terbitkan token QR baru untuk satu unit. Token asli dikembalikan SEKALI dan tidak
	 * pernah disimpan; yang disimpan hanya digestnya. Token lama otomatis dicabut.
	 */
	public function issue_token($unit, $user_id, $reason = 'Penerbitan token baru')
	{
		$token = bin2hex(random_bytes(16));
		$now = utc_now();
		$version = (int) $this->CI->db->select_max('token_version')->where('asset_unit_id', (int) $unit->id)
			->get('asset_qr_tokens')->row('token_version') + 1;

		db_transaction(function () use ($unit, $token, $now, $version, $reason) {
			$this->CI->db->where('asset_unit_id', (int) $unit->id)->where('status', 'active')
				->update('asset_qr_tokens', array(
					'status' => 'revoked', 'revoked_at' => $now,
					'revoked_reason' => mb_substr((string) $reason, 0, 255),
				));
			db_must($this->CI->db->insert('asset_qr_tokens', array(
				'asset_unit_id' => (int) $unit->id,
				'token_digest' => $this->token_digest($token),
				'token_version' => $version,
				'status' => 'active',
				'issued_at' => $now,
			)), 'asset_qr_tokens.insert');
		});

		// Token asli sengaja TIDAK masuk audit log.
		$this->CI->audit->log('assets.qr_issued', 'asset_unit', $unit->public_id,
			array('token_version' => $version), FALSE, 'public_asset_qr');
		return $token;
	}

	public function revoke_token($unit, $reason, $user_id)
	{
		$affected = $this->CI->db->where('asset_unit_id', (int) $unit->id)->where('status', 'active')
			->update('asset_qr_tokens', array(
				'status' => 'revoked', 'revoked_at' => utc_now(),
				'revoked_reason' => mb_substr(trim((string) $reason), 0, 255) ?: 'Dicabut pengelola',
			));
		$this->CI->audit->log('assets.qr_revoked', 'asset_unit', $unit->public_id,
			array('reason' => mb_substr((string) $reason, 0, 200)), FALSE, 'public_asset_qr');
		return $affected;
	}

	public function active_token($unit)
	{
		return $this->CI->db->where('asset_unit_id', (int) $unit->id)->where('status', 'active')
			->order_by('id', 'DESC')->limit(1)->get('asset_qr_tokens')->row();
	}

	protected function token_digest($token)
	{
		return hash('sha256', 'asset-qr|'.(string) $token);
	}

	/**
	 * Cari unit dari token hasil pindai.
	 * @return array{status: string, unit: object|null} status: ok, revoked, unknown
	 */
	public function resolve_token($token)
	{
		$token = (string) $token;
		if ( ! preg_match('/^[a-f0-9]{32}$/', $token))
		{
			return array('status' => 'unknown', 'unit' => NULL);
		}
		$row = $this->CI->db->get_where('asset_qr_tokens', array('token_digest' => $this->token_digest($token)))->row();
		if ( ! $row)
		{
			return array('status' => 'unknown', 'unit' => NULL);
		}
		if ($row->status !== 'active')
		{
			return array('status' => 'revoked', 'unit' => NULL);
		}
		$this->CI->db->where('id', (int) $row->id)->update('asset_qr_tokens', array('last_scanned_at' => utc_now()));
		return array('status' => 'ok', 'unit' => $this->CI->db->get_where('asset_units', array('id' => (int) $row->asset_unit_id))->row());
	}

	/**
	 * Data untuk halaman QR publik. Harga, dokumen, nomor seri, nama penanggung jawab,
	 * lokasi sensitif, dan catatan internal TIDAK pernah masuk ke sini.
	 */
	public function public_unit_view($unit)
	{
		$register = $this->CI->db->select('r.name, r.acquisition_year, r.public_id, c.name AS category_name')
			->from('asset_registers r')->join('asset_categories c', 'c.id = r.category_id')
			->where('r.id', (int) $unit->register_id)->get()->row();
		$location = $unit->location_id
			? $this->CI->db->get_where('asset_locations', array('id' => (int) $unit->location_id))->row()
			: NULL;
		$audit = $this->CI->db->select('f.verified_at, f.observed_condition')
			->from('asset_audit_findings f')
			->join('asset_audit_targets t', 't.id = f.audit_target_id')
			->where('t.asset_unit_id', (int) $unit->id)->where('f.status', 'verified')
			->order_by('f.verified_at', 'DESC')->limit(1)->get()->row();

		return array(
			'asset_tag' => $unit->asset_tag,
			'name' => $register ? $register->name : '',
			'category_name' => $register ? $register->category_name : '',
			'brand' => $unit->brand,
			'model' => $unit->model,
			'acquisition_year' => $register && $register->acquisition_year ? (int) $register->acquisition_year : NULL,
			'owner_unit' => 'Pemerintah Desa Cihawuk',
			// Lokasi sensitif hanya ditampilkan sebagai keterangan umum.
			'location_name' => ($location && (int) $location->is_sensitive === 0) ? $location->name : NULL,
			'lifecycle_status' => $unit->lifecycle_status,
			'lifecycle_label' => self::LIFECYCLE[$unit->lifecycle_status] ?? $unit->lifecycle_status,
			'condition_label' => self::CONDITIONS[$unit->condition_status] ?? $unit->condition_status,
			'verified' => $audit !== NULL,
			'verified_at' => $audit ? $audit->verified_at : NULL,
			'public_note' => $unit->public_note,
			'media_id' => $unit->primary_media_id ? (int) $unit->primary_media_id : NULL,
		);
	}

	// ------------------------------------------------------------------
	// Batch label
	// ------------------------------------------------------------------

	public function label_batches($limit = 20)
	{
		return $this->CI->db->select('b.*, u.display_name AS requester')
			->from('asset_label_batches b')->join('users u', 'u.id = b.requested_by', 'left')
			->order_by('b.id', 'DESC')->limit((int) $limit)->get()->result();
	}

	/** Hanya unit yang punya token aktif yang dapat masuk batch label. */
	public function create_label_batch(array $unit_public_ids, array $input, $user_id)
	{
		$units = array();
		foreach ($unit_public_ids as $public_id)
		{
			$unit = $this->unit((string) $public_id);
			if ( ! $unit)
			{
				continue;
			}
			$token = $this->active_token($unit);
			if ( ! $token)
			{
				throw new DomainRuleException('Unit '.$unit->asset_tag.' belum punya token QR aktif.', 409);
			}
			$units[] = array('unit' => $unit, 'token' => $token);
		}
		if (empty($units))
		{
			throw new DomainRuleException('Pilih minimal satu unit yang sudah punya token QR.', 422);
		}

		$now = utc_now();
		$public_id = $this->CI->crypto->public_id();
		db_transaction(function () use ($units, $input, $user_id, $now, $public_id) {
			db_must($this->CI->db->insert('asset_label_batches', array(
				'public_id' => $public_id,
				'template_code' => (string) ($input['template_code'] ?? 'label.a4_24'),
				'requested_by' => $user_id ? (int) $user_id : NULL,
				'status' => 'ready',
				'item_count' => count($units),
				'start_offset' => (int) ($input['start_offset'] ?? 0),
				'reprint_reason' => mb_substr(trim((string) ($input['reprint_reason'] ?? '')), 0, 255) ?: NULL,
				'created_at' => $now,
			)), 'asset_label_batches.insert');
			$batch_id = (int) $this->CI->db->insert_id();
			$order = 0;
			foreach ($units as $row)
			{
				$order += 1;
				db_must($this->CI->db->insert('asset_label_batch_items', array(
					'batch_id' => $batch_id,
					'asset_unit_id' => (int) $row['unit']->id,
					'qr_token_id' => (int) $row['token']->id,
					'copy_count' => max(1, (int) ($input['copy_count'] ?? 1)),
					'position_order' => $order,
				)), 'asset_label_batch_items.insert');
			}
		});

		$this->CI->audit->log('assets.labels_prepared', 'asset_label_batch', $public_id,
			array('item_count' => count($units)), FALSE, 'assets');
		return $this->CI->db->get_where('asset_label_batches', array('public_id' => $public_id))->row();
	}

	public function mark_batch_printed($batch, $user_id)
	{
		db_must($this->CI->db->where('id', (int) $batch->id)->update('asset_label_batches', array(
			'status' => 'printed', 'printed_at' => utc_now(),
		)), 'asset_label_batches.printed');
		$this->CI->audit->log('assets.labels_printed', 'asset_label_batch', $batch->public_id, array(), FALSE, 'assets');
	}

	// ------------------------------------------------------------------
	// Laporan ringkas
	// ------------------------------------------------------------------

	/** Angka untuk dashboard aset; tidak memuat nilai rupiah. */
	public function summary()
	{
		$by_condition = array();
		foreach ($this->CI->db->select('condition_status, COUNT(*) AS total')
			->group_by('condition_status')->get('asset_units')->result() as $row)
		{
			$by_condition[$row->condition_status] = (int) $row->total;
		}
		$without_qr = (int) $this->CI->db->query('SELECT COUNT(*) AS total FROM asset_units u
			WHERE NOT EXISTS (SELECT 1 FROM asset_qr_tokens q WHERE q.asset_unit_id = u.id AND q.status = "active")')
			->row('total');

		return array(
			'registers' => (int) $this->CI->db->count_all('asset_registers'),
			'units' => (int) $this->CI->db->count_all('asset_units'),
			'without_qr' => $without_qr,
			'by_condition' => $by_condition,
			'incomplete' => (int) $this->CI->db->where('verification_status', 'unverified')
				->count_all_results('asset_registers'),
		);
	}
}
