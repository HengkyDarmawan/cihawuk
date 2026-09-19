<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Transparansi anggaran (modul-backend 15).
 *
 * Pemisahan izin: mengisi angka memakai `finance.manage`; memverifikasi rekonsiliasi
 * memakai `finance.verify`; menyetujui, menerbitkan, dan menarik memakai `finance.publish`.
 */
class Keuangan extends Admin_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('BudgetService', NULL, 'budgets');
		$this->layout_data['nav_active'] = 'keuangan';
	}

	public function index()
	{
		$this->require_any(array('finance.manage', 'finance.verify', 'finance.publish'));
		$this->render('admin/keuangan_index', array(
			'page_title' => 'Anggaran dan Realisasi',
			'years' => $this->budgets->years(),
			'statuses' => BudgetService::STATUSES,
			'can_manage' => $this->authz->can('finance.manage'),
		), 'dashboard');
	}

	public function create_year()
	{
		$this->require_method('post');
		$this->require_permission('finance.manage');
		$year = $this->budgets->create_year($this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id);
		$this->flash('success', 'Tahun anggaran dibuat sebagai draft.');
		redirect(site_url('admin/keuangan/'.rawurlencode($year->public_id)), 'location', 303);
	}

	public function show($public_id)
	{
		$this->require_any(array('finance.manage', 'finance.verify', 'finance.publish'));
		$year = $this->require_year($public_id);
		$revisions = $this->budgets->revisions($year);
		$lines = array();
		foreach ($revisions as $revision)
		{
			$lines[$revision->revision_type] = $this->budgets->lines($revision);
		}
		$this->render('admin/keuangan_tahun', array(
			'page_title' => 'APBDes '.$year->fiscal_year,
			'year' => $year,
			'revisions' => $revisions,
			'lines' => $lines,
			'categories' => $this->budgets->categories($year),
			'documents' => $this->db->where('budget_year_id', (int) $year->id)->get('budget_documents')->result(),
			'report' => $this->budgets->validate_year($year),
			'snapshots' => $this->budgets->snapshots($year),
			'sections' => BudgetService::SECTIONS,
			'revision_types' => BudgetService::REVISION_TYPES,
			'statuses' => BudgetService::STATUSES,
			'can_manage' => $this->authz->can('finance.manage'),
			'can_verify' => $this->authz->can('finance.verify'),
			'can_publish' => $this->authz->can('finance.publish'),
		), 'dashboard');
	}

	public function save_revision($public_id)
	{
		$this->require_method('post');
		$this->require_permission('finance.manage');
		$year = $this->require_year($public_id);
		$this->budgets->save_revision($year, $this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id,
			$this->post_string('revision_public_id', 26) ?: NULL);
		$this->flash('success', 'Revisi anggaran disimpan.');
		$this->back($public_id);
	}

	public function save_category($public_id)
	{
		$this->require_method('post');
		$this->require_permission('finance.manage');
		$year = $this->require_year($public_id);
		$this->budgets->save_category($year, $this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id,
			$this->post_string('category_public_id', 26) ?: NULL);
		$this->flash('success', 'Kategori anggaran disimpan.');
		$this->back($public_id);
	}

	public function save_line($public_id)
	{
		$this->require_method('post');
		$this->require_permission('finance.manage');
		$year = $this->require_year($public_id);
		$revision = $this->budgets->revision((string) $this->input->post('revision_public_id'));
		if ( ! $revision OR (int) $revision->budget_year_id !== (int) $year->id)
		{
			$this->not_found_response();
			return;
		}
		$this->budgets->save_line($year, $revision, $this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id);
		$this->flash('success', 'Angka anggaran disimpan.');
		$this->back($public_id);
	}

	public function save_document($public_id)
	{
		$this->require_method('post');
		$this->require_permission('finance.manage');
		$year = $this->require_year($public_id);
		$title = $this->post_string('title', 220);
		if ($title === '')
		{
			throw new DomainRuleException('Judul dokumen wajib diisi.', 422, array('title' => 'Wajib diisi.'));
		}
		$public_document_id = (int) $this->input->post('public_document_id');
		db_must($this->db->insert('budget_documents', array(
			'public_id' => $this->crypto->public_id(),
			'budget_year_id' => (int) $year->id,
			'title' => $title,
			'document_year' => (int) $this->input->post('document_year') ?: NULL,
			'public_document_id' => $public_document_id ?: NULL,
			'is_redacted' => $this->input->post('is_redacted') ? 1 : 0,
			'note' => $this->post_string('note', 500) ?: NULL,
			'created_at' => utc_now(),
			'updated_at' => utc_now(),
		)), 'budget_documents.insert');
		$this->flash('success', 'Dokumen dicatat. Salinan publik hanya ikut terbit bila sudah ditandai disamarkan.');
		$this->back($public_id);
	}

	public function workflow($public_id, $action)
	{
		$this->require_method('post');
		$year = $this->require_year($public_id);
		$reason = $this->post_string('reason', 500);

		switch ($action)
		{
			case 'rekonsiliasi':
				$this->require_permission('finance.manage');
				$this->budgets->transition($year, 'rekonsiliasi', (int) $this->user->id, $reason);
				$message = 'Tahun anggaran masuk tahap rekonsiliasi.';
				break;

			case 'verifikasi':
				$this->require_permission('finance.verify');
				$this->budgets->transition($year, 'verifikasi', (int) $this->user->id, $reason);
				$message = 'Anggaran ditandai terverifikasi.';
				break;

			case 'setujui':
				$this->require_permission('finance.publish');
				$this->budgets->transition($year, 'setujui', (int) $this->user->id, $reason);
				$message = 'Anggaran disetujui untuk publikasi.';
				break;

			case 'terbitkan':
				$this->require_permission('finance.publish');
				$revision = $this->budgets->publish($year, $reason, (int) $this->user->id);
				$message = 'Anggaran diterbitkan sebagai revisi '.$revision.'.';
				break;

			case 'tarik':
				$this->require_permission('finance.publish');
				$this->budgets->unpublish($year, $reason, (int) $this->user->id);
				$message = 'Snapshot anggaran ditarik dari halaman publik.';
				break;

			case 'kunci':
			case 'buka-kunci':
				$this->require_permission('finance.publish');
				$this->budgets->transition($year, $action, (int) $this->user->id, $reason);
				$message = ($action === 'kunci') ? 'Periode dikunci.' : 'Kunci periode dibuka untuk koreksi.';
				break;

			default:
				$this->not_found_response();
				return;
		}
		$this->flash('success', $message);
		$this->back($public_id);
	}

	protected function require_year($public_id)
	{
		$year = $this->budgets->year($public_id);
		if ( ! $year)
		{
			throw new DomainRuleException('Tahun anggaran tidak ditemukan.', 404);
		}
		return $year;
	}

	protected function back($public_id)
	{
		redirect(site_url('admin/keuangan/'.rawurlencode($public_id)), 'location', 303);
	}
}
