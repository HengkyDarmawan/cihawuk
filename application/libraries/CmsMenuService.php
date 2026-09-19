<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Menu dan navigasi publik (modul-backend §9.3).
 *
 * Item menu disimpan sebagai draft dan baru tampil di situs setelah menu diterbitkan
 * sebagai snapshot (`cms_publication_snapshots.target_type = 'menu'`). Aturan yang
 * ditegakkan server: kedalaman maksimal dua tingkat, tanpa cycle, hanya path internal
 * atau URL http/https, dan halaman CMS yang belum terbit tidak dapat dijadikan item.
 */
class CmsMenuService {

	/** @var CI_Controller */
	protected $CI;

	const LOCATIONS = array(
		'header' => 'Menu utama (header)',
		'mobile' => 'Menu seluler',
		'footer_primary' => 'Footer — tautan utama',
		'footer_secondary' => 'Footer — tautan sekunder',
		'quick_link' => 'Akses cepat',
	);

	const LINK_TYPES = array(
		'route' => 'Halaman aplikasi',
		'page' => 'Halaman CMS',
		'external' => 'Tautan luar',
	);

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->library('CmsService', NULL, 'cms');
		$this->CI->load->library('PublicCache', NULL, 'public_cache');
	}

	public function locations()
	{
		return self::LOCATIONS;
	}

	public function menu($location)
	{
		return $this->CI->db->get_where('cms_menus', array('location' => (string) $location))->row();
	}

	public function menu_by_public_id($public_id)
	{
		return $this->CI->db->get_where('cms_menus', array('public_id' => (string) $public_id))->row();
	}

	public function menus()
	{
		$rows = array();
		foreach ($this->CI->db->order_by('id')->get('cms_menus')->result() as $row)
		{
			$rows[$row->location] = $row;
		}
		return $rows;
	}

	/** Membuat menu untuk sebuah lokasi bila belum ada (dipakai seeder dan dashboard). */
	public function ensure_menu($location, $label = NULL)
	{
		if ( ! isset(self::LOCATIONS[$location]))
		{
			throw new DomainRuleException('Lokasi menu tidak dikenal.', 422);
		}
		$menu = $this->menu($location);
		if ($menu)
		{
			return $menu;
		}
		$now = utc_now();
		db_must($this->CI->db->insert('cms_menus', array(
			'public_id' => $this->CI->crypto->public_id(),
			'location' => $location,
			'label' => mb_substr((string) ($label ?: self::LOCATIONS[$location]), 0, 80),
			'status' => 'draft',
			'created_at' => $now,
			'updated_at' => $now,
		)), 'cms_menus.insert');
		return $this->menu($location);
	}

	// ------------------------------------------------------------------
	// Draft
	// ------------------------------------------------------------------

	public function items($menu_id)
	{
		return $this->CI->db->select('i.*, p.page_key, v.slug AS page_slug, v.nav_title AS page_nav_title, p.status AS page_status')
			->from('cms_menu_items i')
			->join('cms_pages p', 'p.id = i.cms_page_id', 'left')
			->join('cms_page_versions v', 'v.id = p.published_version_id', 'left')
			->where('i.menu_id', (int) $menu_id)
			->order_by('i.parent_id IS NULL', 'DESC', FALSE)
			->order_by('i.sort_order')->order_by('i.id')
			->get()->result();
	}

	/** Daftar datar untuk dashboard: setiap induk langsung diikuti submenunya. */
	public function ordered_items($menu_id)
	{
		$rows = $this->items($menu_id);
		$children = array();
		foreach ($rows as $row)
		{
			if ($row->parent_id !== NULL)
			{
				$children[(int) $row->parent_id][] = $row;
			}
		}
		$out = array();
		foreach ($rows as $row)
		{
			if ($row->parent_id !== NULL)
			{
				continue;
			}
			$out[] = $row;
			foreach ($children[(int) $row->id] ?? array() as $child)
			{
				$out[] = $child;
			}
		}
		return $out;
	}

	public function item($public_id)
	{
		return $this->CI->db->get_where('cms_menu_items', array('public_id' => (string) $public_id))->row();
	}

	/** Susunan bertingkat untuk dashboard; bentuknya sama dengan snapshot publik. */
	public function draft_tree($menu)
	{
		$rows = $this->items($menu->id);
		$tree = array();
		$index = array();
		foreach ($rows as $row)
		{
			if ($row->parent_id === NULL)
			{
				$node = $this->node($row);
				$node['children'] = array();
				$index[(int) $row->id] = count($tree);
				$tree[] = $node;
			}
		}
		foreach ($rows as $row)
		{
			if ($row->parent_id !== NULL && isset($index[(int) $row->parent_id]))
			{
				$tree[$index[(int) $row->parent_id]]['children'][] = $this->node($row);
			}
		}
		return $tree;
	}

	protected function node($row)
	{
		return array(
			'public_id' => $row->public_id,
			'label' => $row->label,
			'link_type' => $row->link_type,
			'href' => $this->href($row),
			'enabled' => (int) $row->is_enabled === 1,
			'page_status' => isset($row->page_status) ? $row->page_status : NULL,
			'sort_order' => (int) $row->sort_order,
		);
	}

	/** Alamat final item; halaman CMS memakai slug versi terbit. */
	public function href($row)
	{
		if ($row->link_type === 'external')
		{
			return (string) $row->external_url;
		}
		if ($row->link_type === 'page')
		{
			$path = $this->CI->cms->public_path((int) $row->cms_page_id);
			return $path === NULL ? NULL : '/'.$path;
		}
		return (string) $row->route_path;
	}

	public function save_item($menu, array $input, $user_id, $item = NULL)
	{
		$data = $this->validate_item($menu, $input, $item);
		$now = utc_now();
		if ($item === NULL)
		{
			$count = $this->CI->db->where('menu_id', (int) $menu->id)->count_all_results('cms_menu_items');
			if ($count >= 40)
			{
				throw new DomainRuleException('Satu menu maksimal 40 item.', 422);
			}
			$data['public_id'] = $this->CI->crypto->public_id();
			$data['menu_id'] = (int) $menu->id;
			$data['created_by'] = $user_id ? (int) $user_id : NULL;
			$data['created_at'] = $now;
			$data['updated_at'] = $now;
			$data['sort_order'] = (int) ($this->CI->db->select_max('sort_order')
				->where(array('menu_id' => (int) $menu->id, 'parent_id' => $data['parent_id']))
				->get('cms_menu_items')->row('sort_order') ?: 0) + 10;
			db_must($this->CI->db->insert('cms_menu_items', $data), 'cms_menu_items.insert');
			$id = (int) $this->CI->db->insert_id();
			$this->CI->audit->log('cms.menu_item_created', 'cms_menu', $menu->public_id, array('label' => $data['label']), FALSE, 'cms');
		}
		else
		{
			$data['updated_at'] = $now;
			db_must($this->CI->db->where('id', (int) $item->id)->update('cms_menu_items', $data), 'cms_menu_items.update');
			$id = (int) $item->id;
			$this->CI->audit->log('cms.menu_item_saved', 'cms_menu', $menu->public_id, array('item' => $item->public_id), FALSE, 'cms');
		}
		$this->touch($menu);
		return $id;
	}

	protected function validate_item($menu, array $input, $item)
	{
		$errors = array();
		$label = trim((string) ($input['label'] ?? ''));
		if ($label === '' OR mb_strlen($label) > 80)
		{
			$errors['label'] = 'Label wajib diisi, maksimal 80 karakter.';
		}
		$type = (string) ($input['link_type'] ?? 'route');
		if ( ! isset(self::LINK_TYPES[$type]))
		{
			$errors['link_type'] = 'Jenis tautan tidak dikenal.';
		}

		$data = array(
			'label' => $label,
			'link_type' => $type,
			'route_path' => NULL,
			'cms_page_id' => NULL,
			'external_url' => NULL,
			'is_enabled' => empty($input['is_enabled']) ? 0 : 1,
			'parent_id' => NULL,
		);

		if ($type === 'route')
		{
			$path = '/'.ltrim(trim((string) ($input['route_path'] ?? '')), '/');
			if ($path === '/' OR ! app_is_safe_url($path, FALSE))
			{
				$errors['route_path'] = 'Gunakan path internal yang valid, misalnya /profil.';
			}
			elseif ($this->is_private_path($path))
			{
				$errors['route_path'] = 'Path dashboard atau berkas privat tidak boleh dipasang di menu publik.';
			}
			$data['route_path'] = mb_substr($path, 0, 191);
		}
		elseif ($type === 'page')
		{
			$page = $this->CI->cms->page_by_public_id((string) ($input['cms_page_id'] ?? ''));
			if ( ! $page)
			{
				$errors['cms_page_id'] = 'Halaman tidak ditemukan.';
			}
			elseif ($page->status !== 'published')
			{
				// modul-backend §9.3: halaman draft tidak boleh dipasang pada menu publik.
				$errors['cms_page_id'] = 'Halaman itu belum terbit, jadi belum dapat dipasang di menu.';
			}
			else
			{
				$data['cms_page_id'] = (int) $page->id;
			}
		}
		else
		{
			$url = trim((string) ($input['external_url'] ?? ''));
			$scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
			if ( ! in_array($scheme, array('http', 'https'), TRUE) OR ! app_is_safe_url($url))
			{
				$errors['external_url'] = 'Tautan luar hanya boleh http atau https.';
			}
			$data['external_url'] = mb_substr($url, 0, 500);
		}

		$parent_public = trim((string) ($input['parent_id'] ?? ''));
		if ($parent_public !== '')
		{
			$parent = $this->item($parent_public);
			if ( ! $parent OR (int) $parent->menu_id !== (int) $menu->id)
			{
				$errors['parent_id'] = 'Induk menu tidak ditemukan.';
			}
			elseif ($item && (int) $parent->id === (int) $item->id)
			{
				$errors['parent_id'] = 'Item tidak boleh menjadi induk dirinya sendiri.';
			}
			elseif ($parent->parent_id !== NULL)
			{
				$errors['parent_id'] = 'Menu hanya boleh dua tingkat.';
			}
			elseif ($item && $this->has_children($item->id))
			{
				$errors['parent_id'] = 'Item ini memiliki submenu, jadi tidak dapat dijadikan submenu.';
			}
			else
			{
				$data['parent_id'] = (int) $parent->id;
			}
		}

		if ($errors)
		{
			throw new DomainRuleException('Periksa kembali isian item menu.', 422, $errors);
		}
		return $data;
	}

	protected function has_children($item_id)
	{
		return $this->CI->db->where('parent_id', (int) $item_id)->count_all_results('cms_menu_items') > 0;
	}

	/** Path yang tidak boleh muncul di menu publik. */
	protected function is_private_path($path)
	{
		$segment = strtolower(trim(explode('/', ltrim($path, '/'))[0]));
		return in_array($segment, array('admin', 'warga', 'tools', 'berkas', 'csrf-token'), TRUE);
	}

	public function delete_item($menu, $item)
	{
		db_transaction(function () use ($item) {
			$this->CI->db->delete('cms_menu_items', array('parent_id' => (int) $item->id));
			$this->CI->db->delete('cms_menu_items', array('id' => (int) $item->id));
		});
		$this->CI->audit->log('cms.menu_item_deleted', 'cms_menu', $menu->public_id, array('item' => $item->public_id), FALSE, 'cms');
		$this->touch($menu);
	}

	public function move_item($menu, $item, $direction)
	{
		$siblings = array();
		foreach ($this->items($menu->id) as $row)
		{
			if ((string) $row->parent_id === (string) $item->parent_id)
			{
				$siblings[] = $row;
			}
		}
		$ids = array_map(function ($row) { return (int) $row->id; }, $siblings);
		$pos = array_search((int) $item->id, $ids, TRUE);
		if ($pos === FALSE)
		{
			return;
		}
		$target = ($direction === 'up') ? $pos - 1 : $pos + 1;
		if ($target < 0 OR $target >= count($ids))
		{
			return;
		}
		$tmp = $ids[$pos];
		$ids[$pos] = $ids[$target];
		$ids[$target] = $tmp;
		$this->apply_order($menu, $ids);
	}

	/** Urutan disimpan transaksional; daftar harus memuat seluruh saudara. */
	public function apply_order($menu, array $ids)
	{
		db_transaction(function () use ($menu, $ids) {
			$order = 10;
			foreach ($ids as $id)
			{
				db_must($this->CI->db->where(array('id' => (int) $id, 'menu_id' => (int) $menu->id))
					->update('cms_menu_items', array('sort_order' => $order, 'updated_at' => utc_now())), 'cms_menu_items.order');
				$order += 10;
			}
		});
		$this->touch($menu);
	}

	protected function touch($menu)
	{
		$this->CI->db->where('id', (int) $menu->id)->update('cms_menus', array('updated_at' => utc_now()));
	}

	// ------------------------------------------------------------------
	// Publikasi
	// ------------------------------------------------------------------

	/**
	 * Susunan yang akan diterbitkan. Item nonaktif dan item yang menunjuk halaman
	 * yang tidak lagi terbit dilewati, tetapi tetap tersimpan sebagai draft.
	 */
	public function build_snapshot($menu)
	{
		$rows = $this->items($menu->id);
		$usable = array();
		foreach ($rows as $row)
		{
			if ((int) $row->is_enabled !== 1)
			{
				continue;
			}
			$href = $this->href($row);
			if ($href === NULL OR $href === '' OR ! app_is_safe_url($href))
			{
				continue;
			}
			if ($row->link_type === 'page' && $row->page_status !== 'published')
			{
				continue;
			}
			$usable[(int) $row->id] = array(
				'id' => (int) $row->id,
				'parent_id' => $row->parent_id === NULL ? NULL : (int) $row->parent_id,
				'label' => $row->label,
				'href' => $href,
				'link_type' => $row->link_type,
			);
		}

		$tree = array();
		$position = array();
		foreach ($usable as $id => $node)
		{
			if ($node['parent_id'] === NULL)
			{
				$position[$id] = count($tree);
				$tree[] = array('label' => $node['label'], 'href' => $node['href'], 'link_type' => $node['link_type'], 'children' => array());
			}
		}
		foreach ($usable as $node)
		{
			if ($node['parent_id'] !== NULL && isset($position[$node['parent_id']]))
			{
				$tree[$position[$node['parent_id']]]['children'][] = array(
					'label' => $node['label'], 'href' => $node['href'], 'link_type' => $node['link_type'],
				);
			}
		}

		return array(
			'location' => $menu->location,
			'label' => $menu->label,
			'items' => $tree,
		);
	}

	public function publish($menu, $reason, $user_id)
	{
		$snapshot = $this->build_snapshot($menu);
		$now = utc_now();
		$revision = NULL;

		db_transaction(function () use ($menu, $snapshot, $reason, $user_id, $now, &$revision) {
			$revision = (int) ($this->CI->db->select_max('revision_no')
				->where(array('target_type' => 'menu', 'target_id' => (int) $menu->id))
				->get('cms_publication_snapshots')->row('revision_no') ?: 0) + 1;

			db_must($this->CI->db->where(array('target_type' => 'menu', 'target_id' => (int) $menu->id))
				->where('superseded_at IS NULL', NULL, FALSE)
				->update('cms_publication_snapshots', array('superseded_at' => $now)), 'cms_snapshots.menu_supersede');

			db_must($this->CI->db->insert('cms_publication_snapshots', array(
				'target_type' => 'menu',
				'target_id' => (int) $menu->id,
				'page_version_id' => NULL,
				'revision_no' => $revision,
				'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
				'reason' => ($reason === '' OR $reason === NULL) ? NULL : mb_substr((string) $reason, 0, 500),
				'published_by' => $user_id ? (int) $user_id : NULL,
				'published_at' => $now,
			)), 'cms_snapshots.menu_insert');

			db_must($this->CI->db->where('id', (int) $menu->id)->update('cms_menus', array(
				'status' => 'published', 'published_at' => $now, 'updated_at' => $now,
			)), 'cms_menus.publish');
		});

		$this->CI->audit->log('cms.menu_published', 'cms_menu', $menu->public_id, array('revision' => $revision), FALSE, 'cms');
		$this->CI->audit->event('info', 'Menu "'.$menu->location.'" diterbitkan (revisi '.$revision.').', 'cms', array());
		$this->CI->public_cache->invalidate_menus();
		return $revision;
	}

	public function rollback($menu, $snapshot_id, $reason, $user_id)
	{
		$reason = trim((string) $reason);
		if (mb_strlen($reason) < 10)
		{
			throw new DomainRuleException('Tuliskan alasan rollback (minimal 10 karakter).', 422, array('reason' => 'Alasan wajib diisi.'));
		}
		$source = $this->CI->db->get_where('cms_publication_snapshots', array(
			'id' => (int) $snapshot_id, 'target_type' => 'menu', 'target_id' => (int) $menu->id,
		))->row();
		if ( ! $source)
		{
			throw new DomainRuleException('Revisi menu tidak ditemukan.', 404);
		}
		$now = utc_now();
		$revision = NULL;
		db_transaction(function () use ($menu, $source, $reason, $user_id, $now, &$revision) {
			$revision = (int) ($this->CI->db->select_max('revision_no')
				->where(array('target_type' => 'menu', 'target_id' => (int) $menu->id))
				->get('cms_publication_snapshots')->row('revision_no') ?: 0) + 1;
			db_must($this->CI->db->where(array('target_type' => 'menu', 'target_id' => (int) $menu->id))
				->where('superseded_at IS NULL', NULL, FALSE)
				->update('cms_publication_snapshots', array('superseded_at' => $now)), 'cms_snapshots.menu_supersede');
			db_must($this->CI->db->insert('cms_publication_snapshots', array(
				'target_type' => 'menu',
				'target_id' => (int) $menu->id,
				'revision_no' => $revision,
				'snapshot_json' => $source->snapshot_json,
				'reason' => mb_substr($reason, 0, 500),
				'rolled_back_from' => (int) $source->id,
				'published_by' => $user_id ? (int) $user_id : NULL,
				'published_at' => $now,
			)), 'cms_snapshots.menu_rollback');
			db_must($this->CI->db->where('id', (int) $menu->id)->update('cms_menus', array(
				'status' => 'published', 'published_at' => $now, 'updated_at' => $now,
			)), 'cms_menus.rollback');
		});
		$this->CI->audit->log('cms.menu_rolled_back', 'cms_menu', $menu->public_id, array(
			'revision' => $revision, 'from_revision' => (int) $source->revision_no,
		), FALSE, 'cms');
		$this->CI->public_cache->invalidate_menus();
		return $revision;
	}

	public function snapshots($menu, $limit = 10)
	{
		return $this->CI->db->select('s.*, u.display_name AS publisher')
			->from('cms_publication_snapshots s')->join('users u', 'u.id = s.published_by', 'left')
			->where(array('s.target_type' => 'menu', 's.target_id' => (int) $menu->id))
			->order_by('s.revision_no', 'DESC')->limit((int) $limit)->get()->result();
	}

	/** Susunan yang dibaca situs publik; NULL bila menu belum pernah diterbitkan. */
	public function published_menu($location)
	{
		$cached = $this->CI->public_cache->get('menu', $location);
		if (is_array($cached))
		{
			return $cached;
		}
		$row = $this->CI->db->select('s.snapshot_json')
			->from('cms_publication_snapshots s')->join('cms_menus m', 'm.id = s.target_id')
			->where('m.location', (string) $location)
			->where('s.target_type', 'menu')->where('s.superseded_at IS NULL', NULL, FALSE)
			->where('m.status', 'published')
			->order_by('s.revision_no', 'DESC')->limit(1)->get()->row();
		if ( ! $row)
		{
			return NULL;
		}
		$snapshot = json_decode((string) $row->snapshot_json, TRUE);
		if ( ! is_array($snapshot) OR ! isset($snapshot['items']))
		{
			return NULL;
		}
		$this->CI->public_cache->set('menu', $location, $snapshot['items'], 1800);
		return $snapshot['items'];
	}
}
