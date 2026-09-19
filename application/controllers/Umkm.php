<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Direktori UMKM publik (modul-frontend 7.3). Hanya usaha yang pemiliknya menyetujui
 * publikasi yang tampil; mencabut persetujuan langsung menghilangkannya dari sini.
 */
class Umkm extends Public_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('BusinessService', NULL, 'businesses');
	}

	public function index()
	{
		$all = $this->businesses->published_businesses();
		$category = (string) $this->input->get('kategori');
		$categories_present = array();
		$visible = array();
		foreach ($all as $business)
		{
			$categories_present[$business['category']] = $business['category_label'];
			if ($category === '' OR $business['category'] === $category)
			{
				$visible[] = $business;
			}
		}

		$this->render('site/umkm', array(
			'page_title' => 'UMKM Desa',
			'meta_description' => 'Profil usaha warga Desa Cihawuk yang pemiliknya menyetujui publikasi.',
			'businesses' => $visible,
			'total' => count($all),
			'categories_present' => $categories_present,
			'filters' => array('kategori' => $category),
		), 'site');
	}

	public function detail($slug = '')
	{
		$business = $this->businesses->published_business($slug);
		if ( ! $business)
		{
			$this->not_found_response();
			return;
		}
		$this->render('site/umkm_detail', array(
			'page_title' => $business['name'],
			'meta_description' => str_limit_id((string) ($business['description'] ?: $business['name']), 160),
			'business' => $business,
			'photo' => $business['photo_media_id'] ? $this->content->media($business['photo_media_id']) : NULL,
		), 'site');
	}
}
