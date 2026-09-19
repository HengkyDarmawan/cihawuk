<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Masuk, MFA, daftar mandiri, aktivasi, lupa/reset password dan keluar.
 */
class Auth extends Public_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->no_store = TRUE;
		$this->start_session();
		$this->load->model('User_model', 'user_model');
		$this->layout_data['noindex'] = TRUE;
	}

	protected function redirect_home_for($user)
	{
		$next = $this->session->userdata('login_next');
		$this->session->unset_userdata('login_next');
		$is_staff = $this->user_model->is_staff($user->id);
		$default = $is_staff ? '/admin' : '/warga';
		$next = app_safe_redirect_path((string) $next, $default);
		// Area harus sesuai jenis akun.
		if (strpos($next, '/admin') === 0 && ! $is_staff)
		{
			$next = '/warga';
		}
		if (strpos($next, '/warga') === 0 && ! in_array('resident', $this->user_model->role_codes($user->id), TRUE))
		{
			$next = $default;
		}
		redirect(site_url(ltrim($next, '/')), 'location', 303);
	}

	// ------------------------------------------------------------ Masuk

	public function login()
	{
		if ($user = $this->auth->user())
		{
			$this->redirect_home_for($user);
			return;
		}
		$this->render('auth/login', array('page_title' => 'Masuk'), 'site');
	}

	public function login_submit()
	{
		$this->require_method('post');
		$identifier = trim((string) $this->input->post('identifier'));
		$password = (string) $this->input->post('password', FALSE);
		$this->old_input = array('identifier' => $identifier);

		if ($identifier === '' OR $password === '')
		{
			$this->form_errors = array('identifier' => ($identifier === '') ? 'Username atau email wajib diisi.' : '', 'password' => ($password === '') ? 'Password wajib diisi.' : '');
			$this->form_errors = array_filter($this->form_errors);
			$this->output->set_status_header(422);
			$this->render('auth/login', array('page_title' => 'Masuk'), 'site');
			return;
		}

		$result = $this->auth->attempt_login($identifier, $password);
		switch ($result['status'])
		{
			case 'ok':
				$this->redirect_home_for($result['user']);
				return;
			case 'mfa_required':
				redirect(site_url('mfa'), 'location', 303);
				return;
			case 'throttled':
				$this->output->set_header('Retry-After: '.(int) $result['retry_after']);
				$this->output->set_status_header(429);
				$message = 'Terlalu banyak percobaan masuk. Tunggu sekitar '.max(1, (int) ceil($result['retry_after'] / 60)).' menit sebelum mencoba lagi.';
				break;
			case 'pending_activation':
				$this->output->set_status_header(403);
				$message = 'Akun Anda belum aktif. Buka tautan aktivasi yang dikirim ke email, atau hubungi kantor desa untuk review aktivasi. Selama menunggu, Anda tetap dapat membuat laporan tanpa akun.';
				break;
			case 'suspended':
				$this->output->set_status_header(403);
				$message = 'Akun Anda sedang ditangguhkan. Hubungi kantor desa untuk informasi lebih lanjut.';
				break;
			case 'closed':
				$this->output->set_status_header(403);
				$message = 'Akun ini sudah ditutup.';
				break;
			default:
				$this->output->set_status_header(401);
				$message = 'Username/email atau password tidak sesuai.';
		}
		$this->render('auth/login', array('page_title' => 'Masuk', 'login_error' => $message), 'site');
	}

	public function mfa()
	{
		if ( ! $this->auth->pre_auth_user())
		{
			redirect(site_url('masuk'), 'location', 303);
			return;
		}
		$this->render('auth/mfa', array('page_title' => 'Verifikasi dua langkah'), 'site');
	}

	public function mfa_submit()
	{
		$this->require_method('post');
		$result = $this->auth->complete_mfa_login((string) $this->input->post('code'));
		if ($result['status'] === 'ok')
		{
			$this->redirect_home_for($result['user']);
			return;
		}
		if ($result['status'] === 'expired')
		{
			$this->flash('warning', 'Sesi verifikasi berakhir. Silakan masuk kembali.');
			redirect(site_url('masuk'), 'location', 303);
			return;
		}
		$status = ($result['status'] === 'throttled') ? 429 : 401;
		$this->output->set_status_header($status);
		$this->render('auth/mfa', array(
			'page_title' => 'Verifikasi dua langkah',
			'mfa_error' => ($status === 429) ? 'Terlalu banyak percobaan. Coba lagi beberapa menit lagi.' : 'Kode tidak sesuai. Gunakan kode 6 digit terbaru atau salah satu kode pemulihan.',
		), 'site');
	}

	public function logout()
	{
		$this->require_method('post');
		$this->auth->logout();
		$this->flash('success', 'Anda telah keluar.');
		redirect(site_url('masuk'), 'location', 303);
	}

	// ------------------------------------------------------------ Daftar

	public function register()
	{
		if ($user = $this->auth->user())
		{
			$this->redirect_home_for($user);
			return;
		}
		$this->render('auth/register', array('page_title' => 'Daftar akun warga', 'mail_enabled' => $this->mailer->enabled()), 'site');
	}

	public function register_submit()
	{
		$this->require_method('post');
		$data = array(
			'display_name' => trim($this->post_string('display_name', 100)),
			'username' => trim($this->post_string('username', 60)),
			'email' => trim($this->post_string('email', 191)),
			'phone' => trim($this->post_string('phone', 30)),
			'password' => (string) $this->input->post('password', FALSE),
			'password_confirm' => (string) $this->input->post('password_confirm', FALSE),
		);
		$this->old_input = array_diff_key($data, array('password' => 1, 'password_confirm' => 1));

		// Honeypot: bot yang mengisi field tersembunyi mendapat respons generik.
		if (trim((string) $this->input->post('website')) !== '')
		{
			redirect(site_url('daftar/berhasil'), 'location', 303);
			return;
		}
		if ( ! $this->input->post('agree'))
		{
			$this->form_errors['agree'] = 'Anda perlu menyetujui kebijakan privasi dan ketentuan layanan.';
		}
		$wait = $this->rate_limiter->retry_after('register_ip', (string) $this->input->ip_address());
		if ($wait > 0)
		{
			$this->too_many($wait);
			return;
		}

		try
		{
			if ( ! empty($this->form_errors))
			{
				$errors = array_merge($this->auth->validate_account_fields($data, TRUE), $this->form_errors);
				throw new DomainRuleException('Periksa kembali data pendaftaran.', 422, $errors);
			}
			$this->rate_limiter->hit('register_ip', (string) $this->input->ip_address());
			$result = $this->auth->register_self($data);
		}
		catch (DomainRuleException $e)
		{
			$this->form_errors = $e->errors;
			$this->output->set_status_header(422);
			$this->render('auth/register', array('page_title' => 'Daftar akun warga', 'mail_enabled' => $this->mailer->enabled()), 'site');
			return;
		}
		$this->session->set_flashdata('register_result', $result['activation']);
		redirect(site_url('daftar/berhasil'), 'location', 303);
	}

	public function register_done()
	{
		$this->render('auth/register_done', array(
			'page_title' => 'Pendaftaran diterima',
			'activation' => $this->session->flashdata('register_result'),
		), 'site');
	}

	// ------------------------------------------------------------ Aktivasi

	public function activate()
	{
		$token = (string) $this->input->get('token');
		$this->referrer_policy = 'no-referrer';
		$row = ($token !== '') ? $this->auth->peek_token($token, 'activation') : NULL;
		$needs_password = TRUE;
		if ($row)
		{
			$user = $this->user_model->find($row->user_id);
			$needs_password = ($user && $user->password_hash === NULL);
		}
		$this->render('auth/activate', array(
			'page_title' => 'Aktivasi akun',
			'token' => $row ? $token : '',
			'token_invalid' => ($token !== '' && ! $row),
			'needs_password' => $needs_password,
		), 'site');
	}

	public function activate_submit()
	{
		$this->require_method('post');
		$this->referrer_policy = 'no-referrer';
		$token = trim((string) $this->input->post('token'));
		$wait = $this->rate_limiter->attempt('activation', (string) $this->input->ip_address());
		if ($wait > 0)
		{
			$this->too_many($wait);
			return;
		}
		try
		{
			$user = $this->auth->activate_with_token($token, (string) $this->input->post('password', FALSE), (string) $this->input->post('password_confirm', FALSE));
		}
		catch (DomainRuleException $e)
		{
			$this->form_errors = $e->errors ?: array('token' => $e->getMessage());
			$this->output->set_status_header(422);
			$row = $this->auth->peek_token($token, 'activation');
			$needs = TRUE;
			if ($row)
			{
				$u = $this->user_model->find($row->user_id);
				$needs = ($u && $u->password_hash === NULL);
			}
			$this->render('auth/activate', array('page_title' => 'Aktivasi akun', 'token' => $token, 'token_invalid' => ! $row, 'needs_password' => $needs, 'activate_error' => $e->getMessage()), 'site');
			return;
		}
		$this->flash('success', 'Akun berhasil diaktifkan. Silakan masuk dengan username '.$user->username.'.');
		redirect(site_url('masuk'), 'location', 303);
	}

	// ------------------------------------------------------------ Pemulihan

	public function forgot()
	{
		$this->render('auth/forgot', array('page_title' => 'Lupa password', 'mail_enabled' => $this->mailer->enabled()), 'site');
	}

	public function forgot_submit()
	{
		$this->require_method('post');
		$wait = $this->rate_limiter->attempt('forgot_ip', (string) $this->input->ip_address());
		if ($wait > 0)
		{
			$this->too_many($wait);
			return;
		}
		$this->auth->request_password_reset((string) $this->input->post('email'));
		// Pesan generik: tidak membocorkan keberadaan akun.
		$this->render('auth/forgot', array(
			'page_title' => 'Lupa password',
			'mail_enabled' => $this->mailer->enabled(),
			'sent' => TRUE,
		), 'site');
	}

	public function reset()
	{
		$this->referrer_policy = 'no-referrer';
		$token = (string) $this->input->get('token');
		$valid = ($token !== '') && $this->auth->peek_token($token, 'reset');
		$this->render('auth/reset', array(
			'page_title' => 'Atur ulang password',
			'token' => $valid ? $token : '',
			'token_invalid' => ($token !== '' && ! $valid),
		), 'site');
	}

	public function reset_submit()
	{
		$this->require_method('post');
		$this->referrer_policy = 'no-referrer';
		$token = trim((string) $this->input->post('token'));
		$wait = $this->rate_limiter->attempt('activation', (string) $this->input->ip_address());
		if ($wait > 0)
		{
			$this->too_many($wait);
			return;
		}
		try
		{
			$this->auth->reset_password($token, (string) $this->input->post('password', FALSE), (string) $this->input->post('password_confirm', FALSE));
		}
		catch (DomainRuleException $e)
		{
			$this->form_errors = $e->errors ?: array('token' => $e->getMessage());
			$this->output->set_status_header(422);
			$this->render('auth/reset', array('page_title' => 'Atur ulang password', 'token' => $token, 'token_invalid' => FALSE, 'reset_error' => $e->getMessage()), 'site');
			return;
		}
		$this->flash('success', 'Password berhasil diubah. Semua sesi lama telah dikeluarkan; silakan masuk kembali.');
		redirect(site_url('masuk'), 'location', 303);
	}

	public function help()
	{
		$this->render('auth/help', array('page_title' => 'Bantuan akun'), 'site');
	}
}
