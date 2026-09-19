<?php

/** RBAC-01..03, FLOW-06, ANON-02, NET-01 (idempotency) di level service. */
class AccessControlTest extends CiTestCase {

	protected function setUp(): void
	{
		parent::setUp();
		$this->truncate_service_data();
	}

	public function test_officer_only_sees_assigned_tickets(): void
	{
		$admin = $this->make_user('admin.acl.test', array('service_admin'));
		$officer = $this->make_user('petugas.acl.test', array('officer'));
		$other = $this->make_user('petugas2.acl.test', array('officer'));
		$r = $this->submit_ticket();
		$t = $this->CI->workflow->perform($r['ticket'], 'start_verification', array(), $this->staff_actor($admin));
		$t = $this->CI->workflow->perform($t, 'assign', array('assignee_id' => $officer->id), $this->staff_actor($admin));

		$this->assertTrue($this->CI->authz->ticket_abilities($officer->id, $t)['work']);
		$this->assertFalse($this->CI->authz->ticket_abilities($other->id, $t)['view']);

		$this->CI->load->model('Ticket_query_model', 'ticket_query');
		$this->assertSame(1, $this->CI->ticket_query->count_all($officer->id));
		$this->assertSame(0, $this->CI->ticket_query->count_all($other->id));
	}

	public function test_editor_and_super_admin_have_no_ticket_access(): void
	{
		$editor = $this->make_user('editor.acl.test', array('content_editor'));
		$super = $this->make_user('super.acl.test', array('super_admin'));
		$r = $this->submit_ticket();
		foreach (array($editor, $super) as $user)
		{
			$this->assertFalse($this->CI->authz->ticket_abilities($user->id, $r['ticket'])['view']);
			$this->assertFalse($this->CI->authz->apply_ticket_scope($this->CI->db->from('tickets t'), $user->id));
			$this->CI->db->reset_query();
		}
	}

	public function test_restricted_ticket_requires_confidential_permission(): void
	{
		$admin = $this->make_user('admin2.acl.test', array('service_admin'));
		$handler = $this->make_user('rahasia.acl.test', array('service_admin', 'confidential_handler'));
		$officer = $this->make_user('petugas3.acl.test', array('officer'));
		$r = $this->submit_ticket(array('category_id' => $this->category_id('PERILAKU_APARAT')));

		$this->assertFalse($this->CI->authz->ticket_abilities($admin->id, $r['ticket'])['view']);
		$this->assertTrue($this->CI->authz->ticket_abilities($handler->id, $r['ticket'])['view']);
		// Petugas biasa tidak dapat ditugaskan ke laporan rahasia.
		$this->assertFalse($this->CI->authz->can_be_assignee($officer->id, $r['ticket']));
	}

	public function test_conflict_of_interest_blocks_access_and_assignment(): void
	{
		$admin = $this->make_user('admin3.acl.test', array('service_admin'));
		$reported = $this->make_user('terlapor.acl.test', array('service_admin', 'officer'));
		$r = $this->submit_ticket();
		$this->CI->db->insert('ticket_conflicts', array('ticket_id' => $r['ticket']->id, 'user_id' => $reported->id, 'declared_by' => $admin->id, 'reason' => 'Petugas terlapor', 'created_at' => utc_now()));
		$this->assertFalse($this->CI->authz->ticket_abilities($reported->id, $r['ticket'])['view']);
		$this->assertFalse($this->CI->authz->can_be_assignee($reported->id, $r['ticket']));
		$t = $this->CI->workflow->perform($r['ticket'], 'start_verification', array(), $this->staff_actor($admin));
		$this->expectException(DomainRuleException::class);
		$this->CI->workflow->perform($t, 'assign', array('assignee_id' => $reported->id), $this->staff_actor($admin));
	}

	public function test_access_code_verification(): void
	{
		$a = $this->submit_ticket();
		$b = $this->submit_ticket();
		$this->assertNotNull($this->CI->ticket_access->verify($a['ticket']->public_code, $a['access_code']));
		// Format bebas (huruf kecil, tanda hubung) tetap diterima.
		$this->assertNotNull($this->CI->ticket_access->verify(strtolower($a['ticket']->public_code), strtolower($this->CI->ticket_access->format_code($a['access_code']))));
		$this->assertNull($this->CI->ticket_access->verify($a['ticket']->public_code, $b['access_code']));
		$this->assertNull($this->CI->ticket_access->verify($a['ticket']->public_code, 'SALAH'));
		$this->assertNull($this->CI->ticket_access->verify('CHW-0000-XXXXXXXX', $a['access_code']));
		$this->assertMatchesRegularExpression('/^CHW-\d{4}-[0-9A-Z]{8}$/', $a['ticket']->public_code);
	}

	public function test_idempotent_submission(): void
	{
		$key = $this->CI->crypto->random_hex(16);
		$ctx = array('idempotency' => array('key' => $key, 'scope' => 'session-uji'));
		$first = $this->submit_ticket(array(), $ctx);
		$second = $this->submit_ticket(array(), $ctx);
		$this->assertFalse($first['replay']);
		$this->assertTrue($second['replay']);
		$this->assertSame((int) $first['ticket']->id, (int) $second['ticket']->id);
		$this->assertNull($second['access_code'], 'Kode akses tidak dikeluarkan ulang saat replay');
		$this->assertSame(1, $this->CI->db->count_all('tickets'));

		try
		{
			$this->submit_ticket(array('title' => 'Judul berbeda untuk kunci yang sama'), $ctx);
			$this->fail('Payload berbeda dengan kunci sama harus konflik');
		}
		catch (DomainRuleException $e)
		{
			$this->assertSame(409, $e->http_status);
		}
		$this->assertSame(1, $this->CI->db->count_all('tickets'));
	}

	public function test_rate_limiter_blocks_and_expires(): void
	{
		$this->CI->clock->set('2026-09-20 00:00:00');
		for ($i = 0; $i < 5; $i++)
		{
			$this->assertSame(0, $this->CI->rate_limiter->attempt('track_ticket', 'CHW-TEST'));
		}
		$this->CI->rate_limiter->hit('track_ticket', 'CHW-TEST');
		$this->assertGreaterThan(0, $this->CI->rate_limiter->retry_after('track_ticket', 'CHW-TEST'));
		// Identifier lain tidak ikut terblokir.
		$this->assertSame(0, $this->CI->rate_limiter->retry_after('track_ticket', 'CHW-OTHER'));
		// Blokir bersifat sementara.
		$this->CI->clock->set('2026-09-20 02:00:00');
		$this->assertSame(0, $this->CI->rate_limiter->retry_after('track_ticket', 'CHW-TEST'));
		$stored = $this->CI->db->get('rate_limits')->result();
		foreach ($stored as $row)
		{
			$this->assertStringNotContainsString('CHW', $row->bucket_key, 'Identifier disimpan sebagai HMAC');
		}
	}
}
