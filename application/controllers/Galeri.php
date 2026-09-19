<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Galeri extends Public_Controller {

	public function index()
	{
		$this->render('site/galeri', array(
			'page_title' => 'Galeri',
			'galleries' => $this->content->galleries(),
		), 'site');
	}

	public function detail($slug)
	{
		$gallery = $this->content->gallery_by_slug($slug);
		if ( ! $gallery)
		{
			$this->not_found_response();
			return;
		}
		$this->render('site/galeri_detail', array(
			'page_title' => $gallery->title,
			'gallery' => $gallery,
		), 'site');
	}
}
