<?php

require_once __DIR__.'/../CiTestCase.php';

/**
 * Pengelolaan role, izin, dan menu dashboard lewat RbacService.
 */
class RbacTest extends CiTestCase {

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('RbacService', NULL, 'rbac');
		$this->CI->load->library('AdminMenuService', NULL, 'admin_menu');
		$this->truncate_service_data();
		$this->clean();
	}

	protected function tearDown(): void
	{
		$this->clean();
		$this->truncate_service_data();
		parent::tearDown();
	}

	protected function clean()
	{
		$db = $this->CI->db;
		foreach ($db->like('code', 'uji_', 'after')->get('roles')->result() as $role)
		{
			$db->where('role_id', (int) $role->id)->delete('user_roles');
			$db->where('role_id', (int) $role->id)->delete('role_permissions');
			$db->where('role_id', (int) $role->id)->delete('role_menu_hidden');
			$db->where('id', (int) $role->id)->delete('roles');
		}
		$db->where('is_custom', 1)->like('code', 'uji', 'after')->delete('permissions');
		$db->query('DELETE FROM admin_menu_overrides');
		$db->query('DELETE FROM role_menu_hidden');
	}

	protected function new_role($code = 'uji_petugas', array $perms = array())
	{
		$role = $this->CI->rbac->save_role(array('code' => $code, 'name' => 'Petugas Uji', 'is_staff' => 1), NULL);
		if ($perms)
		{
			$this->CI->rbac->set_role_permissions($role, $perms, NULL);
		}
		return $role;
	}

	public function test_role_crud_and_permissions_take_effect(): void
	{
		$role = $this->new_role('uji_petugas', array('content.edit'));
		$this->assertSame(0, (int) $role->is_system);
		$user = $this->make_user('rbac.pemegang.test', array('uji_petugas'));
		$this->assertTrue($this->CI->authz->user_can($user->id, 'content.edit'));

		$diff = $this->CI->rbac->set_role_permissions($role, array('facilities.edit'), NULL);
		$this->assertSame(array('facilities.edit'), $diff['added']);
		$this->assertSame(array('content.edit'), $diff['removed']);
		$this->CI->authz->flush();
		$this->assertFalse($this->CI->authz->user_can($user->id, 'content.edit'));
		$this->assertTrue($this->CI->authz->user_can($user->id, 'facilities.edit'));

		$updated = $this->CI->rbac->save_role(array('name' => 'Petugas Lapangan', 'description' => 'Uji', 'is_staff' => 1), NULL, $role);
		$this->assertSame('Petugas Lapangan', $updated->name);
		$this->assertSame('uji_petugas', $updated->code, 'Kode role tidak pernah berubah');

		// Masih dipakai: tidak dapat dihapus.
		try
		{
			$this->CI->rbac->delete_role($updated);
			$this->fail('Role yang masih dipakai tidak boleh dihapus');
		}
		catch (DomainRuleException $e)
		{
			$this->assertSame(409, $e->http_status);
		}
		$this->CI->user_model->revoke_role($user->id, 'uji_petugas');
		$this->CI->rbac->delete_role($updated);
		$this->assertNull($this->CI->rbac->role('uji_petugas'));
	}

	public function test_system_role_cannot_be_deleted_and_codes_are_validated(): void
	{
		$this->expectException(DomainRuleException::class);
		$this->CI->rbac->delete_role($this->CI->rbac->role('admin_desa'));
	}

	public function test_invalid_or_duplicate_role_code_is_rejected(): void
	{
		foreach (array('Uji Spasi', 'x', 'super_admin', 'baru') as $code)
		{
			try
			{
				$this->CI->rbac->save_role(array('code' => $code, 'name' => 'Uji'), NULL);
				$this->fail('Kode "'.$code.'" harus ditolak');
			}
			catch (DomainRuleException $e)
			{
				$this->assertArrayHasKey('code', $e->errors);
			}
		}
	}

	public function test_super_admin_always_keeps_access_management(): void
	{
		$super = $this->CI->rbac->role('super_admin');
		try
		{
			$this->CI->rbac->set_role_permissions($super, array('audit.view'), NULL);
			$this->fail('Super Admin tidak boleh kehilangan roles.manage');
		}
		catch (DomainRuleException $e)
		{
			$this->assertStringContainsString('roles.manage', $e->getMessage());
		}
		$this->assertContains((int) $this->CI->rbac->permission('roles.manage')->id, $this->CI->rbac->role_permission_ids($super));
	}

	public function test_protected_roles_keep_their_access_type(): void
	{
		$resident = $this->CI->rbac->role('resident');
		$this->expectException(DomainRuleException::class);
		$this->CI->rbac->save_role(array('name' => $resident->name, 'is_staff' => 1), NULL, $resident);
	}

	public function test_custom_permissions_can_be_deleted_but_builtin_cannot(): void
	{
		$perm = $this->CI->rbac->save_permission(array('code' => 'uji.lihat', 'description' => 'Izin uji'));
		$this->assertSame(1, (int) $perm->is_custom);
		$role = $this->new_role('uji_izin', array('uji.lihat'));
		try
		{
			$this->CI->rbac->delete_permission($perm);
			$this->fail('Izin yang dipegang role tidak boleh dihapus');
		}
		catch (DomainRuleException $e)
		{
			$this->assertSame(409, $e->http_status);
		}
		$this->CI->rbac->set_role_permissions($role, array(), NULL);
		$this->CI->rbac->delete_permission($this->CI->rbac->permission('uji.lihat'));
		$this->assertNull($this->CI->rbac->permission('uji.lihat'));

		$this->expectException(DomainRuleException::class);
		$this->CI->rbac->delete_permission($this->CI->rbac->permission('content.edit'));
	}

	public function test_menu_hidden_only_when_every_role_hides_it(): void
	{
		$user = $this->make_user('rbac.menu.test', array('content_editor', 'content_publisher'));
		$editor = $this->CI->rbac->role('content_editor');
		$publisher = $this->CI->rbac->role('content_publisher');
		$perms = $this->CI->authz->permissions($user->id);
		$keys = function () use ($user, $perms) {
			$out = array();
			foreach ($this->CI->admin_menu->for_user($user->id, $perms) as $group)
			{
				foreach ($group['items'] as $item) { $out[] = $item['key']; }
			}
			return $out;
		};
		$this->assertContains('konten', $keys());

		$this->CI->rbac->save_menu(array('konten' => array('active' => 1)), array('konten' => array($editor->id)), NULL);
		$this->assertContains('konten', $keys(), 'Masih tampil karena role penerbit tidak menyembunyikannya');

		$this->CI->rbac->save_menu(array('konten' => array('active' => 1)), array('konten' => array($editor->id, $publisher->id)), NULL);
		$this->assertNotContains('konten', $keys());
		// Menyembunyikan menu tidak mencabut izin.
		$this->assertTrue($this->CI->authz->user_can($user->id, 'content.edit'));
	}

	public function test_menu_overrides_rename_reorder_and_lock(): void
	{
		$user = $this->make_user('rbac.menu2.test', array('super_admin'));
		$perms = $this->CI->authz->permissions($user->id);
		$items = array();
		foreach ($this->CI->admin_menu->items() as $key => $item)
		{
			$items[$key] = array('label' => $item['label'], 'group_label' => (string) $item['heading'], 'sort_order' => $item['default_order'], 'active' => 1);
		}
		$items['pengguna']['label'] = 'Akun Pengguna';
		$items['pengguna']['active'] = 0; // terkunci, tetap aktif
		$items['audit']['active'] = 0;
		$this->CI->rbac->save_menu($items, array(), NULL);

		$labels = array();
		foreach ($this->CI->admin_menu->for_user($user->id, $perms) as $group)
		{
			foreach ($group['items'] as $item) { $labels[$item['key']] = $item['label']; }
		}
		$this->assertSame('Akun Pengguna', $labels['pengguna']);
		$this->assertArrayNotHasKey('audit', $labels);
		// Hanya perbedaan dari bawaan yang disimpan.
		$this->assertSame(2, (int) $this->CI->db->count_all_results('admin_menu_overrides'));
	}

	public function test_preset_has_four_roles(): void
	{
		$this->CI->config->load('rbac', TRUE);
		$this->assertSame(array('super_admin', 'admin_desa', 'petugas', 'resident'), array_keys($this->CI->config->item('roles', 'rbac')));
		foreach (array_keys($this->CI->config->item('legacy_role_map', 'rbac')) as $legacy)
		{
			$role = $this->CI->rbac->role($legacy);
			$this->assertTrue($role === NULL OR (int) $role->is_system === 0, 'Role sistem lama '.$legacy.' harus sudah digabung');
		}
	}

	public function test_seeder_consolidates_legacy_system_roles_into_one_role_per_user(): void
	{
		require_once ROOTPATH.'database/seeds/MasterSeeder.php';
		$db = $this->CI->db;
		$now = utc_now();
		// Tiru database lama: role sistem granular yang masih dipegang pengguna.
		foreach (array('uji_lama_admin' => 'admin_desa', 'uji_lama_petugas' => 'petugas') as $code => $target)
		{
			$db->insert('roles', array('code' => $code, 'name' => $code, 'is_system' => 1, 'is_staff' => 1, 'preset_version' => 8, 'created_at' => $now, 'updated_at' => $now));
		}
		$both = $this->make_user('gabung.dua.test', array());
		$one = $this->make_user('gabung.satu.test', array());
		$this->CI->user_model->assign_role($both->id, 'uji_lama_admin');
		$this->CI->user_model->assign_role($both->id, 'uji_lama_petugas');
		$this->CI->user_model->assign_role($one->id, 'uji_lama_petugas');

		$this->CI->config->load('rbac', TRUE);
		$original = $this->CI->config->item('legacy_role_map', 'rbac');
		$this->CI->config->set_item('rbac', array_merge($this->CI->config->item('rbac'), array(
			'legacy_role_map' => array('uji_lama_admin' => 'admin_desa', 'uji_lama_petugas' => 'petugas'),
		)));
		try
		{
			$seeder = new MasterSeeder($this->CI);
			$method = new ReflectionMethod($seeder, 'consolidate_legacy_roles');
			$method->invoke($seeder);
			$method->invoke($seeder); // idempoten
		}
		finally
		{
			$this->CI->config->set_item('rbac', array_merge($this->CI->config->item('rbac'), array('legacy_role_map' => $original)));
		}

		$this->assertNull($this->CI->rbac->role('uji_lama_admin'));
		$this->assertNull($this->CI->rbac->role('uji_lama_petugas'));
		$this->assertSame(array('admin_desa'), $this->CI->user_model->role_codes($both->id), 'Hanya role tertinggi yang tersisa');
		$this->assertSame(array('petugas'), $this->CI->user_model->role_codes($one->id));
	}

	public function test_set_user_role_replaces_roles_and_protects_last_super_admin(): void
	{
		$actor = $this->make_user('pengatur.test', array('super_admin'));
		$target = $this->make_user('target.role.test', array('petugas', 'content_editor'));
		$this->assertTrue($this->CI->rbac->set_user_role($target, 'admin_desa', $actor));
		$this->assertSame(array('admin_desa'), $this->CI->user_model->role_codes($target->id));
		$this->assertFalse($this->CI->rbac->set_user_role($target, 'admin_desa', $actor), 'Tidak ada perubahan');

		try
		{
			$this->CI->rbac->set_user_role($actor, 'petugas', $actor);
			$this->fail('Role sendiri tidak boleh diubah');
		}
		catch (DomainRuleException $e)
		{
			$this->assertSame(409, $e->http_status);
		}
		$this->assertSame(1, (int) $this->CI->db->where('action', 'account.role_set')->count_all_results('audit_logs'));
	}

	public function test_module_labels_are_human_readable(): void
	{
		$labels = $this->CI->rbac->module_labels(array('assets.view', 'asset_audits.perform', 'warehouse.view', 'tickets.verify'));
		$this->assertSame(array('Laporan warga', 'Aset', 'Gudang persediaan'), $labels);
		$groups = $this->CI->rbac->module_permissions();
		$this->assertArrayHasKey('Aset', $groups);
		$this->assertArrayNotHasKey('assets', $groups);
	}
}
