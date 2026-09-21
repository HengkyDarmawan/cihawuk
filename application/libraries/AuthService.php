<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Autentikasi dan siklus hidup akun.
 *
 * Sesi login disimpan pada sesi CI3 (driver database) + baris user_sessions yang
 * dapat dicabut. Setiap request memeriksa status akun, auth_version, pencabutan,
 * idle timeout dan batas absolut per area (admin/warga).
 */
class AuthService {

	const DUMMY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$U1ZORW00MmlqNmhaM0hoQQ$jTwulXcqVPY3GnHv/XNMnLRusC3MK7KbpzX6nwIxgWY';

	/** @var CI_Controller */
	protected $CI;

	/** @var object|null|false  FALSE = belum diperiksa */
	protected $user = FALSE;

	/** @var string|null alasan sesi berakhir (untuk pesan UI) */
	public $end_reason = NULL;

	protected $weak_passwords = array(
		'password1234', 'password12345', '123456789012', '1234567890123', 'qwertyuiop12', 'cihawuk12345',
		'desacihawuk1', 'kertasari123', 'admin1234567', 'bismillah123', 'indonesia123', 'aaaaaaaaaaaa',
	);

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->model('User_model', 'user_model');
		$this->CI->config->load('app', TRUE);
	}

	// ------------------------------------------------------------------
	// Password
	// ------------------------------------------------------------------

	public function hash_password($password)
	{
		return password_hash($password, PASSWORD_ARGON2ID);
	}

	/**
	 * @return string|null pesan error bila tidak memenuhi kebijakan
	 */
	public function password_policy_error($password, $username = NULL)
	{
		$password = (string) $password;
		$min = (int) $this->CI->config->item('password_min_length', 'app');
		$max = (int) $this->CI->config->item('password_max_length', 'app');
		$len = mb_strlen($password, 'UTF-8');
		if ($len < $min)
		{
			return 'Password minimal '.$min.' karakter.';
		}
		if ($len > $max OR strlen($password) > 1024)
		{
			return 'Password maksimal '.$max.' karakter.';
		}
		$lower = mb_strtolower($password);
		if (in_array($lower, $this->weak_passwords, TRUE) OR preg_match('/^(.)\1+$/u', $password))
		{
			return 'Password terlalu mudah ditebak. Gunakan kalimat atau kombinasi yang lebih unik.';
		}
		if ($username !== NULL && $username !== '' && mb_strpos($lower, mb_strtolower($username)) !== FALSE)
		{
			return 'Password tidak boleh memuat username.';
		}
		return NULL;
	}

	// ------------------------------------------------------------------
	// Login
	// ------------------------------------------------------------------

	/**
	 * @return array{status:string, user?:object, retry_after?:int}
	 * status: ok | mfa_required | invalid | throttled | pending_activation | suspended | closed
	 */
	public function attempt_login($identifier, $password)
	{
		$identifier = mb_strtolower(trim((string) $identifier));
		$ip = (string) $this->CI->input->ip_address();

		$wait = max($this->CI->rate_limiter->retry_after('login_identifier', $identifier), $this->CI->rate_limiter->retry_after('login_ip', $ip));
		if ($wait > 0)
		{
			$this->CI->audit->log('auth.login_throttled', 'user', NULL, array('reason' => 'rate_limit'));
			return array('status' => 'throttled', 'retry_after' => $wait);
		}

		$user = NULL;
		if ($identifier !== '' && strlen((string) $password) <= 1024)
		{
			if (strpos($identifier, '@') !== FALSE)
			{
				$candidate = $this->CI->user_model->find_by_email($identifier);
				// Login email hanya untuk email terverifikasi.
				$user = ($candidate && $candidate->email_verified_at !== NULL) ? $candidate : NULL;
			}
			else
			{
				$user = $this->CI->user_model->find_by_username($identifier);
			}
		}

		$hash = ($user && $user->password_hash) ? $user->password_hash : self::DUMMY_HASH;
		$valid = password_verify((string) $password, $hash) && $user && $user->password_hash;

		if ( ! $valid)
		{
			$this->CI->rate_limiter->hit('login_identifier', $identifier);
			$this->CI->rate_limiter->hit('login_ip', $ip);
			$this->CI->audit->log('auth.login_failed', 'user', $user ? $user->public_id : NULL, array('known_account' => (bool) $user), NULL);
			return array('status' => 'invalid');
		}

		if ($user->account_status !== 'active')
		{
			$this->CI->audit->log('auth.login_blocked_status', 'user', $user->public_id, array('account_status' => $user->account_status), (int) $user->id);
			return array('status' => $user->account_status, 'user' => $user);
		}

		$this->CI->rate_limiter->clear('login_identifier', $identifier);

		if (password_needs_rehash($user->password_hash, PASSWORD_ARGON2ID))
		{
			$this->CI->user_model->update($user->id, array('password_hash' => $this->hash_password($password)));
		}

		if ($this->mfa_enabled($user->id))
		{
			$this->CI->session->sess_regenerate(TRUE);
			$this->CI->session->set_userdata('pre_auth', array(
				'uid' => (int) $user->id,
				'v' => (int) $user->auth_version,
				'exp' => $this->CI->clock->timestamp() + 300,
			));
			$this->CI->session->unset_userdata('auth');
			return array('status' => 'mfa_required', 'user' => $user);
		}

		$this->complete_login($user);
		return array('status' => 'ok', 'user' => $user);
	}

	/** Pengguna pada tahap MFA (password valid, belum masuk penuh). */
	public function pre_auth_user()
	{
		$pre = $this->CI->session->userdata('pre_auth');
		if ( ! is_array($pre) OR $pre['exp'] < $this->CI->clock->timestamp())
		{
			return NULL;
		}
		$user = $this->CI->user_model->find($pre['uid']);
		if ( ! $user OR $user->account_status !== 'active' OR (int) $user->auth_version !== (int) $pre['v'])
		{
			return NULL;
		}
		return $user;
	}

	public function complete_mfa_login($code)
	{
		$user = $this->pre_auth_user();
		if ( ! $user)
		{
			return array('status' => 'expired');
		}
		$wait = $this->CI->rate_limiter->retry_after('mfa', $user->public_id);
		if ($wait > 0)
		{
			return array('status' => 'throttled', 'retry_after' => $wait);
		}
		if ( ! $this->mfa_verify($user->id, $code))
		{
			$this->CI->rate_limiter->hit('mfa', $user->public_id);
			$this->CI->audit->log('auth.mfa_failed', 'user', $user->public_id, array(), (int) $user->id);
			return array('status' => 'invalid');
		}
		$this->CI->rate_limiter->clear('mfa', $user->public_id);
		$this->CI->session->unset_userdata('pre_auth');
		$this->complete_login($user, TRUE);
		return array('status' => 'ok', 'user' => $user);
	}

	public function complete_login($user, $mfa = FALSE)
	{
		$this->CI->session->sess_regenerate(TRUE);
		$area = $this->CI->user_model->is_staff($user->id) ? 'admin' : 'resident';
		$now = $this->CI->clock->now();
		$this->open_session($user, $area, array('mfa' => (bool) $mfa));
		$this->CI->user_model->update($user->id, array('last_login_at' => $now->format('Y-m-d H:i:s')));
		$this->CI->audit->log('auth.login_success', 'user', $user->public_id, array('area' => $area, 'mfa' => (bool) $mfa), (int) $user->id);
		$this->user = FALSE;
	}

	/**
	 * Buat baris `user_sessions` dan isi `$_SESSION['auth']`. Pemanggil bertanggung jawab
	 * meregenerasi ID sesi lebih dulu.
	 *
	 * @param array $extra mfa (bool), impersonator (int), ttl_minutes (int)
	 */
	protected function open_session($user, $area, array $extra = array())
	{
		$limits = $this->CI->config->item('session_limits', 'app');
		$now = $this->CI->clock->now();
		$fp_token = $this->CI->crypto->random_hex(32);
		$ttl = (int) $limits[$area]['absolute'];
		if ( ! empty($extra['ttl_minutes']))
		{
			$ttl = min($ttl, (int) $extra['ttl_minutes']);
		}
		$impersonator = empty($extra['impersonator']) ? NULL : (int) $extra['impersonator'];

		$ua = substr(preg_replace('/[\x00-\x1F\x7F]/', '', (string) $this->CI->input->user_agent()), 0, 150);
		db_must($this->CI->db->insert('user_sessions', array(
			'user_id' => (int) $user->id,
			'impersonator_user_id' => $impersonator,
			'session_fingerprint' => hash('sha256', $fp_token),
			'auth_version' => (int) $user->auth_version,
			'area' => $area,
			'device_label' => $impersonator ? 'Login sebagai (oleh pengelola)' : $this->device_label($ua),
			'created_at' => $now->format('Y-m-d H:i:s'),
			'last_seen_at' => $now->format('Y-m-d H:i:s'),
			'expires_at' => $now->modify('+'.$ttl.' minutes')->format('Y-m-d H:i:s'),
		)), 'user_sessions.create');
		$sid = (int) $this->CI->db->insert_id();

		$auth = array(
			'uid' => (int) $user->id,
			'v' => (int) $user->auth_version,
			'area' => $area,
			'sid' => $sid,
			'fp' => $fp_token,
			'login_at' => $now->getTimestamp(),
			'last' => $now->getTimestamp(),
			// Sesi "login sebagai" tidak pernah dianggap baru reautentikasi.
			'reauth_at' => $impersonator ? 0 : $now->getTimestamp(),
			'mfa' => ! empty($extra['mfa']),
		);
		if ($impersonator)
		{
			$auth['imp'] = $impersonator;
		}
		$this->CI->session->set_userdata('auth', $auth);
		return $sid;
	}

	// ------------------------------------------------------------------
	// Login sebagai (impersonation)
	// ------------------------------------------------------------------

	/** Lama maksimum satu sesi "login sebagai", dalam menit. */
	const IMPERSONATION_TTL = 60;

	/** Id pengelola yang sedang login sebagai pengguna lain, atau NULL. */
	public function impersonator_id()
	{
		if (is_cli() OR ! isset($this->CI->session))
		{
			return NULL;
		}
		$auth = $this->CI->session->userdata('auth');
		return (is_array($auth) && ! empty($auth['imp'])) ? (int) $auth['imp'] : NULL;
	}

	public function is_impersonating()
	{
		return $this->impersonator_id() !== NULL;
	}

	/** Akun pengelola asli selama penyamaran, atau NULL. */
	public function impersonator()
	{
		$id = $this->impersonator_id();
		return $id ? $this->CI->user_model->find($id) : NULL;
	}

	/**
	 * Mulai sesi sebagai $target. Sesi pengelola disimpan utuh dan dipulihkan oleh
	 * stop_impersonation(); sesi target berumur paling lama IMPERSONATION_TTL menit.
	 */
	public function impersonate($admin, $target)
	{
		if ($this->is_impersonating())
		{
			throw new DomainRuleException('Kembali ke akun Anda sendiri sebelum login sebagai pengguna lain.', 409);
		}
		if ((int) $admin->id === (int) $target->id)
		{
			throw new DomainRuleException('Anda tidak dapat login sebagai akun Anda sendiri.', 409);
		}
		if ($target->account_status !== 'active')
		{
			throw new DomainRuleException('Hanya akun aktif yang dapat dipakai untuk login sebagai.', 409);
		}
		if (in_array('super_admin', $this->CI->user_model->role_codes($target->id), TRUE))
		{
			throw new DomainRuleException('Login sebagai Super Admin lain tidak diizinkan.', 403);
		}

		$original = $this->CI->session->userdata('auth');
		$area = $this->CI->user_model->is_staff($target->id) ? 'admin' : 'resident';
		$this->CI->session->sess_regenerate(TRUE);
		$this->open_session($target, $area, array(
			'impersonator' => (int) $admin->id,
			'ttl_minutes' => self::IMPERSONATION_TTL,
			'mfa' => ! empty($original['mfa']),
		));
		$this->CI->session->set_userdata('impersonator_auth', $original);
		$this->CI->audit->log('auth.impersonation_started', 'user', $target->public_id,
			array('area' => $area, 'minutes' => self::IMPERSONATION_TTL), (int) $admin->id, 'auth');
		$this->user = FALSE;
		return $area;
	}

	/**
	 * Akhiri penyamaran dan pulihkan sesi pengelola. Mengembalikan akun target (untuk
	 * redirect) atau NULL bila tidak sedang menyamar.
	 */
	public function stop_impersonation()
	{
		$auth = $this->CI->session->userdata('auth');
		$original = $this->CI->session->userdata('impersonator_auth');
		if ( ! is_array($auth) OR empty($auth['imp']))
		{
			return NULL;
		}
		$target = $this->CI->user_model->find($auth['uid']);
		$this->CI->db->where('id', (int) $auth['sid'])->where('revoked_at IS NULL', NULL, FALSE)
			->update('user_sessions', array('revoked_at' => utc_now()));
		$this->CI->audit->log('auth.impersonation_ended', 'user', $target ? $target->public_id : NULL, array(), (int) $auth['imp'], 'auth');

		$this->CI->session->sess_regenerate(TRUE);
		$this->CI->session->unset_userdata(array('auth', 'impersonator_auth'));
		if (is_array($original) && ! empty($original['uid']) && (int) $original['uid'] === (int) $auth['imp'])
		{
			$original['last'] = $this->CI->clock->timestamp();
			$this->CI->session->set_userdata('auth', $original);
		}
		$this->user = FALSE;
		return $target;
	}

	protected function device_label($ua)
	{
		$browser = 'Peramban';
		foreach (array('Edg' => 'Edge', 'OPR' => 'Opera', 'Chrome' => 'Chrome', 'Firefox' => 'Firefox', 'Safari' => 'Safari') as $needle => $name)
		{
			if (stripos($ua, $needle) !== FALSE)
			{
				$browser = $name;
				break;
			}
		}
		$os = 'perangkat tidak dikenal';
		foreach (array('Android' => 'Android', 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Windows' => 'Windows', 'Mac OS' => 'macOS', 'Linux' => 'Linux') as $needle => $name)
		{
			if (stripos($ua, $needle) !== FALSE)
			{
				$os = $name;
				break;
			}
		}
		return $browser.' di '.$os;
	}

	/**
	 * Validasi sesi login untuk request ini. Mengembalikan user atau NULL.
	 */
	public function user()
	{
		if ($this->user !== FALSE)
		{
			return $this->user;
		}
		$this->user = NULL;
		if (is_cli() OR ! isset($this->CI->session))
		{
			return NULL;
		}
		$auth = $this->CI->session->userdata('auth');
		if ( ! is_array($auth) OR empty($auth['uid']))
		{
			return NULL;
		}

		$user = $this->CI->user_model->find($auth['uid']);
		$session_row = $this->CI->db->get_where('user_sessions', array('id' => (int) $auth['sid']))->row();
		$now = $this->CI->clock->now();
		$limits = $this->CI->config->item('session_limits', 'app');
		$area = isset($limits[$auth['area']]) ? $auth['area'] : 'resident';

		$reason = NULL;
		if ( ! $user OR $user->account_status !== 'active')
		{
			$reason = 'account';
		}
		elseif ((int) $user->auth_version !== (int) $auth['v'])
		{
			$reason = 'revoked';
		}
		elseif ( ! $session_row OR $session_row->revoked_at !== NULL OR (int) $session_row->user_id !== (int) $user->id
			OR ! hash_equals($session_row->session_fingerprint, hash('sha256', (string) $auth['fp'])))
		{
			$reason = 'revoked';
		}
		elseif ($now->getTimestamp() - (int) $auth['last'] > $limits[$area]['idle'] * 60)
		{
			$reason = 'idle';
		}
		elseif ($now->getTimestamp() - (int) $auth['login_at'] > $limits[$area]['absolute'] * 60
			OR strtotime($session_row->expires_at.' UTC') < $now->getTimestamp())
		{
			$reason = 'expired';
		}

		if ($reason !== NULL)
		{
			$this->end_reason = $reason;
			if ($session_row && $session_row->revoked_at === NULL && in_array($reason, array('idle', 'expired'), TRUE))
			{
				$this->CI->db->where('id', (int) $session_row->id)->update('user_sessions', array('revoked_at' => $now->format('Y-m-d H:i:s')));
			}
			$original = $this->CI->session->userdata('impersonator_auth');
			$this->CI->session->unset_userdata(array('auth', 'pre_auth', 'impersonator_auth'));
			$this->CI->session->sess_regenerate(TRUE);
			// Sesi "login sebagai" yang berakhir mengembalikan pengelola ke sesinya sendiri;
			// sesi asli itu tetap divalidasi penuh oleh pemanggilan ulang di bawah.
			if ( ! empty($auth['imp']) && is_array($original) && (int) ($original['uid'] ?? 0) === (int) $auth['imp'])
			{
				$this->CI->audit->log('auth.impersonation_ended', 'user', $user ? $user->public_id : NULL,
					array('reason' => $reason), (int) $auth['imp'], 'auth');
				$original['last'] = $now->getTimestamp();
				$this->CI->session->set_userdata('auth', $original);
				$this->end_reason = NULL;
				$this->user = FALSE;
				return $this->user();
			}
			return NULL;
		}

		// Perbarui aktivitas maksimal sekali per 60 detik.
		if ($now->getTimestamp() - (int) $auth['last'] >= 60)
		{
			$auth['last'] = $now->getTimestamp();
			$this->CI->session->set_userdata('auth', $auth);
			$this->CI->db->where('id', (int) $session_row->id)->update('user_sessions', array('last_seen_at' => $now->format('Y-m-d H:i:s')));
		}

		$user->area = $area;
		$user->session_row_id = (int) $session_row->id;
		$this->user = $user;
		return $user;
	}

	public function user_id()
	{
		$user = $this->user();
		return $user ? (int) $user->id : NULL;
	}

	public function check()
	{
		return $this->user() !== NULL;
	}

	public function area()
	{
		$user = $this->user();
		return $user ? $user->area : NULL;
	}

	public function logout()
	{
		// Keluar saat sedang "login sebagai" menutup penyamaran lalu sesi pengelola aslinya.
		if ($this->is_impersonating())
		{
			$this->stop_impersonation();
		}
		$auth = $this->CI->session->userdata('auth');
		if (is_array($auth) && ! empty($auth['sid']))
		{
			$this->CI->db->where('id', (int) $auth['sid'])->where('revoked_at IS NULL', NULL, FALSE)
				->update('user_sessions', array('revoked_at' => utc_now()));
			$user = $this->CI->user_model->find($auth['uid']);
			$this->CI->audit->log('auth.logout', 'user', $user ? $user->public_id : NULL, array(), (int) $auth['uid']);
		}
		$this->CI->session->unset_userdata(array('auth', 'pre_auth', 'anon_grant'));
		$this->CI->session->sess_regenerate(TRUE);
		$this->user = FALSE;
	}

	/** Cabut semua sesi (termasuk sesi ini) dengan menaikkan auth_version. */
	public function logout_all($user_id, $reason = 'user_request')
	{
		$user = $this->CI->user_model->find($user_id);
		$this->CI->user_model->bump_auth_version($user_id);
		$this->CI->db->where('user_id', (int) $user_id)->where('revoked_at IS NULL', NULL, FALSE)
			->update('user_sessions', array('revoked_at' => utc_now()));
		$this->CI->db->where('user_id', (int) $user_id)->where('revoked_at IS NULL', NULL, FALSE)
			->update('remember_tokens', array('revoked_at' => utc_now()));
		$this->CI->audit->log('auth.sessions_revoked_all', 'user', $user ? $user->public_id : NULL, array('reason' => $reason));
		$this->user = FALSE;
	}

	/** Cabut sesi lain milik pengguna, pertahankan sesi aktif. */
	public function revoke_other_sessions($user_id, $keep_session_row_id)
	{
		$this->CI->user_model->bump_auth_version($user_id);
		$user = $this->CI->user_model->find($user_id);
		$this->CI->db->where('user_id', (int) $user_id)->where('id !=', (int) $keep_session_row_id)
			->where('revoked_at IS NULL', NULL, FALSE)->update('user_sessions', array('revoked_at' => utc_now()));
		$this->CI->db->where('id', (int) $keep_session_row_id)->update('user_sessions', array('auth_version' => (int) $user->auth_version));
		$auth = $this->CI->session->userdata('auth');
		if (is_array($auth) && (int) $auth['uid'] === (int) $user_id)
		{
			$auth['v'] = (int) $user->auth_version;
			$this->CI->session->sess_regenerate(TRUE);
			$this->CI->session->set_userdata('auth', $auth);
		}
		$this->user = FALSE;
	}

	public function revoke_session($user_id, $session_row_id)
	{
		$affected = $this->CI->db->where(array('id' => (int) $session_row_id, 'user_id' => (int) $user_id))
			->where('revoked_at IS NULL', NULL, FALSE)
			->update('user_sessions', array('revoked_at' => utc_now()));
		return $affected && $this->CI->db->affected_rows() > 0;
	}

	public function active_sessions($user_id)
	{
		return $this->CI->db->where('user_id', (int) $user_id)
			->where('revoked_at IS NULL', NULL, FALSE)
			->where('expires_at >', utc_now())
			->order_by('last_seen_at', 'DESC')
			->get('user_sessions')->result();
	}

	// ------------------------------------------------------------------
	// Reautentikasi untuk tindakan sensitif
	// ------------------------------------------------------------------

	public function recently_reauthenticated()
	{
		$auth = $this->CI->session->userdata('auth');
		$ttl = (int) $this->CI->config->item('reauth_ttl', 'app');
		return is_array($auth) && isset($auth['reauth_at']) && ($this->CI->clock->timestamp() - (int) $auth['reauth_at']) <= $ttl;
	}

	public function reauthenticate($password)
	{
		$user = $this->user();
		if ( ! $user)
		{
			return FALSE;
		}
		if ($this->CI->rate_limiter->too_many('login_identifier', 'reauth:'.$user->public_id))
		{
			return FALSE;
		}
		if ( ! password_verify((string) $password, (string) $user->password_hash))
		{
			$this->CI->rate_limiter->hit('login_identifier', 'reauth:'.$user->public_id);
			$this->CI->audit->log('auth.reauth_failed', 'user', $user->public_id);
			return FALSE;
		}
		$auth = $this->CI->session->userdata('auth');
		$auth['reauth_at'] = $this->CI->clock->timestamp();
		$this->CI->session->set_userdata('auth', $auth);
		return TRUE;
	}

	// ------------------------------------------------------------------
	// Pendaftaran & aktivasi
	// ------------------------------------------------------------------

	/**
	 * Validasi data akun umum. @return array error per field
	 */
	public function validate_account_fields(array $data, $require_password = TRUE, $exclude_user_id = NULL)
	{
		$errors = array();
		$name = trim((string) ($data['display_name'] ?? ''));
		if (mb_strlen($name) < 3 OR mb_strlen($name) > 100)
		{
			$errors['display_name'] = 'Nama wajib diisi, 3–100 karakter.';
		}
		$username = $this->CI->user_model->normalize_username($data['username'] ?? '');
		if ( ! $this->CI->user_model->username_valid($username))
		{
			$errors['username'] = 'Username 4–50 karakter: huruf kecil, angka, titik atau garis bawah.';
		}
		else
		{
			$existing = $this->CI->user_model->find_by_username($username);
			if ($existing && (int) $existing->id !== (int) $exclude_user_id)
			{
				$errors['username'] = 'Username sudah digunakan.';
			}
		}
		$email = $this->CI->user_model->normalize_email($data['email'] ?? '');
		if ($email !== NULL)
		{
			if (strlen($email) > 191 OR filter_var($email, FILTER_VALIDATE_EMAIL) === FALSE)
			{
				$errors['email'] = 'Format email tidak valid.';
			}
			else
			{
				$existing = $this->CI->user_model->find_by_email($email);
				if ($existing && (int) $existing->id !== (int) $exclude_user_id)
				{
					$errors['email'] = 'Email sudah terdaftar pada akun lain.';
				}
			}
		}
		if (trim((string) ($data['phone'] ?? '')) !== '' && $this->CI->user_model->normalize_phone($data['phone']) === FALSE)
		{
			$errors['phone'] = 'Nomor telepon tidak valid. Contoh: 081234567890.';
		}
		if ($require_password)
		{
			$policy = $this->password_policy_error($data['password'] ?? '', $username);
			if ($policy !== NULL)
			{
				$errors['password'] = $policy;
			}
			elseif ((string) ($data['password'] ?? '') !== (string) ($data['password_confirm'] ?? ''))
			{
				$errors['password_confirm'] = 'Konfirmasi password tidak sama.';
			}
		}
		return $errors;
	}

	/**
	 * Daftar mandiri. Akun berstatus pending_activation sampai aktivasi email/manual.
	 * @return array{user_id:int, activation:string} activation: email_sent|manual_review
	 */
	public function register_self(array $data)
	{
		$errors = $this->validate_account_fields($data, TRUE);
		if ( ! empty($errors))
		{
			throw new DomainRuleException('Periksa kembali data pendaftaran.', 422, $errors);
		}
		$email = $this->CI->user_model->normalize_email($data['email'] ?? '');
		$phone = trim((string) ($data['phone'] ?? '')) === '' ? NULL : $this->CI->user_model->normalize_phone($data['phone']);

		$result = db_transaction(function () use ($data, $email, $phone) {
			$user_id = $this->CI->user_model->create(array(
				'public_id' => $this->CI->crypto->public_id(),
				'username' => $this->CI->user_model->normalize_username($data['username']),
				'display_name' => trim($data['display_name']),
				'email' => $email,
				'phone' => $phone,
				'password_hash' => $this->hash_password($data['password']),
				'account_status' => 'pending_activation',
				'registration_channel' => 'self',
			));
			$this->create_resident_profile($user_id, trim($data['display_name']), 'pending');
			$this->CI->user_model->assign_role($user_id, 'resident', NULL);
			return $user_id;
		});

		$user = $this->CI->user_model->find($result);
		$activation = 'manual_review';
		if ($email !== NULL && $this->CI->mailer->enabled())
		{
			$token = $this->create_token($user->id, 'activation', 86400 * 3, 'email', NULL);
			if ($this->CI->mailer->send_account_email($email, 'activation', array('name' => $user->display_name, 'url' => site_url('aktivasi?token='.rawurlencode($token)))))
			{
				$activation = 'email_sent';
			}
		}
		$this->CI->audit->log('account.registered', 'user', $user->public_id, array('channel' => 'self', 'activation' => $activation), (int) $user->id);
		$this->CI->notifications->notify_permission_holders('residents.verify', 'account.pending_review',
			'Akun warga baru menunggu review aktivasi.', 'user', $user->public_id, '/admin/pengguna?status=pending_activation');
		return array('user_id' => (int) $user->id, 'activation' => $activation);
	}

	protected function create_resident_profile($user_id, $display_name, $status = 'unverified')
	{
		$now = utc_now();
		db_must($this->CI->db->insert('resident_profiles', array(
			'user_id' => (int) $user_id,
			'display_name' => $display_name,
			'verification_status' => $status,
			'created_at' => $now,
			'updated_at' => $now,
		)), 'resident_profiles.create');
	}

	/**
	 * Super Admin/petugas berizin mendaftarkan warga tanpa password.
	 * @return array{user_id:int, token:string, expires_at:string}
	 */
	public function create_resident_by_staff(array $data, $staff_user_id)
	{
		$errors = $this->validate_account_fields($data, FALSE);
		if ( ! empty($errors))
		{
			throw new DomainRuleException('Periksa kembali data warga.', 422, $errors);
		}
		$email = $this->CI->user_model->normalize_email($data['email'] ?? '');
		$phone = trim((string) ($data['phone'] ?? '')) === '' ? NULL : $this->CI->user_model->normalize_phone($data['phone']);
		$ttl = 7 * 86400;

		$out = db_transaction(function () use ($data, $email, $phone, $staff_user_id, $ttl) {
			$user_id = $this->CI->user_model->create(array(
				'public_id' => $this->CI->crypto->public_id(),
				'username' => $this->CI->user_model->normalize_username($data['username']),
				'display_name' => trim($data['display_name']),
				'email' => $email,
				'phone' => $phone,
				'password_hash' => NULL,
				'account_status' => 'pending_activation',
				'registration_channel' => 'staff',
				'created_by' => (int) $staff_user_id,
			));
			$this->create_resident_profile($user_id, trim($data['display_name']), 'unverified');
			// Hanya role warga; role petugas tidak dapat disisipkan melalui form ini.
			$this->CI->user_model->assign_role($user_id, 'resident', (int) $staff_user_id);
			$token = $this->create_token($user_id, 'activation', $ttl, 'front_desk', (int) $staff_user_id);
			return array('user_id' => $user_id, 'token' => $token);
		});
		$user = $this->CI->user_model->find($out['user_id']);
		$this->CI->audit->log('account.created_by_staff', 'user', $user->public_id, array('delivery' => 'front_desk'), (int) $staff_user_id);
		$out['expires_at'] = $this->CI->clock->plus_seconds($ttl);
		return $out;
	}

	/** Aktivasi manual oleh petugas berizin untuk akun yang sudah memiliki password. */
	public function activate_manually($user_id, $staff_user_id, $note)
	{
		$user = $this->CI->user_model->find($user_id);
		if ( ! $user OR $user->account_status !== 'pending_activation')
		{
			throw new DomainRuleException('Akun tidak dalam status menunggu aktivasi.');
		}
		if ($user->password_hash === NULL)
		{
			throw new DomainRuleException('Akun belum memiliki password. Berikan kode aktivasi agar warga menetapkan password sendiri.');
		}
		db_transaction(function () use ($user, $staff_user_id, $note) {
			$this->CI->user_model->update($user->id, array('account_status' => 'active', 'status_reason' => mb_substr((string) $note, 0, 255)));
			$this->CI->db->where('user_id', (int) $user->id)->update('resident_profiles', array(
				'verification_status' => 'verified', 'verified_by' => (int) $staff_user_id, 'verified_at' => utc_now(),
				'review_reason' => mb_substr((string) $note, 0, 255), 'updated_at' => utc_now(),
			));
		});
		$this->CI->audit->log('account.activated_manual', 'user', $user->public_id, array(), (int) $staff_user_id);
		$this->CI->notifications->notify($user->id, 'account.activated', 'Akun Anda telah diaktifkan. Anda sekarang dapat membuat laporan dari dashboard warga.', 'user', $user->public_id, '/warga');
	}

	public function create_token($user_id, $purpose, $ttl_seconds, $delivery, $created_by)
	{
		list($selector, $validator, $token, $hash) = $this->CI->crypto->split_token();
		// Token lama dengan tujuan sama dicabut.
		$this->CI->db->where(array('user_id' => (int) $user_id, 'purpose' => $purpose))
			->where('used_at IS NULL', NULL, FALSE)->where('revoked_at IS NULL', NULL, FALSE)
			->update('account_tokens', array('revoked_at' => utc_now()));
		db_must($this->CI->db->insert('account_tokens', array(
			'user_id' => (int) $user_id,
			'purpose' => $purpose,
			'selector' => $selector,
			'token_hash' => $hash,
			'delivery' => $delivery,
			'expires_at' => $this->CI->clock->plus_seconds($ttl_seconds),
			'created_by' => $created_by,
			'created_at' => utc_now(),
		)), 'account_tokens.create');
		return $token;
	}

	/**
	 * Cari token valid tanpa mengonsumsinya (untuk halaman GET).
	 */
	public function peek_token($token, $purpose)
	{
		$token = trim((string) $token);
		if ( ! preg_match('/^([a-f0-9]{16})\.([0-9A-Z]{20,60})$/', $token, $m))
		{
			return NULL;
		}
		$row = $this->CI->db->get_where('account_tokens', array('selector' => $m[1], 'purpose' => $purpose))->row();
		if ( ! $row OR $row->used_at !== NULL OR $row->revoked_at !== NULL
			OR strtotime($row->expires_at.' UTC') < $this->CI->clock->timestamp()
			OR ! hash_equals($row->token_hash, $this->CI->crypto->hmac($token, 'token')))
		{
			return NULL;
		}
		return $row;
	}

	/** Konsumsi token sekali pakai dengan row lock. Harus di dalam transaction. */
	protected function consume_token($token, $purpose)
	{
		$row = $this->peek_token($token, $purpose);
		if ( ! $row)
		{
			return NULL;
		}
		$locked = $this->CI->db->query('SELECT * FROM account_tokens WHERE id = ? FOR UPDATE', array((int) $row->id))->row();
		if ( ! $locked OR $locked->used_at !== NULL OR $locked->revoked_at !== NULL)
		{
			return NULL;
		}
		db_must($this->CI->db->where('id', (int) $locked->id)->update('account_tokens', array('used_at' => utc_now())), 'account_tokens.consume');
		return $locked;
	}

	public function activate_with_token($token, $password, $password_confirm)
	{
		$row = $this->peek_token($token, 'activation');
		if ( ! $row)
		{
			throw new DomainRuleException('Tautan atau kode aktivasi tidak valid atau sudah kedaluwarsa.', 422, array('token' => 'Kode aktivasi tidak valid.'));
		}
		$user = $this->CI->user_model->find($row->user_id);
		if ($user->account_status !== 'pending_activation')
		{
			throw new DomainRuleException('Akun ini tidak dalam status menunggu aktivasi.');
		}
		$needs_password = ($user->password_hash === NULL);
		if ($needs_password)
		{
			$policy = $this->password_policy_error($password, $user->username);
			if ($policy !== NULL)
			{
				throw new DomainRuleException($policy, 422, array('password' => $policy));
			}
			if ((string) $password !== (string) $password_confirm)
			{
				throw new DomainRuleException('Konfirmasi password tidak sama.', 422, array('password_confirm' => 'Konfirmasi password tidak sama.'));
			}
		}
		db_transaction(function () use ($token, $user, $needs_password, $password, $row) {
			if ( ! $this->consume_token($token, 'activation'))
			{
				throw new DomainRuleException('Kode aktivasi sudah digunakan.');
			}
			$update = array('account_status' => 'active');
			if ($needs_password)
			{
				$update['password_hash'] = $this->hash_password($password);
			}
			if ($row->delivery === 'email' && $user->email !== NULL)
			{
				$update['email_verified_at'] = utc_now();
			}
			$this->CI->user_model->update($user->id, $update);
		});
		$this->CI->audit->log('account.activated_token', 'user', $user->public_id, array('delivery' => $row->delivery), (int) $user->id);
		return $this->CI->user_model->find($user->id);
	}

	// ------------------------------------------------------------------
	// Pemulihan password
	// ------------------------------------------------------------------

	/** Selalu mengembalikan tanpa membocorkan keberadaan akun. */
	public function request_password_reset($email)
	{
		$email = $this->CI->user_model->normalize_email($email);
		if ($email === NULL OR ! $this->CI->mailer->enabled())
		{
			return;
		}
		$user = $this->CI->user_model->find_by_email($email);
		if ( ! $user OR $user->email_verified_at === NULL OR $user->account_status !== 'active')
		{
			$this->CI->audit->log('auth.reset_requested', 'user', NULL, array('eligible' => FALSE));
			return;
		}
		$token = $this->create_token($user->id, 'reset', 1800, 'email', NULL);
		$this->CI->mailer->send_account_email($user->email, 'password_reset', array(
			'name' => $user->display_name,
			'url' => site_url('reset-password?token='.rawurlencode($token)),
		));
		$this->CI->audit->log('auth.reset_requested', 'user', $user->public_id, array('eligible' => TRUE), (int) $user->id);
	}

	/** Kode pemulihan manual setelah pemeriksaan identitas oleh petugas. */
	public function issue_manual_recovery($user_id, $staff_user_id, $reason)
	{
		$user = $this->CI->user_model->find($user_id);
		if ( ! $user OR ! in_array($user->account_status, array('active', 'pending_activation'), TRUE))
		{
			throw new DomainRuleException('Akun tidak dapat dipulihkan.');
		}
		if (mb_strlen(trim((string) $reason)) < 10)
		{
			throw new DomainRuleException('Tuliskan dasar pemeriksaan identitas (minimal 10 karakter).', 422, array('reason' => 'Alasan wajib diisi.'));
		}
		if ((int) $user_id === (int) $staff_user_id)
		{
			throw new DomainRuleException('Pemulihan akun sendiri tidak dapat dilakukan melalui jalur ini.');
		}
		$purpose = ($user->account_status === 'pending_activation') ? 'activation' : 'reset';
		$token = $this->create_token($user->id, $purpose, 86400, 'front_desk', (int) $staff_user_id);
		$this->CI->audit->log('account.manual_recovery_issued', 'user', $user->public_id, array('purpose' => $purpose, 'reason' => mb_substr($reason, 0, 200)), (int) $staff_user_id);
		return array('token' => $token, 'purpose' => $purpose);
	}

	public function reset_password($token, $password, $password_confirm)
	{
		$row = $this->peek_token($token, 'reset');
		if ( ! $row)
		{
			throw new DomainRuleException('Tautan atau kode reset tidak valid atau sudah kedaluwarsa.', 422, array('token' => 'Kode reset tidak valid.'));
		}
		$user = $this->CI->user_model->find($row->user_id);
		$policy = $this->password_policy_error($password, $user->username);
		if ($policy !== NULL)
		{
			throw new DomainRuleException($policy, 422, array('password' => $policy));
		}
		if ((string) $password !== (string) $password_confirm)
		{
			throw new DomainRuleException('Konfirmasi password tidak sama.', 422, array('password_confirm' => 'Konfirmasi password tidak sama.'));
		}
		db_transaction(function () use ($token, $user, $password) {
			if ( ! $this->consume_token($token, 'reset'))
			{
				throw new DomainRuleException('Kode reset sudah digunakan.');
			}
			$this->CI->user_model->update($user->id, array('password_hash' => $this->hash_password($password), 'must_change_password' => 0));
			// Batalkan token reset lain.
			$this->CI->db->where(array('user_id' => (int) $user->id, 'purpose' => 'reset'))->where('used_at IS NULL', NULL, FALSE)
				->update('account_tokens', array('revoked_at' => utc_now()));
		});
		$this->logout_all($user->id, 'password_reset');
		$this->CI->audit->log('auth.password_reset', 'user', $user->public_id, array('delivery' => $row->delivery), (int) $user->id);
		$this->CI->notifications->notify($user->id, 'account.password_changed', 'Password akun Anda telah diubah melalui pemulihan. Hubungi kantor desa bila ini bukan tindakan Anda.', 'user', $user->public_id, NULL);
	}

	public function change_password($user, $current, $new, $confirm)
	{
		if ( ! password_verify((string) $current, (string) $user->password_hash))
		{
			throw new DomainRuleException('Password saat ini tidak sesuai.', 422, array('current_password' => 'Password saat ini tidak sesuai.'));
		}
		$policy = $this->password_policy_error($new, $user->username);
		if ($policy !== NULL)
		{
			throw new DomainRuleException($policy, 422, array('password' => $policy));
		}
		if ((string) $new !== (string) $confirm)
		{
			throw new DomainRuleException('Konfirmasi password tidak sama.', 422, array('password_confirm' => 'Konfirmasi password tidak sama.'));
		}
		if (password_verify((string) $new, (string) $user->password_hash))
		{
			throw new DomainRuleException('Password baru harus berbeda dari password saat ini.', 422, array('password' => 'Gunakan password yang berbeda.'));
		}
		$this->CI->user_model->update($user->id, array('password_hash' => $this->hash_password($new), 'must_change_password' => 0));
		$this->CI->db->where(array('user_id' => (int) $user->id, 'purpose' => 'reset'))->where('used_at IS NULL', NULL, FALSE)
			->update('account_tokens', array('revoked_at' => utc_now()));
		$this->revoke_other_sessions($user->id, $user->session_row_id);
		$this->CI->audit->log('auth.password_changed', 'user', $user->public_id, array(), (int) $user->id);
	}

	// ------------------------------------------------------------------
	// MFA TOTP
	// ------------------------------------------------------------------

	public function mfa_enabled($user_id)
	{
		$row = $this->CI->db->get_where('user_mfa', array('user_id' => (int) $user_id))->row();
		return $row && $row->enabled_at !== NULL;
	}

	/** @return string secret base32 (ditampilkan sekali untuk dimasukkan ke aplikasi autentikator) */
	public function mfa_begin_setup($user_id)
	{
		if ($this->mfa_enabled($user_id))
		{
			throw new DomainRuleException('MFA sudah aktif.');
		}
		$secret = $this->CI->totp->generate_secret();
		$now = utc_now();
		db_must($this->CI->db->query(
			'INSERT INTO user_mfa (user_id, secret_ciphertext, key_version, enabled_at, last_used_step, created_at, updated_at) VALUES (?, ?, ?, NULL, NULL, ?, ?)
			 ON DUPLICATE KEY UPDATE secret_ciphertext = VALUES(secret_ciphertext), key_version = VALUES(key_version), enabled_at = NULL, last_used_step = NULL, updated_at = VALUES(updated_at)',
			array((int) $user_id, $this->CI->crypto->encrypt($secret, 'mfa'), $this->CI->crypto->key_version(), $now, $now)
		), 'user_mfa.setup');
		return $secret;
	}

	public function mfa_pending_secret($user_id)
	{
		$row = $this->CI->db->get_where('user_mfa', array('user_id' => (int) $user_id))->row();
		if ( ! $row OR $row->enabled_at !== NULL)
		{
			return NULL;
		}
		return $this->CI->crypto->decrypt($row->secret_ciphertext, 'mfa');
	}

	/** @return string[] recovery code plaintext (tampilkan sekali) */
	public function mfa_confirm_setup($user_id, $code)
	{
		$row = $this->CI->db->get_where('user_mfa', array('user_id' => (int) $user_id))->row();
		if ( ! $row OR $row->enabled_at !== NULL)
		{
			throw new DomainRuleException('Mulai pengaturan MFA terlebih dahulu.');
		}
		$secret = $this->CI->crypto->decrypt($row->secret_ciphertext, 'mfa');
		$step = $this->CI->totp->verify($secret, $code, $this->CI->clock->timestamp());
		if ($step === FALSE)
		{
			throw new DomainRuleException('Kode autentikator tidak sesuai. Periksa jam perangkat Anda.', 422, array('code' => 'Kode tidak sesuai.'));
		}
		$codes = array();
		db_transaction(function () use ($user_id, $step, &$codes) {
			$now = utc_now();
			$this->CI->db->where('user_id', (int) $user_id)->update('user_mfa', array('enabled_at' => $now, 'last_used_step' => $step, 'updated_at' => $now));
			$this->CI->db->delete('mfa_recovery_codes', array('user_id' => (int) $user_id));
			for ($i = 0; $i < 10; $i++)
			{
				$plain = $this->CI->crypto->random_code(10);
				$codes[] = $this->CI->crypto->group_code($plain, 5);
				db_must($this->CI->db->insert('mfa_recovery_codes', array(
					'user_id' => (int) $user_id,
					'code_hash' => $this->CI->crypto->hmac('mfa-recovery|'.$plain, 'token'),
					'created_at' => $now,
				)), 'mfa_recovery_codes');
			}
		});
		$user = $this->CI->user_model->find($user_id);
		$this->CI->audit->log('auth.mfa_enabled', 'user', $user->public_id, array(), (int) $user_id);
		return $codes;
	}

	public function mfa_verify($user_id, $code)
	{
		$row = $this->CI->db->get_where('user_mfa', array('user_id' => (int) $user_id))->row();
		if ( ! $row OR $row->enabled_at === NULL)
		{
			return FALSE;
		}
		$code = trim((string) $code);
		if (preg_match('/^\d{6}$/', preg_replace('/\s+/', '', $code)))
		{
			$secret = $this->CI->crypto->decrypt($row->secret_ciphertext, 'mfa');
			$step = $this->CI->totp->verify($secret, $code, $this->CI->clock->timestamp(), $row->last_used_step);
			if ($step === FALSE)
			{
				return FALSE;
			}
			$this->CI->db->where('user_id', (int) $user_id)->update('user_mfa', array('last_used_step' => $step, 'updated_at' => utc_now()));
			return TRUE;
		}
		// Recovery code sekali pakai.
		$plain = $this->CI->crypto->normalize_code($code);
		if (strlen($plain) !== 10)
		{
			return FALSE;
		}
		$hash = $this->CI->crypto->hmac('mfa-recovery|'.$plain, 'token');
		$this->CI->db->where(array('user_id' => (int) $user_id, 'code_hash' => $hash))->where('used_at IS NULL', NULL, FALSE)
			->update('mfa_recovery_codes', array('used_at' => utc_now()));
		if ($this->CI->db->affected_rows() === 1)
		{
			$user = $this->CI->user_model->find($user_id);
			$this->CI->audit->log('auth.mfa_recovery_used', 'user', $user->public_id, array(), (int) $user_id);
			return TRUE;
		}
		return FALSE;
	}

	public function mfa_disable($user_id, $actor_user_id)
	{
		db_transaction(function () use ($user_id) {
			$this->CI->db->delete('mfa_recovery_codes', array('user_id' => (int) $user_id));
			$this->CI->db->delete('user_mfa', array('user_id' => (int) $user_id));
		});
		$user = $this->CI->user_model->find($user_id);
		$this->CI->audit->log('auth.mfa_disabled', 'user', $user->public_id, array(), (int) $actor_user_id);
	}

	public function remaining_recovery_codes($user_id)
	{
		return (int) $this->CI->db->where('user_id', (int) $user_id)->where('used_at IS NULL', NULL, FALSE)->count_all_results('mfa_recovery_codes');
	}
}
