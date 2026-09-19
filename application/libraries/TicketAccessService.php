<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Nomor tiket, kode akses anonim, grant sesi pelacakan dan receipt sekali tampil.
 *
 * - Nomor tiket bukan rahasia; kode akses terpisah dengan entropi ≥128 bit.
 * - Hanya hash kode yang disimpan; kode asli hanya muncul sekali pada receipt.
 * - Kode tidak pernah masuk URL, log, atau email pihak ketiga.
 */
class TicketAccessService {

	/** @var CI_Controller */
	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->config->load('app', TRUE);
	}

	// ------------------------------------------------------------ Nomor tiket

	/** Contoh: CHW-2026-X7K4N9Q2 (suffix acak kriptografis + unique index). */
	public function generate_public_code()
	{
		$year = $this->CI->clock->now()->setTimezone(local_tz())->format('Y');
		for ($i = 0; $i < 10; $i++)
		{
			$code = 'CHW-'.$year.'-'.$this->CI->crypto->random_code(8);
			if ($this->CI->db->where('public_code', $code)->count_all_results('tickets') === 0)
			{
				return $code;
			}
		}
		throw new RuntimeException('Unable to generate unique ticket code.');
	}

	// ------------------------------------------------------------ Kode akses

	/** Buat kode akses baru (16 byte acak) dan simpan hash-nya. @return string kode asli */
	public function issue_access_code($ticket_id, $version = 1)
	{
		$plain = $this->CI->crypto->base32_encode(random_bytes(16)); // 26 karakter, 128 bit
		$this->CI->db->where('ticket_id', (int) $ticket_id)->update('ticket_access_secrets', array('revoked_at' => utc_now()));
		db_must($this->CI->db->query(
			'INSERT INTO ticket_access_secrets (ticket_id, secret_hash, secret_version, created_at, revoked_at) VALUES (?, ?, ?, ?, NULL)
			 ON DUPLICATE KEY UPDATE secret_hash = VALUES(secret_hash), secret_version = VALUES(secret_version), created_at = VALUES(created_at), revoked_at = NULL',
			array((int) $ticket_id, $this->hash_code($plain), (int) $version, utc_now())
		), 'ticket_access_secrets');
		return $plain;
	}

	public function hash_code($plain)
	{
		return $this->CI->crypto->hmac('ticket-access|'.$plain, 'token');
	}

	/** Tampilan berkelompok agar mudah disalin: XXXXX-XXXXX-… */
	public function format_code($plain)
	{
		return $this->CI->crypto->group_code($plain, 5);
	}

	/**
	 * Verifikasi pasangan nomor tiket + kode akses.
	 * @return object|null tiket bila cocok
	 */
	public function verify($public_code, $access_code)
	{
		$this->CI->load->model('Ticket_model', 'tickets');
		$ticket = $this->CI->tickets->find_by_code($public_code);
		$normalized = $this->CI->crypto->normalize_code($access_code);
		if ( ! $ticket OR $normalized === '' OR strlen($normalized) < 20)
		{
			return NULL;
		}
		$secret = $this->CI->db->get_where('ticket_access_secrets', array('ticket_id' => (int) $ticket->id))->row();
		if ( ! $secret OR $secret->revoked_at !== NULL)
		{
			return NULL;
		}
		return hash_equals($secret->secret_hash, $this->hash_code($normalized)) ? $ticket : NULL;
	}

	// ------------------------------------------------------------ Grant sesi

	/**
	 * Buat grant sesi singkat untuk satu tiket. Token grant disimpan di sesi,
	 * hash-nya di database (session ID CI3 berotasi sehingga tidak dipakai langsung).
	 */
	public function grant_session($ticket)
	{
		$token = $this->CI->crypto->random_hex(32);
		$ttl = (int) $this->CI->config->item('anonymous_grant_ttl', 'app');
		$this->revoke_session_grant();
		db_must($this->CI->db->insert('anonymous_access_grants', array(
			'ticket_id' => (int) $ticket->id,
			'session_fingerprint' => hash('sha256', $token),
			'secret_version' => 1,
			'created_at' => utc_now(),
			'expires_at' => $this->CI->clock->plus_seconds($ttl),
		)), 'anonymous_access_grants');
		$this->CI->session->set_userdata('anon_grant', array(
			'id' => (int) $this->CI->db->insert_id(),
			'token' => $token,
			'code' => $ticket->public_code,
		));
		return TRUE;
	}

	/** Tiket yang boleh diakses sesi pelacakan saat ini, atau NULL. */
	public function granted_ticket()
	{
		$grant = $this->CI->session->userdata('anon_grant');
		if ( ! is_array($grant) OR empty($grant['id']))
		{
			return NULL;
		}
		$row = $this->CI->db->get_where('anonymous_access_grants', array('id' => (int) $grant['id']))->row();
		if ( ! $row OR $row->revoked_at !== NULL
			OR strtotime($row->expires_at.' UTC') < $this->CI->clock->timestamp()
			OR ! hash_equals($row->session_fingerprint, hash('sha256', (string) $grant['token'])))
		{
			$this->CI->session->unset_userdata('anon_grant');
			return NULL;
		}
		$this->CI->load->model('Ticket_model', 'tickets');
		return $this->CI->tickets->find($row->ticket_id);
	}

	public function revoke_session_grant()
	{
		$grant = $this->CI->session->userdata('anon_grant');
		if (is_array($grant) && ! empty($grant['id']))
		{
			$this->CI->db->where('id', (int) $grant['id'])->where('revoked_at IS NULL', NULL, FALSE)
				->update('anonymous_access_grants', array('revoked_at' => utc_now()));
		}
		$this->CI->session->unset_userdata('anon_grant');
	}

	public function purge_expired_grants()
	{
		$this->CI->db->where('expires_at <', $this->CI->clock->plus_seconds(-86400))->delete('anonymous_access_grants');
		return $this->CI->db->affected_rows();
	}

	// ------------------------------------------------------------ Receipt

	/**
	 * Simpan bukti penerimaan di sesi untuk ditampilkan sekali (TTL pendek).
	 * Kode asli tidak pernah masuk URL, database plaintext, atau log.
	 */
	public function store_receipt($ticket, $access_code, $idempotency_key = NULL)
	{
		$this->CI->session->set_userdata('ticket_receipt', array(
			'code' => $ticket->public_code,
			'access_code' => $access_code,
			'submitted_at' => $ticket->submitted_at,
			'report_type' => $ticket->report_type,
			'expires' => $this->CI->clock->timestamp() + (int) $this->CI->config->item('receipt_ttl', 'app'),
			'idempotency_key' => $idempotency_key,
		));
	}

	public function receipt()
	{
		$receipt = $this->CI->session->userdata('ticket_receipt');
		if ( ! is_array($receipt) OR $receipt['expires'] < $this->CI->clock->timestamp())
		{
			$this->clear_receipt();
			return NULL;
		}
		return $receipt;
	}

	public function clear_receipt()
	{
		$this->CI->session->unset_userdata('ticket_receipt');
	}
}
