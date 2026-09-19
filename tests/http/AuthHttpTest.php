<?php

require_once __DIR__.'/HttpTestCase.php';

/** AUTH-01, AUTH-02, AUTH-04, AUTH-05, AUTH-06, SEC-02 melalui HTTP. */
class AuthHttpTest extends HttpTestCase {

	const PASSWORD = 'KataSandiUjiCoba2026';

	protected function setUp(): void
	{
		parent::setUp();
		$this->truncate_service_data();
	}

	public function test_register_pending_then_active_login_logout(): void
	{
		$r = $this->post_form('daftar', 'daftar', array(
			'display_name' => 'Warga HTTP', 'username' => 'warga.http.test', 'email' => '', 'phone' => '',
			'password' => 'pagi cerah di lereng bukit', 'password_confirm' => 'pagi cerah di lereng bukit', 'agree' => '1', 'website' => '',
		), 'reg');
		$this->assertSame(303, $r['status']);
		$this->assertStringContainsString('daftar/berhasil', $r['location']);

		// Akun pending belum dapat masuk ke dashboard.
		$login = $this->login('warga.http.test', 'pagi cerah di lereng bukit', 'reg');
		$this->assertSame(403, $login['status']);
		$this->assertStringContainsString('belum aktif', $login['body']);
		$this->assertSame(303, $this->get('warga', 'reg')['status']);

		$user = $this->CI->user_model->find_by_username('warga.http.test');
		$this->CI->user_model->update($user->id, array('account_status' => 'active'));

		$before = $this->session_cookie('reg');
		$login = $this->login('warga.http.test', 'pagi cerah di lereng bukit', 'reg');
		$this->assertSame(303, $login['status']);
		$this->assertStringEndsWith('/warga', $login['location']);
		$this->assertNotSame($before, $this->session_cookie('reg'), 'Session ID berganti saat login (anti fixation)');
		$dash = $this->get('warga', 'reg');
		$this->assertSame(200, $dash['status']);
		$this->assertStringContainsString('no-store', implode(',', $dash['headers']['cache-control'] ?? array()));

		// Logout hanya via POST ber-CSRF.
		$this->assertSame(404, $this->get('keluar', 'reg')['status']);
		$logout = $this->post_form('warga', 'keluar', array(), 'reg');
		$this->assertSame(303, $logout['status']);
		$this->assertSame(303, $this->get('warga', 'reg')['status']);
	}

