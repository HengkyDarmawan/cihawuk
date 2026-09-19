<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Direktori fasilitas dan lokasi publik (modul-backend 12.2).
 *
 * Pemisahan izin: menyusun entri memakai `facilities.edit`; memverifikasi, menerbitkan,
 * menarik, dan mengarsipkan memakai `facilities.publish`.
 */
class Fasilitas extends Admin_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('FacilityService', NULL, 'facility_service');
		$this->layout_data['nav_active'] = 'fasilitas';
	}

	public function index()
	{
		$this->require_any(array('facilities.edit', 'facilities.publish'));
		$rows = $this->facility_service->facilities(array('category' => (string) $this->input->get('kategori')));
		foreach ($rows as $row)
		{
			$row->blockers = $this->facility_service->publish_blockers($row);
		}
		$this->render('admin/fasilitas_index', array(
			'page_title' => 'Direktori Fasilitas',
			'facilities' => $rows,
			'places' => $this->facility_service->places(),
			'categories' => $this->facility_service->categories(),
			'place_types' => $this->facility_service->place_types(),
			'statuses' => FacilityService::STATUSES,
			'category_filter' => (string) $this->input->get('kategori'),
			'can_edit' => $this->authz->can('facilities.edit'),
			'can_publish' => $this->authz->can('facilities.publish'),
			'extra_js' => array('vendor/sweetalert2/sweetalert2.min.js'),
		), 'dashboard');
	}

	public function create()
	{
		$this->require_method('post');
		$this->require_permission('facilities.edit');
		$facility = $this->facility_service->save_facility($this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id);
		$this->flash('success', 'Fasilitas dibuat sebagai draft.');
		redirect(site_url('admin/fasilitas/'.rawurlencode($facility->public_id)), 'location', 303);
	}

	public function show($public_id)
	{
		$this->require_any(array('facilities.edit', 'facilities.publish'));
		$facility = $this->require_facility($public_id);
		$this->render('admin/fasilitas_form', array(
			'page_title' => 'Fasilitas: '.$facility->name,
			'facility' => $facility,
			'places' => $this->facility_service->places(),
			'categories' => $this->facility_service->categories(),
			'statuses' => FacilityService::STATUSES,
			'blockers' => $this->facility_service->publish_blockers($facility),
			'sources' => $this->db->order_by('source_code')->get('source_documents')->result(),
			'can_edit' => $this->authz->can('facilities.edit'),
			'can_publish' => $this->authz->can('facilities.publish'),
		), 'dashboard');
	}

	public function save($public_id)
	{
		$this->require_method('post');
		$this->require_permission('facilities.edit');
		$this->require_facility($public_id);
		$this->facility_service->save_facility($this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id, $public_id);
		$this->flash('success', 'Fasilitas disimpan. Verifikasi sebelumnya dicabut karena isinya berubah.');
		redirect(site_url('admin/fasilitas/'.rawurlencode($public_id)), 'location', 303);
	}

	public function service($public_id, $action)
	{
		$this->require_method('post');
		$this->require_permission('facilities.edit');
		$facility = $this->require_facility($public_id);
		if ($action === 'tambah')
		{
			$this->facility_service->save_service($facility, $this->input->post(NULL, FALSE) ?: array());
			$message = 'Layanan ditambahkan.';
		}
		elseif ($action === 'hapus')
		{
			$this->facility_service->delete_service($facility, (int) $this->input->post('service_id'));
			$message = 'Layanan dihapus.';
		}
		else
		{
			$this->not_found_response();
			return;
		}
		$this->flash('success', $message);
		redirect(site_url('admin/fasilitas/'.rawurlencode($public_id)), 'location', 303);
	}

	public function workflow($public_id, $action)
	{
		$this->require_method('post');
		$this->require_permission('facilities.publish');
		$facility = $this->require_facility($public_id);
		$reason = $this->post_string('reason', 500);

		switch ($action)
		{
			case 'verifikasi':
				$this->facility_service->verify($facility, TRUE, (int) $this->user->id);
				$message = 'Fasilitas ditandai terverifikasi.';
				break;

			case 'batal-verifikasi':
				$this->facility_service->verify($facility, FALSE, (int) $this->user->id);
				$message = 'Tanda verifikasi dicabut.';
				break;

			case 'terbitkan':
				$this->facility_service->publish($facility, (int) $this->user->id);
				$message = 'Fasilitas diterbitkan ke direktori publik.';
				break;

			case 'tarik':
				$this->facility_service->unpublish($facility, $reason, (int) $this->user->id);
				$message = 'Fasilitas ditarik dari direktori publik.';
				break;

			case 'arsipkan':
				$this->facility_service->archive($facility, $reason, (int) $this->user->id);
				$message = 'Fasilitas diarsipkan. Datanya tetap tersimpan.';
				break;

			default:
				$this->not_found_response();
				return;
		}
		$this->flash('success', $message);
		redirect(site_url('admin/fasilitas/'.rawurlencode($public_id)), 'location', 303);
	}

	public function save_place()
	{
		$this->require_method('post');
		$this->require_permission('facilities.edit');
		$public_id = $this->post_string('public_id', 26) ?: NULL;
		$this->facility_service->save_place($this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id, $public_id);
		$this->flash('success', 'Lokasi disimpan. Koordinat baru perlu diverifikasi sebelum dipakai di peta.');
		redirect(site_url('admin/fasilitas'), 'location', 303);
	}

	public function verify_place($public_id)
	{
		$this->require_method('post');
		$this->require_permission('facilities.publish');
		$place = $this->facility_service->place($public_id);
		if ( ! $place)
		{
			$this->not_found_response();
			return;
		}
		$verified = (string) $this->input->post('verified') === '1';
		$this->facility_service->verify_place($place, $verified, (int) $this->user->id);
		$this->flash('success', $verified ? 'Lokasi ditandai terverifikasi.' : 'Tanda verifikasi lokasi dicabut.');
		redirect(site_url('admin/fasilitas'), 'location', 303);
	}

	protected function require_facility($public_id)
	{
		$facility = $this->facility_service->facility($public_id);
		if ( ! $facility)
		{
			throw new DomainRuleException('Fasilitas tidak ditemukan.', 404);
		}
		return $facility;
	}
}
