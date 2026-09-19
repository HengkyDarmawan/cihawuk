<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Gudang barang persediaan (prompt-master 18.5).
 *
 * Saldo SELALU dihitung dari `inventory_ledger` yang append-only; tidak ada kolom saldo yang
 * dapat disunting. Pengeluaran memakai row lock pada baris item sehingga dua pengeluaran
 * bersamaan atas stok terakhir tidak dapat sama-sama berhasil.
 *
 * Konversi satuan memakai numerator dan denominator bilangan bulat, dan hasil yang tidak
 * bulat ditolak agar tidak muncul saldo pecahan yang tidak sah.
 */
class WarehouseService {

	/** @var CI_Controller */
	protected $CI;

	const TYPES = array(
		'receipt' => 'Penerimaan',
		'issue' => 'Pengeluaran',
		'transfer' => 'Transfer antarlokasi',
		'return' => 'Retur',
		'adjustment' => 'Penyesuaian',
	);

	const STATUSES = array(
		'draft' => 'Draft',
		'approved' => 'Disetujui',
		'posted' => 'Diposting',
		'cancelled' => 'Dibatalkan',
	);

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	public function locations()
	{
		return $this->CI->db->where('active', 1)->order_by('code')->get('warehouse_locations')->result();
	}

	public function save_location(array $input)
	{
		$code = strtoupper(mb_substr(trim((string) ($input['code'] ?? '')), 0, 40));
		$name = mb_substr(trim((string) ($input['name'] ?? '')), 0, 180);
		if ($code === '' OR $name === '')
		{
			throw new DomainRuleException('Kode dan nama lokasi gudang wajib diisi.', 422,
				array('code' => 'Wajib diisi.', 'name' => 'Wajib diisi.'));
		}
		$now = utc_now();
		$existing = $this->CI->db->get_where('warehouse_locations', array('code' => $code))->row();
		if ($existing)
		{
			db_must($this->CI->db->where('id', (int) $existing->id)->update('warehouse_locations', array(
				'name' => $name, 'active' => 1, 'updated_at' => $now,
			)), 'warehouse_locations.update');
			return $this->CI->db->get_where('warehouse_locations', array('id' => (int) $existing->id))->row();
		}
		db_must($this->CI->db->insert('warehouse_locations', array(
			'code' => $code, 'name' => $name, 'active' => 1, 'created_at' => $now, 'updated_at' => $now,
		)), 'warehouse_locations.insert');
		return $this->CI->db->get_where('warehouse_locations', array('id' => (int) $this->CI->db->insert_id()))->row();
	}

	public function items(array $filters = array())
	{
		$this->CI->db->where('active', 1);
		if ( ! empty($filters['q']))
		{
			$this->CI->db->group_start()->like('name', (string) $filters['q'])
				->or_like('sku', (string) $filters['q'])->group_end();
		}
		return $this->CI->db->order_by('name')->get('inventory_items')->result();
	}

	public function item($public_id)
	{
		return $this->CI->db->get_where('inventory_items', array('public_id' => (string) $public_id))->row();
	}

