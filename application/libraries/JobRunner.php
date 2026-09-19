<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Job berkala (dipanggil CLI: tools run_jobs).
 *
 * - Setiap job memakai job_locks agar dua scheduler tidak berjalan ganda.
 * - Kegagalan email tidak pernah membatalkan perubahan status tiket.
 * - Tidak ada penutupan otomatis: tenggat tanggapan hanya menghasilkan
 *   pengingat dan daftar review.
 */
class JobRunner {

	/** @var CI_Controller */
	protected $CI;

	protected $owner_token;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->library('NotificationService', NULL, 'notifications');
		$this->CI->load->library('SlaService', NULL, 'sla');
		$this->CI->load->library('TicketAccessService', NULL, 'ticket_access');
		$this->CI->load->library('Idempotency', NULL, 'idempotency');
		$this->CI->load->library('AuthorizationService', NULL, 'authz');
		$this->CI->load->model(array('Ticket_model' => 'tickets', 'User_model' => 'user_model'));
		$this->owner_token = bin2hex(random_bytes(16));
	}

	public function jobs()
	{
		return array('outbox', 'sla_escalation', 'confirmation_reminder', 'scheduled_publications',
			'exports', 'link_check', 'cleanup');
	}

	public function run($only = NULL)
	{
		$summary = array();
		foreach ($this->jobs() as $job)
		{
			if ($only !== NULL && $only !== $job)
			{
				continue;
			}
			if ( ! $this->acquire($job, 900))
			{
				$summary[$job] = 'dilewati (job lain sedang berjalan)';
				continue;
			}
			try
			{
				$summary[$job] = $this->{'job_'.$job}();
			}
			catch (Throwable $e)
			{
				log_message('error', 'Job '.$job.' failed: '.$e->getMessage());
				$summary[$job] = 'gagal: '.substr($e->getMessage(), 0, 120);
			}
			finally
			{
				$this->release($job);
			}
		}
		return $summary;
	}

	/** Klaim lock secara atomik (INSERT ... ON DUPLICATE KEY dengan syarat kedaluwarsa). */
	protected function acquire($job_key, $ttl_seconds)
	{
		$now = utc_now();
		$until = $this->CI->clock->plus_seconds($ttl_seconds);
		$this->CI->db->query(
			'INSERT INTO job_locks (job_key, locked_until, owner_token) VALUES (?, ?, ?)
			 ON DUPLICATE KEY UPDATE
				owner_token = IF(locked_until < ?, VALUES(owner_token), owner_token),
				locked_until = IF(locked_until < ?, VALUES(locked_until), locked_until)',
			array($job_key, $until, $this->owner_token, $now, $now)
		);
		$row = $this->CI->db->get_where('job_locks', array('job_key' => $job_key))->row();
		return $row && hash_equals($row->owner_token, $this->owner_token);
	}

	protected function release($job_key)
	{
		$this->CI->db->where('job_key', $job_key)->where('owner_token', $this->owner_token)
			->update('job_locks', array('locked_until' => $this->CI->clock->plus_seconds(-1)));
	}

	// ------------------------------------------------------------ Jobs

	protected function job_outbox()
	{
		$result = $this->CI->notifications->process_outbox(100);
		return $result;
	}

	/**
	 * Eskalasi milestone yang terlambat kepada pemegang tickets.monitor_scope.
	 * Dedupe per tiket/episode/milestone/level agar tidak mengirim berulang.
	 */
	protected function job_sla_escalation()
	{
		$now = utc_now();
		$rows = $this->CI->db->select('t.id, t.public_code, t.status, t.current_episode, t.assigned_user_id, t.assigned_unit_id,
				s.verification_due_at, s.verification_met_at, s.first_response_due_at, s.first_response_met_at, s.resolution_due_at, s.resolution_met_at')
			->from('tickets t')
			->join('ticket_sla_instances s', 's.ticket_id = t.id AND s.episode_no = t.current_episode')
			->where_not_in('t.status', app_config('ticket_terminal_statuses', array()))
			->group_start()
				->group_start()->where('s.verification_met_at IS NULL', NULL, FALSE)->where('s.verification_due_at <', $now)->group_end()
				->or_group_start()->where('s.first_response_met_at IS NULL', NULL, FALSE)->where('s.first_response_due_at <', $now)->group_end()
				->or_group_start()->where('s.resolution_met_at IS NULL', NULL, FALSE)->where('s.resolution_due_at <', $now)->group_end()
			->group_end()
			->limit(200)->get()->result();

		$created = 0;
		foreach ($rows as $row)
		{
			$milestones = array();
			if ($row->verification_met_at === NULL && $row->verification_due_at !== NULL && $row->verification_due_at < $now)
			{
				$milestones[] = 'verification';
			}
			if ($row->first_response_met_at === NULL && $row->first_response_due_at !== NULL && $row->first_response_due_at < $now)
			{
				$milestones[] = 'first_response';
			}
			if ($row->resolution_met_at === NULL && $row->resolution_due_at !== NULL && $row->resolution_due_at < $now)
			{
				$milestones[] = 'resolution';
			}
			foreach ($milestones as $milestone)
			{
				$recipients = $this->escalation_recipients($row, $milestone);
				foreach ($recipients as $recipient_id)
				{
					$dedupe = 'esc:'.$row->id.':'.$row->current_episode.':'.$milestone.':1:'.$recipient_id;
					$inserted = $this->CI->db->query(
						'INSERT IGNORE INTO ticket_escalations (ticket_id, episode_no, milestone, level, recipient_user_id, dedupe_key, triggered_at)
						 VALUES (?, ?, ?, 1, ?, ?, ?)',
						array((int) $row->id, (int) $row->current_episode, $milestone, (int) $recipient_id, $dedupe, $now)
					);
					if ($inserted && $this->CI->db->affected_rows() === 1)
					{
						$labels = array('verification' => 'verifikasi', 'first_response' => 'respons awal', 'resolution' => 'penyelesaian');
						$this->CI->notifications->notify($recipient_id, 'sla.escalation',
							'Tenggat '.$labels[$milestone].' laporan '.$row->public_code.' terlewat dan perlu ditinjau.',
							'ticket', $row->public_code, '/admin/laporan/'.$row->public_code, $milestone);
						$created++;
					}
				}
			}
		}
		return array('tiket_terlambat' => count($rows), 'eskalasi_baru' => $created);
	}

	protected function escalation_recipients($ticket_row, $milestone)
	{
		$ids = array();
		// Petugas yang ditugaskan diberi tahu untuk milestone respons/penyelesaian.
		if ($ticket_row->assigned_user_id !== NULL && $milestone !== 'verification')
		{
			$ids[] = (int) $ticket_row->assigned_user_id;
		}
		foreach ($this->CI->user_model->active_users_with_permission('tickets.monitor_scope') as $user)
		{
			$scope = $this->CI->authz->monitor_scope($user->id);
			if ($scope['all'] OR ($ticket_row->assigned_unit_id !== NULL && in_array((int) $ticket_row->assigned_unit_id, $scope['units'], TRUE)))
			{
				$ids[] = (int) $user->id;
			}
		}
		if (empty($ids) && $milestone === 'verification')
		{
			foreach ($this->CI->user_model->active_users_with_permission('tickets.verify') as $user)
			{
				$ids[] = (int) $user->id;
			}
		}
		return array_values(array_unique($ids));
	}

	/**
	 * Pengingat tanggapan pelapor. Tidak menutup laporan secara otomatis.
	 */
	protected function job_confirmation_reminder()
	{
		$now = utc_now();
		$rows = $this->CI->db->select('t.id, t.public_code, t.reporter_user_id, t.current_episode, t.assigned_user_id, s.confirmation_due_at')
			->from('tickets t')
			->join('ticket_sla_instances s', 's.ticket_id = t.id AND s.episode_no = t.current_episode')
			->where('t.status', 'awaiting_confirmation')
			->where('s.confirmation_due_at IS NOT NULL', NULL, FALSE)
			->where('s.confirmation_due_at <', $now)
			->limit(200)->get()->result();

		$reminded = 0;
		foreach ($rows as $row)
		{
			$dedupe = 'conf:'.$row->id.':'.$row->current_episode;
			$inserted = $this->CI->db->query(
				'INSERT IGNORE INTO ticket_escalations (ticket_id, episode_no, milestone, level, recipient_user_id, dedupe_key, triggered_at)
				 VALUES (?, ?, ?, 1, ?, ?, ?)',
				array((int) $row->id, (int) $row->current_episode, 'confirmation', (int) ($row->assigned_user_id ?: $this->first_verifier()), $dedupe, $now)
			);
			if ($inserted && $this->CI->db->affected_rows() === 1)
			{
				if ($row->reporter_user_id !== NULL)
				{
					$this->CI->notifications->notify($row->reporter_user_id, 'ticket.confirmation_reminder',
						'Laporan '.$row->public_code.' menunggu tanggapan Anda atas hasil penanganan.', 'ticket', $row->public_code, '/warga/laporan/'.$row->public_code);
				}
				if ($row->assigned_user_id !== NULL)
				{
					$this->CI->notifications->notify($row->assigned_user_id, 'ticket.confirmation_overdue',
						'Laporan '.$row->public_code.' belum ditanggapi pelapor sampai batas waktu. Masukkan ke daftar review; tidak ada penutupan otomatis.',
						'ticket', $row->public_code, '/admin/laporan/'.$row->public_code);
				}
				$reminded++;
			}
		}
		return array('menunggu_tanggapan' => count($rows), 'pengingat_baru' => $reminded);
	}

	protected function first_verifier()
	{
		$users = $this->CI->user_model->active_users_with_permission('tickets.verify');
		return empty($users) ? NULL : (int) $users[0]->id;
	}

	/** Publikasi halaman CMS yang dijadwalkan; klaim baris atomik ada di service. */
	protected function job_scheduled_publications()
	{
		$this->CI->load->library('CmsPublicationService', NULL, 'publications');
		return $this->CI->publications->run_due_schedules(20);
	}

	/** Pemeriksa tautan internal rusak (modul-backend 25). */
	protected function job_link_check()
	{
		$this->CI->load->library('LinkCheckService', NULL, 'link_check');
		return $this->CI->link_check->run();
	}

	protected function job_exports()
	{
		if ( ! file_exists(APPPATH.'libraries/ExportService.php'))
		{
			return 'tidak tersedia';
		}
		$this->CI->load->library('ExportService', NULL, 'exports');
		return $this->CI->exports->process_pending(5);
	}

	protected function job_cleanup()
	{
		$result = array();
		$result['rate_limits'] = $this->CI->rate_limiter->purge_expired();
		$result['idempotency'] = $this->CI->idempotency->purge_expired();
		$result['anon_grants'] = $this->CI->ticket_access->purge_expired_grants();

		$this->CI->db->where('expires_at <', $this->CI->clock->plus_seconds(-7 * 86400))
			->where('used_at IS NULL', NULL, FALSE)->delete('account_tokens');
		$result['token_kedaluwarsa'] = $this->CI->db->affected_rows();

		$this->CI->db->where('expires_at <', $this->CI->clock->plus_seconds(-30 * 86400))->delete('user_sessions');
		$result['sesi_lama'] = $this->CI->db->affected_rows();

		// Berkas ekspor kedaluwarsa dihapus beserta barisnya.
		$this->CI->load->library('UploadService', NULL, 'uploads');
		$expired = $this->CI->db->select('f.id, f.storage_key')->from('private_files f')
			->where('f.purpose', 'export')->where('f.expires_at IS NOT NULL', NULL, FALSE)
			->where('f.expires_at <', utc_now())->limit(100)->get()->result();
		$removed = 0;
		foreach ($expired as $file)
		{
			$path = $this->CI->uploads->absolute_path($file);
			if ($path !== NULL && is_file($path))
			{
				@unlink($path);
			}
			$this->CI->db->where('private_file_id', (int) $file->id)->update('export_jobs', array('private_file_id' => NULL, 'status' => 'expired'));
			$this->CI->db->where('id', (int) $file->id)->delete('private_files');
			$removed++;
		}
		$result['berkas_ekspor'] = $removed;
		return $result;
	}
}
