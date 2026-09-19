<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Halaman pengantar kelompok menu Informasi. */
class Informasi extends Public_Controller {

	public function index()
	{
		$this->render('site/informasi', array(
			'page_title' => 'Informasi Desa',
			'posts' => $this->content->posts(array('news', 'announcement'), NULL, 3),
			'events' => $this->content->events(TRUE, 3),
			'documents' => array_slice($this->content->public_documents(), 0, 3),
			'galleries' => array_slice($this->content->galleries(), 0, 2),
		), 'site');
	}
}
