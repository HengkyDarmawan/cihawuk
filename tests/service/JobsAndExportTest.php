<?php

/** JOB-01, EXPORT-01, CMS-01 (service). */
class JobsAndExportTest extends CiTestCase {

	protected function setUp(): void
	{
		parent::setUp();
		$this->truncate_service_data();
		$this->CI->db->query('DELETE FROM job_locks');
		$this->CI->mailer->capture = FALSE;
		$this->CI->mailer->captured = array();
		$this->CI->db->query('DELETE FROM export_jobs');
	}

	public function test_outbox_not_configured_and_dedupe(): void
	{
		$user = $this->make_user('notif.job.test', array('resident'));
		$this->CI->notifications->notify($user->id, 'test', 'Ringkasan uji', 'ticket', 'X', '/warga');
		// Dedupe key sama tidak menggandakan email.
		$this->CI->notifications->queue('email', $user->id, 'notification', 'ticket', 'X', array('summary' => 'dup'), 'dedupe-uji');
		$this->CI->notifications->queue('email', $user->id, 'notification', 'ticket', 'X', array('summary' => 'dup'), 'dedupe-uji');
		$this->assertSame(2, $this->CI->db->count_all('notification_outbox'));

		$this->CI->mailer->capture = FALSE;
		$summary = $this->CI->notifications->process_outbox(10);
		$this->assertSame(2, $summary['not_configured']);
		$this->assertSame(0, $this->CI->db->where('status', 'sent')->count_all_results('notification_outbox'), 'Tanpa SMTP tidak ada yang dianggap terkirim');

		// Dengan SMTP (capture), pesan pending terkirim sekali saja walau diproses dua kali.
		$this->CI->notifications->queue('email', $user->id, 'notification', 'ticket', 'Y', array('summary' => 'kirim'), 'dedupe-kirim');
		$this->CI->mailer->capture = TRUE;
		$this->CI->notifications->process_outbox(10);
		$this->CI->notifications->process_outbox(10);
		$this->CI->mailer->capture = FALSE;
		$this->assertCount(1, $this->CI->mailer->captured);
		$this->CI->mailer->captured = array();
	}

	public function test_smtp_failure_retries_with_backoff_then_fails(): void
	{
		$user = $this->make_user('smtp.job.test', array('resident'));
		$this->CI->clock->set('2026-09-16 01:00:00');
		$this->CI->notifications->queue('email', $user->id, 'notification', 'ticket', 'Z', array('summary' => 'gagal'), 'dedupe-smtp-mati');

		// SMTP dikonfigurasi tetapi port tertutup: koneksi ditolak.
		$env = array('MAIL_ENABLED' => 'true', 'MAIL_HOST' => '127.0.0.1', 'MAIL_PORT' => '1', 'MAIL_ENCRYPTION' => 'none', 'MAIL_FROM' => 'desa@contoh.test');
		foreach ($env as $key => $value)
		{
			putenv($key.'='.$value);
		}
		try
		{
			$first = $this->CI->notifications->process_outbox(10);
			$this->assertSame(1, $first['retry']);
			$row = $this->CI->db->get_where('notification_outbox', array('dedupe_key' => 'dedupe-smtp-mati'))->row();
			$this->assertSame('pending', $row->status);
			$this->assertSame(1, (int) $row->attempts);
			$this->assertSame('2026-09-16 01:01:00', $row->next_attempt_at, 'Percobaan berikutnya ditunda (backoff)');
			$this->assertStringNotContainsString('desa@contoh.test', (string) $row->last_error);

			// Belum waktunya: tidak diproses ulang.
			$this->assertSame(0, array_sum($this->CI->notifications->process_outbox(10)));

			for ($i = 0; $i < 4; $i++)
			{
				$this->CI->clock->advance('PT2H');
				$this->CI->notifications->process_outbox(10);
			}
			$row = $this->CI->db->get_where('notification_outbox', array('dedupe_key' => 'dedupe-smtp-mati'))->row();
			$this->assertSame('failed', $row->status);
			$this->assertSame(5, (int) $row->attempts);
		}
		finally
		{
			foreach (array_keys($env) as $key)
			{
				putenv($key);
			}
		}
	}

	public function test_ticket_survives_mail_failure_and_job_lock(): void
	{
		$admin = $this->make_user('admin.job.test', array('service_admin'));
		$r = $this->submit_ticket();
		$this->assertSame('submitted', $this->CI->tickets->find($r['ticket']->id)->status);
		$this->assertGreaterThan(0, $this->CI->db->where('recipient_user_id', $admin->id)->count_all_results('notifications'));

		require_once APPPATH.'libraries/JobRunner.php';
		$runner1 = new JobRunner();
		$runner2 = new JobRunner();
		$lock = new ReflectionMethod('JobRunner', 'acquire');
		$this->assertTrue($lock->invoke($runner1, 'uji', 60));
		$this->assertFalse($lock->invoke($runner2, 'uji', 60), 'Scheduler kedua tidak boleh mengambil lock yang sama');
	}

