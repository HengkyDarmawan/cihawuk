<?php

/** Tahap 1 v1.2: status modul, dependency, histori dan audit. */
class FeatureModuleServiceTest extends CiTestCase {

	/** @var object */
	protected $actor;

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('FeatureModuleService', NULL, 'modules');
		$this->truncate_service_data();
		$this->CI->db->query('DELETE FROM feature_module_histories');
		$this->CI->db->query('DELETE FROM admin_activity_events');
		$this->CI->db->query('DELETE FROM audit_logs');
		// Kelas ini menguji mesin status, jadi kedua modul aset selalu dimulai dari nonaktif
		// dan dikembalikan ke keadaan seed (aktif) pada tearDown.
		$this->CI->db->where_in('code', array('assets', 'public_asset_qr'))
			->update('feature_modules', array('state' => 'disabled'));
		$this->CI->modules->flush();
		$this->actor = $this->make_user('modul.svc.test', array('super_admin'));
	}

	protected function tearDown(): void
	{
		// Kembalikan state seed agar tes lain tidak terpengaruh.
		$this->CI->db->where('code', 'news')->update('feature_modules', array('state' => 'active'));
		// Kedua modul ini sudah dibangun pada tahap 10, jadi keadaan seednya aktif.
		$this->CI->db->where('code', 'assets')->update('feature_modules', array('state' => 'active'));
		$this->CI->db->where('code', 'public_asset_qr')->update('feature_modules', array('state' => 'active'));
		$this->CI->modules->flush();
		parent::tearDown();
	}

	public function test_seed_states_are_honest_about_unbuilt_modules(): void
	{
		// Modul yang belum dibangun tidak boleh mengklaim aktif.
		foreach (array('village_letters') as $code)
		{
			$this->assertSame('disabled', $this->CI->modules->state($code), $code.' belum dibangun');
		}
		$this->assertSame('active', $this->CI->modules->state('complaints'));
		// Modul tak dikenal ditolak (deny by default).
		$this->assertSame('disabled', $this->CI->modules->state('modul_karangan'));
		$this->assertFalse($this->CI->modules->backend_available('modul_karangan'));
	}

	public function test_state_change_requires_reason_and_records_history(): void
	{
		try
		{
			$this->CI->modules->set_state('assets', 'active', 'pendek', $this->actor->id);
			$this->fail('Alasan pendek harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertSame(422, $e->http_status);
		}

		$from = $this->CI->modules->set_state('assets', 'internal_only', 'Persiapan modul aset untuk tahap berikutnya', $this->actor->id);
		$this->assertSame('disabled', $from);
		$this->assertSame('internal_only', $this->CI->modules->state('assets'));

		$history = $this->CI->modules->history('assets');
		$this->assertCount(1, $history);
		$this->assertSame('disabled', $history[0]->from_state);
		$this->assertSame('internal_only', $history[0]->to_state);
		$this->assertSame((int) $this->actor->id, (int) $history[0]->actor_user_id);

		$audit = $this->CI->db->where('action', 'module.state_changed')->get('audit_logs')->row();
		$this->assertNotNull($audit);
		$this->assertSame('assets', $audit->module_code);
		$this->assertSame('assets', $audit->entity_id);
		$this->assertSame(1, $this->CI->db->where('module_code', 'assets')->count_all_results('admin_activity_events'));
	}

	public function test_dependency_is_enforced_both_directions(): void
	{
		// QR publik tidak dapat aktif selama modul aset nonaktif.
		try
		{
			$this->CI->modules->set_state('public_asset_qr', 'active', 'Mencoba mengaktifkan QR lebih dulu', $this->actor->id);
			$this->fail('Dependency harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertSame(409, $e->http_status);
		}
		$this->assertSame('disabled', $this->CI->modules->state('public_asset_qr'));

		$this->CI->modules->set_state('assets', 'active', 'Aktifkan modul aset lebih dulu', $this->actor->id);
		$this->CI->modules->set_state('public_asset_qr', 'active', 'QR aktif setelah modul aset tersedia', $this->actor->id);
		$this->assertSame('active', $this->CI->modules->state('public_asset_qr'));

		// Modul induk tidak dapat dimatikan selama turunannya hidup.
		try
		{
			$this->CI->modules->set_state('assets', 'disabled', 'Mencoba mematikan modul induk', $this->actor->id);
			$this->fail('Modul induk dengan turunan aktif harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertSame(409, $e->http_status);
			$this->assertStringContainsString('Halaman publik QR aset', $e->getMessage());
		}
		$this->assertSame('active', $this->CI->modules->state('assets'));
	}

	public function test_public_and_backend_availability_per_state(): void
	{
		$this->CI->modules->set_state('news', 'internal_only', 'Menyembunyikan berita dari publik sementara', $this->actor->id);
		$this->assertTrue($this->CI->modules->backend_available('news'));
		$this->assertSame('unavailable', $this->CI->modules->public_status('news'));

		$this->CI->modules->set_state('news', 'public_readonly', 'Publik boleh membaca, input dimatikan', $this->actor->id);
		$this->assertSame('available', $this->CI->modules->public_status('news'));
		$this->assertFalse($this->CI->modules->public_writes_allowed('news'));

		$this->CI->modules->set_state('news', 'maintenance', 'Pemeliharaan modul berita', $this->actor->id);
		$this->assertSame('maintenance', $this->CI->modules->public_status('news'));
	}

	public function test_route_matching_uses_longest_prefix(): void
	{
		list($module, $area) = $this->CI->modules->match_route('lacak/detail');
		$this->assertSame('complaints', $module->code);
		$this->assertSame('public', $area);

		list($module, $area) = $this->CI->modules->match_route('admin/laporan/CHW-2026-ABCD1234');
		$this->assertSame('complaints', $module->code);
		$this->assertSame('admin', $area);

		// Prefix hanya cocok per segmen, bukan awalan string.
		$this->assertNull($this->CI->modules->match_route('laporanku'));
		$this->assertNull($this->CI->modules->match_route('masuk'));
	}
}
