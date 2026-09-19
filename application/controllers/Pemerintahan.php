<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Halaman pemerintahan desa. Struktur dinamis dibaca dari snapshot periode yang terbit
 * (prompt-master 18.6); draft dan penugasan yang sudah berakhir tidak pernah tampil.
 */
class Pemerintahan extends Public_Controller {

	public function index()
	{
		$this->load->library('OrganizationService', NULL, 'org');
		$this->render('site/pemerintahan', array(
			'page_title' => 'Pemerintahan Desa',
			'positions' => $this->content->government_structure(),
			'has_structure' => $this->org->published_structure() !== NULL,
		), 'site');
	}

	public function struktur()
	{
		$this->load->library('OrganizationService', NULL, 'org');
		$periods = $this->org->published_periods();
		$selected = (string) $this->input->get('periode');
		$snapshot = $this->org->published_structure($selected ?: NULL);

		$this->render('site/pemerintahan_struktur', array(
			'page_title' => 'Struktur Organisasi',
			'meta_description' => 'Struktur pemerintah Desa Cihawuk beserta periode dan status penugasannya.',
			'periods' => $periods,
			'selected' => $selected,
			'snapshot' => $snapshot,
			'tree' => $snapshot ? $this->org->tree_from_snapshot($snapshot) : array(),
			'extra_js' => array('site/js/org-tree.js'),
		), 'site');
	}
}
