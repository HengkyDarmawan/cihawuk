<?php

require_once __DIR__.'/HttpTestCase.php';

/**
 * "Login sebagai": super admin dapat melihat dashboard persis seperti pengguna lain,
 * tetapi tidak dapat memakai sesi itu untuk tindakan sensitif, dan pelaku sebenarnya
 * selalu tercatat di audit.
 */
class ImpersonationHttpTest extends HttpTestCase {

	const PASSWORD = 'KataSandiUjiCoba2026';

	protected function setUp(): void
	{
		parent::setUp();
		$this->truncate_service_data();
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		$this->truncate_service_data();
	}

	protected function start(array $target_roles = array('content_editor'))
	{
		$admin = $this->make_user('sa.imp.test', array('super_admin'));
		$target = $this->make_user('target.imp.test', $target_roles);
		$this->assertSame(303, $this->login('sa.imp.test', self::PASSWORD, 'sa')['status']);
		return array($admin, $target);
	}

	protected function impersonate($target, $browser = 'sa')
	{
		return $this->post_form('admin/pengguna/'.$target->public_id,
			'admin/pengguna/'.$target->public_id.'/login-sebagai', array(), $browser);
	}

	public function test_admin_sees_dashboard_as_target_and_returns(): void
	{
		list($admin, $target) = $this->start();
		$before_cookie = $this->session_cookie('sa');

		$go = $this->impersonate($target);
		$this->assertSame(303, $go['status']);
		$this->assertStringEndsWith('/admin', $go['location']);
		$this->assertNotSame($before_cookie, $this->session_cookie('sa'), 'ID sesi berganti saat mulai menyamar');

		$dash = $this->get('admin', 'sa');
		$this->assertSame(200, $dash['status']);
		$this->assertStringContainsString('login sebagai Pengguna target.imp.test', $dash['body']);
		// Menu mengikuti izin target: editor konten tidak mengelola pengguna.
		$this->assertStringNotContainsString('href="'.$this->url('admin/pengguna').'"', $dash['body']);
		$this->assertSame(403, $this->get('admin/pengguna', 'sa')['status']);

		$row = $this->CI->db->where('user_id', (int) $target->id)->where('revoked_at IS NULL', NULL, FALSE)
			->get('user_sessions')->row();
		$this->assertNotNull($row);
		$this->assertSame((int) $admin->id, (int) $row->impersonator_user_id);

		$back = $this->post_form('admin', 'akun/kembali', array(), 'sa');
		$this->assertSame(303, $back['status']);
		$this->assertStringContainsString('admin/pengguna/'.$target->public_id, $back['location']);
		$this->assertSame(200, $this->get('admin/pengguna', 'sa')['status'], 'Hak super admin kembali');
		$this->assertNotNull($this->CI->db->where('id', (int) $row->id)->get('user_sessions')->row('revoked_at'),
			'Sesi penyamaran dicabut');

		$actions = array();
		foreach ($this->CI->db->where('entity_id', $target->public_id)->like('action', 'auth.impersonation', 'after')
			->get('audit_logs')->result() as $log)
		{
			$actions[] = $log->action;
			$this->assertSame((int) $admin->id, (int) $log->actor_user_id);
		}
		$this->assertContains('auth.impersonation_started', $actions);
		$this->assertContains('auth.impersonation_ended', $actions);
	}

	public function test_audit_during_impersonation_records_the_real_actor(): void
	{
		list($admin, $target) = $this->start();
		$this->impersonate($target);
		$this->get('admin', 'sa');
		$logged = (int) $this->CI->db->where('impersonator_user_id', (int) $admin->id)->count_all_results('audit_logs');
		$this->assertGreaterThan(0, $logged, 'Entri audit selama penyamaran memuat impersonator_user_id');
		$before = (int) $this->CI->db->where('impersonator_user_id IS NOT NULL', NULL, FALSE)
			->where('actor_user_id', (int) $admin->id)->where('action', 'auth.login_success')->count_all_results('audit_logs');
		$this->assertSame(0, $before, 'Login biasa tidak pernah bertanda penyamaran');
	}

