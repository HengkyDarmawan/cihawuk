<?php

/**
 * Tahap 12 v1.2: pemeriksa tautan internal rusak.
 *
 * Yang dijaga: path yang cocok route atau halaman CMS terbit dinyatakan sehat, path yang
 * tidak ada ditandai rusak, dan tautan eksternal dicatat tanpa pernah dipanggil server.
 */
class LinkCheckTest extends CiTestCase {

	/** @var object */
	protected $actor;

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('LinkCheckService', NULL, 'link_check');
		$this->CI->load->library('CmsMenuService', NULL, 'menu_service');
		$existing = $this->CI->db->get_where('users', array('username' => 'tautan.svc.test'))->row();
		$this->actor = $existing ?: $this->make_user('tautan.svc.test', array('website_admin', 'content_publisher'));
		$this->clean();
	}

	protected function tearDown(): void
	{
		$this->clean();
		parent::tearDown();
	}

	protected function clean()
	{
		foreach ($this->CI->db->like('label', '[Uji tautan]')->get('cms_menu_items')->result() as $row)
		{
			$this->CI->db->where('id', (int) $row->id)->delete('cms_menu_items');
		}
		$this->CI->db->empty_table('link_check_results');
		$this->CI->public_cache->forget_group('listing');
		$this->CI->public_cache->invalidate_menus();
	}

	public function test_known_routes_are_healthy(): void
	{
		$summary = $this->CI->link_check->run();
		$this->assertGreaterThan(0, $summary['ok'], 'Menu hasil seed harus menghasilkan tautan sehat');
		$this->assertSame(0, $summary['broken'], 'Tautan hasil seed tidak boleh ada yang rusak');
	}

	public function test_broken_menu_item_is_reported(): void
	{
		$menu = $this->CI->menu_service->ensure_menu('header', $this->actor->id);
		$item = $this->CI->menu_service->save_item($menu, array(
			'label' => '[Uji tautan] Halaman hilang',
			'link_type' => 'route',
			'route_path' => '/halaman-yang-tidak-pernah-ada',
			'is_enabled' => 1,
		), $this->actor->id);
		$this->CI->menu_service->publish($menu, 'Publikasi uji pemeriksa tautan', $this->actor->id);
		$this->CI->public_cache->invalidate_menus();

		$summary = $this->CI->link_check->run();
		$this->assertGreaterThan(0, $summary['broken']);

		$paths = array();
		foreach ($this->CI->link_check->results('broken') as $row)
		{
			$paths[] = $row->target_path;
		}
		$this->assertContains('/halaman-yang-tidak-pernah-ada', $paths);

		// Bersihkan lalu terbitkan ulang supaya menu kembali seperti semula.
		$this->CI->menu_service->delete_item($menu, $this->CI->menu_service->item($item->public_id));
		$this->CI->menu_service->publish($menu, 'Kembalikan menu setelah pengujian', $this->actor->id);
		$this->CI->public_cache->invalidate_menus();
	}

	public function test_external_links_are_recorded_but_not_fetched(): void
	{
		$menu = $this->CI->menu_service->ensure_menu('footer_primary', $this->actor->id);
		$item = $this->CI->menu_service->save_item($menu, array(
			'label' => '[Uji tautan] Situs luar',
			'link_type' => 'external',
			'external_url' => 'https://contoh.invalid/halaman',
			'is_enabled' => 1,
		), $this->actor->id);
		$this->CI->menu_service->publish($menu, 'Publikasi uji tautan eksternal', $this->actor->id);
		$this->CI->public_cache->invalidate_menus();

		$summary = $this->CI->link_check->run();
		$this->assertGreaterThan(0, $summary['external']);
		$external = $this->CI->link_check->results('external');
		$this->assertStringContainsString('tidak diperiksa dari server', (string) $external[0]->detail);

		$this->CI->menu_service->delete_item($menu, $this->CI->menu_service->item($item->public_id));
		$this->CI->menu_service->publish($menu, 'Kembalikan menu setelah pengujian', $this->actor->id);
		$this->CI->public_cache->invalidate_menus();
	}

	public function test_results_are_replaced_on_each_run(): void
	{
		$this->CI->link_check->run();
		$first = (int) $this->CI->db->count_all('link_check_results');
		$this->CI->link_check->run();
		$second = (int) $this->CI->db->count_all('link_check_results');
		$this->assertSame($first, $second, 'Menjalankan ulang mengganti hasil, bukan menumpuk');
	}
}
