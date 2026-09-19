<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pratinjau draft halaman CMS memakai template publik yang sama (modul-backend §17.1).
 *
 * Syarat: login, permission `cms.page.view`, dan token bertanda tangan yang masih berlaku
 * serta terikat pada pengguna itu sendiri. Halaman memakai `no-store` dan `noindex`,
 * tidak pernah menjadi tautan publik, dan tidak mengubah apa pun.
 */
class Pratinjau extends Public_Controller {

	public function show($token = '')
	{
		$this->start_session();
		if ( ! $this->auth->check())
		{
			$this->session->set_userdata('login_next', '/admin/cms/beranda');
			redirect(site_url('masuk'), 'location', 303);
			return;
		}
		if ( ! $this->authz->can('cms.page.view'))
		{
			throw new AccessDeniedException('Preview requires cms.page.view');
		}

		$this->load->library('CmsService', NULL, 'cms');
		$this->load->library('CmsPublicationService', NULL, 'publications');
		$page = $this->publications->verify_preview_token($token, (int) $this->auth->user()->id);
		if ( ! $page)
		{
			$this->no_store = TRUE;
			$this->respond_error(410, 'Tautan pratinjau sudah kedaluwarsa atau tidak berlaku. Buka ulang dari dashboard.');
			return;
		}

		$this->no_store = TRUE;
		$this->output->set_header('X-Robots-Tag: noindex, nofollow');

		$layout = $this->publications->draft_layout($page);
		$layout['sections'] = $this->attach_section_data($layout['sections']);
		$assets = $this->section_assets($layout['sections']);
		$types = array_map(function ($section) { return $section['type']; }, $layout['sections']);
		// Template yang dipakai sama persis dengan halaman publiknya.
		$view = ($page->page_key === 'home') ? 'site/home' : 'site/page';

		$this->render($view, array_merge(array(
			'transparent_header' => in_array('hero', $types, TRUE),
			'layout' => $layout,
			'preview_layout' => TRUE,
			'preview_mode' => TRUE,
			'three_enabled' => (bool) $this->config->item('features', 'app')['three_hero'],
			'elevation' => $this->content->latest_statistic('elevation_masl'),
			'page_title' => 'Pratinjau: '.($layout['page']['title'] ?? $page->page_key),
		), $assets));
	}
}
