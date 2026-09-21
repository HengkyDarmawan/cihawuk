<?php

/**
 * Inventaris Desa: satu form tambah barang, kode otomatis, QR per barang, aksi cepat,
 * dan riwayat gabungan.
 */
class InventoryTest extends CiTestCase {

	/** @var object */
	protected $actor;

	/** @var int */
	protected $category_id;

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('InventoryService', NULL, 'inventory');
		$existing = $this->CI->db->get_where('users', array('username' => 'inventaris.svc.test'))->row();
		$this->actor = $existing ?: $this->make_user('inventaris.svc.test', array('admin_desa'));
		$this->clean();
		$this->category_id = (int) $this->CI->db->get_where('asset_categories', array('code' => 'PERALATAN'))->row('id');
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
		foreach ($db->like('name', '[Uji Inv]')->get('asset_registers')->result() as $register)
		{
			foreach ($db->where('register_id', (int) $register->id)->get('asset_units')->result() as $unit)
			{
				foreach (array('asset_qr_tokens', 'asset_status_events', 'asset_movements', 'asset_loans', 'asset_maintenance', 'asset_label_batch_items') as $table)
				{
					$db->where('asset_unit_id', (int) $unit->id)->delete($table);
				}
				$db->where('id', (int) $unit->id)->delete('asset_units');
			}
			$db->where('id', (int) $register->id)->delete('asset_registers');
		}
		$db->like('name', '[Uji Inv]')->delete('asset_locations');
		$db->like('name', '[Uji Inv]')->delete('asset_categories');
		$db->query('SET FOREIGN_KEY_CHECKS = 1');
	}

	protected function add(array $extra = array())
	{
		return $this->CI->inventory->create(array_merge(array(
			'nama' => '[Uji Inv] Kursi lipat', 'kategori_id' => $this->category_id, 'merk' => 'Chitose',
			'jumlah' => 1, 'kondisi' => 'good', 'lokasi_baru' => '[Uji Inv] Balai Desa', 'tahun' => '2024',
		), $extra), $this->actor->id);
	}

	public function test_create_makes_active_items_with_sequential_codes_and_qr(): void
	{
		$first_code = $this->CI->inventory->next_code();
		$units = $this->add(array('jumlah' => 3));
		$this->assertCount(3, $units);
		$prefix = 'INV-'.date('Y').'-';
		$n = (int) substr($first_code, strlen($prefix));
		foreach ($units as $i => $unit)
		{
			$this->assertSame($prefix.str_pad((string) ($n + $i), 4, '0', STR_PAD_LEFT), $unit->asset_tag);
			$this->assertSame('active', $unit->lifecycle_status);
			$this->assertSame('good', $unit->condition_status);
			$url = $this->CI->assets->qr_url($unit);
			$this->assertNotNull($url, 'Setiap barang langsung punya QR');
			$token = substr($url, strrpos($url, '/') + 1);
			$this->assertSame('ok', $this->CI->assets->resolve_token($token)['status']);
		}
		$item = $this->CI->inventory->item($units[0]->public_id);
		$this->assertSame('[Uji Inv] Balai Desa', $item->location_name, 'Lokasi baru dibuat otomatis');
		$register = $this->CI->db->get_where('asset_registers', array('id' => (int) $units[0]->register_id))->row();
		$this->assertSame('verified', $register->verification_status);
	}

	public function test_update_keeps_code_and_qr_and_is_logged(): void
	{
		$unit = $this->add()[0];
		$url = $this->CI->assets->qr_url($unit);
		$this->CI->inventory->update($unit, array('nama' => '[Uji Inv] Kursi lipat besi', 'kategori_id' => $this->category_id, 'merk' => 'Chitose', 'tahun' => '2024'), $this->actor->id);
		$after = $this->CI->assets->unit($unit->public_id);
		$this->assertSame($unit->asset_tag, $after->asset_tag);
		$this->assertSame($url, $this->CI->assets->qr_url($after));
		$titles = array_column($this->CI->inventory->history($after), 'detail', 'type');
		$this->assertStringContainsString('nama', $titles['updated']);
	}

	public function test_quick_actions_are_recorded_in_history(): void
	{
		$unit = $this->add()[0];
		$uid = $this->actor->id;
		$this->CI->inventory->move($unit, array('lokasi_baru' => '[Uji Inv] Gudang'), $uid);
		$unit = $this->CI->assets->unit($unit->public_id);
		$this->assertSame('[Uji Inv] Gudang', $this->CI->inventory->item($unit->public_id)->location_name, 'Pindah langsung berlaku');

		$this->CI->inventory->lend($unit, array('peminjam' => 'Pak RT', 'keperluan' => 'Rapat warga'), $uid);
		$this->CI->inventory->return_item($unit, array('kondisi' => 'minor_damage'), $uid);
		$unit = $this->CI->assets->unit($unit->public_id);
		$this->assertSame('minor_damage', $unit->condition_status);

		$this->CI->inventory->start_repair($unit, array('keluhan' => 'Kaki patah'), $uid);
		$unit = $this->CI->assets->unit($unit->public_id);
		$this->assertSame('in_maintenance', $unit->lifecycle_status);
		$this->CI->inventory->finish_repair($unit, array('tindakan' => 'Dilas', 'kondisi' => 'good'), $uid);
		$unit = $this->CI->assets->unit($unit->public_id);
		$this->assertSame('active', $unit->lifecycle_status);
		$this->assertSame('good', $unit->condition_status);

		$this->CI->inventory->set_status($unit, array('kondisi' => 'major_damage', 'status' => 'disposed', 'catatan' => 'rusak'), $uid);
		$unit = $this->CI->assets->unit($unit->public_id);
		$this->assertSame('disposed', $unit->lifecycle_status);

		$types = array_column($this->CI->inventory->history($unit), 'type');
		foreach (array('created', 'move', 'loan', 'return', 'repair', 'repair_done', 'status', 'qr') as $type)
		{
			$this->assertContains($type, $types, 'Riwayat memuat '.$type);
		}
		$this->assertSame('created', end($types), 'Riwayat terbaru di atas, penambahan paling bawah');
	}

	public function test_validation_uses_form_field_names(): void
	{
		try
		{
			$this->CI->inventory->create(array('nama' => '', 'kategori_id' => 0, 'jumlah' => 1, 'kondisi' => 'good'), $this->actor->id);
			$this->fail('Nama kosong harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('nama', $e->errors);
			$this->assertArrayHasKey('kategori_id', $e->errors);
		}
		$this->expectException(DomainRuleException::class);
		$this->add(array('jumlah' => 0));
	}
}
