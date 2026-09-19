<?php

/** SLA-01: akhir pekan, libur, setelah jam tutup, pause dan reopen. */
class SlaServiceTest extends CiTestCase {

	protected $calendar_id;

	protected function setUp(): void
	{
		parent::setUp();
		$this->truncate_service_data();
		$this->calendar_id = (int) $this->CI->db->select('id')->order_by('id')->limit(1)->get('business_calendars')->row('id');
		$this->CI->db->delete('business_holidays', array('calendar_id' => $this->calendar_id));
	}

	public function test_working_days_skip_weekend(): void
	{
		// Jumat 18 Sep 2026 15:00 WIB = 08:00 UTC. +3 hari kerja = Rabu 23 Sep 16:00 WIB = 09:00 UTC.
		$due = $this->CI->sla->due_after_working_days('2026-09-18 08:00:00', 3, $this->calendar_id);
		$this->assertSame('2026-09-23 09:00:00', $due);
	}

	public function test_after_closing_time_still_counts_next_days(): void
	{
		// Senin 21 Sep 2026 20:00 WIB (13:00 UTC). +1 hari kerja = Selasa 22 Sep 16:00 WIB.
		$due = $this->CI->sla->due_after_working_days('2026-09-21 13:00:00', 1, $this->calendar_id);
		$this->assertSame('2026-09-22 09:00:00', $due);
	}

	public function test_holiday_is_skipped_and_override_counts(): void
	{
		$this->CI->db->insert('business_holidays', array('calendar_id' => $this->calendar_id, 'date' => '2026-09-21', 'label' => 'Libur uji', 'is_working_override' => 0, 'created_at' => utc_now()));
		$this->CI->db->insert('business_holidays', array('calendar_id' => $this->calendar_id, 'date' => '2026-09-19', 'label' => 'Sabtu kerja uji', 'is_working_override' => 1, 'created_at' => utc_now()));
		$sla = new SlaService();   // cache kalender baru
		// Jumat 18 Sep → Sabtu 19 (kerja, override) → Senin 21 (libur) → Selasa 22 = hari kerja ke-2.
		$this->assertSame('2026-09-22 09:00:00', $sla->due_after_working_days('2026-09-18 08:00:00', 2, $this->calendar_id));
	}

	public function test_ticket_sla_snapshot_pause_and_reopen(): void
	{
		$officer = $this->make_user('petugas.sla.test', array('officer'));
		$admin = $this->make_user('admin.sla.test', array('service_admin'));
		$this->CI->clock->set('2026-09-18 08:00:00');
		$result = $this->submit_ticket();
		$ticket = $result['ticket'];
		$instance = $this->CI->sla->instance($ticket->id, 1);
		$this->assertSame('2026-09-23 09:00:00', $instance->verification_due_at);
		$snapshot = json_decode($instance->policy_snapshot_json, TRUE);
		$this->assertSame(3, $snapshot['verification_days']);

		// Perubahan kebijakan tidak mengubah tenggat kasus lama.
		$this->CI->db->where('code', 'DEFAULT')->update('sla_policies', array('verification_days' => 10));
		$this->assertSame('2026-09-23 09:00:00', $this->CI->sla->instance($ticket->id, 1)->verification_due_at);
		$this->CI->db->where('code', 'DEFAULT')->update('sla_policies', array('verification_days' => 3));

		// Terlambat verifikasi (indikator turunan, bukan status).
		$this->CI->clock->set('2026-09-24 01:00:00');
		$this->assertTrue($this->CI->sla->is_overdue($ticket));
		$this->assertSame('submitted', $this->CI->tickets->find($ticket->id)->status);

		$t = $this->CI->workflow->perform($ticket, 'start_verification', array(), $this->staff_actor($admin));
		$t = $this->CI->workflow->perform($t, 'request_information', array('message' => 'Mohon lengkapi lokasi kejadian.'), $this->staff_actor($admin));
		$this->assertTrue($this->CI->sla->paused($t->id, 1));
		$this->CI->clock->set('2026-09-25 01:00:00');
		$t = $this->CI->workflow->perform($t, 'provide_information', array('message' => 'Lokasinya di depan balai.'), $this->reporter_actor());
		$this->assertFalse($this->CI->sla->paused($t->id, 1));
		$this->assertSame('verifying', $t->status);
		// Keterlambatan verifikasi yang sudah terjadi tidak dihapus oleh jeda.
		$this->assertTrue($this->CI->sla->status($t)['verification']['overdue']);

		$t = $this->CI->workflow->perform($t, 'assign', array('assignee_id' => $officer->id), $this->staff_actor($admin));
		$status = $this->CI->sla->status($t);
		$this->assertNotNull($status['verification']['met_at']);
		$this->assertNotNull($status['first_response']['due_at']);

		$t = $this->CI->workflow->perform($t, 'accept_work', array(), $this->staff_actor($officer));
		$t = $this->CI->workflow->perform($t, 'propose_resolution', array('message' => 'Perbaikan selesai dikerjakan hari ini.'), $this->staff_actor($officer));
		$this->assertNotNull($this->CI->sla->status($t)['first_response']['met_at']);
		$this->assertNotNull($this->CI->sla->instance($t->id, 1)->confirmation_due_at);
		$t = $this->CI->workflow->perform($t, 'accept_result', array('rating' => 4), $this->reporter_actor());
		$this->assertSame('resolved', $t->status);

		$coordinator = $this->make_user('koordinator.sla.test', array('service_coordinator'));
		$this->add_unit_scope($coordinator->id, 'PELAYANAN', 'all');
		$this->CI->clock->set('2026-09-28 01:00:00');
		$t = $this->CI->workflow->perform($t, 'reopen', array('message' => 'Pelapor menyampaikan masalah muncul kembali.'), $this->staff_actor($coordinator));
		$this->assertSame(2, (int) $t->current_episode);
		$this->assertNotNull($this->CI->sla->instance($t->id, 2));
		$this->assertNotNull($this->CI->tickets->episode($t->id, 1)->resolved_at, 'Episode lama tetap tercatat selesai');
	}
}
