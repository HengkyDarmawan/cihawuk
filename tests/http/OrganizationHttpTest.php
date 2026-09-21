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
		$this->CI->load->library('UploadService', NULL, 'uploads');
		foreach ($this->CI->db->like('people_shown', '[Uji]', 'after')->get('media_assets')->result() as $media)
		{
			if ($media->storage_key !== NULL)
			{
				$this->CI->uploads->delete_media_asset($media);
			}
			$file = $media->private_file_id ? $this->CI->uploads->file($media->private_file_id) : NULL;
			$this->CI->db->where('media_asset_id', (int) $media->id)->delete('media_usages');
			$this->CI->db->where('id', (int) $media->id)->delete('media_assets');
			if ($file)
			{
				$path = $this->CI->uploads->absolute_path($file);
				if ($path) { @unlink($path); }
				$this->CI->db->where('id', (int) $file->id)->delete('private_files');
			}
		}
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

	public function test_government_menu_shows_the_published_structure(): void
	{
		// Sebelum terbit: halaman menu "Pemerintahan" memakai daftar lama (kosong).
		$before = $this->get('pemerintahan');
		$this->assertSame(200, $before['status']);
		$this->assertStringNotContainsString('class="org-tree', $before['body']);

		$this->publish_structure();

		$after = $this->get('pemerintahan');
		$this->assertSame(200, $after['status']);
		$this->assertStringContainsString('class="org-tree org-tree-root"', $after['body']);
		$this->assertStringContainsString('[Uji] Pejabat Publik', $after['body']);
		$this->assertStringContainsString('<h1>Pemerintahan desa</h1>', $after['body']);
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

	public function test_many_leaf_positions_are_grouped_in_a_card_grid(): void
	{
		$actor = $this->actor();
		$period = $this->CI->org->save_period(array('name' => '[Uji] Periode grid', 'year_start' => 2024), (int) $actor->id);
		$head = $this->CI->org->save_position($period, array('title' => 'Kepala Desa Grid', 'active' => 1), (int) $actor->id);
		foreach (array('Kasi A', 'Kasi B', 'Kasi C') as $title)
		{
			$this->CI->org->save_position($period, array('title' => $title, 'parent_id' => (int) $head->id, 'active' => 1), (int) $actor->id);
		}
		$this->CI->org->publish($period, 'Uji grid', (int) $actor->id);
		$this->CI->public_cache->forget_group('listing');
		$this->CI->public_cache->invalidate_page('pemerintahan');

		$body = $this->get('pemerintahan/struktur')['body'];
		$this->assertSame(1, substr_count($body, 'class="org-leaves"'));
		$this->assertStringContainsString('--org-cols:3', $body);
		$this->assertSame(4, substr_count($body, 'class="org-card"'));
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

	protected function png_file()
	{
		$path = tempnam(sys_get_temp_dir(), 'chwimg').'.png';
		$im = imagecreatetruecolor(400, 400);
		imagefill($im, 0, 0, imagecolorallocate($im, 90, 140, 110));
		imagepng($im, $path);
		imagedestroy($im);
		return $path;
	}

	public function test_officer_photo_upload_edit_and_public_detail(): void
	{
		$this->make_user('foto.struktur.test', array('website_admin'));
		$this->assertSame(303, $this->login('foto.struktur.test', self::PASSWORD, 'web')['status']);
		$png = $this->png_file();
		try
		{
			// Tanpa izin publikasi: ditolak sebelum berkas masuk pustaka media.
			$media_before = (int) $this->CI->db->count_all_results('media_assets');
			$denied = $this->post_form('admin/struktur', 'admin/struktur/orang/simpan', array(
				'full_name' => '[Uji] Perangkat Berfoto',
				'foto[]' => new CURLFile($png, 'image/png', 'perangkat.png'),
			), 'web', TRUE);
			$this->assertSame(422, $denied['status']);
			$this->assertSame($media_before, (int) $this->CI->db->count_all_results('media_assets'));

			$saved = $this->post_form('admin/struktur', 'admin/struktur/orang/simpan', array(
				'full_name' => '[Uji] Perangkat Berfoto', 'photo_consent' => '1', 'bio_public' => 'Bio uji.',
				'foto[]' => new CURLFile($png, 'image/png', 'perangkat.png'),
			), 'web', TRUE);
			$this->assertSame(303, $saved['status']);
			$person = $this->CI->db->get_where('people', array('full_name' => '[Uji] Perangkat Berfoto'))->row();
			$this->assertNotNull($person->photo_media_id);
			$media = $this->CI->db->get_where('media_assets', array('id' => (int) $person->photo_media_id))->row();
			$this->assertSame('permission_granted', $media->rights_status);
			$this->assertSame('[Uji] Perangkat Berfoto', $media->people_shown);

			// Form ubah terisi; menyimpan tanpa berkas baru mempertahankan foto.
			$edit = $this->get('admin/struktur?orang='.$person->public_id, 'web');
			$this->assertStringContainsString('Ubah orang: [Uji] Perangkat Berfoto', $edit['body']);
			$this->post_form('admin/struktur?orang='.$person->public_id, 'admin/struktur/orang/simpan', array(
				'public_id' => $person->public_id, 'full_name' => '[Uji] Perangkat Berfoto', 'photo_consent' => '1',
				'bio_public' => 'Bio uji diperbarui.',
			), 'web');
			$after = $this->CI->db->get_where('people', array('id' => (int) $person->id))->row();
			$this->assertSame((int) $person->photo_media_id, (int) $after->photo_media_id);
			$this->assertSame('Bio uji diperbarui.', $after->bio_public);

			// Terbitkan, lalu halaman publik menampilkan foto di kartu dan detail di dialog.
			$actor = $this->actor();
			$period = $this->CI->org->save_period(array('name' => '[Uji] Periode foto', 'year_start' => 2024), (int) $actor->id);
			$head = $this->CI->org->save_position($period, array('title' => 'Kepala Desa Foto', 'active' => 1,
				'duties_public' => 'Tugas uji untuk dialog.'), (int) $actor->id);
			$this->CI->org->save_assignment($period, array('position_id' => (int) $head->id, 'person_id' => (int) $person->id,
				'assignment_type' => 'definitive', 'start_date' => '2024-01-02'), (int) $actor->id);
			$this->CI->org->publish($period, 'Uji foto', (int) $actor->id);
			$this->CI->public_cache->forget_group('listing');
			$this->CI->public_cache->invalidate_page('pemerintahan');

			$body = $this->get('pemerintahan/struktur')['body'];
			$this->assertStringContainsString('class="org-avatar"', $body);
			$this->assertStringContainsString('<img src="'.$this->url('media/'.$media->storage_key).'"', $body);
			$this->assertStringContainsString('id="org-dialog"', $body);
			$this->assertStringContainsString('Tugas uji untuk dialog.', $body, 'Detail tetap ada di markup (tanpa JS)');
			$this->assertStringContainsString('2024 – sekarang', $body);
		}
		finally
		{
			@unlink($png);
		}
	}
}
