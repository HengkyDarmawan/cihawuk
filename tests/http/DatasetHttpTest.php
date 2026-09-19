<?php

require_once __DIR__."/HttpTestCase.php";

/**
 * Tahap 4 v1.2 lewat HTTP: dataset draft tidak terbaca publik, dataset terbit tampil
 * dengan grafik DAN tabel setara, CSV aman dari formula injection, pemisahan izin
 * verifikasi vs penerbitan ditegakkan server, dan modul nonaktif menutup halamannya.
 */
class DatasetHttpTest extends HttpTestCase {

	const PASSWORD = 'KataSandiUjiCoba2026';
	const SLUG = 'kependudukan-2023';

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('DatasetService', NULL, 'datasets');
		$this->CI->load->library('CmsService', NULL, 'cms');
		$this->CI->load->library('CmsPublicationService', NULL, 'publications');
		$this->restore();
	}

	protected function tearDown(): void
	{
		$this->restore();
		parent::tearDown();
	}

	/** Kembalikan dataset seed ke draft dan beranda ke revisi hasil seed. */
	protected function restore()
	{
		$dataset = $this->dataset();
		if ($dataset)
		{
			$this->CI->db->where(array('target_type' => 'dataset', 'target_id' => (int) $dataset->id))
				->delete('cms_publication_snapshots');
			$this->CI->db->where('id', (int) $dataset->id)->update('datasets', array(
				'status' => 'draft', 'published_version_id' => NULL, 'published_at' => NULL,
				'sensitivity' => 'public', 'archived_at' => NULL,
			));
		}
		$this->CI->db->where('source_year', 2023)->update('statistic_values', array(
			'verification_status' => 'pending', 'publication_status' => 'draft', 'suppressed' => 0,
		));

		// Beranda: buang revisi tambahan, hidupkan lagi revisi pertama hasil seed.
		$page = $this->CI->db->get_where('cms_pages', array('page_key' => 'home'))->row();
		if ($page)
		{
			$this->CI->db->where(array('target_type' => 'page', 'target_id' => (int) $page->id))
				->where('revision_no >', 1)->delete('cms_publication_snapshots');
			$this->CI->db->where(array('target_type' => 'page', 'target_id' => (int) $page->id, 'revision_no' => 1))
				->update('cms_publication_snapshots', array('superseded_at' => NULL));
		}

		$this->CI->public_cache->forget_group('listing');
		$this->CI->public_cache->invalidate_page('home');
		$this->CI->public_cache->invalidate_page('data-desa');
	}

	protected function dataset()
	{
		return $this->CI->db->get_where('datasets', array('slug' => self::SLUG))->row();
	}

	protected function actor()
	{
		$user = $this->CI->db->get_where('users', array('username' => 'aktor.dataset.test'))->row();
		return $user ?: $this->make_user('aktor.dataset.test', array('data_verifier', 'content_publisher'));
	}

	/** Verifikasi keempat nilai 2023 supaya dataset lolos pemeriksaan. */
	protected function verify_values($actor)
	{
		$dataset = $this->dataset();
		$version = $this->CI->datasets->version($dataset->current_version_id);
		foreach ($this->CI->datasets->series($version->id) as $series)
		{
			$this->CI->datasets->verify_value($version, (int) $series->indicator_id, TRUE, (int) $actor->id);
		}
		return $this->dataset();
	}

	protected function publish_dataset()
	{
		$actor = $this->actor();
		$dataset = $this->verify_values($actor);
		$revision = $this->CI->datasets->publish($dataset, 'Publikasi untuk pengujian HTTP', (int) $actor->id);
		$this->CI->public_cache->forget_group('listing');
		return $revision;
	}

	// ------------------------------------------------------------------

	public function test_draft_dataset_is_not_public_then_published_shows_chart_and_table(): void
	{
		$before = $this->get('data-desa');
		$this->assertSame(200, $before['status']);
		$this->assertStringNotContainsString('Kependudukan Desa Cihawuk', $before['body'], 'Dataset draft tidak boleh tampil');
		$this->assertStringContainsString('Belum ada dataset yang diterbitkan', $before['body']);
		$this->assertSame(404, $this->get('data-desa/'.self::SLUG.'/unduh.csv')['status']);

		$this->assertSame(1, $this->publish_dataset());

		$after = $this->get('data-desa');
		$this->assertSame(200, $after['status']);
		$this->assertStringContainsString('Kependudukan Desa Cihawuk', $after['body']);
		// Grafik dan tabel angka setara harus ada berdampingan (modul-frontend 8.2).
		$this->assertStringContainsString('<canvas', $after['body']);
		$this->assertStringContainsString('<table', $after['body']);
		$this->assertStringContainsString('6.809', $after['body'], 'Angka integer tidak boleh tampil sebagai 6809.0');
		$this->assertStringContainsString('2023', $after['body']);
		$this->assertStringContainsString('Metodologi', $after['body']);
		$this->assertStringContainsString('Unduh CSV', $after['body']);
	}

	public function test_csv_download_is_utf8_bom_and_formula_safe(): void
	{
		$this->publish_dataset();
		$csv = $this->get('data-desa/'.self::SLUG.'/unduh.csv');

		$this->assertSame(200, $csv['status']);
		$this->assertSame("\xEF\xBB\xBF", substr($csv['body'], 0, 3), 'CSV harus diawali BOM UTF-8');
		$this->assertStringContainsString('text/csv', implode(' ', $csv['headers']['content-type']));
		$this->assertStringContainsString('population_total', $csv['body']);
		$this->assertStringContainsString('6809', $csv['body']);
		// Tidak boleh ada sel yang dimulai dengan karakter formula tanpa penetral.
		foreach (preg_split('/\r?\n/', substr($csv['body'], 3)) as $line)
		{
			foreach (str_getcsv($line) as $cell)
			{
				if ($cell === NULL || $cell === '')
				{
					continue;
				}
				if (strpos('=+-@', $cell[0]) !== FALSE)
				{
					$this->fail('Sel CSV dimulai dengan karakter formula: '.$cell);
				}
			}
		}
	}

	public function test_theme_route_and_indicator_filter(): void
	{
		$this->publish_dataset();

		$theme = $this->get('data-desa/population');
		$this->assertSame(200, $theme['status']);
		$this->assertStringContainsString('Kependudukan Desa Cihawuk', $theme['body']);

		$this->assertSame(404, $this->get('data-desa/tema-yang-tidak-ada')['status'], 'Tema di luar registry harus 404');

		$match = $this->get('data-desa?indikator=population_total');
		$this->assertStringContainsString('Kependudukan Desa Cihawuk', $match['body']);
		$miss = $this->get('data-desa?indikator=crop_area_potato');
		$this->assertStringNotContainsString('Kependudukan Desa Cihawuk', $miss['body']);
		$this->assertStringContainsString('Tidak ada dataset yang cocok', $miss['body']);
	}

	public function test_restricted_dataset_never_reaches_the_public_page(): void
	{
		$this->publish_dataset();
		$this->assertStringContainsString('Kependudukan Desa Cihawuk', $this->get('data-desa')['body']);

		// Sensitivitas dinaikkan setelah terbit: snapshot lama tidak boleh tetap terbaca.
		$this->CI->db->where('slug', self::SLUG)->update('datasets', array('sensitivity' => 'restricted'));
		$this->CI->public_cache->forget_group('listing');

		$this->assertSame(404, $this->get('data-desa/'.self::SLUG.'/unduh.csv')['status']);
	}

	public function test_review_and_publish_permissions_are_separate(): void
	{
		$this->make_user('verifikator.http.test', array('data_verifier'), 'active');
		$this->make_user('penerbit.http.test', array('content_publisher'), 'active');
		$this->make_user('editor.http.test', array('content_editor'), 'active');

		$dataset = $this->dataset();
		$path = 'admin/dataset/'.rawurlencode($dataset->public_id);
		$indicator = $this->CI->db->get_where('statistic_indicators', array('code' => 'population_total'))->row();

		// Editor konten tidak punya permission data.* sama sekali.
		$this->login('editor.http.test', self::PASSWORD, 'editor');
		$this->assertSame(403, $this->get('admin/dataset', 'editor')['status']);

		// Verifikator boleh memverifikasi nilai, tetapi tidak boleh menerbitkan.
		$this->login('verifikator.http.test', self::PASSWORD, 'verifier');
		$this->assertSame(200, $this->get($path, 'verifier')['status']);
		$verify = $this->post_form($path, $path.'/verifikasi', array(
			'indicator_id' => (int) $indicator->id, 'verified' => '1',
		), 'verifier');
		$this->assertSame(303, $verify['status']);
		$this->assertSame('verified', $this->value_status('population_total'));

		$denied = $this->post_form($path, $path.'/alur/terbitkan', array('reason' => 'Coba terbitkan'), 'verifier');
		$this->assertSame(403, $denied['status'], 'data.review tidak boleh menerbitkan');
		$this->assertSame('draft', $this->dataset()->status);

		// Penerbit boleh menerbitkan, tetapi tidak boleh mengubah tanda verifikasi.
		$this->login('penerbit.http.test', self::PASSWORD, 'publisher');
		$verify_denied = $this->post_form($path, $path.'/verifikasi', array(
			'indicator_id' => (int) $indicator->id, 'verified' => '0',
		), 'publisher');
		$this->assertSame(403, $verify_denied['status'], 'data.publish tidak boleh memverifikasi nilai');
		$this->assertSame('verified', $this->value_status('population_total'));
	}

	protected function value_status($code)
	{
		return $this->CI->db->select('v.verification_status')->from('statistic_values v')
			->join('statistic_indicators i', 'i.id = v.indicator_id')
			->where('i.code', $code)->where('v.source_year', 2023)->get()->row('verification_status');
	}

	public function test_disabled_module_closes_public_and_admin_pages(): void
	{
		$this->CI->load->library('FeatureModuleService', NULL, 'modules');
		$actor = $this->actor();
		// `facilities` bergantung pada `village_data`, jadi harus dinonaktifkan lebih dulu.
		// Urutan ini sekaligus membuktikan guard dependency dua arah benar-benar berlaku.
		$this->CI->modules->set_state('facilities', 'disabled', 'Dinonaktifkan untuk pengujian dependency.', (int) $actor->id);
		$this->CI->modules->set_state('village_data', 'disabled', 'Dinonaktifkan untuk pengujian modul.', (int) $actor->id);
		try
		{
			$this->assertSame(404, $this->get('data-desa')['status']);
			$this->login('aktor.dataset.test', self::PASSWORD, 'staff');
			$this->assertSame(404, $this->get('admin/dataset', 'staff')['status']);
		}
		finally
		{
			$this->CI->modules->set_state('village_data', 'active', 'Dikembalikan setelah pengujian modul.', (int) $actor->id);
			$this->CI->modules->set_state('facilities', 'active', 'Dikembalikan setelah pengujian dependency.', (int) $actor->id);
		}
		$this->assertSame(200, $this->get('data-desa')['status']);
	}

	public function test_homepage_statistics_appear_only_after_the_dataset_is_published(): void
	{
		$home = $this->get('/');
		$this->assertSame(200, $home['status']);
		$this->assertStringContainsString('Statistik sedang direview', $home['body'],
			'Selama dataset masih draft, beranda menampilkan keadaan kosong');
		$this->assertStringNotContainsString('6.809', $home['body']);

		$this->publish_dataset();

		// Section menyimpan rujukan dataset, bukan angkanya; begitu dataset terbit, kartu terisi
		// dan cache halaman yang memakainya sudah dibuang secara terarah.
		$after = $this->get('/');
		$this->assertStringContainsString('6.809', $after['body']);
		$this->assertStringContainsString('3.510', $after['body']);
		$this->assertStringNotContainsString('Statistik sedang direview', $after['body']);
		// Angka wajib disertai tahun dan sumber (modul-frontend 4.2).
		$this->assertStringContainsString('sumber S1', $after['body']);
		$this->assertStringContainsString('2023', $after['body']);

		// Menarik dataset mengembalikan beranda ke keadaan kosong, bukan menyisakan angka basi.
		$actor = $this->actor();
		$this->CI->datasets->unpublish($this->dataset(), 'Ditarik untuk pengujian', (int) $actor->id);
		$final = $this->get('/');
		$this->assertStringNotContainsString('6.809', $final['body']);
		$this->assertStringContainsString('Statistik sedang direview', $final['body']);
	}
}
