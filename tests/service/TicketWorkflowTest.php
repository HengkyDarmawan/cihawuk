<?php

/** FLOW-01..06, ANON-01/03/05, RBAC-04 (sisi service). */
class TicketWorkflowTest extends CiTestCase {

	protected $admin;
	protected $officer;
	protected $officer2;

	protected function setUp(): void
	{
		parent::setUp();
		$this->truncate_service_data();
		$this->admin = $this->make_user('admin.flow.test', array('service_admin'));
		$this->officer = $this->make_user('petugas.flow.test', array('officer'));
		$this->officer2 = $this->make_user('petugas2.flow.test', array('officer'));
	}

	public function test_full_happy_path_records_history(): void
	{
		$r = $this->submit_ticket();
		$this->assertSame('submitted', $r['ticket']->status);
		$this->assertNotEmpty($r['access_code']);
		$t = $this->CI->workflow->perform($r['ticket'], 'start_verification', array(), $this->staff_actor($this->admin));
		$t = $this->CI->workflow->perform($t, 'assign', array('assignee_id' => $this->officer->id), $this->staff_actor($this->admin));
		$t = $this->CI->workflow->perform($t, 'accept_work', array(), $this->staff_actor($this->officer));
		$t = $this->CI->workflow->perform($t, 'follow_up', array('message' => 'Petugas sudah meninjau lokasi.'), $this->staff_actor($this->officer));
		$t = $this->CI->workflow->perform($t, 'propose_resolution', array('message' => 'Perbaikan selesai dan sudah diperiksa.'), $this->staff_actor($this->officer));
		$t = $this->CI->workflow->perform($t, 'accept_result', array('message' => 'Terima kasih', 'rating' => 5), $this->reporter_actor());
		$this->assertSame('resolved', $t->status);

		$actions = array_map(function ($h) { return $h->action; }, $this->CI->tickets->history($t->id));
		$this->assertSame(array('submit', 'start_verification', 'assign', 'accept_work', 'follow_up', 'propose_resolution', 'accept_result'), $actions);
		$this->assertNotNull($this->CI->tickets->feedback($t->id, 1));
	}

