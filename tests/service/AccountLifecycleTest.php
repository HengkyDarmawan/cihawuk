<?php

/** AUTH-01..03, AUTH-06 (sisi service) dan aturan Super Admin terakhir. */
class AccountLifecycleTest extends CiTestCase {

	protected function setUp(): void
	{
		parent::setUp();
		$this->truncate_service_data();
		$this->CI->mailer->capture = FALSE;
		$this->CI->mailer->captured = array();
	}

	public function test_self_registration_pending_then_manual_activation(): void
	{
		$result = $this->CI->auth->register_self(array(
			'display_name' => 'Warga Daftar Mandiri',
			'username' => 'daftar.mandiri.test',
			'email' => '',
			'phone' => '081234567890',
			'password' => 'kebun kentang di kertasari',
			'password_confirm' => 'kebun kentang di kertasari',
		));
		$this->assertSame('manual_review', $result['activation']);
		$user = $this->CI->user_model->find($result['user_id']);
		$this->assertSame('pending_activation', $user->account_status);
		$this->assertSame('+6281234567890', $user->phone);
		$this->assertSame(array('resident'), $this->CI->user_model->role_codes($user->id));
		$this->assertTrue(password_verify('kebun kentang di kertasari', $user->password_hash));

		$admin = $this->make_user('verifikator.acc.test', array('service_admin'));
		$this->CI->auth->activate_manually($user->id, $admin->id, 'Diperiksa di kantor desa');
		$this->assertSame('active', $this->CI->user_model->find($user->id)->account_status);
	}

	public function test_registration_validation_and_uniqueness(): void
	{
		$this->make_user('sudah.ada.test', array('resident'));
		$errors = $this->CI->auth->validate_account_fields(array(
			'display_name' => 'A', 'username' => 'SUDAH.ADA.TEST', 'email' => 'bukan-email',
			'phone' => '12', 'password' => 'pendek', 'password_confirm' => 'x',
		));
		$this->assertArrayHasKey('display_name', $errors);
		$this->assertArrayHasKey('username', $errors, 'Username dinormalisasi huruf kecil lalu dicek unik');
		$this->assertArrayHasKey('email', $errors);
		$this->assertArrayHasKey('phone', $errors);
		$this->assertArrayHasKey('password', $errors);
	}

	public function test_staff_created_resident_activates_with_own_password(): void
	{
		$staff = $this->make_user('loket.acc.test', array('front_desk'));
		$out = $this->CI->auth->create_resident_by_staff(array('display_name' => 'Warga Loket', 'username' => 'warga.loket.test'), $staff->id);
		$user = $this->CI->user_model->find($out['user_id']);
		$this->assertNull($user->password_hash, 'Tidak ada password seragam');
		$this->assertSame(array('resident'), $this->CI->user_model->role_codes($user->id));

		// Token tidak dikonsumsi saat hanya diperiksa (GET).
		$this->assertNotNull($this->CI->auth->peek_token($out['token'], 'activation'));
		$this->assertNotNull($this->CI->auth->peek_token($out['token'], 'activation'));

		$activated = $this->CI->auth->activate_with_token($out['token'], 'sawah hijau di lereng gunung', 'sawah hijau di lereng gunung');
		$this->assertSame('active', $activated->account_status);
		$this->expectException(DomainRuleException::class);
		$this->CI->auth->activate_with_token($out['token'], 'sawah hijau di lereng gunung', 'sawah hijau di lereng gunung');
	}

