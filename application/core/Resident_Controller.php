<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Dashboard warga (/warga). Hanya pengguna aktif dengan role warga.
 */
class Resident_Controller extends MY_Controller {

	/** @var object */
	protected $user;

	public function __construct()
	{
		parent::__construct();
		$this->no_store = TRUE;
		$this->start_session();
		$this->user = $this->auth->user();
		if ( ! $this->user)
		{
			$this->redirect_to_login();
		}
		if ( ! in_array('resident', $this->user_model->role_codes($this->user->id), TRUE))
		{
			// Akun petugas tanpa role warga diarahkan ke dashboard pengelola.
			redirect(site_url('admin'), 'location', 303);
		}
		if ((int) $this->user->must_change_password === 1 && $this->router->fetch_class() !== 'akun')
		{
			redirect(site_url('warga/akun'), 'location', 303);
		}
		$this->layout_data = array(
			'user' => $this->user,
			'area' => 'warga',
			'unread_notifications' => $this->notifications->unread_count($this->user->id),
			'page_title' => 'Dashboard Warga',
			'nav_active' => $this->router->fetch_class(),
		);
	}

	protected function redirect_to_login()
	{
		$next = app_safe_redirect_path('/'.uri_string(), '/warga');
		if ($this->auth->end_reason)
		{
			$this->session->set_flashdata('flash', array('type' => 'warning', 'message' => 'Sesi Anda telah berakhir. Silakan masuk kembali.'));
		}
		$this->session->set_userdata('login_next', $next);
		redirect(site_url('masuk'), 'location', 303);
	}

	protected function error_layout()
	{
		return 'dashboard';
	}
}
