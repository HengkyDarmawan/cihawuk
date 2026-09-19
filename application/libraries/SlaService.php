<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Target layanan (SLA) berbasis kalender kerja desa.
 *
 * - Tenggat disimpan UTC, dihitung memakai kalender zona Asia/Jakarta.
 * - Kebijakan di-snapshot saat instance dibuat; perubahan konfigurasi tidak
 *   mengubah tenggat kasus lama.
 * - Overdue adalah indikator turunan (due terlewat dan milestone belum tercapai),
 *   bukan status laporan.
 */
class SlaService {

	/** @var CI_Controller */
	protected $CI;

	/** @var array cache kalender */
	protected $calendars = array();

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	// ------------------------------------------------------------ Kebijakan

	public function policy_for_category($category)
	{
		if ($category && $category->sla_policy_id)
		{
			$policy = $this->CI->db->get_where('sla_policies', array('id' => (int) $category->sla_policy_id, 'active' => 1))->row();
			if ($policy)
			{
				return $policy;
			}
		}
		$code = (string) $this->CI->settings->get('tickets.default_sla_policy', 'DEFAULT');
		return $this->CI->db->get_where('sla_policies', array('code' => $code, 'active' => 1))->row();
	}

	protected function calendar($calendar_id)
	{
		$id = (int) $calendar_id;
		if ( ! isset($this->calendars[$id]))
		{
			$row = $this->CI->db->get_where('business_calendars', array('id' => $id))->row();
			$schedule = $row ? json_decode($row->weekly_schedule_json, TRUE) : NULL;
			$holidays = array();
			foreach ($this->CI->db->get_where('business_holidays', array('calendar_id' => $id))->result() as $h)
			{
				$holidays[$h->date] = (int) $h->is_working_override;
			}
			$this->calendars[$id] = array(
				'timezone' => $row ? $row->timezone : 'Asia/Jakarta',
				'schedule' => is_array($schedule) ? $schedule : array('1' => array(array('start' => '08:00', 'end' => '16:00'))),
				'holidays' => $holidays,
			);
		}
		return $this->calendars[$id];
	}

	/**
	 * Tenggat setelah $days hari kerja sejak $from (UTC), jatuh pada akhir jam
	 * pelayanan hari kerja ke-$days.
	 *
	 * @return string DATETIME UTC
	 */
	public function due_after_working_days($from_utc, $days, $calendar_id)
	{
		$cal = $this->calendar($calendar_id);
		$tz = new DateTimeZone($cal['timezone']);
		$cursor = (new DateTimeImmutable($from_utc, new DateTimeZone('UTC')))->setTimezone($tz);
		$remaining = max(1, (int) $days);
		$guard = 0;
		while ($guard++ < 400)
		{
			$cursor = $cursor->modify('+1 day');
			if ($this->is_working_day($cursor, $cal))
			{
				$remaining--;
				if ($remaining === 0)
				{
					$end = $this->day_end($cursor, $cal);
					return $end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
				}
			}
		}
		// Kalender tidak memiliki hari kerja: jatuh kembali ke hari kalender.
		return (new DateTimeImmutable($from_utc, new DateTimeZone('UTC')))->modify('+'.max(1, (int) $days).' days')->format('Y-m-d H:i:s');
	}

	/** Tenggat berbasis hari kalender (mis. waktu tanggapan pelapor). */
	public function due_after_calendar_days($from_utc, $days, $calendar_id)
	{
		$cal = $this->calendar($calendar_id);
		$tz = new DateTimeZone($cal['timezone']);
		$local = (new DateTimeImmutable($from_utc, new DateTimeZone('UTC')))->setTimezone($tz)->modify('+'.max(1, (int) $days).' days');
		return $this->day_end($local, $cal)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
	}

	protected function is_working_day(DateTimeImmutable $local, array $cal)
	{
		$date = $local->format('Y-m-d');
		if (array_key_exists($date, $cal['holidays']))
		{
			return $cal['holidays'][$date] === 1;
		}
		$dow = (string) (int) $local->format('N');
		return ! empty($cal['schedule'][$dow]);
	}

	protected function day_end(DateTimeImmutable $local, array $cal)
	{
		$dow = (string) (int) $local->format('N');
		$windows = $cal['schedule'][$dow] ?? array();
		$end = '16:00';
		if ( ! empty($windows))
		{
			$last = end($windows);
			$end = $last['end'] ?? '16:00';
		}
		list($h, $m) = array_pad(explode(':', $end), 2, '00');
		return $local->setTime((int) $h, (int) $m, 0);
	}

	// ------------------------------------------------------------ Instance

