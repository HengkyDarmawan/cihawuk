<?php

require_once __DIR__."/HttpTestCase.php";

/**
 * Tahap 5 v1.2 lewat HTTP: halaman profil hanya menampilkan isi yang sudah diterbitkan,
 * pemisahan izin menyusun vs memverifikasi/menerbitkan ditegakkan server, dan catatan
 * konflik luas wilayah tidak disembunyikan.
 */
class ProfileHttpTest extends HttpTestCase {

	const PASSWORD = 'KataSandiUjiCoba2026';

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('ProfileService', NULL, 'profile_service');
		$this->restore();
	}

	protected function tearDown(): void
	{
		$this->restore();
		parent::tearDown();
	}

	protected function restore()
	{
		$this->CI->db->where('target_type', 'profile')->delete('cms_publication_snapshots');
		$this->CI->db->update('profile_blocks', array(
			'status' => 'draft', 'published_version_id' => NULL, 'published_at' => NULL,
			'verification_status' => 'unverified', 'verified_by' => NULL, 'verified_at' => NULL,
		));
		$this->CI->db->update('leadership_terms', array(
			'publication_status' => 'draft', 'verification_status' => 'unverified',
		));
		foreach ($this->CI->db->get('profile_blocks')->result() as $row)
		{
			$first = $this->CI->db->where('block_id', (int) $row->id)->order_by('version_no')
				->limit(1)->get('profile_block_versions')->row();
			if ( ! $first)
			{
				continue;
			}
			$this->CI->db->where('id', (int) $row->id)->update('profile_blocks', array('current_version_id' => (int) $first->id));
			$this->CI->db->where('block_id', (int) $row->id)->where('version_no >', 1)->delete('profile_block_versions');
		}
		$this->CI->public_cache->forget_group('listing');
		foreach ($this->CI->profile_service->pages() as $page)
		{
			$this->CI->public_cache->invalidate_page($page);
		}
	}

	protected function actor()
	{
		$user = $this->CI->db->get_where('users', array('username' => 'aktor.profil.test'))->row();
		return $user ?: $this->make_user('aktor.profil.test', array('content_editor', 'content_publisher'));
	}

	protected function publish_profile()
	{
		$actor = $this->actor();
		foreach (array('identity', 'history', 'geography') as $key)
		{
			$block = $this->CI->profile_service->block($key);
			$this->CI->profile_service->submit_review($block, (int) $actor->id);
			$this->CI->profile_service->verify_block($this->CI->profile_service->block($key), TRUE, (int) $actor->id);
		}
		foreach ($this->CI->profile_service->terms() as $term)
		{
			$this->CI->profile_service->verify_term($term, TRUE, (int) $actor->id);
		}
		$revision = $this->CI->profile_service->publish('Publikasi untuk pengujian HTTP', (int) $actor->id);
		$this->CI->public_cache->forget_group('listing');
		foreach ($this->CI->profile_service->pages() as $page)
		{
			$this->CI->public_cache->invalidate_page($page);
		}
		return $revision;
	}

	public function test_profile_pages_are_empty_until_published(): void
	{
		foreach (array('profil', 'profil/sejarah', 'profil/visi-misi', 'profil/geografi') as $path)
		{
			$response = $this->get($path);
			$this->assertSame(200, $response['status'], $path.' harus tetap 200 dengan keadaan kosong');
			$this->assertStringNotContainsString('320431.2006', $response['body'], 'Draft tidak boleh bocor ke '.$path);
		}
		$this->assertStringContainsString('Profil desa sedang disiapkan', $this->get('profil')['body']);

		$this->assertSame(1, $this->publish_profile());

		$profil = $this->get('profil');
		$this->assertStringContainsString('320431.2006', $profil['body']);
		$this->assertStringContainsString('Kertasari', $profil['body']);
		$this->assertStringNotContainsString('Profil desa sedang disiapkan', $profil['body']);
	}

	public function test_history_page_does_not_claim_a_current_officeholder(): void
	{
		$this->publish_profile();
		$body = $this->get('profil/sejarah')['body'];

		$this->assertStringContainsString('Yaya Dores', $body);
		$this->assertStringContainsString('sampai sekarang', $body,
			'Klaim dokumen harus ditulis apa adanya, bukan diterjemahkan menjadi jabatan aktif');
		$this->assertStringContainsString('2019&ndash;tahun dokumen', $body,
			'Periode terakhir berakhir pada tahun dokumen, bukan tahun berjalan yang dikarang');
		// Dua periode Aep Saepuloh tetap terpisah.
		$this->assertSame(2, substr_count($body, 'Aep Saepuloh'));
	}

	public function test_geography_page_shows_the_area_conflict(): void
	{
		$this->publish_profile();
		$body = $this->get('profil/geografi')['body'];

		$this->assertStringContainsString('Luas wilayah belum punya angka resmi', $body);
		$this->assertStringContainsString('932,35', $body);
		$this->assertStringContainsString('931,00', $body);
		$this->assertStringContainsString('1.514', $body, 'Ketinggian ditampilkan dengan pemisah ribuan Indonesia');
		$this->assertStringContainsString('Jumlah komposisi', $body, 'Tabel wajib menampilkan jumlah komposisinya sendiri');
	}

	public function test_editor_and_publisher_permissions_are_separate(): void
	{
		$this->make_user('editor.profil.test', array('content_editor'), 'active');
		$this->make_user('penerbit.profil.test', array('content_publisher'), 'active');

		$block = $this->CI->profile_service->block('identity');
		$path = 'admin/profil/blok/'.rawurlencode($block->public_id);

		// Editor boleh menyunting draft, tetapi tidak boleh memverifikasi atau menerbitkan.
		$this->login('editor.profil.test', self::PASSWORD, 'editor');
		$this->assertSame(200, $this->get($path, 'editor')['status']);
		$save = $this->post_form($path, $path.'/simpan', array(
			'title' => 'Identitas desa',
			'village_name' => 'Cihawuk', 'district' => 'Kertasari', 'regency' => 'Bandung', 'province' => 'Jawa Barat',
			'pum_code' => '320431.2006',
			'summary' => 'Ringkasan hasil suntingan editor untuk pengujian.',
		), 'editor');
		$this->assertSame(303, $save['status']);
		$this->assertStringContainsString('suntingan editor', (string) $this->CI->profile_service->block('identity')->body['summary']);

		$this->assertSame(403, $this->post_form($path, $path.'/verifikasi', array('verified' => '1'), 'editor')['status']);
		$this->assertSame(403, $this->post_form('admin/profil', 'admin/profil/alur/terbitkan', array('reason' => 'Coba terbit'), 'editor')['status']);
		$this->assertNull($this->CI->profile_service->published());

		// Penerbit memverifikasi dan menerbitkan. Preset "Penerbit Konten" memang juga
		// memegang `content.edit`, jadi yang dijaga di sini adalah arah sebaliknya:
		// editor tidak boleh menembus verifikasi dan penerbitan.
		$this->login('penerbit.profil.test', self::PASSWORD, 'publisher');
		$this->assertSame(303, $this->post_form($path, $path.'/verifikasi', array('verified' => '1'), 'publisher')['status']);
		$this->assertSame('verified', $this->CI->profile_service->block('identity')->verification_status);

		// Peran tanpa permission konten sama sekali tidak dapat membuka pengelola profil.
		$this->make_user('auditor.profil.test', array('system_auditor'), 'active');
		$this->login('auditor.profil.test', self::PASSWORD, 'auditor');
		$this->assertSame(403, $this->get('admin/profil', 'auditor')['status']);
	}

	public function test_unpublish_returns_pages_to_the_empty_state(): void
	{
		$this->publish_profile();
		$this->assertStringContainsString('320431.2006', $this->get('profil')['body']);

		$actor = $this->actor();
		$this->CI->profile_service->unpublish('Ditarik untuk pengujian', (int) $actor->id);

		$this->assertStringNotContainsString('320431.2006', $this->get('profil')['body']);
		$this->assertStringContainsString('Profil desa sedang disiapkan', $this->get('profil')['body']);
		$this->assertSame(1, $this->CI->db->where('target_type', 'profile')->count_all_results('cms_publication_snapshots'),
			'Riwayat snapshot tetap tersimpan setelah ditarik');
	}
}
