<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controller halaman publik. Sesi hanya dimulai pada controller yang membutuhkannya
 * (formulir laporan, pelacakan, autentikasi) agar halaman informasi tetap ringan.
 *
 * Navigasi, identitas situs, dan token tema dibaca dari snapshot publikasi CMS
 * (Tahap 3); bila menu belum pernah diterbitkan, navigasi lama `navigation_items`
 * dipakai sebagai cadangan agar situs tetap dapat dijelajahi.
 *
 * @property Content_model $content
 */
class Public_Controller extends MY_Controller {

	/** @var object|null profil desa terbit */
	protected $village = NULL;

	/** @var bool mode pratinjau draft untuk editor berizin */
	protected $preview_mode = FALSE;

	/** @var array identitas dan tema situs yang sedang terbit */
	protected $site = array();

	public function __construct()
	{
		parent::__construct();
		$this->load->model('Content_model', 'content');
		$this->load->library('SiteSettingsService', NULL, 'site_settings');
		$this->load->library('CmsMenuService', NULL, 'menus');
		$this->detect_preview_mode();
		$this->content->preview = $this->preview_mode;
		$this->village = $this->content->village_profile();
		$this->site = $this->site_settings->published();
		$this->layout_data = array(
			'village' => $this->village,
			'nav_items' => $this->menu_items('header', 'main'),
			'footer_items' => $this->menu_items('footer_primary', 'footer'),
			'preview_mode' => $this->preview_mode,
			'demo_mode' => (bool) $this->config->item('features', 'app')['demo_mode'],
			'body_class' => '',
			'page_title' => NULL,
			'meta_description' => $this->village ? $this->village->seo_description : NULL,
			'transparent_header' => FALSE,
			'site_settings' => $this->settings->public_values(),
			'site' => $this->site,
			'theme_css' => $this->site_settings->css_variables($this->site['theme']),
		);
	}

	/**
	 * Item menu dalam bentuk seragam array {label, href, children}.
	 * Sumber utama adalah snapshot menu CMS; `navigation_items` hanya cadangan.
	 */
	protected function menu_items($location, $legacy_key)
	{
		$published = $this->menus->published_menu($location);
		if (is_array($published))
		{
			return $published;
		}
		$items = array();
		foreach ($this->content->navigation($legacy_key) as $row)
		{
			$children = array();
			foreach ($row->children as $child)
			{
				$children[] = array('label' => $child->label, 'href' => $child->target_url, 'children' => array());
			}
			$items[] = array('label' => $row->label, 'href' => $row->target_url, 'children' => $children);
		}
		return $items;
	}

	/**
	 * Pratinjau draft hanya bila cookie sesi ada dan pengguna memiliki content.edit
	 * serta mengaktifkannya dari dashboard. Tidak pernah aktif hanya karena environment.
	 */
	protected function detect_preview_mode()
	{
		$cookie = $this->config->item('sess_cookie_name');
		if (empty($_COOKIE[$cookie]))
		{
			return;
		}
		$this->start_session();
		if ($this->auth->check() && $this->authz->can('content.edit') && $this->session->userdata('content_preview') === TRUE)
		{
			$this->preview_mode = TRUE;
			$this->no_store = TRUE;
			$this->output->set_header('X-Robots-Tag: noindex');
		}
	}

	// ------------------------------------------------------------------
	// Render halaman berbasis section CMS (beranda, halaman publik, pratinjau)
	// ------------------------------------------------------------------

	/** Data bisnis diambil saat render, bukan disalin ke dalam snapshot. */
	protected function attach_section_data(array $sections)
	{
		$this->load->library('CmsService', NULL, 'cms');
		foreach ($sections as &$section)
		{
			$section['data'] = $this->cms->section_data($section['type'], $section['config'] ?? array());
		}
		unset($section);
		return $sections;
	}

	/** Aset yang benar-benar dibutuhkan susunan section ini saja. */
	protected function section_assets(array $sections)
	{
		$css = array();
		$js = array();
		$features = $this->config->item('features', 'app');
		foreach ($sections as $section)
		{
			$type = $section['type'];
			$layout = $section['layout'] ?? '';
			if ($layout === 'cards.carousel')
			{
				$css['vendor/swiper/swiper-bundle.min.css'] = TRUE;
				$js['vendor/swiper/swiper-bundle.min.js'] = TRUE;
			}
			if ($type === 'verified_map' && ( ! empty($section['data']['features']) OR (app_env('MAP_TILE_URL') && ! empty($section['data']['center']))))
			{
				$css['vendor/leaflet/leaflet.css'] = TRUE;
				$js['vendor/leaflet/leaflet.js'] = TRUE;
				$js['site/js/map.js'] = TRUE;
			}
			if ($type === 'upcoming_agenda')
			{
				$css['site/css/calendar.css'] = TRUE;
				$js['site/js/calendar.js'] = TRUE;
			}
			if ($type === 'hero' && ($section['config']['mode'] ?? '') === 'three' && ! empty($features['three_hero']))
			{
				$js['site/js/hero-loader.js'] = TRUE;
			}
		}
		return array('extra_css' => array_keys($css), 'extra_js' => array_keys($js));
	}
}
