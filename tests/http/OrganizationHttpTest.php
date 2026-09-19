<?php

require_once __DIR__."/HttpTestCase.php";

/**
 * Tahap 7 v1.2 lewat HTTP: struktur publik hanya menampilkan snapshot periode terbit,
 * tetap terbaca tanpa JavaScript, dan tidak membocorkan nomor SK maupun foto tanpa izin.
 */
class OrganizationHttpTest extends HttpTestCase {

	const PASSWORD = 'KataSandiUjiCoba2026';

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('OrganizationService', NULL, 'org');
		$this->clean();
	}

	protected function tearDown(): void
	{
		$this->clean();
		parent::tearDown();
	}

	protected function clean()
	{
		foreach ($this->CI->db->like('name', '[Uji]')->get('org_periods')->result() as $row)
		{
			$this->CI->db->where(array('target_type' => 'organization', 'target_id' => (int) $row->id))
				->delete('cms_publication_snapshots');
			$this->CI->db->where('period_id', (int) $row->id)->delete('org_assignments');
			$this->CI->db->where('period_id', (int) $row->id)->delete('org_positions');
			$this->CI->db->where('period_id', (int) $row->id)->delete('org_units');
			$this->CI->db->where('id', (int) $row->id)->delete('org_periods');
		}
		$this->CI->db->like('full_name', '[Uji]')->delete('people');
		$this->CI->public_cache->forget_group('listing');
		$this->CI->public_cache->invalidate_page('pemerintahan');
	}

	protected function actor()
	{
		$user = $this->CI->db->get_where('users', array('username' => 'aktor.struktur.test'))->row();
		return $user ?: $this->make_user('aktor.struktur.test', array('super_admin'));
	}

	protected function publish_structure()
	{
		$actor = $this->actor();
		$period = $this->CI->org->save_period(array(
			'name' => '[Uji] Periode publik', 'year_start' => 2024, 'year_end' => 2030,
		), (int) $actor->id);
		$head = $this->CI->org->save_position($period, array('title' => 'Kepala Desa Uji', 'active' => 1), (int) $actor->id);
		$secretary = $this->CI->org->save_position($period, array(
			'title' => 'Sekretaris Desa Uji', 'parent_id' => (int) $head->id, 'active' => 1,
		), (int) $actor->id);
		$person = $this->CI->org->save_person(array('full_name' => '[Uji] Pejabat Publik'), (int) $actor->id);
		$this->CI->org->save_assignment($period, array(
			'position_id' => (int) $head->id, 'person_id' => (int) $person->id,
			'assignment_type' => 'definitive', 'decree_number' => 'SK/RAHASIA/2024',
		), (int) $actor->id);
		$this->CI->org->save_assignment($period, array(
			'position_id' => (int) $secretary->id, 'assignment_type' => 'vacant',
		), (int) $actor->id);
		$this->CI->org->publish($period, 'Publikasi untuk pengujian HTTP', (int) $actor->id);
		$this->CI->public_cache->forget_group('listing');
		$this->CI->public_cache->invalidate_page('pemerintahan');
		return $period;
	}

	public function test_structure_page_is_empty_until_published(): void
	{
		$before = $this->get('pemerintahan/struktur');
		$this->assertSame(200, $before['status']);
		$this->assertStringContainsString('belum diterbitkan', $before['body']);
		$this->assertStringNotContainsString('[Uji] Pejabat Publik', $before['body']);

		$this->publish_structure();

		$after = $this->get('pemerintahan/struktur');
		$this->assertStringContainsString('Kepala Desa Uji', $after['body']);
		$this->assertStringContainsString('[Uji] Pejabat Publik', $after['body']);
		// Jabatan tanpa penugasan ditandai kosong, bukan diisi nama lama.
		$this->assertStringContainsString('Kosong', $after['body']);
		$this->assertStringContainsString('Belum ada penugasan', $after['body']);
	}

	public function test_public_page_never_exposes_decree_numbers(): void
	{
		$this->publish_structure();
		$body = $this->get('pemerintahan/struktur')['body'];
		$this->assertStringNotContainsString('SK/RAHASIA', $body);
		$this->assertStringNotContainsString('decree', $body);
	}

	public function test_tree_is_readable_without_javascript(): void
	{
		$this->publish_structure();
		$body = $this->get('pemerintahan/struktur')['body'];

		// Seluruh node ada di markup, bukan dibangun oleh JavaScript.
		$this->assertStringContainsString('<ul class="org-tree org-tree-root">', $body);
		$this->assertSame(2, substr_count($body, 'class="org-card"'));
		// Toolbar disembunyikan sampai JavaScript menyalakannya.
		$this->assertStringContainsString('data-org-toolbar hidden', $body);
	}

	public function test_unpublish_returns_the_page_to_empty_state(): void
	{
		$period = $this->publish_structure();
		$this->assertStringContainsString('Kepala Desa Uji', $this->get('pemerintahan/struktur')['body']);

		$actor = $this->actor();
		$this->CI->org->unpublish($this->CI->org->period($period->public_id), 'Ditarik untuk pengujian', (int) $actor->id);
		$this->CI->public_cache->forget_group('listing');

		$this->assertStringContainsString('belum diterbitkan', $this->get('pemerintahan/struktur')['body']);
		$this->assertSame(1, $this->CI->db->where(array('target_type' => 'organization', 'target_id' => (int) $period->id))
			->count_all_results('cms_publication_snapshots'), 'Riwayat snapshot tetap ada');
	}

	public function test_edit_and_publish_permissions_are_separate(): void
	{
		$this->make_user('penyusun.struktur.test', array('content_editor'), 'active');

		$actor = $this->actor();
		$period = $this->CI->org->save_period(array('name' => '[Uji] Periode izin', 'year_start' => 2024), (int) $actor->id);
		$head = $this->CI->org->save_position($period, array('title' => 'Kepala Desa Izin', 'active' => 1), (int) $actor->id);
		$this->CI->org->save_assignment($period, array(
			'position_id' => (int) $head->id, 'assignment_type' => 'vacant',
		), (int) $actor->id);
		$path = 'admin/struktur/periode/'.rawurlencode($period->public_id);

		// Editor konten tidak punya permission organization.* sama sekali.
		$this->login('penyusun.struktur.test', self::PASSWORD, 'editor');
		$this->assertSame(403, $this->get('admin/struktur', 'editor')['status']);
		$this->assertSame(403, $this->post_form('admin/struktur', $path.'/alur/terbitkan',
			array('reason' => 'Coba terbit'), 'editor')['status']);
		$this->assertSame('draft', $this->CI->org->period($period->public_id)->status);
	}
}
