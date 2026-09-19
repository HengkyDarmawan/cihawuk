<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Data sumber, observasi, masalah data, dan nilai statistik.
 * Review memakai statistics.review; penerbitan nilai memakai content.publish.
 */
class Statistik extends Admin_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('SourceImportService', NULL, 'source_import');
		$this->load->library('ContentService', NULL, 'content_service');
		$this->layout_data['nav_active'] = 'statistik';
	}

	protected function require_view()
	{
		$this->require_any(array('statistics.review', 'content.edit'));
	}

	public function index()
	{
		$this->require_view();
		$indicators = $this->db->order_by('group_code')->order_by('display_order')->get('statistic_indicators')->result();
		$values = $this->db->select('v.*, i.code, i.label, i.unit, i.value_type, s.source_code')
			->from('statistic_values v')
			->join('statistic_indicators i', 'i.id = v.indicator_id')
			->join('source_documents s', 's.id = v.source_id', 'left')
			->order_by('v.source_year', 'DESC')->order_by('i.display_order')
			->get()->result();
		$this->render('admin/statistik_index', array(
			'page_title' => 'Data & Statistik',
			'indicators' => $indicators,
			'values' => $values,
			'sources' => $this->source_import->sources(),
			'open_issues' => (int) $this->db->where('status', 'open')->count_all_results('data_issues'),
			'pending_observations' => (int) $this->db->where('validation_status', 'pending')->count_all_results('source_observations'),
			'areas' => $this->db->order_by('name')->get('administrative_areas')->result(),
			'can_review' => $this->authz->can('statistics.review'),
			'can_publish' => $this->authz->can('content.publish'),
			'extra_js' => array('vendor/sweetalert2/sweetalert2.min.js'),
		), 'dashboard');
	}

	public function sources()
	{
		$this->require_view();
		$sources = $this->source_import->sources();
		foreach ($sources as $source)
		{
			$source->file_present = $this->source_import->reference_path($source) !== NULL;
			$source->observation_count = (int) $this->db->where('source_id', (int) $source->id)->count_all_results('source_observations');
			$source->batches = $this->db->where('source_id', (int) $source->id)->order_by('id', 'DESC')->limit(5)->get('import_batches')->result();
		}
		$this->render('admin/statistik_sumber', array(
			'page_title' => 'Dokumen Sumber',
			'sources' => $sources,
			'can_review' => $this->authz->can('statistics.review'),
			'extra_js' => array('vendor/sweetalert2/sweetalert2.min.js'),
		), 'dashboard');
	}

	public function source($id)
	{
		$this->require_view();
		$source = $this->source_import->source($id);
		if ( ! $source)
		{
			$this->not_found_response();
			return;
		}
		$filters = array(
			'status' => (string) $this->input->get('status'),
			'keyword' => (string) $this->input->get('q'),
		);
		$page = max(1, (int) $this->input->get('hal'));
		$per_page = 50;
		$total = $this->source_import->count_observations($source->id, $filters);

		$this->render('admin/statistik_observasi', array(
			'page_title' => 'Observasi '.$source->source_code,
			'source' => $source,
			'observations' => $this->source_import->observations($source->id, $filters, $per_page, ($page - 1) * $per_page),
			'filters' => $filters,
			'page' => $page,
			'pages' => max(1, (int) ceil($total / $per_page)),
			'total' => $total,
			'indicators' => $this->db->order_by('display_order')->get('statistic_indicators')->result(),
			'areas' => $this->db->order_by('name')->get('administrative_areas')->result(),
			'can_review' => $this->authz->can('statistics.review'),
			'extra_js' => array('vendor/sweetalert2/sweetalert2.min.js'),
		), 'dashboard');
	}

	public function import()
	{
		$this->require_method('post');
		$this->require_permission('statistics.review');
		$code = strtoupper((string) $this->input->post('source_code'));
		$results = $this->source_import->import_reference_documents($code !== '' ? $code : NULL, (int) $this->user->id);
		$this->flash('success', 'Hasil impor: '.implode(' | ', array_map(function ($k, $v) { return $k.': '.$v; }, array_keys($results), $results)));
		redirect(site_url('admin/statistik/sumber'), 'location', 303);
	}

	public function review_observation($id)
	{
		$this->require_method('post');
		$this->require_permission('statistics.review');
		$action = (string) $this->input->post('action');

		if ($action === 'promote')
		{
			$this->source_import->promote_to_statistic(
				(int) $id,
				(int) $this->input->post('indicator_id'),
				(int) $this->input->post('source_year'),
				(int) $this->input->post('area_id'),
				(int) $this->user->id
			);
			$this->flash('success', 'Nilai statistik diperbarui dari observasi ini (status draft, menunggu penerbitan).');
		}
		else
		{
			$this->source_import->review_observation(
				(int) $id,
				(string) $this->input->post('status'),
				$this->post_string('corrected_value', 255),
				$this->post_string('review_note', 500),
				(int) $this->user->id
			);
			$this->flash('success', 'Status review observasi disimpan. Nilai mentah tidak diubah.');
		}
		$observation = $this->db->get_where('source_observations', array('id' => (int) $id))->row();
		redirect(site_url('admin/statistik/sumber/'.(int) $observation->source_id), 'location', 303);
	}

	public function issues()
	{
		$this->require_view();
		$status = (string) $this->input->get('status') ?: 'open';
		$rows = $this->db->select('d.*, s.source_code, u.display_name AS resolver')
			->from('data_issues d')
			->join('source_documents s', 's.id = d.source_id', 'left')
			->join('users u', 'u.id = d.resolved_by', 'left')
			->where_in('d.status', $status === 'all' ? array('open', 'resolved', 'accepted') : array($status))
			->order_by('d.severity', 'DESC')->order_by('d.id')
			->get()->result();
		$this->render('admin/statistik_isu', array(
			'page_title' => 'Masalah Data',
			'issues' => $rows,
			'status' => $status,
			'can_review' => $this->authz->can('statistics.review'),
		), 'dashboard');
	}

	public function resolve_issue($id)
	{
		$this->require_method('post');
		$this->require_permission('statistics.review');
		$status = (string) $this->input->post('status');
		if ( ! in_array($status, array('open', 'resolved', 'accepted'), TRUE))
		{
			throw new DomainRuleException('Status masalah tidak valid.', 422);
		}
		$note = $this->post_string('resolution_note', 1000);
		if ($status !== 'open' && mb_strlen(trim($note)) < 10)
		{
			throw new DomainRuleException('Tuliskan catatan penyelesaian (minimal 10 karakter).', 422, array('resolution_note' => 'Catatan wajib diisi.'));
		}
		db_must($this->db->where('id', (int) $id)->update('data_issues', array(
			'status' => $status,
			'resolution_note' => $note,
			'resolved_by' => ($status === 'open') ? NULL : (int) $this->user->id,
			'resolved_at' => ($status === 'open') ? NULL : utc_now(),
			'updated_at' => utc_now(),
		)), 'data_issues.update');
		$this->audit->log('data_issue.'.$status, 'data_issue', (string) $id, array());
		$this->flash('success', 'Status masalah data diperbarui.');
		redirect(site_url('admin/statistik/isu'), 'location', 303);
	}

	/** Isi nilai statistik manual (tetap mencatat sumber dan status verifikasi). */
	public function save_value()
	{
		$this->require_method('post');
		$this->require_permission('statistics.review');
		$indicator_id = (int) $this->input->post('indicator_id');
		$year = (int) $this->input->post('source_year');
		$area_id = (int) $this->input->post('area_id');
		$value = $this->post_string('numeric_value', 50);
		$note = $this->post_string('year_label', 100);

		if ($this->db->where('id', $indicator_id)->count_all_results('statistic_indicators') === 0)
		{
			throw new DomainRuleException('Indikator tidak valid.', 422);
		}
		if ($year < 1900 OR $year > 2100)
		{
			throw new DomainRuleException('Tahun sumber tidak valid.', 422);
		}
		if ($this->db->where('id', $area_id)->count_all_results('administrative_areas') === 0)
		{
			throw new DomainRuleException('Wilayah tidak valid.', 422);
		}
		if ($value !== '' && ! is_numeric(str_replace(',', '.', $value)))
		{
			throw new DomainRuleException('Nilai harus berupa angka.', 422, array('numeric_value' => 'Gunakan angka, contoh 6809 atau 932.35.'));
		}
		$numeric = ($value === '') ? NULL : str_replace(',', '.', $value);
		$now = utc_now();
		db_must($this->db->query(
			'INSERT INTO statistic_values (indicator_id, source_year, year_label, area_id, numeric_value, verification_status, publication_status, reviewed_by, reviewed_at, created_at, updated_at)
			 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
			 ON DUPLICATE KEY UPDATE numeric_value = VALUES(numeric_value), year_label = VALUES(year_label),
				verification_status = VALUES(verification_status), reviewed_by = VALUES(reviewed_by), reviewed_at = VALUES(reviewed_at), updated_at = VALUES(updated_at)',
			array($indicator_id, $year, ($note === '' ? NULL : $note), $area_id, $numeric, 'verified', 'draft', (int) $this->user->id, $now, $now, $now)
		), 'statistic_values.save');
		$this->audit->log('statistic.value_manual', 'statistic_value', $indicator_id.':'.$year, array());
		$this->flash('success', 'Nilai statistik disimpan sebagai draft terverifikasi. Terbitkan agar tampil pada halaman publik.');
		redirect(site_url('admin/statistik'), 'location', 303);
	}

	public function value_status($id)
	{
		$this->require_method('post');
		$status = (string) $this->input->post('publication_status');
		if ( ! in_array($status, array('draft', 'in_review', 'published', 'archived'), TRUE))
		{
			throw new DomainRuleException('Status tidak valid.', 422);
		}
		if (in_array($status, array('published', 'archived'), TRUE))
		{
			$this->require_permission('content.publish');
		}
		else
		{
			$this->require_permission('statistics.review');
		}
		$value = $this->db->get_where('statistic_values', array('id' => (int) $id))->row();
		if ( ! $value)
		{
			$this->not_found_response();
			return;
		}
		if ($status === 'published' && $value->verification_status !== 'verified')
		{
			throw new DomainRuleException('Nilai harus berstatus terverifikasi sebelum diterbitkan.', 409);
		}
		db_must($this->db->where('id', (int) $id)->update('statistic_values', array(
			'publication_status' => $status,
			'published_at' => ($status === 'published') ? utc_now() : NULL,
			'updated_at' => utc_now(),
		)), 'statistic_values.status');
		$this->audit->log('statistic.value_'.$status, 'statistic_value', (string) $id, array());
		$this->content_service->invalidate_cache();
		$this->flash('success', 'Status publikasi nilai statistik diperbarui.');
		redirect(site_url('admin/statistik'), 'location', 303);
	}
}
