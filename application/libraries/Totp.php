<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * TOTP RFC 6238 (HMAC-SHA1, 6 digit, periode 30 detik) untuk MFA.
 * Diuji dengan test vector RFC 6238 pada tests/unit/TotpTest.php.
 */
class Totp {

	const RFC4648 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

	public $digits = 6;
	public $period = 30;
	public $window = 1;

	public function generate_secret($bytes = 20)
	{
		return $this->base32_encode(random_bytes($bytes));
	}

	public function base32_encode($bytes)
	{
		$bits = '';
		foreach (str_split($bytes) as $c)
		{
			$bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
		}
		$out = '';
		foreach (str_split($bits, 5) as $chunk)
		{
			$out .= self::RFC4648[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
		}
		return $out;
	}

	public function base32_decode($secret)
	{
		$secret = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', (string) $secret));
		$bits = '';
		foreach (str_split($secret) as $c)
		{
			$pos = strpos(self::RFC4648, $c);
			if ($pos === FALSE)
			{
				return '';
			}
			$bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
		}
		$out = '';
		foreach (str_split($bits, 8) as $byte)
		{
			if (strlen($byte) === 8)
			{
				$out .= chr(bindec($byte));
			}
		}
		return $out;
	}

	public function code_at($binary_secret, $timestamp, $algo = 'sha1', $digits = NULL)
	{
		$digits = ($digits === NULL) ? $this->digits : (int) $digits;
		$counter = intdiv((int) $timestamp, $this->period);
		return $this->hotp($binary_secret, $counter, $algo, $digits);
	}

	public function hotp($binary_secret, $counter, $algo = 'sha1', $digits = 6)
	{
		$bin_counter = pack('N*', 0, $counter);
		$hash = hash_hmac($algo, $bin_counter, $binary_secret, TRUE);
		$offset = ord($hash[strlen($hash) - 1]) & 0x0F;
		$value = ((ord($hash[$offset]) & 0x7F) << 24)
			| ((ord($hash[$offset + 1]) & 0xFF) << 16)
			| ((ord($hash[$offset + 2]) & 0xFF) << 8)
			| (ord($hash[$offset + 3]) & 0xFF);
		return str_pad((string) ($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
	}

	/**
	 * Verifikasi kode dengan toleransi ±window langkah. Mengembalikan nomor langkah
	 * yang cocok (untuk mencegah pemakaian ulang) atau FALSE.
	 */
	public function verify($base32_secret, $code, $timestamp, $last_used_step = NULL)
	{
		$code = preg_replace('/\s+/', '', (string) $code);
		if ( ! preg_match('/^\d{'.$this->digits.'}$/', $code))
		{
			return FALSE;
		}
		$secret = $this->base32_decode($base32_secret);
		if ($secret === '')
		{
			return FALSE;
		}
		$step = intdiv((int) $timestamp, $this->period);
		for ($i = -$this->window; $i <= $this->window; $i++)
		{
			$candidate = $step + $i;
			if ($last_used_step !== NULL && $candidate <= (int) $last_used_step)
			{
				continue;
			}
			if (hash_equals($this->hotp($secret, $candidate), $code))
			{
				return $candidate;
			}
		}
		return FALSE;
	}

	public function provisioning_uri($label, $issuer, $base32_secret)
	{
		return 'otpauth://totp/'.rawurlencode($issuer.':'.$label)
			.'?secret='.rawurlencode($base32_secret)
			.'&issuer='.rawurlencode($issuer)
			.'&algorithm=SHA1&digits='.$this->digits.'&period='.$this->period;
	}
}
