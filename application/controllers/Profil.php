<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Halaman profil publik dibaca dari snapshot profil yang sudah diterbitkan
 * (modul-frontend 5). Draft dan blok yang belum diverifikasi tidak pernah sampai ke sini.
 */
class Profil extends Public_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('ProfileService', NULL, 'profile_service');
	}

	public function index()
	{
		$blocks = $this->profile_service->published_blocks_for('profil');
		$this->render('site/profil', array(
			'page_title' => 'Profil Desa',
			'meta_description' => isset($blocks['identity']['body']['summary'])
				? str_limit_id($blocks['identity']['body']['summary'], 160) : NULL,
			'blocks' => $blocks,
			'snapshot' => $this->profile_service->published(),
			'areas' => $this->db->where('type', 'hamlet')->where('verification_status', 'verified')
				->order_by('name')->get('administrative_areas')->result(),
		), 'site');
	}

	public function sejarah()
	{
		$snapshot = $this->profile_service->published();
		$this->render('site/profil_sejarah', array(
			'page_title' => 'Sejarah Desa',
			'meta_description' => 'Sejarah Desa Cihawuk dan linimasa kepemimpinan menurut dokumen profil desa.',
			'blocks' => $this->profile_service->published_blocks_for('profil/sejarah'),
			'terms' => $snapshot['terms'] ?? array(),
			'snapshot' => $snapshot,
		), 'site');
	}

	public function visi_misi()
	{
		$this->render('site/profil_visi_misi', array(
			'page_title' => 'Visi dan Misi',
			'meta_description' => 'Visi dan misi Desa Cihawuk beserta dokumen dasarnya.',
			'blocks' => $this->profile_service->published_blocks_for('profil/visi-misi'),
			'snapshot' => $this->profile_service->published(),
		), 'site');
	}

	public function geografi()
	{
		$this->render('site/profil_geografi', array(
			'page_title' => 'Geografi Desa',
			'meta_description' => 'Ketinggian, iklim, penggunaan lahan, dan batas wilayah Desa Cihawuk menurut dokumen sumber.',
			'blocks' => $this->profile_service->published_blocks_for('profil/geografi'),
			'snapshot' => $this->profile_service->published(),
		), 'site');
	}
}
