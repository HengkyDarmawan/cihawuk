<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Notifikasi extends Resident_Controller {

	public function index()
	{
		$this->render('shared/notifikasi', array(
			'page_title' => 'Notifikasi',
			'items' => $this->notifications->latest($this->user->id, 50),
			'read_url' => 'warga/notifikasi/baca',
		), 'dashboard');
	}

	public function read()
	{
		$this->require_method('post');
		$id = $this->input->post('id');
		$this->notifications->mark_read($this->user->id, $id === NULL ? NULL : (int) $id);
		if ($this->is_ajax())
		{
			$this->json(200, array('success' => TRUE, 'message' => 'Notifikasi ditandai dibaca.'));
			return;
		}
		redirect(site_url('warga/notifikasi'), 'location', 303);
	}
}
