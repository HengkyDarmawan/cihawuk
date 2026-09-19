<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Dataset dan statistik publik (modul-backend §11).
 *
 * Pemisahan izin: menyusun dataset dan memverifikasi nilai memakai `data.review`;
 * menerbitkan, menarik, dan rollback memakai `data.publish`. Impor staging memakai
 * `data.import`. Tombol yang disembunyikan bukan kontrol akses — setiap aksi diperiksa di sini.
 */
class Dataset extends Admin_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('DatasetService', NULL, 'datasets');
		$this->layout_data['nav_active'] = 'dataset';
	}

	protected function require_view()
	{
		$this->require_any(array('data.review', 'data.publish', 'statistics.review'));
	}

	public function index()
	{
		$this->require_view();
		$rows = $this->datasets->datasets(array('theme' => (string) $this->input->get('tema')));
		foreach ($rows as $row)
		{
			$row->series_count = $row->current_version_id
				? count($this->datasets->series($row->current_version_id)) : 0;
		}
		$this->render('admin/dataset_index', array(
			'page_title' => 'Dataset dan Statistik',
			'datasets' => $rows,
			'themes' => $this->datasets->themes(),
			'sensitivities' => $this->datasets->sensitivities(),
			'statuses' => DatasetService::STATUSES,
			'theme_filter' => (string) $this->input->get('tema'),
			'sources' => $this->db->order_by('source_code')->get('source_documents')->result(),
			'can_edit' => $this->authz->can('data.review'),
			'pending_values' => (int) $this->db->where('verification_status', 'pending')->count_all_results('statistic_values'),
			'open_issues' => (int) $this->db->where('status', 'open')->count_all_results('data_issues'),
			'extra_js' => array('vendor/sweetalert2/sweetalert2.min.js'),
		), 'dashboard');
	}

	public function create()
	{
		$this->require_method('post');
		$this->require_permission('data.review');
		$dataset = $this->datasets->create_dataset($this->meta_input(), (int) $this->user->id);
		$this->flash('success', 'Dataset dibuat sebagai draft versi 1.');
		redirect(site_url('admin/dataset/'.rawurlencode($dataset->public_id)), 'location', 303);
	}

	public function show($public_id)
	{
		$this->require_view();
		$dataset = $this->datasets->dataset($public_id);
		if ( ! $dataset)
		{
			$this->not_found_response();
			return;
		}
		$version = $dataset->current_version_id ? $this->datasets->version($dataset->current_version_id) : NULL;
		$this->render('admin/dataset_form', array(
			'page_title' => 'Dataset: '.$dataset->name,
			'dataset' => $dataset,
			'version' => $version,
			'versions' => $this->datasets->versions($dataset),
			'series' => $version ? $this->datasets->values($version) : array(),
			'report' => $version ? $this->datasets->validation_report($version) : NULL,
			'themes' => $this->datasets->themes(),
			'sensitivities' => $this->datasets->sensitivities(),
			'chart_types' => $this->datasets->chart_types(),
			'statuses' => DatasetService::STATUSES,
			'indicators' => $this->db->order_by('group_code')->order_by('display_order')->get('statistic_indicators')->result(),
			'sources' => $this->db->order_by('source_code')->get('source_documents')->result(),
			'snapshots' => $this->datasets->snapshots($dataset),
			'can_edit' => $this->authz->can('data.review'),
			'can_publish' => $this->authz->can('data.publish'),
			'extra_js' => array('vendor/sweetalert2/sweetalert2.min.js'),
		), 'dashboard');
	}

	public function save($public_id)
	{
		$this->require_method('post');
		$this->require_permission('data.review');
		$dataset = $this->require_dataset($public_id);
		$this->datasets->save_version($dataset, $this->meta_input(), (int) $this->user->id);
		$this->flash('success', 'Versi dataset baru disimpan. Halaman publik belum berubah sampai diterbitkan.');
		$this->back($dataset);
	}

	public function series($public_id, $action)
	{
		$this->require_method('post');
		$this->require_permission('data.review');
		$dataset = $this->require_dataset($public_id);
		$version = $this->require_version($dataset);

		switch ($action)
		{
			case 'tambah':
				$this->datasets->add_series($version, array(
					'indicator_id' => (int) $this->input->post('indicator_id'),
					'chart_type' => (string) $this->input->post('chart_type'),
					'note' => $this->post_string('note', 255),
				));
				$message = 'Indikator ditambahkan ke dataset.';
				break;

			case 'hapus':
				$this->datasets->remove_series($version, (int) $this->input->post('series_id'));
				$message = 'Indikator dikeluarkan dari dataset.';
				break;

			case 'naik':
			case 'turun':
				$this->datasets->move_series($version, (int) $this->input->post('series_id'), $action === 'naik' ? 'up' : 'down');
				$message = 'Urutan indikator diperbarui.';
				break;

			default:
				$this->not_found_response();
				return;
		}
		$this->flash('success', $message);
		$this->back($dataset);
	}

	/** Verifikasi nilai satu indikator pada tahun versi ini. */
	public function verify($public_id)
	{
		$this->require_method('post');
		$this->require_permission('data.review');
		$dataset = $this->require_dataset($public_id);
		$version = $this->require_version($dataset);
		$verified = (string) $this->input->post('verified') === '1';
		$this->datasets->verify_value($version, (int) $this->input->post('indicator_id'), $verified,
			(int) $this->user->id, $this->post_string('note', 200));
		$this->flash('success', $verified
			? 'Nilai ditandai terverifikasi. Nilai mentah observasi tidak diubah.'
			: 'Tanda verifikasi dicabut.');
		$this->back($dataset);
	}

	public function validate($public_id)
	{
		$this->require_method('post');
		$this->require_permission('data.review');
		$dataset = $this->require_dataset($public_id);
		$version = $this->require_version($dataset);
		$report = $this->datasets->validate_version($dataset, $version);
		$this->flash(empty($report['errors']) ? 'success' : 'error', empty($report['errors'])
			? 'Pemeriksaan lolos. Dataset siap diajukan atau diterbitkan.'
			: 'Pemeriksaan menemukan '.count($report['errors']).' masalah yang harus diperbaiki.');
		$this->back($dataset);
	}

	public function workflow($public_id, $action)
	{
		$this->require_method('post');
		$dataset = $this->require_dataset($public_id);
		$reason = $this->post_string('reason', 500);

		switch ($action)
		{
			case 'ajukan':
				$this->require_permission('data.review');
				$this->datasets->submit_review($dataset, (int) $this->user->id);
				$message = 'Dataset diajukan untuk review.';
				break;

			case 'terbitkan':
				$this->require_permission('data.publish');
				$revision = $this->datasets->publish($dataset, $reason, (int) $this->user->id);
				$message = 'Dataset diterbitkan sebagai revisi '.$revision.'.';
				break;

			case 'tarik':
				$this->require_permission('data.publish');
				$this->datasets->unpublish($dataset, $reason, (int) $this->user->id);
				$message = 'Dataset ditarik dari halaman publik. Riwayat tetap tersimpan.';
				break;

			case 'rollback':
				$this->require_permission('data.publish');
				$revision = $this->datasets->rollback($dataset, (int) $this->input->post('snapshot_id'), $reason, (int) $this->user->id);
				$message = 'Dataset dikembalikan ke revisi sebelumnya sebagai revisi '.$revision.'.';
				break;

			case 'arsipkan':
				$this->require_permission('data.publish');
				$this->datasets->archive($dataset, $reason, (int) $this->user->id);
				$message = 'Dataset diarsipkan.';
				break;

			default:
				$this->not_found_response();
				return;
		}
		$this->flash('success', $message);
		$this->back($dataset);
	}

	// ------------------------------------------------------------------

	protected function meta_input()
	{
		return array(
			'name' => $this->post_string('name', 160),
			'slug' => $this->post_string('slug', 120),
			'theme' => (string) $this->input->post('theme'),
			'sensitivity' => (string) $this->input->post('sensitivity'),
			'coverage' => $this->post_string('coverage', 120),
			'description' => $this->post_string('description', 600),
			'period_year' => (int) $this->input->post('period_year'),
			'period_label' => $this->post_string('period_label', 60),
			'methodology' => $this->post_string('methodology', 1000),
			'quality_note' => $this->post_string('quality_note', 1000),
			'source_id' => $this->input->post('source_id'),
			'source_note' => $this->post_string('source_note', 255),
		);
	}

	protected function require_dataset($public_id)
	{
		$dataset = $this->datasets->dataset($public_id);
		if ( ! $dataset)
		{
			throw new DomainRuleException('Dataset tidak ditemukan.', 404);
		}
		return $dataset;
	}

	protected function require_version($dataset)
	{
		if ( ! $dataset->current_version_id)
		{
			throw new DomainRuleException('Dataset belum memiliki versi.', 409);
		}
		return $this->datasets->version($dataset->current_version_id);
	}

	protected function back($dataset)
	{
		redirect(site_url('admin/dataset/'.rawurlencode($dataset->public_id)), 'location', 303);
	}
}
