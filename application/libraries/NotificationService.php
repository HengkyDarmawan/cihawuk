<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Notifikasi dalam aplikasi + outbox email.
 * Ringkasan notifikasi tidak memuat narasi laporan atau identitas pelapor.
 */
class NotificationService {

	/** @var CI_Controller */
	protected $CI;

	public $max_attempts = 5;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->model('User_model', 'user_model');
	}

	public function notify($user_id, $type, $safe_summary, $entity_type = NULL, $entity_id = NULL, $link_path = NULL, $dedupe_suffix = NULL)
	{
		$now = utc_now();
		db_must($this->CI->db->insert('notifications', array(
			'recipient_user_id' => (int) $user_id,
			'type' => $type,
			'entity_type' => $entity_type,
			'entity_id' => ($entity_id === NULL) ? NULL : (string) $entity_id,
			'safe_summary' => mb_substr($safe_summary, 0, 255),
			'link_path' => $link_path,
			'created_at' => $now,
		)), 'notifications.create');
		$notification_id = (int) $this->CI->db->insert_id();

		$user = $this->CI->user_model->find($user_id);
		if ($user && $user->email !== NULL && $user->email_verified_at !== NULL && $user->account_status === 'active')
		{
			$this->queue('email', (int) $user_id, 'notification', $entity_type, $entity_id, array(
				'summary' => mb_substr($safe_summary, 0, 255),
				'link_path' => $link_path,
			), 'notif:'.$notification_id.($dedupe_suffix ? ':'.$dedupe_suffix : ''));
		}
		return $notification_id;
	}

	public function notify_many(array $user_ids, $type, $safe_summary, $entity_type = NULL, $entity_id = NULL, $link_path = NULL)
	{
		foreach (array_unique(array_map('intval', $user_ids)) as $uid)
		{
			if ($uid > 0)
			{
				$this->notify($uid, $type, $safe_summary, $entity_type, $entity_id, $link_path);
			}
		}
	}

	public function notify_permission_holders($permission, $type, $safe_summary, $entity_type = NULL, $entity_id = NULL, $link_path = NULL, array $exclude = array())
	{
		$ids = array();
		foreach ($this->CI->user_model->active_users_with_permission($permission) as $u)
		{
			if ( ! in_array((int) $u->id, $exclude, TRUE))
			{
				$ids[] = (int) $u->id;
			}
		}
		$this->notify_many($ids, $type, $safe_summary, $entity_type, $entity_id, $link_path);
		return $ids;
	}

	/** Masukkan pesan ke outbox (idempotent berdasarkan dedupe_key). */
	public function queue($channel, $recipient_user_id, $template_key, $entity_type, $entity_id, array $payload, $dedupe_key)
	{
		$now = utc_now();
		return db_must($this->CI->db->query(
			'INSERT IGNORE INTO notification_outbox (recipient_user_id, channel, template_key, entity_type, entity_id, payload_json, dedupe_key, status, attempts, next_attempt_at, created_at, updated_at)
			 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)',
			array($recipient_user_id, $channel, $template_key, $entity_type, ($entity_id === NULL ? NULL : (string) $entity_id),
				json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), substr($dedupe_key, 0, 191), 'pending', $now, $now, $now)
		), 'notification_outbox.queue');
	}

	public function unread_count($user_id)
	{
		return (int) $this->CI->db->where('recipient_user_id', (int) $user_id)->where('read_at IS NULL', NULL, FALSE)->count_all_results('notifications');
	}

	public function latest($user_id, $limit = 20, $offset = 0)
	{
		return $this->CI->db->where('recipient_user_id', (int) $user_id)
			->order_by('created_at', 'DESC')->order_by('id', 'DESC')
			->limit((int) $limit, (int) $offset)->get('notifications')->result();
	}

	public function mark_read($user_id, $notification_id = NULL)
	{
		$this->CI->db->where('recipient_user_id', (int) $user_id)->where('read_at IS NULL', NULL, FALSE);
		if ($notification_id !== NULL)
		{
			$this->CI->db->where('id', (int) $notification_id);
		}
		$this->CI->db->update('notifications', array('read_at' => utc_now()));
	}

	/**
	 * Proses outbox (dipanggil job CLI). Kegagalan tidak mempengaruhi tiket.
	 * @return array ringkasan hasil
	 */
	public function process_outbox($limit = 50)
	{
		$summary = array('sent' => 0, 'failed' => 0, 'retry' => 0, 'not_configured' => 0);
		$rows = $this->CI->db->where('status', 'pending')->where('next_attempt_at <=', utc_now())
			->order_by('id')->limit((int) $limit)->get('notification_outbox')->result();
		foreach ($rows as $row)
		{
			// Klaim baris secara atomik agar dua scheduler tidak mengirim ganda.
			$this->CI->db->where(array('id' => (int) $row->id, 'status' => 'pending', 'attempts' => (int) $row->attempts))
				->update('notification_outbox', array('status' => 'sending', 'attempts' => (int) $row->attempts + 1, 'updated_at' => utc_now()));
			if ($this->CI->db->affected_rows() !== 1)
			{
				continue;
			}
			$attempts = (int) $row->attempts + 1;
			$result = $this->deliver($row);
			$now = $this->CI->clock->now();
			if ($result === TRUE)
			{
				$this->CI->db->where('id', (int) $row->id)->update('notification_outbox', array('status' => 'sent', 'sent_at' => $now->format('Y-m-d H:i:s'), 'last_error' => NULL, 'updated_at' => $now->format('Y-m-d H:i:s')));
				$summary['sent']++;
			}
			elseif ($result === 'not_configured' OR $result === 'skipped')
			{
				$this->CI->db->where('id', (int) $row->id)->update('notification_outbox', array('status' => $result, 'last_error' => $result, 'updated_at' => $now->format('Y-m-d H:i:s')));
				$summary['not_configured']++;
			}
			elseif ($attempts >= $this->max_attempts)
			{
				$this->CI->db->where('id', (int) $row->id)->update('notification_outbox', array('status' => 'failed', 'last_error' => substr((string) $result, 0, 255), 'updated_at' => $now->format('Y-m-d H:i:s')));
				$summary['failed']++;
			}
			else
			{
				$delay = (int) min(3600, 60 * (2 ** ($attempts - 1)));
				$this->CI->db->where('id', (int) $row->id)->update('notification_outbox', array(
					'status' => 'pending', 'last_error' => substr((string) $result, 0, 255),
					'next_attempt_at' => $now->modify('+'.$delay.' seconds')->format('Y-m-d H:i:s'), 'updated_at' => $now->format('Y-m-d H:i:s'),
				));
				$summary['retry']++;
			}
		}
		// Pulihkan baris 'sending' yang tertinggal (proses mati di tengah jalan).
		$this->CI->db->where('status', 'sending')->where('updated_at <', $this->CI->clock->plus_seconds(-900))
			->update('notification_outbox', array('status' => 'pending', 'updated_at' => utc_now()));
		return $summary;
	}

	/** @return true|string */
	protected function deliver($row)
	{
		if ($row->channel !== 'email')
		{
			return 'skipped';
		}
		if ( ! $this->CI->mailer->enabled())
		{
			return 'not_configured';
		}
		$user = $row->recipient_user_id ? $this->CI->user_model->find($row->recipient_user_id) : NULL;
		if ( ! $user OR $user->email === NULL OR $user->email_verified_at === NULL)
		{
			return 'skipped';
		}
		$payload = json_decode($row->payload_json, TRUE) ?: array();
		$vars = array(
			'name' => $user->display_name,
			'summary' => (string) ($payload['summary'] ?? ''),
			'url' => empty($payload['link_path']) ? site_url('/') : site_url(ltrim($payload['link_path'], '/')),
		);
		$html = $this->CI->load->view('email/notification', $vars + array('format' => 'html'), TRUE);
		$text = $this->CI->load->view('email/notification', $vars + array('format' => 'text'), TRUE);
		$ok = $this->CI->mailer->send($user->email, 'Pemberitahuan Layanan Desa Cihawuk', $html, $text);
		return $ok ? TRUE : (string) $this->CI->mailer->last_error;
	}
}
