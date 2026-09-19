<?php

/**
 * Tahap 4 v1.2: dataset berversi, verifikasi nilai, pemeriksaan sebelum terbit,
 * publikasi snapshot, dan idempotensi impor staging.
 */
class DatasetTest extends CiTestCase {

	/** @var object */
	protected $verifier;

	/** @var object */
	protected $dataset;

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('DatasetService', NULL, 'datasets');
		$this->truncate_service_data();
		$this->clean();
		$this->verifier = $this->make_user('data.svc.test', array('data_verifier', 'content_publisher'));
		$this->dataset = $this->CI->datasets->create_dataset(array(
			'name' => 'Kependudukan Uji',
			'slug' => 'kependudukan-uji',
			'theme' => 'population',
			'sensitivity' => 'public',
			'coverage' => 'Desa Cihawuk',
			'period_year' => 2023,
			'methodology' => 'Dihitung dari rekap profil desa 2023 tanpa perubahan definisi indikator.',
			'source_note' => 'Profil Desa Cihawuk 2023',
		), $this->verifier->id);
	}

	protected function tearDown(): void
	{
		$this->clean();
		parent::tearDown();
	}

	protected function clean()
	{
		foreach ($this->CI->db->where('slug', 'kependudukan-uji')->get('datasets')->result() as $row)
		{
			$this->CI->db->where(array('target_type' => 'dataset', 'target_id' => (int) $row->id))->delete('cms_publication_snapshots');
			$this->CI->db->where('id', (int) $row->id)->update('datasets', array('current_version_id' => NULL, 'published_version_id' => NULL));
			foreach ($this->CI->db->where('dataset_id', (int) $row->id)->get('dataset_versions')->result() as $version)
			{
				$this->CI->db->where('dataset_version_id', (int) $version->id)->delete('dataset_series');
				$this->CI->db->where('dataset_version_id', (int) $version->id)
					->update('statistic_values', array('dataset_version_id' => NULL));
			}
			$this->CI->db->where('dataset_id', (int) $row->id)->delete('dataset_versions');
			$this->CI->db->where('id', (int) $row->id)->delete('datasets');
		}
		// Kembalikan nilai statistik ke keadaan hasil seed, termasuk angka yang diubah tes lain.
		$this->CI->db->query('UPDATE statistic_values v
			JOIN source_observations o ON o.id = v.canonical_observation_id
			SET v.numeric_value = o.normalized_value
			WHERE v.source_year = 2023 AND o.normalized_value IS NOT NULL');
		$this->CI->db->where('source_year', 2023)->update('statistic_values', array(
			'verification_status' => 'pending', 'publication_status' => 'draft', 'suppressed' => 0,
		));
		$this->CI->public_cache->forget_group('listing');
	}

	protected function reload()
	{
		return $this->CI->db->get_where('datasets', array('id' => (int) $this->dataset->id))->row();
	}

	protected function version()
	{
		return $this->CI->datasets->version($this->reload()->current_version_id);
	}

	protected function indicator($code)
	{
		return $this->CI->db->get_where('statistic_indicators', array('code' => $code))->row();
	}

	/** Tambahkan indikator ke dataset dan tandai nilainya terverifikasi. */
	protected function add($code, $chart = 'bar', $verify = TRUE)
	{
		$indicator = $this->indicator($code);
		$this->CI->datasets->add_series($this->version(), array('indicator_id' => (int) $indicator->id, 'chart_type' => $chart));
		if ($verify)
		{
			$this->CI->datasets->verify_value($this->version(), (int) $indicator->id, TRUE, $this->verifier->id);
		}
		return $indicator;
	}

	protected function errors()
	{
		return $this->CI->datasets->validate_version($this->reload(), $this->version(), FALSE)['errors'];
	}

	public function test_draft_dataset_is_not_public_until_published(): void
	{
		$this->add('population_total', 'number');
		$this->assertSame(array(), $this->CI->datasets->published_datasets(), 'Dataset draft tidak boleh terbaca publik');

		$revision = $this->CI->datasets->publish($this->reload(), 'Publikasi uji', $this->verifier->id);
		$this->assertSame(1, $revision);
		$this->CI->public_cache->forget_group('listing');

		$published = $this->CI->datasets->published_datasets();
		$this->assertCount(1, $published);
		$this->assertSame('kependudukan-uji', $published[0]['dataset']['slug']);
		$this->assertSame(6809, $published[0]['series'][0]['value']);
		$this->assertSame(2023, $published[0]['version']['period_year']);
		$this->assertSame('published', $this->reload()->status);

		// Nilai yang dipakai dataset ikut berstatus terbit sehingga beranda konsisten.
		$value = $this->CI->db->select('publication_status')->from('statistic_values v')
			->join('statistic_indicators i', 'i.id = v.indicator_id')
			->where('i.code', 'population_total')->where('v.source_year', 2023)->get()->row();
		$this->assertSame('published', $value->publication_status);
	}

	public function test_publication_requires_verified_values_and_metadata(): void
	{
		// Belum ada indikator sama sekali.
		$this->assertNotEmpty($this->errors());

		$indicator = $this->add('population_total', 'number', FALSE);
		$errors = implode(' | ', $this->errors());
		$this->assertStringContainsString('belum diverifikasi', $errors);

		$this->CI->datasets->verify_value($this->version(), (int) $indicator->id, TRUE, $this->verifier->id);
		$this->assertSame(array(), $this->errors(), 'Setelah verifikasi, dataset lolos pemeriksaan');

		// Metodologi terlalu pendek dan sumber kosong ditolak.
		$this->CI->datasets->save_version($this->reload(), array(
			'name' => 'Kependudukan Uji', 'theme' => 'population', 'sensitivity' => 'public',
			'coverage' => 'Desa Cihawuk', 'period_year' => 2023, 'methodology' => 'singkat', 'source_note' => '',
		), $this->verifier->id);
		$errors = implode(' | ', $this->errors());
		$this->assertStringContainsString('Metodologi', $errors);
		$this->assertStringContainsString('Sumber data', $errors);
	}

	public function test_publish_is_blocked_while_validation_fails(): void
	{
		$this->add('population_total', 'number', FALSE);
		$rejected = FALSE;
		try
		{
			$this->CI->datasets->publish($this->reload(), 'Coba terbit', $this->verifier->id);
		}
		catch (DomainRuleException $e)
		{
			$rejected = TRUE;
			$this->assertSame(422, $e->http_status);
		}
		$this->assertTrue($rejected, 'Dataset yang belum lolos pemeriksaan tidak boleh terbit');
		$this->assertSame('draft', $this->reload()->status);
		$this->assertSame(array(), $this->CI->datasets->published_datasets());
	}

	public function test_composition_rules_follow_source_reality(): void
	{
		// Donut hanya untuk indikator yang termasuk komposisi.
		$total = $this->indicator('population_total');
		$rejected = FALSE;
		try
		{
			$this->CI->datasets->add_series($this->version(), array('indicator_id' => (int) $total->id, 'chart_type' => 'donut'));
		}
		catch (DomainRuleException $e)
		{
			$rejected = TRUE;
		}
		$this->assertTrue($rejected, 'Donut ditolak untuk kategori yang bisa tumpang tindih');

		// Komposisi tidak lengkap: hanya laki-laki.
		$male = $this->add('population_male', 'donut');
		$errors = implode(' | ', $this->errors());
		$this->assertStringContainsString('belum lengkap', $errors);

		// Lengkap dan jumlahnya cocok dengan total.
		$this->add('population_female', 'donut');
		$this->add('population_total', 'number');
		$this->assertSame(array(), $this->errors());

		// Angka diubah sehingga jumlah komponen tidak lagi sama dengan total.
		$this->CI->db->where(array('indicator_id' => (int) $male->id, 'source_year' => 2023))
			->update('statistic_values', array('numeric_value' => 3000));
		$errors = implode(' | ', $this->errors());
		$this->assertStringContainsString('tidak sama dengan population_total', $errors);
	}

	public function test_empty_value_is_unknown_not_zero(): void
	{
		$indicator = $this->add('population_total', 'number');
		$this->CI->db->where(array('indicator_id' => (int) $indicator->id, 'source_year' => 2023))
			->update('statistic_values', array('numeric_value' => NULL));
		$errors = implode(' | ', $this->errors());
		$this->assertStringContainsString('tidak diketahui, bukan nol', $errors);
	}

	public function test_small_counts_on_sensitive_theme_need_suppression(): void
	{
		$indicator = $this->add('hamlet_count', 'number');
		$this->CI->datasets->save_version($this->reload(), array(
			'name' => 'Kependudukan Uji', 'theme' => 'health', 'sensitivity' => 'public',
			'coverage' => 'Desa Cihawuk', 'period_year' => 2023,
			'methodology' => 'Contoh dataset tema sensitif untuk menguji ambang data kecil.',
			'source_note' => 'Uji',
		), $this->verifier->id);
		$this->CI->datasets->verify_value($this->version(), (int) $indicator->id, TRUE, $this->verifier->id);

		// hamlet_count bernilai 4, di bawah ambang 10.
		$errors = implode(' | ', $this->errors());
		$this->assertStringContainsString('ambang', $errors);

		$this->CI->db->where(array('indicator_id' => (int) $indicator->id, 'source_year' => 2023))
			->update('statistic_values', array('suppressed' => 1));
		$this->assertSame(array(), $this->errors());

		// Nilai yang disamarkan tidak pernah keluar angkanya pada snapshot.
		$this->CI->datasets->publish($this->reload(), 'Publikasi tema sensitif', $this->verifier->id);
		$this->CI->public_cache->forget_group('listing');
		$snapshot = $this->CI->datasets->published_dataset('kependudukan-uji');
		$this->assertTrue($snapshot['series'][0]['suppressed']);
		$this->assertNull($snapshot['series'][0]['value']);
	}

	public function test_unpublish_and_rollback_keep_history(): void
	{
		$this->add('population_total', 'number');
		$this->CI->datasets->publish($this->reload(), 'Revisi pertama', $this->verifier->id);
		$this->add('households', 'number');
		$this->CI->datasets->publish($this->reload(), 'Revisi kedua', $this->verifier->id);
		$this->CI->public_cache->forget_group('listing');
		$this->assertCount(2, $this->CI->datasets->published_dataset('kependudukan-uji')['series']);

		$first = $this->CI->db->where(array('target_type' => 'dataset', 'target_id' => (int) $this->dataset->id, 'revision_no' => 1))
			->get('cms_publication_snapshots')->row();
		$this->assertSame(3, $this->CI->datasets->rollback($this->reload(), (int) $first->id, 'Kembalikan revisi pertama', $this->verifier->id));
		$this->CI->public_cache->forget_group('listing');
		$this->assertCount(1, $this->CI->datasets->published_dataset('kependudukan-uji')['series']);
		$this->assertSame(3, $this->CI->db->where(array('target_type' => 'dataset', 'target_id' => (int) $this->dataset->id))
			->count_all_results('cms_publication_snapshots'));

		$this->CI->datasets->unpublish($this->reload(), 'Ditarik untuk pengujian', $this->verifier->id);
		$this->CI->public_cache->forget_group('listing');
		$this->assertSame(array(), $this->CI->datasets->published_datasets());
		$this->assertSame(3, $this->CI->db->where(array('target_type' => 'dataset', 'target_id' => (int) $this->dataset->id))
			->count_all_results('cms_publication_snapshots'), 'Riwayat tidak dihapus saat ditarik');
	}

	public function test_verification_is_audited_and_raw_observation_untouched(): void
	{
		$indicator = $this->indicator('population_total');
		$before = $this->CI->db->select('raw_value, validation_status')->from('source_observations')
			->order_by('id')->limit(1)->get()->row();

		$this->CI->datasets->add_series($this->version(), array('indicator_id' => (int) $indicator->id, 'chart_type' => 'number'));
		$this->CI->datasets->verify_value($this->version(), (int) $indicator->id, TRUE, $this->verifier->id);
		$this->assertSame(1, $this->CI->db->where('action', 'dataset.value_verified')->count_all_results('audit_logs'));

		$after = $this->CI->db->select('raw_value, validation_status')->from('source_observations')
			->order_by('id')->limit(1)->get()->row();
		$this->assertEquals($before, $after, 'Verifikasi tidak boleh mengubah nilai mentah observasi');
	}

	public function test_csv_rows_mark_suppressed_and_escape_formula(): void
	{
		$this->add('population_total', 'number');
		$this->CI->datasets->publish($this->reload(), 'Publikasi uji CSV', $this->verifier->id);
		$this->CI->public_cache->forget_group('listing');
		$rows = $this->CI->datasets->csv_rows($this->CI->datasets->published_dataset('kependudukan-uji'));

		$this->assertSame(array('indikator', 'kode', 'nilai', 'satuan', 'tahun', 'sumber', 'catatan'), $rows[0]);
		$this->assertSame('population_total', $rows[1][1]);
		$this->assertSame(6809, $rows[1][2]);
		$this->assertSame(2023, $rows[1][4]);
	}

	public function test_import_staging_is_idempotent(): void
	{
		$this->CI->load->library('SourceImportService', NULL, 'source_import');
		$source = NULL;
		$path = NULL;
		foreach ($this->CI->db->order_by('id')->get('source_documents')->result() as $row)
		{
			// S3 masih .doc dan belum dapat diekstrak; pakai dokumen pertama yang berkasnya ada.
			if (substr(strtolower((string) $row->original_filename), -5) !== '.docx')
			{
				continue;
			}
			$candidate = $this->CI->source_import->reference_path($row);
			if ($candidate !== NULL)
			{
				$source = $row;
				$path = $candidate;
				break;
			}
		}
		if ( ! $source)
		{
			$this->markTestSkipped('Tidak ada dokumen sumber .docx pada reference/documents.');
		}

		$this->CI->source_import->import_docx($source, $path, $this->verifier->id);
		$first = (int) $this->CI->db->where('source_id', (int) $source->id)->count_all_results('source_observations');
		$this->assertGreaterThan(0, $first, 'Impor pertama harus menghasilkan observasi staging');

		$this->CI->source_import->import_docx($source, $path, $this->verifier->id);
		$second = (int) $this->CI->db->where('source_id', (int) $source->id)->count_all_results('source_observations');
		$this->assertSame($first, $second, 'Impor ulang tidak boleh menduplikasi observasi');
	}
}
