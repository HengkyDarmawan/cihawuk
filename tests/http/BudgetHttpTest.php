<?php

require_once __DIR__."/HttpTestCase.php";

/**
 * Tahap 8 v1.2 lewat HTTP: halaman transparansi hanya membaca snapshot terbit, pemisahan
 * izin isi/verifikasi/terbit ditegakkan, dan menarik snapshot mengosongkan halamannya lagi.
 */
class BudgetHttpTest extends HttpTestCase {

	const PASSWORD = 'KataSandiUjiCoba2026';

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('BudgetService', NULL, 'budgets');
		$this->clean();
	}

	protected function tearDown(): void
	{
		$this->clean();
		parent::tearDown();
	}

	protected function clean()
	{
		$this->CI->db->query('SET FOREIGN_KEY_CHECKS = 0');
		foreach ($this->CI->db->where('fiscal_year >=', 2090)->get('budget_years')->result() as $row)
		{
			$id = (int) $row->id;
			$this->CI->db->where(array('target_type' => 'budget', 'target_id' => $id))->delete('cms_publication_snapshots');
			$this->CI->db->query('DELETE l FROM budget_lines l JOIN budget_revisions r ON r.id = l.revision_id WHERE r.budget_year_id = '.$id);
			$this->CI->db->where('budget_year_id', $id)->delete('budget_documents');
			$this->CI->db->where('budget_year_id', $id)->delete('budget_categories');
			$this->CI->db->where('budget_year_id', $id)->delete('budget_revisions');
			$this->CI->db->where('id', $id)->delete('budget_years');
		}
		$this->CI->db->query('SET FOREIGN_KEY_CHECKS = 1');
		$this->CI->public_cache->forget_group('listing');
		$this->CI->public_cache->invalidate_page('transparansi');
		$this->CI->public_cache->invalidate_page('home');
	}

	protected function actor()
	{
		$user = $this->CI->db->get_where('users', array('username' => 'aktor.keuangan.test'))->row();
		return $user ?: $this->make_user('aktor.keuangan.test', array('finance_manager', 'finance_verifier', 'content_publisher'));
	}

	/** Susun satu tahun seimbang lalu terbitkan; mengembalikan barisnya. */
	protected function published_year($fiscal_year = 2099)
	{
		$actor = $this->actor();
		$year = $this->CI->budgets->create_year(array('fiscal_year' => $fiscal_year), (int) $actor->id);
		$income = $this->CI->budgets->save_category($year, array('section' => 'income', 'name' => 'Dana Desa Uji'), (int) $actor->id);
		$spending = $this->CI->budgets->save_category($year, array('section' => 'expenditure', 'name' => 'Bidang Pembangunan Uji'), (int) $actor->id);
		$original = $this->CI->budgets->save_revision($year, array('revision_type' => 'original'), (int) $actor->id);
		foreach (array($income, $spending) as $category)
		{
			$this->CI->budgets->save_line($this->CI->budgets->year($year->public_id), $original, array(
				'category_public_id' => $category->public_id, 'amount' => '750000000',
			), (int) $actor->id);
		}
		$this->CI->budgets->transition($this->CI->budgets->year($year->public_id), 'verifikasi', (int) $actor->id);
		$this->CI->budgets->transition($this->CI->budgets->year($year->public_id), 'setujui', (int) $actor->id);
		$this->CI->budgets->publish($this->CI->budgets->year($year->public_id), 'Publikasi HTTP', (int) $actor->id);
		$this->CI->public_cache->forget_group('listing');
		$this->CI->public_cache->invalidate_page('transparansi');
		return $this->CI->budgets->year($year->public_id);
	}

	public function test_page_is_empty_until_published(): void
	{
		$before = $this->get('transparansi/anggaran');
		$this->assertSame(200, $before['status']);
		$this->assertStringContainsString('Belum ada APBDes yang diterbitkan', $before['body']);

		$this->published_year();

		$after = $this->get('transparansi/anggaran');
		$this->assertStringContainsString('Bidang Pembangunan Uji', $after['body']);
		$this->assertStringContainsString('Rp750.000.000', $after['body']);
		$this->assertStringContainsString('Realisasi tahun ini belum diterbitkan', $after['body']);
		$this->assertStringContainsString('Angka sementara', $after['body']);
	}

	public function test_csv_and_json_downloads_are_safe(): void
	{
		$this->published_year();

		$csv = $this->get('transparansi/anggaran/2099/original/unduh.csv');
		$this->assertSame(200, $csv['status']);
		$this->assertSame("\xEF\xBB\xBF", substr($csv['body'], 0, 3));
		$this->assertStringContainsString('Bidang Pembangunan Uji', $csv['body']);
		foreach (preg_split('/\r?\n/', substr($csv['body'], 3)) as $line)
		{
			foreach (str_getcsv($line) as $cell)
			{
				if ($cell === NULL OR $cell === '')
				{
					continue;
				}
				if (strpos('=+-@', $cell[0]) !== FALSE)
				{
					$this->fail('Sel CSV dimulai dengan karakter formula: '.$cell);
				}
			}
		}

		$json = $this->get('transparansi/anggaran/2099/unduh.json');
		$this->assertSame(200, $json['status']);
		$decoded = json_decode($json['body'], TRUE);
		$this->assertSame(2099, $decoded['year']['fiscal_year']);
		$this->assertArrayHasKey('original', $decoded['revisions']);
	}

	public function test_unpublish_empties_the_page(): void
	{
		$year = $this->published_year();
		$this->assertStringContainsString('Bidang Pembangunan Uji', $this->get('transparansi/anggaran')['body']);

		$actor = $this->actor();
		$this->CI->budgets->unpublish($this->CI->budgets->year($year->public_id), 'Ditarik untuk pengujian', (int) $actor->id);
		$this->CI->public_cache->forget_group('listing');

		$this->assertStringContainsString('Belum ada APBDes yang diterbitkan', $this->get('transparansi/anggaran')['body']);
		$this->assertSame(404, $this->get('transparansi/anggaran/2099/unduh.json')['status']);
	}

	public function test_permissions_are_separate_across_the_workflow(): void
	{
		$this->make_user('pengelola.keuangan.test', array('finance_manager'), 'active');
		$this->make_user('verifikator.keuangan.test', array('finance_verifier'), 'active');
		$this->make_user('editor.keuangan.test', array('content_editor'), 'active');

		$actor = $this->actor();
		$year = $this->CI->budgets->create_year(array('fiscal_year' => 2098), (int) $actor->id);
		$income = $this->CI->budgets->save_category($year, array('section' => 'income', 'name' => 'Dana Desa Izin'), (int) $actor->id);
		$spending = $this->CI->budgets->save_category($year, array('section' => 'expenditure', 'name' => 'Belanja Izin'), (int) $actor->id);
		$original = $this->CI->budgets->save_revision($year, array('revision_type' => 'original'), (int) $actor->id);
		foreach (array($income, $spending) as $category)
		{
			$this->CI->budgets->save_line($this->CI->budgets->year($year->public_id), $original, array(
				'category_public_id' => $category->public_id, 'amount' => '100000000',
			), (int) $actor->id);
		}
		$path = 'admin/keuangan/'.rawurlencode($year->public_id);

		// Editor konten tidak punya permission keuangan sama sekali.
		$this->login('editor.keuangan.test', self::PASSWORD, 'editor');
		$this->assertSame(403, $this->get('admin/keuangan', 'editor')['status']);

		// Pengelola mengisi angka, tetapi tidak boleh memverifikasi maupun menerbitkan.
		$this->login('pengelola.keuangan.test', self::PASSWORD, 'manager');
		$this->assertSame(200, $this->get($path, 'manager')['status']);
		$this->assertSame(403, $this->post_form($path, $path.'/alur/verifikasi', array(), 'manager')['status']);
		$this->assertSame(403, $this->post_form($path, $path.'/alur/terbitkan', array(), 'manager')['status']);

		// Verifikator memverifikasi, tetapi tidak boleh menyetujui atau menerbitkan.
		$this->login('verifikator.keuangan.test', self::PASSWORD, 'verifier');
		$this->assertSame(303, $this->post_form($path, $path.'/alur/verifikasi', array(), 'verifier')['status']);
		$this->assertSame('verified', $this->CI->budgets->year($year->public_id)->status);
		$this->assertSame(403, $this->post_form($path, $path.'/alur/setujui', array(), 'verifier')['status']);
		$this->assertSame(403, $this->post_form($path, $path.'/alur/terbitkan', array(), 'verifier')['status']);
		$this->assertNull($this->CI->budgets->published_budget(2098));
	}
}