	public function save_item(array $input, $user_id, $public_id = NULL)
	{
		$errors = array();
		$sku = strtoupper(mb_substr(trim((string) ($input['sku'] ?? '')), 0, 60));
		$name = mb_substr(trim((string) ($input['name'] ?? '')), 0, 200);
		$base_unit = mb_substr(trim((string) ($input['base_unit'] ?? '')), 0, 30);

		if ($sku === '')
		{
			$errors['sku'] = 'SKU wajib diisi.';
		}
		if ($name === '')
		{
			$errors['name'] = 'Nama barang wajib diisi.';
		}
		if ($base_unit === '')
		{
			$errors['base_unit'] = 'Satuan dasar wajib diisi.';
		}
		if ($errors)
		{
			throw new DomainRuleException('Periksa kembali data barang.', 422, $errors);
		}

		$now = utc_now();
		$data = array(
			'name' => $name,
			'category' => mb_substr(trim((string) ($input['category'] ?? '')), 0, 60) ?: NULL,
			'base_unit' => $base_unit,
			'minimum_stock' => trim((string) ($input['minimum_stock'] ?? '')) === ''
				? NULL : round((float) str_replace(',', '.', (string) $input['minimum_stock']), 3),
			'track_batch' => empty($input['track_batch']) ? 0 : 1,
			'active' => array_key_exists('active', $input) && empty($input['active']) ? 0 : 1,
			'updated_at' => $now,
		);

		$existing = $public_id ? $this->item($public_id) : NULL;
		if ($existing)
		{
			$data['version'] = (int) $existing->version + 1;
			db_must($this->CI->db->where('id', (int) $existing->id)->update('inventory_items', $data), 'inventory_items.update');
			return $this->item($existing->public_id);
		}
		if ($this->CI->db->where('sku', $sku)->count_all_results('inventory_items') > 0)
		{
			throw new DomainRuleException('SKU itu sudah dipakai barang lain.', 409, array('sku' => 'Sudah dipakai.'));
		}
		$data['public_id'] = $this->CI->crypto->public_id();
		$data['sku'] = $sku;
		$data['created_at'] = $now;
		db_must($this->CI->db->insert('inventory_items', $data), 'inventory_items.insert');
		$this->CI->audit->log('warehouse.item_created', 'inventory_item', $data['public_id'],
			array('sku' => $sku), FALSE, 'warehouse');
		return $this->item($data['public_id']);
	}

	/** Konversi satuan wajib bilangan bulat positif, misalnya 1 dus = 12 pcs. */
	public function save_conversion($item, array $input)
	{
		$from = mb_substr(trim((string) ($input['from_unit'] ?? '')), 0, 30);
		$numerator = (int) ($input['numerator'] ?? 0);
		$denominator = (int) ($input['denominator'] ?? 1);

		if ($from === '' OR $from === $item->base_unit)
		{
			throw new DomainRuleException('Satuan asal wajib diisi dan berbeda dari satuan dasar.', 422,
				array('from_unit' => 'Tidak sah.'));
		}
		if ($numerator < 1 OR $denominator < 1)
		{
			throw new DomainRuleException('Numerator dan denominator harus bilangan bulat positif.', 422,
				array('numerator' => 'Harus positif.'));
		}
		$existing = $this->CI->db->where(array('item_id' => (int) $item->id, 'from_unit' => $from,
			'to_unit' => $item->base_unit))->get('inventory_unit_conversions')->row();
		if ($existing)
		{
			db_must($this->CI->db->where('id', (int) $existing->id)->update('inventory_unit_conversions', array(
				'numerator' => $numerator, 'denominator' => $denominator, 'active' => 1,
			)), 'inventory_unit_conversions.update');
			return;
		}
		db_must($this->CI->db->insert('inventory_unit_conversions', array(
			'item_id' => (int) $item->id,
			'from_unit' => $from,
			'to_unit' => $item->base_unit,
			'numerator' => $numerator,
			'denominator' => $denominator,
			'active' => 1,
			'created_at' => utc_now(),
		)), 'inventory_unit_conversions.insert');
	}

	public function conversions($item)
	{
		return $this->CI->db->where('item_id', (int) $item->id)->where('active', 1)
			->order_by('from_unit')->get('inventory_unit_conversions')->result();
	}

	/**
	 * Ubah jumlah dari satuan masukan menjadi satuan dasar.
	 * Hasil yang tidak bulat ditolak supaya saldo tidak pernah pecahan yang tidak sah.
	 */
	public function to_base($item, $quantity, $unit)
	{
		$quantity = (float) str_replace(',', '.', (string) $quantity);
		if ($quantity <= 0)
		{
			throw new DomainRuleException('Jumlah harus lebih besar dari nol.', 422, array('quantity' => 'Tidak sah.'));
		}
		$unit = trim((string) $unit);
		if ($unit === '' OR $unit === $item->base_unit)
		{
			return round($quantity, 3);
		}
		$conversion = $this->CI->db->where(array('item_id' => (int) $item->id, 'from_unit' => $unit,
			'to_unit' => $item->base_unit, 'active' => 1))->get('inventory_unit_conversions')->row();
		if ( ! $conversion)
		{
			throw new DomainRuleException('Konversi dari satuan '.$unit.' belum didaftarkan.', 422,
				array('input_unit' => 'Belum ada konversi.'));
		}
		$base = $quantity * (int) $conversion->numerator / (int) $conversion->denominator;
		if (abs($base - round($base)) > 0.0005)
		{
			throw new DomainRuleException('Konversi menghasilkan jumlah pecahan; periksa numerator dan denominatornya.', 422,
				array('quantity' => 'Hasil konversi pecahan.'));
		}
		return round($base, 3);
	}

