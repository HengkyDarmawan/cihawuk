<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends CI_Model {

	public function find($id)
	{
		return $this->db->get_where('users', array('id' => (int) $id))->row();
	}

	public function find_by_public_id($public_id)
	{
		return $this->db->get_where('users', array('public_id' => (string) $public_id))->row();
	}

	public function find_by_username($username)
	{
		return $this->db->get_where('users', array('username' => $this->normalize_username($username)))->row();
	}

	public function find_by_email($email)
	{
		return $this->db->get_where('users', array('email' => $this->normalize_email($email)))->row();
	}

	public function normalize_username($username)
	{
		return mb_strtolower(trim((string) $username));
	}

	public function normalize_email($email)
	{
		$email = trim((string) $email);
		return ($email === '') ? NULL : mb_strtolower($email);
	}

	/**
	 * Normalisasi nomor telepon Indonesia ke format +62xxxxxxxxx.
	 * Format tidak membuktikan kepemilikan nomor.
	 */
	public function normalize_phone($phone)
	{
		$digits = preg_replace('/[^0-9+]/', '', (string) $phone);
		if ($digits === '')
		{
			return NULL;
		}
		if (strpos($digits, '+62') === 0)
		{
			$digits = '0'.substr($digits, 3);
		}
		elseif (strpos($digits, '62') === 0)
		{
			$digits = '0'.substr($digits, 2);
		}
		if ( ! preg_match('/^08[0-9]{7,12}$/', $digits))
		{
			return FALSE;
		}
		return '+62'.substr($digits, 1);
	}

	public function username_valid($username)
	{
		return (bool) preg_match('/^[a-z0-9._]{4,50}$/', (string) $username);
	}

	public function create(array $data)
	{
		$now = utc_now();
		$data['created_at'] = $now;
		$data['updated_at'] = $now;
		db_must($this->db->insert('users', $data), 'users.create');
		return (int) $this->db->insert_id();
	}

	public function update($id, array $data)
	{
		$data['updated_at'] = utc_now();
		return db_must($this->db->where('id', (int) $id)->update('users', $data), 'users.update');
	}

	public function bump_auth_version($id)
	{
		db_must($this->db->query('UPDATE users SET auth_version = auth_version + 1, updated_at = ? WHERE id = ?', array(utc_now(), (int) $id)), 'users.auth_version');
	}

	public function role_codes($user_id)
	{
		$rows = $this->db->select('r.code')
			->from('user_roles ur')
			->join('roles r', 'r.id = ur.role_id')
			->where('ur.user_id', (int) $user_id)
			->get()->result();
		return array_map(function ($r) { return $r->code; }, $rows);
	}

	public function roles($user_id)
	{
		return $this->db->select('r.*')
			->from('user_roles ur')
			->join('roles r', 'r.id = ur.role_id')
			->where('ur.user_id', (int) $user_id)
			->order_by('r.name')
			->get()->result();
	}

	public function permission_codes($user_id)
	{
		$rows = $this->db->distinct()->select('p.code')
			->from('user_roles ur')
			->join('role_permissions rp', 'rp.role_id = ur.role_id')
			->join('permissions p', 'p.id = rp.permission_id')
			->where('ur.user_id', (int) $user_id)
			->get()->result();
		return array_map(function ($r) { return $r->code; }, $rows);
	}

	public function is_staff($user_id)
	{
		return $this->db->from('user_roles ur')
			->join('roles r', 'r.id = ur.role_id')
			->where('ur.user_id', (int) $user_id)
			->where('r.is_staff', 1)
			->count_all_results() > 0;
	}

	public function assign_role($user_id, $role_code, $assigned_by = NULL)
	{
		$role = $this->db->get_where('roles', array('code' => $role_code))->row();
		if ( ! $role)
		{
			throw new InvalidArgumentException('Unknown role '.$role_code);
		}
		db_must($this->db->query(
			'INSERT IGNORE INTO user_roles (user_id, role_id, assigned_by, assigned_at) VALUES (?, ?, ?, ?)',
			array((int) $user_id, (int) $role->id, $assigned_by, utc_now())
		), 'user_roles.assign');
	}

	public function revoke_role($user_id, $role_code)
	{
		$role = $this->db->get_where('roles', array('code' => $role_code))->row();
		if ($role)
		{
			db_must($this->db->delete('user_roles', array('user_id' => (int) $user_id, 'role_id' => (int) $role->id)), 'user_roles.revoke');
		}
	}

	public function resident_profile($user_id)
	{
		return $this->db->get_where('resident_profiles', array('user_id' => (int) $user_id))->row();
	}

	public function count_active_super_admins($exclude_user_id = NULL)
	{
		$this->db->from('users u')
			->join('user_roles ur', 'ur.user_id = u.id')
			->join('roles r', 'r.id = ur.role_id')
			->where('r.code', 'super_admin')
			->where('u.account_status', 'active');
		if ($exclude_user_id !== NULL)
		{
			$this->db->where('u.id !=', (int) $exclude_user_id);
		}
		return (int) $this->db->count_all_results();
	}

	/** Pengguna aktif yang memiliki permission tertentu. */
	public function active_users_with_permission($permission_code)
	{
		return $this->db->distinct()->select('u.id, u.display_name, u.username, u.email, u.email_verified_at')
			->from('users u')
			->join('user_roles ur', 'ur.user_id = u.id')
			->join('role_permissions rp', 'rp.role_id = ur.role_id')
			->join('permissions p', 'p.id = rp.permission_id')
			->where('p.code', $permission_code)
			->where('u.account_status', 'active')
			->order_by('u.display_name')
			->get()->result();
	}

	public function unit_scopes($user_id)
	{
		return $this->db->get_where('user_unit_scopes', array('user_id' => (int) $user_id))->result();
	}
}
