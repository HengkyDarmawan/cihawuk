<?php

class TotpTest extends CiTestCase {

	/** Test vector RFC 6238 (SHA1, secret "12345678901234567890"). */
	public function test_rfc6238_vectors(): void
	{
		$secret = '12345678901234567890';
		$cases = array(59 => '94287082', 1111111109 => '07081804', 1111111111 => '14050471', 1234567890 => '89005924', 2000000000 => '69279037');
		foreach ($cases as $time => $expected)
		{
			$this->assertSame($expected, $this->CI->totp->code_at($secret, $time, 'sha1', 8), 'time '.$time);
		}
	}

	public function test_verify_accepts_window_and_blocks_replay(): void
	{
		$secret = $this->CI->totp->generate_secret();
		$binary = $this->CI->totp->base32_decode($secret);
		$now = 1_800_000_000;
		$code = $this->CI->totp->code_at($binary, $now);
		$step = $this->CI->totp->verify($secret, $code, $now);
		$this->assertIsInt($step);
		// Kode yang sama tidak boleh dipakai ulang.
		$this->assertFalse($this->CI->totp->verify($secret, $code, $now, $step));
		$this->assertFalse($this->CI->totp->verify($secret, '000000x', $now));
	}

	public function test_base32_roundtrip(): void
	{
		$bytes = random_bytes(20);
		$this->assertSame($bytes, $this->CI->totp->base32_decode($this->CI->totp->base32_encode($bytes)));
	}
}
