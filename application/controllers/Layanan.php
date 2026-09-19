<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Layanan extends Public_Controller {

	public function index()
	{
		$this->load->model('Ticket_model', 'tickets');
		$this->render('site/layanan', array(
			'page_title' => 'Layanan Warga',
			'categories' => $this->tickets->active_categories(),
			'report_types' => $this->config->item('report_types', 'app'),
			'statuses' => $this->config->item('ticket_statuses', 'app'),
			'service_hours' => $this->settings->get('site.service_hours'),
		), 'site');
	}
}
