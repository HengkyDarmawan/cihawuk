<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pengelolaan aset desa (prompt-master 18.4).
 *
 * Pemisahan izin: melihat `assets.view`; menambah `assets.create`; mengubah `assets.edit`;
 * mengubah status `assets.change_status`; mutasi `assets.move`; pemeliharaan
 * `assets.maintain`; label QR `assets.print_labels`; nilai perolehan hanya untuk
 * `assets.view_financial`.
 */
class Aset extends Admin_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('AssetService', NULL, 'assets');
		$this->layout_data['nav_active'] = 'aset';
	}

	/** CSS/JS tambahan halaman aset: DataTables lokal dan penggambar QR. */
	protected function asset_assets(array $data)
	{
		$data['extra_css'] = array('vendor/datatables/css/dataTables.bootstrap4.min.css');
		$data['extra_js'] = array('vendor/datatables/js/dataTables.min.js', 'vendor/datatables/js/dataTables.bootstrap4.min.js',
			'vendor/sweetalert2/sweetalert2.min.js', 'vendor/qrcode-generator/qrcode.js', 'admin/js/asset-qr.js');
		return $data;
	}

	public function index()
	{
		$this->require_permission('assets.view');
		// Pekerjaan sehari-hari ada di Inventaris Desa; halaman register tetap tersedia sebagai mode lanjutan.
		if ($this->input->get('lanjutan') === NULL)
		{
			redirect(site_url('admin/inventaris'), 'location', 303);
			return;
		}
		$this->render('admin/aset_index', $this->asset_assets(array(
			'page_title' => 'Aset dan QR',
			'registers' => $this->assets->registers(array(
				'category_id' => (int) $this->input->get('kategori'),
				'q' => (string) $this->input->get('q'),
			)),
			'categories' => $this->assets->categories(),
			'locations' => $this->assets->locations(),
			'summary' => $this->assets->summary(),
			'batches' => $this->assets->label_batches(10),
			'ownership' => AssetService::OWNERSHIP,
			'can_create' => $this->authz->can('assets.create'),
			'can_financial' => $this->authz->can('assets.view_financial'),
			'filters' => array('kategori' => (int) $this->input->get('kategori'), 'q' => (string) $this->input->get('q')),
			'can_labels' => $this->authz->can('assets.print_labels'),
		)), 'dashboard');
	}

	public function register($public_id)
	{
		$this->require_permission('assets.view');
		$register = $this->require_register($public_id);
		$units = $this->assets->units($register);
		$this->render('admin/aset_register', $this->asset_assets(array(
			'page_title' => 'Register: '.$register->name,
			'register' => $register,
			'units' => $units,
			'qr_status' => $this->assets->qr_status_map(array_map(function ($u) { return (int) $u->id; }, $units)),
			'categories' => $this->assets->categories(),
			'locations' => $this->assets->locations(),
			'ownership' => AssetService::OWNERSHIP,
			'lifecycle' => AssetService::LIFECYCLE,
			'conditions' => AssetService::CONDITIONS,
			'can_edit' => $this->authz->can('assets.edit'),
			'can_create' => $this->authz->can('assets.create'),
			'can_financial' => $this->authz->can('assets.view_financial'),
			'can_labels' => $this->authz->can('assets.print_labels'),
		)), 'dashboard');
	}

	public function unit($public_id)
	{
		$this->require_permission('assets.view');
		$unit = $this->require_unit($public_id);
		if ($this->input->get('lanjutan') === NULL)
		{
			redirect(site_url('admin/inventaris/'.rawurlencode($unit->public_id)), 'location', 303);
			return;
		}
		$register = $this->db->get_where('asset_registers', array('id' => (int) $unit->register_id))->row();
		$token = $this->assets->active_token($unit);
		$this->render('admin/aset_unit', $this->asset_assets(array(
			'page_title' => 'Unit: '.$unit->asset_tag,
			'unit' => $unit,
			'register' => $register,
			'locations' => $this->assets->locations(),
			'lifecycle' => AssetService::LIFECYCLE,
			'conditions' => AssetService::CONDITIONS,
			'events' => $this->assets->status_events($unit),
			'movements' => $this->assets->movements($unit),
			'loans' => $this->assets->loans($unit),
			'maintenances' => $this->assets->maintenances($unit),
			'token' => $token,
			'qr_url' => $token ? $this->assets->qr_url($unit, $token) : NULL,
			'location' => $unit->location_id ? $this->db->get_where('asset_locations', array('id' => (int) $unit->location_id))->row() : NULL,
			'can_status' => $this->authz->can('assets.change_status'),
			'can_move' => $this->authz->can('assets.move'),
			'can_maintain' => $this->authz->can('assets.maintain'),
			'can_labels' => $this->authz->can('assets.print_labels'),
			'can_financial' => $this->authz->can('assets.view_financial'),
		)), 'dashboard');
	}

	public function save_category()
	{
		$this->require_method('post');
		$this->require_permission('assets.create');
		$this->assets->save_category($this->input->post(NULL, FALSE) ?: array());
		$this->flash('success', 'Kategori aset disimpan.');
		redirect(site_url('admin/aset'), 'location', 303);
	}

	public function save_location()
	{
		$this->require_method('post');
		$this->require_permission('assets.create');
		$this->assets->save_location($this->input->post(NULL, FALSE) ?: array());
		$this->flash('success', 'Lokasi aset disimpan.');
		redirect(site_url('admin/aset'), 'location', 303);
	}

	public function create_register()
	{
		$this->require_method('post');
		$this->require_permission('assets.create');
		$register = $this->assets->save_register($this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id);
		$this->flash('success', 'Register aset dibuat sebagai draft.');
		redirect(site_url('admin/aset/'.rawurlencode($register->public_id)), 'location', 303);
	}

	public function save_register($public_id)
	{
		$this->require_method('post');
		$this->require_permission('assets.edit');
		$this->require_register($public_id);
		$this->assets->save_register($this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id, $public_id);
		$this->flash('success', 'Register disimpan. Verifikasi sebelumnya dicabut karena isinya berubah.');
		redirect(site_url('admin/aset/'.rawurlencode($public_id)), 'location', 303);
	}

	public function verify_register($public_id)
	{
		$this->require_method('post');
		$this->require_permission('assets.change_status');
		$register = $this->require_register($public_id);
		$this->assets->verify_register($register, (string) $this->input->post('verified') === '1', (int) $this->user->id);
		$this->flash('success', 'Status verifikasi register diperbarui.');
		redirect(site_url('admin/aset/'.rawurlencode($public_id)), 'location', 303);
	}

	public function create_unit($public_id)
	{
		$this->require_method('post');
		$this->require_permission('assets.create');
		$register = $this->require_register($public_id);
		$unit = $this->assets->create_unit($register, $this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id);
		$this->flash('success', 'Unit fisik dibuat sebagai draft.');
		redirect(site_url('admin/aset/unit/'.rawurlencode($unit->public_id)), 'location', 303);
	}

	public function propose_units($public_id)
	{
		$this->require_method('post');
		$this->require_permission('assets.create');
		$register = $this->require_register($public_id);
		$created = $this->assets->propose_units($register, (int) $this->input->post('count'),
			$this->post_string('reason', 500), (int) $this->user->id, $this->post_string('tag_prefix', 30));
		$this->flash('success', count($created).' unit dibuat dari usulan pemecahan.');
		redirect(site_url('admin/aset/'.rawurlencode($public_id)), 'location', 303);
	}

	public function change_status($public_id)
	{
		$this->require_method('post');
		$this->require_permission('assets.change_status');
		$unit = $this->require_unit($public_id);
		$this->assets->change_status($unit, $this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id);
		$this->flash('success', 'Status unit diperbarui beserta historinya.');
		$this->back_unit($public_id);
	}

	public function qr($public_id, $action)
	{
		$this->require_method('post');
		$this->require_permission('assets.print_labels');
		$unit = $this->require_unit($public_id);

		if ($action === 'terbitkan')
		{
			$had_token = $this->assets->active_token($unit) !== NULL;
			$this->assets->issue_token($unit, (int) $this->user->id, $this->post_string('reason', 200) ?: 'Penerbitan QR dari halaman unit');
			$message = $had_token ? 'QR baru dibuat. Label lama tidak berlaku lagi; cetak label yang baru.' : 'QR dibuat dan siap dicetak.';
		}
		elseif ($action === 'cabut')
		{
			$this->assets->revoke_token($unit, $this->post_string('reason', 200), (int) $this->user->id);
			$message = 'QR dicabut. Label lama tidak lagi berlaku.';
		}
		else
		{
			$this->not_found_response();
			return;
		}
		$this->flash('success', $message);
		$this->back_unit($public_id);
	}

	public function request_movement($public_id)
	{
		$this->require_method('post');
		$this->require_permission('assets.move');
		$unit = $this->require_unit($public_id);
		$this->assets->request_movement($unit, $this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id);
		$this->flash('success', 'Mutasi diajukan. Lokasi baru berlaku setelah serah terima diterima.');
		$this->back_unit($public_id);
	}

	public function accept_movement($public_id, $movement_id)
	{
		$this->require_method('post');
		$this->require_permission('assets.move');
		$this->require_unit($public_id);
		$movement = $this->db->get_where('asset_movements', array('public_id' => (string) $movement_id))->row();
		if ( ! $movement)
		{
			$this->not_found_response();
			return;
		}
		$this->assets->accept_movement($movement, (int) $this->user->id);
		$this->flash('success', 'Mutasi diterima dan lokasi unit diperbarui.');
		$this->back_unit($public_id);
	}

	public function checkout($public_id)
	{
		$this->require_method('post');
		$this->require_permission('assets.move');
		$unit = $this->require_unit($public_id);
		$this->assets->checkout($unit, $this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id);
		$this->flash('success', 'Peminjaman dicatat. Kepemilikan aset tidak berubah.');
		$this->back_unit($public_id);
	}

	public function return_loan($public_id, $loan_id)
	{
		$this->require_method('post');
		$this->require_permission('assets.move');
		$this->require_unit($public_id);
		$loan = $this->db->get_where('asset_loans', array('public_id' => (string) $loan_id))->row();
		if ( ! $loan)
		{
			$this->not_found_response();
			return;
		}
		$this->assets->return_loan($loan, $this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id);
		$this->flash('success', 'Pengembalian dicatat.');
		$this->back_unit($public_id);
	}

	public function create_maintenance($public_id)
	{
		$this->require_method('post');
		$this->require_permission('assets.maintain');
		$unit = $this->require_unit($public_id);
		$this->assets->create_maintenance($unit, $this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id);
		$this->flash('success', 'Pemeliharaan dicatat.');
		$this->back_unit($public_id);
	}

	public function complete_maintenance($public_id, $maintenance_id)
	{
		$this->require_method('post');
		$this->require_permission('assets.maintain');
		$this->require_unit($public_id);
		$maintenance = $this->db->get_where('asset_maintenance', array('public_id' => (string) $maintenance_id))->row();
		if ( ! $maintenance)
		{
			$this->not_found_response();
			return;
		}
		$this->assets->complete_maintenance($maintenance, $this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id);
		$this->flash('success', 'Pemeliharaan ditutup. Kondisi unit tetap harus dinilai terpisah.');
		$this->back_unit($public_id);
	}

	/**
	 * Siapkan label QR untuk unit terpilih (`unit_ids[]`) atau seluruh unit satu register
	 * (`register`). Unit yang belum punya QR otomatis dibuatkan, lalu diarahkan ke halaman cetak.
	 */
	public function create_labels()
	{
		$this->require_method('post');
		$this->require_permission('assets.print_labels');
		$units = $this->input->post('unit_ids');
		$units = is_array($units) ? array_values(array_filter($units, 'is_string')) : array();
		$register_id = $this->post_string('register', 40);
		if ($register_id !== '')
		{
			foreach ($this->assets->units($this->require_register($register_id)) as $unit)
			{
				$units[] = $unit->public_id;
			}
		}
		$batch = $this->assets->prepare_labels($units, (int) $this->user->id);
		redirect(site_url('admin/aset/label/'.rawurlencode($batch->public_id)), 'location', 303);
	}

	/** Halaman cetak label (A4, 3 x 8) tanpa sidebar. */
	public function print_labels($public_id)
	{
		$this->require_permission('assets.print_labels');
		$batch = $this->assets->label_batch($public_id);
		if ( ! $batch)
		{
			throw new DomainRuleException('Batch label tidak ditemukan.', 404);
		}
		if ($batch->status === 'ready')
		{
			$this->assets->mark_batch_printed($batch, (int) $this->user->id);
		}
		$this->load->view('admin/aset_label_print', array(
			'batch' => $batch,
			'items' => $this->assets->label_items($batch),
		));
	}

	protected function require_register($public_id)
	{
		$register = $this->assets->register($public_id);
		if ( ! $register)
		{
			throw new DomainRuleException('Register aset tidak ditemukan.', 404);
		}
		return $register;
	}

	protected function require_unit($public_id)
	{
		$unit = $this->assets->unit($public_id);
		if ( ! $unit)
		{
			throw new DomainRuleException('Unit aset tidak ditemukan.', 404);
		}
		return $unit;
	}

	protected function back_unit($public_id)
	{
		redirect(site_url('admin/aset/unit/'.rawurlencode($public_id)), 'location', 303);
	}
}
