<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CMS: definisi skema konten, sanitasi HTML berbasis allowlist, slug unik,
 * revisi, serta alur publikasi draft → in_review → published → archived.
 *
 * Tidak ada template/PHP dari database yang dieksekusi; rich text disanitasi
 * di server (HTML Purifier), bukan sekadar mengandalkan editor.
 */
class ContentService {

	/** @var CI_Controller */
	protected $CI;

	/** @var HTMLPurifier|null */
	protected $purifier = NULL;

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	// ------------------------------------------------------------ Skema

	public function types()
	{
		return array(
			'berita' => 'Berita &amp; pengumuman',
			'potensi' => 'Potensi desa',
			'agenda' => 'Agenda kegiatan',
			'galeri' => 'Galeri',
			'dokumen' => 'Dokumen publik',
			'pejabat' => 'Jabatan &amp; pejabat',
			'menu' => 'Menu navigasi',
			'hero' => 'Hero beranda',
		);
	}

	/**
	 * @return array{table:string,label:string,fields:array,publishable:bool,soft_delete:bool,slug_field:?string,order:string}
	 */
	public function schema($type)
	{
		$media_options = NULL; // diisi controller (daftar media terbit)
		$schemas = array(
			'berita' => array(
				'table' => 'posts',
				'label' => 'Berita & pengumuman',
				'singular' => 'Artikel',
				'publishable' => TRUE,
				'soft_delete' => TRUE,
				'slug_field' => 'slug',
				'slug_source' => 'title',
				'order' => 'created_at DESC',
				'revisions' => TRUE,
				'list_columns' => array('title' => 'Judul', 'type' => 'Tipe', 'publication_status' => 'Status', 'published_at' => 'Terbit'),
				'fields' => array(
					'type' => array('label' => 'Tipe', 'type' => 'select', 'options' => array('news' => 'Berita', 'announcement' => 'Pengumuman'), 'required' => TRUE),
					'category_id' => array('label' => 'Kategori', 'type' => 'category', 'content_type' => 'news'),
					'title' => array('label' => 'Judul', 'type' => 'text', 'required' => TRUE, 'max' => 200, 'min' => 5),
					'slug' => array('label' => 'Slug URL', 'type' => 'slug', 'max' => 220, 'help' => 'Dibuat otomatis dari judul bila dikosongkan. Mengubah slug membuat pengalihan 301 dari slug lama.'),
					'excerpt' => array('label' => 'Ringkasan', 'type' => 'textarea', 'max' => 500, 'rows' => 3),
					'body_html' => array('label' => 'Isi', 'type' => 'richtext', 'required' => TRUE),
					'cover_media_id' => array('label' => 'Gambar sampul', 'type' => 'media'),
					'is_featured' => array('label' => 'Tampilkan sebagai unggulan', 'type' => 'checkbox'),
					'publish_at' => array('label' => 'Jadwal terbit', 'type' => 'datetime', 'help' => 'Kosongkan untuk terbit segera saat status diubah menjadi Terbit.'),
					'seo_title' => array('label' => 'Judul SEO', 'type' => 'text', 'max' => 200),
					'seo_description' => array('label' => 'Deskripsi SEO', 'type' => 'textarea', 'max' => 300, 'rows' => 2),
				),
			),
			'potensi' => array(
				'table' => 'potentials',
				'label' => 'Potensi desa',
				'singular' => 'Potensi',
				'publishable' => TRUE,
				'soft_delete' => TRUE,
				'slug_field' => 'slug',
				'slug_source' => 'title',
				'order' => 'sort_order ASC, id DESC',
				'list_columns' => array('title' => 'Judul', 'verification_status' => 'Verifikasi', 'publication_status' => 'Status'),
				'fields' => array(
					'category_id' => array('label' => 'Kategori', 'type' => 'category', 'content_type' => 'potential', 'required' => TRUE),
					'title' => array('label' => 'Judul', 'type' => 'text', 'required' => TRUE, 'max' => 200, 'min' => 3),
					'slug' => array('label' => 'Slug URL', 'type' => 'slug', 'max' => 220),
					'summary' => array('label' => 'Ringkasan', 'type' => 'textarea', 'required' => TRUE, 'max' => 500, 'rows' => 3),
					'body_html' => array('label' => 'Deskripsi', 'type' => 'richtext', 'required' => TRUE),
					'cover_media_id' => array('label' => 'Foto sampul', 'type' => 'media'),
					'access_status' => array('label' => 'Status akses pengunjung', 'type' => 'select', 'options_config' => 'potential_access_statuses',
						'help' => 'Potensi tanpa nama tempat, akses, dan keselamatan yang jelas tetap draft.'),
					'manager_name' => array('label' => 'Pengelola atau kontak resmi', 'type' => 'text', 'max' => 180),
					'safety_note' => array('label' => 'Catatan keselamatan', 'type' => 'textarea', 'max' => 600, 'rows' => 3),
					'public_contact' => array('label' => 'Kontak publik', 'type' => 'text', 'max' => 255, 'help' => 'Hanya diisi bila pemilik kontak mengizinkan publikasi.'),
					'contact_permission' => array('label' => 'Ada izin publikasi kontak', 'type' => 'checkbox'),
					'source_year' => array('label' => 'Tahun sumber', 'type' => 'number', 'min' => 1900, 'max' => 2100),
					'verification_status' => array('label' => 'Status verifikasi data', 'type' => 'select', 'options_config' => 'verification_statuses', 'required' => TRUE),
					'sort_order' => array('label' => 'Urutan tampil', 'type' => 'number', 'min' => 0, 'max' => 9999),
				),
			),
			'agenda' => array(
				'table' => 'events',
				'label' => 'Agenda kegiatan',
				'singular' => 'Agenda',
				'publishable' => TRUE,
				'soft_delete' => TRUE,
				'slug_field' => 'slug',
				'slug_source' => 'title',
				'order' => 'starts_at DESC',
				'list_columns' => array('title' => 'Kegiatan', 'starts_at' => 'Mulai', 'publication_status' => 'Status'),
				'fields' => array(
					'title' => array('label' => 'Nama kegiatan', 'type' => 'text', 'required' => TRUE, 'max' => 200, 'min' => 3),
					'slug' => array('label' => 'Slug URL', 'type' => 'slug', 'max' => 220),
					'summary' => array('label' => 'Ringkasan', 'type' => 'textarea', 'max' => 500, 'rows' => 2),
					'description_html' => array('label' => 'Deskripsi', 'type' => 'richtext'),
					'starts_at' => array('label' => 'Mulai (WIB)', 'type' => 'datetime', 'required' => TRUE),
					'ends_at' => array('label' => 'Selesai (WIB)', 'type' => 'datetime'),
					'location_text' => array('label' => 'Lokasi', 'type' => 'text', 'max' => 255),
					'organizer' => array('label' => 'Penyelenggara', 'type' => 'text', 'max' => 150),
					'poster_media_id' => array('label' => 'Poster', 'type' => 'media'),
				),
			),
			'galeri' => array(
				'table' => 'galleries',
				'label' => 'Galeri',
				'singular' => 'Album',
				'publishable' => TRUE,
				'soft_delete' => TRUE,
				'slug_field' => 'slug',
				'slug_source' => 'title',
				'order' => 'id DESC',
				'list_columns' => array('title' => 'Album', 'publication_status' => 'Status'),
				'fields' => array(
					'title' => array('label' => 'Judul album', 'type' => 'text', 'required' => TRUE, 'max' => 200, 'min' => 3),
					'slug' => array('label' => 'Slug URL', 'type' => 'slug', 'max' => 220),
					'description' => array('label' => 'Deskripsi', 'type' => 'textarea', 'max' => 1000, 'rows' => 3),
					'gallery_items' => array('label' => 'Foto album', 'type' => 'media_multi'),
				),
			),
			'dokumen' => array(
				'table' => 'public_documents',
				'label' => 'Dokumen publik',
				'singular' => 'Dokumen',
				'publishable' => TRUE,
				'soft_delete' => TRUE,
				'slug_field' => NULL,
				'order' => 'source_year DESC, id DESC',
				'list_columns' => array('title' => 'Dokumen', 'source_year' => 'Tahun', 'publication_status' => 'Status'),
				'fields' => array(
					'title' => array('label' => 'Judul dokumen', 'type' => 'text', 'required' => TRUE, 'max' => 200, 'min' => 3),
					'category_id' => array('label' => 'Kategori', 'type' => 'category', 'content_type' => 'document'),
					'source_year' => array('label' => 'Tahun', 'type' => 'number', 'min' => 1900, 'max' => 2100),
					'description' => array('label' => 'Keterangan', 'type' => 'textarea', 'max' => 500, 'rows' => 2),
					'media_asset_id' => array('label' => 'Berkas dokumen', 'type' => 'media', 'required' => TRUE, 'help' => 'Unggah berkas pada menu Media terlebih dahulu (PDF).'),
					'version_label' => array('label' => 'Versi', 'type' => 'text', 'required' => TRUE, 'max' => 50, 'help' => 'Contoh: "Final 2024" atau "Revisi 2".'),
				),
			),
			'pejabat' => array(
				'table' => 'officials',
				'label' => 'Pejabat desa',
				'singular' => 'Pejabat',
				'publishable' => TRUE,
				'soft_delete' => FALSE,
				'slug_field' => NULL,
				'order' => 'sort_order ASC, id DESC',
				'list_columns' => array('name' => 'Nama', 'verification_status' => 'Verifikasi', 'publication_status' => 'Status'),
				'fields' => array(
					'position_id' => array('label' => 'Jabatan', 'type' => 'position', 'required' => TRUE),
					'name' => array('label' => 'Nama pejabat', 'type' => 'text', 'required' => TRUE, 'max' => 150, 'min' => 3),
					'photo_media_id' => array('label' => 'Foto', 'type' => 'media'),
					'term_start' => array('label' => 'Awal periode', 'type' => 'date'),
					'term_end' => array('label' => 'Akhir periode', 'type' => 'date'),
					'source_note' => array('label' => 'Catatan sumber', 'type' => 'text', 'max' => 255, 'help' => 'Contoh: "Disebut pada dokumen profil 2023; dikonfirmasi kembali pada 2026".'),
					'verification_status' => array('label' => 'Status verifikasi', 'type' => 'select', 'options_config' => 'verification_statuses', 'required' => TRUE),
					'sort_order' => array('label' => 'Urutan', 'type' => 'number', 'min' => 0, 'max' => 9999),
				),
			),
			'menu' => array(
				'table' => 'navigation_items',
				'label' => 'Menu navigasi',
				'singular' => 'Item menu',
				'publishable' => FALSE,
				'soft_delete' => FALSE,
				'slug_field' => NULL,
				'order' => 'menu_key ASC, sort_order ASC',
				'list_columns' => array('label' => 'Label', 'menu_key' => 'Menu', 'target_url' => 'Tautan', 'active' => 'Aktif'),
				'fields' => array(
					'menu_key' => array('label' => 'Posisi menu', 'type' => 'select', 'options' => array('main' => 'Menu utama', 'footer' => 'Footer'), 'required' => TRUE),
					'label' => array('label' => 'Label', 'type' => 'text', 'required' => TRUE, 'max' => 80),
					'target_url' => array('label' => 'Tautan', 'type' => 'url_internal', 'required' => TRUE, 'max' => 500, 'help' => 'Gunakan path internal seperti /berita, atau URL lengkap http(s). Skema javascript: ditolak.'),
					'parent_id' => array('label' => 'Induk menu', 'type' => 'menu_parent'),
					'sort_order' => array('label' => 'Urutan', 'type' => 'number', 'min' => 0, 'max' => 9999),
					'active' => array('label' => 'Aktif', 'type' => 'checkbox'),
				),
			),
			'hero' => array(
				'table' => 'hero_slides',
				'label' => 'Hero beranda',
				'singular' => 'Hero',
				'publishable' => FALSE,
				'soft_delete' => FALSE,
				'slug_field' => NULL,
				'order' => 'sort_order ASC',
				'list_columns' => array('title' => 'Judul', 'animation_mode' => 'Mode', 'active' => 'Aktif'),
				'fields' => array(
					'title' => array('label' => 'Judul hero', 'type' => 'text', 'required' => TRUE, 'max' => 200),
					'subtitle' => array('label' => 'Subjudul', 'type' => 'text', 'max' => 300),
					'image_media_id' => array('label' => 'Foto hero', 'type' => 'media'),
					'animation_mode' => array('label' => 'Mode tampilan', 'type' => 'select', 'required' => TRUE,
						'options' => array('image' => 'Foto statis', 'video' => 'Video (butuh media video)', 'three' => 'Animasi Three.js + fallback')),
					'cta_primary_json' => array('label' => 'Tombol utama', 'type' => 'cta'),
					'cta_secondary_json' => array('label' => 'Tombol kedua', 'type' => 'cta'),
					'sort_order' => array('label' => 'Urutan', 'type' => 'number', 'min' => 0, 'max' => 999),
					'active' => array('label' => 'Aktif', 'type' => 'checkbox'),
				),
			),
		);
		if ( ! isset($schemas[$type]))
		{
			throw new DomainRuleException('Jenis konten tidak dikenal.', 404);
		}
		return $schemas[$type];
	}

