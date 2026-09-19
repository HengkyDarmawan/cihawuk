<?php

class CryptoAndHelpersTest extends CiTestCase {

	public function test_encrypt_decrypt_roundtrip_and_tamper_detection(): void
	{
		$cipher = $this->CI->crypto->encrypt('Nama Pelapor Rahasia');
		$this->assertStringStartsWith('v1:', $cipher);
		$this->assertStringNotContainsString('Rahasia', $cipher);
		$this->assertSame('Nama Pelapor Rahasia', $this->CI->crypto->decrypt($cipher));

		$raw = base64_decode(substr($cipher, 3));
		$raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 1);
		$this->expectException(RuntimeException::class);
		$this->CI->crypto->decrypt('v1:'.base64_encode($raw));
	}

	public function test_code_normalization(): void
	{
		$this->assertSame('ABC101', $this->CI->crypto->normalize_code('abc-iOl '));
		$this->assertSame('', $this->CI->crypto->normalize_code('<script>'));
		$this->assertSame(26, strlen($this->CI->crypto->base32_encode(random_bytes(16))));
	}

	public function test_safe_redirect_path_blocks_open_redirect(): void
	{
		$this->assertSame('/warga/laporan', app_safe_redirect_path('/warga/laporan'));
		$this->assertSame('/', app_safe_redirect_path('//evil.test/warga'));
		$this->assertSame('/', app_safe_redirect_path('https://evil.test/admin'));
		$this->assertSame('/', app_safe_redirect_path('/\\evil.test'));
		$this->assertSame('/', app_safe_redirect_path('/berita'));
	}

	public function test_safe_url(): void
	{
		$this->assertTrue(app_is_safe_url('/berita'));
		$this->assertTrue(app_is_safe_url('https://contoh.test/x'));
		$this->assertFalse(app_is_safe_url('javascript:alert(1)'));
		$this->assertFalse(app_is_safe_url('//evil.test'));
		$this->assertFalse(app_is_safe_url("java\nscript:alert(1)"));
		$this->assertSame('#', nav_href('javascript:alert(1)'));
	}

	public function test_formatting(): void
	{
		$this->assertSame('6.809', format_number_id(6809));
		$this->assertSame('932,35', format_number_id(932.35, 2));
		// 2026-09-15 17:00 UTC = 16 September 00.00 WIB
		$this->assertSame('16 September 2026, 00.00 WIB', format_wib('2026-09-15 17:00:00'));
		$this->assertSame('2026-09-15 03:30:00', local_to_utc('2026-09-15T10:30'));
		$this->assertSame('&lt;b&gt;', e('<b>'));
	}

	public function test_password_policy(): void
	{
		$this->assertNotNull($this->CI->auth->password_policy_error('pendek'));
		$this->assertNotNull($this->CI->auth->password_policy_error('password1234'));
		$this->assertNotNull($this->CI->auth->password_policy_error('budi.santoso-2026', 'budi.santoso'));
		$this->assertNull($this->CI->auth->password_policy_error('kebun kentang di kertasari'));
	}

	public function test_csv_formula_injection_is_neutralized(): void
	{
		$this->assertSame("'=HYPERLINK(\"x\")", $this->CI->exports->safe_cell('=HYPERLINK("x")'));
		$this->assertSame("'+1", $this->CI->exports->safe_cell('+1'));
		$this->assertSame("'-2", $this->CI->exports->safe_cell('-2'));
		$this->assertSame("'@SUM(A1)", $this->CI->exports->safe_cell('@SUM(A1)'));
		$this->assertSame("'\tx", $this->CI->exports->safe_cell("\tx"));
		$this->assertSame('Normal', $this->CI->exports->safe_cell('Normal'));
	}

	public function test_html_sanitizer_strips_active_content(): void
	{
		$clean = $this->CI->content_service->sanitize_html('<p onclick="x()">Hai</p><script>alert(1)</script><iframe src="//x"></iframe><a href="javascript:alert(1)">t</a><img src="https://evil.test/a.png">');
		$this->assertStringNotContainsString('script', $clean);
		$this->assertStringNotContainsString('onclick', $clean);
		$this->assertStringNotContainsString('iframe', $clean);
		$this->assertStringNotContainsString('javascript', $clean);
		$this->assertStringNotContainsString('evil.test', $clean);
		$this->assertStringContainsString('<p>Hai</p>', $clean);
	}
}
