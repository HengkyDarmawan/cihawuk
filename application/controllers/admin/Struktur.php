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
		$this->load->library('UploadService', NULL, 'uploads');
		$this->layout_data['nav_active'] = 'struktur';
	}

	public function index()
	{
		$this->require_any(array('organization.edit', 'organization.publish'));
		$people = $this->org->people();
		$photos = array();
		foreach ($people as $person)
		{
			if ($person->photo_media_id)
			{
				$photos[(int) $person->photo_media_id] = TRUE;
			}
		}
		$edit_id = (string) $this->input->get('orang');
		$this->render('admin/struktur_index', array(
			'page_title' => 'Struktur Organisasi',
			'periods' => $this->org->periods(),
			'people' => $people,
			'photos' => $this->media_by_id(array_keys($photos)),
			'media_options' => $this->image_options(),
			'edit_person' => $edit_id !== '' ? $this->org->person($edit_id) : NULL,
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
		$input = $this->input->post(NULL, FALSE) ?: array();
		$existing = $public_id ? $this->org->person($public_id) : NULL;
		$input['photo_media_id'] = $this->resolve_photo($input, $existing);
		$this->org->save_person($input, (int) $this->user->id, $public_id);
		$this->flash('success', 'Data orang disimpan. Terbitkan ulang periode agar perubahan tampil di halaman publik.');
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

	/**
	 * Foto orang: unggahan baru diutamakan, lalu pilihan dari pustaka, lalu foto lama.
	 * Izin publikasi diperiksa sebelum berkas disentuh, supaya foto tanpa izin tidak
	 * pernah masuk pustaka media sebagai foto pejabat.
	 */
	protected function resolve_photo(array $input, $existing)
	{
		if ( ! empty($input['remove_photo']))
		{
			return NULL;
		}
		$name = mb_substr(trim((string) ($input['full_name'] ?? '')), 0, 180);
		if ( ! empty($_FILES['foto']['name']) && is_array($_FILES['foto']['name'])
			&& (int) ($_FILES['foto']['error'][0] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)
		{
			if (empty($input['photo_consent']))
			{
				throw new DomainRuleException('Centang izin publikasi foto sebelum mengunggah foto pejabat.', 422,
					array('photo_consent' => 'Izin publikasi foto belum dicatat.'));
			}
			$alt = $this->post_string('photo_alt', 255) ?: 'Foto '.$name;
			$media_id = $this->uploads->store_media_asset('foto', array(
				'alt_text' => $alt,
				'caption' => $name,
				'people_shown' => $name,
				'rights_status' => 'permission_granted',
			), (int) $this->user->id);
			$this->uploads->commit_staged();
			$this->audit->log('media.uploaded', 'media', (string) $media_id, array('context' => 'organization.person'));
			return (int) $media_id;
		}
		if ( ! empty($input['photo_media_id']))
		{
			return (int) $input['photo_media_id'];
		}
		return ($existing && $existing->photo_media_id) ? (int) $existing->photo_media_id : NULL;
	}

	/** Gambar pustaka yang status haknya jelas, untuk dipilih sebagai foto orang. */
	protected function image_options()
	{
		return $this->db->select('id, original_name, alt_text')
			->where('deleted_at IS NULL', NULL, FALSE)
			->like('mime_type', 'image/', 'after')
			->where_in('rights_status', array('owned', 'licensed', 'permission_granted'))
			->order_by('id', 'DESC')->limit(200)->get('media_assets')->result();
	}

	protected function media_by_id(array $ids)
	{
		if (empty($ids))
		{
			return array();
		}
		$out = array();
		foreach ($this->db->where_in('id', $ids)->where('deleted_at IS NULL', NULL, FALSE)->get('media_assets')->result() as $media)
		{
			$out[(int) $media->id] = $media;
		}
		return $out;
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