	// ------------------------------------------------------------ Sanitasi

	/** Sanitasi rich text berbasis allowlist (server-side). */
	public function sanitize_html($html)
	{
		if ($this->purifier === NULL)
		{
			$cache = ROOTPATH.'storage/cache/htmlpurifier';
			if ( ! is_dir($cache))
			{
				@mkdir($cache, 0750, TRUE);
			}
			$config = HTMLPurifier_Config::createDefault();
			$config->set('Cache.SerializerPath', $cache);
			$config->set('HTML.Doctype', 'HTML 4.01 Transitional');
			$config->set('HTML.Allowed', 'p,br,strong,em,u,ul,ol,li,h2,h3,h4,blockquote,a[href|title|rel],img[src|alt|width|height],table,thead,tbody,tr,th,td,hr');
			$config->set('HTML.TargetBlank', TRUE);
			$config->set('AutoFormat.RemoveEmpty', TRUE);
			$config->set('URI.AllowedSchemes', array('http' => TRUE, 'https' => TRUE, 'mailto' => TRUE, 'tel' => TRUE));
			// Gambar hanya dari media desa sendiri (path /media/...).
			$config->set('URI.Base', base_url());
			$config->set('URI.MakeAbsolute', FALSE);
			$this->purifier = new HTMLPurifier($config);
		}
		$clean = $this->purifier->purify((string) $html);
		// Tolak gambar dari luar situs: hanya /media/ yang diizinkan.
		$clean = preg_replace_callback('/<img[^>]+src="([^"]*)"[^>]*>/i', function ($m) {
			$src = $m[1];
			$path = parse_url($src, PHP_URL_PATH);
			$host = parse_url($src, PHP_URL_HOST);
			$own_host = parse_url(base_url(), PHP_URL_HOST);
			if ($path !== NULL && strpos($path, '/media/') === 0 && ($host === NULL OR $host === $own_host))
			{
				return $m[0];
			}
			return '';
		}, $clean);
		return $clean;
	}