	/** Mulai instance SLA untuk satu episode penanganan. */
	public function start_instance($ticket, $episode_no, $policy, $from_utc)
	{
		if ( ! $policy)
		{
			return NULL;
		}
		$snapshot = array(
			'policy_code' => $policy->code,
			'verification_days' => (int) $policy->verification_days,
			'first_response_days' => (int) $policy->first_response_days,
			'confirmation_days' => (int) $policy->confirmation_days,
			'resolution_days' => $policy->resolution_days === NULL ? NULL : (int) $policy->resolution_days,
			'calendar_id' => (int) $policy->calendar_id,
			'pause_rules' => json_decode($policy->pause_rules_json, TRUE) ?: array(),
			'auto_close_enabled' => (int) $policy->auto_close_enabled,
			'snapshot_at' => utc_now(),
		);
		$now = utc_now();
		$data = array(
			'ticket_id' => (int) $ticket->id,
			'episode_no' => (int) $episode_no,
			'policy_snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
			'verification_due_at' => ($episode_no === 1) ? $this->due_after_working_days($from_utc, $snapshot['verification_days'], $snapshot['calendar_id']) : NULL,
			'resolution_due_at' => $snapshot['resolution_days'] ? $this->due_after_working_days($from_utc, $snapshot['resolution_days'], $snapshot['calendar_id']) : NULL,
			'created_at' => $now,
			'updated_at' => $now,
		);
		db_must($this->CI->db->query(
			'INSERT INTO ticket_sla_instances (ticket_id, episode_no, policy_snapshot_json, verification_due_at, resolution_due_at, created_at, updated_at)
			 VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)',
			array($data['ticket_id'], $data['episode_no'], $data['policy_snapshot_json'], $data['verification_due_at'], $data['resolution_due_at'], $now, $now)
		), 'ticket_sla_instances.start');
		return $this->instance($ticket->id, $episode_no);
	}

	public function instance($ticket_id, $episode_no)
	{
		return $this->CI->db->get_where('ticket_sla_instances', array('ticket_id' => (int) $ticket_id, 'episode_no' => (int) $episode_no))->row();
	}

	protected function snapshot($instance)
	{
		return json_decode($instance->policy_snapshot_json, TRUE) ?: array();
	}

	protected function update_instance($instance_id, array $data)
	{
		$data['updated_at'] = utc_now();
		db_must($this->CI->db->where('id', (int) $instance_id)->update('ticket_sla_instances', $data), 'ticket_sla_instances.update');
	}

	public function mark_verification_met($ticket_id, $episode_no, $at = NULL)
	{
		$instance = $this->instance($ticket_id, $episode_no);
		if ($instance && $instance->verification_met_at === NULL)
		{
			$this->update_instance($instance->id, array('verification_met_at' => $at ?: utc_now()));
		}
	}

	/** Tenggat respons awal dihitung sejak disposisi. */
	public function start_first_response($ticket_id, $episode_no, $assigned_at)
	{
		$instance = $this->instance($ticket_id, $episode_no);
		if ( ! $instance OR $instance->first_response_due_at !== NULL)
		{
			return;
		}
		$snap = $this->snapshot($instance);
		$this->update_instance($instance->id, array(
			'first_response_due_at' => $this->due_after_working_days($assigned_at, $snap['first_response_days'] ?? 5, $snap['calendar_id'] ?? 1),
		));
	}

	/** First response = balasan yang dapat dibaca pelapor, bukan sekadar penugasan. */
	public function mark_first_response_met($ticket_id, $episode_no, $at = NULL)
	{
		$instance = $this->instance($ticket_id, $episode_no);
		if ($instance && $instance->first_response_met_at === NULL && $instance->first_response_due_at !== NULL)
		{
			$this->update_instance($instance->id, array('first_response_met_at' => $at ?: utc_now()));
		}
	}

	public function start_confirmation_window($ticket_id, $episode_no, $from = NULL)
	{
		$instance = $this->instance($ticket_id, $episode_no);
		if ( ! $instance)
		{
			return;
		}
		$snap = $this->snapshot($instance);
		$this->update_instance($instance->id, array(
			'confirmation_due_at' => $this->due_after_calendar_days($from ?: utc_now(), $snap['confirmation_days'] ?? 10, $snap['calendar_id'] ?? 1),
		));
	}

	public function mark_resolved($ticket_id, $episode_no, $at = NULL)
	{
		$instance = $this->instance($ticket_id, $episode_no);
		if ($instance && $instance->resolution_met_at === NULL)
		{
			$this->update_instance($instance->id, array('resolution_met_at' => $at ?: utc_now(), 'confirmation_due_at' => NULL));
		}
	}

	// ------------------------------------------------------------ Pause

	public function pause($ticket_id, $episode_no, $reason, $actor_user_id = NULL)
	{
		$instance = $this->instance($ticket_id, $episode_no);
		if ( ! $instance)
		{
			return;
		}
		$rules = $this->snapshot($instance)['pause_rules'] ?? array();
		if (empty($rules['pause_resolution_on_needs_information']))
		{
			return;
		}
		$open = $this->CI->db->where('sla_instance_id', (int) $instance->id)->where('ended_at IS NULL', NULL, FALSE)->count_all_results('ticket_sla_pauses');
		if ($open > 0)
		{
			return;
		}
		db_must($this->CI->db->insert('ticket_sla_pauses', array(
			'sla_instance_id' => (int) $instance->id,
			'started_at' => utc_now(),
			'reason' => mb_substr((string) $reason, 0, 255),
			'actor_user_id' => $actor_user_id,
		)), 'ticket_sla_pauses.start');
	}

	/**
	 * Akhiri jeda dan geser tenggat penyelesaian sepanjang durasi jeda.
	 * Tenggat verifikasi yang sudah lewat tidak dihapus.
	 */
	public function resume($ticket_id, $episode_no)
	{
		$instance = $this->instance($ticket_id, $episode_no);
		if ( ! $instance)
		{
			return;
		}
		$pause = $this->CI->db->where('sla_instance_id', (int) $instance->id)->where('ended_at IS NULL', NULL, FALSE)
			->order_by('id', 'DESC')->limit(1)->get('ticket_sla_pauses')->row();
		if ( ! $pause)
		{
			return;
		}
		$now = utc_now();
		db_must($this->CI->db->where('id', (int) $pause->id)->update('ticket_sla_pauses', array('ended_at' => $now)), 'ticket_sla_pauses.end');
		$seconds = max(0, strtotime($now.' UTC') - strtotime($pause->started_at.' UTC'));
		if ($instance->resolution_due_at !== NULL && $seconds > 0)
		{
			$shifted = (new DateTimeImmutable($instance->resolution_due_at, new DateTimeZone('UTC')))->modify('+'.$seconds.' seconds');
			$this->update_instance($instance->id, array('resolution_due_at' => $shifted->format('Y-m-d H:i:s')));
		}
	}

	public function paused($ticket_id, $episode_no)
	{
		$instance = $this->instance($ticket_id, $episode_no);
		if ( ! $instance)
		{
			return FALSE;
		}
		return $this->CI->db->where('sla_instance_id', (int) $instance->id)->where('ended_at IS NULL', NULL, FALSE)->count_all_results('ticket_sla_pauses') > 0;
	}

	// ------------------------------------------------------------ Indikator

	/**
	 * Ringkasan milestone untuk tampilan: due, tercapai, dan keterlambatan.
	 */
	public function status($ticket, $episode_no = NULL)
	{
		$episode_no = $episode_no ?: (int) $ticket->current_episode;
		$instance = $this->instance($ticket->id, $episode_no);
		if ( ! $instance)
		{
			return NULL;
		}
		$now = $this->CI->clock->timestamp();
		$milestone = function ($due, $met) use ($now) {
			if ($due === NULL)
			{
				return NULL;
			}
			$due_ts = strtotime($due.' UTC');
			return array(
				'due_at' => $due,
				'met_at' => $met,
				'overdue' => ($met === NULL && $due_ts < $now),
				'late_seconds' => ($met === NULL && $due_ts < $now) ? ($now - $due_ts) : (($met !== NULL && strtotime($met.' UTC') > $due_ts) ? strtotime($met.' UTC') - $due_ts : 0),
			);
		};
		return array(
			'episode_no' => $episode_no,
			'paused' => $this->paused($ticket->id, $episode_no),
			'verification' => $milestone($instance->verification_due_at, $instance->verification_met_at),
			'first_response' => $milestone($instance->first_response_due_at, $instance->first_response_met_at),
			'confirmation' => $milestone($instance->confirmation_due_at, NULL),
			'resolution' => $milestone($instance->resolution_due_at, $instance->resolution_met_at),
			'policy' => $this->snapshot($instance),
		);
	}

	/** Apakah tiket memiliki milestone yang terlambat (untuk daftar/monitoring). */
	public function is_overdue($ticket)
	{
		$status = $this->status($ticket);
		if ( ! $status)
		{
			return FALSE;
		}
		foreach (array('verification', 'first_response', 'resolution') as $key)
		{
			if ($status[$key] && $status[$key]['overdue'])
			{
				return TRUE;
			}
		}
		return FALSE;
	}
}
