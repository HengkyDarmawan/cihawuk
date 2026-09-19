<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Satu-satunya jalur perubahan status tiket (§10 spesifikasi).
 *
 * - Transisi hanya melalui aksi yang terdaftar pada ALLOWED (deny-by-default).
 * - Setiap perubahan memakai transaction + optimistic version check (409 bila bentrok),
 *   menulis riwayat append-only, memperbarui SLA, lalu meng-enqueue notifikasi.
 * - Controller tidak pernah mengubah kolom status secara langsung.
 */
class TicketWorkflowService {

	/** aksi => [from[], to|NULL (NULL = status tidak berubah), aktor] */
	const ALLOWED = array(
		'start_verification'  => array('from' => array('submitted'), 'to' => 'verifying', 'actor' => 'staff'),
		'request_information' => array('from' => array('verifying', 'assigned', 'in_progress'), 'to' => 'needs_information', 'actor' => 'staff'),
		'assign'              => array('from' => array('verifying', 'assigned', 'in_progress', 'needs_information'), 'to' => 'assigned', 'actor' => 'staff'),
		'reject'              => array('from' => array('verifying'), 'to' => 'rejected', 'actor' => 'staff'),
		'refer'               => array('from' => array('verifying'), 'to' => 'referred', 'actor' => 'staff'),
		'accept_work'         => array('from' => array('assigned'), 'to' => 'in_progress', 'actor' => 'staff'),
		'propose_resolution'  => array('from' => array('in_progress'), 'to' => 'awaiting_confirmation', 'actor' => 'staff'),
		'close_by_policy'     => array('from' => array('awaiting_confirmation'), 'to' => 'resolved', 'actor' => 'staff'),
		'resume_work'         => array('from' => array('awaiting_confirmation'), 'to' => 'in_progress', 'actor' => 'staff'),
		'reopen'              => array('from' => array('resolved'), 'to' => 'in_progress', 'actor' => 'staff'),
		'follow_up'           => array('from' => array('verifying', 'assigned', 'in_progress', 'needs_information', 'awaiting_confirmation'), 'to' => NULL, 'actor' => 'staff'),
		'accept_result'       => array('from' => array('awaiting_confirmation'), 'to' => 'resolved', 'actor' => 'reporter'),
		'request_followup'    => array('from' => array('awaiting_confirmation'), 'to' => 'in_progress', 'actor' => 'reporter'),
		'provide_information' => array('from' => array('needs_information'), 'to' => 'return', 'actor' => 'reporter'),
		'reply'               => array('from' => array('submitted', 'verifying', 'assigned', 'in_progress', 'awaiting_confirmation'), 'to' => NULL, 'actor' => 'reporter'),
		'withdraw'            => array('from' => array('submitted', 'verifying'), 'to' => 'withdrawn', 'actor' => 'reporter'),
		'request_withdrawal'  => array('from' => array('assigned', 'in_progress', 'needs_information', 'awaiting_confirmation'), 'to' => NULL, 'actor' => 'reporter'),
	);

