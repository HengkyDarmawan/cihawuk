<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Kontak extends Public_Controller {

	public function index()
	{
		$office = $this->content->map_features(array('office'));
		$center = $this->config->item('village_center', 'app');
		$needs_map = ! empty($office) || ($center && app_env('MAP_TILE_URL'));
		$this->render('site/kontak', array(
			'page_title' => 'Kontak',
			'contact' => $this->settings->get('site.contact'),
			'hours' => $this->settings->get('site.service_hours'),
			'office' => $office,
			'center' => $center,
			'extra_css' => $needs_map ? array('vendor/leaflet/leaflet.css') : array(),
			'extra_js' => $needs_map ? array('vendor/leaflet/leaflet.js', 'site/js/map.js') : array(),
		), 'site');
	}
}
