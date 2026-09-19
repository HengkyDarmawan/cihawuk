<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Audit fisik aset (prompt-master 18.4.3).
 *
 * Pemisahan izin: membuat sesi `asset_audits.create`; mencatat temuan
 * `asset_audits.perform`; memverifikasi, mengoreksi master, menutup sesi, dan membuat
 * addendum `asset_audits.verify`.
 */
class Audit_aset extends Admin_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('AssetAuditService', NULL, 'audits');
		$this->load->library('AssetService', NULL, 'assets');
		$this->layout_data['nav_active'] = 'audit-aset';
	}

	public function index()
	{
		$this->require_any(array('asset_audits.create', 'asset_audits.perform', 'asset_audits.verify'));
		$this->render('admin/audit_aset_index', array(
			'page_title' => 'Audit Aset',
			'sessions' => $this->audits->sessions(),
			'categories' => $this->assets->categories(),
			'locations' => $this->assets->locations(),
			'statuses' => AssetAuditService::SESSION_STATUSES,
			'can_create' => $this->authz->can('asset_audits.create'),
		), 'dashboard');
	}

	public function show($public_id)
	{
		$this->require_any(array('asset_audits.create', 'asset_audits.perform', 'asset_audits.verify'));
		$session = $this->require_session($public_id);
		$this->render('admin/audit_aset_sesi', array(
			'page_title' => 'Audit: '.$session->name,
			'session' => $session,
			'targets' => $this->audits->targets($session),
			'findings' => $this->audits->findings($session),
			'differences' => $this->audits->differences($session),
			'addenda' => $this->audits->addenda($session),
			'locations' => $this->assets->locations(),
			'existence' => AssetAuditService::EXISTENCE,
			'conditions' => AssetService::CONDITIONS,
			'statuses' => AssetAuditService::SESSION_STATUSES,
			'can_perform' => $this->authz->can('asset_audits.perform'),
			'can_verify' => $this->authz->can('asset_audits.verify'),
		), 'dashboard');
	}

	public function create()
	{
		$this->require_method('post');
		$this->require_permission('asset_audits.create');
		$session = $this->audits->create_session($this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id);
		$this->flash('success', 'Sesi audit dibuat sebagai draft.');
		redirect(site_url('admin/audit-aset/'.rawurlencode($session->public_id)), 'location', 303);
	}

	public function publish($public_id)
	{
		$this->require_method('post');
		$this->require_permission('asset_audits.verify');
		$session = $this->require_session($public_id);
		$count = $this->audits->publish_session($session, (int) $this->user->id);
		$this->flash('success', 'Sesi audit diterbitkan. '.$count.' unit dibekukan sebagai target.');
		$this->back($public_id);
	}

	public function record($public_id)
	{
		$this->require_method('post');
		$this->require_permission('asset_audits.perform');
		$session = $this->require_session($public_id);
		$this->audits->record_finding($session, $this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id);
		$this->flash('success', 'Temuan dicatat. Data master belum berubah sampai temuan diverifikasi.');
		$this->back($public_id);
	}

	public function finding_action($public_id, $finding_id, $action)
	{
		$this->require_method('post');
		$this->require_permission('asset_audits.verify');
		$this->require_session($public_id);
		$finding = $this->audits->finding($finding_id);
		if ( ! $finding)
		{
			$this->not_found_response();
			return;
		}
		switch ($action)
		{
			case 'terima':
				$this->audits->verify_finding($finding, TRUE, $this->post_string('note', 500), (int) $this->user->id);
				$message = 'Temuan diterima. Koreksi master masih menjadi langkah terpisah.';
				break;

			case 'tolak':
				$this->audits->verify_finding($finding, FALSE, $this->post_string('note', 500), (int) $this->user->id);
				$message = 'Temuan ditolak.';
				break;

			case 'koreksi':
				$this->audits->apply_finding($finding, (int) $this->user->id);
				$message = 'Master dikoreksi mengikuti temuan, beserta histori perubahannya.';
				break;

			default:
				$this->not_found_response();
				return;
		}
		$this->flash('success', $message);
		$this->back($public_id);
	}

	public function close($public_id)
	{
		$this->require_method('post');
		$this->require_permission('asset_audits.verify');
		$session = $this->require_session($public_id);
		$this->audits->close_session($session, (int) $this->user->id);
		$this->flash('success', 'Sesi audit ditutup dan laporannya dibekukan.');
		$this->back($public_id);
	}

	public function addendum($public_id)
	{
		$this->require_method('post');
		$this->require_permission('asset_audits.verify');
		$session = $this->require_session($public_id);
		$this->audits->add_addendum($session, $this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id);
		$this->flash('success', 'Addendum dicatat pada sesi audit yang sudah ditutup.');
		$this->back($public_id);
	}

	protected function require_session($public_id)
	{
		$session = $this->audits->session($public_id);
		if ( ! $session)
		{
			throw new DomainRuleException('Sesi audit tidak ditemukan.', 404);
		}
		return $session;
	}

	protected function back($public_id)
	{
		redirect(site_url('admin/audit-aset/'.rawurlencode($public_id)), 'location', 303);
	}
}
