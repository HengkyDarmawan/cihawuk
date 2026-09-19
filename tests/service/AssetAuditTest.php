<?php

/**
 * Tahap 10 v1.2: audit fisik aset.
 *
 * Yang dijaga: daftar target dibekukan saat sesi diterbitkan, temuan tidak menimpa master,
 * sesi tidak dapat ditutup selama masih ada temuan menggantung, dan koreksi setelah
 * penutupan hanya lewat addendum.
 */
class AssetAuditTest extends CiTestCase {

	/** @var object */
	protected $actor;

	/** @var object */
	protected $unit;

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('AssetService', NULL, 'assets');
		$this->CI->load->library('AssetAuditService', NULL, 'audits');
		$existing = $this->CI->db->get_where('users', array('username' => 'auditaset.svc.test'))->row();
		$this->actor = $existing ?: $this->make_user('auditaset.svc.test', array('asset_manager', 'asset_auditor', 'asset_verifier'));
		$this->clean();

		$category = $this->CI->db->get_where('asset_categories', array('code' => 'PERALATAN'))->row();
		$register = $this->CI->assets->save_register(array(
			'name' => '[Uji audit] Kursi rapat', 'category_id' => (int) $category->id,
			'legacy_asset_code' => 'UJI-AUDIT-01',
		), $this->actor->id);
		$this->unit = $this->CI->assets->create_unit($register, array('asset_tag' => 'UJI-AUDIT-01-001'), $this->actor->id);
		$this->unit = $this->CI->assets->change_status($this->unit, array(
			'to_lifecycle' => 'active', 'to_condition' => 'good', 'reason' => 'Barang diterima pengurus aset.',
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
		foreach ($this->CI->db->like('name', '[Uji audit]')->get('asset_audit_sessions')->result() as $session)
		{
			$this->CI->db->where('audit_session_id', (int) $session->id)->delete('asset_audit_addenda');
			foreach ($this->CI->db->where('audit_session_id', (int) $session->id)->get('asset_audit_targets')->result() as $target)
			{
				$this->CI->db->where('audit_target_id', (int) $target->id)->delete('asset_audit_findings');
			}
			$this->CI->db->where('audit_session_id', (int) $session->id)->delete('asset_audit_targets');
			$this->CI->db->where('id', (int) $session->id)->delete('asset_audit_sessions');
		}
		foreach ($this->CI->db->like('name', '[Uji audit]')->get('asset_registers')->result() as $register)
		{
			foreach ($this->CI->db->where('register_id', (int) $register->id)->get('asset_units')->result() as $unit)
			{
				$this->CI->db->where('asset_unit_id', (int) $unit->id)->delete('asset_status_events');
				$this->CI->db->where('asset_unit_id', (int) $unit->id)->delete('asset_qr_tokens');
				$this->CI->db->where('id', (int) $unit->id)->delete('asset_units');
			}
			$this->CI->db->where('id', (int) $register->id)->delete('asset_registers');
		}
		$this->CI->db->query('SET FOREIGN_KEY_CHECKS = 1');
	}

	protected function published_session()
	{
		$session = $this->CI->audits->create_session(array('name' => '[Uji audit] Semester satu'), $this->actor->id);
		$this->CI->audits->publish_session($session, $this->actor->id);
		return $this->CI->audits->session($session->public_id);
	}

	protected function my_target($session)
	{
		foreach ($this->CI->audits->targets($session) as $target)
		{
			if ($target->asset_tag === 'UJI-AUDIT-01-001')
			{
				return $target;
			}
		}
		$this->fail('Target unit uji tidak ditemukan pada sesi audit.');
	}

	public function test_publishing_freezes_the_target_list(): void
	{
		$session = $this->published_session();
		$target = $this->my_target($session);
		$snapshot = json_decode($target->asset_snapshot_json, TRUE);
		$this->assertSame('good', $snapshot['condition_status']);

		// Master berubah setelah sesi terbit; snapshot target tidak ikut berubah.
		$this->CI->assets->change_status($this->CI->assets->unit($this->unit->public_id), array(
			'to_lifecycle' => 'active', 'to_condition' => 'major_damage',
			'reason' => 'Rusak setelah sesi audit diterbitkan.',
		), $this->actor->id);

		$again = json_decode($this->my_target($session)->asset_snapshot_json, TRUE);
		$this->assertSame('good', $again['condition_status'], 'Snapshot target harus tetap beku');
	}

	public function test_finding_does_not_overwrite_master_until_corrected(): void
	{
		$session = $this->published_session();
		$target = $this->my_target($session);

		$finding = $this->CI->audits->record_finding($session, array(
			'target_id' => (int) $target->id, 'existence_result' => 'match',
			'observed_condition' => 'minor_damage', 'note' => 'Sandaran retak.',
		), $this->actor->id);

		$this->assertSame('good', $this->CI->assets->unit($this->unit->public_id)->condition_status,
			'Temuan tidak boleh langsung menimpa master');
		$this->assertNotEmpty($this->CI->audits->differences($session));

		$this->CI->audits->verify_finding($finding, TRUE, 'Sesuai pemeriksaan ulang.', $this->actor->id);
		$this->assertSame('good', $this->CI->assets->unit($this->unit->public_id)->condition_status,
			'Verifikasi saja belum mengubah master');

		$this->CI->audits->apply_finding($this->CI->audits->finding($finding->public_id), $this->actor->id);
		$after = $this->CI->assets->unit($this->unit->public_id);
		$this->assertSame('minor_damage', $after->condition_status);
		$this->assertCount(2, $this->CI->assets->status_events($after), 'Koreksi meninggalkan histori');
	}

	public function test_not_found_finding_marks_the_unit_lost_after_correction(): void
	{
		$session = $this->published_session();
		$target = $this->my_target($session);
		$finding = $this->CI->audits->record_finding($session, array(
			'target_id' => (int) $target->id, 'existence_result' => 'not_found',
			'observed_condition' => 'not_assessed',
		), $this->actor->id);
		$this->CI->audits->verify_finding($finding, TRUE, '', $this->actor->id);
		$this->CI->audits->apply_finding($this->CI->audits->finding($finding->public_id), $this->actor->id);

		$this->assertSame('lost', $this->CI->assets->unit($this->unit->public_id)->lifecycle_status);
	}

	public function test_session_cannot_close_with_pending_findings(): void
	{
		$session = $this->published_session();
		$target = $this->my_target($session);
		$finding = $this->CI->audits->record_finding($session, array(
			'target_id' => (int) $target->id, 'existence_result' => 'match', 'observed_condition' => 'good',
		), $this->actor->id);

		try
		{
			$this->CI->audits->close_session($session, $this->actor->id);
			$this->fail('Sesi dengan temuan menggantung tidak boleh ditutup');
		}
		catch (DomainRuleException $e)
		{
			$this->assertStringContainsString('belum diverifikasi', $e->getMessage());
		}

		$this->CI->audits->verify_finding($finding, TRUE, '', $this->actor->id);
		$this->CI->audits->close_session($this->CI->audits->session($session->public_id), $this->actor->id);
		$this->assertSame('closed', $this->CI->audits->session($session->public_id)->status);
	}

	public function test_closed_session_only_accepts_addenda(): void
	{
		$session = $this->published_session();
		$target = $this->my_target($session);
		$this->CI->audits->close_session($session, $this->actor->id);
		$session = $this->CI->audits->session($session->public_id);

		try
		{
			$this->CI->audits->record_finding($session, array(
				'target_id' => (int) $target->id, 'existence_result' => 'match', 'observed_condition' => 'good',
			), $this->actor->id);
			$this->fail('Sesi tertutup tidak boleh menerima temuan baru');
		}
		catch (DomainRuleException $e)
		{
			$this->assertSame(409, $e->http_status);
		}

		$this->CI->audits->add_addendum($session, array('reason' => 'Koreksi lokasi setelah laporan dibekukan.'), $this->actor->id);
		$this->assertCount(1, $this->CI->audits->addenda($session));
	}

	public function test_verified_finding_turns_on_the_public_verified_badge(): void
	{
		$session = $this->published_session();
		$target = $this->my_target($session);
		$this->assertFalse($this->CI->assets->public_unit_view($this->unit)['verified']);

		$finding = $this->CI->audits->record_finding($session, array(
			'target_id' => (int) $target->id, 'existence_result' => 'match', 'observed_condition' => 'good',
		), $this->actor->id);
		$this->CI->audits->verify_finding($finding, TRUE, '', $this->actor->id);

		$view = $this->CI->assets->public_unit_view($this->CI->assets->unit($this->unit->public_id));
		$this->assertTrue($view['verified']);
		$this->assertNotNull($view['verified_at']);
	}
}
