<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Struktur organisasi dinamis (prompt-master 18.6, modul-backend 10.3).
 *
 * Pemisahan izin: menyusun periode, unit, jabatan, orang, dan penugasan memakai
 * `organization.edit`; menerbitkan dan menarik snapshot memakai `organization.publish`.
 */
class Struktur extends Admin_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('OrganizationService', NULL, 'org');
		$this->layout_data['nav_active'] = 'struktur';
	}

	public function index()
	{
		$this->require_any(array('organization.edit', 'organization.publish'));
		$this->render('admin/struktur_index', array(
			'page_title' => 'Struktur Organisasi',
			'periods' => $this->org->periods(),
			'people' => $this->org->people(),
			'can_edit' => $this->authz->can('organization.edit'),
			'can_publish' => $this->authz->can('organization.publish'),
		), 'dashboard');
	}

	public function period($public_id)
	{
		$this->require_any(array('organization.edit', 'organization.publish'));
		$period = $this->require_period($public_id);
		$this->render('admin/struktur_periode', array(
			'page_title' => 'Periode: '.$period->name,
			'period' => $period,
			'units' => $this->org->units($period),
			'positions' => $this->org->positions($period),
			'assignments' => $this->org->assignments($period),
			'people' => $this->org->people(),
			'periods' => $this->org->periods(),
			'unit_types' => OrganizationService::UNIT_TYPES,
			'assignment_types' => OrganizationService::ASSIGNMENT_TYPES,
			'report' => $this->org->validate_period($period),
			'snapshots' => $this->org->snapshots($period),
			'can_edit' => $this->authz->can('organization.edit'),
			'can_publish' => $this->authz->can('organization.publish'),
		), 'dashboard');
	}

	public function save_period()
	{
		$this->require_method('post');
		$this->require_permission('organization.edit');
		$public_id = $this->post_string('public_id', 26) ?: NULL;
		$period = $this->org->save_period($this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id, $public_id);
		$this->flash('success', 'Periode disimpan sebagai draft.');
		redirect(site_url('admin/struktur/periode/'.rawurlencode($period->public_id)), 'location', 303);
	}

	public function clone_structure($public_id)
	{
		$this->require_method('post');
		$this->require_permission('organization.edit');
		$target = $this->require_period($public_id);
		$source = $this->org->period((string) $this->input->post('source_period_id'));
		if ( ! $source)
		{
			$this->not_found_response();
			return;
		}
		$this->org->clone_structure($source, $target, (int) $this->user->id);
		$this->flash('success', 'Unit dan jabatan disalin. Penugasan sengaja tidak ikut disalin karena masa jabatannya sudah berakhir.');
		$this->back($public_id);
	}

	public function save_unit($public_id)
	{
		$this->require_method('post');
		$this->require_permission('organization.edit');
		$period = $this->require_period($public_id);
		$unit_id = $this->post_string('unit_public_id', 26) ?: NULL;
		$this->org->save_unit($period, $this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id, $unit_id);
		$this->flash('success', 'Unit disimpan.');
		$this->back($public_id);
	}

	public function save_position($public_id)
	{
		$this->require_method('post');
		$this->require_permission('organization.edit');
		$period = $this->require_period($public_id);
		$position_id = $this->post_string('position_public_id', 26) ?: NULL;
		$this->org->save_position($period, $this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id, $position_id);
		$this->flash('success', 'Jabatan disimpan.');
		$this->back($public_id);
	}

	public function deactivate_position($public_id)
	{
		$this->require_method('post');
		$this->require_permission('organization.edit');
		$this->require_period($public_id);
		$position = $this->org->position((string) $this->input->post('position_public_id'));
		if ( ! $position)
		{
			$this->not_found_response();
			return;
		}
		$this->org->deactivate_position($position, (int) $this->user->id);
		$this->flash('success', 'Jabatan dinonaktifkan. Histori penugasannya tetap tersimpan.');
		$this->back($public_id);
	}

	public function save_person()
	{
		$this->require_method('post');
		$this->require_permission('organization.edit');
		$public_id = $this->post_string('public_id', 26) ?: NULL;
		$this->org->save_person($this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id, $public_id);
		$this->flash('success', 'Data orang disimpan. Akun login tetap entitas terpisah.');
		redirect(site_url('admin/struktur'), 'location', 303);
	}

	public function save_assignment($public_id)
	{
		$this->require_method('post');
		$this->require_permission('organization.edit');
		$period = $this->require_period($public_id);
		$assignment_id = $this->post_string('assignment_public_id', 26) ?: NULL;
		$this->org->save_assignment($period, $this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id, $assignment_id);
		$this->flash('success', 'Penugasan disimpan.');
		$this->back($public_id);
	}

	public function end_assignment($public_id)
	{
		$this->require_method('post');
		$this->require_permission('organization.edit');
		$this->require_period($public_id);
		$assignment = $this->org->assignment((string) $this->input->post('assignment_public_id'));
		if ( ! $assignment)
		{
			$this->not_found_response();
			return;
		}
		$this->org->end_assignment($assignment, (string) $this->input->post('end_date'),
			$this->post_string('reason', 255), (int) $this->user->id);
		$this->flash('success', 'Penugasan diakhiri. Barisnya tetap tersimpan sebagai histori.');
		$this->back($public_id);
	}

	public function workflow($public_id, $action)
	{
		$this->require_method('post');
		$this->require_permission('organization.publish');
		$period = $this->require_period($public_id);
		$reason = $this->post_string('reason', 500);

		if ($action === 'terbitkan')
		{
			$revision = $this->org->publish($period, $reason, (int) $this->user->id,
				(string) $this->input->post('make_default') !== '0');
			$message = 'Struktur periode ini diterbitkan sebagai revisi '.$revision.'.';
		}
		elseif ($action === 'tarik')
		{
			$this->org->unpublish($period, $reason, (int) $this->user->id);
			$message = 'Struktur ditarik dari halaman publik. Riwayat tetap tersimpan.';
		}
		else
		{
			$this->not_found_response();
			return;
		}
		$this->flash('success', $message);
		$this->back($public_id);
	}

	protected function require_period($public_id)
	{
		$period = $this->org->period($public_id);
		if ( ! $period)
		{
			throw new DomainRuleException('Periode tidak ditemukan.', 404);
		}
		return $period;
	}

	protected function back($public_id)
	{
		redirect(site_url('admin/struktur/periode/'.rawurlencode($public_id)), 'location', 303);
	}
}
