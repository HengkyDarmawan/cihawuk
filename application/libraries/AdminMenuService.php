<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Menu dashboard pengelola: registry `config/admin_menu.php` digabung dengan pengaturan
 * dari Admin › Role & Izin (label, grup, urutan, aktif, dan visibilitas per role).
 *
 * Menu hanya tampil bila izin dan modulnya terpenuhi. Menyembunyikan menu bukan kontrol
 * akses: setiap route tetap memeriksa izin di server.
 */
class AdminMenuService {

	/** @var CI_Controller */
	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->config->load('admin_menu', TRUE);
	}

	/** Registry mentah: array grup dengan item. */
	public function registry()
	{
		return (array) $this->CI->config->item('admin_menu', 'admin_menu');
	}

	/** Kunci item yang tidak boleh disembunyikan atau dimatikan. */
	public function locked_keys()
	{
		return (array) $this->CI->config->item('admin_menu_locked', 'admin_menu');
	}

	/** Seluruh item registry sebagai daftar datar key => item (dengan heading asal). */
	public function items()
	{
		$out = array();
		foreach ($this->registry() as $g => $group)
		{
			foreach ($group['items'] as $i => $item)
			{
				$item['heading'] = $group['heading'];
				$item['default_order'] = ($g + 1) * 100 + $i;
				$out[$item['key']] = $item;
			}
		}
		return $out;
	}

	/** @return array menu_key => row */
	public function overrides()
	{
		if ( ! $this->CI->db->table_exists('admin_menu_overrides'))
		{
			return array();
		}
		$out = array();
		foreach ($this->CI->db->get('admin_menu_overrides')->result() as $row)
		{
			$out[$row->menu_key] = $row;
		}
		return $out;
	}

	/** @return array role_id => array(menu_key => TRUE) */
	public function hidden_by_role()
	{
		if ( ! $this->CI->db->table_exists('role_menu_hidden'))
		{
			return array();
		}
		$out = array();
		foreach ($this->CI->db->get('role_menu_hidden')->result() as $row)
		{
			$out[(int) $row->role_id][$row->menu_key] = TRUE;
		}
		return $out;
	}

	/**
	 * Menu untuk satu pengguna, siap dirender layout: array(heading, items[]).
	 *
	 * @param int   $user_id
	 * @param array $permissions kode izin pengguna
	 */
	public function for_user($user_id, array $permissions)
	{
		$this->CI->load->library('FeatureModuleService', NULL, 'modules');
		$role_ids = array();
		foreach ($this->CI->user_model->roles($user_id) as $role)
		{
			$role_ids[] = (int) $role->id;
		}
		$hidden = $this->hidden_by_role();
		$overrides = $this->overrides();
		$locked = $this->locked_keys();

		$groups = array();
		foreach ($this->items() as $key => $item)
		{
			$override = $overrides[$key] ?? NULL;
			if ( ! in_array($key, $locked, TRUE))
			{
				if ($override && (int) $override->active === 0)
				{
					continue;
				}
				// Tersembunyi hanya bila SEMUA role pengguna menyembunyikannya.
				if ( ! empty($role_ids))
				{
					$hidden_all = TRUE;
					foreach ($role_ids as $rid)
					{
						if (empty($hidden[$rid][$key]))
						{
							$hidden_all = FALSE;
							break;
						}
					}
					if ($hidden_all)
					{
						continue;
					}
				}
			}
			if ( ! $this->allowed($item, $permissions))
			{
				continue;
			}
			$heading = ($override && $override->group_label !== NULL && $override->group_label !== '') ? $override->group_label : $item['heading'];
			$item['label'] = ($override && $override->label !== NULL && $override->label !== '') ? $override->label : $item['label'];
			$item['order'] = ($override && $override->sort_order !== NULL) ? (int) $override->sort_order : $item['default_order'];
			$gkey = (string) $heading;
			if ( ! isset($groups[$gkey]))
			{
				$groups[$gkey] = array('heading' => $heading, 'order' => $item['order'], 'items' => array());
			}
			$groups[$gkey]['order'] = min($groups[$gkey]['order'], $item['order']);
			$groups[$gkey]['items'][] = $item;
		}

		foreach ($groups as &$group)
		{
			usort($group['items'], function ($a, $b) { return $a['order'] - $b['order']; });
		}
		unset($group);
		uasort($groups, function ($a, $b) { return $a['order'] - $b['order']; });
		return array_values($groups);
	}

	/** Syarat izin dan modul sebuah item. */
	public function allowed(array $item, array $permissions)
	{
		if ( ! empty($item['module']) && ! $this->CI->modules->backend_available($item['module']))
		{
			return FALSE;
		}
		if ( ! empty($item['all']) && count(array_diff($item['all'], $permissions)) > 0)
		{
			return FALSE;
		}
		if ( ! empty($item['any']) && count(array_intersect($item['any'], $permissions)) === 0)
		{
			return FALSE;
		}
		return TRUE;
	}
}
