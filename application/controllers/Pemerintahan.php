<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Halaman pemerintahan desa. Struktur dinamis dibaca dari snapshot periode yang terbit
 * (prompt-master 18.6); draft dan penugasan yang sudah berakhir tidak pernah tampil.
 */
class Pemerintahan extends Public_Controller {

	/**
	 * Menu "Pemerintahan": bila struktur dinamis sudah terbit, itulah yang ditampilkan.
	 * Daftar pejabat lama (official_positions/officials) hanya menjadi cadangan selama
	 * belum ada periode yang diterbitkan.
	 */
	public function index()
	{
		$this->load->library('OrganizationService', NULL, 'org');
		if ($this->org->published_structure() !== NULL)
		{
			$this->render_structure('Pemerintahan Desa', FALSE);
			return;
		}
		$this->render('site/pemerintahan', array(
			'page_title' => 'Pemerintahan Desa',
			'positions' => $this->content->government_structure(),
			'has_structure' => FALSE,
		), 'site');
	}

	public function struktur()
	{
		$this->load->library('OrganizationService', NULL, 'org');
		$this->render_structure('Struktur Organisasi', TRUE);
	}

	protected function render_structure($page_title, $breadcrumb_parent)
	{
		$periods = $this->org->published_periods();
		$selected = (string) $this->input->get('periode');
		$snapshot = $this->org->published_structure($selected ?: NULL);

		// Foto dibaca ulang lewat Content_model::media(), yang hanya mengembalikan media
		// terbit dengan hak jelas; media yang ditarik setelah snapshot dibuat tidak ikut tampil.
		$photos = array();
		foreach (($snapshot['nodes'] ?? array()) as $node)
		{
			$media_id = (int) ($node['person']['photo_media_id'] ?? 0);
			if ($media_id > 0 && ! array_key_exists($media_id, $photos))
			{
				$photos[$media_id] = $this->content->media($media_id);
			}
		}

		$this->render('site/pemerintahan_struktur', array(
			'page_title' => $page_title,
			'breadcrumb_parent' => $breadcrumb_parent,
			'meta_description' => 'Struktur pemerintah Desa Cihawuk beserta periode dan status penugasannya.',
			'periods' => $periods,
			'selected' => $selected,
			'snapshot' => $snapshot,
			'tree' => $snapshot ? $this->org->tree_from_snapshot($snapshot) : array(),
			'photos' => $photos,
			'extra_js' => array('site/js/org-tree.js'),
		), 'site');
	}
}
