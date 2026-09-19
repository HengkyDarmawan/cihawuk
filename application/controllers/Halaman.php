<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Halaman kebijakan (privasi, ketentuan) dan halaman publik hasil CMS.
 *
 * Halaman CMS dilayani dari snapshot yang diterbitkan. Halaman draft, ditarik, atau
 * diarsipkan menghasilkan 404 — bukan halaman kosong. Slug lama yang pernah terbit
 * diarahkan 301 ke alamat baru lewat `cms_redirects`.
 */
class Halaman extends Public_Controller {

	public function privasi()
	{
		$this->render('site/privasi', array(
			'page_title' => 'Kebijakan Privasi',
			// Kontak privasi dari identitas situs bila diisi; bila kosong tetap memakai pengaturan lama.
			'contact' => ($this->site['identity']['privacy_contact'] ?? '') !== ''
				? array('name' => $this->site['identity']['privacy_contact'], 'channel' => '', 'confirmed' => FALSE)
				: $this->settings->get('site.privacy_contact'),
			'limits' => $this->config->item('session_limits', 'app'),
		), 'site');
	}

	public function ketentuan()
	{
		$this->render('site/ketentuan', array('page_title' => 'Ketentuan Layanan'), 'site');
	}

	/** Halaman CMS pada '/{slug}' atau '/{induk}/{anak}'. */
	public function show($segment = '', $child = NULL)
	{
		$path = trim($segment.($child === NULL ? '' : '/'.$child), '/');
		if ($path === '')
		{
			$this->not_found_response();
			return;
		}

		$this->load->library('CmsService', NULL, 'cms');
		$this->load->library('CmsPublicationService', NULL, 'publications');

		$page = $this->cms->page_by_path($path);
		if ( ! $page)
		{
			$redirect = $this->db->get_where('cms_redirects', array('old_path' => '/'.$path, 'active' => 1))->row();
			if ($redirect && $redirect->new_path !== '/'.$path)
			{
				redirect(site_url(ltrim($redirect->new_path, '/')), 'location', (int) $redirect->status_code === 302 ? 302 : 301);
				return;
			}
			$this->not_found_response();
			return;
		}

		$layout = $this->publications->published_layout($page->page_key);
		if ($layout === NULL)
		{
			$this->not_found_response();
			return;
		}
		$layout['sections'] = $this->attach_section_data($layout['sections']);
		$assets = $this->section_assets($layout['sections']);
		$types = array_map(function ($section) { return $section['type']; }, $layout['sections']);

		$this->render('site/page', array_merge(array(
			'page_title' => $layout['page']['seo_title'] ?: $layout['page']['title'],
			'meta_description' => $layout['page']['seo_description'] ?: $layout['page']['summary'],
			'noindex' => empty($layout['page']['search_indexable']),
			'transparent_header' => in_array('hero', $types, TRUE),
			'layout' => $layout,
			'preview_layout' => FALSE,
			'three_enabled' => (bool) $this->config->item('features', 'app')['three_hero'],
			'elevation' => $this->content->latest_statistic('elevation_masl'),
		), $assets), 'site');
	}
}
