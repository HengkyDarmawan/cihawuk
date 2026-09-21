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
		$page = $this->get('admin/rbac', 'sa');
		$this->assertSame(200, $page['status']);
		$this->assertStringContainsString('content_editor', $page['body']);
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

	public function test_hidden_menu_disappears_but_url_is_still_guarded(): void
	{
		$editor_role = $this->CI->db->get_where('roles', array('code' => 'content_editor'))->row();
		$this->make_user('sa.rbac.test', array('super_admin'));
		$this->login('sa.rbac.test', self::PASSWORD, 'sa');
		$save = $this->post_form('admin/rbac/menu', 'admin/rbac/menu', array(
			'hidden' => array('media' => array((int) $editor_role->id)),
		), 'sa');
		$this->assertSame(303, $save['status']);

		$this->make_user('editor.rbac.test', array('content_editor'));
		$this->login('editor.rbac.test', self::PASSWORD, 'ed');
		$dash = $this->get('admin', 'ed');
		$this->assertStringNotContainsString('href="'.$this->url('admin/media').'"', $dash['body']);
		$this->assertStringContainsString('href="'.$this->url('admin/konten').'"', $dash['body']);
		// Izin tidak dicabut: URL tetap terbuka bagi pemegang izin.
		$this->assertSame(200, $this->get('admin/media', 'ed')['status']);
	}
}
