<?php

/**
 * Tahap 10 v1.2: impor register aset ke staging.
 *
 * Yang dijaga: nilai mentah tidak diubah, baris bermasalah ditandai bukan dibuang, impor
 * ulang berkas yang sama tidak menggandakan staging, dan volume non-angka tidak dipecah
 * menjadi unit secara otomatis.
 */
class AssetImportTest extends CiTestCase {

	/** @var object */
	protected $actor;

	/** @var int */
	protected $source_id;

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('AssetImportService', NULL, 'asset_import');
		$this->CI->load->library('AssetService', NULL, 'assets');
		$existing = $this->CI->db->get_where('users', array('username' => 'impor.svc.test'))->row();
		$this->actor = $existing ?: $this->make_user('impor.svc.test', array('asset_manager', 'data_verifier'));
		$this->clean();
		$this->source_id = (int) $this->CI->db->order_by('id')->limit(1)->get('source_documents')->row('id');
	}

	protected function tearDown(): void
	{
		$this->clean();
		parent::tearDown();
	}

	protected function clean()
	{
		$this->CI->db->query('SET FOREIGN_KEY_CHECKS = 0');
		foreach ($this->CI->db->where('parser_version', AssetImportService::PARSER_VERSION)->get('import_batches')->result() as $batch)
		{
			$this->CI->db->where('import_batch_id', (int) $batch->id)->delete('asset_import_rows');
			$this->CI->db->where('id', (int) $batch->id)->delete('import_batches');
		}
		foreach ($this->CI->db->like('legacy_asset_code', 'UJI-IMP')->get('asset_registers')->result() as $register)
		{
			$this->CI->db->where('id', (int) $register->id)->delete('asset_registers');
		}
		$this->CI->db->query('SET FOREIGN_KEY_CHECKS = 1');
	}

	protected function fixture()
	{
		return ROOTPATH.'tests/fixtures/aset-contoh.csv';
	}

	public function test_import_classifies_rows_without_discarding_any(): void
	{
		$summary = $this->CI->asset_import->import_csv($this->fixture(), $this->source_id, $this->actor->id);

		// Baris bercatatan tetap dihitung valid; catatannya yang menandai apa yang perlu dilihat.
		$this->assertSame(3, $summary['valid']);
		$this->assertSame(1, $summary['failed'], 'Baris tanpa nama barang ditandai gagal');
		$this->assertSame(1, $summary['conflict'], 'Tahun 3099 ditandai konflik');
		$this->assertSame(0, $summary['empty']);

		$rows = $this->CI->asset_import->rows($summary['batch_id']);
		$this->assertCount(5, $rows, 'Seluruh baris tetap tersimpan, tidak ada yang dibuang');
	}

	public function test_raw_values_are_kept_exactly_as_written(): void
	{
		$summary = $this->CI->asset_import->import_csv($this->fixture(), $this->source_id, $this->actor->id);
		$rows = $this->CI->asset_import->rows($summary['batch_id']);

		$paket = NULL;
		foreach ($rows as $row)
		{
			if ($row->legacy_code === 'UJI-IMP-003')
			{
				$paket = $row;
			}
		}
		$this->assertNotNull($paket);
		$raw = json_decode($paket->raw_json, TRUE);
		$this->assertContains('I Set', $raw, 'Volume ditulis apa adanya, bukan dikoreksi menjadi 1');
		$proposed = json_decode($paket->proposed_register_json, TRUE);
		$this->assertSame('I Set', $proposed['source_volume_raw']);
		$this->assertNull($proposed['acquisition_value'], 'Harga kosong tidak menjadi nol');
		$this->assertStringContainsString('pemecahan unit harus diputuskan manual', (string) $paket->review_note);
	}

	public function test_missing_existence_is_flagged_not_assumed(): void
	{
		$summary = $this->CI->asset_import->import_csv($this->fixture(), $this->source_id, $this->actor->id);
		$rows = $this->CI->asset_import->rows($summary['batch_id']);
		$kursi = NULL;
		foreach ($rows as $row)
		{
			if ($row->legacy_code === 'UJI-IMP-002')
			{
				$kursi = $row;
			}
		}
		$this->assertNotNull($kursi);
		$this->assertStringContainsString('Keberadaan barang belum diisi', (string) $kursi->review_note);
	}

	public function test_reimport_is_idempotent(): void
	{
		$first = $this->CI->asset_import->import_csv($this->fixture(), $this->source_id, $this->actor->id);
		$count = count($this->CI->asset_import->rows($first['batch_id']));

		$second = $this->CI->asset_import->import_csv($this->fixture(), $this->source_id, $this->actor->id);
		$this->assertSame($first['batch_id'], $second['batch_id'], 'Berkas yang sama memakai batch yang sama');
		$this->assertSame(5, $second['skipped'], 'Seluruh baris dilewati pada impor ulang');
		$this->assertSame($count, count($this->CI->asset_import->rows($first['batch_id'])));
	}

	public function test_commit_creates_one_register_only(): void
	{
		$summary = $this->CI->asset_import->import_csv($this->fixture(), $this->source_id, $this->actor->id);
		$category = $this->CI->db->get_where('asset_categories', array('code' => 'PERALATAN'))->row();
		$row = NULL;
		foreach ($this->CI->asset_import->rows($summary['batch_id']) as $candidate)
		{
			if ($candidate->legacy_code === 'UJI-IMP-001')
			{
				$row = $candidate;
			}
		}
		$this->assertNotNull($row);

		$register = $this->CI->asset_import->commit_row($row, (int) $category->id, $this->actor->id);
		$this->assertSame('Laptop kantor', $register->name);
		$this->assertSame('2', $register->source_volume_raw ? substr($register->source_volume_raw, 0, 1) : '');

		// Komit ulang tidak membuat register kedua.
		$again = $this->CI->asset_import->commit_row(
			$this->CI->db->get_where('asset_import_rows', array('id' => (int) $row->id))->row(),
			(int) $category->id, $this->actor->id);
		$this->assertSame((int) $register->id, (int) $again->id);
		$this->assertSame(1, $this->CI->db->where('legacy_asset_code', 'UJI-IMP-001')->count_all_results('asset_registers'));
	}

	public function test_failed_row_cannot_be_committed(): void
	{
		$summary = $this->CI->asset_import->import_csv($this->fixture(), $this->source_id, $this->actor->id);
		$category = $this->CI->db->get_where('asset_categories', array('code' => 'PERALATAN'))->row();
		$failed = $this->CI->asset_import->rows($summary['batch_id'], 'failed');
		$this->assertNotEmpty($failed);

		try
		{
			$this->CI->asset_import->commit_row($failed[0], (int) $category->id, $this->actor->id);
			$this->fail('Baris gagal tidak boleh dikomit');
		}
		catch (DomainRuleException $e)
		{
			$this->assertSame(409, $e->http_status);
		}
	}
}
