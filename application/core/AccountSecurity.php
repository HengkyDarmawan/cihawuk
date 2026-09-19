<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Aksi keamanan akun yang sama untuk area warga dan pengelola:
 * ganti password, daftar sesi aktif, cabut sesi, MFA TOTP, dan reautentikasi.
 *
 * Dipakai sebagai trait agar kedua controller tetap memiliki guard area masing-masing.
 */
trait AccountSecurity {

	protected function account_area()
	{
		return ($this->layout_data['area'] ?? 'warga');
	}

	protected function account_base()
	{
		return ($this->account_area() === 'admin') ? 'admin/akun' : 'warga/akun';
	}

	public function index()
	{
		$this->render('shared/akun', $this->account_data(), 'dashboard');
	}

	protected function account_data(array $extra = array())
	{
		return array_merge(array(
			'page_title' => 'Keamanan Akun',
			'account' => $this->user,
			'sessions' => $this->auth->active_sessions($this->user->id),
			'current_session_id' => (int) $this->user->session_row_id,
			'mfa_enabled' => $this->auth->mfa_enabled($this->user->id),
			'recovery_left' => $this->auth->remaining_recovery_codes($this->user->id),
			'base' => $this->account_base(),
			'reauth_ok' => $this->auth->recently_reauthenticated(),
			'mail_enabled' => $this->mailer->enabled(),
		), $extra);
	}

	/** Konfirmasi ulang password untuk membuka tindakan sensitif. */
	public function konfirmasi()
	{
		$this->require_method('post');
		if ( ! $this->auth->reauthenticate((string) $this->input->post('password', FALSE)))
		{
			$this->flash('error', 'Password tidak sesuai atau terlalu banyak percobaan.');
		}
		else
		{
			$this->flash('success', 'Password terkonfirmasi. Tindakan sensitif terbuka selama 10 menit.');
		}
		$next = app_safe_redirect_path((string) $this->session->userdata('reauth_next'), '/'.$this->account_base());
		$this->session->unset_userdata('reauth_next');
		redirect(site_url(ltrim($next, '/')), 'location', 303);
	}

	public function password()
	{
		$this->require_method('post');
		try
		{
			$this->auth->change_password(
				$this->user,
				(string) $this->input->post('current_password', FALSE),
				(string) $this->input->post('password', FALSE),
				(string) $this->input->post('password_confirm', FALSE)
			);
		}
		catch (DomainRuleException $e)
		{
			$this->form_errors = $e->errors;
			$this->output->set_status_header($e->http_status);
			$this->render('shared/akun', $this->account_data(array('password_error' => $e->getMessage())), 'dashboard');
			return;
		}
		$this->flash('success', 'Password berhasil diubah. Sesi lain telah dikeluarkan.');
		redirect(site_url($this->account_base()), 'location', 303);
	}

	public function sesi()
	{
		$this->require_method('post');
		$id = (int) $this->input->post('session_id');
		if ($id === (int) $this->user->session_row_id)
		{
			$this->flash('warning', 'Gunakan tombol Keluar untuk mengakhiri sesi yang sedang Anda pakai.');
		}
		elseif ($this->auth->revoke_session($this->user->id, $id))
		{
			$this->audit->log('auth.session_revoked', 'user', $this->user->public_id, array('session_id' => $id));
			$this->flash('success', 'Sesi perangkat lain dikeluarkan.');
		}
		else
		{
			$this->flash('warning', 'Sesi tidak ditemukan atau sudah berakhir.');
		}
		redirect(site_url($this->account_base()), 'location', 303);
	}

	public function sesi_semua()
	{
		$this->require_method('post');
		$this->auth->logout_all($this->user->id, 'user_request');
		$this->flash('success', 'Semua sesi dikeluarkan. Silakan masuk kembali.');
		redirect(site_url('masuk'), 'location', 303);
	}

	public function mfa_mulai()
	{
		$this->require_method('post');
		if ( ! $this->auth->recently_reauthenticated())
		{
			$this->session->set_userdata('reauth_next', '/'.$this->account_base());
			throw new DomainRuleException('Konfirmasi ulang password Anda sebelum mengatur MFA.', 428);
		}
		$secret = $this->auth->mfa_begin_setup($this->user->id);
		$this->render('shared/akun', $this->account_data(array(
			'mfa_secret' => $secret,
			'mfa_uri' => $this->totp->provisioning_uri($this->user->username, 'Desa Cihawuk', $secret),
		)), 'dashboard');
	}

	public function mfa_aktifkan()
	{
		$this->require_method('post');
		try
		{
			$codes = $this->auth->mfa_confirm_setup($this->user->id, (string) $this->input->post('code'));
		}
		catch (DomainRuleException $e)
		{
			$this->form_errors = $e->errors;
			$this->output->set_status_header($e->http_status);
			$secret = $this->auth->mfa_pending_secret($this->user->id);
			$this->render('shared/akun', $this->account_data(array(
				'mfa_error' => $e->getMessage(),
				'mfa_secret' => $secret,
				'mfa_uri' => $secret ? $this->totp->provisioning_uri($this->user->username, 'Desa Cihawuk', $secret) : NULL,
			)), 'dashboard');
			return;
		}
		$this->render('shared/akun', $this->account_data(array('recovery_codes' => $codes)), 'dashboard');
	}

	public function mfa_nonaktif()
	{
		$this->require_method('post');
		if ( ! $this->auth->recently_reauthenticated())
		{
			$this->session->set_userdata('reauth_next', '/'.$this->account_base());
			throw new DomainRuleException('Konfirmasi ulang password Anda sebelum menonaktifkan MFA.', 428);
		}
		$this->auth->mfa_disable($this->user->id, $this->user->id);
		$this->flash('success', 'MFA dinonaktifkan untuk akun Anda.');
		redirect(site_url($this->account_base()), 'location', 303);
	}
}
