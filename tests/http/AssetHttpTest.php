<?php

require_once __DIR__."/HttpTestCase.php";

/**
 * Tahap 10 v1.2 lewat HTTP: halaman QR publik.
 *
 * Yang dijaga: setiap keadaan punya halamannya sendiri (tidak pernah 500 atau kosong),
 * field sensitif tidak pernah keluar, halaman selalu noindex dan di luar sitemap, serta
 * modul nonaktif menutup halamannya.
 */
class AssetHttpTest extends HttpTestCase {

	const PASSWORD = 'KataSandiUjiCoba2026';

	/** @var object */
	protected $unit;

	/** @var string */
	protected $token;

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('AssetService', NULL, 'assets');
		$this->clean();

		$actor = $this->actor();
		$category = $this->CI->db->get_where('asset_categories', array('code' => 'PERALATAN'))->row();
		$register = $this->CI->assets->save_register(array(
			'name' => '[Uji http] Laptop pelayanan', 'category_id' => (int) $category->id,
			'legacy_asset_code' => 'UJI-HTTP-01', 'acquisition_year' => 2023,
			'acquisition_value' => '17500000',
		), (int) $actor->id);
		$this->unit = $this->CI->assets->create_unit($register, array(
			'asset_tag' => 'UJI-HTTP-01-001', 'serial_number' => 'SN-RAHASIA-HTTP', 'brand' => 'Contoh',
		), (int) $actor->id);
		$this->unit = $this->CI->assets->change_status($this->unit, array(
			'to_lifecycle' => 'active', 'to_condition' => 'good', 'reason' => 'Barang diterima pengurus aset.',
		), (int) $actor->id);
		$this->token = $this->CI->assets->issue_token($this->unit, (int) $actor->id);
		$this->CI->db->query('DELETE FROM rate_limits');
	}

	protected function tearDown(): void
	{
		$this->clean();
		parent::tearDown();
	}

	protected function clean()
	{
		$this->CI->db->query('SET FOREIGN_KEY_CHECKS = 0');
		foreach ($this->CI->db->like('name', '[Uji http]')->get('asset_registers')->result() as $register)
		{
			foreach ($this->CI->db->where('register_id', (int) $register->id)->get('asset_units')->result() as $unit)
			{
				foreach ($this->CI->db->where('asset_unit_id', (int) $unit->id)->get('asset_label_batch_items')->result() as $item)
				{
					$this->CI->db->where('batch_id', (int) $item->batch_id)->delete('asset_label_batch_items');
					$this->CI->db->where('id', (int) $item->batch_id)->delete('asset_label_batches');
				}
				$this->CI->db->where('asset_unit_id', (int) $unit->id)->delete('asset_qr_tokens');
				$this->CI->db->where('asset_unit_id', (int) $unit->id)->delete('asset_status_events');
				$this->CI->db->where('id', (int) $unit->id)->delete('asset_units');
			}
			$this->CI->db->where('id', (int) $register->id)->delete('asset_registers');
		}
		$this->CI->db->query('SET FOREIGN_KEY_CHECKS = 1');
	}

	protected function actor()
	{
		$user = $this->CI->db->get_where('users', array('username' => 'aktor.aset.test'))->row();
		return $user ?: $this->make_user('aktor.aset.test', array('asset_manager', 'asset_verifier'));
	}

	public function test_scan_shows_identity_without_sensitive_fields(): void
	{
		$response = $this->get('aset/q/'.$this->token);
		$this->assertSame(200, $response['status']);
		$this->assertStringContainsString('[Uji http] Laptop pelayanan', $response['body']);
		$this->assertStringContainsString('UJI-HTTP-01-001', $response['body']);
		$this->assertStringContainsString('Pemerintah Desa Cihawuk', $response['body']);
		$this->assertStringContainsString('bukan bukti kepemilikan', $response['body']);

		// Field sensitif tidak boleh muncul sama sekali.
		$this->assertStringNotContainsString('SN-RAHASIA', $response['body']);
		$this->assertStringNotContainsString('17500000', $response['body']);
		$this->assertStringNotContainsString('17.500.000', $response['body']);
		$this->assertStringNotContainsString('aktor.aset.test', $response['body']);

		// Halaman wajib noindex dan tidak masuk sitemap.
		$this->assertStringContainsString('noindex', implode(' ', $response['headers']['x-robots-tag'] ?? array()));
		$this->assertStringContainsString('noindex', $response['body']);
		$this->assertStringNotContainsString('aset/q/', $this->get('sitemap.xml')['body']);
	}

	public function test_every_state_has_its_own_page(): void
	{
		$this->assertStringContainsString('QR tidak dikenali', $this->get('aset/q/'.str_repeat('a', 32))['body']);
		$this->assertStringContainsString('QR tidak dikenali', $this->get('aset/q/bukan-token')['body']);

		$actor = $this->actor();
		$this->CI->assets->change_status($this->CI->assets->unit($this->unit->public_id), array(
			'to_lifecycle' => 'in_maintenance', 'to_condition' => 'minor_damage',
			'reason' => 'Masuk bengkel untuk perbaikan layar.',
		), (int) $actor->id);
		$this->assertStringContainsString('sedang dalam pemeliharaan', $this->get('aset/q/'.$this->token)['body']);

		$this->CI->assets->change_status($this->CI->assets->unit($this->unit->public_id), array(
			'to_lifecycle' => 'lost', 'to_condition' => 'not_assessed',
			'reason' => 'Tidak ditemukan pada audit fisik.',
		), (int) $actor->id);
		$this->assertStringContainsString('tercatat hilang', $this->get('aset/q/'.$this->token)['body']);

		$this->CI->assets->revoke_token($this->CI->assets->unit($this->unit->public_id),
			'Label diganti.', (int) $actor->id);
		$revoked = $this->get('aset/q/'.$this->token);
		$this->assertSame(200, $revoked['status']);
		$this->assertStringContainsString('sudah tidak berlaku', $revoked['body']);
	}

	public function test_draft_unit_is_not_acknowledged_publicly(): void
	{
		$actor = $this->actor();
		$register = $this->CI->db->where('legacy_asset_code', 'UJI-HTTP-01')->get('asset_registers')->row();
		$draft = $this->CI->assets->create_unit($register, array('asset_tag' => 'UJI-HTTP-01-002'), (int) $actor->id);
		$token = $this->CI->assets->issue_token($draft, (int) $actor->id);

		$response = $this->get('aset/q/'.$token);
		$this->assertSame(200, $response['status']);
		$this->assertStringContainsString('QR tidak dikenali', $response['body'],
			'Unit yang masih draft belum boleh diakui publik');
	}

	public function test_disabled_module_closes_the_qr_page(): void
	{
		$actor = $this->actor();
		$this->CI->load->library('FeatureModuleService', NULL, 'modules');
		$this->CI->modules->set_state('public_asset_qr', 'disabled', 'Dinonaktifkan untuk pengujian modul.', (int) $actor->id);
		try
		{
			$this->assertSame(404, $this->get('aset/q/'.$this->token)['status']);
		}
		finally
		{
			$this->CI->modules->set_state('public_asset_qr', 'active', 'Dikembalikan setelah pengujian modul.', (int) $actor->id);
		}
		$this->assertSame(200, $this->get('aset/q/'.$this->token)['status']);
	}

	public function test_asset_admin_requires_permission(): void
	{
		$this->make_user('editor.aset.test', array('content_editor'), 'active');
		$this->make_user('pengurus.aset.test', array('asset_manager'), 'active');

		$this->login('editor.aset.test', self::PASSWORD, 'editor');
		$this->assertSame(403, $this->get('admin/inventaris', 'editor')['status']);

		$this->login('pengurus.aset.test', self::PASSWORD, 'manager');
		$this->assertSame(303, $this->get('admin/aset', 'manager')['status'], 'Halaman aset lama diarahkan ke Inventaris');
		$listing = $this->get('admin/inventaris', 'manager');
		$this->assertSame(200, $listing['status']);
		// Pengurus aset pada preset ini tidak memegang izin nilai keuangan.
		$this->assertStringNotContainsString('17.500.000', $listing['body']);
	}

	public function test_labels_can_be_printed_for_a_whole_register(): void
	{
		$this->make_user('label.aset.test', array('asset_manager'), 'active');
		$this->login('label.aset.test', self::PASSWORD, 'label');
		$actor = $this->actor();
		$register = $this->CI->db->where('legacy_asset_code', 'UJI-HTTP-01')->get('asset_registers')->row();
		$draft = $this->CI->assets->create_unit($register, array('asset_tag' => 'UJI-HTTP-01-003'), (int) $actor->id);

		$unit_page = $this->get('admin/inventaris/'.$this->unit->public_id, 'label');
		$this->assertSame(200, $unit_page['status']);
		$this->assertStringContainsString('aset/q/'.$this->token.'"', $unit_page['body'],
			'QR aktif selalu dapat ditampilkan lagi di halaman unit');

		$register_path = 'admin/aset/'.$register->public_id.'?lanjutan=1';
		$this->assertStringContainsString('Cetak QR semua unit', $this->get($register_path, 'label')['body']);
		$created = $this->post_form($register_path, 'admin/aset/label', array('register' => $register->public_id), 'label');
		$this->assertSame(303, $created['status']);
		$this->assertStringContainsString('admin/aset/label/', (string) $created['location']);

		$sheet = $this->get(substr((string) $created['location'], strpos((string) $created['location'], 'admin/aset/label/')), 'label');
		$this->assertSame(200, $sheet['status']);
		$this->assertStringContainsString('UJI-HTTP-01-001', $sheet['body']);
		$this->assertStringContainsString('UJI-HTTP-01-003', $sheet['body']);
		$this->assertStringContainsString('aset/q/'.$this->token.'"', $sheet['body']);
		$this->assertNotNull($this->CI->assets->qr_token($this->CI->assets->unit($draft->public_id)),
			'Unit tanpa QR otomatis dibuatkan QR saat dicetak');

		$this->make_user('editor2.aset.test', array('content_editor'), 'active');
		$this->login('editor2.aset.test', self::PASSWORD, 'editor2');
		$this->assertSame(403, $this->get(substr((string) $created['location'], strpos((string) $created['location'], 'admin/aset/label/')), 'editor2')['status']);
	}
}
