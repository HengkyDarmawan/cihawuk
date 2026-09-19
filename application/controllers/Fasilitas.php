<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Direktori fasilitas publik (modul-frontend 9). Hanya entri terbit, terverifikasi, dan
 * aktif yang tampil. Koordinat lokasi sensitif tidak pernah dikirim ke peta.
 */
class Fasilitas extends Public_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('FacilityService', NULL, 'facility_service');
	}

	public function index()
	{
		$all = $this->facility_service->published_facilities();
		$category = (string) $this->input->get('kategori');
		$keyword = trim((string) $this->input->get('q'));

		$categories_present = array();
		foreach ($all as $facility)
		{
			$categories_present[$facility['category']] = $facility['category_label'];
		}

		$visible = array();
		foreach ($all as $facility)
		{
			if ($category !== '' && $facility['category'] !== $category)
			{
				continue;
			}
			if ($keyword !== '' && mb_stripos($facility['name'], $keyword) === FALSE
				&& mb_stripos((string) $facility['description'], $keyword) === FALSE)
			{
				continue;
			}
			$visible[] = $facility;
		}

		$this->render('site/fasilitas', array(
			'page_title' => 'Fasilitas Desa',
			'meta_description' => 'Direktori fasilitas Desa Cihawuk yang sudah diverifikasi beserta layanan, alamat, dan tahun datanya.',
			'facilities' => $visible,
			'total' => count($all),
			'categories_present' => $categories_present,
			'filters' => array('kategori' => $category, 'q' => $keyword),
			'extra_js' => array('vendor/leaflet/leaflet.js', 'site/js/map.js'),
			'extra_css' => array('vendor/leaflet/leaflet.css'),
		), 'site');
	}

	public function detail($slug = '')
	{
		$facility = $this->facility_service->published_facility($slug);
		if ( ! $facility)
		{
			$this->not_found_response();
			return;
		}
		$this->render('site/fasilitas_detail', array(
			'page_title' => $facility['name'],
			'meta_description' => str_limit_id((string) ($facility['description'] ?: $facility['name']), 160),
			'facility' => $facility,
			'photo' => $facility['photo_media_id'] ? $this->content->media($facility['photo_media_id']) : NULL,
			'extra_js' => array('vendor/leaflet/leaflet.js', 'site/js/map.js'),
			'extra_css' => array('vendor/leaflet/leaflet.css'),
		), 'site');
	}
}