	// ------------------------------------------------------------------
	// Saldo
	// ------------------------------------------------------------------

	/** Saldo kanonis satu barang pada satu lokasi, dijumlahkan dari ledger. */
	public function balance($item_id, $location_id)
	{
		$row = $this->CI->db->select('COALESCE(SUM(quantity_delta), 0) AS saldo', FALSE)
			->where(array('item_id' => (int) $item_id, 'location_id' => (int) $location_id))
			->get('inventory_ledger')->row();
		return (float) ($row ? $row->saldo : 0);
	}

	public function balances($item)
	{
		return $this->CI->db->select('l.location_id, w.code, w.name, SUM(l.quantity_delta) AS saldo', FALSE)
			->from('inventory_ledger l')->join('warehouse_locations w', 'w.id = l.location_id')
			->where('l.item_id', (int) $item->id)->group_by(array('l.location_id', 'w.code', 'w.name'))
			->having('SUM(l.quantity_delta) <> 0')->order_by('w.code')->get()->result();
	}

	/** Kartu stok satu barang pada satu lokasi. */
	public function stock_card($item_id, $location_id, $limit = 100)
	{
		return $this->CI->db->select('l.*, t.transaction_type, t.reference_no, t.public_id AS transaction_public_id')
			->from('inventory_ledger l')
			->join('inventory_transaction_lines tl', 'tl.id = l.transaction_line_id')
			->join('inventory_transactions t', 't.id = tl.transaction_id')
			->where(array('l.item_id' => (int) $item_id, 'l.location_id' => (int) $location_id))
			->order_by('l.posted_at', 'DESC')->order_by('l.id', 'DESC')->limit((int) $limit)->get()->result();
	}

	/** Barang yang saldonya di bawah minimum, dijumlahkan seluruh lokasi. */
	public function below_minimum()
	{
		$out = array();
		foreach ($this->CI->db->where('active', 1)->where('minimum_stock IS NOT NULL', NULL, FALSE)
			->get('inventory_items')->result() as $item)
		{
			$total = (float) $this->CI->db->select('COALESCE(SUM(quantity_delta), 0) AS saldo', FALSE)
				->where('item_id', (int) $item->id)->get('inventory_ledger')->row('saldo');
			if ($total < (float) $item->minimum_stock)
			{
				$item->total_balance = $total;
				$out[] = $item;
			}
		}
		return $out;
	}

	// ------------------------------------------------------------------
	// Transaksi
	// ------------------------------------------------------------------

	public function transactions($limit = 50)
	{
		return $this->CI->db->select('t.*, f.name AS from_name, o.name AS to_name')
			->from('inventory_transactions t')
			->join('warehouse_locations f', 'f.id = t.from_location_id', 'left')
			->join('warehouse_locations o', 'o.id = t.to_location_id', 'left')
			->order_by('t.id', 'DESC')->limit((int) $limit)->get()->result();
	}

	public function transaction($public_id)
	{
		return $this->CI->db->get_where('inventory_transactions', array('public_id' => (string) $public_id))->row();
	}

	public function lines($transaction)
	{
		return $this->CI->db->select('l.*, i.sku, i.name, i.base_unit')
			->from('inventory_transaction_lines l')->join('inventory_items i', 'i.id = l.item_id')
			->where('l.transaction_id', (int) $transaction->id)->order_by('l.id')->get()->result();
	}

