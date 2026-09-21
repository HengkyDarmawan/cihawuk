<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Role, izin, dan menu dashboard.
 *
 * Seluruh halaman memakai `roles.manage`. Setiap perubahan memerlukan konfirmasi ulang
 * password dan tercatat di audit log (`rbac.*`). Aturan domain ada di RbacService.
 */
class Rbac extends Admin_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('RbacService', NULL, 'rbac');
		$this->layout_data['nav_active'] = 'rbac';
	}

	public function index()
	{
		$this->require_permission('roles.manage');
		$this->render('admin/rbac_roles', array(
			'page_title' => 'Role, Izin, dan Menu',
			'tab' => 'role',
			'roles' => $this->rbac->roles(),
		), 'dashboard');
	}

	public function role_create()
	{
		$this->require_permission('roles.manage');
		$this->render('admin/rbac_role_form', array(
			'page_title' => 'Tambah role',
			'tab' => 'role',
			'role' => NULL,
			'groups' => $this->rbac->grouped_permissions(),
			'held' => array(),
		), 'dashboard');
	}

	public function role_store()
	{
		$this->require_method('post');
		$this->require_permission('roles.manage');
		$this->require_reauth();
		$role = $this->rbac->save_role($this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id);
		$this->rbac->set_role_permissions($role, (array) $this->input->post('permissions'), (int) $this->user->id);
		$this->flash('success', 'Role "'.$role->name.'" dibuat.');
		redirect(site_url('admin/rbac/role/'.rawurlencode($role->code)), 'location', 303);
	}

	public function role_edit($code)
	{
		$this->require_permission('roles.manage');
		$role = $this->require_role($code);
		$this->render('admin/rbac_role_form', array(
			'page_title' => 'Role: '.$role->name,
			'tab' => 'role',
			'role' => $role,
			'groups' => $this->rbac->grouped_permissions(),
			'held' => $this->rbac->role_permission_ids($role),
			'user_count' => (int) $this->db->where('role_id', (int) $role->id)->count_all_results('user_roles'),
		), 'dashboard');
	}

	public function role_update($code)
	{
		$this->require_method('post');
		$this->require_permission('roles.manage');
		$this->require_reauth();
		$role = $this->require_role($code);
		$role = $this->rbac->save_role($this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id, $role);
		$diff = $this->rbac->set_role_permissions($role, (array) $this->input->post('permissions'), (int) $this->user->id);
		$this->flash('success', 'Role disimpan: '.count($diff['added']).' izin ditambah, '.count($diff['removed']).' dicabut.');
		redirect(site_url('admin/rbac/role/'.rawurlencode($role->code)), 'location', 303);
	}

	public function role_delete($code)
	{
		$this->require_method('post');
		$this->require_permission('roles.manage');
		$this->require_reauth();
		$role = $this->require_role($code);
		$this->rbac->delete_role($role);
		$this->flash('success', 'Role "'.$role->name.'" dihapus.');
		redirect(site_url('admin/rbac'), 'location', 303);
	}

	public function permissions()
	{
		$this->require_permission('roles.manage');
		$this->render('admin/rbac_permissions', array(
			'page_title' => 'Daftar izin',
			'tab' => 'izin',
			'groups' => $this->rbac->grouped_permissions(),
		), 'dashboard');
	}

	public function permission_store()
	{
		$this->require_method('post');
		$this->require_permission('roles.manage');
		$this->require_reauth();
		$perm = $this->rbac->save_permission($this->input->post(NULL, FALSE) ?: array());
		$this->flash('success', 'Izin "'.$perm->code.'" dibuat. Izin baru baru berarti bila dipakai kode atau menu.');
		redirect(site_url('admin/rbac/izin'), 'location', 303);
	}

	public function permission_update($code)
	{
		$this->require_method('post');
		$this->require_permission('roles.manage');
		$this->require_reauth();
		$perm = $this->require_permission_row($code);
		$this->rbac->save_permission($this->input->post(NULL, FALSE) ?: array(), $perm);
		$this->flash('success', 'Deskripsi izin "'.$perm->code.'" diperbarui.');
		redirect(site_url('admin/rbac/izin'), 'location', 303);
	}

	public function permission_delete($code)
	{
		$this->require_method('post');
		$this->require_permission('roles.manage');
		$this->require_reauth();
		$perm = $this->require_permission_row($code);
		$this->rbac->delete_permission($perm);
		$this->flash('success', 'Izin "'.$perm->code.'" dihapus.');
		redirect(site_url('admin/rbac/izin'), 'location', 303);
	}

	public function menu()
	{
		$this->require_permission('roles.manage');
		$this->load->library('AdminMenuService', NULL, 'admin_menu');
		$this->render('admin/rbac_menu', array(
			'page_title' => 'Menu dashboard',
			'tab' => 'menu',
			'items' => $this->admin_menu->items(),
			'overrides' => $this->admin_menu->overrides(),
			'hidden' => $this->admin_menu->hidden_by_role(),
			'locked' => $this->admin_menu->locked_keys(),
			'roles' => $this->db->where('is_staff', 1)->order_by('name')->get('roles')->result(),
		), 'dashboard');
	}

	public function menu_save()
	{
		$this->require_method('post');
		$this->require_permission('roles.manage');
		$this->require_reauth();
		$this->rbac->save_menu((array) $this->input->post('items'), (array) $this->input->post('hidden'), (int) $this->user->id);
		$this->flash('success', 'Pengaturan menu disimpan. Ingat: menyembunyikan menu tidak mencabut izin.');
		redirect(site_url('admin/rbac/menu'), 'location', 303);
	}

	protected function require_role($code)
	{
		$role = $this->rbac->role($code);
		if ( ! $role)
		{
			throw new DomainRuleException('Role tidak ditemukan.', 404);
		}
		return $role;
	}

	protected function require_permission_row($code)
	{
		$perm = $this->rbac->permission($code);
		if ( ! $perm)
		{
			throw new DomainRuleException('Izin tidak ditemukan.', 404);
		}
		return $perm;
	}
}
