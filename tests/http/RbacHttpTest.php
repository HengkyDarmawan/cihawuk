<?php

require_once __DIR__.'/HttpTestCase.php';

/**
 * Halaman Role, Izin, dan Menu lewat HTTP: hanya untuk roles.manage, perubahan tercatat
 * di audit, dan menu yang disembunyikan tetap dijaga izin di server.
 */
class RbacHttpTest extends HttpTestCase {

	const PASSWORD = 'KataSandiUjiCoba2026';

	protected function setUp(): void
	{
		parent::setUp();
		$this->truncate_service_data();
		$this->clean();
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		$this->clean();
		$this->truncate_service_data();
	}

	protected function clean()
	{
		$db = $this->CI->db;
		foreach ($db->like('code', 'uji_', 'after')->get('roles')->result() as $role)
		{
			$db->where('role_id', (int) $role->id)->delete('user_roles');
			$db->where('role_id', (int) $role->id)->delete('role_permissions');
			$db->where('id', (int) $role->id)->delete('roles');
		}
		$db->query('DELETE FROM admin_menu_overrides');
		$db->query('DELETE FROM role_menu_hidden');
	}

	public function test_only_role_managers_open_the_page(): void
	{
		$this->make_user('editor.rbac.test', array('content_editor'));
		$this->login('editor.rbac.test', self::PASSWORD, 'ed');
		$this->assertSame(403, $this->get('admin/rbac', 'ed')['status']);

		$this->make_user('sa.rbac.test', array('super_admin'));
		$this->login('sa.rbac.test', self::PASSWORD, 'sa');
		// Role dikelola dari Pengguna & Akses; daftar lengkap tetap ada di ?semua=1.
		$this->assertSame(303, $this->get('admin/rbac', 'sa')['status']);
		$page = $this->get('admin/rbac?semua=1', 'sa');
		$this->assertSame(200, $page['status']);
		$this->assertStringContainsString('content_editor', $page['body']);
		$users = $this->get('admin/pengguna?role=admin_desa', 'sa');
		$this->assertSame(200, $users['status']);
		$this->assertStringContainsString('Atur akses role ini', $users['body']);
		$this->assertSame(200, $this->get('admin/rbac/izin', 'sa')['status']);
		$this->assertSame(200, $this->get('admin/rbac/menu', 'sa')['status']);
		$this->assertSame(200, $this->get('admin/rbac/role/content_editor', 'sa')['status']);
	}

	public function test_create_role_with_permissions_then_assign(): void
	{
		$this->make_user('sa.rbac.test', array('super_admin'));
		$this->login('sa.rbac.test', self::PASSWORD, 'sa');
		$res = $this->post_form('admin/rbac/role/baru', 'admin/rbac/role/baru', array(
			'code' => 'uji_loket', 'name' => 'Petugas Loket Uji', 'is_staff' => 1,
			'permissions' => array('facilities.edit', 'umkm.edit'),
		), 'sa');
		$this->assertSame(303, $res['status']);
		$role = $this->CI->db->get_where('roles', array('code' => 'uji_loket'))->row();
		$this->assertNotNull($role);
		$this->assertSame(2, (int) $this->CI->db->where('role_id', (int) $role->id)->count_all_results('role_permissions'));
		$this->assertSame(1, (int) $this->CI->db->where('action', 'rbac.role_created')->count_all_results('audit_logs'));
	}

	public function test_users_page_manages_roles_and_impersonation_in_one_place(): void
	{
		$this->make_user('sa.rbac.test', array('super_admin'));
		$target = $this->make_user('staf.rbac.test', array('petugas'));
		$other = $this->make_user('lain.rbac.test', array('resident'));
		$this->login('sa.rbac.test', self::PASSWORD, 'sa');

		$list = $this->get('admin/pengguna?role=petugas', 'sa');
		$this->assertSame(200, $list['status']);
		$this->assertStringContainsString($this->url('admin/pengguna/'.$target->public_id.'/login-sebagai'), $list['body']);
		$this->assertStringContainsString('Atur akses role ini', $list['body']);

		// Ganti role langsung dari tabel: kembali ke tab yang sama, satu role saja.
		$res = $this->post_form('admin/pengguna?role=petugas', 'admin/pengguna/'.$target->public_id.'/role', array(
			'operation' => 'set', 'role_code' => 'admin_desa', 'kembali' => 'admin/pengguna?role=petugas',
		), 'sa');
		$this->assertSame(303, $res['status']);
		$this->assertStringContainsString('admin/pengguna?role=petugas', (string) $res['location']);
		$this->assertSame(array('admin_desa'), $this->CI->user_model->role_codes($target->id));

		// Halaman role: tab Anggota dapat menambahkan pengguna ke role ini.
		$role_page = $this->get('admin/rbac/role/petugas', 'sa');
		$this->assertSame(200, $role_page['status']);
		$this->assertStringContainsString('Tambahkan pengguna ke role ini', $role_page['body']);
		$add = $this->post_form('admin/rbac/role/petugas', 'admin/rbac/role/petugas/anggota', array('user_public_id' => $other->public_id), 'sa');
		$this->assertSame(303, $add['status']);
		$this->assertSame(array('petugas'), $this->CI->user_model->role_codes($other->id));

		// Halaman Super Admin tidak menampilkan daftar izin untuk dicentang.
		$super = $this->get('admin/rbac/role/super_admin', 'sa');
		$this->assertStringContainsString('otomatis memegang semua akses', $super['body']);
		$this->assertStringNotContainsString('name="permissions[]"', $super['body']);
	}

	public function test_hidden_menu_disappears_but_url_is_still_guarded(): void
	{
		$this->make_user('editor.rbac.test', array('content_editor'));
		$editor_role = $this->CI->db->get_where('roles', array('code' => 'content_editor'))->row();
		$this->make_user('sa.rbac.test', array('super_admin'));
		$this->login('sa.rbac.test', self::PASSWORD, 'sa');
		$save = $this->post_form('admin/rbac/menu', 'admin/rbac/menu', array(
			'hidden' => array('media' => array((int) $editor_role->id)),
		), 'sa');
		$this->assertSame(303, $save['status']);

		$this->login('editor.rbac.test', self::PASSWORD, 'ed');
		$dash = $this->get('admin', 'ed');
		$this->assertStringNotContainsString('href="'.$this->url('admin/media').'"', $dash['body']);
		$this->assertStringContainsString('href="'.$this->url('admin/konten').'"', $dash['body']);
		// Izin tidak dicabut: URL tetap terbuka bagi pemegang izin.
		$this->assertSame(200, $this->get('admin/media', 'ed')['status']);
	}
}
