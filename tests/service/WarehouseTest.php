<?php

/**
 * Tahap 11 v1.2: gudang barang persediaan.
 *
 * Yang dijaga: saldo dihitung dari ledger append-only, stok negatif ditolak secara
 * transaksional, konversi satuan memakai bilangan bulat, dan selisih opname dihitung server.
 */
class WarehouseTest extends CiTestCase {

	/** @var object */
	protected $actor;

	/** @var object */
	protected $item;

	/** @var object */
	protected $gudang;

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('WarehouseService', NULL, 'warehouse');
		$existing = $this->CI->db->get_where('users', array('username' => 'gudang.svc.test'))->row();
		$this->actor = $existing ?: $this->make_user('gudang.svc.test', array('warehouse_officer'));
		$this->clean();

		$this->gudang = $this->CI->warehouse->save_location(array('code' => 'UJI-GD1', 'name' => 'Gudang uji satu'));
		$this->item = $this->CI->warehouse->save_item(array(
			'sku' => 'UJI-ATK-01', 'name' => 'Kertas HVS A4', 'base_unit' => 'lembar', 'minimum_stock' => '100',
		), $this->actor->id);
		$this->CI->warehouse->save_conversion($this->item, array(
			'from_unit' => 'rim', 'numerator' => 500, 'denominator' => 1,
		));
	}

	protected function tearDown(): void
	{
		$this->clean();
		parent::tearDown();
	}

	protected function clean()
	{
		$db = $this->CI->db;
		$db->query('SET FOREIGN_KEY_CHECKS = 0');
		$db->query('DELETE l FROM inventory_ledger l JOIN inventory_items i ON i.id = l.item_id WHERE i.sku LIKE "UJI-%"');
		$db->query('DELETE sl FROM inventory_stocktake_lines sl JOIN inventory_items i ON i.id = sl.item_id WHERE i.sku LIKE "UJI-%"');
		$db->query('DELETE s FROM inventory_stocktakes s JOIN warehouse_locations w ON w.id = s.location_id WHERE w.code LIKE "UJI-%"');
		$db->query('DELETE tl FROM inventory_transaction_lines tl JOIN inventory_items i ON i.id = tl.item_id WHERE i.sku LIKE "UJI-%"');
		$db->query('DELETE FROM inventory_transactions WHERE id NOT IN (SELECT transaction_id FROM inventory_transaction_lines)');
		$db->query('DELETE FROM inventory_unit_conversions WHERE item_id IN (SELECT id FROM inventory_items WHERE sku LIKE "UJI-%")');
		$db->like('sku', 'UJI-')->delete('inventory_items');
		$db->like('code', 'UJI-')->delete('warehouse_locations');
		$db->query('SET FOREIGN_KEY_CHECKS = 1');
	}

	protected function receive($quantity, $unit = 'lembar')
	{
		$transaction = $this->CI->warehouse->create_transaction(array(
			'transaction_type' => 'receipt', 'to_location_id' => (int) $this->gudang->id,
		), $this->actor->id);
		$this->CI->warehouse->add_line($transaction, array(
			'item_public_id' => $this->item->public_id, 'quantity' => $quantity, 'input_unit' => $unit,
		));
		return $this->CI->warehouse->post($transaction, $this->actor->id);
	}

	protected function issue($quantity)
	{
		$transaction = $this->CI->warehouse->create_transaction(array(
			'transaction_type' => 'issue', 'from_location_id' => (int) $this->gudang->id,
		), $this->actor->id);
		$this->CI->warehouse->add_line($transaction, array(
			'item_public_id' => $this->item->public_id, 'quantity' => $quantity,
		));
		return $this->CI->warehouse->post($transaction, $this->actor->id);
	}

	public function test_balance_comes_from_the_ledger(): void
	{
		$this->assertSame(0.0, $this->CI->warehouse->balance($this->item->id, $this->gudang->id));

		$this->receive(2, 'rim');
		$this->assertSame(1000.0, $this->CI->warehouse->balance($this->item->id, $this->gudang->id),
			'2 rim = 1000 lembar');

		$this->issue(250);
		$this->assertSame(750.0, $this->CI->warehouse->balance($this->item->id, $this->gudang->id));

		// Ledger bersifat append-only: setiap pergerakan menyisakan barisnya sendiri.
		$this->assertSame(2, (int) $this->CI->db->where('item_id', (int) $this->item->id)
			->count_all_results('inventory_ledger'));
	}

	public function test_negative_stock_is_rejected(): void
	{
		$this->receive(100);
		try
		{
			$this->issue(150);
			$this->fail('Pengeluaran melebihi stok harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertSame(409, $e->http_status);
			$this->assertStringContainsString('tidak cukup', $e->getMessage());
		}
		$this->assertSame(100.0, $this->CI->warehouse->balance($this->item->id, $this->gudang->id),
			'Saldo tidak berubah setelah pengeluaran ditolak');
	}

	public function test_two_issues_of_the_last_stock_cannot_both_succeed(): void
	{
		$this->receive(10);

		$first = $this->CI->warehouse->create_transaction(array(
			'transaction_type' => 'issue', 'from_location_id' => (int) $this->gudang->id,
		), $this->actor->id);
		$this->CI->warehouse->add_line($first, array('item_public_id' => $this->item->public_id, 'quantity' => 10));

		$second = $this->CI->warehouse->create_transaction(array(
			'transaction_type' => 'issue', 'from_location_id' => (int) $this->gudang->id,
		), $this->actor->id);
		$this->CI->warehouse->add_line($second, array('item_public_id' => $this->item->public_id, 'quantity' => 10));

		// Keduanya disiapkan atas saldo yang sama; hanya satu yang boleh lolos posting.
		$this->CI->warehouse->post($first, $this->actor->id);
		try
		{
			$this->CI->warehouse->post($second, $this->actor->id);
			$this->fail('Pengeluaran kedua atas stok terakhir harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertSame(409, $e->http_status);
		}
		$this->assertSame(0.0, $this->CI->warehouse->balance($this->item->id, $this->gudang->id));
	}

	public function test_unit_conversion_must_be_whole(): void
	{
		$this->CI->warehouse->save_conversion($this->item, array(
			'from_unit' => 'lusin', 'numerator' => 1, 'denominator' => 3,
		));
		try
		{
			$this->CI->warehouse->to_base($this->item, 1, 'lusin');
			$this->fail('Konversi yang menghasilkan pecahan harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertStringContainsString('pecahan', $e->getMessage());
		}
		$this->assertSame(3.0, $this->CI->warehouse->to_base($this->item, 9, 'lusin'));

		try
		{
			$this->CI->warehouse->to_base($this->item, 1, 'palet');
			$this->fail('Satuan tanpa konversi harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('input_unit', $e->errors);
		}
	}

	public function test_conversion_requires_positive_integers(): void
	{
		try
		{
			$this->CI->warehouse->save_conversion($this->item, array(
				'from_unit' => 'dus', 'numerator' => 0, 'denominator' => 1,
			));
			$this->fail('Numerator nol harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('numerator', $e->errors);
		}
	}

	public function test_transfer_moves_stock_between_locations(): void
	{
		$tujuan = $this->CI->warehouse->save_location(array('code' => 'UJI-GD2', 'name' => 'Gudang uji dua'));
		$this->receive(500);

		$transfer = $this->CI->warehouse->create_transaction(array(
			'transaction_type' => 'transfer',
			'from_location_id' => (int) $this->gudang->id,
			'to_location_id' => (int) $tujuan->id,
		), $this->actor->id);
		$this->CI->warehouse->add_line($transfer, array('item_public_id' => $this->item->public_id, 'quantity' => 200));
		$this->CI->warehouse->post($transfer, $this->actor->id);

		$this->assertSame(300.0, $this->CI->warehouse->balance($this->item->id, $this->gudang->id));
		$this->assertSame(200.0, $this->CI->warehouse->balance($this->item->id, $tujuan->id));
	}

	public function test_posted_transaction_cannot_be_edited(): void
	{
		$posted = $this->receive(50);
		try
		{
			$this->CI->warehouse->add_line($posted, array('item_public_id' => $this->item->public_id, 'quantity' => 10));
			$this->fail('Transaksi yang sudah diposting tidak boleh diubah');
		}
		catch (DomainRuleException $e)
		{
			$this->assertSame(409, $e->http_status);
		}
	}

	public function test_stocktake_variance_is_computed_by_the_server(): void
	{
		$this->receive(1000);
		$stocktake = $this->CI->warehouse->open_stocktake(array(
			'location_id' => (int) $this->gudang->id, 'name' => 'Opname uji',
		), $this->actor->id);

		$lines = $this->CI->warehouse->stocktake_lines($stocktake);
		$this->assertCount(1, $lines);
		$this->assertSame(1000.0, (float) $lines[0]->expected_quantity_base);

		$this->CI->warehouse->count_line($stocktake, array('line_id' => (int) $lines[0]->id, 'counted' => '980'), $this->actor->id);
		$lines = $this->CI->warehouse->stocktake_lines($stocktake);
		$this->assertSame(-20.0, (float) $lines[0]->variance_quantity_base);

		$adjustment = $this->CI->warehouse->close_stocktake($stocktake, $this->actor->id);
		$this->assertNotNull($adjustment);
		$this->assertSame(980.0, $this->CI->warehouse->balance($this->item->id, $this->gudang->id),
			'Saldo mengikuti hasil hitung setelah penyesuaian diposting');
		$this->assertSame('closed', $this->CI->warehouse->stocktake($stocktake->public_id)->status);
	}

	public function test_below_minimum_report(): void
	{
		$this->receive(50);
		$codes = array();
		foreach ($this->CI->warehouse->below_minimum() as $row)
		{
			$codes[] = $row->sku;
		}
		$this->assertContains('UJI-ATK-01', $codes);

		$this->receive(200);
		$codes = array();
		foreach ($this->CI->warehouse->below_minimum() as $row)
		{
			$codes[] = $row->sku;
		}
		$this->assertNotContains('UJI-ATK-01', $codes);
	}

	public function test_requester_cannot_approve_own_request(): void
	{
		$request = $this->CI->warehouse->create_request(array('purpose' => 'Kebutuhan ATK sekretariat'), $this->actor->id);
		try
		{
			$this->CI->warehouse->approve_request($request, $this->actor->id);
			$this->fail('Pemohon tidak boleh menyetujui permintaannya sendiri');
		}
		catch (DomainRuleException $e)
		{
			$this->assertSame(409, $e->http_status);
		}
	}
}