	public function create_transaction(array $input, $user_id)
	{
		$type = (string) ($input['transaction_type'] ?? '');
		if ( ! isset(self::TYPES[$type]))
		{
			throw new DomainRuleException('Jenis transaksi tidak dikenal.', 422, array('transaction_type' => 'Tidak dikenal.'));
		}
		$from = empty($input['from_location_id']) ? NULL : (int) $input['from_location_id'];
		$to = empty($input['to_location_id']) ? NULL : (int) $input['to_location_id'];

		$errors = array();
		if (in_array($type, array('receipt', 'return'), TRUE) && $to === NULL)
		{
			$errors['to_location_id'] = 'Lokasi tujuan wajib diisi.';
		}
		if ($type === 'issue' && $from === NULL)
		{
			$errors['from_location_id'] = 'Lokasi asal wajib diisi.';
		}
		if ($type === 'transfer' && ($from === NULL OR $to === NULL))
		{
			$errors['from_location_id'] = 'Transfer membutuhkan lokasi asal dan tujuan.';
		}
		if ($type === 'transfer' && $from !== NULL && $from === $to)
		{
			$errors['to_location_id'] = 'Lokasi asal dan tujuan tidak boleh sama.';
		}
		if ($type === 'adjustment' && $to === NULL && $from === NULL)
		{
			$errors['to_location_id'] = 'Penyesuaian membutuhkan lokasi.';
		}
		if ($errors)
		{
			throw new DomainRuleException('Periksa kembali data transaksi.', 422, $errors);
		}

		$now = utc_now();
		$public_id = $this->CI->crypto->public_id();
		db_must($this->CI->db->insert('inventory_transactions', array(
			'public_id' => $public_id,
			'transaction_type' => $type,
			'reference_no' => mb_substr(trim((string) ($input['reference_no'] ?? '')), 0, 60) ?: NULL,
			'transaction_at' => $now,
			'from_location_id' => $from,
			'to_location_id' => $to,
			'source_fund' => mb_substr(trim((string) ($input['source_fund'] ?? '')), 0, 120) ?: NULL,
			'status' => 'draft',
			'requested_by' => $user_id ? (int) $user_id : NULL,
			'reason' => mb_substr(trim((string) ($input['reason'] ?? '')), 0, 500) ?: NULL,
			'created_at' => $now,
			'updated_at' => $now,
		)), 'inventory_transactions.insert');
		$this->CI->audit->log('warehouse.transaction_created', 'inventory_transaction', $public_id,
			array('type' => $type), FALSE, 'warehouse');
		return $this->transaction($public_id);
	}

