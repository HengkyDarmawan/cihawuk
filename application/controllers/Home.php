<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Beranda: seluruh susunan berasal dari snapshot publikasi CMS.
 * Editor dengan mode pratinjau melihat susunan draft, ditandai badge pratinjau.
 */
class Home extends Public_Controller {

	public function index()
	{
		$this->load->library('CmsService', NULL, 'cms');
		$this->load->library('CmsPublicationService', NULL, 'publications');

		$layout = $this->publications->published_layout('home');
		$preview = FALSE;
		// Mode pratinjau berasal dari Public_Controller (butuh login + content.edit + diaktifkan dari dashboard).
		if ($layout === NULL && $this->preview_mode)
		{
			$page = $this->cms->page('home');
			if ($page)
			{
				$layout = $this->publications->draft_layout($page);
				$preview = TRUE;
			}
		}
		$layout = $layout ?: array('page' => array(), 'sections' => array());
		$layout['sections'] = $this->attach_section_data($layout['sections']);
		$assets = $this->section_assets($layout['sections']);
		$types = array_map(function ($section) { return $section['type']; }, $layout['sections']);

		$this->render('site/home', array_merge(array(
			'transparent_header' => in_array('hero', $types, TRUE),
			'layout' => $layout,
			'preview_layout' => $preview,
			'three_enabled' => (bool) $this->config->item('features', 'app')['three_hero'],
			'elevation' => $this->content->latest_statistic('elevation_masl'),
		), $assets));
	}
}
