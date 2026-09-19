<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Sitemap XML hanya memuat halaman publik terbit. Halaman layanan/akun
 * (lapor, lacak, warga, admin) tidak pernah dimasukkan.
 */
class Sitemap extends Public_Controller {

	public function index()
	{
		$urls = array(
			array('loc' => site_url('/'), 'priority' => '1.0'),
			array('loc' => site_url('profil'), 'priority' => '0.8'),
			array('loc' => site_url('pemerintahan'), 'priority' => '0.6'),
			array('loc' => site_url('potensi'), 'priority' => '0.8'),
			array('loc' => site_url('data-desa'), 'priority' => '0.7'),
			array('loc' => site_url('berita'), 'priority' => '0.7'),
			array('loc' => site_url('agenda'), 'priority' => '0.6'),
			array('loc' => site_url('galeri'), 'priority' => '0.5'),
			array('loc' => site_url('dokumen'), 'priority' => '0.5'),
			array('loc' => site_url('layanan'), 'priority' => '0.9'),
			array('loc' => site_url('kontak'), 'priority' => '0.5'),
			array('loc' => site_url('privasi'), 'priority' => '0.3'),
			array('loc' => site_url('ketentuan'), 'priority' => '0.3'),
		);
		// Halaman CMS terbit; halaman yang ditandai tidak boleh diindeks dilewati.
		$this->load->library('CmsService', NULL, 'cms');
		foreach ($this->cms->published_pages() as $page)
		{
			if ($page->page_key === 'home' OR (int) $page->search_indexable !== 1)
			{
				continue;
			}
			$urls[] = array('loc' => site_url($page->path), 'lastmod' => $page->published_at, 'priority' => '0.7');
		}
		foreach ($this->content->posts(array('news', 'announcement'), NULL, 200) as $post)
		{
			$urls[] = array('loc' => site_url('berita/'.rawurlencode($post->slug)), 'lastmod' => $post->updated_at, 'priority' => '0.6');
		}
		foreach ($this->content->potentials(NULL, 200) as $potential)
		{
			$urls[] = array('loc' => site_url('potensi/'.rawurlencode($potential->slug)), 'lastmod' => $potential->updated_at, 'priority' => '0.6');
		}
		foreach ($this->content->events(TRUE, 100) as $event)
		{
			$urls[] = array('loc' => site_url('agenda/'.rawurlencode($event->slug)), 'lastmod' => $event->updated_at, 'priority' => '0.4');
		}

		$xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
		foreach ($urls as $url)
		{
			$xml .= "\t<url>\n\t\t<loc>".htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8')."</loc>\n";
			if ( ! empty($url['lastmod']))
			{
				$xml .= "\t\t<lastmod>".gmdate('Y-m-d', strtotime($url['lastmod'].' UTC'))."</lastmod>\n";
			}
			$xml .= "\t\t<priority>".$url['priority']."</priority>\n\t</url>\n";
		}
		$xml .= '</urlset>';

		$this->output->set_content_type('application/xml', 'utf-8')->set_output($xml);
	}
}
