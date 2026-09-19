<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Profil desa: blok terstruktur berversi dan linimasa kepemimpinan (modul-backend 10.1, 10.2).
 *
 * Pemisahan izin: menyusun draft memakai `content.edit`; memverifikasi terhadap dokumen
 * sumber, menerbitkan, menarik, dan rollback memakai `content.publish`.
 */
class Profil extends Admin_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('ProfileService', NULL, 'profile_service');
		$this->layout_data['nav_active'] = 'profil';
	}

	public function index()
	{
		$this->require_any(array('content.edit', 'content.publish'));
		$this->render('admin/profil_index', array(
			'page_title' => 'Profil Desa',
			'definitions' => $this->profile_service->definitions(),
			'blocks' => $this->profile_service->blocks(),
			'terms' => $this->profile_service->terms(),
			'report' => $this->profile_service->validate_profile(),
			'snapshots' => $this->profile_service->snapshots(),
			'published' => $this->profile_service->published(),
			'statuses' => ProfileService::STATUSES,
			'can_edit' => $this->authz->can('content.edit'),
			'can_publish' => $this->authz->can('content.publish'),
			'extra_js' => array('vendor/sweetalert2/sweetalert2.min.js'),
		), 'dashboard');
	}

	public function block($public_id)
	{
		$this->require_any(array('content.edit', 'content.publish'));
		$block = $this->require_block($public_id);
		$this->render('admin/profil_block', array(
			'page_title' => 'Blok profil: '.$block->title,
			'block' => $block,
			'definition' => $this->profile_service->definition($block->block_key),
			'versions' => $this->profile_service->versions($block),
			'sources' => $this->db->order_by('source_code')->get('source_documents')->result(),
			'statuses' => ProfileService::STATUSES,
			'can_edit' => $this->authz->can('content.edit'),
			'can_publish' => $this->authz->can('content.publish'),
		), 'dashboard');
	}

	public function save_block($public_id)
	{
		$this->require_method('post');
		$this->require_permission('content.edit');
		$block = $this->require_block($public_id);
		$this->profile_service->save_block($block, $this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id);
		$this->flash('success', 'Versi baru blok profil disimpan. Halaman publik belum berubah sampai profil diterbitkan.');
		redirect(site_url('admin/profil/blok/'.rawurlencode($public_id)), 'location', 303);
	}

	public function submit_block($public_id)
	{
		$this->require_method('post');
		$this->require_permission('content.edit');
		$block = $this->require_block($public_id);
		$this->profile_service->submit_review($block, (int) $this->user->id);
		$this->flash('success', 'Blok diajukan untuk diverifikasi.');
		redirect(site_url('admin/profil'), 'location', 303);
	}

	public function verify_block($public_id)
	{
		$this->require_method('post');
		$this->require_permission('content.publish');
		$block = $this->require_block($public_id);
		$verified = (string) $this->input->post('verified') === '1';
		$this->profile_service->verify_block($block, $verified, (int) $this->user->id, $this->post_string('note', 200));
		$this->flash('success', $verified ? 'Blok ditandai terverifikasi.' : 'Tanda verifikasi dicabut.');
		redirect(site_url('admin/profil'), 'location', 303);
	}

	public function archive_block($public_id)
	{
		$this->require_method('post');
		$this->require_permission('content.publish');
		$block = $this->require_block($public_id);
		$this->profile_service->archive_block($block, (int) $this->user->id);
		$this->flash('success', 'Blok diarsipkan. Riwayat versinya tetap tersimpan.');
		redirect(site_url('admin/profil'), 'location', 303);
	}

	public function save_term()
	{
		$this->require_method('post');
		$this->require_permission('content.edit');
		$public_id = $this->post_string('public_id', 26) ?: NULL;
		$this->profile_service->save_term($this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id, $public_id);
		$this->flash('success', 'Periode kepemimpinan disimpan sebagai draft.');
		redirect(site_url('admin/profil'), 'location', 303);
	}

	public function term_action($public_id, $action)
	{
		$this->require_method('post');
		$term = $this->profile_service->term($public_id);
		if ( ! $term)
		{
			$this->not_found_response();
			return;
		}
		switch ($action)
		{
			case 'verifikasi':
				$this->require_permission('content.publish');
				$this->profile_service->verify_term($term, TRUE, (int) $this->user->id);
				$message = 'Periode ditandai terverifikasi dan siap diterbitkan.';
				break;

			case 'batal-verifikasi':
				$this->require_permission('content.publish');
				$this->profile_service->verify_term($term, FALSE, (int) $this->user->id);
				$message = 'Tanda verifikasi periode dicabut.';
				break;

			case 'tarik':
				$this->require_permission('content.publish');
				$this->profile_service->withdraw_term($term, (int) $this->user->id);
				$message = 'Periode ditarik dari publikasi. Datanya tetap tersimpan.';
				break;

			default:
				$this->not_found_response();
				return;
		}
		$this->flash('success', $message);
		redirect(site_url('admin/profil'), 'location', 303);
	}

	public function workflow($action)
	{
		$this->require_method('post');
		$this->require_permission('content.publish');
		$reason = $this->post_string('reason', 500);

		switch ($action)
		{
			case 'terbitkan':
				$revision = $this->profile_service->publish($reason, (int) $this->user->id);
				$message = 'Profil diterbitkan sebagai revisi '.$revision.'.';
				break;

			case 'tarik':
				$this->profile_service->unpublish($reason, (int) $this->user->id);
				$message = 'Profil ditarik dari halaman publik. Riwayat tetap tersimpan.';
				break;

			case 'rollback':
				$revision = $this->profile_service->rollback((int) $this->input->post('snapshot_id'), $reason, (int) $this->user->id);
				$message = 'Profil dikembalikan ke revisi sebelumnya sebagai revisi '.$revision.'.';
				break;

			default:
				$this->not_found_response();
				return;
		}
		$this->flash('success', $message);
		redirect(site_url('admin/profil'), 'location', 303);
	}

	protected function require_block($public_id)
	{
		$block = $this->profile_service->block_by_public_id($public_id);
		if ( ! $block)
		{
			throw new DomainRuleException('Blok profil tidak ditemukan.', 404);
		}
		return $block;
	}
}
