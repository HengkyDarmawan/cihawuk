<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'core/AccountSecurity.php';

class Akun extends Resident_Controller {

	use AccountSecurity;

	public function __construct()
	{
		parent::__construct();
		$this->layout_data['nav_active'] = 'akun';
	}
}