	public function test_csrf_is_required_for_mutations(): void
	{
		$this->get('masuk', 'nocsrf');
		$login = $this->request('POST', 'masuk', 'nocsrf', http_build_query(array('identifier' => 'x', 'password' => 'y')));
		$this->assertSame(403, $login['status']);
		$lapor = $this->request('POST', 'lapor', 'nocsrf', http_build_query(array('title' => 'x')));
		$this->assertSame(403, $lapor['status']);
		$ajax = $this->request('POST', 'lacak', 'nocsrf', http_build_query(array('csrf_chw' => str_repeat('a', 32))), array('Accept: application/json', 'X-Requested-With: XMLHttpRequest'));
		$this->assertSame(403, $ajax['status']);
		$payload = json_decode($ajax['body'], TRUE);
		$this->assertSame('csrf_invalid', $payload['code']);
		$this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $payload['csrf']['hash'], 'Token baru disertakan untuk pemulihan');
		$refresh = $this->get('csrf-token', 'nocsrf', array('Accept: application/json'));
		$this->assertSame(200, $refresh['status']);
	}

	public function test_bruteforce_is_throttled_without_blocking_other_accounts(): void
	{
		$this->make_user('target.http.test', array('resident'));
		$this->make_user('lain.http.test', array('resident'));
		for ($i = 0; $i < 5; $i++)
		{
			$this->assertSame(401, $this->login('target.http.test', 'salah-password-'.$i, 'brute')['status']);
		}
		$blocked = $this->login('target.http.test', self::PASSWORD, 'brute');
		$this->assertSame(429, $blocked['status'], 'Identifier terkunci sementara, bahkan dengan password benar');
		$other = $this->login('lain.http.test', self::PASSWORD, 'brute2');
		$this->assertSame(303, $other['status'], 'Akun lain dari jaringan yang sama tetap bisa masuk');
		// Pesan generik: tidak membedakan akun ada/tidak.
		$unknown = $this->login('tidak.ada.test', 'apapun-juga-panjang', 'brute3');
		$this->assertSame(401, $unknown['status']);
		$this->assertStringContainsString('tidak sesuai', $unknown['body']);
	}

	public function test_revoked_and_suspended_sessions_end(): void
	{
		$user = $this->make_user('sesi.http.test', array('resident'));
		$this->assertSame(303, $this->login('sesi.http.test', self::PASSWORD, 'a')['status']);
		$this->assertSame(303, $this->login('sesi.http.test', self::PASSWORD, 'b')['status']);
		$this->assertSame(200, $this->get('warga', 'a')['status']);

		// Cabut sesi perangkat B dari perangkat A.
		$sessions = $this->CI->db->where('user_id', $user->id)->order_by('id')->get('user_sessions')->result();
		$page = $this->get('warga/akun', 'a');
		$revoke = $this->request('POST', 'warga/akun/sesi', 'a', http_build_query(array('csrf_chw' => $this->csrf_from($page['body']), 'session_id' => $sessions[1]->id)));
		$this->assertSame(303, $revoke['status']);
		$this->assertSame(303, $this->get('warga', 'b')['status'], 'Sesi yang dicabut tidak dapat melanjutkan akses');
		$this->assertSame(200, $this->get('warga', 'a')['status']);

		// Penangguhan akun mengakhiri sesi aktif.
		$this->CI->user_model->update($user->id, array('account_status' => 'suspended'));
		$this->assertSame(303, $this->get('warga', 'a')['status']);
		$login = $this->login('sesi.http.test', self::PASSWORD, 'c');
		$this->assertSame(403, $login['status']);
	}

	public function test_mfa_pre_auth_cannot_access_dashboard(): void
	{
		$user = $this->make_user('mfa.http.test', array('service_admin'));
		$secret = $this->CI->auth->mfa_begin_setup($user->id);
		$code = $this->CI->totp->code_at($this->CI->totp->base32_decode($secret), time());
		$this->CI->auth->mfa_confirm_setup($user->id, $code);

		$login = $this->login('mfa.http.test', self::PASSWORD, 'mfa');
		$this->assertSame(303, $login['status']);
		$this->assertStringEndsWith('/mfa', $login['location']);
		$this->assertSame(303, $this->get('admin', 'mfa')['status'], 'Tahap pre-auth belum boleh membuka dashboard');

		$wrong = $this->post_form('mfa', 'mfa', array('code' => '000000'), 'mfa');
		$this->assertSame(401, $wrong['status']);

		// Kode berikutnya (langkah waktu berbeda dari kode aktivasi).
		$next = $this->CI->totp->code_at($this->CI->totp->base32_decode($secret), time() + 30);
		$ok = $this->post_form('mfa', 'mfa', array('code' => $next), 'mfa');
		$this->assertSame(303, $ok['status']);
		$this->assertSame(200, $this->get('admin', 'mfa')['status']);
	}

	public function test_staff_registers_resident_without_role_escalation(): void
	{
		$this->make_user('superadmin.http.test', array('super_admin'));
		$this->assertSame(303, $this->login('superadmin.http.test', self::PASSWORD, 'sa')['status']);
		$create = $this->post_form('admin/pengguna/buat', 'admin/pengguna/buat', array(
			'display_name' => 'Warga Loket HTTP', 'username' => 'loket.warga.test', 'email' => '', 'phone' => '',
			// Field tambahan dari peramban tidak boleh menyisipkan role/status.
			'role' => 'super_admin', 'account_status' => 'active',
		), 'sa');
		$this->assertSame(303, $create['status']);
		$user = $this->CI->user_model->find_by_username('loket.warga.test');
		$this->assertSame('pending_activation', $user->account_status);
		$this->assertSame(array('resident'), $this->CI->user_model->role_codes($user->id));
		$detail = $this->get(parse_url($create['location'], PHP_URL_PATH), 'sa');
		$this->assertStringContainsString('Kode aktivasi (tampil sekali)', $detail['body']);
		$again = $this->get(parse_url($create['location'], PHP_URL_PATH), 'sa');
		$this->assertStringNotContainsString('Kode aktivasi (tampil sekali)', $again['body'], 'Kode tidak ditampilkan ulang');
	}
}
