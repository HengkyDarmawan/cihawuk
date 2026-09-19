<?php

require_once __DIR__."/HttpTestCase.php";

/**
 * Tahap 3 v1.2 lewat HTTP: halaman CMS punya alamat publik nyata, slug lama diarahkan 301,
 * menu dan identitas hanya berubah setelah diterbitkan, serta sitemap/pencarian mengikuti.
 */
class CmsNavigationHttpTest extends HttpTestCase {

	const PASSWORD = 'KataSandiUjiCoba2026';

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('CmsService', NULL, 'cms');
		$this->CI->load->library('CmsPublicationService', NULL, 'publications');
		$this->CI->load->library('CmsMenuService', NULL, 'menu_service');
		$this->CI->load->library('SiteSettingsService', NULL, 'site_service');
		$this->cleanup();
	}

	protected function tearDown(): void
	{
		$this->cleanup();
		parent::tearDown();
	}

	protected function cleanup()
	{
		foreach ($this->CI->db->where('page_key', 'uji_http_nav')->get('cms_pages')->result() as $page)
		{
			$this->CI->db->where(array('target_type' => 'page', 'target_id' => (int) $page->id))->delete('cms_publication_snapshots');
			$this->CI->db->where('id', (int) $page->id)->update('cms_pages', array('current_version_id' => NULL, 'published_version_id' => NULL));
			$this->CI->db->where('page_id', (int) $page->id)->delete('cms_sections');
			$this->CI->db->where('page_id', (int) $page->id)->delete('cms_page_versions');
			$this->CI->db->where('id', (int) $page->id)->delete('cms_pages');
		}
		$this->CI->db->like('old_path', '/uji-http')->delete('cms_redirects');

		// Identitas dikembalikan ke revisi pertama hasil seed.
		$first = $this->CI->db->where(array('target_type' => 'site', 'target_id' => 0, 'revision_no' => 1))
			->get('cms_publication_snapshots')->row();
		if ($first)
		{
			$this->CI->db->where(array('target_type' => 'site', 'target_id' => 0))
				->where('id <>', (int) $first->id)->delete('cms_publication_snapshots');
			$this->CI->db->where('id', (int) $first->id)->update('cms_publication_snapshots', array('superseded_at' => NULL));
			$restored = json_decode((string) $first->snapshot_json, TRUE);
			if (is_array($restored))
			{
				$this->CI->settings->set(SiteSettingsService::DRAFT_KEY, $restored, 'site', FALSE, NULL);
			}
		}
	}

	/** Aktor untuk perubahan lewat service; akun HTTP dibuat terpisah pada tes izin. */
	protected function actor()
	{
		$user = $this->CI->db->get_where('users', array('username' => 'aktor.nav.test'))->row();
		return $user ?: $this->make_user('aktor.nav.test', array('website_admin', 'content_publisher'));
	}

	protected function publish_page($slug = 'uji-http-nav')
	{
		$user = $this->actor();
		$page = $this->CI->cms->create_page(array(
			'page_key' => 'uji_http_nav', 'title' => 'Halaman Uji HTTP', 'slug' => $slug,
			'summary' => 'Ringkasan halaman uji HTTP navigasi.',
		), $user->id);
		$section = $this->CI->cms->add_section($page, 'custom_notice', $user->id);
		$this->CI->cms->save_section($this->CI->cms->section($section->public_id), array(
			'title' => 'Catatan halaman', 'layout_variant' => 'cards.grid_3', 'severity' => 'info',
			'body' => 'Isi halaman uji HTTP.',
		), $user->id);
		$this->CI->cms->toggle_section($this->CI->cms->section($section->public_id), TRUE, $user->id);
		$this->CI->db->where('id', (int) $page->id)->update('cms_pages', array('status' => 'approved'));
		$this->CI->publications->publish($this->CI->db->get_where('cms_pages', array('id' => (int) $page->id))->row(),
			'Publikasi uji HTTP', $user->id);
		return $this->CI->db->get_where('cms_pages', array('id' => (int) $page->id))->row();
	}

	public function test_cms_page_is_404_until_published_then_reachable(): void
	{
		$user = $this->actor();
		$page = $this->CI->cms->create_page(array(
			'page_key' => 'uji_http_nav', 'title' => 'Halaman Uji HTTP', 'slug' => 'uji-http-nav',
		), $user->id);
		$this->assertSame(404, $this->get('uji-http-nav', 'anon')['status'], 'Halaman draft tidak boleh dapat dibuka');

		$this->cleanup();
		$this->publish_page();
		$response = $this->get('uji-http-nav', 'anon');
		$this->assertSame(200, $response['status']);
		$this->assertStringContainsString('Halaman Uji HTTP', $response['body']);
		$this->assertStringContainsString('Isi halaman uji HTTP.', $response['body']);

		// Ditarik dari publik: alamatnya kembali 404.
		$this->CI->publications->unpublish($this->CI->cms->page('uji_http_nav'), 'Ditarik untuk pengujian HTTP', $user->id);
		$this->assertSame(404, $this->get('uji-http-nav', 'anon')['status']);
	}

	public function test_old_slug_redirects_to_new_address(): void
	{
		$user = $this->actor();
		$page = $this->publish_page();
		$this->CI->cms->save_page_version($page, array('title' => 'Halaman Uji HTTP', 'slug' => 'uji-http-nav-baru'), $user->id);
		$this->CI->publications->publish($this->CI->cms->page('uji_http_nav'), 'Ganti slug', $user->id);

		$this->assertSame(200, $this->get('uji-http-nav-baru', 'anon')['status']);
		$old = $this->get('uji-http-nav', 'anon');
		$this->assertSame(301, $old['status']);
		$this->assertStringContainsString('uji-http-nav-baru', (string) $old['location']);
	}

	public function test_published_page_appears_in_sitemap_and_search(): void
	{
		$this->publish_page();
		$sitemap = $this->get('sitemap.xml', 'anon');
		$this->assertSame(200, $sitemap['status']);
		$this->assertStringContainsString('uji-http-nav', $sitemap['body']);

		$search = $this->get('cari?q=Halaman+Uji+HTTP', 'anon');
		$this->assertSame(200, $search['status']);
		$this->assertStringContainsString('Halaman Uji HTTP', $search['body']);

		// Halaman yang ditandai tidak boleh diindeks hilang dari sitemap dan pencarian.
		$user = $this->actor();
		$page = $this->CI->cms->page('uji_http_nav');
		$this->CI->cms->save_page_version($page, array(
			'title' => 'Halaman Uji HTTP', 'slug' => 'uji-http-nav', 'search_indexable' => 0,
		), $user->id);
		$this->CI->publications->publish($this->CI->cms->page('uji_http_nav'), 'Tandai noindex', $user->id);

		$this->assertStringNotContainsString('uji-http-nav', $this->get('sitemap.xml', 'anon')['body']);
		$page_html = $this->get('uji-http-nav', 'anon');
		$this->assertSame(200, $page_html['status']);
		$this->assertStringContainsString('noindex', $page_html['body']);
	}

	public function test_menu_changes_are_invisible_until_published(): void
	{
		$menu = $this->CI->menu_service->ensure_menu('header');
		$before = $this->get('/', 'anon')['body'];
		$this->assertStringNotContainsString('Tautan Uji Menu', $before);

		$user = $this->actor();
		$this->CI->menu_service->save_item($menu, array(
			'label' => 'Tautan Uji Menu', 'link_type' => 'route', 'route_path' => '/kontak', 'is_enabled' => 1,
		), $user->id);
		$this->assertStringNotContainsString('Tautan Uji Menu', $this->get('/', 'anon')['body'],
			'Draft menu tidak boleh langsung tampil di situs');

		$revision = $this->CI->menu_service->publish($this->CI->menu_service->menu('header'), 'Uji publikasi menu', $user->id);
		$this->assertStringContainsString('Tautan Uji Menu', $this->get('/', 'anon')['body']);

		// Rollback ke revisi sebelumnya mengembalikan menu lama.
		$previous = $this->CI->db->where(array('target_type' => 'menu', 'target_id' => (int) $menu->id))
			->where('revision_no <', $revision)->order_by('revision_no', 'DESC')->limit(1)
			->get('cms_publication_snapshots')->row();
		$this->CI->menu_service->rollback($this->CI->menu_service->menu('header'), (int) $previous->id,
			'Kembalikan menu sebelum pengujian', $user->id);
		$this->CI->db->where(array('menu_id' => (int) $menu->id, 'label' => 'Tautan Uji Menu'))->delete('cms_menu_items');
		$this->assertStringNotContainsString('Tautan Uji Menu', $this->get('/', 'anon')['body']);
	}

	public function test_site_identity_and_theme_apply_after_publication(): void
	{
		$user = $this->actor();
		$draft = $this->CI->site_service->draft();
		$draft['identity']['site_name'] = 'Desa Uji Identitas';
		$draft['theme']['color_primary'] = '#2A1B54';
		$this->CI->site_service->save_draft($draft, $user->id);

		$before = $this->get('/', 'anon')['body'];
		$this->assertStringNotContainsString('Desa Uji Identitas', $before, 'Draft identitas belum boleh tampil');
		$this->assertStringNotContainsString('#2A1B54', $before);

		$this->CI->site_service->publish('Uji publikasi identitas', $user->id);
		$after = $this->get('/', 'anon')['body'];
		$this->assertStringContainsString('Desa Uji Identitas', $after);
		$this->assertStringContainsString('--color-primary:#2A1B54', $after);
		$this->assertStringNotContainsString('&quot;Manrope&quot;', $after, 'Token font harus CSS valid, bukan entitas HTML');
	}

	public function test_editor_cannot_publish_menu_or_site_settings(): void
	{
		$this->make_user('editor.nav.test', array('content_editor'));
		$this->make_user('webadmin.nav.test', array('website_admin'));
		$this->assertSame(303, $this->login('editor.nav.test', self::PASSWORD, 'ed')['status']);
		$this->assertSame(303, $this->login('webadmin.nav.test', self::PASSWORD, 'wa')['status']);

		// Editor konten tidak memegang cms.menu.manage maupun cms.site.manage.
		$this->assertSame(403, $this->get('admin/cms/menu', 'ed')['status']);
		$this->assertSame(403, $this->get('admin/cms/situs', 'ed')['status']);

		// Admin website boleh menyusun, tetapi tidak boleh menerbitkan.
		$this->assertSame(200, $this->get('admin/cms/menu', 'wa')['status']);
		$this->assertSame(200, $this->get('admin/cms/menu/header', 'wa')['status']);
		$form = $this->get('admin/cms/situs', 'wa');
		$this->assertSame(200, $form['status']);
		$publish = $this->request('POST', 'admin/cms/situs/terbitkan', 'wa', http_build_query(array(
			'csrf_chw' => $this->csrf_from($form['body']), 'reason' => 'Coba terbitkan',
		)));
		$this->assertSame(403, $publish['status']);
	}
}
