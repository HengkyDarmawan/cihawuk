<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Idempotency key per submission, disimpan server-side.
 * Key yang sama + payload sama => hasil yang sama; payload berbeda => konflik (409).
 */
class Idempotency {

	const NEW_REQUEST = 'new';
	const REPLAY = 'replay';
	const CONFLICT = 'conflict';
	const IN_PROGRESS = 'in_progress';

	/** @var CI_Controller */
	protected $CI;

	public $ttl_seconds = 86400;

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	public function valid_key($key)
	{
		return is_string($key) && preg_match('/^[a-f0-9]{32,64}$/', $key);
	}

	public function request_hash(array $payload)
	{
		ksort($payload);
		return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	/**
	 * Mulai request idempotent. Harus dipanggil di dalam transaction bisnis agar
	 * baris ikut rollback bila penyimpanan gagal.
	 *
	 * @return array{state:string,id:int|null,result_type:?string,result_id:?int}
	 */
	public function begin($action, $actor_scope, $key, $request_hash)
	{
		$scope_hash = hash('sha256', (string) $actor_scope);
		$key_hash = hash('sha256', (string) $key);
		$now = utc_now();
		// INSERT IGNORE: duplikat tidak dianggap query gagal sehingga transaction tidak rusak.
		$ok = $this->CI->db->query(
			'INSERT IGNORE INTO idempotency_keys (actor_scope_hash, action, key_hash, request_hash, state, created_at, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
			array($scope_hash, $action, $key_hash, $request_hash, 'processing', $now, $this->CI->clock->plus_seconds($this->ttl_seconds))
		);
		if ( ! $ok)
		{
			throw new RuntimeException('Idempotency key could not be stored.');
		}
		if ($this->CI->db->affected_rows() === 1)
		{
			return array('state' => self::NEW_REQUEST, 'id' => (int) $this->CI->db->insert_id(), 'result_type' => NULL, 'result_id' => NULL);
		}

		$row = $this->CI->db->get_where('idempotency_keys', array(
			'actor_scope_hash' => $scope_hash, 'action' => $action, 'key_hash' => $key_hash,
		))->row();
		if ( ! $row)
		{
			throw new RuntimeException('Idempotency key could not be stored.');
		}
		if ( ! hash_equals($row->request_hash, $request_hash))
		{
			return array('state' => self::CONFLICT, 'id' => (int) $row->id, 'result_type' => NULL, 'result_id' => NULL);
		}
		if ($row->state === 'completed')
		{
			return array('state' => self::REPLAY, 'id' => (int) $row->id, 'result_type' => $row->result_type, 'result_id' => (int) $row->result_id);
		}
		return array('state' => self::IN_PROGRESS, 'id' => (int) $row->id, 'result_type' => NULL, 'result_id' => NULL);
	}

	public function complete($id, $result_type, $result_id)
	{
		$this->CI->db->where('id', (int) $id)->update('idempotency_keys', array(
			'state' => 'completed', 'result_type' => $result_type, 'result_id' => (int) $result_id,
		));
	}

	/** Cari hasil selesai dalam scope yang sama (pemulihan setelah koneksi putus). */
	public function find_completed($action, $actor_scope, $key)
	{
		$row = $this->CI->db->get_where('idempotency_keys', array(
			'actor_scope_hash' => hash('sha256', (string) $actor_scope),
			'action' => $action,
			'key_hash' => hash('sha256', (string) $key),
			'state' => 'completed',
		))->row();
		return $row ? array('result_type' => $row->result_type, 'result_id' => (int) $row->result_id) : NULL;
	}

	public function purge_expired()
	{
		$this->CI->db->where('expires_at <', utc_now())->delete('idempotency_keys');
		return $this->CI->db->affected_rows();
	}
}
