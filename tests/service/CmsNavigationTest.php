<?php

/**
 * Tahap 3 v1.2: menu navigasi, identitas/tema situs, dan invalidasi cache terarah.
 */
class CmsNavigationTest extends CiTestCase {

	/** @var object */
	protected $admin;

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('CmsService', NULL, 'cms');
		$this->CI->load->library('CmsPublicationService', NULL, 'publications');
		$this->CI->load->library('CmsMenuService', NULL, 'menu_service');
		$this->CI->load->library('SiteSettingsService', NULL, 'site_service');
		$this->truncate_service_data();
		$this->clean_test_menu();
		$this->clean_test_pages();
		$this->admin = $this->make_user('cms.nav.test', array('website_admin'));
	}

	protected function tearDown(): void
	{
		$this->clean_test_menu();
		$this->clean_test_pages();
		parent::tearDown();
	}

	/** Menu uji memakai lokasi `quick_link` supaya menu hasil seed tidak terganggu. */
	protected function clean_test_menu()
	{
		$menu = $this->CI->db->get_where('cms_menus', array('location' => 'quick_link'))->row();
		if ( ! $menu)
		{
			return;
		}
		$this->CI->db->where(array('target_type' => 'menu', 'target_id' => (int) $menu->id))->delete('cms_publication_snapshots');
		$this->CI->db->where('menu_id', (int) $menu->id)->where('parent_id IS NOT NULL', NULL, FALSE)->delete('cms_menu_items');
		$this->CI->db->where('menu_id', (int) $menu->id)->delete('cms_menu_items');
		$this->CI->db->where('id', (int) $menu->id)->delete('cms_menus');
	}

	protected function clean_test_pages()
	{
		foreach ($this->CI->db->where_in('page_key', array('uji_nav', 'uji_nav_anak'))->get('cms_pages')->result() as $page)
		{
			$this->CI->db->where(array('target_type' => 'page', 'target_id' => (int) $page->id))->delete('cms_publication_snapshots');
			$this->CI->db->where('id', (int) $page->id)->update('cms_pages', array('current_version_id' => NULL, 'published_version_id' => NULL));
			$this->CI->db->where('page_id', (int) $page->id)->delete('cms_sections');
			$this->CI->db->where('page_id', (int) $page->id)->delete('cms_page_versions');
			$this->CI->db->where('id', (int) $page->id)->delete('cms_pages');
		}
		$this->CI->db->like('old_path', '/uji-')->delete('cms_redirects');
		$this->CI->db->like('new_path', '/uji-')->delete('cms_redirects');
	}

	protected function menu()
	{
		return $this->CI->menu_service->ensure_menu('quick_link');
	}

	protected function make_published_page($key = 'uji_nav', $slug = 'uji-navigasi')
	{
		$page = $this->CI->cms->page($key);
		if ( ! $page)
		{
			$page = $this->CI->cms->create_page(array(
				'page_key' => $key, 'title' => 'Halaman Uji Navigasi', 'slug' => $slug, 'summary' => 'Ringkasan halaman uji navigasi.',
			), $this->admin->id);
		}
		$section = $this->CI->cms->add_section($page, 'custom_notice', $this->admin->id);
		$this->CI->cms->save_section($this->CI->cms->section($section->public_id), array(
			'title' => 'Catatan', 'layout_variant' => 'cards.grid_3', 'severity' => 'info', 'body' => 'Isi halaman uji.',
		), $this->admin->id);
		$this->CI->cms->toggle_section($this->CI->cms->section($section->public_id), TRUE, $this->admin->id);
		$page = $this->CI->db->get_where('cms_pages', array('id' => (int) $page->id))->row();
		$this->CI->db->where('id', (int) $page->id)->update('cms_pages', array('status' => 'approved'));
		$this->CI->publications->publish($this->CI->db->get_where('cms_pages', array('id' => (int) $page->id))->row(),
			'Publikasi halaman uji', $this->admin->id);
		return $this->CI->db->get_where('cms_pages', array('id' => (int) $page->id))->row();
	}

	// ------------------------------------------------------------------ Halaman publik

	public function test_published_page_has_public_path_and_draft_does_not(): void
	{
		$page = $this->CI->cms->create_page(array(
			'page_key' => 'uji_nav', 'title' => 'Halaman Uji Navigasi', 'slug' => 'uji-navigasi',
		), $this->admin->id);
		$this->assertNull($this->CI->cms->public_path((int) $page->id), 'Halaman draft belum punya alamat publik');
		$this->assertNull($this->CI->cms->page_by_path('uji-navigasi'));

		$page = $this->make_published_page();
		$this->assertSame('uji-navigasi', $this->CI->cms->public_path((int) $page->id));
		$found = $this->CI->cms->page_by_path('uji-navigasi');
		$this->assertNotNull($found);
		$this->assertSame('uji_nav', $found->page_key);

		// Beranda tetap hanya dilayani di '/', bukan lewat slug-nya.
		$this->assertNull($this->CI->cms->page_by_path('beranda'));
	}

	public function test_slug_change_keeps_old_path_as_redirect(): void
	{
		$page = $this->make_published_page();
		$this->CI->cms->save_page_version($page, array(
			'title' => 'Halaman Uji Navigasi', 'slug' => 'uji-navigasi-baru',
		), $this->admin->id);
		$redirect = $this->CI->db->get_where('cms_redirects', array('old_path' => '/uji-navigasi'))->row();
		$this->assertNotNull($redirect, 'Slug lama yang pernah terbit harus meninggalkan redirect');
		$this->assertSame('/uji-navigasi-baru', $redirect->new_path);
		$this->assertSame(301, (int) $redirect->status_code);
	}

	public function test_reserved_slug_from_route_is_rejected(): void
	{
		$page = $this->CI->cms->create_page(array(
			'page_key' => 'uji_nav', 'title' => 'Halaman Uji Navigasi', 'slug' => 'uji-navigasi',
		), $this->admin->id);
		$reserved = $this->CI->cms->reserved_slugs();
		$this->assertContains('berita', $reserved);
		$this->assertContains('lapor', $reserved);
		$this->assertContains('admin', $reserved);

		$this->expectException(DomainRuleException::class);
		$this->CI->cms->save_page_version($page, array('title' => 'Berita', 'slug' => 'berita'), $this->admin->id);
	}

	// ------------------------------------------------------------------ Menu

	public function test_menu_item_rules_are_enforced(): void
	{
		$menu = $this->menu();
		$this->CI->menu_service->save_item($menu, array(
			'label' => 'Layanan', 'link_type' => 'route', 'route_path' => '/layanan', 'is_enabled' => 1,
		), $this->admin->id);
		$root = $this->CI->menu_service->items($menu->id)[0];

		// Dua tingkat boleh; tiga tingkat ditolak.
		$this->CI->menu_service->save_item($menu, array(
			'label' => 'Buat Laporan', 'link_type' => 'route', 'route_path' => '/lapor',
			'parent_id' => $root->public_id, 'is_enabled' => 1,
		), $this->admin->id);
		$child = NULL;
		foreach ($this->CI->menu_service->items($menu->id) as $row)
		{
			if ($row->parent_id !== NULL) { $child = $row; }
		}
		$this->assertNotNull($child);

		$cases = array(
			'tiga tingkat' => array('label' => 'Terlalu dalam', 'link_type' => 'route', 'route_path' => '/lacak', 'parent_id' => $child->public_id),
			'javascript' => array('label' => 'Jahat', 'link_type' => 'external', 'external_url' => 'javascript:alert(1)'),
			'data uri' => array('label' => 'Jahat', 'link_type' => 'external', 'external_url' => 'data:text/html,<script>'),
			'path dashboard' => array('label' => 'Admin', 'link_type' => 'route', 'route_path' => '/admin/pengguna'),
			'berkas privat' => array('label' => 'Berkas', 'link_type' => 'route', 'route_path' => '/berkas/privat/1'),
		);
		foreach ($cases as $name => $input)
		{
			$rejected = FALSE;
			try
			{
				$this->CI->menu_service->save_item($menu, $input + array('is_enabled' => 1), $this->admin->id);
			}
			catch (DomainRuleException $e)
			{
				$rejected = TRUE;
				$this->assertSame(422, $e->http_status);
			}
			$this->assertTrue($rejected, 'Kasus "'.$name.'" harus ditolak');
		}
	}

	public function test_draft_page_cannot_be_used_as_menu_item(): void
	{
		$page = $this->CI->cms->create_page(array(
			'page_key' => 'uji_nav', 'title' => 'Halaman Uji Navigasi', 'slug' => 'uji-navigasi',
		), $this->admin->id);
		$menu = $this->menu();

		$rejected = FALSE;
		try
		{
			$this->CI->menu_service->save_item($menu, array(
				'label' => 'Halaman draft', 'link_type' => 'page', 'cms_page_id' => $page->public_id, 'is_enabled' => 1,
			), $this->admin->id);
		}
		catch (DomainRuleException $e)
		{
			$rejected = TRUE;
		}
		$this->assertTrue($rejected, 'Halaman draft tidak boleh dipasang pada menu publik');

		// Setelah terbit, halaman yang sama boleh dipakai.
		$published = $this->make_published_page();
		$this->CI->menu_service->save_item($menu, array(
			'label' => 'Halaman uji', 'link_type' => 'page', 'cms_page_id' => $published->public_id, 'is_enabled' => 1,
		), $this->admin->id);
		$snapshot = $this->CI->menu_service->build_snapshot($menu);
		$this->assertSame('/uji-navigasi', $snapshot['items'][0]['href']);
	}

	public function test_menu_publish_snapshot_and_rollback(): void
	{
		$menu = $this->menu();
		$this->CI->menu_service->save_item($menu, array(
			'label' => 'Layanan', 'link_type' => 'route', 'route_path' => '/layanan', 'is_enabled' => 1,
		), $this->admin->id);

		// Draft belum terbit: situs publik belum melihat menu ini.
		$this->assertNull($this->CI->menu_service->published_menu('quick_link'));

		$revision = $this->CI->menu_service->publish($this->menu(), 'Publikasi menu uji', $this->admin->id);
		$this->assertSame(1, $revision);
		$published = $this->CI->menu_service->published_menu('quick_link');
		$this->assertCount(1, $published);
		$this->assertSame('Layanan', $published[0]['label']);

		// Item baru hanya tampil setelah publikasi berikutnya.
		$this->CI->menu_service->save_item($this->menu(), array(
			'label' => 'Lacak', 'link_type' => 'route', 'route_path' => '/lacak', 'is_enabled' => 1,
		), $this->admin->id);
		$this->assertCount(1, $this->CI->menu_service->published_menu('quick_link'));
		$this->assertSame(2, $this->CI->menu_service->publish($this->menu(), 'Tambah lacak', $this->admin->id));
		$this->assertCount(2, $this->CI->menu_service->published_menu('quick_link'));

		// Rollback membuat revisi baru dan mengembalikan susunan lama tanpa menghapus riwayat.
		$first = $this->CI->db->where(array('target_type' => 'menu', 'target_id' => (int) $this->menu()->id, 'revision_no' => 1))
			->get('cms_publication_snapshots')->row();
		$this->assertSame(3, $this->CI->menu_service->rollback($this->menu(), (int) $first->id, 'Kembalikan menu awal', $this->admin->id));
		$this->assertCount(1, $this->CI->menu_service->published_menu('quick_link'));
		$this->assertSame(3, $this->CI->db->where(array('target_type' => 'menu', 'target_id' => (int) $this->menu()->id))
			->count_all_results('cms_publication_snapshots'));
	}

	public function test_disabled_item_and_unpublished_page_are_skipped_on_publish(): void
	{
		$menu = $this->menu();
		$page = $this->make_published_page();
		$this->CI->menu_service->save_item($menu, array(
			'label' => 'Aktif', 'link_type' => 'route', 'route_path' => '/layanan', 'is_enabled' => 1,
		), $this->admin->id);
		$this->CI->menu_service->save_item($menu, array(
			'label' => 'Nonaktif', 'link_type' => 'route', 'route_path' => '/kontak', 'is_enabled' => 0,
		), $this->admin->id);
		$this->CI->menu_service->save_item($menu, array(
			'label' => 'Halaman uji', 'link_type' => 'page', 'cms_page_id' => $page->public_id, 'is_enabled' => 1,
		), $this->admin->id);

		// Halaman ditarik setelah item dibuat: item itu tidak ikut diterbitkan.
		$this->CI->publications->unpublish($this->CI->db->get_where('cms_pages', array('id' => (int) $page->id))->row(),
			'Ditarik untuk pengujian menu', $this->admin->id);

		$snapshot = $this->CI->menu_service->build_snapshot($this->menu());
		$labels = array_map(function ($item) { return $item['label']; }, $snapshot['items']);
		$this->assertSame(array('Aktif'), $labels);
	}

	// ------------------------------------------------------------------ Identitas dan tema

	public function test_site_identity_and_theme_validation(): void
	{
		$valid = $this->CI->site_service->defaults();
		$cases = array(
			'warna bukan hex' => array('theme' => array('color_primary' => 'merah')),
			'kontras rendah' => array('theme' => array('color_primary' => '#F2F2F2')),
			'latar terlalu gelap' => array('theme' => array('color_surface' => '#101010')),
			'font tidak terpasang' => array('theme' => array('font_body' => 'comic')),
			'radius tak dikenal' => array('theme' => array('radius' => 'bulat-sekali')),
			'email tidak valid' => array('identity' => array('contact_email' => 'bukan-email')),
			'sosial javascript' => array('identity' => array('social' => array('facebook' => 'javascript:alert(1)'))),
			'nama situs kosong' => array('identity' => array('site_name' => '   ')),
		);
		foreach ($cases as $name => $override)
		{
			$input = $valid;
			foreach ($override as $group => $fields)
			{
				$input[$group] = array_merge($input[$group], $fields);
			}
			$rejected = FALSE;
			try
			{
				$this->CI->site_service->validate($input);
			}
			catch (DomainRuleException $e)
			{
				$rejected = TRUE;
				$this->assertSame(422, $e->http_status);
			}
			$this->assertTrue($rejected, 'Kasus "'.$name.'" harus ditolak');
		}

		// Nilai bawaan tetap lolos, dan kunci asing dibuang.
		$clean = $this->CI->site_service->validate(array_merge($valid, array('foo' => 'bar')));
		$this->assertArrayNotHasKey('foo', $clean);
		$this->assertSame('#174B3A', $clean['theme']['color_primary']);
		$this->assertGreaterThan(4.5, $this->CI->site_service->contrast('#174B3A', '#FFFFFF'));
	}

	public function test_site_draft_is_not_public_until_published(): void
	{
		$before = $this->CI->site_service->published();
		$draft = $this->CI->site_service->defaults();
		$draft['identity']['site_name'] = 'Situs Uji Identitas';
		$draft['theme']['color_primary'] = '#1A3C6E';
		$this->CI->site_service->save_draft($draft, $this->admin->id);

		$this->assertSame($before['identity']['site_name'], $this->CI->site_service->published()['identity']['site_name']);
		$this->assertTrue($this->CI->site_service->has_unpublished_changes());

		$revision = $this->CI->site_service->publish('Perbarui identitas uji', $this->admin->id);
		$published = $this->CI->site_service->published();
		$this->assertSame('Situs Uji Identitas', $published['identity']['site_name']);
		$this->assertSame('#1A3C6E', $published['theme']['color_primary']);
		$this->assertFalse($this->CI->site_service->has_unpublished_changes());

		// Rollback mengembalikan identitas lama sebagai revisi baru.
		$previous = $this->CI->db->where(array('target_type' => 'site', 'target_id' => 0))
			->where('revision_no <', $revision)->order_by('revision_no', 'DESC')->limit(1)
			->get('cms_publication_snapshots')->row();
		$this->assertNotNull($previous, 'Seed menyediakan revisi 1 sebagai titik rollback');
		$this->CI->site_service->rollback((int) $previous->id, 'Kembalikan identitas awal', $this->admin->id);
		$this->assertSame($before['identity']['site_name'], $this->CI->site_service->published()['identity']['site_name']);
	}

	// ------------------------------------------------------------------ Cache

	public function test_public_cache_invalidation_is_targeted(): void
	{
		$root = sys_get_temp_dir().'/cihawuk-cache-'.bin2hex(random_bytes(4));
		$cache = new PublicCache(array('root' => $root, 'enabled' => TRUE));
		$cache->set('page', 'home', array('a' => 1));
		$cache->set('page', 'uji_nav', array('a' => 2));
		$cache->set('menu', 'header', array('menu'));
		$cache->set('listing', 'sitemap', array('x'));

		$cache->invalidate_page('uji_nav');
		$this->assertNull($cache->get('page', 'uji_nav'), 'Cache halaman yang diterbitkan dibuang');
		$this->assertSame(array('a' => 1), $cache->get('page', 'home'), 'Halaman lain tidak ikut dibuang');
		$this->assertNull($cache->get('listing', 'sitemap'), 'Sitemap/pencarian ikut diperbarui');
		$this->assertSame(array('menu'), $cache->get('menu', 'header'), 'Cache menu tidak disentuh oleh publikasi halaman');

		$cache->invalidate_menus();
		$this->assertNull($cache->get('menu', 'header'));
		$this->assertSame(array('a' => 1), $cache->get('page', 'home'));

		$this->assertArrayHasKey('menu', $cache->last_invalidation());
		array_map('unlink', glob($root.'/*/*.cache') ?: array());
	}
}
