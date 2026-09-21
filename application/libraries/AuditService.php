<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Log audit append-only. Tidak menyimpan password, token, kode anonim, OTP,
 * cookie, payload multipart atau narasi laporan lengkap.
 */
class AuditService {

	/** @var CI_Controller */
	protected $CI;

	/** @var int|null actor default untuk request ini */
	public $actor_user_id = NULL;
	public $actor_label = NULL;
	public $request_id = NULL;

	protected $forbidden_key_pattern = '/pass|token|secret|code_plain|access_code|otp|cookie|session|description|message|body|nik|recovery/i';

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	public function log($action, $entity_type = NULL, $entity_id = NULL, array $metadata = array(), $actor_user_id = FALSE, $module_code = NULL)
	{
		$actor = ($actor_user_id === FALSE) ? $this->actor_user_id : $actor_user_id;
		$row = array(
			'actor_user_id' => $actor,
			// Saat super admin login sebagai pengguna lain, pelaku sebenarnya ikut tercatat.
			'impersonator_user_id' => $this->impersonator_id(),
			'actor_label' => ($actor === NULL) ? ($this->actor_label !== NULL ? $this->actor_label : (is_cli() ? 'cli' : 'guest')) : NULL,
			'action' => substr((string) $action, 0, 80),
			'module_code' => ($module_code === NULL) ? $this->module_from_action((string) $action) : substr((string) $module_code, 0, 50),
			'entity_type' => ($entity_type === NULL) ? NULL : substr((string) $entity_type, 0, 50),
			'entity_id' => ($entity_id === NULL) ? NULL : substr((string) $entity_id, 0, 64),
			'safe_metadata_json' => empty($metadata) ? NULL : json_encode($this->sanitize($metadata), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			'request_id' => $this->request_id,
			'ip_hash' => $this->ip_hash(),
			'user_agent_digest' => $this->user_agent_digest(),
			'created_at' => utc_now(),
		);
		if ( ! $this->CI->db->insert('audit_logs', $row))
		{
			log_message('error', 'Audit insert failed for action '.$row['action']);
			return FALSE;
		}
		return TRUE;
	}

	protected function impersonator_id()
	{
		if (is_cli() OR ! isset($this->CI->session))
		{
			return NULL;
		}
		$auth = $this->CI->session->userdata('auth');
		return (is_array($auth) && ! empty($auth['imp'])) ? (int) $auth['imp'] : NULL;
	}

	/**
	 * Peristiwa operasional untuk dashboard maintenance. Terpisah dari audit log:
	 * audit menjawab "siapa melakukan apa", peristiwa menjawab "apa yang perlu ditindaklanjuti".
	 */
	public function event($severity, $message, $module_code = NULL, array $context = array(), $actor_user_id = FALSE)
	{
		$actor = ($actor_user_id === FALSE) ? $this->actor_user_id : $actor_user_id;
		$severity = in_array($severity, array('info', 'warning', 'error'), TRUE) ? $severity : 'info';
		$row = array(
			'severity' => $severity,
			'module_code' => ($module_code === NULL) ? NULL : substr((string) $module_code, 0, 50),
			'message' => mb_substr((string) $message, 0, 255),
			'context_json' => empty($context) ? NULL : json_encode($this->sanitize($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			'actor_user_id' => $actor,
			'occurred_at' => utc_now(),
		);
		if ( ! $this->CI->db->insert('admin_activity_events', $row))
		{
			log_message('error', 'Activity event insert failed: '.$row['message']);
			return FALSE;
		}
		return TRUE;
	}

	/** Prefiks aksi dipakai sebagai kode modul default, mis. "module.state_changed" → "module". */
	protected function module_from_action($action)
	{
		$prefix = strstr($action, '.', TRUE);
		return ($prefix === FALSE || $prefix === '') ? NULL : substr($prefix, 0, 50);
	}

	/** IP disimpan sebagai HMAC berkunci, bukan alamat mentah. */
	protected function ip_hash()
	{
		if (is_cli())
		{
			return NULL;
		}
		$ip = (string) $this->CI->input->ip_address();
		if ($ip === '')
		{
			return NULL;
		}
		$this->CI->load->library('Crypto', NULL, 'crypto');
		return $this->CI->crypto->hmac($ip, 'rate_limit');
	}

	/** Hanya ringkasan user agent (nama peramban/OS terpotong), bukan string penuh. */
	protected function user_agent_digest()
	{
		if (is_cli())
		{
			return NULL;
		}
		$ua = trim((string) $this->CI->input->user_agent());
		if ($ua === '')
		{
			return NULL;
		}
		$ua = preg_replace('/[0-9]{3,}/', '', $ua);
		return mb_substr(preg_replace('/\s+/', ' ', $ua), 0, 120);
	}

	public function sanitize(array $metadata, $depth = 0)
	{
		$clean = array();
		foreach ($metadata as $key => $value)
		{
			if (is_string($key) && preg_match($this->forbidden_key_pattern, $key))
			{
				continue;
			}
			if (is_array($value))
			{
				$clean[$key] = ($depth < 2) ? $this->sanitize($value, $depth + 1) : '[array]';
			}
			elseif (is_scalar($value) OR $value === NULL)
			{
				$clean[$key] = is_string($value) ? mb_substr($value, 0, 200) : $value;
			}
		}
		return $clean;
	}
}