	/** @var CI_Controller */
	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->model(array('Ticket_model' => 'tickets', 'User_model' => 'user_model'));
		$this->CI->load->library('TicketAccessService', NULL, 'ticket_access');
		$this->CI->load->library('SlaService', NULL, 'sla');
		$this->CI->load->library('UploadService', NULL, 'uploads');
		$this->CI->load->library('Idempotency', NULL, 'idempotency');
		$this->CI->load->library('AuthorizationService', NULL, 'authz');
		$this->CI->config->load('app', TRUE);
	}

	// =================================================================
	// Pembuatan tiket
	// =================================================================

	/**
	 * Validasi input formulir laporan (dipakai kanal anonim, warga, dan loket).
	 * @return array data bersih
	 * @throws DomainRuleException
	 */
	public function validate_submission(array $input, array $context = array())
	{
		$errors = array();
		$types = $this->CI->config->item('report_types', 'app');
		$report_type = (string) ($input['report_type'] ?? '');
		if ( ! isset($types[$report_type]))
		{
			$errors['report_type'] = 'Pilih jenis laporan.';
		}

		$category = $this->CI->tickets->category((int) ($input['category_id'] ?? 0));
		if ( ! $category OR ! $category->active)
		{
			$errors['category_id'] = 'Pilih kategori yang tersedia.';
		}
		elseif ($category->report_type !== NULL && $category->report_type !== $report_type)
		{
			$errors['category_id'] = 'Kategori tidak sesuai dengan jenis laporan.';
		}

		$title = trim(preg_replace('/\s+/u', ' ', (string) ($input['title'] ?? '')));
		if (mb_strlen($title) < 10 OR mb_strlen($title) > 180)
		{
			$errors['title'] = 'Judul wajib diisi, 10–180 karakter.';
		}

		$description = trim((string) ($input['description'] ?? ''));
		if (mb_strlen($description) < 30 OR mb_strlen($description) > 10000)
		{
			$errors['description'] = 'Uraian wajib diisi, 30–10.000 karakter.';
		}

		$rules = (array) $this->CI->settings->get('tickets.field_rules', array());
		$type_rules = $rules[$report_type] ?? array();

		$incident_date = trim((string) ($input['incident_date'] ?? ''));
		if ($incident_date !== '' && ($type_rules['incident_date'] ?? 'optional') !== 'hidden')
		{
			$dt = DateTimeImmutable::createFromFormat('!Y-m-d', $incident_date, local_tz());
			if ($dt === FALSE)
			{
				$errors['incident_date'] = 'Format tanggal tidak valid.';
			}
			elseif ($report_type === 'complaint' && $dt->format('Y-m-d') > $this->CI->clock->now()->setTimezone(local_tz())->format('Y-m-d'))
			{
				$errors['incident_date'] = 'Tanggal kejadian tidak boleh di masa depan.';
			}
			elseif ($dt->format('Y') < '1990')
			{
				$errors['incident_date'] = 'Tanggal kejadian tidak wajar.';
			}
		}
		else
		{
			$incident_date = '';
		}

		$location = trim((string) ($input['location_text'] ?? ''));
		$location_rule = $type_rules['location'] ?? 'optional';
		if ($location_rule === 'hidden')
		{
			$location = '';
		}
		elseif ($category && $location_rule === 'category' && (int) $category->location_required === 1 && mb_strlen($location) < 5)
		{
			$errors['location_text'] = 'Lokasi wajib diisi untuk kategori ini (sebutkan dusun/RT/RW atau patokan).';
		}
		if (mb_strlen($location) > 255)
		{
			$errors['location_text'] = 'Lokasi maksimal 255 karakter.';
		}

		$latitude = $longitude = NULL;
		if (($type_rules['map_point'] ?? 'optional') !== 'hidden')
		{
			$lat_raw = trim((string) ($input['latitude'] ?? ''));
			$lng_raw = trim((string) ($input['longitude'] ?? ''));
			if ($lat_raw !== '' OR $lng_raw !== '')
			{
				if ( ! is_numeric($lat_raw) OR ! is_numeric($lng_raw) OR abs((float) $lat_raw) > 90 OR abs((float) $lng_raw) > 180)
				{
					$errors['latitude'] = 'Titik lokasi tidak valid.';
				}
				else
				{
					$latitude = round((float) $lat_raw, 7);
					$longitude = round((float) $lng_raw, 7);
				}
			}
		}

		if (empty($input['statement']))
		{
			$errors['statement'] = 'Centang pernyataan sebelum mengirim laporan.';
		}

		if ( ! empty($errors))
		{
			throw new DomainRuleException('Periksa kembali isian laporan Anda.', 422, $errors);
		}

		// Kategori sensitif otomatis dibatasi; pengguna juga dapat meminta kerahasiaan.
		$confidentiality = ((int) $category->is_sensitive === 1 OR ! empty($input['confidential'])) ? 'restricted' : 'private';

		return array(
			'report_type' => $report_type,
			'category_id' => (int) $category->id,
			'category' => $category,
			'title' => $title,
			'description' => $description,
			'incident_date' => ($incident_date === '') ? NULL : $incident_date,
			'location_text' => ($location === '') ? NULL : $location,
			'latitude' => $latitude,
			'longitude' => $longitude,
			'confidentiality' => $confidentiality,
		);
	}

	/**
	 * Simpan laporan baru beserta akses anonim, riwayat, SLA dan notifikasi
	 * dalam satu transaction.
	 *
	 * $context: intake_channel, identity_mode, reporter_user_id, created_by_user_id,
	 *           actor_type, attachments_field, idempotency (key, scope), contact
	 *
	 * @return array{ticket:object, access_code:?string, replay:bool}
	 */
	public function submit(array $input, array $context)
	{
		$clean = $this->validate_submission($input, $context);
		$channel = $context['intake_channel'];
		$identity_mode = $context['identity_mode'];
		$reporter_user_id = $context['reporter_user_id'] ?? NULL;
		$actor_type = $context['actor_type'] ?? 'anonymous';
		$needs_code = ($identity_mode === 'anonymous');

		$idem = $context['idempotency'] ?? NULL;
		$request_hash = $this->CI->idempotency->request_hash(array(
			'report_type' => $clean['report_type'], 'category_id' => $clean['category_id'],
			'title' => $clean['title'], 'description' => $clean['description'],
			'reporter' => (int) $reporter_user_id, 'channel' => $channel,
		));

		$result = NULL;
		try
		{
			$result = db_transaction(function () use ($clean, $context, $channel, $identity_mode, $reporter_user_id, $actor_type, $needs_code, $idem, $request_hash) {
				if ($idem)
				{
					$state = $this->CI->idempotency->begin('ticket_submit', $idem['scope'], $idem['key'], $request_hash);
					if ($state['state'] === Idempotency::CONFLICT)
					{
						throw new DomainRuleException('Permintaan dengan kunci yang sama sudah dipakai untuk data berbeda. Muat ulang formulir.', 409);
					}
					if ($state['state'] === Idempotency::IN_PROGRESS)
					{
						throw new DomainRuleException('Laporan yang sama sedang diproses. Tunggu beberapa saat lalu periksa kembali.', 409);
					}
					if ($state['state'] === Idempotency::REPLAY)
					{
						return array('ticket' => $this->CI->tickets->find($state['result_id']), 'access_code' => NULL, 'replay' => TRUE);
					}
				}

				$now = utc_now();
				$ticket_id = $this->CI->tickets->insert(array(
					'public_code' => $this->CI->ticket_access->generate_public_code(),
					'report_type' => $clean['report_type'],
					'category_id' => $clean['category_id'],
					'title' => $clean['title'],
					'description' => $clean['description'],
					'incident_date' => $clean['incident_date'],
					'location_text' => $clean['location_text'],
					'latitude' => $clean['latitude'],
					'longitude' => $clean['longitude'],
					'reporter_user_id' => $reporter_user_id,
					'created_by_user_id' => $context['created_by_user_id'] ?? NULL,
					'intake_channel' => $channel,
					'identity_mode' => $identity_mode,
					'confidentiality' => $clean['confidentiality'],
					'status' => 'submitted',
					'priority' => 'normal',
					'current_episode' => 1,
					'version' => 1,
					'submitted_at' => $now,
				));
				$ticket = $this->CI->tickets->find($ticket_id);

				$access_code = NULL;
				if ($needs_code)
				{
					$access_code = $this->CI->ticket_access->issue_access_code($ticket_id);
				}

				// Kontak privat opsional (loket, pelapor tanpa akun yang bersedia).
				if ( ! empty($context['contact']) && array_filter($context['contact']))
				{
					$c = $context['contact'];
					db_must($this->CI->db->insert('ticket_private_contacts', array(
						'ticket_id' => $ticket_id,
						'name_ciphertext' => empty($c['name']) ? NULL : $this->CI->crypto->encrypt($c['name']),
						'email_ciphertext' => empty($c['email']) ? NULL : $this->CI->crypto->encrypt($c['email']),
						'phone_ciphertext' => empty($c['phone']) ? NULL : $this->CI->crypto->encrypt($c['phone']),
						'key_version' => $this->CI->crypto->key_version(),
						'recorded_by' => $context['created_by_user_id'] ?? NULL,
						'purpose' => 'Kontak pelapor untuk tindak lanjut laporan (diberikan saat penerimaan).',
						'created_at' => $now,
					)), 'ticket_private_contacts.insert');
				}

				db_must($this->CI->db->insert('ticket_resolution_episodes', array(
					'ticket_id' => $ticket_id, 'episode_no' => 1, 'opened_at' => $now,
				)), 'ticket_resolution_episodes.insert');

				$this->CI->sla->start_instance($ticket, 1, $this->CI->sla->policy_for_category($clean['category']), $now);

				$this->CI->tickets->add_history($ticket_id, array(
					'from_status' => NULL, 'to_status' => 'submitted', 'action' => 'submit',
					'actor_user_id' => $context['created_by_user_id'] ?? $reporter_user_id,
					'actor_type' => $actor_type, 'reason' => NULL, 'ticket_version' => 1, 'created_at' => $now,
				));

				if ( ! empty($context['attachments_field']))
				{
					$file_ids = $this->CI->uploads->store_ticket_attachments(
						$context['attachments_field'],
						$context['created_by_user_id'] ?? $reporter_user_id,
						$actor_type
					);
					foreach ($file_ids as $file_id)
					{
						db_must($this->CI->db->insert('ticket_attachments', array(
							'ticket_id' => $ticket_id, 'message_id' => NULL, 'private_file_id' => $file_id,
							'visibility' => 'reporter', 'uploaded_by_user_id' => $context['created_by_user_id'] ?? $reporter_user_id,
							'uploader_type' => $actor_type, 'created_at' => $now,
						)), 'ticket_attachments.insert');
					}
				}

				$this->notify_new_ticket($ticket);

				if ($idem)
				{
					$this->CI->idempotency->complete($state['id'], 'ticket', $ticket_id);
				}
				$this->CI->uploads->commit_staged();
				return array('ticket' => $ticket, 'access_code' => $access_code, 'replay' => FALSE);
			});
		}
		catch (Throwable $e)
		{
			$this->CI->uploads->discard_staged();
			throw $e;
		}

		if ( ! $result['replay'])
		{
			$this->CI->audit->log('ticket.submitted', 'ticket', $result['ticket']->public_code, array(
				'channel' => $channel, 'identity_mode' => $identity_mode, 'report_type' => $clean['report_type'],
				'confidentiality' => $clean['confidentiality'],
			));
		}
		return $result;
	}

	protected function notify_new_ticket($ticket)
	{
		$summary = 'Laporan baru '.$ticket->public_code.' menunggu verifikasi.';
		$this->CI->notifications->notify_permission_holders('tickets.verify', 'ticket.new', $summary, 'ticket', $ticket->public_code, '/admin/laporan/'.$ticket->public_code);
	}

	// =================================================================
	// Transisi
	// =================================================================

	protected function guard($action, $ticket, $actor_kind)
	{
		if ( ! isset(self::ALLOWED[$action]))
		{
			throw new DomainRuleException('Tindakan tidak dikenal.', 400);
		}
		$rule = self::ALLOWED[$action];
		if ($rule['actor'] !== $actor_kind)
		{
			throw new AccessDeniedException('Actor kind mismatch for '.$action);
		}
		if ( ! in_array($ticket->status, $rule['from'], TRUE))
		{
			throw new DomainRuleException('Tindakan ini tidak tersedia pada status laporan saat ini ('.config_label('ticket_statuses', $ticket->status).').', 409);
		}
		return $rule;
	}

	/**
	 * Jalankan aksi. Semua perubahan dalam satu transaction dengan row lock +
	 * pemeriksaan versi.
	 *
	 * @param array $data data spesifik aksi
	 * @param array $actor ['kind' => staff|reporter, 'user_id' => ?int, 'type' => staff|resident|anonymous|system]
	 * @return object tiket terbaru
	 */
	public function perform($ticket, $action, array $data, array $actor)
	{
		$rule = $this->guard($action, $ticket, $actor['kind']);
		$expected_version = (int) ($data['version'] ?? $ticket->version);

		return db_transaction(function () use ($ticket, $action, $data, $actor, $rule, $expected_version) {
			$locked = $this->CI->tickets->lock($ticket->id);
			if ( ! $locked)
			{
				throw new DomainRuleException('Laporan tidak ditemukan.', 404);
			}
			if ((int) $locked->version !== $expected_version)
			{
				throw new VersionConflictException('Ticket version changed');
			}
			// Status bisa berubah antara pembacaan dan penguncian.
			$this->guard($action, $locked, $actor['kind']);

			$method = 'do_'.$action;
			$update = $this->{$method}($locked, $data, $actor, $rule);

			$to_status = $update['status'] ?? NULL;
			$fields = $update['fields'] ?? array();
			if ($to_status !== NULL)
			{
				$fields['status'] = $to_status;
			}
			$new_version = $this->CI->tickets->update_with_version($locked->id, $expected_version, $fields);

			$this->CI->tickets->add_history($locked->id, array(
				'from_status' => $locked->status,
				'to_status' => $to_status ?: $locked->status,
				'action' => $action,
				'actor_user_id' => $actor['user_id'] ?? NULL,
				'actor_type' => $actor['type'],
				'reason_code' => $update['reason_code'] ?? NULL,
				'reason' => $update['reason'] ?? NULL,
				'ticket_version' => $new_version,
			));

			$fresh = $this->CI->tickets->find($locked->id);
			if (isset($update['after']) && is_callable($update['after']))
			{
				call_user_func($update['after'], $fresh);
			}
			$this->CI->audit->log('ticket.'.$action, 'ticket', $fresh->public_code, array(
				'from' => $locked->status, 'to' => $fresh->status, 'actor_type' => $actor['type'],
			));
			return $fresh;
		});
	}

	// ------------------------------------------------------------ Aksi petugas

	protected function do_start_verification($ticket, array $data, array $actor, array $rule)
	{
		return array('status' => 'verifying', 'fields' => array());
	}

	protected function do_request_information($ticket, array $data, array $actor, array $rule)
	{
		$question = trim((string) ($data['message'] ?? ''));
		if (mb_strlen($question) < 10)
		{
			throw new DomainRuleException('Tuliskan pertanyaan atau data yang dibutuhkan (minimal 10 karakter).', 422, array('message' => 'Pertanyaan wajib diisi.'));
		}
		$return_status = $ticket->status;
		$ticket_id = $ticket->id;
		$episode = (int) $ticket->current_episode;
		$actor_id = $actor['user_id'] ?? NULL;
		return array(
			'status' => 'needs_information',
			'fields' => array('return_status' => $return_status),
			'reason' => $question,
			'after' => function ($fresh) use ($question, $actor_id, $ticket_id, $episode) {
				$this->CI->tickets->add_message($ticket_id, array(
					'actor_user_id' => $actor_id, 'actor_type' => 'staff', 'message_kind' => 'question',
					'message' => $question, 'visibility' => 'reporter',
				));
				$this->CI->sla->mark_first_response_met($ticket_id, $episode);
				$this->CI->sla->pause($ticket_id, $episode, 'Menunggu kelengkapan dari pelapor', $actor_id);
				$this->notify_reporter($fresh, 'Petugas meminta kelengkapan pada laporan '.$fresh->public_code.'.');
			},
		);
	}

	protected function do_assign($ticket, array $data, array $actor, array $rule)
	{
		$assignee_id = (int) ($data['assignee_id'] ?? 0);
		$unit_id = (int) ($data['unit_id'] ?? 0) ?: NULL;
		$reason = trim((string) ($data['reason'] ?? ''));
		$assignee = $this->CI->user_model->find($assignee_id);
		if ( ! $assignee OR ! $this->CI->authz->can_be_assignee($assignee_id, $ticket))
		{
			throw new DomainRuleException('Petugas yang dipilih tidak dapat menangani laporan ini (izin, konflik kepentingan, atau status akun).', 422, array('assignee_id' => 'Pilih petugas lain.'));
		}
		if ($unit_id !== NULL && $this->CI->db->where('id', $unit_id)->where('active', 1)->count_all_results('organizational_units') === 0)
		{
			throw new DomainRuleException('Unit tujuan tidak valid.', 422, array('unit_id' => 'Pilih unit yang aktif.'));
		}
		$is_reassignment = ($ticket->assigned_user_id !== NULL);
		if ($is_reassignment && mb_strlen($reason) < 5)
		{
			throw new DomainRuleException('Tuliskan alasan pemindahan penugasan.', 422, array('reason' => 'Alasan wajib diisi.'));
		}
		// Reassignment tetap pada status kerja yang sesuai.
		$to_status = in_array($ticket->status, array('in_progress', 'needs_information'), TRUE) ? $ticket->status : 'assigned';
		$ticket_id = $ticket->id;
		$episode = (int) $ticket->current_episode;
		$actor_id = $actor['user_id'] ?? NULL;
		$previous_assignee = $ticket->assigned_user_id;
		$was_verifying = ($ticket->status === 'verifying');

		return array(
			'status' => $to_status,
			'fields' => array('assigned_user_id' => $assignee_id, 'assigned_unit_id' => $unit_id),
			'reason' => $reason ?: NULL,
			'after' => function ($fresh) use ($ticket_id, $episode, $assignee_id, $unit_id, $actor_id, $reason, $previous_assignee, $was_verifying) {
				$now = utc_now();
				if ($previous_assignee !== NULL)
				{
					$this->CI->db->where('ticket_id', $ticket_id)->where('ended_at IS NULL', NULL, FALSE)
						->update('ticket_assignments', array('ended_at' => $now));
				}
				db_must($this->CI->db->insert('ticket_assignments', array(
					'ticket_id' => $ticket_id, 'unit_id' => $unit_id, 'assignee_id' => $assignee_id,
					'assigned_by' => $actor_id, 'assigned_at' => $now, 'reason' => $reason ?: NULL,
				)), 'ticket_assignments.insert');
				if ($was_verifying)
				{
					$this->CI->sla->mark_verification_met($ticket_id, $episode, $now);
				}
				$this->CI->sla->start_first_response($ticket_id, $episode, $now);
				$this->CI->notifications->notify($assignee_id, 'ticket.assigned',
					'Anda ditugaskan menangani laporan '.$fresh->public_code.'.', 'ticket', $fresh->public_code, '/admin/laporan/'.$fresh->public_code);
				$this->notify_reporter($fresh, 'Laporan '.$fresh->public_code.' telah diteruskan ke petugas.');
			},
		);
	}

	protected function do_reject($ticket, array $data, array $actor, array $rule)
	{
		$reasons = $this->CI->config->item('rejection_reasons', 'app');
		$code = (string) ($data['reason_code'] ?? '');
		$explanation = trim((string) ($data['message'] ?? ''));
		if ( ! isset($reasons[$code]))
		{
			throw new DomainRuleException('Pilih alasan penolakan.', 422, array('reason_code' => 'Alasan wajib dipilih.'));
		}
		if (mb_strlen($explanation) < 15)
		{
			throw new DomainRuleException('Tuliskan penjelasan yang dapat dibaca pelapor (minimal 15 karakter).', 422, array('message' => 'Penjelasan wajib diisi.'));
		}
		$duplicate_of = NULL;
		if ($code === 'duplicate')
		{
			$other = $this->CI->tickets->find_by_code((string) ($data['duplicate_of'] ?? ''));
			if ( ! $other OR (int) $other->id === (int) $ticket->id)
			{
				throw new DomainRuleException('Nomor tiket duplikat tidak ditemukan.', 422, array('duplicate_of' => 'Nomor tiket tidak valid.'));
			}
			$duplicate_of = (int) $other->id;
		}
		$ticket_id = $ticket->id;
		$episode = (int) $ticket->current_episode;
		$actor_id = $actor['user_id'] ?? NULL;
		return array(
			'status' => 'rejected',
			'fields' => array('duplicate_of_ticket_id' => $duplicate_of, 'closed_at' => utc_now()),
			'reason_code' => $code,
			'reason' => $explanation,
			'after' => function ($fresh) use ($ticket_id, $episode, $explanation, $actor_id, $reasons, $code) {
				$this->CI->tickets->add_message($ticket_id, array(
					'actor_user_id' => $actor_id, 'actor_type' => 'staff', 'message_kind' => 'decision',
					'message' => 'Laporan tidak dapat diproses ('.$reasons[$code].').'."\n\n".$explanation, 'visibility' => 'reporter',
				));
				$this->CI->sla->mark_verification_met($ticket_id, $episode);
				$this->CI->sla->mark_first_response_met($ticket_id, $episode);
				$this->CI->db->where(array('ticket_id' => $ticket_id, 'episode_no' => $episode))
					->update('ticket_resolution_episodes', array('resolved_at' => utc_now()));
				$this->notify_reporter($fresh, 'Laporan '.$fresh->public_code.' tidak dapat diproses. Alasan tersedia pada detail laporan.');
			},
		);
	}

	protected function do_refer($ticket, array $data, array $actor, array $rule)
	{
		$target = trim((string) ($data['target_name'] ?? ''));
		$type = (string) ($data['reference_type'] ?? '');
		$url = trim((string) ($data['target_url'] ?? ''));
		$note = trim((string) ($data['message'] ?? ''));
		if (mb_strlen($target) < 3)
		{
			throw new DomainRuleException('Tuliskan instansi atau layanan tujuan.', 422, array('target_name' => 'Tujuan wajib diisi.'));
		}
		if ( ! in_array($type, array('guidance_only', 'actual_forwarding'), TRUE))
		{
			throw new DomainRuleException('Pilih jenis rujukan.', 422, array('reference_type' => 'Jenis rujukan wajib dipilih.'));
		}
		if ($url !== '' && ! app_is_safe_url($url))
		{
			throw new DomainRuleException('Tautan rujukan tidak valid.', 422, array('target_url' => 'Gunakan tautan http(s) yang valid.'));
		}
		$external_reference = trim((string) ($data['external_reference'] ?? ''));
		if ($type === 'actual_forwarding' && mb_strlen($external_reference) < 3)
		{
			throw new DomainRuleException('Untuk penerusan nyata, catat nomor/bukti penerusan dari instansi tujuan.', 422, array('external_reference' => 'Bukti penerusan wajib diisi.'));
		}
		if (mb_strlen($note) < 15)
		{
			throw new DomainRuleException('Tuliskan penjelasan rujukan untuk pelapor (minimal 15 karakter).', 422, array('message' => 'Penjelasan wajib diisi.'));
		}
		$ticket_id = $ticket->id;
		$episode = (int) $ticket->current_episode;
		$actor_id = $actor['user_id'] ?? NULL;
		return array(
			'status' => 'referred',
			'fields' => array('closed_at' => utc_now()),
			'reason_code' => $type,
			'reason' => $note,
			'after' => function ($fresh) use ($ticket_id, $episode, $target, $type, $url, $external_reference, $note, $actor_id) {
				db_must($this->CI->db->insert('ticket_references', array(
					'ticket_id' => $ticket_id, 'target_name' => $target, 'target_url' => ($url === '' ? NULL : $url),
					'reference_type' => $type, 'external_reference' => ($external_reference === '' ? NULL : $external_reference),
					'sent_at' => ($type === 'actual_forwarding') ? utc_now() : NULL,
					'recorded_by' => $actor_id, 'created_at' => utc_now(),
				)), 'ticket_references.insert');
				$label = ($type === 'actual_forwarding')
					? 'Laporan diteruskan ke '.$target.'.'
					: 'Laporan berada di luar kewenangan desa. Petunjuk rujukan ke '.$target.'.';
				$this->CI->tickets->add_message($ticket_id, array(
					'actor_user_id' => $actor_id, 'actor_type' => 'staff', 'message_kind' => 'decision',
					'message' => $label."\n\n".$note, 'visibility' => 'reporter',
				));
				$this->CI->sla->mark_verification_met($ticket_id, $episode);
				$this->CI->sla->mark_first_response_met($ticket_id, $episode);
				$this->CI->db->where(array('ticket_id' => $ticket_id, 'episode_no' => $episode))
					->update('ticket_resolution_episodes', array('resolved_at' => utc_now()));
				$this->notify_reporter($fresh, 'Laporan '.$fresh->public_code.' dirujuk ke layanan lain. Detail tersedia pada laporan Anda.');
			},
		);
	}

	protected function do_accept_work($ticket, array $data, array $actor, array $rule)
	{
		if ((int) $ticket->assigned_user_id !== (int) ($actor['user_id'] ?? 0))
		{
			throw new AccessDeniedException('Only the assignee can accept work.');
		}
		return array('status' => 'in_progress', 'fields' => array());
	}

	protected function do_follow_up($ticket, array $data, array $actor, array $rule)
	{
		$message = trim((string) ($data['message'] ?? ''));
		$visibility = ($data['visibility'] ?? 'reporter') === 'internal' ? 'internal' : 'reporter';
		if (mb_strlen($message) < 5)
		{
			throw new DomainRuleException('Tuliskan catatan tindak lanjut.', 422, array('message' => 'Catatan wajib diisi.'));
		}
		$ticket_id = $ticket->id;
		$episode = (int) $ticket->current_episode;
		$actor_id = $actor['user_id'] ?? NULL;
		return array(
			'status' => NULL,
			'fields' => array(),
			'after' => function ($fresh) use ($ticket_id, $episode, $message, $visibility, $actor_id) {
				$this->CI->tickets->add_message($ticket_id, array(
					'actor_user_id' => $actor_id, 'actor_type' => 'staff', 'message_kind' => 'follow_up',
					'message' => $message, 'visibility' => $visibility,
				));
				if ($visibility === 'reporter')
				{
					$this->CI->sla->mark_first_response_met($ticket_id, $episode);
					$this->notify_reporter($fresh, 'Ada tindak lanjut baru pada laporan '.$fresh->public_code.'.');
				}
			},
		);
	}

	protected function do_propose_resolution($ticket, array $data, array $actor, array $rule)
	{
		if ((int) $ticket->assigned_user_id !== (int) ($actor['user_id'] ?? 0) && ! $this->CI->authz->user_can($actor['user_id'], 'tickets.close'))
		{
			throw new AccessDeniedException('Only assignee or closer may propose resolution.');
		}
		$message = trim((string) ($data['message'] ?? ''));
		if (mb_strlen($message) < 15)
		{
			throw new DomainRuleException('Tuliskan hasil penanganan untuk ditanggapi pelapor (minimal 15 karakter).', 422, array('message' => 'Hasil penanganan wajib diisi.'));
		}
		$ticket_id = $ticket->id;
		$episode = (int) $ticket->current_episode;
		$actor_id = $actor['user_id'] ?? NULL;
		return array(
			'status' => 'awaiting_confirmation',
			'fields' => array(),
			'reason' => $message,
			'after' => function ($fresh) use ($ticket_id, $episode, $message, $actor_id) {
				$this->CI->tickets->add_message($ticket_id, array(
					'actor_user_id' => $actor_id, 'actor_type' => 'staff', 'message_kind' => 'result',
					'message' => $message, 'visibility' => 'reporter',
				));
				$this->CI->sla->mark_first_response_met($ticket_id, $episode);
				$this->CI->sla->start_confirmation_window($ticket_id, $episode);
				$this->notify_reporter($fresh, 'Petugas menyampaikan hasil penanganan laporan '.$fresh->public_code.'. Mohon tanggapan Anda.');
			},
		);
	}

	protected function do_close_by_policy($ticket, array $data, array $actor, array $rule)
	{
		$reason = trim((string) ($data['message'] ?? ''));
		if (mb_strlen($reason) < 15)
		{
			throw new DomainRuleException('Tuliskan alasan penutupan dan dasar kebijakannya (minimal 15 karakter).', 422, array('message' => 'Alasan penutupan wajib diisi.'));
		}
		$ticket_id = $ticket->id;
		$episode = (int) $ticket->current_episode;
		$actor_id = $actor['user_id'] ?? NULL;
		return array(
			'status' => 'resolved',
			'fields' => array('resolved_at' => utc_now(), 'closed_at' => utc_now()),
			'reason_code' => 'closed_by_policy',
			'reason' => $reason,
			'after' => function ($fresh) use ($ticket_id, $episode, $reason, $actor_id) {
				$this->CI->tickets->add_message($ticket_id, array(
					'actor_user_id' => $actor_id, 'actor_type' => 'staff', 'message_kind' => 'decision',
					'message' => 'Laporan ditutup oleh petugas berwenang.'."\n\n".$reason, 'visibility' => 'reporter',
				));
				$this->close_episode($ticket_id, $episode);
				$this->notify_reporter($fresh, 'Laporan '.$fresh->public_code.' ditutup dengan alasan yang dapat Anda baca pada detail laporan.');
			},
		);
	}

	protected function do_resume_work($ticket, array $data, array $actor, array $rule)
	{
		$ticket_id = $ticket->id;
		$episode = (int) $ticket->current_episode;
		return array(
			'status' => 'in_progress',
			'fields' => array(),
			'after' => function ($fresh) use ($ticket_id, $episode) {
				$this->CI->db->where(array('ticket_id' => $ticket_id, 'episode_no' => $episode))
					->update('ticket_sla_instances', array('confirmation_due_at' => NULL, 'updated_at' => utc_now()));
			},
		);
	}

	protected function do_reopen($ticket, array $data, array $actor, array $rule)
	{
		$reason = trim((string) ($data['message'] ?? ''));
		if (mb_strlen($reason) < 10)
		{
			throw new DomainRuleException('Tuliskan alasan membuka kembali laporan.', 422, array('message' => 'Alasan wajib diisi.'));
		}
		if ($ticket->assigned_user_id === NULL)
		{
			throw new DomainRuleException('Tentukan penanggung jawab sebelum membuka kembali laporan.', 422);
		}
		$ticket_id = $ticket->id;
		$new_episode = (int) $ticket->current_episode + 1;
		$actor_id = $actor['user_id'] ?? NULL;
		$category = $this->CI->tickets->category($ticket->category_id);
		return array(
			'status' => 'in_progress',
			'fields' => array('current_episode' => $new_episode, 'resolved_at' => NULL, 'closed_at' => NULL),
			'reason' => $reason,
			'after' => function ($fresh) use ($ticket_id, $new_episode, $reason, $actor_id, $category) {
				$now = utc_now();
				db_must($this->CI->db->insert('ticket_resolution_episodes', array(
					'ticket_id' => $ticket_id, 'episode_no' => $new_episode, 'opened_at' => $now,
					'reopened_by' => $actor_id, 'reopen_reason' => $reason,
				)), 'ticket_resolution_episodes.reopen');
				$this->CI->sla->start_instance($fresh, $new_episode, $this->CI->sla->policy_for_category($category), $now);
				$this->CI->sla->start_first_response($ticket_id, $new_episode, $now);
				$this->CI->tickets->add_message($ticket_id, array(
					'actor_user_id' => $actor_id, 'actor_type' => 'staff', 'message_kind' => 'reopen',
					'message' => 'Laporan dibuka kembali. '.$reason, 'visibility' => 'reporter',
				));
				if ($fresh->assigned_user_id)
				{
					$this->CI->notifications->notify($fresh->assigned_user_id, 'ticket.reopened',
						'Laporan '.$fresh->public_code.' dibuka kembali dan ditugaskan kepada Anda.', 'ticket', $fresh->public_code, '/admin/laporan/'.$fresh->public_code);
				}
				$this->notify_reporter($fresh, 'Laporan '.$fresh->public_code.' dibuka kembali untuk penanganan lanjutan.');
			},
		);
	}

	// ------------------------------------------------------------ Aksi pelapor

	protected function do_reply($ticket, array $data, array $actor, array $rule)
	{
		$message = trim((string) ($data['message'] ?? ''));
		if (mb_strlen($message) < 5 OR mb_strlen($message) > 5000)
		{
			throw new DomainRuleException('Tuliskan balasan Anda (5–5.000 karakter).', 422, array('message' => 'Balasan wajib diisi.'));
		}
		$ticket_id = $ticket->id;
		$actor_id = $actor['user_id'] ?? NULL;
		$actor_type = $actor['type'];
		return array(
			'status' => NULL,
			'fields' => array(),
			'after' => function ($fresh) use ($ticket_id, $message, $actor_id, $actor_type) {
				$this->CI->tickets->add_message($ticket_id, array(
					'actor_user_id' => $actor_id, 'actor_type' => $actor_type, 'message_kind' => 'reply',
					'message' => $message, 'visibility' => 'reporter',
				));
				$this->notify_handlers($fresh, 'Pelapor mengirim balasan pada laporan '.$fresh->public_code.'.');
			},
		);
	}

	protected function do_provide_information($ticket, array $data, array $actor, array $rule)
	{
		$message = trim((string) ($data['message'] ?? ''));
		if (mb_strlen($message) < 5 OR mb_strlen($message) > 5000)
		{
			throw new DomainRuleException('Tuliskan informasi yang diminta petugas (5–5.000 karakter).', 422, array('message' => 'Jawaban wajib diisi.'));
		}
		// Server menentukan status tujuan dari return stage yang disimpan.
		$return_status = in_array($ticket->return_status, array('verifying', 'assigned', 'in_progress'), TRUE) ? $ticket->return_status : 'verifying';
		$ticket_id = $ticket->id;
		$episode = (int) $ticket->current_episode;
		$actor_id = $actor['user_id'] ?? NULL;
		$actor_type = $actor['type'];
		return array(
			'status' => $return_status,
			'fields' => array('return_status' => NULL),
			'after' => function ($fresh) use ($ticket_id, $episode, $message, $actor_id, $actor_type) {
				$this->CI->tickets->add_message($ticket_id, array(
					'actor_user_id' => $actor_id, 'actor_type' => $actor_type, 'message_kind' => 'information',
					'message' => $message, 'visibility' => 'reporter',
				));
				$this->CI->sla->resume($ticket_id, $episode);
				$this->notify_handlers($fresh, 'Pelapor melengkapi informasi pada laporan '.$fresh->public_code.'.');
			},
		);
	}

	protected function do_accept_result($ticket, array $data, array $actor, array $rule)
	{
		$comment = trim((string) ($data['message'] ?? ''));
		$rating = isset($data['rating']) && $data['rating'] !== '' ? (int) $data['rating'] : NULL;
		if ($rating !== NULL && ($rating < 1 OR $rating > 5))
		{
			throw new DomainRuleException('Penilaian harus antara 1 sampai 5.', 422, array('rating' => 'Penilaian tidak valid.'));
		}
		$ticket_id = $ticket->id;
		$episode = (int) $ticket->current_episode;
		$actor_id = $actor['user_id'] ?? NULL;
		$actor_type = $actor['type'];
		return array(
			'status' => 'resolved',
			'fields' => array('resolved_at' => utc_now(), 'closed_at' => utc_now()),
			'reason' => $comment ?: NULL,
			'after' => function ($fresh) use ($ticket_id, $episode, $comment, $rating, $actor_id, $actor_type) {
				$this->CI->db->query(
					'INSERT INTO ticket_feedback (ticket_id, handling_episode, rating, response, comment, actor_user_id, actor_type, created_at)
					 VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE rating = VALUES(rating), response = VALUES(response), comment = VALUES(comment)',
					array($ticket_id, $episode, $rating, 'accepted', ($comment === '' ? NULL : $comment), $actor_id, $actor_type, utc_now())
				);
				if ($comment !== '')
				{
					$this->CI->tickets->add_message($ticket_id, array(
						'actor_user_id' => $actor_id, 'actor_type' => $actor_type, 'message_kind' => 'confirmation',
						'message' => $comment, 'visibility' => 'reporter',
					));
				}
				$this->close_episode($ticket_id, $episode);
				$this->notify_handlers($fresh, 'Pelapor menerima hasil penanganan laporan '.$fresh->public_code.'.');
			},
		);
	}

	protected function do_request_followup($ticket, array $data, array $actor, array $rule)
	{
		$message = trim((string) ($data['message'] ?? ''));
		if (mb_strlen($message) < 10)
		{
			throw new DomainRuleException('Tuliskan alasan Anda meminta tindak lanjut (minimal 10 karakter).', 422, array('message' => 'Alasan wajib diisi.'));
		}
		$ticket_id = $ticket->id;
		$episode = (int) $ticket->current_episode;
		$actor_id = $actor['user_id'] ?? NULL;
		$actor_type = $actor['type'];
		return array(
			'status' => 'in_progress',
			'fields' => array(),
			'reason' => $message,
			'after' => function ($fresh) use ($ticket_id, $episode, $message, $actor_id, $actor_type) {
				$this->CI->db->query(
					'INSERT INTO ticket_feedback (ticket_id, handling_episode, rating, response, comment, actor_user_id, actor_type, created_at)
					 VALUES (?, ?, NULL, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE response = VALUES(response), comment = VALUES(comment)',
					array($ticket_id, $episode, 'follow_up_requested', $message, $actor_id, $actor_type, utc_now())
				);
				$this->CI->tickets->add_message($ticket_id, array(
					'actor_user_id' => $actor_id, 'actor_type' => $actor_type, 'message_kind' => 'followup_request',
					'message' => $message, 'visibility' => 'reporter',
				));
				$this->CI->db->where(array('ticket_id' => $ticket_id, 'episode_no' => $episode))
					->update('ticket_sla_instances', array('confirmation_due_at' => NULL, 'updated_at' => utc_now()));
				$this->notify_handlers($fresh, 'Pelapor meminta tindak lanjut pada laporan '.$fresh->public_code.'.');
			},
		);
	}

	protected function do_withdraw($ticket, array $data, array $actor, array $rule)
	{
		$reason = trim((string) ($data['message'] ?? ''));
		$ticket_id = $ticket->id;
		$episode = (int) $ticket->current_episode;
		$actor_id = $actor['user_id'] ?? NULL;
		$actor_type = $actor['type'];
		return array(
			'status' => 'withdrawn',
			'fields' => array('withdrawn_at' => utc_now(), 'closed_at' => utc_now()),
			'reason' => $reason ?: NULL,
			'after' => function ($fresh) use ($ticket_id, $episode, $reason, $actor_id, $actor_type) {
				if ($reason !== '')
				{
					$this->CI->tickets->add_message($ticket_id, array(
						'actor_user_id' => $actor_id, 'actor_type' => $actor_type, 'message_kind' => 'withdrawal',
						'message' => $reason, 'visibility' => 'reporter',
					));
				}
				$this->close_episode($ticket_id, $episode);
				$this->notify_handlers($fresh, 'Pelapor menarik laporan '.$fresh->public_code.'.');
			},
		);
	}

	/** Penarikan saat sudah ditangani menjadi permintaan kepada petugas. */
	protected function do_request_withdrawal($ticket, array $data, array $actor, array $rule)
	{
		$reason = trim((string) ($data['message'] ?? ''));
		if (mb_strlen($reason) < 10)
		{
			throw new DomainRuleException('Tuliskan alasan permintaan penarikan (minimal 10 karakter).', 422, array('message' => 'Alasan wajib diisi.'));
		}
		$ticket_id = $ticket->id;
		$actor_id = $actor['user_id'] ?? NULL;
		$actor_type = $actor['type'];
		return array(
			'status' => NULL,
			'fields' => array('withdrawal_requested_at' => utc_now()),
			'reason' => $reason,
			'after' => function ($fresh) use ($ticket_id, $reason, $actor_id, $actor_type) {
				$this->CI->tickets->add_message($ticket_id, array(
					'actor_user_id' => $actor_id, 'actor_type' => $actor_type, 'message_kind' => 'withdrawal_request',
					'message' => 'Permintaan penarikan laporan: '.$reason, 'visibility' => 'reporter',
				));
				$this->notify_handlers($fresh, 'Pelapor meminta penarikan laporan '.$fresh->public_code.'. Proses yang sudah berjalan tetap tercatat.');
			},
		);
	}

	// ------------------------------------------------------------ Util

	protected function close_episode($ticket_id, $episode)
	{
		$this->CI->db->where(array('ticket_id' => $ticket_id, 'episode_no' => $episode))->where('resolved_at IS NULL', NULL, FALSE)
			->update('ticket_resolution_episodes', array('resolved_at' => utc_now()));
		$this->CI->sla->mark_resolved($ticket_id, $episode);
	}

	protected function notify_reporter($ticket, $summary)
	{
		if ($ticket->reporter_user_id !== NULL)
		{
			$this->CI->notifications->notify($ticket->reporter_user_id, 'ticket.update', $summary, 'ticket', $ticket->public_code, '/warga/laporan/'.$ticket->public_code);
		}
		// Pelapor anonim tidak memiliki akun: pemberitahuan diambil saat melacak tiket.
	}

	protected function notify_handlers($ticket, $summary)
	{
		$recipients = array();
		if ($ticket->assigned_user_id !== NULL)
		{
			$recipients[] = (int) $ticket->assigned_user_id;
		}
		if (empty($recipients))
		{
			$this->CI->notifications->notify_permission_holders('tickets.verify', 'ticket.update', $summary, 'ticket', $ticket->public_code, '/admin/laporan/'.$ticket->public_code);
			return;
		}
		$this->CI->notifications->notify_many($recipients, 'ticket.update', $summary, 'ticket', $ticket->public_code, '/admin/laporan/'.$ticket->public_code);
	}

	/**
	 * Aksi yang tersedia bagi pelapor pada status saat ini (untuk tampilan).
	 */
	public function reporter_actions($ticket)
	{
		$actions = array();
		foreach (array('reply', 'provide_information', 'accept_result', 'request_followup', 'withdraw', 'request_withdrawal') as $action)
		{
			if (in_array($ticket->status, self::ALLOWED[$action]['from'], TRUE))
			{
				$actions[] = $action;
			}
		}
		return $actions;
	}

	/** Aksi petugas yang tersedia sesuai status + kemampuan. */
	public function staff_actions($ticket, array $abilities)
	{
		$available = array();
		$map = array(
			'start_verification' => $abilities['verify'],
			'request_information' => $abilities['verify'] || $abilities['work'],
			'assign' => $abilities['assign'],
			'reject' => $abilities['verify'],
			'refer' => $abilities['verify'],
			'accept_work' => $abilities['work'],
			'follow_up' => $abilities['work'] || $abilities['verify'] || $abilities['monitor'],
			'propose_resolution' => $abilities['work'] || $abilities['close'],
			'close_by_policy' => $abilities['close'],
			'resume_work' => $abilities['work'] || $abilities['close'],
			'reopen' => $abilities['reopen'],
		);
		foreach ($map as $action => $allowed)
		{
			if ($allowed && in_array($ticket->status, self::ALLOWED[$action]['from'], TRUE))
			{
				$available[] = $action;
			}
		}
		return $available;
	}
}