	public function slugify($text, $fallback = 'item')
	{
		$slug = strtolower(trim((string) $text));
		$slug = preg_replace('/[^a-z0-9]+/u', '-', $slug);
		$slug = trim(preg_replace('/-+/', '-', $slug), '-');
		return ($slug === '') ? $fallback.'-'.substr(md5(uniqid('', TRUE)), 0, 6) : mb_substr($slug, 0, 200);
	}

	public function unique_slug($table, $column, $slug, $exclude_id = NULL, array $extra_where = array())
	{
		$base = $slug;
		$i = 2;
		while (TRUE)
		{
			$this->CI->db->where($column, $slug);
			foreach ($extra_where as $key => $value)
			{
				$this->CI->db->where($key, $value);
			}
			if ($exclude_id !== NULL)
			{
				$this->CI->db->where('id !=', (int) $exclude_id);
			}
			if ($this->CI->db->count_all_results($table) === 0)
			{
				return $slug;
			}
			$slug = $base.'-'.$i;
			$i++;
		}
	}

	// ------------------------------------------------------------ CRUD

	public function listing($type, array $filters = array(), $limit = 50, $offset = 0)
	{
		$schema = $this->schema($type);
		$this->CI->db->from($schema['table']);
		if ($schema['soft_delete'])
		{
			$this->CI->db->where('deleted_at IS NULL', NULL, FALSE);
		}
		if ( ! empty($filters['status']) && $schema['publishable'])
		{
			$this->CI->db->where('publication_status', $filters['status']);
		}
		if ( ! empty($filters['keyword']))
		{
			$column = isset($schema['fields']['title']) ? 'title' : (isset($schema['fields']['name']) ? 'name' : 'label');
			$this->CI->db->like($column, mb_substr($filters['keyword'], 0, 80));
		}
		return $this->CI->db->order_by($schema['order'])->limit((int) $limit, (int) $offset)->get()->result();
	}