	public function test_sensitive_actions_are_blocked_while_impersonating(): void
	{
		// Role uji yang memegang roles.manage: halaman RBAC terbuka, tetapi setiap perubahan
		// memerlukan reautentikasi, dan reautentikasi tidak tersedia saat menyamar.
		$now = utc_now();
		$this->CI->db->insert('roles', array('code' => 'uji_rbac_imp', 'name' => 'Uji RBAC', 'is_system' => 0, 'is_staff' => 1,
			'preset_version' => 0, 'created_at' => $now, 'updated_at' => $now));
		$role_id = (int) $this->CI->db->insert_id();
		$perm = $this->CI->db->get_where('permissions', array('code' => 'roles.manage'))->row();
		$this->CI->db->insert('role_permissions', array('role_id' => $role_id, 'permission_id' => (int) $perm->id));
		try
		{
			list($admin, $target) = $this->start(array('uji_rbac_imp'));
			$this->impersonate($target);
			$this->assertSame(200, $this->get('admin/rbac/menu', 'sa')['status']);
			$save = $this->post_form('admin/rbac/menu', 'admin/rbac/menu', array(), 'sa');
			$this->assertSame(403, $save['status']);
		}
		finally
		{
			$this->CI->db->where('role_id', $role_id)->delete('user_roles');
			$this->CI->db->where('role_id', $role_id)->delete('role_permissions');
			$this->CI->db->where('id', $role_id)->delete('roles');
		}
	}

	public function test_account_security_is_read_only_while_impersonating(): void
	{
		list($admin, $target) = $this->start();
		$this->impersonate($target);
		$this->assertSame(200, $this->get('admin/akun', 'sa')['status']);
		$pw = $this->post_form('admin/akun', 'admin/akun/password', array(
			'current_password' => self::PASSWORD, 'new_password' => 'SandiBaruYangPanjang2026!', 'new_password_confirm' => 'SandiBaruYangPanjang2026!',
		), 'sa');
		$this->assertSame(403, $pw['status']);
		$confirm = $this->post_form('admin/akun', 'admin/akun/konfirmasi', array('password' => self::PASSWORD), 'sa');
		$this->assertSame(403, $confirm['status']);
	}

	public function test_cannot_impersonate_super_admin_or_self_or_without_permission(): void
	{
		list($admin, $target) = $this->start();
		$other_admin = $this->make_user('sa2.imp.test', array('super_admin'));
		$this->assertSame(403, $this->impersonate($other_admin)['status']);
		$this->assertSame(409, $this->impersonate($admin)['status']);

		// Pengguna tanpa users.impersonate ditolak.
		$this->make_user('op.imp.test', array('content_editor'));
		$this->assertSame(303, $this->login('op.imp.test', self::PASSWORD, 'op')['status']);
		$page = $this->get('admin', 'op');
		$denied = $this->request('POST', 'admin/pengguna/'.$target->public_id.'/login-sebagai', 'op',
			http_build_query(array('csrf_chw' => $this->csrf_from($page['body']))));
		$this->assertSame(403, $denied['status']);
	}

	public function test_logout_while_impersonating_ends_both_sessions(): void
	{
		list($admin, $target) = $this->start();
		$this->impersonate($target);
		$out = $this->post_form('admin', 'keluar', array(), 'sa');
		$this->assertSame(303, $out['status']);
		$this->assertSame(303, $this->get('admin', 'sa')['status'], 'Tidak ada sesi yang tersisa');
		$this->assertSame(0, (int) $this->CI->db->where_in('user_id', array((int) $admin->id, (int) $target->id))
			->where('revoked_at IS NULL', NULL, FALSE)->count_all_results('user_sessions'));
	}
}
