<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Dashboard pengelola (/admin). Wajib login + role staf; setiap aksi memeriksa permission.
 */
class Admin_Controller extends MY_Controller {

	/** @var object */
	protected $user;

	public function __construct()
	{
		parent::__construct();
		$this->no_store = TRUE;
		$this->start_session();
		$this->user = $this->auth->user();
		if ( ! $this->user)
		{
			$next = app_safe_redirect_path('/'.uri_string(), '/admin');
			if ($this->auth->end_reason)
			{
				$this->session->set_flashdata('flash', array('type' => 'warning', 'message' => 'Sesi Anda telah berakhir. Silakan masuk kembali.'));
			}
			$this->session->set_userdata('login_next', $next);
			redirect(site_url('masuk'), 'location', 303);
		}
		if ( ! $this->user_model->is_staff($this->user->id))
		{
			redirect(site_url('warga'), 'location', 303);
		}

		$class = $this->router->fetch_class();
		// Saat login sebagai, kewajiban akun milik target (ganti password, MFA) tidak dipaksakan:
		// pengelola asli sudah melewatinya, dan aksi keamanan akun diblokir selama penyamaran.
		$impersonating = $this->auth->is_impersonating();
		if ( ! $impersonating && (int) $this->user->must_change_password === 1 && $class !== 'akun')
		{
			redirect(site_url('admin/akun'), 'location', 303);
		}
		if ( ! $impersonating && $this->config->item('features', 'app')['mfa_enforce_privileged'] && $this->is_privileged()
			&& ! $this->auth->mfa_enabled($this->user->id) && $class !== 'akun')
		{
			$this->session->set_flashdata('flash', array('type' => 'warning', 'message' => 'Aktifkan autentikasi dua langkah (MFA) sebelum menggunakan dashboard pengelola.'));
			redirect(site_url('admin/akun'), 'location', 303);
		}

		$this->layout_data = array(
			'user' => $this->user,
			'area' => 'admin',
			'permissions' => $this->authz->permissions($this->user->id),
			'roles' => $this->user_model->roles($this->user->id),
			'unread_notifications' => $this->notifications->unread_count($this->user->id),
			'page_title' => 'Dashboard Pengelola',
			'nav_active' => $class,
			'impersonator' => $impersonating ? $this->auth->impersonator() : NULL,
		);
	}

	protected function is_privileged()
	{
		return count(array_intersect(array('users.assign_roles', 'roles.manage', 'settings.manage', 'tickets.view_identity', 'tickets.handle_confidential', 'content.publish'), $this->authz->permissions($this->user->id))) > 0;
	}

	protected function require_permission($permission)
	{
		if ( ! $this->authz->can($permission))
		{
			throw new AccessDeniedException('Missing '.$permission);
		}
	}

	protected function require_any(array $permissions)
	{
		if ( ! $this->authz->can_any($permissions))
		{
			throw new AccessDeniedException('Missing any of '.implode(',', $permissions));
		}
	}

	/**
	 * Tindakan sensitif memerlukan konfirmasi password dalam 10 menit terakhir, dan tidak
	 * pernah tersedia saat login sebagai pengguna lain.
	 */
	protected function require_reauth()
	{
		if ($this->auth->is_impersonating())
		{
			// Password target tidak diketahui pengelola, dan tindakan sensitif tidak boleh
			// dilakukan atas nama orang lain.
			throw new AccessDeniedException('Sensitive action blocked while impersonating');
		}
		if ( ! $this->auth->recently_reauthenticated())
		{
			$this->session->set_userdata('reauth_next', app_safe_redirect_path((string) $this->input->server('HTTP_REFERER') ? parse_url((string) $this->input->server('HTTP_REFERER'), PHP_URL_PATH) : '/admin', '/admin'));
			throw new DomainRuleException('Konfirmasi ulang password Anda untuk melanjutkan tindakan ini.', 428);
		}
	}

	protected function error_layout()
	{
		return 'dashboard';
	}
}
