<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pengelolaan role, izin, dan menu dashboard (Admin › Role, Izin, dan Menu).
 *
 * Basis data adalah sumber kebenaran setelah seed; `config/rbac.php` hanya preset awal.
 * Aturan yang dijaga di sini, bukan di view:
 * - role bawaan (`is_system`) tidak dapat dihapus, dan kode role tidak pernah diubah
 *   karena beberapa pemeriksaan kode memakai kode role (mis. `super_admin`);
 * - role yang masih dipakai pengguna tidak dapat dihapus;
 * - `super_admin` selalu memegang izin pengelolaan akses, supaya tidak ada yang terkunci;
 * - izin bawaan config tidak dapat dihapus karena diperiksa oleh kode; izin buatan
 *   dashboard hanya berarti bila kelak dipakai kode atau menu.
 */
class RbacService {

	/** Izin yang tidak boleh lepas dari super_admin. */
	const SUPER_ADMIN_REQUIRED = array('roles.manage', 'users.assign_roles', 'users.manage');

	/** Role yang kodenya dipakai langsung oleh kode aplikasi. */
	const PROTECTED_ROLES = array('super_admin', 'resident');

	/** @var CI_Controller */
	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->model('User_model', 'user_model');
		$this->CI->load->library('AdminMenuService', NULL, 'admin_menu');
	}

	// ------------------------------------------------------------------
	// Role
	// ------------------------------------------------------------------

	public function roles()
	{
		return $this->CI->db->select('r.*, COUNT(DISTINCT ur.user_id) AS user_count, COUNT(DISTINCT rp.permission_id) AS permission_count', FALSE)
			->from('roles r')
			->join('user_roles ur', 'ur.role_id = r.id', 'left')
			->join('role_permissions rp', 'rp.role_id = r.id', 'left')
			->group_by('r.id')->order_by('r.is_staff', 'DESC')->order_by('r.name')->get()->result();
	}

	public function role($code)
	{
		return $this->CI->db->get_where('roles', array('code' => (string) $code))->row();
	}

	public function save_role(array $input, $actor_id, $existing = NULL)
	{
		$errors = array();
		$name = mb_substr(trim((string) ($input['name'] ?? '')), 0, 100);
		$description = mb_substr(trim((string) ($input['description'] ?? '')), 0, 255);
		$is_staff = empty($input['is_staff']) ? 0 : 1;
		if ($name === '')
		{
			$errors['name'] = 'Nama role wajib diisi.';
		}

		if ($existing)
		{
			if (in_array($existing->code, self::PROTECTED_ROLES, TRUE) && $is_staff !== (int) $existing->is_staff)
			{
				$errors['is_staff'] = 'Jenis akses role ini dipakai langsung oleh aplikasi dan tidak dapat diubah.';
			}
			if ($errors)
			{
				throw new DomainRuleException('Periksa kembali data role.', 422, $errors);
			}
			db_must($this->CI->db->where('id', (int) $existing->id)->update('roles', array(
				'name' => $name, 'description' => $description ?: NULL, 'is_staff' => $is_staff, 'updated_at' => utc_now(),
			)), 'roles.update');
			$this->CI->audit->log('rbac.role_updated', 'role', $existing->code, array('name' => $name, 'is_staff' => $is_staff), FALSE, 'rbac');
			$this->flush_holders((int) $existing->id);
			return $this->role($existing->code);
		}

		$code = strtolower(trim((string) ($input['code'] ?? '')));
		if ( ! preg_match('/^[a-z][a-z0-9_]{2,39}$/', $code))
		{
			$errors['code'] = 'Kode 3–40 karakter: huruf kecil, angka, atau garis bawah, diawali huruf.';
		}
		elseif ($code === 'baru' OR $this->role($code))
		{
			$errors['code'] = 'Kode role sudah dipakai.';
		}
		if ($errors)
		{
			throw new DomainRuleException('Periksa kembali data role.', 422, $errors);
		}
		$now = utc_now();
		db_must($this->CI->db->insert('roles', array(
			'code' => $code, 'name' => $name, 'description' => $description ?: NULL,
			'is_system' => 0, 'is_staff' => $is_staff, 'preset_version' => 0,
			'created_at' => $now, 'updated_at' => $now,
		)), 'roles.insert');
		$this->CI->audit->log('rbac.role_created', 'role', $code, array('name' => $name, 'is_staff' => $is_staff), FALSE, 'rbac');
		return $this->role($code);
	}

	public function delete_role($role)
	{
		if ((int) $role->is_system === 1)
		{
			throw new DomainRuleException('Role bawaan tidak dapat dihapus. Kosongkan izinnya bila tidak dipakai.', 409);
		}
		$users = (int) $this->CI->db->where('role_id', (int) $role->id)->count_all_results('user_roles');
		if ($users > 0)
		{
			throw new DomainRuleException('Role masih dipakai '.$users.' pengguna. Cabut role dari pengguna tersebut lebih dulu.', 409);
		}
		db_transaction(function () use ($role) {
			$this->CI->db->where('role_id', (int) $role->id)->delete('role_permissions');
			$this->CI->db->where('role_id', (int) $role->id)->delete('role_menu_hidden');
			db_must($this->CI->db->where('id', (int) $role->id)->delete('roles'), 'roles.delete');
		});
		$this->CI->audit->log('rbac.role_deleted', 'role', $role->code, array('name' => $role->name), FALSE, 'rbac');
	}

	// ------------------------------------------------------------------
	// Izin per role
	// ------------------------------------------------------------------

	/** @return int[] id izin yang dipegang role */
	public function role_permission_ids($role)
	{
		$out = array();
		foreach ($this->CI->db->select('permission_id')->where('role_id', (int) $role->id)->get('role_permissions')->result() as $row)
		{
			$out[] = (int) $row->permission_id;
		}
		return $out;
	}

	/**
	 * Ganti seluruh izin role dengan daftar kode yang dikirim.
	 *
	 * @param string[] $codes
	 */
	public function set_role_permissions($role, array $codes, $actor_id)
	{
		$codes = array_values(array_unique(array_filter(array_map('strval', $codes))));
		$known = array();
		foreach ($this->permissions() as $perm)
		{
			$known[$perm->code] = (int) $perm->id;
		}
		foreach ($codes as $code)
		{
			if ( ! isset($known[$code]))
			{
				throw new DomainRuleException('Izin tidak dikenal: '.$code, 422);
			}
		}
		if ($role->code === 'super_admin')
		{
			$missing = array_diff(self::SUPER_ADMIN_REQUIRED, $codes);
			if ( ! empty($missing))
			{
				throw new DomainRuleException('Super Admin wajib tetap memegang izin: '.implode(', ', $missing).'. Tanpa itu tidak ada yang dapat mengelola akses.', 409);
			}
		}

		$before = array();
		foreach ($this->CI->db->select('p.code')->from('role_permissions rp')->join('permissions p', 'p.id = rp.permission_id')
			->where('rp.role_id', (int) $role->id)->get()->result() as $row)
		{
			$before[] = $row->code;
		}
		$added = array_values(array_diff($codes, $before));
		$removed = array_values(array_diff($before, $codes));
		if (empty($added) && empty($removed))
		{
			return array('added' => array(), 'removed' => array());
		}

		db_transaction(function () use ($role, $codes, $known) {
			$this->CI->db->where('role_id', (int) $role->id)->delete('role_permissions');
			foreach ($codes as $code)
			{
				db_must($this->CI->db->insert('role_permissions', array('role_id' => (int) $role->id, 'permission_id' => $known[$code])), 'role_permissions.insert');
			}
		});
		$this->CI->audit->log('rbac.role_permissions_changed', 'role', $role->code,
			array('added' => $added, 'removed' => $removed), FALSE, 'rbac');
		$this->flush_holders((int) $role->id);
		return array('added' => $added, 'removed' => $removed);
	}

	/**
	 * Izin dibaca ulang dari basis data setiap request, jadi perubahan langsung berlaku.
	 * Cache per-request dikosongkan untuk berjaga bila pemanggil masih memakainya.
	 */
	protected function flush_holders($role_id)
	{
		if (isset($this->CI->authz))
		{
			$this->CI->authz->flush();
		}
	}

	// ------------------------------------------------------------------
	// Izin
	// ------------------------------------------------------------------

	public function permissions()
	{
		return $this->CI->db->select('p.*, COUNT(rp.role_id) AS role_count', FALSE)
			->from('permissions p')->join('role_permissions rp', 'rp.permission_id = p.id', 'left')
			->group_by('p.id')->order_by('p.code')->get()->result();
	}

	/** Kelompokkan izin menurut awalan kode, mis. "tickets.verify" → "tickets". */
	public function grouped_permissions()
	{
		$out = array();
		foreach ($this->permissions() as $perm)
		{
			$prefix = strstr($perm->code, '.', TRUE) ?: $perm->code;
			$out[$prefix][] = $perm;
		}
		ksort($out);
		return $out;
	}

	public function permission($code)
	{
		return $this->CI->db->get_where('permissions', array('code' => (string) $code))->row();
	}

	public function save_permission(array $input, $existing = NULL)
	{
		$description = mb_substr(trim((string) ($input['description'] ?? '')), 0, 255);
		if ($description === '')
		{
			throw new DomainRuleException('Deskripsi izin wajib diisi.', 422, array('description' => 'Wajib diisi.'));
		}
		if ($existing)
		{
			db_must($this->CI->db->where('id', (int) $existing->id)->update('permissions', array('description' => $description)), 'permissions.update');
			$this->CI->audit->log('rbac.permission_updated', 'permission', $existing->code, array(), FALSE, 'rbac');
			return $this->permission($existing->code);
		}
		$code = strtolower(trim((string) ($input['code'] ?? '')));
		if (strlen($code) > 60 OR ! preg_match('/^[a-z][a-z0-9_]{1,40}(\.[a-z][a-z0-9_]{1,40}){1,2}$/', $code))
		{
			throw new DomainRuleException('Periksa kembali kode izin.', 422,
				array('code' => 'Format: modul.aksi, huruf kecil, angka, atau garis bawah (mis. arsip.lihat).'));
		}
		if ($this->permission($code))
		{
			throw new DomainRuleException('Kode izin sudah ada.', 422, array('code' => 'Kode izin sudah ada.'));
		}
		db_must($this->CI->db->insert('permissions', array('code' => $code, 'description' => $description, 'is_custom' => 1)), 'permissions.insert');
		$this->CI->audit->log('rbac.permission_created', 'permission', $code, array(), FALSE, 'rbac');
		return $this->permission($code);
	}

	public function delete_permission($permission)
	{
		if ((int) $permission->is_custom !== 1)
		{
			throw new DomainRuleException('Izin bawaan dipakai langsung oleh kode aplikasi dan tidak dapat dihapus.', 409);
		}
		$roles = (int) $this->CI->db->where('permission_id', (int) $permission->id)->count_all_results('role_permissions');
		if ($roles > 0)
		{
			throw new DomainRuleException('Izin masih dipegang '.$roles.' role. Lepaskan dari role tersebut lebih dulu.', 409);
		}
		db_must($this->CI->db->where('id', (int) $permission->id)->delete('permissions'), 'permissions.delete');
		$this->CI->audit->log('rbac.permission_deleted', 'permission', $permission->code, array(), FALSE, 'rbac');
	}

	// ------------------------------------------------------------------
	// Menu dashboard
	// ------------------------------------------------------------------

	/**
	 * Simpan pengaturan seluruh item menu sekaligus.
	 *
	 * @param array $items  menu_key => array(label, group_label, sort_order, active)
	 * @param array $hidden menu_key => array(role_id, ...) role yang TIDAK melihat item
	 */
	public function save_menu(array $items, array $hidden, $actor_id)
	{
		$registry = $this->CI->admin_menu->items();
		$locked = $this->CI->admin_menu->locked_keys();
		$role_ids = array();
		foreach ($this->CI->db->select('id')->get('roles')->result() as $row)
		{
			$role_ids[(int) $row->id] = TRUE;
		}
		$now = utc_now();

		db_transaction(function () use ($items, $hidden, $registry, $locked, $role_ids, $actor_id, $now) {
			foreach ($registry as $key => $item)
			{
				$this->save_menu_visibility($key, $hidden, $locked, $role_ids, $now);
				// Item yang tidak ikut dikirim dibiarkan apa adanya (bukan dianggap dimatikan).
				if ( ! isset($items[$key]) OR ! is_array($items[$key]))
				{
					continue;
				}
				$in = $items[$key];
				$label = mb_substr(trim((string) ($in['label'] ?? '')), 0, 80);
				$group = mb_substr(trim((string) ($in['group_label'] ?? '')), 0, 80);
				$order = trim((string) ($in['sort_order'] ?? ''));
				$active = in_array($key, $locked, TRUE) ? 1 : (empty($in['active']) ? 0 : 1);
				$row = array(
					'label' => ($label === '' OR $label === $item['label']) ? NULL : $label,
					'group_label' => ($group === '' OR $group === (string) $item['heading']) ? NULL : $group,
					'sort_order' => ($order === '' OR ! is_numeric($order) OR (int) $order === (int) $item['default_order']) ? NULL : max(0, min(99999, (int) $order)),
					'active' => $active,
					'updated_by' => $actor_id ? (int) $actor_id : NULL,
					'updated_at' => $now,
				);
				$this->CI->db->where('menu_key', $key)->delete('admin_menu_overrides');
				if ($row['label'] !== NULL OR $row['group_label'] !== NULL OR $row['sort_order'] !== NULL OR $active === 0)
				{
					db_must($this->CI->db->insert('admin_menu_overrides', array('menu_key' => $key) + $row), 'admin_menu_overrides.insert');
				}
			}
		});
		$this->CI->audit->log('rbac.menu_saved', 'admin_menu', NULL, array('items' => count($registry)), FALSE, 'rbac');
	}

	/**
	 * Visibilitas per role selalu diganti penuh: multi-select tanpa pilihan memang tidak
	 * terkirim, jadi "tidak ada" berarti item tampil untuk semua role.
	 */
	protected function save_menu_visibility($key, array $hidden, array $locked, array $role_ids, $now)
	{
		$this->CI->db->where('menu_key', $key)->delete('role_menu_hidden');
		if (in_array($key, $locked, TRUE))
		{
			return;
		}
		foreach (array_unique(array_map('intval', (array) ($hidden[$key] ?? array()))) as $rid)
		{
			if (isset($role_ids[$rid]))
			{
				db_must($this->CI->db->insert('role_menu_hidden', array('role_id' => $rid, 'menu_key' => $key, 'created_at' => $now)), 'role_menu_hidden.insert');
			}
		}
	}
}
