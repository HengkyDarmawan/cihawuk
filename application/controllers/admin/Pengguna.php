<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pengelolaan akun: daftar, pendaftaran warga oleh petugas, aktivasi manual,
 * status akun, role, lingkup unit dan pemulihan akun.
 * Perubahan hanya melalui POST dengan permission per aksi.
 */
class Pengguna extends Admin_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('AuthService', NULL, 'auth');
		$this->layout_data['nav_active'] = 'pengguna';
	}

	public function index()
	{
		$this->require_any(array('users.manage', 'users.create_resident', 'residents.verify', 'users.assign_roles'));
		$status = (string) $this->input->get('status');
		$role = (string) $this->input->get('role');
		$keyword = mb_substr(trim((string) $this->input->get('q')), 0, 60);
		$page = max(1, (int) $this->input->get('hal'));
		$per_page = 20;

		$build = function () use ($status, $role, $keyword) {
			$this->db->from('users u');
			if ($role !== '')
			{
				$this->db->join('user_roles ur', 'ur.user_id = u.id')->join('roles r', 'r.id = ur.role_id')->where('r.code', $role);
			}
			if ($status !== '' && isset(app_config('account_statuses', array())[$status]))
			{
				$this->db->where('u.account_status', $status);
			}
			if ($keyword !== '')
			{
				$this->db->group_start()->like('u.username', $keyword)->or_like('u.display_name', $keyword)->group_end();
			}
		};
		$build();
		$total = (int) $this->db->count_all_results();
		$build();
		$users = $this->db->select('u.id, u.public_id, u.username, u.display_name, u.email, u.account_status, u.last_login_at, u.created_at')
			->order_by('u.created_at', 'DESC')->limit($per_page, ($page - 1) * $per_page)->get()->result();
		foreach ($users as $user)
		{
			$user->roles = $this->user_model->roles($user->id);
		}

		$this->render('admin/pengguna_index', array(
			'page_title' => 'Pengguna',
			'users' => $users,
			'roles' => $this->db->order_by('name')->get('roles')->result(),
			'statuses' => app_config('account_statuses'),
			'filters' => array('status' => $status, 'role' => $role, 'q' => $keyword),
			'page' => $page,
			'pages' => max(1, (int) ceil($total / $per_page)),
			'total' => $total,
			'extra_js' => array('vendor/sweetalert2/sweetalert2.min.js'),
		), 'dashboard');
	}

	public function create()
	{
		$this->require_permission('users.create_resident');
		$this->render('admin/pengguna_create', array('page_title' => 'Daftarkan warga'), 'dashboard');
	}

	public function store()
	{
		$this->require_method('post');
		$this->require_permission('users.create_resident');
		$data = array(
			'display_name' => trim($this->post_string('display_name', 100)),
			'username' => trim($this->post_string('username', 60)),
			'email' => trim($this->post_string('email', 191)),
			'phone' => trim($this->post_string('phone', 30)),
		);
		$this->old_input = $data;
		try
		{
			$result = $this->auth->create_resident_by_staff($data, (int) $this->user->id);
		}
		catch (DomainRuleException $e)
		{
			$this->form_errors = $e->errors;
			$this->output->set_status_header($e->http_status);
			$this->render('admin/pengguna_create', array('page_title' => 'Daftarkan warga', 'create_error' => $e->getMessage()), 'dashboard');
			return;
		}
		// Kode aktivasi ditampilkan sekali kepada petugas untuk diserahkan kepada warga.
		$this->session->set_flashdata('activation_receipt', array(
			'token' => $result['token'],
			'expires_at' => $result['expires_at'],
			'url' => site_url('aktivasi?token='.rawurlencode($result['token'])),
		));
		$user = $this->user_model->find($result['user_id']);
		$this->flash('success', 'Akun warga '.$user->username.' dibuat. Berikan kode aktivasi kepada warga; warga menetapkan passwordnya sendiri.');
		redirect(site_url('admin/pengguna/'.$user->public_id), 'location', 303);
	}

	protected function load_user($public_id)
	{
		$user = $this->user_model->find_by_public_id($public_id);
		if ( ! $user)
		{
			throw new DomainRuleException('Pengguna tidak ditemukan.', 404);
		}
		return $user;
	}

	public function show($public_id)
	{
		$this->require_any(array('users.manage', 'users.create_resident', 'residents.verify', 'users.assign_roles'));
		$user = $this->load_user($public_id);
		$this->render('admin/pengguna_detail', $this->detail_data($user), 'dashboard');
	}

	protected function detail_data($user, array $extra = array())
	{
		$assignable_roles = array();
		foreach ($this->db->order_by('name')->get('roles')->result() as $role)
		{
			$assignable_roles[$role->code] = $role->name;
		}
		$units = array();
		foreach ($this->db->where('active', 1)->order_by('name')->get('organizational_units')->result() as $unit)
		{
			$units[(string) $unit->id] = $unit->name;
		}
		return array_merge(array(
			'page_title' => 'Pengguna '.$user->username,
			'account' => $user,
			'profile' => $this->user_model->resident_profile($user->id),
			'user_roles' => $this->user_model->roles($user->id),
			'assignable_roles' => $assignable_roles,
			'scopes' => $this->db->select('s.*, o.name AS unit_name')->from('user_unit_scopes s')
				->join('organizational_units o', 'o.id = s.unit_id')->where('s.user_id', (int) $user->id)->get()->result(),
			'units' => $units,
			'sessions' => $this->auth->active_sessions($user->id),
			'statuses' => app_config('account_statuses'),
			'mfa_enabled' => $this->auth->mfa_enabled($user->id),
			'is_last_super_admin' => in_array('super_admin', $this->user_model->role_codes($user->id), TRUE)
				&& $this->user_model->count_active_super_admins($user->id) === 0,
			'activation_receipt' => $this->session->flashdata('activation_receipt'),
			'recovery_receipt' => $this->session->flashdata('recovery_receipt'),
			'audit' => $this->authz->can('audit.view')
				? $this->db->where('entity_type', 'user')->where('entity_id', $user->public_id)->order_by('id', 'DESC')->limit(10)->get('audit_logs')->result()
				: array(),
			'extra_js' => array('vendor/sweetalert2/sweetalert2.min.js'),
		), $extra);
	}

	/**
	 * Aksi pada akun. Setiap aksi memeriksa permission sendiri dan tidak pernah
	 * membaca role/status langsung dari field POST tanpa allowlist.
	 */
	public function action($public_id, $action)
	{
		$this->require_method('post');
		$user = $this->load_user($public_id);
		$handlers = array(
			'aktivasi' => 'do_activate', 'status' => 'do_status', 'role' => 'do_role',
			'lingkup' => 'do_scope', 'pemulihan' => 'do_recovery', 'verifikasi' => 'do_verify_profile',
			'mfa-reset' => 'do_mfa_reset', 'sesi' => 'do_revoke_sessions', 'login-sebagai' => 'do_impersonate',
		);
		if ( ! isset($handlers[$action]))
		{
			throw new DomainRuleException('Aksi tidak dikenal.', 404);
		}
		$method = $handlers[$action];
		$this->{$method}($user);
	}

	protected function back($user, $type, $message)
	{
		$this->flash($type, $message);
		redirect(site_url('admin/pengguna/'.$user->public_id), 'location', 303);
	}

	protected function do_activate($user)
	{
		$this->require_permission('residents.verify');
		$note = $this->post_string('note', 255);
		if ($user->password_hash === NULL)
		{
			// Warga belum menetapkan password: terbitkan kode aktivasi baru.
			$token = $this->auth->create_token($user->id, 'activation', 7 * 86400, 'front_desk', (int) $this->user->id);
			$this->session->set_flashdata('activation_receipt', array(
				'token' => $token,
				'expires_at' => $this->clock->plus_seconds(7 * 86400),
				'url' => site_url('aktivasi?token='.rawurlencode($token)),
			));
			$this->audit->log('account.activation_reissued', 'user', $user->public_id, array(), (int) $this->user->id);
			$this->back($user, 'success', 'Kode aktivasi baru dibuat. Berikan kepada warga; kode hanya tampil sekali.');
			return;
		}
		$this->auth->activate_manually($user->id, (int) $this->user->id, $note);
		$this->back($user, 'success', 'Akun diaktifkan melalui review manual.');
	}

	protected function do_status($user)
	{
		$this->require_permission('users.manage');
		$status = (string) $this->input->post('account_status');
		$reason = $this->post_string('reason', 255);
		if ( ! isset(app_config('account_statuses', array())[$status]))
		{
			throw new DomainRuleException('Status akun tidak valid.', 422);
		}
		if ($status !== 'active' && in_array('super_admin', $this->user_model->role_codes($user->id), TRUE)
			&& $this->user_model->count_active_super_admins($user->id) === 0)
		{
			throw new DomainRuleException('Tidak dapat menonaktifkan Super Admin aktif terakhir. Tetapkan Super Admin pengganti terlebih dahulu.', 409);
		}
		if ((int) $user->id === (int) $this->user->id && $status !== 'active')
		{
			throw new DomainRuleException('Anda tidak dapat menonaktifkan akun Anda sendiri.', 409);
		}
		if (mb_strlen(trim($reason)) < 5)
		{
			throw new DomainRuleException('Tuliskan alasan perubahan status (minimal 5 karakter).', 422, array('reason' => 'Alasan wajib diisi.'));
		}
		$this->user_model->update($user->id, array('account_status' => $status, 'status_reason' => $reason));
		if ($status !== 'active')
		{
			$this->auth->logout_all($user->id, 'status_change');
		}
		$this->audit->log('account.status_changed', 'user', $user->public_id, array('status' => $status, 'reason' => mb_substr($reason, 0, 200)), (int) $this->user->id);
		$this->back($user, 'success', 'Status akun diperbarui menjadi '.config_label('account_statuses', $status).'.');
	}

	protected function do_role($user)
	{
		$this->require_permission('users.assign_roles');
		$this->require_reauth();
		$role = (string) $this->input->post('role_code');
		$operation = (string) $this->input->post('operation');
		$role_row = $this->db->get_where('roles', array('code' => $role))->row();
		if ( ! $role_row OR ! in_array($operation, array('add', 'remove'), TRUE))
		{
			throw new DomainRuleException('Role atau operasi tidak valid.', 422);
		}
		if ((int) $user->id === (int) $this->user->id)
		{
			// Akun tidak dapat menaikkan/mengubah rolenya sendiri.
			throw new DomainRuleException('Perubahan role untuk akun sendiri harus dilakukan oleh Super Admin lain.', 409);
		}
		if ($operation === 'remove' && $role === 'super_admin' && $this->user_model->count_active_super_admins($user->id) === 0)
		{
			throw new DomainRuleException('Tidak dapat mencabut Super Admin aktif terakhir.', 409);
		}
		if ($operation === 'add')
		{
			$this->user_model->assign_role($user->id, $role, (int) $this->user->id);
		}
		else
		{
			$this->user_model->revoke_role($user->id, $role);
		}
		// Perubahan hak memutus sesi aktif pengguna tersebut.
		$this->auth->logout_all($user->id, 'role_change');
		$this->authz->flush($user->id);
		$this->audit->log('account.role_'.$operation, 'user', $user->public_id, array('role' => $role), (int) $this->user->id);
		$this->back($user, 'success', 'Role '.$role_row->name.($operation === 'add' ? ' ditambahkan.' : ' dicabut.').' Sesi pengguna tersebut dikeluarkan.');
	}

	/**
	 * Login sebagai pengguna ini untuk memeriksa tampilan dan hak aksesnya. Sesi pengelola
	 * disimpan dan dipulihkan lewat tombol "Kembali ke akun saya"; semua aksi selama
	 * penyamaran tercatat di audit dengan impersonator_user_id.
	 */
	protected function do_impersonate($user)
	{
		$this->require_permission('users.impersonate');
		$this->require_reauth();
		$area = $this->auth->impersonate($this->user, $user);
		$this->session->set_flashdata('flash', array('type' => 'info',
			'message' => 'Anda sekarang login sebagai '.$user->display_name.'. Sesi ini berakhir otomatis dalam '.AuthService::IMPERSONATION_TTL.' menit.'));
		if ($area === 'admin')
		{
			redirect(site_url('admin'), 'location', 303);
		}
		elseif (in_array('resident', $this->user_model->role_codes($user->id), TRUE))
		{
			redirect(site_url('warga'), 'location', 303);
		}
		else
		{
			redirect(site_url('/'), 'location', 303);
		}
	}

	protected function do_scope($user)
	{
		$this->require_permission('users.assign_roles');
		$unit_id = (int) $this->input->post('unit_id');
		$scope_type = (string) $this->input->post('scope_type');
		$operation = (string) $this->input->post('operation');
		if ( ! in_array($scope_type, array('member', 'monitor', 'all'), TRUE) OR ! in_array($operation, array('add', 'remove'), TRUE))
		{
			throw new DomainRuleException('Lingkup tidak valid.', 422);
		}
		if ($this->db->where(array('id' => $unit_id, 'active' => 1))->count_all_results('organizational_units') === 0)
		{
			throw new DomainRuleException('Unit tidak ditemukan.', 422);
		}
		if ($operation === 'add')
		{
			db_must($this->db->query(
				'INSERT IGNORE INTO user_unit_scopes (user_id, unit_id, scope_type, created_at) VALUES (?, ?, ?, ?)',
				array((int) $user->id, $unit_id, $scope_type, utc_now())
			), 'user_unit_scopes.add');
		}
		else
		{
			$this->db->delete('user_unit_scopes', array('user_id' => (int) $user->id, 'unit_id' => $unit_id, 'scope_type' => $scope_type));
		}
		$this->authz->flush($user->id);
		$this->audit->log('account.scope_'.$operation, 'user', $user->public_id, array('unit_id' => $unit_id, 'scope' => $scope_type), (int) $this->user->id);
		$this->back($user, 'success', 'Lingkup unit diperbarui.');
	}

	protected function do_recovery($user)
	{
		$this->require_permission('users.manage');
		$this->require_reauth();
		$reason = $this->post_string('reason', 500);
		$result = $this->auth->issue_manual_recovery($user->id, (int) $this->user->id, $reason);
		$this->session->set_flashdata('recovery_receipt', array(
			'token' => $result['token'],
			'purpose' => $result['purpose'],
			'url' => site_url(($result['purpose'] === 'activation' ? 'aktivasi' : 'reset-password').'?token='.rawurlencode($result['token'])),
		));
		$this->back($user, 'success', 'Kode pemulihan dibuat dan hanya ditampilkan sekali. Serahkan langsung kepada pemilik akun.');
	}

	protected function do_verify_profile($user)
	{
		$this->require_permission('residents.verify');
		$status = (string) $this->input->post('verification_status');
		$reason = $this->post_string('reason', 255);
		if ( ! isset(app_config('resident_verification_statuses', array())[$status]))
		{
			throw new DomainRuleException('Status verifikasi tidak valid.', 422);
		}
		db_must($this->db->where('user_id', (int) $user->id)->update('resident_profiles', array(
			'verification_status' => $status,
			'verified_by' => (int) $this->user->id,
			'verified_at' => ($status === 'verified') ? utc_now() : NULL,
			'review_reason' => $reason,
			'updated_at' => utc_now(),
		)), 'resident_profiles.verify');
		$this->audit->log('resident.verification_'.$status, 'user', $user->public_id, array('reason' => mb_substr($reason, 0, 200)), (int) $this->user->id);
		$this->notifications->notify($user->id, 'account.verification',
			'Status verifikasi profil Anda diperbarui menjadi '.config_label('resident_verification_statuses', $status).'.', 'user', $user->public_id, '/warga/profil');
		$this->back($user, 'success', 'Status verifikasi warga diperbarui.');
	}

	protected function do_mfa_reset($user)
	{
		$this->require_permission('users.manage');
		$this->require_reauth();
		$reason = $this->post_string('reason', 255);
		if (mb_strlen(trim($reason)) < 10)
		{
			throw new DomainRuleException('Tuliskan dasar pemeriksaan identitas sebelum mereset MFA (minimal 10 karakter).', 422, array('reason' => 'Alasan wajib diisi.'));
		}
		$this->auth->mfa_disable($user->id, (int) $this->user->id);
		$this->audit->log('account.mfa_reset_by_admin', 'user', $user->public_id, array('reason' => mb_substr($reason, 0, 200)), (int) $this->user->id);
		$this->back($user, 'success', 'MFA akun tersebut direset. Minta pemilik akun mengaktifkannya kembali.');
	}

	protected function do_revoke_sessions($user)
	{
		$this->require_permission('users.manage');
		$this->auth->logout_all($user->id, 'admin_revoke');
		$this->audit->log('account.sessions_revoked_by_admin', 'user', $user->public_id, array(), (int) $this->user->id);
		$this->back($user, 'success', 'Semua sesi pengguna tersebut dikeluarkan.');
	}
}
