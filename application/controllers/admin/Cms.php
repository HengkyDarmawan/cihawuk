<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pengaturan beranda dan halaman publik (modul-backend §7, §8, §17).
 *
 * Pemisahan izin: menyusun draft memakai `cms.page.edit`, mengajukan review
 * `cms.page.submit_review`, menyetujui `cms.page.approve`, menerbitkan `cms.page.publish`,
 * menarik `cms.page.unpublish`, mengembalikan versi `cms.page.rollback`.
 * Menyembunyikan tombol bukan kontrol akses: setiap aksi diperiksa ulang di sini.
 */
class Cms extends Admin_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('CmsService', NULL, 'cms');
		$this->load->library('CmsPublicationService', NULL, 'publications');
		$this->layout_data['nav_active'] = 'cms';
	}

	// ------------------------------------------------------------------
	// Pengaturan beranda
	// ------------------------------------------------------------------

	public function home()
	{
		$this->require_permission('cms.page.view');
		$page = $this->cms->page('home');
		if ( ! $page)
		{
			$this->not_found_response();
			return;
		}
		$this->layout_data['nav_active'] = 'cms-beranda';
		$this->render('admin/cms_home', $this->page_data($page, array(
			'page_title' => 'Pengaturan Beranda',
		)), 'dashboard');
	}

	public function section($public_id)
	{
		$this->require_permission('cms.page.edit');
		$section = $this->cms->section($public_id);
		if ( ! $section)
		{
			$this->not_found_response();
			return;
		}
		$page = $this->db->get_where('cms_pages', array('id' => (int) $section->page_id))->row();
		$definition = $this->cms->section_type($section->section_type);
		$this->layout_data['nav_active'] = 'cms-beranda';
		$this->render('admin/cms_section', array(
			'page_title' => 'Section: '.$definition['label'],
			'page' => $page,
			'section' => $section,
			'definition' => $definition,
			'media' => $this->media_options(),
			'indicators' => $this->indicator_options(),
			'datasets' => $this->dataset_options(),
			'dataset_series' => $this->dataset_series_options($section->config['dataset_slug'] ?? ''),
			'potentials' => $this->potential_options(),
			'galleries' => $this->gallery_options(),
			'available' => $this->cms->section_available($section->section_type),
		), 'dashboard');
	}

	public function save_section($public_id)
	{
		$this->require_method('post');
		$this->require_permission('cms.page.edit');
		$section = $this->cms->section($public_id);
		if ( ! $section)
		{
			$this->not_found_response();
			return;
		}
		$this->cms->save_section($section, $this->section_input(), (int) $this->user->id);
		$this->flash('success', 'Section disimpan sebagai draft. Terbitkan halaman agar perubahan tampil publik.');
		redirect(site_url('admin/cms/section/'.rawurlencode($public_id)), 'location', 303);
	}

	public function add_section()
	{
		$this->require_method('post');
		$this->require_permission('cms.page.create');
		$page = $this->require_page((string) $this->input->post('page_key'));
		$section = $this->cms->add_section($page, (string) $this->input->post('section_type'), (int) $this->user->id);
		$this->flash('success', 'Section ditambahkan sebagai draft nonaktif. Atur isinya lalu aktifkan.');
		redirect(site_url('admin/cms/section/'.rawurlencode($section->public_id)), 'location', 303);
	}

	public function toggle_section($public_id)
	{
		$this->require_method('post');
		$this->require_permission('cms.page.edit');
		$section = $this->cms->section($public_id);
		if ( ! $section)
		{
			$this->not_found_response();
			return;
		}
		$this->cms->toggle_section($section, (bool) $this->input->post('enabled'), (int) $this->user->id);
		$this->flash('success', 'Status section diperbarui pada draft.');
		$this->back_to_page($section->page_id);
	}

	public function archive_section($public_id)
	{
		$this->require_method('post');
		$this->require_permission('cms.page.edit');
		$section = $this->cms->section($public_id);
		if ( ! $section)
		{
			$this->not_found_response();
			return;
		}
		$this->cms->archive_section($section, (int) $this->user->id);
		$this->flash('success', 'Section diarsipkan. Riwayat versinya tetap tersimpan.');
		$this->back_to_page($section->page_id);
	}

	public function reorder()
	{
		$this->require_method('post');
		$this->require_permission('cms.page.edit');
		$page = $this->require_page((string) $this->input->post('page_key'));
		$order = $this->input->post('order');
		if ( ! is_array($order))
		{
			$order = array_filter(array_map('trim', explode(',', (string) $order)));
		}
		$this->cms->reorder_sections($page, $order, (int) $this->user->id);
		$this->flash('success', 'Urutan section disimpan pada draft.');
		$this->back_to_page($page->id);
	}

	/** Naik/turun satu posisi: alternatif keyboard untuk drag-and-drop. */
	public function move_section($public_id)
	{
		$this->require_method('post');
		$this->require_permission('cms.page.edit');
		$section = $this->cms->section($public_id);
		if ( ! $section)
		{
			$this->not_found_response();
			return;
		}
		$page = $this->db->get_where('cms_pages', array('id' => (int) $section->page_id))->row();
		$ids = array();
		foreach ($this->cms->sections($page->id) as $row)
		{
			$ids[] = $row->public_id;
		}
		$index = array_search($section->public_id, $ids, TRUE);
		$target = $index + ((string) $this->input->post('direction') === 'up' ? -1 : 1);
		if ($index !== FALSE && isset($ids[$target]))
		{
			$swap = $ids[$target];
			$ids[$target] = $ids[$index];
			$ids[$index] = $swap;
			$this->cms->reorder_sections($page, $ids, (int) $this->user->id);
			$this->flash('success', 'Urutan section diperbarui.');
		}
		$this->back_to_page($page->id);
	}

	// ------------------------------------------------------------------
	// Halaman
	// ------------------------------------------------------------------

	public function pages()
	{
		$this->require_permission('cms.page.view');
		$this->layout_data['nav_active'] = 'cms-halaman';
		$this->render('admin/cms_pages', array(
			'page_title' => 'Halaman Publik',
			'pages' => $this->cms->pages(),
			'templates' => $this->cms->templates(),
		), 'dashboard');
	}

	public function page($public_id)
	{
		$this->require_permission('cms.page.view');
		$page = $this->cms->page_by_public_id($public_id);
		if ( ! $page)
		{
			$this->not_found_response();
			return;
		}
		$this->layout_data['nav_active'] = $page->page_key === 'home' ? 'cms-beranda' : 'cms-halaman';
		$this->render('admin/cms_page', $this->page_data($page, array(
			'page_title' => 'Halaman: '.$page->page_key,
		)), 'dashboard');
	}

	public function create_page()
	{
		$this->require_method('post');
		$this->require_permission('cms.page.create');
		$page = $this->cms->create_page($this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id);
		$this->flash('success', 'Halaman dibuat sebagai draft.');
		redirect(site_url('admin/cms/halaman/'.rawurlencode($page->public_id)), 'location', 303);
	}

	public function save_page($public_id)
	{
		$this->require_method('post');
		$this->require_permission('cms.page.edit');
		$page = $this->cms->page_by_public_id($public_id);
		if ( ! $page)
		{
			$this->not_found_response();
			return;
		}
		$this->cms->save_page_version($page, array(
			'nav_title' => $this->post_string('nav_title', 120),
			'title' => $this->post_string('title', 180),
			'slug' => $this->post_string('slug', 180),
			'summary' => $this->post_string('summary', 500),
			'template_code' => (string) $this->input->post('template_code'),
			'seo_title' => $this->post_string('seo_title', 200),
			'seo_description' => $this->post_string('seo_description', 300),
			'cover_media_id' => (int) $this->input->post('cover_media_id'),
			'search_indexable' => (bool) $this->input->post('search_indexable'),
		), (int) $this->user->id);
		$this->flash('success', 'Versi draft halaman disimpan.');
		redirect(site_url('admin/cms/halaman/'.rawurlencode($public_id)), 'location', 303);
	}

	// ------------------------------------------------------------------
	// Alur publikasi
	// ------------------------------------------------------------------

	public function workflow($public_id, $action)
	{
		$this->require_method('post');
		$page = $this->cms->page_by_public_id($public_id);
		if ( ! $page)
		{
			$this->not_found_response();
			return;
		}
		$reason = $this->post_string('reason', 500);

		switch ($action)
		{
			case 'ajukan':
				$this->require_permission('cms.page.submit_review');
				$this->publications->submit_review($page, (int) $this->user->id);
				$message = 'Halaman diajukan untuk direview.';
				break;

			case 'minta-perbaikan':
				$this->require_permission('cms.page.approve');
				$this->publications->request_changes($page, $reason, (int) $this->user->id);
				$message = 'Permintaan perbaikan dikirim ke penyusun.';
				break;

			case 'setujui':
				$this->require_permission('cms.page.approve');
				$this->publications->approve($page, (int) $this->user->id);
				$message = 'Halaman disetujui. Penerbitan masih merupakan langkah terpisah.';
				break;

			case 'terbitkan':
				$this->require_permission('cms.page.publish');
				$revision = $this->publications->publish($page, $reason, (int) $this->user->id);
				$message = 'Halaman diterbitkan sebagai revisi '.$revision.'.';
				break;

			case 'jadwalkan':
				$this->require_permission('cms.page.publish');
				$run_at = $this->publications->schedule($page, (string) $this->input->post('run_at'), $reason, (int) $this->user->id);
				$message = 'Publikasi dijadwalkan pada '.format_wib($run_at).'.';
				break;

			case 'tarik':
				$this->require_permission('cms.page.unpublish');
				$this->publications->unpublish($page, $reason, (int) $this->user->id);
				$message = 'Halaman ditarik dari publik. Riwayat publikasi tetap tersimpan.';
				break;

			case 'arsipkan':
				$this->require_permission('cms.page.unpublish');
				$this->publications->archive($page, $reason, (int) $this->user->id);
				$message = 'Halaman diarsipkan.';
				break;

			case 'rollback':
				$this->require_permission('cms.page.rollback');
				$revision = $this->publications->rollback($page, (int) $this->input->post('snapshot_id'), $reason, (int) $this->user->id);
				$message = 'Publikasi dikembalikan ke versi sebelumnya sebagai revisi '.$revision.'.';
				break;

			default:
				$this->not_found_response();
				return;
		}

		$this->flash('success', $message);
		$this->back_to_page($page->id);
	}

	/** Buka pratinjau draft memakai template publik yang sama. */
	public function preview_link($public_id)
	{
		$this->require_method('post');
		$this->require_permission('cms.page.view');
		$page = $this->cms->page_by_public_id($public_id);
		if ( ! $page)
		{
			$this->not_found_response();
			return;
		}
		$token = $this->publications->preview_token($page, (int) $this->user->id);
		redirect(site_url('admin/pratinjau/'.rawurlencode($token)), 'location', 303);
	}

	// ------------------------------------------------------------------
	// Menu publik (modul-backend §9.3)
	// ------------------------------------------------------------------

	public function menus()
	{
		$this->require_permission('cms.menu.manage');
		$this->load->library('CmsMenuService', NULL, 'menu_service');
		$menus = array();
		foreach ($this->menu_service->locations() as $location => $label)
		{
			$menu = $this->menu_service->ensure_menu($location, $label);
			$menu->item_count = $this->db->where('menu_id', (int) $menu->id)->count_all_results('cms_menu_items');
			$menus[$location] = $menu;
		}
		$this->layout_data['nav_active'] = 'cms-menu';
		$this->render('admin/cms_menus', array(
			'page_title' => 'Menu dan Navigasi',
			'menus' => $menus,
			'locations' => $this->menu_service->locations(),
		), 'dashboard');
	}

	public function menu($location)
	{
		$this->require_permission('cms.menu.manage');
		$this->load->library('CmsMenuService', NULL, 'menu_service');
		if ( ! isset($this->menu_service->locations()[$location]))
		{
			$this->not_found_response();
			return;
		}
		$menu = $this->menu_service->ensure_menu($location);
		$this->layout_data['nav_active'] = 'cms-menu';
		$this->render('admin/cms_menu', array(
			'page_title' => 'Menu: '.$this->menu_service->locations()[$location],
			'menu' => $menu,
			'locations' => $this->menu_service->locations(),
			'items' => $this->menu_service->ordered_items($menu->id),
			'tree' => $this->menu_service->draft_tree($menu),
			'link_types' => CmsMenuService::LINK_TYPES,
			'pages' => $this->cms->published_pages(),
			'snapshots' => $this->menu_service->snapshots($menu),
			'can_publish' => $this->authz->can('cms.page.publish'),
			'extra_js' => array('vendor/sweetalert2/sweetalert2.min.js'),
		), 'dashboard');
	}

	public function save_menu_item($location)
	{
		$this->require_method('post');
		$this->require_permission('cms.menu.manage');
		$menu = $this->require_menu($location);
		$item_id = $this->post_string('item_id', 32);
		$item = ($item_id === '') ? NULL : $this->menu_service->item($item_id);
		$this->menu_service->save_item($menu, array(
			'label' => $this->post_string('label', 80),
			'link_type' => (string) $this->input->post('link_type'),
			'route_path' => $this->post_string('route_path', 191),
			'cms_page_id' => $this->post_string('cms_page_id', 32),
			'external_url' => $this->post_string('external_url', 500),
			'parent_id' => $this->post_string('parent_id', 32),
			'is_enabled' => $this->input->post('is_enabled') ? 1 : 0,
		), (int) $this->user->id, $item);
		$this->flash('success', 'Item menu disimpan sebagai draft. Terbitkan menu agar tampil di situs.');
		redirect(site_url('admin/cms/menu/'.rawurlencode($location)), 'location', 303);
	}

	public function menu_item_action($public_id, $action)
	{
		$this->require_method('post');
		$this->require_permission('cms.menu.manage');
		$this->load->library('CmsMenuService', NULL, 'menu_service');
		$item = $this->menu_service->item($public_id);
		if ( ! $item)
		{
			$this->not_found_response();
			return;
		}
		$menu = $this->db->get_where('cms_menus', array('id' => (int) $item->menu_id))->row();
		switch ($action)
		{
			case 'hapus':
				$this->menu_service->delete_item($menu, $item);
				$message = 'Item menu dihapus dari draft.';
				break;
			case 'naik':
			case 'turun':
				$this->menu_service->move_item($menu, $item, $action === 'naik' ? 'up' : 'down');
				$message = 'Urutan item diperbarui.';
				break;
			default:
				$this->not_found_response();
				return;
		}
		$this->flash('success', $message);
		redirect(site_url('admin/cms/menu/'.rawurlencode($menu->location)), 'location', 303);
	}

	public function reorder_menu($location)
	{
		$this->require_method('post');
		$this->require_permission('cms.menu.manage');
		$menu = $this->require_menu($location);
		$ids = array();
		foreach (explode(',', (string) $this->input->post('order')) as $public_id)
		{
			$public_id = trim($public_id);
			if ($public_id === '')
			{
				continue;
			}
			$item = $this->menu_service->item($public_id);
			if ($item && (int) $item->menu_id === (int) $menu->id)
			{
				$ids[] = (int) $item->id;
			}
		}
		$this->menu_service->apply_order($menu, $ids);
		$this->flash('success', 'Urutan menu disimpan.');
		redirect(site_url('admin/cms/menu/'.rawurlencode($location)), 'location', 303);
	}

	public function publish_menu($location)
	{
		$this->require_method('post');
		$this->require_permission('cms.page.publish');
		$menu = $this->require_menu($location);
		$revision = $this->menu_service->publish($menu, $this->post_string('reason', 500), (int) $this->user->id);
		$this->flash('success', 'Menu diterbitkan sebagai revisi '.$revision.'.');
		redirect(site_url('admin/cms/menu/'.rawurlencode($location)), 'location', 303);
	}

	public function rollback_menu($location)
	{
		$this->require_method('post');
		$this->require_permission('cms.page.rollback');
		$menu = $this->require_menu($location);
		$revision = $this->menu_service->rollback($menu, (int) $this->input->post('snapshot_id'),
			$this->post_string('reason', 500), (int) $this->user->id);
		$this->flash('success', 'Menu dikembalikan ke revisi sebelumnya sebagai revisi '.$revision.'.');
		redirect(site_url('admin/cms/menu/'.rawurlencode($location)), 'location', 303);
	}

	protected function require_menu($location)
	{
		$this->load->library('CmsMenuService', NULL, 'menu_service');
		if ( ! isset($this->menu_service->locations()[$location]))
		{
			throw new DomainRuleException('Lokasi menu tidak dikenal.', 404);
		}
		return $this->menu_service->ensure_menu($location);
	}

	// ------------------------------------------------------------------
	// Identitas situs dan tema (modul-backend §9.1, §9.2)
	// ------------------------------------------------------------------

	public function site()
	{
		$this->require_permission('cms.site.manage');
		$this->load->library('SiteSettingsService', NULL, 'site_service');
		$this->layout_data['nav_active'] = 'cms-situs';
		$this->render('admin/cms_site', array(
			'page_title' => 'Identitas dan Tema Situs',
			'draft' => $this->site_service->draft(),
			'published' => $this->site_service->published(),
			'dirty' => $this->site_service->has_unpublished_changes(),
			'fonts' => SiteSettingsService::FONTS,
			'radius_presets' => SiteSettingsService::RADIUS,
			'hero_modes' => SiteSettingsService::HERO_MODES,
			'media' => $this->media_options(),
			'snapshots' => $this->site_service->snapshots(),
			'can_publish' => $this->authz->can('cms.page.publish'),
			'extra_js' => array('vendor/sweetalert2/sweetalert2.min.js'),
		), 'dashboard');
	}

	public function save_site()
	{
		$this->require_method('post');
		$this->require_permission('cms.site.manage');
		$this->load->library('SiteSettingsService', NULL, 'site_service');
		$this->site_service->save_draft(array(
			'identity' => array(
				'site_name' => $this->post_string('site_name', 120),
				'village_name' => $this->post_string('village_name', 80),
				'district' => $this->post_string('district', 80),
				'regency' => $this->post_string('regency', 80),
				'province' => $this->post_string('province', 80),
				'office_address' => $this->post_string('office_address', 250),
				'contact_phone' => $this->post_string('contact_phone', 40),
				'contact_email' => $this->post_string('contact_email', 120),
				'service_hours' => $this->post_string('service_hours', 160),
				'footer_text' => $this->post_string('footer_text', 300),
				'privacy_contact' => $this->post_string('privacy_contact', 160),
				'social' => array(
					'facebook' => $this->post_string('social_facebook', 300),
					'instagram' => $this->post_string('social_instagram', 300),
					'youtube' => $this->post_string('social_youtube', 300),
					'whatsapp' => $this->post_string('social_whatsapp', 300),
				),
				'logo_media_id' => $this->input->post('logo_media_id'),
				'favicon_media_id' => $this->input->post('favicon_media_id'),
				'social_image_media_id' => $this->input->post('social_image_media_id'),
			),
			'theme' => array(
				'color_primary' => $this->post_string('color_primary', 7),
				'color_secondary' => $this->post_string('color_secondary', 7),
				'color_accent' => $this->post_string('color_accent', 7),
				'color_surface' => $this->post_string('color_surface', 7),
				'font_body' => (string) $this->input->post('font_body'),
				'font_display' => (string) $this->input->post('font_display'),
				'radius' => (string) $this->input->post('radius'),
				'hero_mode' => (string) $this->input->post('hero_mode'),
				'reduced_motion_default' => (bool) $this->input->post('reduced_motion_default'),
				'placeholder_media_id' => $this->input->post('placeholder_media_id'),
			),
		), (int) $this->user->id);
		$this->flash('success', 'Tersimpan sebagai draft. Situs publik belum berubah sampai diterbitkan.');
		redirect(site_url('admin/cms/situs'), 'location', 303);
	}

	public function publish_site()
	{
		$this->require_method('post');
		$this->require_permission('cms.page.publish');
		$this->load->library('SiteSettingsService', NULL, 'site_service');
		$revision = $this->site_service->publish($this->post_string('reason', 500), (int) $this->user->id);
		$this->flash('success', 'Identitas dan tema diterbitkan sebagai revisi '.$revision.'.');
		redirect(site_url('admin/cms/situs'), 'location', 303);
	}

	public function rollback_site()
	{
		$this->require_method('post');
		$this->require_permission('cms.page.rollback');
		$this->load->library('SiteSettingsService', NULL, 'site_service');
		$revision = $this->site_service->rollback((int) $this->input->post('snapshot_id'),
			$this->post_string('reason', 500), (int) $this->user->id);
		$this->flash('success', 'Identitas dan tema dikembalikan sebagai revisi '.$revision.'.');
		redirect(site_url('admin/cms/situs'), 'location', 303);
	}

	// ------------------------------------------------------------------
	// Helper
	// ------------------------------------------------------------------

	protected function require_page($page_key)
	{
		$page = $this->cms->page($page_key);
		if ( ! $page)
		{
			throw new DomainRuleException('Halaman tidak ditemukan.', 404);
		}
		return $page;
	}

	protected function back_to_page($page_id)
	{
		$page = $this->db->get_where('cms_pages', array('id' => (int) $page_id))->row();
		$url = ($page && $page->page_key === 'home')
			? 'admin/cms/beranda'
			: 'admin/cms/halaman/'.rawurlencode($page ? $page->public_id : '');
		redirect(site_url($url), 'location', 303);
	}

	protected function page_data($page, array $extra = array())
	{
		$version = $page->current_version_id ? $this->cms->version($page->current_version_id) : NULL;
		return array_merge(array(
			'page' => $page,
			'version' => $version,
			'sections' => $this->cms->sections($page->id),
			'section_types' => $this->cms->available_section_types(),
			'templates' => $this->cms->templates(),
			'snapshots' => $this->publications->snapshots($page),
			'reviews' => $this->publications->review_history($page),
			'schedules' => $this->db->where(array('object_type' => 'cms_page', 'object_id' => (int) $page->id))
				->order_by('id', 'DESC')->limit(5)->get('scheduled_publications')->result(),
			'media' => $this->media_options(),
			'extra_js' => array('vendor/sweetalert2/sweetalert2.min.js'),
		), $extra);
	}

	/** Susun konfigurasi dari POST sesuai tipe field pada registry. */
	protected function section_input()
	{
		$input = array(
			'title' => $this->post_string('title', 180),
			'subtitle' => $this->post_string('subtitle', 300),
			'layout_variant' => (string) $this->input->post('layout_variant'),
		);
		foreach ((array) $this->input->post(NULL, FALSE) as $key => $value)
		{
			if (in_array($key, array('title', 'subtitle', 'layout_variant', 'csrf_chw'), TRUE))
			{
				continue;
			}
			$input[$key] = $value;
		}
		// Tautan dikirim sebagai tiga array sejajar agar mudah dibaca tanpa JavaScript.
		$labels = (array) $this->input->post('link_label');
		$urls = (array) $this->input->post('link_url');
		$descriptions = (array) $this->input->post('link_description');
		if ($labels)
		{
			$links = array();
			foreach ($labels as $i => $label)
			{
				$links[] = array(
					'label' => (string) $label,
					'url' => (string) ($urls[$i] ?? ''),
					'description' => (string) ($descriptions[$i] ?? ''),
				);
			}
			$input['cta'] = $links;
			$input['links'] = $links;
		}
		return $input;
	}

	protected function media_options()
	{
		$options = array('' => 'Tanpa media');
		foreach ($this->db->where('deleted_at IS NULL', NULL, FALSE)->where('publication_status', 'published')
			->order_by('id', 'DESC')->limit(100)->get('media_assets')->result() as $media)
		{
			$options[(string) $media->id] = $media->original_name.' ('.$media->mime_type.')';
		}
		return $options;
	}

	protected function indicator_options()
	{
		$options = array();
		foreach ($this->db->order_by('display_order')->get('statistic_indicators')->result() as $indicator)
		{
			$options[$indicator->code] = $indicator->label.' ('.$indicator->code.')';
		}
		return $options;
	}

	/** Dataset yang sudah terbit; hanya ini yang boleh dipakai section statistik. */
	protected function dataset_options()
	{
		$this->load->library('DatasetService', NULL, 'datasets');
		$options = array();
		foreach ($this->datasets->published_datasets() as $snapshot)
		{
			$options[$snapshot['dataset']['slug']] = $snapshot['dataset']['name'].' ('.$snapshot['version']['period_year'].')';
		}
		return $options;
	}

	/** Seri milik dataset yang sedang dipilih; daftar ikut berubah setelah section disimpan. */
	protected function dataset_series_options($slug)
	{
		$this->load->library('DatasetService', NULL, 'datasets');
		$snapshot = $slug ? $this->datasets->published_dataset($slug) : NULL;
		$options = array();
		if ($snapshot)
		{
			foreach ($snapshot['series'] as $series)
			{
				$options[$series['code']] = $series['label'].' ('.$series['code'].')';
			}
		}
		return $options;
	}

	protected function potential_options()
	{
		$options = array();
		foreach ($this->db->where('publication_status', 'published')->where('verification_status', 'verified')
			->where('deleted_at IS NULL', NULL, FALSE)->order_by('title')->get('potentials')->result() as $row)
		{
			$options[(string) $row->id] = $row->title;
		}
		return $options;
	}

	protected function gallery_options()
	{
		$options = array();
		foreach ($this->db->where('publication_status', 'published')->order_by('title')->get('galleries')->result() as $row)
		{
			$options[(string) $row->id] = $row->title;
		}
		return $options;
	}
}
