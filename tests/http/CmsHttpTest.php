<?php

require_once __DIR__."/HttpTestCase.php";

/**
 * Tahap 2 v1.2 lewat HTTP: pemisahan izin susun/terbit, draft tidak tampil publik,
 * beranda dirender dari snapshot, dan pratinjau memerlukan login + token sah.
 */
class CmsHttpTest extends HttpTestCase {

	const PASSWORD = 'KataSandiUjiCoba2026';

	/** @var object */
	protected $page;

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('CmsService', NULL, 'cms');
		$this->CI->load->library('CmsPublicationService', NULL, 'publications');
		$this->page = $this->CI->cms->page('home');
		$this->assertNotNull($this->page, 'Halaman beranda hasil seed harus ada');
	}

	protected function tearDown(): void
	{
		// Kembalikan seluruh section beranda ke keadaan aktif dan terbitkan ulang susunan awal.
		$this->CI->db->where('page_id', (int) $this->page->id)->update('cms_sections', array('is_enabled' => 1));
		$first = $this->CI->db->where(array('target_type' => 'page', 'target_id' => (int) $this->page->id, 'revision_no' => 1))
			->get('cms_publication_snapshots')->row();
		if ($first)
		{
			$this->CI->db->where(array('target_type' => 'page', 'target_id' => (int) $this->page->id))
				->where('id <>', (int) $first->id)->delete('cms_publication_snapshots');
			$this->CI->db->where('id', (int) $first->id)->update('cms_publication_snapshots', array('superseded_at' => NULL));
		}
		$this->CI->db->where('id', (int) $this->page->id)->update('cms_pages', array('status' => 'published'));
		parent::tearDown();
	}

	protected function section_of($type)
	{
		return $this->CI->db->where(array('page_id' => (int) $this->page->id, 'section_type' => $type))
			->get('cms_sections')->row();
	}

	protected function post_admin($path, $browser, array $fields, $form_path = 'admin/cms/beranda')
	{
		$page = $this->get($form_path, $browser);
		return $this->request('POST', $path, $browser, http_build_query(array_merge(
			array('csrf_chw' => $this->csrf_from($page['body'])), $fields
		)));
	}

	public function test_homepage_is_rendered_from_published_snapshot(): void
	{
		$home = $this->get('/', 'anon');
		$this->assertSame(200, $home['status']);
		$this->assertStringContainsString('hero-title', $home['body']);
		$this->assertStringContainsString('quick-card', $home['body']);
		$this->assertStringContainsString('cta-banner', $home['body']);
	}

	public function test_editor_edits_draft_without_changing_public_page(): void
	{
		$this->make_user('editor.cms.test', array('content_editor'));
		$this->assertSame(303, $this->login('editor.cms.test', self::PASSWORD, 'ed')['status']);

		$section = $this->section_of('service_cta');
		$toggle = $this->post_admin('admin/cms/section/'.$section->public_id.'/status', 'ed', array('enabled' => '0'));
		$this->assertSame(303, $toggle['status']);

		// Publik belum berubah karena belum diterbitkan.
		$this->assertStringContainsString('cta-banner', $this->get('/', 'anon')['body']);

		// Editor tidak boleh menerbitkan.
		$publish = $this->post_admin('admin/cms/halaman/'.$this->page->public_id.'/alur/terbitkan', 'ed', array('reason' => 'Coba terbit'));
		$this->assertSame(403, $publish['status']);
		$this->assertStringContainsString('cta-banner', $this->get('/', 'anon')['body']);
	}

	public function test_publisher_publishes_and_cannot_edit_draft(): void
	{
		$this->make_user('editor.pub.test', array('content_editor'));
		$this->make_user('penerbit.pub.test', array('content_publisher'));
		$this->assertSame(303, $this->login('editor.pub.test', self::PASSWORD, 'ed')['status']);
		$this->assertSame(303, $this->login('penerbit.pub.test', self::PASSWORD, 'pub')['status']);

		$section = $this->section_of('service_cta');
		$this->assertSame(303, $this->post_admin('admin/cms/section/'.$section->public_id.'/status', 'ed', array('enabled' => '0'))['status']);

		// Penerbit tanpa cms.page.edit tidak dapat mengubah draft.
		$this->assertSame(403, $this->post_admin('admin/cms/section/'.$section->public_id.'/status', 'pub', array('enabled' => '1'))['status']);

		$publish = $this->post_admin('admin/cms/halaman/'.$this->page->public_id.'/alur/terbitkan', 'pub', array('reason' => 'Uji publikasi HTTP'));
		$this->assertSame(303, $publish['status']);
		$this->assertStringNotContainsString('cta-banner', $this->get('/', 'anon')['body'], 'Section nonaktif hilang setelah publikasi');

		// Rollback mengembalikan susunan lama tanpa menghapus riwayat.
		$first = $this->CI->db->where(array('target_type' => 'page', 'target_id' => (int) $this->page->id, 'revision_no' => 1))
			->get('cms_publication_snapshots')->row();
		$rollback = $this->post_admin('admin/cms/halaman/'.$this->page->public_id.'/alur/rollback', 'pub', array(
			'snapshot_id' => $first->id, 'reason' => 'Kembalikan susunan awal beranda',
		));
		$this->assertSame(303, $rollback['status']);
		$this->assertStringContainsString('cta-banner', $this->get('/', 'anon')['body']);
		$this->assertGreaterThanOrEqual(3, $this->CI->db->where(array('target_type' => 'page', 'target_id' => (int) $this->page->id))
			->count_all_results('cms_publication_snapshots'));
	}

	public function test_preview_requires_login_and_valid_token(): void
	{
		$user = $this->make_user('editor.preview.test', array('content_editor'));
		$this->assertSame(303, $this->login('editor.preview.test', self::PASSWORD, 'pv')['status']);

		$token = $this->CI->publications->preview_token($this->page, (int) $user->id);
		$preview = $this->get('admin/pratinjau/'.$token, 'pv');
		$this->assertSame(200, $preview['status']);
		$this->assertStringContainsString('noindex', implode(',', $preview['headers']['x-robots-tag'] ?? array()));
		$this->assertStringContainsString('no-store', implode(',', $preview['headers']['cache-control'] ?? array()));

		// Pengunjung tanpa login diarahkan ke halaman masuk, bukan melihat draft.
		$anon = $this->get('admin/pratinjau/'.$token, 'anon');
		$this->assertSame(303, $anon['status']);
		$this->assertStringContainsString('masuk', (string) $anon['location']);

		$this->assertSame(410, $this->get('admin/pratinjau/'.$token.'x', 'pv')['status']);
	}

	public function test_section_type_from_disabled_module_is_rejected(): void
	{
		$this->make_user('editor.modul.test', array('content_editor'));
		$this->assertSame(303, $this->login('editor.modul.test', self::PASSWORD, 'em')['status']);

		// Modul transparansi anggaran dimatikan sementara; section yang bergantung
		// padanya tidak boleh dibuat selama modulnya tidak aktif.
		$this->CI->load->library('FeatureModuleService', NULL, 'modules');
		$actor = $this->CI->db->get_where('users', array('username' => 'editor.modul.test'))->row();
		$this->CI->modules->set_state('budget_transparency', 'disabled',
			'Dinonaktifkan untuk pengujian section modul.', (int) $actor->id);
		try
		{
			$response = $this->post_admin('admin/cms/section/tambah', 'em', array(
				'page_key' => 'home', 'section_type' => 'budget_summary',
			));
			$this->assertSame(409, $response['status']);
			$this->assertSame(0, $this->CI->db->where(array('page_id' => (int) $this->page->id, 'section_type' => 'budget_summary'))
				->count_all_results('cms_sections'));
		}
		finally
		{
			$this->CI->modules->set_state('budget_transparency', 'active',
				'Dikembalikan setelah pengujian section modul.', (int) $actor->id);
		}
	}
}
