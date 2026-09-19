<?php

/**
 * Tahap 2 v1.2: section registry, versi draft, snapshot publikasi, penjadwalan dan rollback.
 * Memakai halaman uji tersendiri agar halaman `home` hasil seed tidak terganggu.
 */
class CmsPublicationTest extends CiTestCase {

	/** @var object */
	protected $editor;

	/** @var object */
	protected $page;

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('CmsService', NULL, 'cms');
		$this->CI->load->library('CmsPublicationService', NULL, 'publications');
		$this->CI->load->library('FeatureModuleService', NULL, 'modules');
		$this->truncate_service_data();
		$this->clean_test_pages();
		$this->editor = $this->make_user('cms.svc.test', array('content_editor'));
		$this->page = $this->CI->cms->create_page(array(
			'page_key' => 'uji_cms',
			'title' => 'Halaman Uji CMS',
			'slug' => 'halaman-uji-cms',
		), $this->editor->id);
	}

	protected function tearDown(): void
	{
		$this->clean_test_pages();
		$this->CI->db->where('code', 'news')->update('feature_modules', array('state' => 'active'));
		$this->CI->modules->flush();
		parent::tearDown();
	}

	protected function clean_test_pages()
	{
		$page = $this->CI->db->get_where('cms_pages', array('page_key' => 'uji_cms'))->row();
		if ( ! $page)
		{
			return;
		}
		$this->CI->db->where(array('target_type' => 'page', 'target_id' => (int) $page->id))->delete('cms_publication_snapshots');
		$this->CI->db->where(array('object_type' => 'cms_page', 'object_id' => (int) $page->id))->delete('scheduled_publications');
		$this->CI->db->where(array('object_type' => 'cms_page', 'object_id' => (int) $page->id))->delete('content_review_requests');
		$this->CI->db->where('id', (int) $page->id)->update('cms_pages', array('current_version_id' => NULL, 'published_version_id' => NULL));
		$this->CI->db->where('page_id', (int) $page->id)->delete('cms_sections');
		$this->CI->db->where('page_id', (int) $page->id)->delete('cms_page_versions');
		$this->CI->db->where('id', (int) $page->id)->delete('cms_pages');
	}

	protected function reload()
	{
		return $this->CI->db->get_where('cms_pages', array('id' => (int) $this->page->id))->row();
	}

	protected function add_notice($body = 'Pengumuman uji untuk publikasi CMS.')
	{
		$section = $this->CI->cms->add_section($this->page, 'custom_notice', $this->editor->id);
		$this->CI->cms->save_section($this->CI->cms->section($section->public_id), array(
			'title' => 'Pengumuman uji',
			'layout_variant' => 'cards.grid_3',
			'severity' => 'info',
			'body' => $body,
		), $this->editor->id);
		$this->CI->cms->toggle_section($this->CI->cms->section($section->public_id), TRUE, $this->editor->id);
		return $this->CI->cms->section($section->public_id);
	}

	protected function publish($reason = 'Publikasi uji')
	{
		$page = $this->reload();
		$this->CI->db->where('id', (int) $page->id)->update('cms_pages', array('status' => 'approved'));
		return $this->CI->publications->publish($this->reload(), $reason, $this->editor->id);
	}

	public function test_draft_is_not_public_until_published(): void
	{
		$this->add_notice();
		$this->assertNull($this->CI->publications->published_layout('uji_cms'), 'Draft tidak boleh terbaca publik');

		$revision = $this->publish();
		$this->assertSame(1, $revision);

		$layout = $this->CI->publications->published_layout('uji_cms');
		$this->assertNotNull($layout);
		$this->assertCount(1, $layout['sections']);
		$this->assertSame('custom_notice', $layout['sections'][0]['type']);
		$this->assertSame('published', $this->reload()->status);

		// Snapshot menyimpan rujukan konfigurasi, bukan hasil render.
		$this->assertSame('Pengumuman uji untuk publikasi CMS.', $layout['sections'][0]['config']['body']);
		$this->assertSame(1, $this->CI->db->where('action', 'cms.published')->count_all_results('audit_logs'));
	}

	public function test_editing_draft_does_not_change_public_page(): void
	{
		$section = $this->add_notice('Teks versi pertama.');
		$this->publish();

		$this->CI->cms->save_section($this->CI->cms->section($section->public_id), array(
			'title' => 'Pengumuman uji', 'layout_variant' => 'cards.grid_3',
			'severity' => 'warning', 'body' => 'Teks versi kedua yang belum diterbitkan.',
		), $this->editor->id);

		$layout = $this->CI->publications->published_layout('uji_cms');
		$this->assertSame('Teks versi pertama.', $layout['sections'][0]['config']['body']);

		$this->publish('Publikasi kedua');
		$layout = $this->CI->publications->published_layout('uji_cms');
		$this->assertSame('Teks versi kedua yang belum diterbitkan.', $layout['sections'][0]['config']['body']);
	}

	public function test_disabled_section_and_disabled_module_are_not_published(): void
	{
		$notice = $this->add_notice();
		$news = $this->CI->cms->add_section($this->page, 'featured_news', $this->editor->id);
		$this->CI->cms->save_section($this->CI->cms->section($news->public_id), array(
			'title' => 'Berita', 'layout_variant' => 'cards.grid_3', 'limit' => 3,
		), $this->editor->id);
		$this->CI->cms->toggle_section($this->CI->cms->section($news->public_id), TRUE, $this->editor->id);

		// Section nonaktif tidak ikut snapshot.
		$this->CI->cms->toggle_section($this->CI->cms->section($notice->public_id), FALSE, $this->editor->id);
		$this->publish();
		$layout = $this->CI->publications->published_layout('uji_cms');
		$this->assertCount(1, $layout['sections']);
		$this->assertSame('featured_news', $layout['sections'][0]['type']);

		// Modul mati: section tetap ada sebagai draft, tetapi tidak diterbitkan.
		$this->CI->db->where('code', 'news')->update('feature_modules', array('state' => 'disabled'));
		$this->CI->modules->flush();
		$this->publish('Publikasi saat modul berita mati');
		$layout = $this->CI->publications->published_layout('uji_cms');
		$this->assertCount(0, $layout['sections']);
		$this->assertSame(2, $this->CI->db->where('page_id', (int) $this->page->id)->count_all_results('cms_sections'));
	}

	public function test_unpublish_and_rollback_keep_history(): void
	{
		$section = $this->add_notice('Versi satu.');
		$this->publish();
		$this->CI->cms->save_section($this->CI->cms->section($section->public_id), array(
			'title' => 'Pengumuman uji', 'layout_variant' => 'cards.grid_3', 'severity' => 'info', 'body' => 'Versi dua.',
		), $this->editor->id);
		$this->publish('Publikasi kedua');
		$this->assertSame('Versi dua.', $this->CI->publications->published_layout('uji_cms')['sections'][0]['config']['body']);

		$this->CI->publications->unpublish($this->reload(), 'Menarik halaman untuk perbaikan isi', $this->editor->id);
		$this->assertNull($this->CI->publications->published_layout('uji_cms'), 'Halaman ditarik tidak lagi terbaca publik');
		$this->assertSame('unpublished', $this->reload()->status);

		$first = $this->CI->db->where(array('target_type' => 'page', 'target_id' => (int) $this->page->id, 'revision_no' => 1))
			->get('cms_publication_snapshots')->row();
		$revision = $this->CI->publications->rollback($this->reload(), $first->id, 'Kembali ke susunan versi pertama', $this->editor->id);

		$this->assertSame(3, $revision);
		$this->assertSame('Versi satu.', $this->CI->publications->published_layout('uji_cms')['sections'][0]['config']['body']);
		// Riwayat tidak dihapus: tiga revisi tetap ada.
		$this->assertSame(3, $this->CI->db->where(array('target_type' => 'page', 'target_id' => (int) $this->page->id))
			->count_all_results('cms_publication_snapshots'));
	}

	public function test_scheduled_publication_runs_once(): void
	{
		$this->add_notice();
		$this->CI->db->where('id', (int) $this->page->id)->update('cms_pages', array('status' => 'approved'));
		$this->CI->clock->set('2026-09-20 01:00:00');
		$run_at = $this->CI->publications->schedule($this->reload(), '2026-09-20 09:00', 'Publikasi terjadwal uji', $this->editor->id);
		$this->assertSame('2026-09-20 02:00:00', $run_at, 'Waktu WIB disimpan sebagai UTC');
		$this->assertSame('scheduled', $this->reload()->status);

		// Belum jatuh tempo.
		$this->assertSame(0, $this->CI->publications->run_due_schedules()['diterbitkan']);
		$this->assertNull($this->CI->publications->published_layout('uji_cms'));

		$this->CI->clock->set('2026-09-20 03:00:00');
		$first = $this->CI->publications->run_due_schedules();
		$second = $this->CI->publications->run_due_schedules();
		$this->assertSame(1, $first['diterbitkan']);
		$this->assertSame(0, $second['diterbitkan'], 'Scheduler kedua tidak menerbitkan ulang');
		$this->assertSame(1, $this->CI->db->where(array('target_type' => 'page', 'target_id' => (int) $this->page->id))
			->count_all_results('cms_publication_snapshots'));
	}

	public function test_section_reorder_is_transactional_and_validated(): void
	{
		$a = $this->add_notice('Section A.');
		$b = $this->add_notice('Section B.');
		$this->CI->cms->reorder_sections($this->page, array($b->public_id, $a->public_id), $this->editor->id);

		$sections = $this->CI->cms->sections($this->page->id);
		$this->assertSame($b->public_id, $sections[0]->public_id);
		$this->assertSame($a->public_id, $sections[1]->public_id);

		try
		{
			$this->CI->cms->reorder_sections($this->page, array($a->public_id), $this->editor->id);
			$this->fail('Urutan tidak lengkap harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertSame(422, $e->http_status);
		}
		// Urutan lama tidak berubah setelah penolakan.
		$sections = $this->CI->cms->sections($this->page->id);
		$this->assertSame($b->public_id, $sections[0]->public_id);
	}

	public function test_registry_rules_reject_unsafe_or_unpublished_references(): void
	{
		// Tautan javascript: ditolak.
		$notice = $this->CI->cms->add_section($this->page, 'service_cta', $this->editor->id);
		try
		{
			$this->CI->cms->save_section($this->CI->cms->section($notice->public_id), array(
				'title' => 'CTA', 'layout_variant' => 'cards.grid_3', 'body' => 'Isi',
				'cta' => array(array('label' => 'Klik', 'url' => 'javascript:alert(1)')),
			), $this->editor->id);
			$this->fail('Tautan javascript: harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('cta', $e->errors);
		}

		// Statistik: dataset yang belum terbit tidak dapat dipasang di halaman publik.
		$stat = $this->CI->cms->add_section($this->page, 'statistics', $this->editor->id);
		try
		{
			$this->CI->cms->save_section($this->CI->cms->section($stat->public_id), array(
				'title' => 'Statistik', 'layout_variant' => 'statistics.cards_4',
				'dataset_slug' => 'dataset-belum-terbit', 'series' => array('population_total'),
			), $this->editor->id);
			$this->fail('Dataset yang belum terbit harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('dataset_slug', $e->errors);
		}

		// Hero mode video wajib punya poster.
		$hero = $this->CI->cms->add_section($this->page, 'hero', $this->editor->id);
		try
		{
			$this->CI->cms->save_section($this->CI->cms->section($hero->public_id), array(
				'title' => 'Hero', 'layout_variant' => 'hero.standard', 'mode' => 'video',
			), $this->editor->id);
			$this->fail('Mode video tanpa poster harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('fallback_media_id', $e->errors);
		}

		// Section singleton hanya boleh satu per halaman.
		try
		{
			$this->CI->cms->add_section($this->page, 'hero', $this->editor->id);
			$this->fail('Section singleton kedua harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertSame(409, $e->http_status);
		}
	}

	public function test_preview_token_is_bound_to_user_and_expires(): void
	{
		$other = $this->make_user('cms.lain.test', array('content_editor'));
		$token = $this->CI->publications->preview_token($this->reload(), $this->editor->id, 900);

		$this->assertNotNull($this->CI->publications->verify_preview_token($token, $this->editor->id));
		$this->assertNull($this->CI->publications->verify_preview_token($token, $other->id), 'Token milik pengguna lain ditolak');
		$this->assertNull($this->CI->publications->verify_preview_token($token.'x', $this->editor->id), 'Tanda tangan rusak ditolak');

		$this->CI->clock->set(NULL);
		$expired = $this->CI->publications->preview_token($this->reload(), $this->editor->id, -10);
		$this->assertNull($this->CI->publications->verify_preview_token($expired, $this->editor->id), 'Token kedaluwarsa ditolak');
	}
}
