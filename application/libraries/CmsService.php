<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Halaman publik berbasis section registry (modul-backend §7, §8, §21).
 *
 * - Jenis section berasal dari daftar tertutup pada `config/cms_sections.php`.
 * - Konfigurasi section divalidasi server dan hanya menyimpan kunci yang dikenal.
 * - Data bisnis (statistik, potensi, berita) dirujuk lewat ID/preset dan diambil saat render,
 *   bukan disalin ke JSON.
 * - Draft tidak pernah dibaca frontend publik; publikasi ada di CmsPublicationService.
 */
class CmsService {

	/** @var CI_Controller */
	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->config->load('cms_sections', TRUE);
		$this->CI->load->library('FeatureModuleService', NULL, 'modules');
		$this->CI->load->model('Content_model', 'content');
	}

	/** Dimuat saat dibutuhkan saja; hanya section statistik yang memerlukannya. */
	protected function datasets()
	{
		$this->CI->load->library('DatasetService', NULL, 'datasets');
		return $this->CI->datasets;
	}

	// ------------------------------------------------------------------
	// Registry
	// ------------------------------------------------------------------

	public function section_types()
	{
		return (array) $this->CI->config->item('cms_section_types', 'cms_sections');
	}

	public function templates()
	{
		return (array) $this->CI->config->item('cms_templates', 'cms_sections');
	}

	public function section_type($type)
	{
		$types = $this->section_types();
		return isset($types[$type]) ? $types[$type] : NULL;
	}

	/** Section yang modulnya mati tidak dapat dipilih pengelola. */
	public function available_section_types()
	{
		$out = array();
		foreach ($this->section_types() as $code => $definition)
		{
			if (empty($definition['module']) OR $this->CI->modules->is_enabled($definition['module']))
			{
				$out[$code] = $definition;
			}
		}
		return $out;
	}

	public function section_available($type)
	{
		$definition = $this->section_type($type);
		if ( ! $definition)
		{
			return FALSE;
		}
		return empty($definition['module']) OR $this->CI->modules->is_enabled($definition['module']);
	}

	// ------------------------------------------------------------------
	// Halaman
	// ------------------------------------------------------------------

	public function page($page_key)
	{
		return $this->CI->db->get_where('cms_pages', array('page_key' => (string) $page_key))->row();
	}

	public function page_by_public_id($public_id)
	{
		return $this->CI->db->get_where('cms_pages', array('public_id' => (string) $public_id))->row();
	}

	public function pages()
	{
		return $this->CI->db->select('p.*, v.title, v.slug, v.nav_title')
			->from('cms_pages p')->join('cms_page_versions v', 'v.id = p.current_version_id', 'left')
			->where('p.archived_at IS NULL', NULL, FALSE)
			->order_by('p.sort_order')->order_by('p.id')->get()->result();
	}

	public function version($version_id)
	{
		return $this->CI->db->get_where('cms_page_versions', array('id' => (int) $version_id))->row();
	}

	/** Buat halaman baru beserta versi draft pertamanya. */
	public function create_page(array $input, $user_id)
	{
		$page_key = strtolower(trim((string) ($input['page_key'] ?? '')));
		if ( ! preg_match('/^[a-z][a-z0-9_]{2,59}$/', $page_key))
		{
			throw new DomainRuleException('Kunci halaman hanya huruf kecil, angka, dan garis bawah (3–60 karakter).', 422, array('page_key' => 'Kunci halaman tidak valid.'));
		}
		if ($this->page($page_key))
		{
			throw new DomainRuleException('Kunci halaman sudah dipakai.', 409, array('page_key' => 'Sudah dipakai.'));
		}
		$now = utc_now();
		$page_id = NULL;
		db_transaction(function () use ($page_key, $input, $user_id, $now, &$page_id) {
			db_must($this->CI->db->insert('cms_pages', array(
				'public_id' => $this->CI->crypto->public_id(),
				'page_key' => $page_key,
				'is_system' => 0,
				'sort_order' => (int) ($this->CI->db->select_max('sort_order')->get('cms_pages')->row('sort_order') ?: 0) + 10,
				'status' => 'draft',
				'created_by' => (int) $user_id,
				'created_at' => $now,
				'updated_at' => $now,
			)), 'cms_pages.insert');
			$page_id = (int) $this->CI->db->insert_id();
		});
		$page = $this->CI->db->get_where('cms_pages', array('id' => $page_id))->row();
		$this->save_page_version($page, $input, $user_id);
		$this->CI->audit->log('cms.page_created', 'cms_page', $page->public_id, array('page_key' => $page_key), FALSE, 'cms');
		return $this->CI->db->get_where('cms_pages', array('id' => $page_id))->row();
	}

	/**
	 * Simpan versi draft baru halaman. Versi lama tidak diubah sehingga riwayat tetap utuh.
	 * Slug yang berubah setelah halaman pernah terbit membuat redirect 301.
	 */
	public function save_page_version($page, array $input, $user_id)
	{
		$nav_title = trim((string) ($input['nav_title'] ?? ''));
		$title = trim((string) ($input['title'] ?? ''));
		// Slug kosong pada form berarti "ambil dari judul", bukan slug acak.
		$slug_input = trim((string) ($input['slug'] ?? ''));
		$slug = $this->CI->content_service->slugify($slug_input === '' ? $title : $slug_input, $page->page_key);
		$template = (string) ($input['template_code'] ?? 'page.standard');
		$errors = array();

		if (mb_strlen($title) < 3)
		{
			$errors['title'] = 'Judul halaman wajib diisi (minimal 3 karakter).';
		}
		if ($nav_title === '')
		{
			$nav_title = mb_substr($title, 0, 120);
		}
		if ( ! isset($this->templates()[$template]))
		{
			$errors['template_code'] = 'Template tidak dikenal.';
		}
		if ($errors)
		{
			throw new DomainRuleException('Periksa kembali isian halaman.', 422, $errors);
		}

		if (in_array($slug, $this->reserved_slugs(), TRUE))
		{
			throw new DomainRuleException('Slug tersebut dipakai route sistem. Pilih slug lain.', 422, array('slug' => 'Slug tidak boleh dipakai.'));
		}
		$clash = $this->CI->db->select('p.id')->from('cms_page_versions v')->join('cms_pages p', 'p.current_version_id = v.id')
			->where('v.slug', $slug)->where('p.id <>', (int) $page->id)->get()->row();
		if ($clash)
		{
			throw new DomainRuleException('Slug sudah dipakai halaman lain.', 409, array('slug' => 'Slug sudah dipakai.'));
		}

		$now = utc_now();
		$previous = $page->current_version_id ? $this->version($page->current_version_id) : NULL;
		$version_id = NULL;
		db_transaction(function () use ($page, $input, $user_id, $now, $nav_title, $title, $slug, $template, &$version_id) {
			$next = (int) ($this->CI->db->select_max('version_no')->where('page_id', (int) $page->id)
				->get('cms_page_versions')->row('version_no') ?: 0) + 1;
			db_must($this->CI->db->insert('cms_page_versions', array(
				'page_id' => (int) $page->id,
				'version_no' => $next,
				'nav_title' => mb_substr($nav_title, 0, 120),
				'title' => mb_substr($title, 0, 180),
				'slug' => $slug,
				'summary' => ($input['summary'] ?? '') === '' ? NULL : mb_substr((string) $input['summary'], 0, 500),
				'template_code' => $template,
				'seo_title' => ($input['seo_title'] ?? '') === '' ? NULL : mb_substr((string) $input['seo_title'], 0, 200),
				'seo_description' => ($input['seo_description'] ?? '') === '' ? NULL : mb_substr((string) $input['seo_description'], 0, 300),
				'cover_media_id' => empty($input['cover_media_id']) ? NULL : (int) $input['cover_media_id'],
				// Halaman baru boleh diindeks kecuali pengelola mematikannya dari form halaman.
				'search_indexable' => array_key_exists('search_indexable', $input) ? (empty($input['search_indexable']) ? 0 : 1) : 1,
				'created_by' => (int) $user_id,
				'created_at' => $now,
			)), 'cms_page_versions.insert');
			$version_id = (int) $this->CI->db->insert_id();

			// Mengedit draft mengembalikan status ke draft bila sebelumnya diminta perbaikan/disetujui.
			$status = in_array($page->status, array('published', 'unpublished', 'archived'), TRUE) ? $page->status : 'draft';
			db_must($this->CI->db->where('id', (int) $page->id)->update('cms_pages', array(
				'current_version_id' => $version_id,
				'status' => $status,
				'updated_at' => $now,
			)), 'cms_pages.version');
		});

		if ($previous && $previous->slug !== $slug && $page->published_version_id)
		{
			$this->CI->db->query(
				'INSERT INTO cms_redirects (old_path, new_path, status_code, source, active, created_at)
				 VALUES (?, ?, 301, ?, 1, ?) ON DUPLICATE KEY UPDATE new_path = VALUES(new_path), active = 1',
				array('/'.$previous->slug, '/'.$slug, 'cms_page', $now)
			);
		}
		$this->CI->audit->log('cms.page_version_saved', 'cms_page', $page->public_id, array('version_id' => $version_id), FALSE, 'cms');
		return $version_id;
	}

	/**
	 * Slug yang tidak boleh dipakai halaman CMS: seluruh segmen pertama route yang
	 * sudah terdaftar. Diturunkan dari `routes.php` agar daftar ini tidak pernah
	 * ketinggalan saat route baru ditambahkan.
	 */
	public function reserved_slugs()
	{
		static $reserved = NULL;
		if ($reserved !== NULL)
		{
			return $reserved;
		}
		$route = array();
		include APPPATH.'config/routes.php';
		$reserved = array('assets', 'media', 'index.php', 'robots.txt');
		foreach (array_keys($route) as $pattern)
		{
			if (in_array($pattern, array('default_controller', '404_override', 'translate_uri_dashes'), TRUE))
			{
				continue;
			}
			$first = explode('/', ltrim((string) $pattern, '/'))[0];
			// Segmen dinamis (:any/:num) dan catch-all tidak mengunci slug apa pun.
			if ($first === '' OR strpos($first, '(') !== FALSE)
			{
				continue;
			}
			$reserved[] = strtolower($first);
		}
		$reserved = array_values(array_unique($reserved));
		return $reserved;
	}

	/**
	 * Path publik halaman terbit: slug versi yang diterbitkan, diawali slug induk
	 * bila halaman memiliki induk yang juga terbit. NULL bila halaman belum terbit.
	 */
	public function public_path($page_id)
	{
		$row = $this->CI->db->select('p.id, p.parent_id, p.status, p.page_key, v.slug')
			->from('cms_pages p')->join('cms_page_versions v', 'v.id = p.published_version_id')
			->where('p.id', (int) $page_id)->get()->row();
		if ( ! $row OR $row->status !== 'published')
		{
			return NULL;
		}
		if ($row->parent_id === NULL)
		{
			// Beranda dilayani pada '/', bukan pada slug-nya.
			return ($row->page_key === 'home') ? '' : $row->slug;
		}
		$parent = $this->CI->db->select('p.status, v.slug')
			->from('cms_pages p')->join('cms_page_versions v', 'v.id = p.published_version_id')
			->where('p.id', (int) $row->parent_id)->get()->row();
		if ( ! $parent OR $parent->status !== 'published')
		{
			// Induk tidak terbit: halaman anak tidak memiliki alamat publik.
			return NULL;
		}
		return $parent->slug.'/'.$row->slug;
	}

	/** Halaman terbit berdasarkan path publik ('slug' atau 'induk/anak'). */
	public function page_by_path($path)
	{
		$segments = array_values(array_filter(explode('/', trim((string) $path, '/')), 'strlen'));
		if (count($segments) === 0 OR count($segments) > 2)
		{
			return NULL;
		}
		$row = $this->CI->db->select('p.*, v.slug, v.title, v.nav_title')
			->from('cms_pages p')->join('cms_page_versions v', 'v.id = p.published_version_id')
			->where('v.slug', end($segments))->where('p.status', 'published')
			// Beranda selalu dilayani pada '/', bukan pada slug-nya, agar tidak ada dua alamat.
			->where('p.page_key <>', 'home')
			->limit(1)->get()->row();
		if ( ! $row)
		{
			return NULL;
		}
		// Path harus cocok persis, termasuk induknya, agar satu halaman hanya punya satu alamat.
		return ($this->public_path((int) $row->id) === implode('/', $segments)) ? $row : NULL;
	}

	/** Halaman terbit untuk sitemap, pencarian, dan pemilih menu. */
	public function published_pages()
	{
		$rows = $this->CI->db->select('p.id, p.public_id, p.page_key, p.parent_id, p.published_at, v.title, v.nav_title, v.slug, v.summary, v.search_indexable')
			->from('cms_pages p')->join('cms_page_versions v', 'v.id = p.published_version_id')
			->where('p.status', 'published')->order_by('p.sort_order')->order_by('p.id')->get()->result();
		foreach ($rows as $row)
		{
			$row->path = $this->public_path((int) $row->id);
		}
		return array_values(array_filter($rows, function ($row) { return $row->path !== NULL; }));
	}

	// ------------------------------------------------------------------
	// Section
	// ------------------------------------------------------------------

	public function sections($page_id, $include_archived = FALSE)
	{
		$this->CI->db->select('s.*, v.title, v.subtitle, v.layout_variant, v.config_json, v.version_no')
			->from('cms_sections s')->join('cms_section_versions v', 'v.id = s.current_version_id', 'left')
			->where('s.page_id', (int) $page_id);
		if ( ! $include_archived)
		{
			$this->CI->db->where('s.archived_at IS NULL', NULL, FALSE);
		}
		$rows = $this->CI->db->order_by('s.sort_order')->order_by('s.id')->get()->result();
		foreach ($rows as $row)
		{
			$row->config = json_decode((string) $row->config_json, TRUE) ?: array();
		}
		return $rows;
	}

	public function section($public_id)
	{
		$row = $this->CI->db->select('s.*, v.title, v.subtitle, v.layout_variant, v.config_json')
			->from('cms_sections s')->join('cms_section_versions v', 'v.id = s.current_version_id', 'left')
			->where('s.public_id', (string) $public_id)->get()->row();
		if ($row)
		{
			$row->config = json_decode((string) $row->config_json, TRUE) ?: array();
		}
		return $row;
	}

	public function add_section($page, $type, $user_id)
	{
		$definition = $this->section_type($type);
		if ( ! $definition)
		{
			throw new DomainRuleException('Jenis section tidak dikenal.', 422, array('section_type' => 'Pilih jenis yang tersedia.'));
		}
		if ( ! $this->section_available($type))
		{
			throw new DomainRuleException('Jenis section ini membutuhkan modul yang belum aktif.', 409);
		}
		if ( ! empty($definition['singleton']))
		{
			$existing = $this->CI->db->where(array('page_id' => (int) $page->id, 'section_type' => $type))
				->where('archived_at IS NULL', NULL, FALSE)->count_all_results('cms_sections');
			if ($existing > 0)
			{
				throw new DomainRuleException('Section "'.$definition['label'].'" hanya boleh satu pada halaman ini.', 409);
			}
		}

		$now = utc_now();
		$layouts = array_keys($definition['layouts']);
		$section_id = NULL;
		db_transaction(function () use ($page, $type, $user_id, $now, $layouts, $definition, &$section_id) {
			$order = (int) ($this->CI->db->select_max('sort_order')->where('page_id', (int) $page->id)
				->get('cms_sections')->row('sort_order') ?: 0) + 10;
			db_must($this->CI->db->insert('cms_sections', array(
				'public_id' => $this->CI->crypto->public_id(),
				'page_id' => (int) $page->id,
				'section_type' => $type,
				'sort_order' => $order,
				'is_enabled' => 0,
				'created_by' => (int) $user_id,
				'created_at' => $now,
				'updated_at' => $now,
			)), 'cms_sections.insert');
			$section_id = (int) $this->CI->db->insert_id();

			$defaults = array();
			foreach ($definition['fields'] as $field => $meta)
			{
				if (isset($meta['default']))
				{
					$defaults[$field] = $meta['default'];
				}
			}
			db_must($this->CI->db->insert('cms_section_versions', array(
				'section_id' => $section_id,
				'version_no' => 1,
				'title' => $definition['label'],
				'layout_variant' => $layouts[0],
				'config_json' => json_encode($defaults, JSON_UNESCAPED_UNICODE),
				'created_by' => (int) $user_id,
				'created_at' => $now,
			)), 'cms_section_versions.insert');
			$version_id = (int) $this->CI->db->insert_id();
			db_must($this->CI->db->where('id', $section_id)->update('cms_sections', array('current_version_id' => $version_id)), 'cms_sections.version');
		});

		$section = $this->CI->db->get_where('cms_sections', array('id' => $section_id))->row();
		$this->CI->audit->log('cms.section_added', 'cms_section', $section->public_id, array('type' => $type), FALSE, 'cms');
		return $section;
	}

	/** Simpan versi baru section setelah konfigurasi divalidasi terhadap registry. */
	public function save_section($section, array $input, $user_id)
	{
		$definition = $this->section_type($section->section_type);
		if ( ! $definition)
		{
			throw new DomainRuleException('Jenis section tidak dikenal.', 422);
		}
		$layout = (string) ($input['layout_variant'] ?? '');
		if ( ! isset($definition['layouts'][$layout]))
		{
			throw new DomainRuleException('Varian layout tidak tersedia untuk section ini.', 422, array('layout_variant' => 'Pilih varian yang tersedia.'));
		}
		$config = $this->validate_config($section->section_type, $input);

		$now = utc_now();
		db_transaction(function () use ($section, $input, $user_id, $now, $layout, $config) {
			$next = (int) ($this->CI->db->select_max('version_no')->where('section_id', (int) $section->id)
				->get('cms_section_versions')->row('version_no') ?: 0) + 1;
			db_must($this->CI->db->insert('cms_section_versions', array(
				'section_id' => (int) $section->id,
				'version_no' => $next,
				'title' => ($input['title'] ?? '') === '' ? NULL : mb_substr((string) $input['title'], 0, 180),
				'subtitle' => ($input['subtitle'] ?? '') === '' ? NULL : mb_substr((string) $input['subtitle'], 0, 300),
				'layout_variant' => $layout,
				'config_json' => json_encode($config, JSON_UNESCAPED_UNICODE),
				'created_by' => (int) $user_id,
				'created_at' => $now,
			)), 'cms_section_versions.insert');
			$version_id = (int) $this->CI->db->insert_id();
			db_must($this->CI->db->where('id', (int) $section->id)->update('cms_sections', array(
				'current_version_id' => $version_id,
				'updated_at' => $now,
			)), 'cms_sections.update');
		});
		$this->CI->audit->log('cms.section_saved', 'cms_section', $section->public_id, array('type' => $section->section_type), FALSE, 'cms');
	}

	public function toggle_section($section, $enabled, $user_id)
	{
		if ($enabled && ! $this->section_available($section->section_type))
		{
			throw new DomainRuleException('Section ini membutuhkan modul yang belum aktif.', 409);
		}
		db_must($this->CI->db->where('id', (int) $section->id)->update('cms_sections', array(
			'is_enabled' => $enabled ? 1 : 0,
			'updated_at' => utc_now(),
		)), 'cms_sections.toggle');
		$this->CI->audit->log('cms.section_toggled', 'cms_section', $section->public_id, array('enabled' => $enabled ? 1 : 0), FALSE, 'cms');
	}

	public function archive_section($section, $user_id)
	{
		db_must($this->CI->db->where('id', (int) $section->id)->update('cms_sections', array(
			'is_enabled' => 0,
			'archived_at' => utc_now(),
			'updated_at' => utc_now(),
		)), 'cms_sections.archive');
		$this->CI->audit->log('cms.section_archived', 'cms_section', $section->public_id, array(), FALSE, 'cms');
	}

	/** Urutan baru diterapkan dalam satu transaksi; id yang bukan milik halaman ditolak. */
	public function reorder_sections($page, array $public_ids, $user_id)
	{
		$sections = $this->sections($page->id);
		$known = array();
		foreach ($sections as $section)
		{
			$known[$section->public_id] = $section;
		}
		$public_ids = array_values(array_unique(array_filter(array_map('strval', $public_ids))));
		foreach ($public_ids as $public_id)
		{
			if ( ! isset($known[$public_id]))
			{
				throw new DomainRuleException('Urutan section tidak valid.', 422);
			}
		}
		if (count($public_ids) !== count($known))
		{
			throw new DomainRuleException('Urutan harus memuat seluruh section pada halaman ini.', 422);
		}

		$now = utc_now();
		db_transaction(function () use ($public_ids, $known, $now) {
			foreach ($public_ids as $index => $public_id)
			{
				db_must($this->CI->db->where('id', (int) $known[$public_id]->id)->update('cms_sections', array(
					'sort_order' => ($index + 1) * 10,
					'updated_at' => $now,
				)), 'cms_sections.reorder');
			}
		});
		$this->CI->audit->log('cms.sections_reordered', 'cms_page', $page->public_id, array('count' => count($public_ids)), FALSE, 'cms');
	}

	// ------------------------------------------------------------------
	// Validasi konfigurasi
	// ------------------------------------------------------------------

	/**
	 * Hanya kunci yang dikenal registry yang disimpan; nilai divalidasi per tipe field.
	 * @throws DomainRuleException
	 */
	public function validate_config($type, array $input)
	{
		$definition = $this->section_type($type);
		$config = array();
		$errors = array();

		foreach ($definition['fields'] as $field => $meta)
		{
			$raw = $input[$field] ?? NULL;
			switch ($meta['type'])
			{
				case 'text':
				case 'textarea':
					$value = trim((string) $raw);
					if ($value === '')
					{
						if ( ! empty($meta['required']))
						{
							$errors[$field] = $meta['label'].' wajib diisi.';
						}
						break;
					}
					$config[$field] = mb_substr($value, 0, (int) ($meta['max'] ?? 1000));
					break;

				case 'select':
					$value = (string) $raw;
					if ( ! isset($meta['options'][$value]))
					{
						if ( ! empty($meta['required']))
						{
							$errors[$field] = $meta['label'].' wajib dipilih.';
						}
						break;
					}
					$config[$field] = $value;
					break;

				case 'number':
					if ($raw === NULL OR $raw === '')
					{
						if ( ! empty($meta['required']))
						{
							$errors[$field] = $meta['label'].' wajib diisi.';
						}
						break;
					}
					$value = (int) $raw;
					if (isset($meta['min']) && $value < $meta['min'] OR isset($meta['max']) && $value > $meta['max'])
					{
						$errors[$field] = $meta['label'].' di luar rentang yang diizinkan.';
						break;
					}
					$config[$field] = $value;
					break;

				case 'checkbox':
					$config[$field] = $raw ? 1 : 0;
					break;

				case 'media':
					if (empty($raw))
					{
						break;
					}
					$media = $this->CI->content->media((int) $raw);
					if ( ! $media)
					{
						$errors[$field] = 'Media tidak ditemukan atau belum disetujui untuk publik.';
						break;
					}
					$config[$field] = (int) $raw;
					break;

				case 'links':
					$links = $this->validate_links($raw, $meta, $field, $errors);
					if ($links !== NULL)
					{
						$config[$field] = $links;
					}
					break;

				case 'ids':
					$ids = $this->validate_ids($raw, $meta, $field, $errors);
					if ($ids !== NULL)
					{
						$config[$field] = $ids;
					}
					break;

				// Slug dataset yang sudah TERBIT; dataset draft atau ditarik tidak dapat dipilih.
				case 'dataset':
					$slug = trim((string) $raw);
					if ($slug === '')
					{
						if ( ! empty($meta['required']))
						{
							$errors[$field] = $meta['label'].' wajib dipilih.';
						}
						break;
					}
					if ( ! $this->datasets()->published_dataset($slug))
					{
						$errors[$field] = 'Dataset itu belum terbit, jadi belum dapat dipakai pada halaman publik.';
						break;
					}
					$config[$field] = $slug;
					break;

				// Kode indikator yang benar-benar ada pada snapshot dataset yang dipilih di atas.
				case 'dataset_series':
					$codes = is_array($raw) ? $raw : array_filter(array_map('trim', explode(',', (string) $raw)));
					$codes = array_values(array_unique(array_map('strval', $codes)));
					if (empty($codes))
					{
						if ( ! empty($meta['required']))
						{
							$errors[$field] = $meta['label'].' wajib dipilih.';
						}
						break;
					}
					if (count($codes) > (int) ($meta['max_items'] ?? 4))
					{
						$errors[$field] = 'Maksimal '.(int) $meta['max_items'].' indikator.';
						break;
					}
					$snapshot = empty($config['dataset_slug']) ? NULL : $this->datasets()->published_dataset($config['dataset_slug']);
					if ( ! $snapshot)
					{
						// Datasetnya sendiri sudah ditolak di atas; jangan menumpuk pesan.
						break;
					}
					$available = array();
					foreach ($snapshot['series'] as $series)
					{
						$available[] = $series['code'];
					}
					$unknown = array_diff($codes, $available);
					if ($unknown)
					{
						$errors[$field] = 'Indikator berikut tidak ada pada dataset itu: '.implode(', ', $unknown).'.';
						break;
					}
					$config[$field] = $codes;
					break;

				case 'indicators':
					$codes = is_array($raw) ? $raw : array_filter(array_map('trim', explode(',', (string) $raw)));
					$codes = array_values(array_unique(array_map('strval', $codes)));
					if (empty($codes))
					{
						if ( ! empty($meta['required']))
						{
							$errors[$field] = $meta['label'].' wajib dipilih.';
						}
						break;
					}
					if (count($codes) > (int) ($meta['max_items'] ?? 4))
					{
						$errors[$field] = 'Maksimal '.(int) $meta['max_items'].' indikator.';
						break;
					}
					$known = array();
					foreach ($this->CI->db->select('code')->where_in('code', $codes)->get('statistic_indicators')->result() as $row)
					{
						$known[] = $row->code;
					}
					$unknown = array_diff($codes, $known);
					if ($unknown)
					{
						$errors[$field] = 'Indikator tidak dikenal: '.implode(', ', $unknown);
						break;
					}
					$config[$field] = $codes;
					break;
			}
		}

		$this->validate_type_rules($type, $config, $errors);
		if ($errors)
		{
			throw new DomainRuleException('Periksa kembali konfigurasi section.', 422, $errors);
		}
		return $config;
	}

	protected function validate_links($raw, array $meta, $field, array &$errors)
	{
		$items = is_array($raw) ? $raw : array();
		$out = array();
		foreach ($items as $item)
		{
			$label = trim((string) ($item['label'] ?? ''));
			$url = trim((string) ($item['url'] ?? ''));
			if ($label === '' && $url === '')
			{
				continue;
			}
			if ($label === '' OR $url === '')
			{
				$errors[$field] = 'Setiap tautan membutuhkan label dan alamat.';
				return NULL;
			}
			// Hanya path internal atau URL http/https; `javascript:` dan `data:` ditolak.
			if (strpos($url, '/') === 0)
			{
				if ( ! app_is_safe_url(base_url(ltrim($url, '/'))))
				{
					$errors[$field] = 'Alamat internal tidak valid.';
					return NULL;
				}
			}
			elseif ( ! app_is_safe_url($url))
			{
				$errors[$field] = 'Gunakan path internal (diawali /) atau URL http/https yang valid.';
				return NULL;
			}
			$link = array('label' => mb_substr($label, 0, 80), 'url' => mb_substr($url, 0, 300));
			$description = trim((string) ($item['description'] ?? ''));
			if ($description !== '')
			{
				$link['description'] = mb_substr($description, 0, 160);
			}
			$out[] = $link;
		}
		if (empty($out))
		{
			if ( ! empty($meta['required']))
			{
				$errors[$field] = $meta['label'].' wajib diisi.';
				return NULL;
			}
			return array();
		}
		if (count($out) > (int) ($meta['max_items'] ?? 8))
		{
			$errors[$field] = 'Maksimal '.(int) $meta['max_items'].' tautan.';
			return NULL;
		}
		return $out;
	}

	protected function validate_ids($raw, array $meta, $field, array &$errors)
	{
		$ids = is_array($raw) ? $raw : array_filter(array_map('trim', explode(',', (string) $raw)));
		$ids = array_values(array_unique(array_map('intval', $ids)));
		$ids = array_values(array_filter($ids, function ($id) { return $id > 0; }));
		if (empty($ids))
		{
			if ( ! empty($meta['required']))
			{
				$errors[$field] = $meta['label'].' wajib dipilih.';
				return NULL;
			}
			return array();
		}
		if (count($ids) > (int) ($meta['max_items'] ?? 12))
		{
			$errors[$field] = 'Maksimal '.(int) $meta['max_items'].' item.';
			return NULL;
		}

		$entity = $meta['entity'] ?? '';
		if ($entity === 'potential')
		{
			// Unggulan wajib punya judul, ringkasan, cover, verifikasi, dan status terbit
			// (modul-frontend 4.4). Cover ikut diperiksa supaya kartu tidak pernah kosong.
			$valid = $this->CI->db->select('id')->where_in('id', $ids)
				->where('publication_status', 'published')->where('verification_status', 'verified')
				->where('cover_media_id IS NOT NULL', NULL, FALSE)
				->where('deleted_at IS NULL', NULL, FALSE)->get('potentials')->result();
			if (count($valid) !== count($ids))
			{
				$errors[$field] = 'Hanya potensi yang sudah terbit, terverifikasi, dan punya foto sampul yang dapat dipilih.';
				return NULL;
			}
		}
		elseif ($entity === 'gallery')
		{
			$valid = $this->CI->db->select('id')->where_in('id', $ids)->where('publication_status', 'published')
				->get('galleries')->result();
			if (count($valid) !== count($ids))
			{
				$errors[$field] = 'Hanya album galeri yang sudah terbit yang dapat dipilih.';
				return NULL;
			}
		}
		return $ids;
	}

	/** Aturan khusus per jenis section (modul-backend §7.3). */
	protected function validate_type_rules($type, array $config, array &$errors)
	{
		if ($type === 'hero')
		{
			$mode = $config['mode'] ?? 'image';
			// Mode video wajib punya poster; mode animasi memakai ilustrasi bawaan (gradasi + SVG)
			// yang selalu dirender lebih dulu, sehingga foto fallback bersifat opsional di sana.
			if ($mode === 'video' && empty($config['fallback_media_id']))
			{
				$errors['fallback_media_id'] = 'Mode video wajib memiliki foto poster sebagai fallback.';
			}
			if ($mode === 'video' && empty($config['video_media_id']))
			{
				$errors['video_media_id'] = 'Pilih media video atau ganti mode.';
			}
		}
		if ($type === 'statistics')
		{
			$snapshot = empty($config['dataset_slug']) ? NULL : $this->datasets()->published_dataset($config['dataset_slug']);
			$codes = $config['series'] ?? array();
			if ($snapshot && $codes)
			{
				// Kartu berisi angka. Kalau seluruh pilihan kosong atau disamarkan, section ini
				// hanya akan menghasilkan kartu tanpa isi, jadi ditolak sejak awal.
				$shown = 0;
				foreach ($snapshot['series'] as $series)
				{
					if (in_array($series['code'], $codes, TRUE) && empty($series['suppressed']) && $series['value'] !== NULL)
					{
						$shown++;
					}
				}
				if ($shown === 0)
				{
					$errors['series'] = 'Tidak ada indikator terpilih yang nilainya dapat ditampilkan (kosong atau disamarkan).';
				}
			}
		}
		if ($type === 'featured_potentials' && ($config['selection'] ?? 'auto') === 'manual' && empty($config['item_ids']))
		{
			$errors['item_ids'] = 'Pilih minimal satu potensi atau gunakan pemilihan otomatis.';
		}
	}

	// ------------------------------------------------------------------
	// Data untuk render
	// ------------------------------------------------------------------

	/**
	 * Ambil data bisnis untuk satu section berdasarkan konfigurasinya.
	 * Selalu memakai query scope publik (hanya konten terbit).
	 */
	public function section_data($type, array $config)
	{
		switch ($type)
		{
			case 'hero':
				return array(
					'fallback' => empty($config['fallback_media_id']) ? NULL : $this->CI->content->media((int) $config['fallback_media_id']),
					'video' => empty($config['video_media_id']) ? NULL : $this->CI->content->media((int) $config['video_media_id']),
					'side' => empty($config['side_media_id']) ? NULL : $this->CI->content->media((int) $config['side_media_id']),
					'regional' => $this->regional_photos(),
				);

			case 'profile_summary':
				return array(
					'media' => empty($config['media_id']) ? NULL : $this->CI->content->media((int) $config['media_id']),
					'regional' => $this->regional_photos(),
				);

			case 'text_image':
				return array('media' => empty($config['media_id']) ? NULL : $this->CI->content->media((int) $config['media_id']));

			case 'statistics':
				// Angka beranda berasal dari snapshot dataset yang terbit, bukan dari tabel kerja.
				$snapshot = empty($config['dataset_slug']) ? NULL : $this->datasets()->published_dataset($config['dataset_slug']);
				if ( ! $snapshot)
				{
					return array('dataset' => NULL, 'version' => NULL, 'published_at' => NULL, 'series' => array());
				}
				$by_code = array();
				foreach ($snapshot['series'] as $row)
				{
					$by_code[$row['code']] = $row;
				}
				// Urutan mengikuti pilihan pengelola, bukan urutan indikator di dataset.
				$series = array();
				foreach (($config['series'] ?? array()) as $code)
				{
					if (isset($by_code[$code]))
					{
						$series[] = $by_code[$code];
					}
				}
				return array(
					'dataset' => $snapshot['dataset'],
					'version' => $snapshot['version'],
					'published_at' => $snapshot['published_at'] ?? NULL,
					'series' => $series,
				);

			case 'budget_summary':
				// Frontend tidak pernah membaca tabel kerja keuangan (modul-backend 15.4).
				$this->CI->load->library('BudgetService', NULL, 'budgets');
				return array('budget' => $this->CI->budgets->published_budget((int) ($config['budget_year'] ?? 0)));

			case 'featured_potentials':
				if (($config['selection'] ?? 'auto') === 'manual' && ! empty($config['item_ids']))
				{
					$items = array();
					foreach ($this->CI->content->potentials(NULL, 50) as $potential)
					{
						if (in_array((int) $potential->id, array_map('intval', $config['item_ids']), TRUE))
						{
							$items[] = $potential;
						}
					}
					return array('items' => $items, 'categories' => $this->CI->content->potential_categories());
				}
				return array(
					'items' => $this->CI->content->potentials(NULL, (int) ($config['limit'] ?? 6)),
					'categories' => $this->CI->content->potential_categories(),
				);

			case 'featured_news':
				return array('items' => $this->CI->content->posts(array('news', 'announcement'), NULL, (int) ($config['limit'] ?? 4)));

			case 'upcoming_agenda':
				$month = agenda_month();
				return array(
					'items' => $this->CI->content->events(TRUE, (int) ($config['limit'] ?? 4)),
					'month' => $month,
					'weeks' => agenda_month_grid($month, $this->CI->content->events_between($month['from_utc'], $month['to_utc'])),
				);

			case 'gallery_preview':
				$ids = $config['gallery_id'] ?? array();
				$gallery = NULL;
				if ($ids)
				{
					$gallery = $this->CI->db->get_where('galleries', array('id' => (int) $ids[0], 'publication_status' => 'published'))->row();
				}
				$items = array();
				if ($gallery)
				{
					$items = $this->CI->db->select('m.*')->from('gallery_items gi')
						->join('media_assets m', 'm.id = gi.media_asset_id')
						->where('gi.gallery_id', (int) $gallery->id)
						->where('m.publication_status', 'published')->where('m.deleted_at IS NULL', NULL, FALSE)
						->order_by('gi.sort_order')->limit((int) ($config['limit'] ?? 6))->get()->result();
				}
				return array('gallery' => $gallery, 'items' => $items);

			case 'public_documents':
				$documents = $this->CI->content->public_documents();
				return array('items' => array_slice($documents, 0, (int) ($config['limit'] ?? 4)));

			case 'verified_map':
				return array(
					'features' => $this->CI->content->map_features(array((string) ($config['feature_types'] ?? 'office'))),
					'center' => $this->CI->config->item('village_center', 'app'),
				);

			default:
				return array();
		}
	}

	/** Foto kawasan Kec. Kertasari (bukan foto desa) sebagai ilustrasi sampai foto asli diunggah. */
	protected function regional_photos()
	{
		$this->CI->config->load('regional_photos', TRUE);
		return array(
			'area' => (string) $this->CI->config->item('regional_photos_area', 'regional_photos'),
			'photos' => (array) $this->CI->config->item('regional_photos', 'regional_photos'),
		);
	}
}