	public function count($type, array $filters = array())
	{
		$schema = $this->schema($type);
		$this->CI->db->from($schema['table']);
		if ($schema['soft_delete'])
		{
			$this->CI->db->where('deleted_at IS NULL', NULL, FALSE);
		}
		if ( ! empty($filters['status']) && $schema['publishable'])
		{
			$this->CI->db->where('publication_status', $filters['status']);
		}
		return (int) $this->CI->db->count_all_results();
	}

	public function find($type, $id)
	{
		$schema = $this->schema($type);
		return $this->CI->db->get_where($schema['table'], array('id' => (int) $id))->row();
	}

	/**
	 * Simpan konten (buat/ubah). Nilai dibersihkan sesuai tipe field.
	 * @return int id konten
	 */
	public function save($type, $id, array $input, $user_id)
	{
		$schema = $this->schema($type);
		$existing = $id ? $this->find($type, $id) : NULL;
		if ($id && ! $existing)
		{
			throw new DomainRuleException('Konten tidak ditemukan.', 404);
		}

		$data = array();
		$errors = array();
		foreach ($schema['fields'] as $name => $field)
		{
			if ($field['type'] === 'media_multi')
			{
				continue; // ditangani terpisah
			}
			$value = $input[$name] ?? NULL;
			try
			{
				$data[$name] = $this->clean_field($field, $value, $name, $schema, $existing);
			}
			catch (DomainRuleException $e)
			{
				$errors[$name] = $e->getMessage();
			}
		}
		if ( ! empty($errors))
		{
			throw new DomainRuleException('Periksa kembali isian konten.', 422, $errors);
		}

		// Slug otomatis + unik + pengalihan 301 bila berubah.
		$old_slug = NULL;
		if ( ! empty($schema['slug_field']))
		{
			$field = $schema['slug_field'];
			$source = $schema['slug_source'] ?? 'title';
			$slug = trim((string) ($data[$field] ?? ''));
			if ($slug === '')
			{
				$slug = $this->slugify($data[$source] ?? '', $type);
			}
			else
			{
				$slug = $this->slugify($slug, $type);
			}
			$extra = ($schema['table'] === 'posts') ? array('type' => $data['type']) : array();
			$data[$field] = $this->unique_slug($schema['table'], $field, $slug, $id, $extra);
			if ($existing && $existing->{$field} !== $data[$field])
			{
				$old_slug = $existing->{$field};
			}
		}

		$now = utc_now();
		$data['updated_at'] = $now;
		if (in_array('author_id', array_keys($this->columns($schema['table'])), TRUE) && ! $existing)
		{
			$data['author_id'] = (int) $user_id;
		}
		if ($schema['table'] === 'village_profiles')
		{
			$data['updated_by'] = (int) $user_id;
		}

		$saved_id = db_transaction(function () use ($schema, $id, $existing, $data, $now, $old_slug, $type, $user_id) {
			if ($existing)
			{
				db_must($this->CI->db->where('id', (int) $id)->update($schema['table'], $data), 'content.update');
				$content_id = (int) $id;
			}
			else
			{
				$data['created_at'] = $now;
				if ($schema['publishable'])
				{
					$data['publication_status'] = 'draft';
				}
				db_must($this->CI->db->insert($schema['table'], $data), 'content.insert');
				$content_id = (int) $this->CI->db->insert_id();
			}
			if ($old_slug !== NULL)
			{
				db_must($this->CI->db->query(
					'INSERT IGNORE INTO slug_redirects (content_type, old_slug, new_slug, created_at) VALUES (?, ?, ?, ?)',
					array($this->redirect_type($type), $old_slug, $data[$schema['slug_field']], $now)
				), 'slug_redirects.insert');
			}
			if ( ! empty($schema['revisions']))
			{
				$this->store_revision($schema['table'], $content_id, $user_id);
			}
			return $content_id;
		});

		$this->CI->audit->log('content.saved', 'content', $type.':'.$saved_id, array('created' => ! $existing));
		$this->sync_media_usages();
		$this->invalidate_cache();
		return $saved_id;
	}

