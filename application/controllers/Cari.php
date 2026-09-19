<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pencarian konten publik terbit saja. Tidak pernah mencari tiket, akun,
 * NIK, atau dokumen privat.
 */
class Cari extends Public_Controller {

	public function index()
	{
		$keyword = trim((string) $this->input->get('q'));
		$keyword = mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $keyword), 0, 80);
		$results = array();
		$limited = FALSE;

		if ($keyword !== '')
		{
			$wait = $this->rate_limiter->attempt('search_ip', (string) $this->input->ip_address());
			if ($wait > 0)
			{
				$limited = TRUE;
			}
			elseif (mb_strlen($keyword) >= 3)
			{
				$results = array_merge($this->cms_page_results($keyword), $this->content->search($keyword, 30));
			}
		}

		$this->render('site/cari', array(
			'page_title' => 'Pencarian',
			'keyword' => $keyword,
			'results' => $results,
			'limited' => $limited,
			'noindex' => TRUE,
		), 'site');
	}

	/**
	 * Halaman CMS hanya diindeks bila sudah terbit dan tidak ditandai "jangan indeks".
	 * Draft, halaman ditarik, dan halaman arsip tidak pernah muncul di hasil pencarian.
	 */
	protected function cms_page_results($keyword)
	{
		$this->load->library('CmsService', NULL, 'cms');
		$out = array();
		foreach ($this->cms->published_pages() as $page)
		{
			if ($page->page_key === 'home' OR (int) $page->search_indexable !== 1)
			{
				continue;
			}
			if (mb_stripos($page->title, $keyword) === FALSE && mb_stripos((string) $page->summary, $keyword) === FALSE)
			{
				continue;
			}
			$out[] = array('type' => 'Halaman', 'title' => $page->title, 'summary' => $page->summary,
				'url' => '/'.$page->path, 'date' => $page->published_at);
		}
		return $out;
	}
}
