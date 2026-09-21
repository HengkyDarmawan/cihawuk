<?php

require_once __DIR__.'/HttpTestCase.php';

/**
 * Inventaris Desa lewat HTTP: tambah barang dari satu form, QR langsung tampil di detail
 * dan daftar, aksi cepat tercatat di riwayat, cetak semua QR, dan izin tetap dijaga.
 */
class InventoryHttpTest extends HttpTestCase {

	const PASSWORD = 'KataSandiUjiCoba2026';

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('InventoryService', NULL, 'inventory');
		$this->clean();
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
		foreach ($db->like('name', '[Uji Web]')->get('asset_registers')->result() as $register)
		{
			foreach ($db->where('register_id', (int) $register->id)->get('asset_units')->result() as $unit)
			{
				foreach ($db->where('asset_unit_id', (int) $unit->id)->get('asset_label_batch_items')->result() as $item)
				{
					$db->where('batch_id', (int) $item->batch_id)->delete('asset_label_batch_items');
					$db->where('id', (int) $item->batch_id)->delete('asset_label_batches');
				}
				foreach (array('asset_qr_tokens', 'asset_status_events', 'asset_movements', 'asset_loans', 'asset_maintenance') as $table)
				{
					$db->where('asset_unit_id', (int) $unit->id)->delete($table);
				}
				$db->where('id', (int) $unit->id)->delete('asset_units');
			}
			$db->where('id', (int) $register->id)->delete('asset_registers');
		}
		$db->like('name', '[Uji Web]')->delete('asset_locations');
		$db->query('DELETE FROM rate_limits');
		$db->query('SET FOREIGN_KEY_CHECKS = 1');
	}

	public function test_add_item_then_see_qr_history_and_print(): void
	{
		$this->make_user('petugas.inv.test', array('petugas'));
		$this->make_user('admin.inv.test', array('admin_desa'));
		$this->login('admin.inv.test', self::PASSWORD, 'adm');
		$category = (int) $this->CI->db->get_where('asset_categories', array('code' => 'PERALATAN'))->row('id');

		$this->assertSame(200, $this->get('admin/inventaris', 'adm')['status']);
		$created = $this->post_form('admin/inventaris/tambah', 'admin/inventaris/tambah', array(
			'nama' => '[Uji Web] Proyektor', 'kategori_id' => $category, 'merk' => 'Epson', 'jumlah' => 1,
			'lokasi_baru' => '[Uji Web] Aula', 'kondisi' => 'good', 'tahun' => '2025', 'harga' => '4500000',
		), 'adm');
		$this->assertSame(303, $created['status']);
		$this->assertStringContainsString('admin/inventaris/', (string) $created['location']);
		$path = substr((string) $created['location'], strpos((string) $created['location'], 'admin/inventaris/'));

		$detail = $this->get($path, 'adm');
		$this->assertSame(200, $detail['status']);
		$this->assertStringContainsString('data-qr="', $detail['body'], 'QR langsung tampil di detail barang');
		$this->assertStringContainsString('Barang ditambahkan', $detail['body'], 'Riwayat tampil');
		$this->assertStringContainsString('Rp 4.500.000', $detail['body']);

		$list = $this->get('admin/inventaris', 'adm');
		$this->assertStringContainsString('[Uji Web] Proyektor', $list['body']);
		$this->assertStringContainsString('data-qr-show="', $list['body'], 'Tombol QR per barang di daftar');

		$moved = $this->post_form($path, $path.'/pindah', array('location_id' => '', 'lokasi_baru' => '[Uji Web] Kantor'), 'adm');
		$this->assertSame(303, $moved['status']);
		$this->assertStringContainsString('Pindah lokasi', $this->get($path, 'adm')['body']);

		$print = $this->post_form('admin/inventaris', 'admin/inventaris/cetak-qr', array('q' => '[Uji Web]'), 'adm');
		$this->assertSame(303, $print['status']);
		$sheet = $this->get(substr((string) $print['location'], strpos((string) $print['location'], 'admin/aset/label/')), 'adm');
		$this->assertSame(200, $sheet['status']);
		$this->assertStringContainsString('[Uji Web] Proyektor', $sheet['body']);

		// Petugas dapat melihat dan mencatat, tetapi tidak melihat harga dan tidak dapat mengubah data.
		$this->login('petugas.inv.test', self::PASSWORD, 'ptg');
		$staff_view = $this->get($path, 'ptg');
		$this->assertSame(200, $staff_view['status']);
		$this->assertStringNotContainsString('4.500.000', $staff_view['body']);
		$this->assertSame(403, $this->get($path.'/ubah', 'ptg')['status']);
	}

	public function test_residents_cannot_open_inventory(): void
	{
		$this->make_user('warga.inv.test', array('resident'));
		$this->login('warga.inv.test', self::PASSWORD, 'w');
		$this->assertContains($this->get('admin/inventaris', 'w')['status'], array(302, 303, 403));
	}
}
