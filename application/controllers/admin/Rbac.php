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
		$this->load->library('AuthService', NULL, 'auth');
		$this->layout_data['nav_active'] = 'pengguna';
	}

	/** Role dikelola dari halaman Pengguna & Akses (tab per role). */
	public function index()
	{
		$this->require_permission('roles.manage');
		if ($this->input->get('semua') === NULL)
		{
			redirect(site_url('admin/pengguna'), 'location', 303);
			return;
		}
		$this->render('admin/rbac_roles', array(
			'page_title' => 'Semua role',
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
			'groups' => $this->rbac->module_permissions(),
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
		$members = $this->rbac->role_members($role);
		foreach ($members as $member)
		{
			$member->role_codes = $this->user_model->role_codes($member->id);
		}
		$role_options = array();
		foreach ($this->rbac->roles() as $r)
		{
			$role_options[$r->code] = $r->name;
		}
		$candidates = array();
		foreach ($this->db->select('u.public_id, u.display_name, u.username')->from('users u')
			->where('NOT EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = u.id AND ur.role_id = '.(int) $role->id.')', NULL, FALSE)
			->where('u.id !=', (int) $this->user->id)
			->order_by('u.display_name')->limit(500)->get()->result() as $candidate)
		{
			$candidates[$candidate->public_id] = $candidate->display_name.' ('.$candidate->username.')';
		}
		$this->render('admin/rbac_role_form', array(
			'page_title' => 'Role: '.$role->name,
			'tab' => 'role',
			'role' => $role,
			'groups' => $this->rbac->module_permissions(),
			'held' => $this->rbac->role_permission_ids($role),
			'user_count' => (int) $this->db->where('role_id', (int) $role->id)->count_all_results('user_roles'),
			'members' => $members,
			'role_options' => $role_options,
			'candidates' => $candidates,
			'modules' => $this->rbac->module_labels($this->rbac->role_permission_codes($role)),
			'active_tab' => $this->input->get('tab') === 'anggota' ? 'anggota' : 'akses',
			'extra_js' => array('vendor/sweetalert2/sweetalert2.min.js'),
		), 'dashboard');
	}

	/** Tambahkan pengguna ke role ini (role lain pengguna tersebut diganti). */
	public function member_add($code)
	{
		$this->require_method('post');
		$this->require_permission('users.assign_roles');
		$this->require_reauth();
		$role = $this->require_role($code);
		$member = $this->user_model->find_by_public_id((string) $this->input->post('user_public_id'));
		if ( ! $member)
		{
			throw new DomainRuleException('Pilih pengguna yang akan ditambahkan.', 422, array('user_public_id' => 'Wajib dipilih.'));
		}
		if ($this->rbac->set_user_role($member, $role->code, $this->user))
		{
			$this->auth->logout_all($member->id, 'role_change');
			$this->authz->flush($member->id);
		}
		$this->flash('success', $member->display_name.' sekarang memegang role '.$role->name.'.');
		redirect(site_url('admin/rbac/role/'.rawurlencode($role->code).'?tab=anggota'), 'location', 303);
	}

	public function role_update($code)
	{
		$this->require_method('post');
		$this->require_permission('roles.manage');
		$this->require_reauth();
		$role = $this->require_role($code);
		$role = $this->rbac->save_role($this->input->post(NULL, FALSE) ?: array(), (int) $this->user->id, $role);
		if ($role->code === 'super_admin')
		{
			// Super Admin selalu memegang semua izin; tidak ada daftar izin yang disimpan.
			$this->flash('success', 'Data role disimpan. Super Admin tetap memegang semua akses.');
			redirect(site_url('admin/rbac/role/super_admin'), 'location', 303);
			return;
		}
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
		redirect(site_url('admin/pengguna'), 'location', 303);
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
