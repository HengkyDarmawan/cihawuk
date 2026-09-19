<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Errors extends Public_Controller {

	public function not_found()
	{
		$this->not_found_page();
	}

	protected function not_found_page()
	{
		$this->respond_error(404, 'Halaman yang Anda cari tidak tersedia atau sudah dipindahkan.');
	}
}
