<?php

/**
 * Tahap 10 v1.2: aset, unit fisik, QR, dan impor staging.
 *
 * Yang dijaga: register dan unit adalah dua tingkat terpisah, pemecahan unit wajib beralasan,
 * perubahan status selalu meninggalkan histori, token QR hanya ada sekali, halaman publik
 * tidak pernah memuat field sensitif, dan impor ulang tidak menggandakan staging.
 */
class AssetTest extends CiTestCase {

	/** @var object */
	protected $actor;

	/** @var object */
	protected $register;

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('AssetService', NULL, 'assets');
		$existing = $this->CI->db->get_where('users', array('username' => 'aset.svc.test'))->row();
		$this->actor = $existing ?: $this->make_user('aset.svc.test', array('asset_manager', 'asset_verifier'));
		$this->clean();
		$category = $this->CI->db->get_where('asset_categories', array('code' => 'PERALATAN'))->row();
		$this->register = $this->CI->assets->save_register(array(
			'name' => '[Uji] Laptop kantor desa',
			'category_id' => (int) $category->id,
			'legacy_asset_code' => 'UJI-LAPTOP-01',
			'source_volume_raw' => '2 Unit',
			'acquisition_year' => 2023,
			'acquisition_value' => '15000000',
		), $this->actor->id);
	}

	protected function tearDown(): void
	{
		$this->clean();
		parent::tearDown();
	}

	protected function clean()
	{
		$this->CI->db->query('SET FOREIGN_KEY_CHECKS = 0');
		foreach ($this->CI->db->like('name', '[Uji]')->get('asset_registers')->result() as $register)
		{
			foreach ($this->CI->db->where('register_id', (int) $register->id)->get('asset_units')->result() as $unit)
			{
				$this->CI->db->where('asset_unit_id', (int) $unit->id)->delete('asset_qr_tokens');
				$this->CI->db->where('asset_unit_id', (int) $unit->id)->delete('asset_status_events');
				$this->CI->db->where('asset_unit_id', (int) $unit->id)->delete('asset_movements');
				$this->CI->db->where('asset_unit_id', (int) $unit->id)->delete('asset_loans');
				$this->CI->db->where('asset_unit_id', (int) $unit->id)->delete('asset_maintenance');
				$this->CI->db->where('id', (int) $unit->id)->delete('asset_units');
			}
			$this->CI->db->where('id', (int) $register->id)->delete('asset_registers');
		}
		$this->CI->db->like('legacy_code', 'UJI-')->delete('asset_import_rows');
		$this->CI->db->query('SET FOREIGN_KEY_CHECKS = 1');
	}

	protected function unit($tag = 'UJI-LAPTOP-01-001')
	{
		return $this->CI->assets->create_unit($this->register, array(
			'asset_tag' => $tag, 'serial_number' => 'SN-RAHASIA-123', 'brand' => 'Contoh',
		), $this->actor->id);
	}

	public function test_register_and_unit_are_separate_levels(): void
	{
		$this->assertSame('2 Unit', $this->register->source_volume_raw,
			'Volume sumber disimpan apa adanya, tidak diubah menjadi angka');
		$this->assertCount(0, $this->CI->assets->units($this->register),
			'Register tidak otomatis membuat unit fisik');

		$unit = $this->unit();
		$this->assertSame('draft', $unit->lifecycle_status);
		$this->assertSame('not_assessed', $unit->condition_status);
		$this->assertCount(1, $this->CI->assets->units($this->register));
	}

	public function test_unit_split_requires_a_reason(): void
	{
		try
		{
			$this->CI->assets->propose_units($this->register, 2, 'pendek', $this->actor->id);
			$this->fail('Pemecahan unit tanpa alasan memadai harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('reason', $e->errors);
		}

		$created = $this->CI->assets->propose_units($this->register, 2,
			'Dokumen menyebut 2 Unit dan pengurus memastikan keduanya barang terpisah.', $this->actor->id, 'UJI-LAPTOP');
		$this->assertCount(2, $created);
		$this->assertSame('UJI-LAPTOP-001', $created[0]->asset_tag);
	}

	public function test_serial_number_is_encrypted_at_rest(): void
	{
		$unit = $this->unit();
		$row = $this->CI->db->get_where('asset_units', array('id' => (int) $unit->id))->row();
		$this->assertNotNull($row->serial_number_ciphertext);
		$this->assertStringNotContainsString('SN-RAHASIA', (string) $row->serial_number_ciphertext);
		$this->assertSame('SN-RAHASIA-123', $this->CI->crypto->decrypt($row->serial_number_ciphertext));
	}

	public function test_status_change_requires_reason_and_writes_history(): void
	{
		$unit = $this->unit();
		try
		{
			$this->CI->assets->change_status($unit, array('to_lifecycle' => 'active', 'reason' => 'pendek'), $this->actor->id);
			$this->fail('Alasan pendek harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('reason', $e->errors);
		}

		$updated = $this->CI->assets->change_status($unit, array(
			'to_lifecycle' => 'active', 'to_condition' => 'good',
			'reason' => 'Barang diterima dan diperiksa pengurus aset.',
		), $this->actor->id);
		$this->assertSame('active', $updated->lifecycle_status);
		$this->assertSame('good', $updated->condition_status);

		$events = $this->CI->assets->status_events($updated);
		$this->assertCount(1, $events);
		$this->assertSame('draft', $events[0]->from_lifecycle);
		$this->assertSame('active', $events[0]->to_lifecycle);
	}

	public function test_deactivating_keeps_the_unit_and_its_history(): void
	{
		$unit = $this->unit();
		$this->CI->assets->change_status($unit, array(
			'to_lifecycle' => 'active', 'to_condition' => 'good', 'reason' => 'Barang diterima pengurus aset.',
		), $this->actor->id);
		$unit = $this->CI->assets->unit($unit->public_id);
		$this->CI->assets->change_status($unit, array(
			'to_lifecycle' => 'inactive', 'to_condition' => 'major_damage',
			'reason' => 'Rusak berat dan tidak digunakan lagi.',
		), $this->actor->id);

		$after = $this->CI->assets->unit($unit->public_id);
		$this->assertNotNull($after, 'Unit tidak boleh dihapus saat dinonaktifkan');
		$this->assertSame('inactive', $after->lifecycle_status);
		$this->assertCount(2, $this->CI->assets->status_events($after));
	}

	public function test_qr_token_is_shown_once_and_can_be_revoked(): void
	{
		$unit = $this->unit();
		$token = $this->CI->assets->issue_token($unit, $this->actor->id);
		$this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $token);

		// Token asli tidak disimpan; yang tersimpan hanya digestnya.
		$row = $this->CI->assets->active_token($unit);
		$this->assertStringNotContainsString($token, (string) $row->token_digest);
		$this->assertSame(0, $this->CI->db->like('safe_metadata_json', $token)->count_all_results('audit_logs'),
			'Token asli tidak boleh masuk audit log');

		$resolved = $this->CI->assets->resolve_token($token);
		$this->assertSame('ok', $resolved['status']);
		$this->assertSame((int) $unit->id, (int) $resolved['unit']->id);

		$this->CI->assets->revoke_token($unit, 'Label hilang di lapangan.', $this->actor->id);
		$this->assertSame('revoked', $this->CI->assets->resolve_token($token)['status']);
		$this->assertSame('unknown', $this->CI->assets->resolve_token('00000000000000000000000000000000')['status']);
	}

	public function test_issuing_a_new_token_revokes_the_previous_one(): void
	{
		$unit = $this->unit();
		$first = $this->CI->assets->issue_token($unit, $this->actor->id);
		$second = $this->CI->assets->issue_token($unit, $this->actor->id, 'Label diganti karena sobek.');

		$this->assertSame('revoked', $this->CI->assets->resolve_token($first)['status']);
		$this->assertSame('ok', $this->CI->assets->resolve_token($second)['status']);
		$this->assertSame(2, (int) $this->CI->assets->active_token($unit)->token_version);
	}

	public function test_public_view_hides_sensitive_fields(): void
	{
		$sensitive = $this->CI->assets->save_location(array(
			'code' => 'UJI-BRANKAS', 'name' => 'Ruang brankas', 'is_sensitive' => 1,
		));
		$unit = $this->CI->assets->create_unit($this->register, array(
			'asset_tag' => 'UJI-LAPTOP-01-009', 'serial_number' => 'SN-RAHASIA-999',
			'location_id' => (int) $sensitive->id,
		), $this->actor->id);
		$this->CI->assets->change_status($unit, array(
			'to_lifecycle' => 'active', 'to_condition' => 'good', 'reason' => 'Barang diterima pengurus aset.',
		), $this->actor->id);

		$view = $this->CI->assets->public_unit_view($this->CI->assets->unit($unit->public_id));
		$json = json_encode($view);
		$this->assertStringNotContainsString('SN-RAHASIA', $json, 'Nomor seri tidak boleh publik');
		$this->assertStringNotContainsString('15000000', $json, 'Nilai perolehan tidak boleh publik');
		$this->assertNull($view['location_name'], 'Lokasi sensitif tidak boleh publik');
		$this->assertArrayNotHasKey('acquisition_value', $view);
		$this->assertArrayNotHasKey('custodian_user_id', $view);
		$this->assertFalse($view['verified'], 'Badge terverifikasi hanya setelah audit diverifikasi');

		$this->CI->db->where('code', 'UJI-BRANKAS')->delete('asset_locations');
	}

	public function test_loan_and_maintenance_do_not_silently_change_condition(): void
	{
		$unit = $this->unit();
		$this->CI->assets->change_status($unit, array(
			'to_lifecycle' => 'active', 'to_condition' => 'minor_damage', 'reason' => 'Ada lecet pada casing.',
		), $this->actor->id);
		$unit = $this->CI->assets->unit($unit->public_id);

		$loan = $this->CI->assets->checkout($unit, array(
			'borrower_name' => 'Peminjam Uji', 'purpose' => 'Kegiatan musyawarah desa',
		), $this->actor->id);
		$row = $this->CI->db->get_where('asset_loans', array('id' => (int) $loan->id))->row();
		$this->assertStringNotContainsString('Peminjam Uji', (string) $row->borrower_name_ciphertext);

		try
		{
			$this->CI->assets->checkout($unit, array('borrower_name' => 'Lain', 'purpose' => 'Lain'), $this->actor->id);
			$this->fail('Dua peminjaman aktif pada satu unit harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertSame(409, $e->http_status);
		}

		$maintenance = $this->CI->assets->create_maintenance($unit, array('complaint' => 'Layar berkedip'), $this->actor->id);
		$this->CI->assets->complete_maintenance($maintenance, array(
			'action_taken' => 'Ganti kabel fleksibel', 'condition_after' => 'good',
		), $this->actor->id);

		// Kondisi unit tetap seperti semula sampai dinilai lewat perubahan status.
		$this->assertSame('minor_damage', $this->CI->assets->unit($unit->public_id)->condition_status);
	}

	public function test_movement_only_changes_location_after_acceptance(): void
	{
		$unit = $this->unit();
		$target = $this->CI->assets->save_location(array('code' => 'UJI-GUDANG', 'name' => 'Gudang uji'));
		$movement = $this->CI->assets->request_movement($unit, array(
			'to_location_id' => (int) $target->id, 'reason' => 'Dipindahkan ke gudang setelah renovasi.',
		), $this->actor->id);

		$this->assertNull($this->CI->assets->unit($unit->public_id)->location_id,
			'Mengajukan mutasi belum boleh mengubah lokasi');

		$this->CI->assets->accept_movement($movement, $this->actor->id);
		$this->assertSame((int) $target->id, (int) $this->CI->assets->unit($unit->public_id)->location_id);
		$this->CI->db->where('code', 'UJI-GUDANG')->delete('asset_locations');
	}

	public function test_duplicate_asset_tag_is_rejected(): void
	{
		$this->unit();
		try
		{
			$this->unit();
			$this->fail('Asset tag ganda harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('asset_tag', $e->errors);
		}
	}
}
