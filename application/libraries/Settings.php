<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pengaturan aplikasi dari tabel app_settings (nilai JSON).
 * Rahasia tidak disimpan di sini; hanya item is_public yang boleh dikirim ke frontend.
 */
class Settings {

	/** @var CI_Controller */
	protected $CI;

	/** @var array|null */
	protected $cache = NULL;

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	protected function load()
	{
		if ($this->cache !== NULL)
		{
			return;
		}
		$this->cache = array();
		if ( ! isset($this->CI->db) OR ! $this->CI->db->table_exists('app_settings'))
		{
			return;
		}
		foreach ($this->CI->db->get('app_settings')->result() as $row)
		{
			$decoded = json_decode($row->value_json, TRUE);
			$this->cache[$row->key] = array('value' => $decoded, 'is_public' => (int) $row->is_public, 'group' => $row->group_code);
		}
	}

	public function get($key, $default = NULL)
	{
		$this->load();
		return array_key_exists($key, $this->cache) ? $this->cache[$key]['value'] : $default;
	}

	public function set($key, $value, $group, $is_public = FALSE, $user_id = NULL)
	{
		$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === FALSE)
		{
			throw new InvalidArgumentException('Setting value must be JSON serializable.');
		}
		db_must($this->CI->db->query(
			'INSERT INTO app_settings (`key`, value_json, group_code, is_public, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, ?)
			 ON DUPLICATE KEY UPDATE value_json = VALUES(value_json), group_code = VALUES(group_code), is_public = VALUES(is_public), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)',
			array($key, $json, $group, $is_public ? 1 : 0, $user_id, utc_now())
		), 'app_settings');
		$this->cache = NULL;
	}

	/** Set hanya bila belum ada (seed tidak menimpa pengaturan yang sudah diedit). */
	public function set_default($key, $value, $group, $is_public = FALSE)
	{
		$this->load();
		if ( ! array_key_exists($key, $this->cache))
		{
			$this->set($key, $value, $group, $is_public, NULL);
		}
	}

	public function group($group)
	{
		$this->load();
		$out = array();
		foreach ($this->cache as $key => $item)
		{
			if ($item['group'] === $group)
			{
				$out[$key] = $item['value'];
			}
		}
		return $out;
	}

	public function public_values()
	{
		$this->load();
		$out = array();
		foreach ($this->cache as $key => $item)
		{
			if ($item['is_public'])
			{
				$out[$key] = $item['value'];
			}
		}
		return $out;
	}

	public function flush()
	{
		$this->cache = NULL;
	}
}
