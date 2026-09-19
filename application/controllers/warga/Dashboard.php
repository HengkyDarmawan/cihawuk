<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends Resident_Controller {

	public function index()
	{
		$this->load->model('Ticket_model', 'tickets');
		$this->render('warga/dashboard', array(
			'page_title' => 'Beranda',
			'summary' => $this->tickets->reporter_summary($this->user->id),
			'recent' => $this->tickets->for_reporter($this->user->id, array(), 5),
			'needs_response' => $this->tickets->for_reporter($this->user->id, array('status' => 'awaiting_confirmation'), 5),
			'needs_info' => $this->tickets->for_reporter($this->user->id, array('status' => 'needs_information'), 5),
			'notifications' => $this->notifications->latest($this->user->id, 5),
			'profile' => $this->user_model->resident_profile($this->user->id),
		), 'dashboard');
	}
}