	public function test_status_cannot_be_skipped(): void
	{
		$r = $this->submit_ticket();
		try
		{
			$this->CI->workflow->perform($r['ticket'], 'propose_resolution', array('message' => 'Langsung selesai tanpa proses.'), $this->staff_actor($this->officer));
			$this->fail('Transisi melompat seharusnya ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertSame(409, $e->http_status);
		}
		// Pelapor tidak dapat menjalankan aksi petugas.
		$this->expectException(AccessDeniedException::class);
		$this->CI->workflow->perform($r['ticket'], 'start_verification', array(), $this->reporter_actor());
	}

	public function test_needs_information_returns_to_original_stage(): void
	{
		$r = $this->submit_ticket();
		$t = $this->CI->workflow->perform($r['ticket'], 'start_verification', array(), $this->staff_actor($this->admin));
		$t = $this->CI->workflow->perform($t, 'assign', array('assignee_id' => $this->officer->id), $this->staff_actor($this->admin));
		$t = $this->CI->workflow->perform($t, 'accept_work', array(), $this->staff_actor($this->officer));
		$t = $this->CI->workflow->perform($t, 'request_information', array('message' => 'Kapan kejadian ini terakhir terlihat?'), $this->staff_actor($this->officer));
		$this->assertSame('needs_information', $t->status);
		$this->assertSame('in_progress', $t->return_status);
		$t = $this->CI->workflow->perform($t, 'provide_information', array('message' => 'Terakhir terlihat kemarin sore.'), $this->reporter_actor());
		$this->assertSame('in_progress', $t->status);
		$this->assertNull($t->return_status);
	}

	public function test_version_conflict_is_detected(): void
	{
		$r = $this->submit_ticket();
		$stale = $r['ticket'];
		$this->CI->workflow->perform($stale, 'start_verification', array(), $this->staff_actor($this->admin));
		$this->expectException(VersionConflictException::class);
		// Petugas kedua masih memegang versi lama.
		$this->CI->workflow->perform($stale, 'start_verification', array('version' => $stale->version), $this->staff_actor($this->admin));
	}

	public function test_reassignment_duplicate_and_referral(): void
	{
		$r1 = $this->submit_ticket(array('title' => 'Laporan utama untuk uji duplikat'));
		$r2 = $this->submit_ticket(array('title' => 'Laporan kedua yang merupakan duplikat'));
		$t = $this->CI->workflow->perform($r1['ticket'], 'start_verification', array(), $this->staff_actor($this->admin));
		$t = $this->CI->workflow->perform($t, 'assign', array('assignee_id' => $this->officer->id), $this->staff_actor($this->admin));
		try
		{
			$this->CI->workflow->perform($t, 'assign', array('assignee_id' => $this->officer2->id), $this->staff_actor($this->admin));
			$this->fail('Reassignment tanpa alasan harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('reason', $e->errors);
		}
		$t = $this->CI->workflow->perform($t, 'assign', array('assignee_id' => $this->officer2->id, 'reason' => 'Beban kerja dipindahkan.'), $this->staff_actor($this->admin));
		$this->assertSame((int) $this->officer2->id, (int) $t->assigned_user_id);
		$this->assertCount(2, $this->CI->tickets->assignments($t->id));

		$d = $this->CI->workflow->perform($r2['ticket'], 'start_verification', array(), $this->staff_actor($this->admin));
		$d = $this->CI->workflow->perform($d, 'reject', array(
			'reason_code' => 'duplicate', 'duplicate_of' => $r1['ticket']->public_code,
			'message' => 'Laporan ini sama dengan laporan yang sedang ditangani.',
		), $this->staff_actor($this->admin));
		$this->assertSame('rejected', $d->status);
		$this->assertSame((int) $r1['ticket']->id, (int) $d->duplicate_of_ticket_id);
		// Pemegang akses tiket duplikat tidak otomatis bisa membuka tiket utama.
		$this->assertNull($this->CI->ticket_access->verify($r1['ticket']->public_code, $r2['access_code']));

		$r3 = $this->submit_ticket(array('title' => 'Gangguan layanan di luar kewenangan'));
		$x = $this->CI->workflow->perform($r3['ticket'], 'start_verification', array(), $this->staff_actor($this->admin));
		try
		{
			$this->CI->workflow->perform($x, 'refer', array('target_name' => 'Instansi X', 'reference_type' => 'actual_forwarding', 'message' => 'Diteruskan ke instansi berwenang.'), $this->staff_actor($this->admin));
			$this->fail('Penerusan nyata tanpa bukti harus ditolak');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('external_reference', $e->errors);
		}
		$x = $this->CI->workflow->perform($x, 'refer', array('target_name' => 'Instansi X', 'reference_type' => 'guidance_only', 'message' => 'Silakan hubungi instansi berwenang melalui kanal resminya.'), $this->staff_actor($this->admin));
		$this->assertSame('referred', $x->status);
		$refs = $this->CI->tickets->references($x->id);
		$this->assertSame('guidance_only', $refs[0]->reference_type);
		$this->assertNull($refs[0]->sent_at);
	}

	public function test_front_desk_records_creator_separately(): void
	{
		$desk = $this->make_user('loket.flow.test', array('front_desk'));
		$resident = $this->make_user('warga.flow.test', array('resident'));
		$anon = $this->submit_ticket(array(), array('intake_channel' => 'front_desk', 'created_by_user_id' => $desk->id, 'actor_type' => 'staff'));
		$this->assertNull($anon['ticket']->reporter_user_id);
		$this->assertSame((int) $desk->id, (int) $anon['ticket']->created_by_user_id);
		$this->assertNotEmpty($anon['access_code']);

		$known = $this->submit_ticket(array(), array(
			'intake_channel' => 'front_desk', 'identity_mode' => 'identified', 'actor_type' => 'staff',
			'created_by_user_id' => $desk->id, 'reporter_user_id' => $resident->id,
		));
		$this->assertSame((int) $resident->id, (int) $known['ticket']->reporter_user_id);
		$this->assertSame((int) $desk->id, (int) $known['ticket']->created_by_user_id);
		$this->assertNull($known['access_code']);

		$contact = $this->submit_ticket(array(), array(
			'intake_channel' => 'front_desk', 'identity_mode' => 'identified', 'actor_type' => 'staff',
			'created_by_user_id' => $desk->id, 'contact' => array('name' => 'Ibu Contoh', 'phone' => '0812', 'email' => ''),
		));
		$row = $this->CI->db->get_where('ticket_private_contacts', array('ticket_id' => $contact['ticket']->id))->row();
		$this->assertStringNotContainsString('Ibu Contoh', $row->name_ciphertext);
		$this->assertSame('Ibu Contoh', $this->CI->crypto->decrypt($row->name_ciphertext));
	}

	public function test_anonymous_submission_stores_no_identity(): void
	{
		$r = $this->submit_ticket();
		$t = $r['ticket'];
		$this->assertNull($t->reporter_user_id);
		$this->assertNull($t->created_by_user_id);
		$this->assertSame('anonymous', $t->identity_mode);
		$this->assertSame(0, $this->CI->db->where('ticket_id', $t->id)->count_all_results('ticket_private_contacts'));
		// Kode akses hanya tersimpan sebagai hash.
		$secret = $this->CI->db->get_where('ticket_access_secrets', array('ticket_id' => $t->id))->row();
		$this->assertNotSame($r['access_code'], $secret->secret_hash);
		$this->assertSame(0, $this->CI->db->like('safe_metadata_json', $r['access_code'])->count_all_results('audit_logs'));
	}

	public function test_sensitive_category_is_restricted_and_withdraw_rules(): void
	{
		$r = $this->submit_ticket(array('category_id' => $this->category_id('PERILAKU_APARAT')));
		$this->assertSame('restricted', $r['ticket']->confidentiality);

		$w = $this->CI->workflow->perform($r['ticket'], 'withdraw', array('message' => 'Sudah selesai secara kekeluargaan.'), $this->reporter_actor());
		$this->assertSame('withdrawn', $w->status);
		$this->assertGreaterThan(0, $this->CI->db->where('ticket_id', $w->id)->count_all_results('ticket_status_history'));

		// Setelah ditangani, penarikan menjadi permintaan (status tidak berubah).
		$r2 = $this->submit_ticket();
		$t = $this->CI->workflow->perform($r2['ticket'], 'start_verification', array(), $this->staff_actor($this->admin));
		$t = $this->CI->workflow->perform($t, 'assign', array('assignee_id' => $this->officer->id), $this->staff_actor($this->admin));
		$t = $this->CI->workflow->perform($t, 'request_withdrawal', array('message' => 'Masalah sudah tidak terjadi lagi.'), $this->reporter_actor());
		$this->assertSame('assigned', $t->status);
		$this->assertNotNull($t->withdrawal_requested_at);
	}

	public function test_validation_rules(): void
	{
		try
		{
			$this->submit_ticket(array('title' => 'pendek', 'description' => 'kurang', 'statement' => FALSE));
			$this->fail('Validasi harus gagal');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('title', $e->errors);
			$this->assertArrayHasKey('description', $e->errors);
			$this->assertArrayHasKey('statement', $e->errors);
		}
		try
		{
			$this->submit_ticket(array('incident_date' => '2999-01-01'));
			$this->fail('Tanggal masa depan harus ditolak untuk pengaduan');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('incident_date', $e->errors);
		}
		try
		{
			$this->submit_ticket(array('category_id' => $this->category_id('INFRA')));
			$this->fail('Kategori dengan lokasi wajib harus meminta lokasi');
		}
		catch (DomainRuleException $e)
		{
			$this->assertArrayHasKey('location_text', $e->errors);
		}
	}
}
