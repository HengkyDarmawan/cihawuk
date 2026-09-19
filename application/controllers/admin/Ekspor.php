<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ekspor extends Admin_Controller {

	public function __construct()
	{
		parent::__construct();
		// Permission diperiksa pada setiap aksi (bukan di constructor) agar penolakan
		// tetap menghasilkan halaman 403 yang rapi, bukan error.
		$this->load->library('ExportService', NULL, 'exports');
		$this->layout_data['nav_active'] = 'ekspor';
	}

	public function index()
	{
		$this->require_permission('tickets.export');
		$this->render('admin/ekspor', array(
			'page_title' => 'Ekspor Rekap',
			'jobs' => $this->exports->listing($this->user->id, 20),
			'report_types' => $this->exports->report_types(),
			'statuses' => app_config('ticket_statuses'),
			'report_kinds' => app_config('report_types'),
			'extra_js' => array('vendor/sweetalert2/sweetalert2.min.js'),
		), 'dashboard');
	}

	public function request_export()
	{
		$this->require_method('post');
		$this->require_permission('tickets.export');
		$filters = array(
			'status' => (string) $this->input->post('status'),
			'report_type' => (string) $this->input->post('jenis'),
			'from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $this->input->post('from')) ? (string) $this->input->post('from') : '',
			'to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $this->input->post('to')) ? (string) $this->input->post('to') : '',
			'overdue' => $this->input->post('overdue') ? 1 : 0,
		);
		$public_id = $this->exports->request_export(
			$this->user,
			(string) $this->input->post('report_type'),
			$filters,
			(string) $this->input->post('format')
		);
		$this->flash('success', 'Permintaan ekspor tercatat ('.$public_id.'). Berkas dibuat oleh job terjadwal dan Anda akan menerima notifikasi bila siap.');
		redirect(site_url('admin/ekspor'), 'location', 303);
	}

	public function download($public_id)
	{
		$this->require_method('get');
		$this->require_permission('tickets.export');
		$this->exports->download($public_id, $this->user);
	}
}
