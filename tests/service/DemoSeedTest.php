<?php

require_once __DIR__.'/../CiTestCase.php';
require_once ROOTPATH.'database/seeds/DemoSeeder.php';

/**
 * Data demonstrasi harus dapat dihapus tuntas.
 *
 * Modul baru sengaja tidak memakai awalan "[Demo]" pada namanya supaya wajar saat
 * diperagakan, jadi jaminan kebersihannya bergantung sepenuhnya pada jejak `demo_records`.
 * Kelas ini membuktikan seluruh isian kembali ke jumlah semula setelah dihapus.
 */
class DemoSeedTest extends CiTestCase {

	/** Tabel yang harus kembali persis ke jumlah semula. */
	const TABLES = array(
		'users', 'tickets', 'places', 'facilities', 'facility_services', 'potentials',
		'businesses', 'budget_years', 'budget_revisions', 'budget_categories', 'budget_lines',
		'asset_registers', 'asset_units', 'asset_qr_tokens', 'asset_label_batches',
		'inventory_items', 'inventory_transactions', 'inventory_ledger', 'inventory_stocktakes',
		'warehouse_locations', 'posts', 'events', 'demo_records',
	);

	protected function counts()
	{
		$out = array();
		foreach (self::TABLES as $table)
		{
			$out[$table] = (int) $this->CI->db->count_all_results($table);
		}
		return $out;
	}

	protected function tearDown(): void
	{
		// Kelas lain tidak boleh melihat sisa data demo apa pun.
		$seeder = new DemoSeeder($this->CI);
		$seeder->purge();
		parent::tearDown();
	}

	public function test_demo_data_is_created_then_removed_completely(): void
	{
		$before = $this->counts();

		$seeder = new DemoSeeder($this->CI);
		$seeder->run();

		$after = $this->counts();
		// Modul yang paling penting untuk peragaan memang harus terisi.
		foreach (array('facilities', 'businesses', 'budget_years', 'asset_units',
			'inventory_items', 'inventory_ledger', 'posts', 'events') as $table)
		{
			$this->assertGreaterThan($before[$table], $after[$table], $table.' harus terisi data demo');
		}
		$this->assertGreaterThan(0, $after['demo_records'], 'Setiap entitas akar wajib punya jejak.');

		$seeder->purge();

		$final = $this->counts();
		foreach (self::TABLES as $table)
		{
			$this->assertSame($before[$table], $final[$table],
				$table.' tidak kembali ke jumlah semula setelah purge.');
		}
	}

	public function test_every_tracked_type_has_a_purge_rule(): void
	{
		$seeder = new DemoSeeder($this->CI);
		$seeder->run();

		$types = array();
		foreach ($this->CI->db->select('DISTINCT(entity_type) AS entity_type', FALSE)
			->get('demo_records')->result() as $row)
		{
			$types[] = $row->entity_type;
		}
		$this->assertNotEmpty($types);

		$seeder->purge();
		$left = (int) $this->CI->db->count_all_results('demo_records');
		$this->assertSame(0, $left,
			'Jenis entitas yang tidak punya aturan purge akan tertinggal di demo_records: '
			.implode(', ', $types));
	}

	public function test_demo_seeder_refuses_to_run_on_production(): void
	{
		// Penjaga ini ada dua lapis: di perintah CLI dan di dalam seeder sendiri.
		$this->assertNotSame('production', ENVIRONMENT);
		$source = file_get_contents(ROOTPATH.'database/seeds/DemoSeeder.php');
		$this->assertStringContainsString("ENVIRONMENT === 'production'", $source);
	}
}