	protected function redirect_type($type)
	{
		return ($type === 'berita') ? 'post' : (($type === 'potensi') ? 'potential' : $type);
	}

	protected function columns($table)
	{
		static $cache = array();
		if ( ! isset($cache[$table]))
		{
			$cache[$table] = $this->CI->db->list_fields($table);
		}
		return $cache[$table];
	}

	protected function store_revision($table, $id, $user_id)
	{
		if ($table !== 'posts')
		{
			return;
		}
		$row = $this->CI->db->get_where('posts', array('id' => (int) $id))->row_array();
		if ( ! $row)
		{
			return;
		}
		$version = (int) $this->CI->db->where('post_id', (int) $id)->count_all_results('post_revisions') + 1;
		db_must($this->CI->db->insert('post_revisions', array(
			'post_id' => (int) $id,
			'version_no' => $version,
			'snapshot_json' => json_encode($row, JSON_UNESCAPED_UNICODE),
			'edited_by' => (int) $user_id,
			'created_at' => utc_now(),
		)), 'post_revisions.insert');
		$this->CI->db->where('id', (int) $id)->update('posts', array('version_no' => $version));
	}

	protected function clean_field(array $field, $value, $name, array $schema, $existing)
	{
		$type = $field['type'];
		$required = ! empty($field['required']);
		$string = is_string($value) ? trim($value) : $value;

		switch ($type)
		{
			case 'text':
			case 'slug':
				$string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $string);
				if ($required && $string === '')
				{
					throw new DomainRuleException($field['label'].' wajib diisi.');
				}
				if (isset($field['min']) && $string !== '' && mb_strlen($string) < $field['min'])
				{
					throw new DomainRuleException($field['label'].' minimal '.$field['min'].' karakter.');
				}
				if (isset($field['max']) && mb_strlen($string) > $field['max'])
				{
					throw new DomainRuleException($field['label'].' maksimal '.$field['max'].' karakter.');
				}
				return ($string === '') ? NULL : $string;

			case 'textarea':
				$string = str_replace(array("\r\n", "\r"), "\n", (string) $string);
				if ($required && trim($string) === '')
				{
					throw new DomainRuleException($field['label'].' wajib diisi.');
				}
				if (isset($field['max']) && mb_strlen($string) > $field['max'])
				{
					throw new DomainRuleException($field['label'].' maksimal '.$field['max'].' karakter.');
				}
				return (trim($string) === '') ? NULL : $string;

			case 'richtext':
				$clean = $this->sanitize_html((string) $value);
				if ($required && trim(strip_tags($clean)) === '')
				{
					throw new DomainRuleException($field['label'].' wajib diisi.');
				}
				return $clean;

			case 'select':
				$options = isset($field['options']) ? $field['options'] : app_config($field['options_config'], array());
				if ( ! isset($options[(string) $string]))
				{
					if ($required)
					{
						throw new DomainRuleException('Pilihan '.$field['label'].' tidak valid.');
					}
					return NULL;
				}
				return (string) $string;

			case 'category':
				$id = (int) $string;
				if ($id === 0)
				{
					if ($required)
					{
						throw new DomainRuleException($field['label'].' wajib dipilih.');
					}
					return NULL;
				}
				if ($this->CI->db->where(array('id' => $id, 'content_type' => $field['content_type']))->count_all_results('content_categories') === 0)
				{
					throw new DomainRuleException('Kategori tidak valid.');
				}
				return $id;

			case 'position':
				$id = (int) $string;
				if ($this->CI->db->where('id', $id)->count_all_results('official_positions') === 0)
				{
					throw new DomainRuleException('Jabatan tidak valid.');
				}
				return $id;

			case 'menu_parent':
				$id = (int) $string;
				if ($id === 0)
				{
					return NULL;
				}
				if ($existing && (int) $existing->id === $id)
				{
					throw new DomainRuleException('Menu tidak boleh menjadi induk dirinya sendiri.');
				}
				$parent = $this->CI->db->get_where('navigation_items', array('id' => $id))->row();
				if ( ! $parent OR $parent->parent_id !== NULL)
				{
					throw new DomainRuleException('Induk menu tidak valid (maksimal dua tingkat).');
				}
				return $id;

			case 'media':
				$id = (int) $string;
				if ($id === 0)
				{
					if ($required)
					{
						throw new DomainRuleException($field['label'].' wajib dipilih.');
					}
					return NULL;
				}
				$media = $this->CI->db->get_where('media_assets', array('id' => $id))->row();
				if ( ! $media OR $media->deleted_at !== NULL)
				{
					throw new DomainRuleException('Media tidak ditemukan.');
				}
				if ( ! in_array($media->rights_status, array('owned', 'licensed', 'permission_granted'), TRUE))
				{
					throw new DomainRuleException('Media "'.$media->original_name.'" belum memiliki status hak publikasi yang jelas.');
				}
				return $id;

			case 'checkbox':
				return $value ? 1 : 0;

			case 'number':
				if ($string === '' OR $string === NULL)
				{
					return NULL;
				}
				if ( ! is_numeric($string))
				{
					throw new DomainRuleException($field['label'].' harus berupa angka.');
				}
				$number = (int) $string;
				if (isset($field['min']) && $number < $field['min'])
				{
					throw new DomainRuleException($field['label'].' minimal '.$field['min'].'.');
				}
				if (isset($field['max']) && $number > $field['max'])
				{
					throw new DomainRuleException($field['label'].' maksimal '.$field['max'].'.');
				}
				return $number;

			case 'date':
				if ($string === '' OR $string === NULL)
				{
					if ($required)
					{
						throw new DomainRuleException($field['label'].' wajib diisi.');
					}
					return NULL;
				}
				if ( ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $string))
				{
					throw new DomainRuleException($field['label'].' tidak valid.');
				}
				return $string;

