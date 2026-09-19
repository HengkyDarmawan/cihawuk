<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Direktori UMKM (modul-backend 12.3).
 *
 * Pemisahan izin: menyusun profil usaha memakai `umkm.edit`; menerbitkan, menarik,
 * mencabut persetujuan, dan mengarsipkan memakai `umkm.publish`.
 */
class Umkm extends Admin_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('BusinessService', NULL, 'businesses');
		$this->layout_data['nav_active'] = 'umkm';
	}

	public function index()
	{
		$this->require_any(array('umkm.edit', 'umkm.publish'));
		$rows = $this->businesses->businesses(array('category' => (string) $this->input->get('kategori')));
		foreach ($rows as $row)
		{
			$row->blockers = $this->businesses->publish_blockers($row);
		}
		$this->render('admin/umkm_index', array(
			'page_title' => 'Direktori UMKM',
			'businesses' => $rows,
			'categories' => BusinessService::CATEGORIES,
			'statuses' => BusinessService::STATUSES,
			'category_filter' => (string) $this->input->get('kategori'),
			'can_edit' => $this->authz->can('umkm.edit'),
			'can_publish' => $this->authz->can('umkm.publish'),
		), 'dashboard');
	}

	public function show($public_id)
	{
		$this->require_any(array('umkm.edit', 'umkm.publish'));
		$business = $this->require_business($public_id);
		$this->render('admin/umkm_form', array(
			'page_title' => 'UMKM: '.$business->name,
			'business' => $business,
			'categories' => BusinessService::CATEGORIES,
			'statuses' => BusinessService::STATUSES,
			'blockers' => $this->businesses->publish_blockers($business),
			'can_edit' => $this->authz->can('umkm.edit'),
			'can_publish' => $this->authz->can('umkm.publish'),
		), 'dashboard');
	}

	public function create()
	{
		$this->require_method('post');
		$this->require_permission('umkm.edit');
		$business = $this->businesses->save($this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id);
		$this->flash('success', 'Profil usaha dibuat sebagai draft.');
		redirect(site_url('admin/umkm/'.rawurlencode($business->public_id)), 'location', 303);
	}

	public function save($public_id)
	{
		$this->require_method('post');
		$this->require_permission('umkm.edit');
		$this->require_business($public_id);
		$this->businesses->save($this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id, $public_id);
		$this->flash('success', 'Profil usaha disimpan.');
		redirect(site_url('admin/umkm/'.rawurlencode($public_id)), 'location', 303);
	}

	public function workflow($public_id, $action)
	{
		$this->require_method('post');
		$this->require_permission('umkm.publish');
		$business = $this->require_business($public_id);
		$reason = $this->post_string('reason', 500);

		switch ($action)
		{
			case 'terbitkan':
				$this->businesses->publish($business, (int) $this->user->id);
				$message = 'Profil usaha diterbitkan ke direktori publik.';
				break;

			case 'tarik':
				$this->businesses->unpublish($business, $reason, (int) $this->user->id);
				$message = 'Profil usaha ditarik dari direktori publik.';
				break;

			case 'cabut-persetujuan':
				$this->businesses->revoke_consent($business, $reason, (int) $this->user->id);
				$message = 'Persetujuan pemilik dicabut dan profilnya diturunkan dari direktori.';
				break;

			case 'arsipkan':
				$this->businesses->archive($business, $reason, (int) $this->user->id);
				$message = 'Profil usaha diarsipkan.';
				break;

			default:
				$this->not_found_response();
				return;
		}
		$this->flash('success', $message);
		redirect(site_url('admin/umkm/'.rawurlencode($public_id)), 'location', 303);
	}

	protected function require_business($public_id)
	{
		$business = $this->businesses->business($public_id);
		if ( ! $business)
		{
			throw new DomainRuleException('Profil usaha tidak ditemukan.', 404);
		}
		return $business;
	}
}
