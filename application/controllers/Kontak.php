<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Kontak extends Public_Controller {

	public function index()
	{
		$office = $this->content->map_features(array('office'));
		$this->render('site/kontak', array(
			'page_title' => 'Kontak',
			'contact' => $this->settings->get('site.contact'),
			'hours' => $this->settings->get('site.service_hours'),
			'office' => $office,
			'extra_css' => $office ? array('vendor/leaflet/leaflet.css') : array(),
			'extra_js' => $office ? array('vendor/leaflet/leaflet.js', 'site/js/map.js') : array(),
		), 'site');
	}
}
