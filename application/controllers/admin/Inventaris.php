<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Inventaris Desa: daftar barang, tambah/ubah dalam satu form, QR per barang,
 * aksi cepat (pindah, pinjam, perbaikan, kondisi), dan riwayat.
 * Aturan ada di InventoryService/AssetService; controller hanya memeriksa izin.
 */
class Inventaris extends Admin_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('InventoryService', NULL, 'inventory');
		$this->layout_data['nav_active'] = 'aset';
	}

	/** CSS/JS halaman inventaris: DataTables lokal dan penggambar QR. */
	protected function page(array $data)
	{
		$data['extra_css'] = array('vendor/datatables/css/dataTables.bootstrap4.min.css');
		$data['extra_js'] = array('vendor/datatables/js/dataTables.min.js', 'vendor/datatables/js/dataTables.bootstrap4.min.js',
			'vendor/sweetalert2/sweetalert2.min.js', 'vendor/qrcode-generator/qrcode.js', 'admin/js/asset-qr.js');
		return $data;
	}

	protected function options()
	{
		$categories = array();
		foreach ($this->assets->categories() as $c) { $categories[(string) $c->id] = $c->name; }
		$locations = array();
		foreach ($this->assets->locations() as $l) { $locations[(string) $l->id] = $l->name; }
		return array('categories' => $categories, 'locations' => $locations);
	}

	public function index()
	{
		$this->require_permission('assets.view');
		$filters = array(
			'q' => mb_substr(trim((string) $this->input->get('q')), 0, 80),
			'kategori' => (int) $this->input->get('kategori'),
			'lokasi' => (int) $this->input->get('lokasi'),
			'kondisi' => (string) $this->input->get('kondisi'),
			'status' => (string) $this->input->get('status'),
		);
		$items = $this->inventory->items($filters);
		$qr_urls = array();
		foreach ($items as $item)
		{
			$qr_urls[$item->id] = (int) $item->qr_active > 0 ? $this->assets->qr_url($item) : NULL;
		}
		$this->render('admin/inventaris_index', $this->page(array_merge($this->options(), array(
			'page_title' => 'Inventaris Desa',
			'items' => $items,
			'qr_urls' => $qr_urls,
			'summary' => $this->inventory->summary(),
			'filters' => $filters,
			'can_create' => $this->authz->can('assets.create'),
			'can_edit' => $this->authz->can('assets.edit'),
			'can_labels' => $this->authz->can('assets.print_labels'),
		))), 'dashboard');
	}

	public function create()
	{
		$this->require_permission('assets.create');
		$this->render_form(NULL);
	}

	public function store()
	{
		$this->require_method('post');
		$this->require_permission('assets.create');
		$input = $this->form_input();
		$this->old_input = $input;
		try
		{
			$created = $this->inventory->create($input, (int) $this->user->id);
		}
		catch (DomainRuleException $e)
		{
			$this->form_errors = $e->errors;
			$this->output->set_status_header($e->http_status);
			$this->render_form(NULL, $e->getMessage());
			return;
		}
		$photo_note = $this->try_photo($created[0]);
		$this->flash('success', count($created) > 1
			? count($created).' barang ditambahkan ('.$created[0]->asset_tag.' s.d. '.end($created)->asset_tag.'), masing-masing sudah punya QR.'.$photo_note
			: 'Barang '.$created[0]->asset_tag.' ditambahkan dan QR-nya sudah jadi.'.$photo_note);
		redirect(site_url(count($created) > 1 ? 'admin/inventaris' : 'admin/inventaris/'.rawurlencode($created[0]->public_id)), 'location', 303);
	}

	public function show($public_id)
	{
		$this->require_permission('assets.view');
		$item = $this->require_item($public_id);
		$token = $this->assets->active_token($item);
		$this->render('admin/inventaris_detail', $this->page(array_merge($this->options(), array(
			'page_title' => $item->asset_tag.' · '.$item->name,
			'item' => $item,
			'token' => $token,
			'qr_url' => $token ? $this->assets->qr_url($item, $token) : NULL,
			'loan' => $this->inventory->open_loan($item),
			'maintenance' => $this->inventory->open_maintenance($item),
			'history' => $this->inventory->history($item),
			'media' => $item->primary_media_id ? $this->db->get_where('media_assets', array('id' => (int) $item->primary_media_id))->row() : NULL,
			'can_edit' => $this->authz->can('assets.edit'),
			'can_move' => $this->authz->can('assets.move'),
			'can_maintain' => $this->authz->can('assets.maintain'),
			'can_status' => $this->authz->can('assets.change_status'),
			'can_labels' => $this->authz->can('assets.print_labels'),
			'can_financial' => $this->authz->can('assets.view_financial'),
		))), 'dashboard');
	}

	public function edit($public_id)
	{
		$this->require_permission('assets.edit');
		$this->render_form($this->require_item($public_id));
	}

	public function update($public_id)
	{
		$this->require_method('post');
		$this->require_permission('assets.edit');
		$item = $this->require_item($public_id);
		$input = $this->form_input();
		$this->old_input = $input;
		try
		{
			$this->inventory->update($item, $input, (int) $this->user->id);
		}
		catch (DomainRuleException $e)
		{
			$this->form_errors = $e->errors;
			$this->output->set_status_header($e->http_status);
			$this->render_form($item, $e->getMessage());
			return;
		}
		$this->flash('success', 'Data barang disimpan. Kode dan QR tidak berubah.'.$this->try_photo($item));
		$this->back($public_id);
	}

	/** Aksi cepat dari halaman detail. */
	public function action($public_id, $action)
	{
		$this->require_method('post');
		$item = $this->require_item($public_id);
		$input = $this->input->post(NULL, FALSE) ?: array();
		$uid = (int) $this->user->id;
		$actions = array(
			'pindah' => array('assets.move', 'move', 'Lokasi barang dipindahkan.'),
			'pinjam' => array('assets.move', 'lend', 'Peminjaman dicatat.'),
			'kembali' => array('assets.move', 'return_item', 'Pengembalian dicatat.'),
			'perbaikan' => array('assets.maintain', 'start_repair', 'Perbaikan dicatat. Status barang: sedang diperbaiki.'),
			'selesai-perbaikan' => array('assets.maintain', 'finish_repair', 'Perbaikan selesai dicatat.'),
			'kondisi' => array('assets.change_status', 'set_status', 'Kondisi/status barang diperbarui.'),
		);
		if ($action === 'qr-baru')
		{
			$this->require_permission('assets.print_labels');
			$had = $this->assets->active_token($item) !== NULL;
			$this->assets->issue_token($item, $uid, 'QR dibuat ulang dari halaman inventaris');
			$this->flash('success', $had ? 'QR baru dibuat. Label lama tidak berlaku lagi, cetak label yang baru.' : 'QR dibuat dan siap dicetak.');
			$this->back($public_id);
			return;
		}
		if ($action === 'aktifkan')
		{
			$this->require_permission('assets.change_status');
			$this->assets->change_status($item, array('to_lifecycle' => 'active',
				'to_condition' => $item->condition_status === 'not_assessed' ? 'good' : $item->condition_status,
				'reason' => 'Diaktifkan dari halaman inventaris'), $uid);
			$this->assets->ensure_token($this->assets->unit($item->public_id), $uid);
			$this->flash('success', 'Barang diaktifkan dan QR siap dicetak.');
			$this->back($public_id);
			return;
		}
		if ( ! isset($actions[$action]))
		{
			throw new DomainRuleException('Aksi tidak dikenal.', 404);
		}
		list($permission, $method, $message) = $actions[$action];
		$this->require_permission($permission);
		try
		{
			$this->inventory->{$method}($item, $input, $uid);
		}
		catch (DomainRuleException $e)
		{
			if ($e->http_status >= 500)
			{
				throw $e;
			}
			$this->flash('error', $e->getMessage());
			$this->back($public_id);
			return;
		}
		$this->flash('success', $message);
		$this->back($public_id);
	}

	/**
	 * Cetak label QR: satu barang (`barang`), barang terpilih (`unit_ids[]`), atau semua barang
	 * sesuai filter daftar. Barang yang belum punya QR otomatis dibuatkan.
	 */
	public function print_qr()
	{
		$this->require_method('post');
		$this->require_permission('assets.print_labels');
		$ids = $this->input->post('unit_ids');
		$ids = is_array($ids) ? array_values(array_filter($ids, 'is_string')) : array();
		$single = $this->post_string('barang', 40);
		if ($single !== '')
		{
			$ids = array($single);
		}
		elseif (empty($ids))
		{
			foreach ($this->inventory->items(array(
				'kategori' => (int) $this->input->post('kategori'),
				'lokasi' => (int) $this->input->post('lokasi'),
				'kondisi' => (string) $this->input->post('kondisi'),
				'status' => (string) $this->input->post('status'),
				'q' => $this->post_string('q', 80),
			)) as $item)
			{
				if (in_array($item->lifecycle_status, array('disposed', 'lost'), TRUE))
				{
					continue;
				}
				$ids[] = $item->public_id;
			}
		}
		if (empty($ids))
		{
			$this->flash('error', 'Tidak ada barang untuk dicetak.');
			redirect(site_url('admin/inventaris'), 'location', 303);
			return;
		}
		$batch = $this->assets->prepare_labels($ids, (int) $this->user->id);
		redirect(site_url('admin/aset/label/'.rawurlencode($batch->public_id)), 'location', 303);
	}

	// ------------------------------------------------------------------

	protected function form_input()
	{
		$fields = array('nama' => 220, 'kategori_id' => 20, 'kategori_baru' => 120, 'merk' => 120, 'tipe' => 120,
			'jumlah' => 4, 'location_id' => 20, 'lokasi_baru' => 180, 'tahun' => 4, 'kondisi' => 30,
			'keterangan' => 1000, 'catatan_publik' => 500);
		if ($this->authz->can('assets.view_financial'))
		{
			$fields += array('sumber_dana' => 180, 'harga' => 30);
		}
		$input = array();
		foreach ($fields as $field => $max)
		{
			$input[$field] = trim($this->post_string($field, $max));
		}
		return $input;
	}

	protected function render_form($item, $error = NULL)
	{
		$this->render('admin/inventaris_form', $this->page(array_merge($this->options(), array(
			'page_title' => $item ? 'Ubah '.$item->asset_tag : 'Tambah Barang',
			'item' => $item,
			'form_error' => $error,
			'next_code' => $this->inventory->next_code(),
			'can_financial' => $this->authz->can('assets.view_financial'),
		))), 'dashboard');
	}

	/** Unggah foto bila ada; kegagalan unggah tidak membatalkan penyimpanan barang. */
	protected function try_photo($unit)
	{
		try
		{
			return $this->inventory->attach_photo($unit, 'foto', (int) $this->user->id) ? ' Foto tersimpan.' : '';
		}
		catch (DomainRuleException $e)
		{
			return ' Foto tidak tersimpan: '.$e->getMessage();
		}
	}

	protected function require_item($public_id)
	{
		$item = $this->inventory->item($public_id);
		if ( ! $item)
		{
			throw new DomainRuleException('Barang tidak ditemukan.', 404);
		}
		return $item;
	}

	protected function back($public_id)
	{
		redirect(site_url('admin/inventaris/'.rawurlencode($public_id)), 'location', 303);
	}
}