			case 'datetime':
				if ($string === '' OR $string === NULL)
				{
					if ($required)
					{
						throw new DomainRuleException($field['label'].' wajib diisi.');
					}
					return NULL;
				}
				$utc = local_to_utc($string);
				if ($utc === NULL)
				{
					throw new DomainRuleException($field['label'].' tidak valid.');
				}
				return $utc;

			case 'url_internal':
				if ( ! app_is_safe_url((string) $string))
				{
					throw new DomainRuleException('Tautan tidak valid. Gunakan path internal (/berita) atau URL http(s).');
				}
				return (string) $string;

			case 'cta':
				$label = trim((string) ($value['label'] ?? ''));
				$url = trim((string) ($value['url'] ?? ''));
				if ($label === '' OR $url === '')
				{
					return NULL;
				}
				if ( ! app_is_safe_url($url))
				{
					throw new DomainRuleException('Tautan tombol tidak valid.');
				}
				return json_encode(array('label' => mb_substr($label, 0, 60), 'url' => $url), JSON_UNESCAPED_UNICODE);
		}
		return NULL;
	}

	// ------------------------------------------------------------ Publikasi

	/**
	 * Ubah status publikasi. Publish/arsip memerlukan permission content.publish
	 * (diperiksa controller). Mengarsipkan membatalkan cache dan sitemap terkait.
	 */
	public function set_status($type, $id, $status, $user_id)
	{
		$schema = $this->schema($type);
		if ( ! $schema['publishable'])
		{
			throw new DomainRuleException('Jenis konten ini tidak memiliki alur publikasi.', 409);
		}
		$allowed = array('draft', 'in_review', 'published', 'archived');
		if ( ! in_array($status, $allowed, TRUE))
		{
			throw new DomainRuleException('Status publikasi tidak valid.', 422);
		}
		$row = $this->find($type, $id);
		if ( ! $row)
		{
			throw new DomainRuleException('Konten tidak ditemukan.', 404);
		}
		$data = array('publication_status' => $status, 'updated_at' => utc_now());
		$columns = $this->columns($schema['table']);
		if ($status === 'published' && in_array('published_at', $columns, TRUE))
		{
			$scheduled = in_array('publish_at', $columns, TRUE) ? $row->publish_at : NULL;
			$data['published_at'] = $scheduled ?: utc_now();
		}
		if (in_array('reviewer_id', $columns, TRUE) && $status === 'in_review')
		{
			$data['reviewer_id'] = (int) $user_id;
		}
		if (in_array('approved_by', $columns, TRUE) && $status === 'published')
		{
			$data['approved_by'] = (int) $user_id;
		}
		db_must($this->CI->db->where('id', (int) $id)->update($schema['table'], $data), 'content.status');
		$this->CI->audit->log('content.status_'.$status, 'content', $type.':'.$id, array());
		$this->invalidate_cache();
		return TRUE;
	}

	/** Arsip/soft delete: konten tidak dihapus permanen dari aplikasi. */
	public function archive($type, $id, $user_id)
	{
		$schema = $this->schema($type);
		if ($schema['soft_delete'])
		{
			db_must($this->CI->db->where('id', (int) $id)->update($schema['table'], array('deleted_at' => utc_now(), 'updated_at' => utc_now())), 'content.archive');
		}
		elseif ($schema['publishable'])
		{
			$this->set_status($type, $id, 'archived', $user_id);
		}
		else
		{
			db_must($this->CI->db->where('id', (int) $id)->update($schema['table'], array('active' => 0, 'updated_at' => utc_now())), 'content.deactivate');
		}
		$this->CI->audit->log('content.archived', 'content', $type.':'.$id, array());
		$this->invalidate_cache();
	}

	public function invalidate_cache()
	{
		$dir = ROOTPATH.'storage/cache/public';
		if ( ! is_dir($dir))
		{
			return;
		}
		foreach (glob($dir.'/*.cache') ?: array() as $file)
		{
			@unlink($file);
		}
	}

	// ------------------------------------------------------------ Galeri

	public function set_gallery_items($gallery_id, array $media_ids)
	{
		db_transaction(function () use ($gallery_id, $media_ids) {
			$this->CI->db->delete('gallery_items', array('gallery_id' => (int) $gallery_id));
			$order = 0;
			foreach ($media_ids as $media_id)
			{
				$media_id = (int) $media_id;
				if ($media_id <= 0 OR $this->CI->db->where('id', $media_id)->count_all_results('media_assets') === 0)
				{
					continue;
				}
				db_must($this->CI->db->query(
					'INSERT IGNORE INTO gallery_items (gallery_id, media_asset_id, sort_order) VALUES (?, ?, ?)',
					array((int) $gallery_id, $media_id, $order)
				), 'gallery_items.insert');
				$order += 10;
			}
		});
	}

	public function gallery_item_ids($gallery_id)
	{
		$ids = array();
		foreach ($this->CI->db->where('gallery_id', (int) $gallery_id)->order_by('sort_order')->get('gallery_items')->result() as $row)
		{
			$ids[] = (int) $row->media_asset_id;
		}
		return $ids;
	}

	/** Media yang masih dipakai konten lain (mencegah penghapusan yang merusak halaman). */
	/** Tabel dan kolom yang dapat merujuk media publik. */
	protected function media_usage_map()
	{
		return array(
			'posts' => array('cover_media_id'),
			'potentials' => array('cover_media_id'),
			'events' => array('poster_media_id'),
			'public_documents' => array('media_asset_id'),
			'officials' => array('photo_media_id'),
			'hero_slides' => array('image_media_id', 'video_media_id'),
			'village_profiles' => array('logo_media_id', 'profile_media_id'),
		);
	}

	/**
	 * Isi ulang tabel `media_usages` dari rujukan yang benar-benar ada di tabel konten.
	 * Dipakai agar daftar pemakaian dapat diquery, bukan hanya dihitung saat halaman dibuka.
	 *
	 * @return int jumlah baris pemakaian setelah sinkronisasi
	 */
	public function sync_media_usages($media_id = NULL)
	{
		$now = utc_now();
		$rows = array();
		foreach ($this->media_usage_map() as $table => $columns)
		{
			foreach ($columns as $column)
			{
				$builder = $this->CI->db->select($column.' AS media_id, id AS object_id')->from($table)
					->where($column.' IS NOT NULL', NULL, FALSE);
				if ($media_id !== NULL)
				{
					$builder->where($column, (int) $media_id);
				}
				foreach ($builder->get()->result() as $row)
				{
					$rows[] = array((int) $row->media_id, $table, (string) $row->object_id, $column);
				}
			}
		}
		$gallery = $this->CI->db->select('media_asset_id AS media_id, gallery_id AS object_id')->from('gallery_items')
			->where('media_asset_id IS NOT NULL', NULL, FALSE);
		if ($media_id !== NULL)
		{
			$gallery->where('media_asset_id', (int) $media_id);
		}
		foreach ($gallery->get()->result() as $row)
		{
			$rows[] = array((int) $row->media_id, 'gallery_items', (string) $row->object_id, 'media_asset_id');
		}

		if ($media_id === NULL)
		{
			$this->CI->db->where('1 = 1', NULL, FALSE)->delete('media_usages');
		}
		else
		{
			$this->CI->db->where('media_asset_id', (int) $media_id)->delete('media_usages');
		}
		foreach ($rows as $row)
		{
			$this->CI->db->query(
				'INSERT IGNORE INTO media_usages (media_asset_id, object_type, object_id, field_name, created_at) VALUES (?, ?, ?, ?, ?)',
				array($row[0], $row[1], $row[2], $row[3], $now)
			);
		}
		return count($rows);
	}

	public function media_usage($media_id)
	{
		$media_id = (int) $media_id;
		$usage = array();
		$checks = array(
			'posts' => array('cover_media_id'),
			'potentials' => array('cover_media_id'),
			'events' => array('poster_media_id'),
			'public_documents' => array('media_asset_id'),
			'officials' => array('photo_media_id'),
			'hero_slides' => array('image_media_id', 'video_media_id'),
			'village_profiles' => array('logo_media_id', 'profile_media_id'),
		);
		foreach ($checks as $table => $columns)
		{
			foreach ($columns as $column)
			{
				$count = $this->CI->db->where($column, $media_id)->count_all_results($table);
				if ($count > 0)
				{
					$usage[] = $table.' ('.$count.')';
				}
			}
		}
		$count = $this->CI->db->where('media_asset_id', $media_id)->count_all_results('gallery_items');
		if ($count > 0)
		{
			$usage[] = 'gallery_items ('.$count.')';
		}
		return $usage;
	}
}
