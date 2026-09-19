<?php

/**
 * Tahap 8 v1.2: transparansi anggaran.
 *
 * Yang dijaga: murni/perubahan/realisasi tidak tercampur, total komponen harus cocok atau
 * dijelaskan, realisasi melebihi anggaran wajib penjelasan, surplus/defisit direkonsiliasi
 * dengan pembiayaan, dokumen belum disamarkan tidak boleh publik, dan draft tidak terbaca.
 */
class BudgetTest extends CiTestCase {

	/** @var object */
	protected $actor;

	/** @var object */
	protected $year;

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('BudgetService', NULL, 'budgets');
		$existing = $this->CI->db->get_where('users', array('username' => 'keuangan.svc.test'))->row();
		$this->actor = $existing ?: $this->make_user('keuangan.svc.test', array('finance_manager', 'finance_verifier', 'content_publisher'));
		$this->clean();
		$this->year = $this->CI->budgets->create_year(array('fiscal_year' => 2099), $this->actor->id);
	}

	protected function tearDown(): void
	{
		$this->clean();
		parent::tearDown();
	}

	protected function clean()
	{
		// `budget_categories.parent_id` memakai RESTRICT, jadi cascade dimatikan sebentar
		// dan tabel anaknya dihapus eksplisit.
		$this->CI->db->query('SET FOREIGN_KEY_CHECKS = 0');
		foreach ($this->CI->db->where('fiscal_year >=', 2090)->get('budget_years')->result() as $row)
		{
			$id = (int) $row->id;
			$this->CI->db->where(array('target_type' => 'budget', 'target_id' => $id))->delete('cms_publication_snapshots');
			$this->CI->db->query('DELETE l FROM budget_lines l JOIN budget_revisions r ON r.id = l.revision_id WHERE r.budget_year_id = '.$id);
			$this->CI->db->query('DELETE p FROM budget_project_progress p JOIN budget_projects pr ON pr.id = p.project_id WHERE pr.budget_year_id = '.$id);
			$this->CI->db->where('budget_year_id', $id)->delete('budget_projects');
			$this->CI->db->where('budget_year_id', $id)->delete('budget_documents');
			$this->CI->db->where('budget_year_id', $id)->delete('budget_categories');
			$this->CI->db->where('budget_year_id', $id)->delete('budget_revisions');
			$this->CI->db->where('id', $id)->delete('budget_years');
		}
		$this->CI->db->query('SET FOREIGN_KEY_CHECKS = 1');
		$this->CI->public_cache->forget_group('listing');
	}

	protected function reload()
	{
		return $this->CI->budgets->year($this->year->public_id);
	}

	protected function category($section, $name, $parent = NULL)
	{
		return $this->CI->budgets->save_category($this->reload(), array(
			'section' => $section, 'name' => $name, 'parent_id' => $parent ? (int) $parent->id : NULL,
		), $this->actor->id);
	}

	protected function revision($type)
	{
		return $this->CI->budgets->save_revision($this->reload(), array('revision_type' => $type), $this->actor->id);
	}

	protected function line($revision, $category, $amount, $note = '')
	{
		$this->CI->budgets->save_line($this->reload(), $revision, array(
			'category_public_id' => $category->public_id, 'amount' => (string) $amount, 'variance_note' => $note,
		), $this->actor->id);
	}

	/** Susun satu tahun yang seimbang: pendapatan = belanja, pembiayaan nol. */
	protected function balanced_year()
	{
		$income = $this->category('income', 'Dana Desa');
		$spending = $this->category('expenditure', 'Bidang Pembangunan');
		$original = $this->revision('original');
		$this->line($original, $income, 500000000);
		$this->line($original, $spending, 500000000);
		return array($original, $income, $spending);
	}

	public function test_draft_year_is_not_public(): void
	{
		$this->balanced_year();
		$this->assertSame(array(), $this->CI->budgets->published_years());
		$this->assertNull($this->CI->budgets->published_budget(2099));
	}

	public function test_revision_types_cannot_be_duplicated(): void
	{
		$this->revision('original');
		try
		{
			$this->CI->budgets->save_revision($this->reload(), array('revision_type' => 'original'), $this->actor->id);
			$this->fail('Dua revisi dengan jenis sama harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertSame(409, $e->http_status);
		}
	}

	public function test_component_mismatch_requires_a_note(): void
	{
		$parent = $this->category('expenditure', 'Bidang Pembangunan');
		$child = $this->category('expenditure', 'Kegiatan Jalan Desa', $parent);
		$income = $this->category('income', 'Dana Desa');
		$original = $this->revision('original');
		$this->line($original, $income, 100000000);
		$this->line($original, $parent, 100000000);
		$this->line($original, $child, 60000000);

		$errors = implode(' ', $this->CI->budgets->validate_year($this->reload())['errors']);
		$this->assertStringContainsString('tidak sama dengan nilai induknya', $errors);

		// Dengan catatan selisih, pemeriksaan itu lolos.
		$this->line($original, $parent, 100000000, 'Sisa 40 juta belum dirinci menjadi kegiatan.');
		$errors = implode(' ', $this->CI->budgets->validate_year($this->reload())['errors']);
		$this->assertStringNotContainsString('tidak sama dengan nilai induknya', $errors);
	}

	public function test_unbalanced_financing_is_rejected(): void
	{
		$income = $this->category('income', 'Dana Desa');
		$spending = $this->category('expenditure', 'Bidang Pembangunan');
		$original = $this->revision('original');
		$this->line($original, $income, 400000000);
		$this->line($original, $spending, 500000000);

		$errors = implode(' ', $this->CI->budgets->validate_year($this->reload())['errors']);
		$this->assertStringContainsString('belum direkonsiliasi dengan pembiayaan', $errors);

		// Defisit 100 juta ditutup penerimaan pembiayaan (SILPA).
		$financing = $this->category('financing_in', 'SILPA tahun sebelumnya');
		$this->line($original, $financing, 100000000);
		$errors = implode(' ', $this->CI->budgets->validate_year($this->reload())['errors']);
		$this->assertStringNotContainsString('pembiayaan', $errors);
	}

	public function test_realization_over_budget_needs_an_explanation(): void
	{
		list($original, $income, $spending) = $this->balanced_year();
		$realization = $this->revision('realization');
		$this->line($realization, $income, 500000000);
		$this->line($realization, $spending, 550000000);
		// Defisit realisasi ditutup pembiayaan supaya hanya aturan kelebihan yang diuji.
		$financing = $this->category('financing_in', 'SILPA');
		// Penerimaan yang tidak dianggarkan pun wajib dijelaskan, bukan hanya belanja.
		$this->line($realization, $financing, 50000000, 'SILPA tidak dianggarkan pada anggaran murni.');

		$errors = implode(' ', $this->CI->budgets->validate_year($this->reload())['errors']);
		$this->assertStringContainsString('melebihi anggaran', $errors);

		$this->line($realization, $spending, 550000000, 'Dasar: Perbup perubahan nomor 12/2099.');
		$errors = implode(' ', $this->CI->budgets->validate_year($this->reload())['errors']);
		$this->assertStringNotContainsString('melebihi anggaran', $errors);
	}

	public function test_public_document_must_be_redacted(): void
	{
		$this->balanced_year();
		$year = $this->reload();
		$this->CI->db->insert('budget_documents', array(
			'public_id' => $this->CI->crypto->public_id(),
			'budget_year_id' => (int) $year->id,
			'title' => 'Laporan realisasi belum disamarkan',
			'public_document_id' => NULL,
			'is_redacted' => 0,
			'created_at' => utc_now(), 'updated_at' => utc_now(),
		));
		// Tanpa salinan publik, dokumen itu tidak menghalangi apa pun.
		$this->assertStringNotContainsString('disamarkan', implode(' ', $this->CI->budgets->validate_year($this->reload())['errors']));

		$document = $this->CI->db->where('budget_year_id', (int) $year->id)->get('budget_documents')->row();
		$this->CI->db->query('SET FOREIGN_KEY_CHECKS = 0');
		$this->CI->db->where('id', (int) $document->id)->update('budget_documents', array('public_document_id' => 999999));
		$this->CI->db->query('SET FOREIGN_KEY_CHECKS = 1');
		$this->assertStringContainsString('disamarkan', implode(' ', $this->CI->budgets->validate_year($this->reload())['errors']));
	}

	public function test_workflow_order_is_enforced(): void
	{
		$this->balanced_year();

		try
		{
			$this->CI->budgets->transition($this->reload(), 'setujui', $this->actor->id);
			$this->fail('Persetujuan sebelum verifikasi harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertSame(409, $e->http_status);
		}

		try
		{
			$this->CI->budgets->publish($this->reload(), 'Coba terbit', $this->actor->id);
			$this->fail('Publikasi sebelum persetujuan harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertSame(409, $e->http_status);
		}

		$this->CI->budgets->transition($this->reload(), 'verifikasi', $this->actor->id);
		$this->CI->budgets->transition($this->reload(), 'setujui', $this->actor->id);
		$this->assertSame(1, $this->CI->budgets->publish($this->reload(), 'Publikasi uji', $this->actor->id));
		$this->CI->public_cache->forget_group('listing');
		$this->assertNotNull($this->CI->budgets->published_budget(2099));
	}

	public function test_changing_a_number_cancels_verification(): void
	{
		list($original, $income, $spending) = $this->balanced_year();
		$this->CI->budgets->transition($this->reload(), 'verifikasi', $this->actor->id);
		$this->assertSame('verified', $this->reload()->status);

		$this->line($original, $spending, 500000000, 'Perbaikan angka.');
		$this->assertSame('reconciling', $this->reload()->status,
			'Angka berubah, verifikasi sebelumnya tidak boleh tetap berlaku');
	}

	public function test_locked_period_cannot_be_edited_directly(): void
	{
		list($original, $income, $spending) = $this->balanced_year();
		$this->CI->budgets->transition($this->reload(), 'kunci', $this->actor->id);
		try
		{
			$this->line($original, $spending, 600000000);
			$this->fail('Periode terkunci tidak boleh disunting langsung');
		}
		catch (DomainRuleException $e)
		{
			$this->assertStringContainsString('terkunci', $e->getMessage());
		}
	}

	public function test_unpublish_clears_the_public_snapshot(): void
	{
		$this->balanced_year();
		$this->CI->budgets->transition($this->reload(), 'verifikasi', $this->actor->id);
		$this->CI->budgets->transition($this->reload(), 'setujui', $this->actor->id);
		$this->CI->budgets->publish($this->reload(), 'Publikasi uji', $this->actor->id);
		$this->CI->public_cache->forget_group('listing');
		$this->assertNotNull($this->CI->budgets->published_budget(2099));

		$this->CI->budgets->unpublish($this->reload(), 'Ditarik untuk pengujian', $this->actor->id);
		$this->CI->public_cache->forget_group('listing');
		$this->assertNull($this->CI->budgets->published_budget(2099));
		$this->assertSame(array(), $this->CI->budgets->published_years());
		$this->assertSame(1, $this->CI->db->where('target_type', 'budget')->count_all_results('cms_publication_snapshots'),
			'Riwayat snapshot tetap tersimpan');
	}

	public function test_snapshot_keeps_revisions_separate(): void
	{
		list($original, $income, $spending) = $this->balanced_year();
		$realization = $this->revision('realization');
		$this->line($realization, $income, 480000000);
		$this->line($realization, $spending, 480000000);

		$this->CI->budgets->transition($this->reload(), 'verifikasi', $this->actor->id);
		$this->CI->budgets->transition($this->reload(), 'setujui', $this->actor->id);
		$this->CI->budgets->publish($this->reload(), 'Publikasi uji', $this->actor->id);
		$this->CI->public_cache->forget_group('listing');

		$snapshot = $this->CI->budgets->published_budget(2099);
		$this->assertEquals(500000000, $snapshot['revisions']['original']['totals']['expenditure']);
		$this->assertEquals(480000000, $snapshot['revisions']['realization']['totals']['expenditure']);
		$this->assertArrayNotHasKey('amended', $snapshot['revisions']);
	}

	public function test_negative_amount_is_rejected(): void
	{
		$category = $this->category('expenditure', 'Bidang Pembangunan');
		$original = $this->revision('original');
		try
		{
			$this->line($original, $category, -1000);
			$this->fail('Nilai negatif harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('amount', $e->errors);
		}
	}
}