	public function test_sla_escalation_is_deduplicated(): void
	{
		$coordinator = $this->make_user('koor.job.test', array('service_coordinator'));
		$this->add_unit_scope($coordinator->id, 'PELAYANAN', 'all');
		$this->CI->clock->set('2026-09-01 01:00:00');
		$this->submit_ticket();
		$this->CI->clock->set('2026-09-15 01:00:00');
		require_once APPPATH.'libraries/JobRunner.php';
		$runner = new JobRunner();
		$first = $runner->run('sla_escalation');
		$second = $runner->run('sla_escalation');
		$this->assertGreaterThan(0, $first['sla_escalation']['eskalasi_baru']);
		$this->assertSame(0, $second['sla_escalation']['eskalasi_baru']);
	}

	public function test_export_denied_when_permission_revoked_before_run(): void
	{
		$staff = $this->make_user('ekspor.job.test', array('service_admin'));
		$this->submit_ticket(array('title' => '=HYPERLINK("http://evil.test")  judul'));
		$id_ok = $this->CI->exports->request_export($staff, 'tickets', array(), 'csv');
		$result = $this->CI->exports->process_pending(5);
		$this->assertSame(1, $result['selesai']);
		$job = $this->CI->exports->find($id_ok);
		$this->assertSame('ready', $job->status);

		$file = $this->CI->db->get_where('private_files', array('id' => $job->private_file_id))->row();
		$this->assertStringStartsWith('export/', $file->storage_key);
		$content = file_get_contents($this->CI->uploads->absolute_path($file));
		$this->assertStringContainsString('Tanpa identitas pelapor', $content);
		$this->assertStringNotContainsString('Nama pelapor', $content, 'Kolom identitas tidak disertakan bawaan');
		$this->assertSame(1, substr_count($content, 'CHW-'), 'Hanya tiket dalam lingkup pemohon');

		$id_denied = $this->CI->exports->request_export($staff, 'tickets', array(), 'csv');
		$this->CI->user_model->revoke_role($staff->id, 'service_admin');
		$this->CI->authz->flush($staff->id);
		$this->CI->exports->process_pending(5);
		$this->assertSame('denied', $this->CI->exports->find($id_denied)->status);

		$this->expectException(AccessDeniedException::class);
		$this->CI->exports->download($id_ok, $staff);
	}

	public function test_content_publication_workflow(): void
	{
		$editor = $this->make_user('editor.job.test', array('content_editor'));
		$id = $this->CI->content_service->save('berita', NULL, array(
			'type' => 'news', 'title' => 'Berita uji publikasi', 'body_html' => '<p>Isi berita</p>',
			'excerpt' => 'Ringkas', 'category_id' => '', 'slug' => '', 'is_featured' => FALSE,
		), $editor->id);
		$this->CI->load->model('Content_model', 'content');
		$this->CI->content->preview = FALSE;
		$this->assertNull($this->CI->content->post_by_slug('berita-uji-publikasi'), 'Draft tidak tampil publik');

		$this->CI->content_service->set_status('berita', $id, 'published', $editor->id);
		$this->assertNotNull($this->CI->content->post_by_slug('berita-uji-publikasi'));

		// Ubah slug → pengalihan 301 dari slug lama tersimpan.
		$this->CI->content_service->save('berita', $id, array(
			'type' => 'news', 'title' => 'Berita uji publikasi', 'body_html' => '<p>Isi berita</p>',
			'excerpt' => 'Ringkas', 'category_id' => '', 'slug' => 'slug-baru-uji', 'is_featured' => FALSE,
		), $editor->id);
		$this->assertSame('slug-baru-uji', $this->CI->content->redirect_for('post', 'berita-uji-publikasi')->new_slug);
		$this->assertGreaterThanOrEqual(2, $this->CI->db->where('post_id', $id)->count_all_results('post_revisions'));

		$this->CI->content_service->set_status('berita', $id, 'archived', $editor->id);
		$this->assertNull($this->CI->content->post_by_slug('slug-baru-uji'), 'Arsip hilang dari publik');
		$this->CI->db->where('id', $id)->delete('post_revisions');
		$this->CI->db->where('post_id', $id)->delete('post_revisions');
		$this->CI->db->delete('slug_redirects', array('old_slug' => 'berita-uji-publikasi'));
		$this->CI->db->where('id', $id)->delete('posts');
	}
}