	public function add_line($transaction, array $input)
	{
		if ($transaction->status === 'posted')
		{
			throw new DomainRuleException('Transaksi yang sudah diposting tidak dapat diubah.', 409);
		}
		$item = $this->item((string) ($input['item_public_id'] ?? ''));
		if ( ! $item)
		{
			throw new DomainRuleException('Barang tidak ditemukan.', 404, array('item_public_id' => 'Tidak ditemukan.'));
		}
		$unit = trim((string) ($input['input_unit'] ?? $item->base_unit));
		$base = $this->to_base($item, $input['quantity'] ?? 0, $unit);
		if ($item->track_batch && trim((string) ($input['batch_no'] ?? '')) === '')
		{
			throw new DomainRuleException('Barang ini memakai batch; nomor batch wajib diisi.', 422,
				array('batch_no' => 'Wajib diisi.'));
		}

		db_must($this->CI->db->insert('inventory_transaction_lines', array(
			'transaction_id' => (int) $transaction->id,
			'item_id' => (int) $item->id,
			'quantity_base' => $base,
			'input_quantity' => round((float) str_replace(',', '.', (string) $input['quantity']), 3),
			'input_unit' => $unit,
			'batch_no' => mb_substr(trim((string) ($input['batch_no'] ?? '')), 0, 60) ?: NULL,
			'expires_at' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($input['expires_at'] ?? '')) ? $input['expires_at'] : NULL,
			'note' => mb_substr(trim((string) ($input['note'] ?? '')), 0, 255) ?: NULL,
			'created_at' => utc_now(),
		)), 'inventory_transaction_lines.insert');
		return $this->CI->db->get_where('inventory_transaction_lines', array('id' => (int) $this->CI->db->insert_id()))->row();
	}

	/**
	 * Posting transaksi: menulis ledger dan mengunci baris item lebih dulu.
	 *
	 * Kunci baris membuat dua pengeluaran bersamaan atas stok terakhir tidak dapat
	 * sama-sama lolos: yang kedua menunggu, lalu membaca saldo terbaru dan ditolak.
	 */
	public function post($transaction, $user_id)
	{
		if ($transaction->status === 'posted')
		{
			throw new DomainRuleException('Transaksi itu sudah diposting.', 409);
		}
		$lines = $this->lines($transaction);
		if (empty($lines))
		{
			throw new DomainRuleException('Transaksi belum punya baris barang.', 409);
		}

		$now = utc_now();
		db_transaction(function () use ($transaction, $lines, $user_id, $now) {
			foreach ($lines as $line)
			{
				// Kunci baris barang selama transaksi berjalan.
				$this->CI->db->query('SELECT id FROM inventory_items WHERE id = ? FOR UPDATE', array((int) $line->item_id));

				$movements = $this->movements_for($transaction, $line);
				foreach ($movements as $movement)
				{
					if ($movement['delta'] < 0)
					{
						$available = $this->balance((int) $line->item_id, (int) $movement['location_id']);
						if ($available + $movement['delta'] < -0.0005)
						{
							throw new DomainRuleException('Stok '.$line->name.' pada lokasi itu tidak cukup: tersedia '
								.rtrim(rtrim(number_format($available, 3, ',', '.'), '0'), ',').' '.$line->base_unit.'.', 409);
						}
					}
					db_must($this->CI->db->insert('inventory_ledger', array(
						'transaction_line_id' => (int) $line->id,
						'item_id' => (int) $line->item_id,
						'location_id' => (int) $movement['location_id'],
						'quantity_delta' => $movement['delta'],
						'posted_at' => $now,
					)), 'inventory_ledger.insert');
				}
			}
			db_must($this->CI->db->where('id', (int) $transaction->id)->update('inventory_transactions', array(
				'status' => 'posted',
				'posted_by' => $user_id ? (int) $user_id : NULL,
				'posted_at' => $now,
				'version' => (int) $transaction->version + 1,
				'updated_at' => $now,
			)), 'inventory_transactions.post');
		});

		$this->CI->audit->log('warehouse.transaction_posted', 'inventory_transaction', $transaction->public_id,
			array('type' => $transaction->transaction_type, 'lines' => count($lines)), FALSE, 'warehouse');
		return $this->transaction($transaction->public_id);
	}

	/** Pergerakan ledger untuk satu baris, ditentukan jenis transaksinya. */
	protected function movements_for($transaction, $line)
	{
		$quantity = (float) $line->quantity_base;
		switch ($transaction->transaction_type)
		{
			case 'receipt':
			case 'return':
				return array(array('location_id' => (int) $transaction->to_location_id, 'delta' => $quantity));

			case 'issue':
				return array(array('location_id' => (int) $transaction->from_location_id, 'delta' => -$quantity));

			case 'transfer':
				return array(
					array('location_id' => (int) $transaction->from_location_id, 'delta' => -$quantity),
					array('location_id' => (int) $transaction->to_location_id, 'delta' => $quantity),
				);

			case 'adjustment':
				// Penyesuaian memakai tanda pada jumlah barisnya sendiri.
				$location = $transaction->to_location_id ?: $transaction->from_location_id;
				return array(array('location_id' => (int) $location, 'delta' => $quantity));
		}
		throw new DomainRuleException('Jenis transaksi tidak dikenal saat posting.', 409);
	}

	/** Penyesuaian minus dicatat sebagai baris dengan jumlah negatif. */
	public function add_adjustment_line($transaction, array $input)
	{
		$item = $this->item((string) ($input['item_public_id'] ?? ''));
		if ( ! $item)
		{
			throw new DomainRuleException('Barang tidak ditemukan.', 404);
		}
		$quantity = (float) str_replace(',', '.', (string) ($input['quantity'] ?? 0));
		if (abs($quantity) < 0.0005)
		{
			throw new DomainRuleException('Jumlah penyesuaian tidak boleh nol.', 422, array('quantity' => 'Tidak sah.'));
		}
		if (trim((string) ($input['note'] ?? '')) === '')
		{
			throw new DomainRuleException('Penyesuaian wajib disertai alasan.', 422, array('note' => 'Wajib diisi.'));
		}
		db_must($this->CI->db->insert('inventory_transaction_lines', array(
			'transaction_id' => (int) $transaction->id,
			'item_id' => (int) $item->id,
			'quantity_base' => round($quantity, 3),
			'input_quantity' => round($quantity, 3),
			'input_unit' => $item->base_unit,
			'note' => mb_substr(trim((string) $input['note']), 0, 255),
			'created_at' => utc_now(),
		)), 'inventory_transaction_lines.adjustment');
		return $this->CI->db->get_where('inventory_transaction_lines', array('id' => (int) $this->CI->db->insert_id()))->row();
	}

	// ------------------------------------------------------------------
	// Permintaan barang
	// ------------------------------------------------------------------

	public function requests($limit = 50)
	{
		return $this->CI->db->order_by('id', 'DESC')->limit((int) $limit)->get('inventory_requests')->result();
	}

	public function request($public_id)
	{
		return $this->CI->db->get_where('inventory_requests', array('public_id' => (string) $public_id))->row();
	}

	public function create_request(array $input, $user_id)
	{
		$purpose = trim((string) ($input['purpose'] ?? ''));
		if ($purpose === '')
		{
			throw new DomainRuleException('Tujuan permintaan wajib diisi.', 422, array('purpose' => 'Wajib diisi.'));
		}
		$now = utc_now();
		$public_id = $this->CI->crypto->public_id();
		db_must($this->CI->db->insert('inventory_requests', array(
			'public_id' => $public_id,
			'requesting_unit_id' => empty($input['requesting_unit_id']) ? NULL : (int) $input['requesting_unit_id'],
			'requested_by' => $user_id ? (int) $user_id : NULL,
			'needed_at' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($input['needed_at'] ?? '')) ? $input['needed_at'] : NULL,
			'purpose' => mb_substr($purpose, 0, 500),
			'status' => 'submitted',
			'created_at' => $now,
			'updated_at' => $now,
		)), 'inventory_requests.insert');
		return $this->request($public_id);
	}

	/** Pemohon tidak boleh menyetujui permintaannya sendiri. */
	public function approve_request($request, $user_id)
	{
		if ((int) $request->requested_by === (int) $user_id)
		{
			throw new DomainRuleException('Pemohon tidak dapat menyetujui permintaannya sendiri.', 409);
		}
		if ($request->status !== 'submitted')
		{
			throw new DomainRuleException('Permintaan itu sudah diproses.', 409);
		}
		db_must($this->CI->db->where('id', (int) $request->id)->update('inventory_requests', array(
			'status' => 'approved', 'approved_by' => $user_id ? (int) $user_id : NULL, 'updated_at' => utc_now(),
		)), 'inventory_requests.approve');
		$this->CI->audit->log('warehouse.request_approved', 'inventory_request', $request->public_id, array(), FALSE, 'warehouse');
	}

	// ------------------------------------------------------------------
	// Stock opname
	// ------------------------------------------------------------------

	public function stocktakes($limit = 30)
	{
		return $this->CI->db->select('s.*, w.name AS location_name')
			->from('inventory_stocktakes s')->join('warehouse_locations w', 'w.id = s.location_id')
			->order_by('s.id', 'DESC')->limit((int) $limit)->get()->result();
	}

	public function stocktake($public_id)
	{
		return $this->CI->db->get_where('inventory_stocktakes', array('public_id' => (string) $public_id))->row();
	}

	/** Membuka opname membekukan saldo harapan setiap barang pada lokasi itu. */
	public function open_stocktake(array $input, $user_id)
	{
		$location_id = (int) ($input['location_id'] ?? 0);
		if ( ! $this->CI->db->where('id', $location_id)->count_all_results('warehouse_locations'))
		{
			throw new DomainRuleException('Lokasi gudang tidak ditemukan.', 422, array('location_id' => 'Tidak ditemukan.'));
		}
		if ($this->CI->db->where(array('location_id' => $location_id, 'status' => 'open'))
			->count_all_results('inventory_stocktakes') > 0)
		{
			throw new DomainRuleException('Lokasi itu masih punya opname yang belum selesai.', 409);
		}

		$now = utc_now();
		$public_id = $this->CI->crypto->public_id();
		db_transaction(function () use ($location_id, $input, $user_id, $now, $public_id) {
			db_must($this->CI->db->insert('inventory_stocktakes', array(
				'public_id' => $public_id,
				'location_id' => $location_id,
				'name' => mb_substr(trim((string) ($input['name'] ?? 'Opname')), 0, 200),
				'snapshot_at' => $now,
				'status' => 'open',
				'created_by' => $user_id ? (int) $user_id : NULL,
				'created_at' => $now,
				'updated_at' => $now,
			)), 'inventory_stocktakes.insert');
			$stocktake_id = (int) $this->CI->db->insert_id();

			$rows = $this->CI->db->select('item_id, SUM(quantity_delta) AS saldo', FALSE)
				->where('location_id', $location_id)->group_by('item_id')
				->having('SUM(quantity_delta) <> 0')->get('inventory_ledger')->result();
			foreach ($rows as $row)
			{
				db_must($this->CI->db->insert('inventory_stocktake_lines', array(
					'stocktake_id' => $stocktake_id,
					'item_id' => (int) $row->item_id,
					'expected_quantity_base' => (float) $row->saldo,
				)), 'inventory_stocktake_lines.insert');
			}
		});
		$this->CI->audit->log('warehouse.stocktake_opened', 'inventory_stocktake', $public_id, array(), FALSE, 'warehouse');
		return $this->stocktake($public_id);
	}

	public function stocktake_lines($stocktake)
	{
		return $this->CI->db->select('l.*, i.sku, i.name, i.base_unit, i.public_id AS item_public_id')
			->from('inventory_stocktake_lines l')->join('inventory_items i', 'i.id = l.item_id')
			->where('l.stocktake_id', (int) $stocktake->id)->order_by('i.name')->get()->result();
	}

	public function count_line($stocktake, array $input, $user_id)
	{
		$line = $this->CI->db->where(array('stocktake_id' => (int) $stocktake->id,
			'id' => (int) ($input['line_id'] ?? 0)))->get('inventory_stocktake_lines')->row();
		if ( ! $line)
		{
			throw new DomainRuleException('Baris opname tidak ditemukan.', 404);
		}
		$counted = round((float) str_replace(',', '.', (string) ($input['counted'] ?? 0)), 3);
		db_must($this->CI->db->where('id', (int) $line->id)->update('inventory_stocktake_lines', array(
			'counted_quantity_base' => $counted,
			// Selisih dihitung server, bukan diketik petugas.
			'variance_quantity_base' => round($counted - (float) $line->expected_quantity_base, 3),
			'counted_by' => $user_id ? (int) $user_id : NULL,
			'counted_at' => utc_now(),
			'note' => mb_substr(trim((string) ($input['note'] ?? '')), 0, 255) ?: NULL,
		)), 'inventory_stocktake_lines.count');
	}

	/** Menutup opname membuat transaksi penyesuaian dari selisih yang sudah dihitung. */
	public function close_stocktake($stocktake, $user_id)
	{
		if ($stocktake->status !== 'open')
		{
			throw new DomainRuleException('Opname itu sudah ditutup.', 409);
		}
		$variances = array();
		foreach ($this->stocktake_lines($stocktake) as $line)
		{
			if ($line->counted_quantity_base === NULL)
			{
				throw new DomainRuleException('Masih ada barang yang belum dihitung.', 409);
			}
			if (abs((float) $line->variance_quantity_base) > 0.0005)
			{
				$variances[] = $line;
			}
		}

		$transaction = NULL;
		if ($variances)
		{
			$transaction = $this->create_transaction(array(
				'transaction_type' => 'adjustment',
				'to_location_id' => (int) $stocktake->location_id,
				'reason' => 'Penyesuaian hasil opname '.$stocktake->name,
			), $user_id);
			foreach ($variances as $line)
			{
				$this->add_adjustment_line($transaction, array(
					'item_public_id' => $line->item_public_id,
					'quantity' => (float) $line->variance_quantity_base,
					'note' => 'Selisih opname '.$stocktake->name,
				));
			}
			$transaction = $this->post($transaction, $user_id);
		}

		db_must($this->CI->db->where('id', (int) $stocktake->id)->update('inventory_stocktakes', array(
			'status' => 'closed',
			'approved_by' => $user_id ? (int) $user_id : NULL,
			'posted_adjustment_transaction_id' => $transaction ? (int) $transaction->id : NULL,
			'updated_at' => utc_now(),
		)), 'inventory_stocktakes.close');
		$this->CI->audit->log('warehouse.stocktake_closed', 'inventory_stocktake', $stocktake->public_id,
			array('variances' => count($variances)), FALSE, 'warehouse');
		return $transaction;
	}
}
