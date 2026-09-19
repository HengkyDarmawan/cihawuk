<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Potensi extends Public_Controller {

	public function index()
	{
		$slug = (string) $this->input->get('kategori');
		$categories = $this->content->potential_categories();
		$valid = NULL;
		foreach ($categories as $cat)
		{
			if ($cat->slug === $slug)
			{
				$valid = $slug;
			}
		}
		$this->render('site/potensi', array(
			'page_title' => 'Potensi Desa',
			'categories' => $categories,
			'active_category' => $valid,
			'potentials' => $this->content->potentials($valid),
		), 'site');
	}

	public function detail($slug)
	{
		$potential = $this->content->potential_by_slug($slug);
		if ( ! $potential)
		{
			$redirect = $this->content->redirect_for('potential', $slug);
			if ($redirect)
			{
				redirect(site_url('potensi/'.rawurlencode($redirect->new_slug)), 'location', 301);
				return;
			}
			$this->not_found_response();
			return;
		}
		$this->render('site/potensi_detail', array(
			'page_title' => $potential->title,
			'meta_description' => str_limit_id($potential->summary, 160),
			'potential' => $potential,
			'related' => $this->content->potentials($potential->category_slug, 4),
			'extra_css' => $potential->map_feature ? array('vendor/leaflet/leaflet.css') : array(),
			'extra_js' => $potential->map_feature ? array('vendor/leaflet/leaflet.js', 'site/js/map.js') : array(),
		), 'site');
	}
}