	public function test_reset_token_expiry_single_use_and_session_revocation(): void
	{
		$user = $this->make_user('reset.acc.test', array('resident'));
		$this->CI->clock->set('2026-09-20 00:00:00');
		$token = $this->CI->auth->create_token($user->id, 'reset', 1800, 'email', NULL);

		// Kedaluwarsa setelah 30 menit.
		$this->CI->clock->set('2026-09-20 00:31:00');
		$this->assertNull($this->CI->auth->peek_token($token, 'reset'));

		$this->CI->clock->set('2026-09-20 00:10:00');
		$this->CI->db->insert('user_sessions', array(
			'user_id' => $user->id, 'session_fingerprint' => hash('sha256', 'sesi-lama'), 'auth_version' => $user->auth_version,
			'area' => 'resident', 'created_at' => utc_now(), 'last_seen_at' => utc_now(), 'expires_at' => '2026-09-21 00:00:00',
		));
		$this->CI->auth->reset_password($token, 'bukit teh pagi berkabut', 'bukit teh pagi berkabut');
		$fresh = $this->CI->user_model->find($user->id);
		$this->assertTrue(password_verify('bukit teh pagi berkabut', $fresh->password_hash));
		$this->assertGreaterThan((int) $user->auth_version, (int) $fresh->auth_version);
		$this->assertSame(0, $this->CI->db->where('user_id', $user->id)->where('revoked_at IS NULL', NULL, FALSE)->count_all_results('user_sessions'));

		// Token sekali pakai.
		$this->expectException(DomainRuleException::class);
		$this->CI->auth->reset_password($token, 'bukit teh pagi berkabut lagi', 'bukit teh pagi berkabut lagi');
	}

	public function test_tokens_never_logged_in_plaintext(): void
	{
		$user = $this->make_user('log.acc.test', array('resident'));
		$token = $this->CI->auth->create_token($user->id, 'reset', 1800, 'email', NULL);
		$row = $this->CI->db->get_where('account_tokens', array('user_id' => $user->id))->row();
		$this->assertStringNotContainsString(explode('.', $token)[1], $row->token_hash);
		$this->assertSame(0, $this->CI->db->like('safe_metadata_json', explode('.', $token)[1])->count_all_results('audit_logs'));
		$log_files = glob(ROOTPATH.'storage/logs/*.log') ?: array();
		foreach ($log_files as $file)
		{
			$this->assertStringNotContainsString(explode('.', $token)[1], (string) file_get_contents($file));
		}
	}

	public function test_mfa_setup_and_recovery_codes_single_use(): void
	{
		$user = $this->make_user('mfa.acc.test', array('service_admin'));
		$secret = $this->CI->auth->mfa_begin_setup($user->id);
		$stored = $this->CI->db->get_where('user_mfa', array('user_id' => $user->id))->row();
		$this->assertStringNotContainsString($secret, $stored->secret_ciphertext);

		$this->CI->clock->set('2026-09-20 00:00:00');
		$code = $this->CI->totp->code_at($this->CI->totp->base32_decode($secret), $this->CI->clock->timestamp());
		$recovery = $this->CI->auth->mfa_confirm_setup($user->id, $code);
		$this->assertCount(10, $recovery);
		$this->assertTrue($this->CI->auth->mfa_enabled($user->id));

		// Kode TOTP yang sama tidak dapat dipakai ulang.
		$this->assertFalse($this->CI->auth->mfa_verify($user->id, $code));
		$this->CI->clock->set('2026-09-20 00:01:00');
		$next = $this->CI->totp->code_at($this->CI->totp->base32_decode($secret), $this->CI->clock->timestamp());
		$this->assertTrue($this->CI->auth->mfa_verify($user->id, $next));

		$this->assertTrue($this->CI->auth->mfa_verify($user->id, strtolower($recovery[0])));
		$this->assertFalse($this->CI->auth->mfa_verify($user->id, $recovery[0]), 'Kode pemulihan sekali pakai');
		$this->assertSame(9, $this->CI->auth->remaining_recovery_codes($user->id));
	}

	public function test_last_super_admin_count(): void
	{
		$super = $this->make_user('super.acc.test', array('super_admin'));
		$this->assertSame(0, $this->CI->user_model->count_active_super_admins($super->id));
		$second = $this->make_user('super2.acc.test', array('super_admin'));
		$this->assertSame(1, $this->CI->user_model->count_active_super_admins($super->id));
	}

	public function test_email_activation_when_smtp_enabled(): void
	{
		$this->CI->mailer->capture = TRUE;
		$result = $this->CI->auth->register_self(array(
			'display_name' => 'Warga Email', 'username' => 'warga.email.test', 'email' => 'warga.email@contoh.test',
			'password' => 'ladang wortel pagi hari', 'password_confirm' => 'ladang wortel pagi hari',
		));
		$this->CI->mailer->capture = FALSE;
		$this->assertSame('email_sent', $result['activation']);
		$this->assertCount(1, $this->CI->mailer->captured);
		$this->assertStringContainsString('aktivasi?token=', $this->CI->mailer->captured[0]['text']);
	}
}
