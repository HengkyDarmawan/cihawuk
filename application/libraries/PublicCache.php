<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Cache halaman publik dengan invalidasi terarah (modul-backend §24).
 *
 * Aturannya: publikasi/penarikan sebuah objek hanya menghapus entri milik objek itu
 * beserta turunan yang benar-benar terpengaruh (mis. sitemap dan pencarian), bukan
 * `flush all`. Mengubah draft tidak menyentuh cache sama sekali.
 *
 * Penyimpanan: berkas di `storage/cache/public/{grup}/{hash}.cache`. Grup dipakai agar
 * penghapusan per grup murah dan tidak perlu memindai seluruh direktori cache.
 */
class PublicCache {

	const GROUPS = array('page', 'menu', 'site', 'listing');

	/** @var string */
	protected $root;

	/** @var bool cache dimatikan pada environment testing agar tes tidak saling mempengaruhi */
	protected $enabled = TRUE;

	public function __construct(array $params = array())
	{
		$this->root = rtrim(isset($params['root']) ? $params['root'] : ROOTPATH.'storage/cache/public', '/\\');
		if (array_key_exists('enabled', $params))
		{
			// Dipakai pengujian agar mekanisme invalidasi tetap dapat diuji walau cache dimatikan.
			$this->enabled = (bool) $params['enabled'];
			return;
		}
		$CI =& get_instance();
		$flag = $CI->config->item('features', 'app');
		$this->enabled = ! (is_array($flag) && isset($flag['public_cache']) && ! $flag['public_cache']);
	}

	public function enabled()
	{
		return $this->enabled;
	}

	/** Ambil nilai cache; NULL bila belum ada, kedaluwarsa, atau cache dimatikan. */
	public function get($group, $key)
	{
		if ( ! $this->enabled)
		{
			return NULL;
		}
		$file = $this->path($group, $key);
		if ($file === NULL OR ! is_file($file))
		{
			return NULL;
		}
		$raw = @file_get_contents($file);
		if ($raw === FALSE)
		{
			return NULL;
		}
		$payload = @unserialize($raw, array('allowed_classes' => FALSE));
		if ( ! is_array($payload) OR ! array_key_exists('value', $payload))
		{
			return NULL;
		}
		if ( ! empty($payload['expires']) && $payload['expires'] < time())
		{
			@unlink($file);
			return NULL;
		}
		return $payload['value'];
	}

	public function set($group, $key, $value, $ttl = 3600)
	{
		if ( ! $this->enabled)
		{
			return FALSE;
		}
		$file = $this->path($group, $key);
		if ($file === NULL)
		{
			return FALSE;
		}
		$dir = dirname($file);
		if ( ! is_dir($dir) && ! @mkdir($dir, 0775, TRUE) && ! is_dir($dir))
		{
			return FALSE;
		}
		$payload = serialize(array(
			'key' => $key,
			'stored_at' => time(),
			'expires' => $ttl > 0 ? time() + (int) $ttl : 0,
			'value' => $value,
		));
		// Tulis atomik agar pembaca tidak pernah melihat berkas setengah jadi.
		$tmp = $file.'.'.getmypid().'.tmp';
		if (@file_put_contents($tmp, $payload, LOCK_EX) === FALSE)
		{
			return FALSE;
		}
		if ( ! @rename($tmp, $file))
		{
			@unlink($tmp);
			return FALSE;
		}
		return TRUE;
	}

	/** Hapus satu entri. */
	public function forget($group, $key)
	{
		$file = $this->path($group, $key);
		if ($file !== NULL && is_file($file))
		{
			@unlink($file);
		}
	}

	/** Hapus seluruh entri satu grup (mis. semua menu setelah menu diterbitkan). */
	public function forget_group($group)
	{
		if ( ! in_array($group, self::GROUPS, TRUE))
		{
			return;
		}
		foreach (glob($this->root.'/'.$group.'/*.cache') ?: array() as $file)
		{
			@unlink($file);
		}
	}

	/**
	 * Invalidasi setelah sebuah halaman diterbitkan/ditarik: entri halaman itu,
	 * daftar turunan (sitemap, pencarian, navigasi yang memuat judulnya), dan penanda waktu.
	 */
	public function invalidate_page($page_key)
	{
		$this->forget('page', (string) $page_key);
		$this->forget_group('listing');
		$this->touch('page:'.$page_key);
	}

	public function invalidate_menus()
	{
		$this->forget_group('menu');
		$this->touch('menu');
	}

	public function invalidate_site()
	{
		$this->forget_group('site');
		// Identitas dan tema ikut dirender pada setiap halaman.
		$this->forget_group('page');
		$this->touch('site');
	}

	/**
	 * Kosongkan seluruh grup sekaligus.
	 *
	 * Dipakai saat banyak entitas dihapus serentak (mis. `tools purge_demo`), ketika
	 * membatalkan cache per halaman satu per satu tidak lagi sepadan.
	 */
	public function flush()
	{
		foreach (self::GROUPS as $group)
		{
			$this->forget_group($group);
		}
		$this->touch('flush');
	}

	/** Catat kapan invalidasi terakhir terjadi; dipakai dashboard maintenance. */
	public function touch($label)
	{
		$file = $this->root.'/last-invalidation.json';
		$data = array();
		if (is_file($file))
		{
			$decoded = json_decode((string) @file_get_contents($file), TRUE);
			$data = is_array($decoded) ? $decoded : array();
		}
		$data[(string) $label] = gmdate('Y-m-d H:i:s');
		if ( ! is_dir($this->root))
		{
			@mkdir($this->root, 0775, TRUE);
		}
		@file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES), LOCK_EX);
	}

	public function last_invalidation()
	{
		$file = $this->root.'/last-invalidation.json';
		if ( ! is_file($file))
		{
			return array();
		}
		$decoded = json_decode((string) @file_get_contents($file), TRUE);
		return is_array($decoded) ? $decoded : array();
	}

	protected function path($group, $key)
	{
		if ( ! in_array($group, self::GROUPS, TRUE))
		{
			return NULL;
		}
		return $this->root.'/'.$group.'/'.sha1((string) $key).'.cache';
	}
}
