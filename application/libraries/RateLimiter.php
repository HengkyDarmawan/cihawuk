<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pembatas laju berbasis database (fixed window + blokir sementara).
 * Identifier (IP, username) di-HMAC dengan key khusus limiter; tidak disimpan polos.
 */
class RateLimiter {

	/** @var CI_Controller */
	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->config->load('app', TRUE);
	}

	protected function rule($bucket)
	{
		$rules = $this->CI->config->item('rate_limits', 'app');
		if ( ! isset($rules[$bucket]))
		{
			throw new InvalidArgumentException('Unknown rate limit bucket: '.$bucket);
		}
		$rule = $rules[$bucket];
		// Override dari pengaturan aplikasi bila ada.
		if (isset($this->CI->settings))
		{
			$override = $this->CI->settings->get('rate_limits.'.$bucket);
			if (is_array($override))
			{
				$rule = array_merge($rule, array_intersect_key($override, $rule));
			}
		}
		return array_map('intval', $rule);
	}

	public function key($bucket, $identifier)
	{
		return $this->CI->crypto->hmac($bucket.'|'.mb_strtolower((string) $identifier), 'rate_limit');
	}

	/** Cek tanpa menambah hitungan. @return int detik sampai boleh mencoba (0 = boleh) */
	public function retry_after($bucket, $identifier)
	{
		$rule = $this->rule($bucket);
		$row = $this->CI->db->get_where('rate_limits', array('bucket_key' => $this->key($bucket, $identifier)))->row();
		if ( ! $row)
		{
			return 0;
		}
		$now = $this->CI->clock->timestamp();
		if ($row->blocked_until !== NULL && strtotime($row->blocked_until.' UTC') > $now)
		{
			return strtotime($row->blocked_until.' UTC') - $now;
		}
		$window_end = strtotime($row->window_start.' UTC') + $rule['window'];
		if ($window_end > $now && (int) $row->hits >= $rule['limit'])
		{
			return $window_end - $now;
		}
		return 0;
	}

	public function too_many($bucket, $identifier)
	{
		return $this->retry_after($bucket, $identifier) > 0;
	}

	/**
	 * Tambah satu hitungan secara atomik. Bila batas terlampaui, pasang blokir sementara.
	 * @return int detik sampai boleh mencoba lagi (0 = masih dalam batas)
	 */
	public function hit($bucket, $identifier)
	{
		$rule = $this->rule($bucket);
		$key = $this->key($bucket, $identifier);
		$now = $this->CI->clock->now();
		$now_s = $now->format('Y-m-d H:i:s');
		$window_floor = $now->modify('-'.$rule['window'].' seconds')->format('Y-m-d H:i:s');
		$expires = $now->modify('+'.max($rule['window'], $rule['block']).' seconds')->format('Y-m-d H:i:s');

		// Urutan assignment penting: hits dihitung dari window_start lama.
		$this->CI->db->query(
			'INSERT INTO rate_limits (bucket_key, window_start, hits, blocked_until, expires_at) VALUES (?, ?, 1, NULL, ?)
			 ON DUPLICATE KEY UPDATE
				hits = IF(window_start <= ?, 1, hits + 1),
				window_start = IF(window_start <= ?, ?, window_start),
				blocked_until = IF(blocked_until IS NOT NULL AND blocked_until <= ?, NULL, blocked_until),
				expires_at = GREATEST(expires_at, ?)',
			array($key, $now_s, $expires, $window_floor, $window_floor, $now_s, $now_s, $expires)
		);

		$row = $this->CI->db->get_where('rate_limits', array('bucket_key' => $key))->row();
		if ( ! $row)
		{
			return 0;
		}
		if ((int) $row->hits > $rule['limit'] && $row->blocked_until === NULL)
		{
			$until = $now->modify('+'.$rule['block'].' seconds')->format('Y-m-d H:i:s');
			$this->CI->db->query('UPDATE rate_limits SET blocked_until = ?, expires_at = GREATEST(expires_at, ?) WHERE bucket_key = ?', array($until, $until, $key));
			return $rule['block'];
		}
		return $this->retry_after($bucket, $identifier);
	}

	/**
	 * Untuk operasi yang setiap percobaannya dihitung: kembalikan detik tunggu bila
	 * sudah melampaui batas, atau 0 lalu hitungan bertambah.
	 */
	public function attempt($bucket, $identifier)
	{
		$wait = $this->retry_after($bucket, $identifier);
		if ($wait > 0)
		{
			return $wait;
		}
		$this->hit($bucket, $identifier);
		return 0;
	}

	public function clear($bucket, $identifier)
	{
		$this->CI->db->delete('rate_limits', array('bucket_key' => $this->key($bucket, $identifier)));
	}

	public function purge_expired()
	{
		$this->CI->db->where('expires_at <', utc_now())->delete('rate_limits');
		return $this->CI->db->affected_rows();
	}
}
