<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Identitas situs dan token tema (modul-backend §9.1 dan §9.2).
 *
 * Pengelola menyunting draft; situs publik membaca snapshot yang diterbitkan
 * (`cms_publication_snapshots.target_type = 'site'`). Dengan begitu perubahan nama,
 * kontak, logo, atau warna tidak pernah langsung tampil sebelum ditinjau dan diterbitkan.
 *
 * Yang boleh diatur hanya token yang disediakan: warna (hex, wajib lolos kontras
 * minimum), font dari daftar yang sudah terpasang, preset radius, mode hero, dan
 * preferensi reduced motion. CSS bebas tetap hanya dapat diubah lewat source code.
 */
class SiteSettingsService {

	/** @var CI_Controller */
	protected $CI;

	const DRAFT_KEY = 'site.draft';

	/** Kontras minimum teks terhadap latar (WCAG AA teks normal). */
	const MIN_CONTRAST = 4.5;

	const FONTS = array(
		'manrope' => 'Manrope (sans-serif)',
		'playfair' => 'Playfair Display (serif)',
		'system' => 'Font sistem',
	);

	const RADIUS = array(
		'tegas' => array('label' => 'Tegas', 'input' => 6, 'card' => 10),
		'sedang' => array('label' => 'Sedang', 'input' => 12, 'card' => 20),
		'lembut' => array('label' => 'Lembut', 'input' => 18, 'card' => 28),
	);

	const HERO_MODES = array('image' => 'Foto', 'video' => 'Video', 'three' => 'Animasi Three.js');

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->library('PublicCache', NULL, 'public_cache');
	}

	/** Nilai bawaan = tampilan yang sekarang dipakai situs. */
	public function defaults()
	{
		return array(
			'identity' => array(
				'site_name' => 'Desa Cihawuk',
				'village_name' => 'Cihawuk',
				'district' => 'Kertasari',
				'regency' => 'Kabupaten Bandung',
				'province' => 'Jawa Barat',
				'office_address' => '',
				'contact_phone' => '',
				'contact_email' => '',
				'service_hours' => '',
				'footer_text' => 'Situs ini tidak terhubung dengan SP4N-LAPOR!.',
				'privacy_contact' => '',
				'social' => array('facebook' => '', 'instagram' => '', 'youtube' => '', 'whatsapp' => ''),
				'logo_media_id' => NULL,
				'favicon_media_id' => NULL,
				'social_image_media_id' => NULL,
			),
			'theme' => array(
				'color_primary' => '#174B3A',
				'color_secondary' => '#10392D',
				'color_accent' => '#D7AF67',
				'color_surface' => '#F7F9F6',
				'font_body' => 'manrope',
				'font_display' => 'playfair',
				'radius' => 'sedang',
				'hero_mode' => 'three',
				'reduced_motion_default' => FALSE,
				'placeholder_media_id' => NULL,
			),
		);
	}

	// ------------------------------------------------------------------
	// Draft
	// ------------------------------------------------------------------

	public function draft()
	{
		$stored = $this->CI->settings->get(self::DRAFT_KEY);
		return $this->merge_defaults(is_array($stored) ? $stored : array());
	}

	protected function merge_defaults(array $stored)
	{
		$defaults = $this->defaults();
		$out = array();
		foreach ($defaults as $group => $fields)
		{
			$out[$group] = array();
			foreach ($fields as $key => $value)
			{
				if ($key === 'social')
				{
					$social = isset($stored[$group][$key]) && is_array($stored[$group][$key]) ? $stored[$group][$key] : array();
					$out[$group][$key] = array_merge($value, array_intersect_key($social, $value));
					continue;
				}
				$out[$group][$key] = array_key_exists($key, (array) ($stored[$group] ?? array()))
					? $stored[$group][$key] : $value;
			}
		}
		return $out;
	}

	public function save_draft(array $input, $user_id)
	{
		$clean = $this->validate($input);
		$this->CI->settings->set(self::DRAFT_KEY, $clean, 'site', FALSE, $user_id ? (int) $user_id : NULL);
		$this->CI->audit->log('cms.site_draft_saved', 'setting', 'site', array(
			'site_name' => $clean['identity']['site_name'],
		), FALSE, 'cms');
		return $clean;
	}

	/**
	 * Validasi server. Nilai yang tidak dikenal dibuang; warna wajib hex enam digit dan
	 * lolos kontras minimum sehingga pengelola tidak dapat membuat situs tidak terbaca.
	 */
	public function validate(array $input)
	{
		$defaults = $this->defaults();
		$errors = array();
		$identity = is_array($input['identity'] ?? NULL) ? $input['identity'] : array();
		$theme = is_array($input['theme'] ?? NULL) ? $input['theme'] : array();

		$out = array('identity' => array(), 'theme' => array());

		$limits = array(
			'site_name' => 120, 'village_name' => 80, 'district' => 80, 'regency' => 80, 'province' => 80,
			'office_address' => 250, 'contact_phone' => 40, 'contact_email' => 120, 'service_hours' => 160,
			'footer_text' => 300, 'privacy_contact' => 160,
		);
		foreach ($limits as $field => $max)
		{
			$value = trim((string) ($identity[$field] ?? $defaults['identity'][$field]));
			$value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
			$out['identity'][$field] = mb_substr($value, 0, $max);
		}
		if ($out['identity']['site_name'] === '')
		{
			$errors['site_name'] = 'Nama situs wajib diisi.';
		}
		if ($out['identity']['contact_email'] !== '' && ! filter_var($out['identity']['contact_email'], FILTER_VALIDATE_EMAIL))
		{
			$errors['contact_email'] = 'Alamat email tidak valid.';
		}
		if ($out['identity']['contact_phone'] !== '' && ! preg_match('/^[0-9 ()+\-]{6,40}$/', $out['identity']['contact_phone']))
		{
			$errors['contact_phone'] = 'Nomor telepon hanya boleh angka, spasi, dan tanda + ( ) -.';
		}

		$out['identity']['social'] = array();
		foreach ($defaults['identity']['social'] as $network => $ignored)
		{
			$url = trim((string) ($identity['social'][$network] ?? ''));
			if ($url === '')
			{
				$out['identity']['social'][$network] = '';
				continue;
			}
			$scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
			if ( ! in_array($scheme, array('http', 'https'), TRUE) OR ! app_is_safe_url($url))
			{
				$errors['social_'.$network] = 'Tautan '.$network.' hanya boleh http atau https.';
				$out['identity']['social'][$network] = '';
				continue;
			}
			$out['identity']['social'][$network] = mb_substr($url, 0, 300);
		}

		foreach (array('logo_media_id', 'favicon_media_id', 'social_image_media_id') as $field)
		{
			$out['identity'][$field] = $this->media_id($identity[$field] ?? NULL, $field, $errors);
		}

		foreach (array('color_primary', 'color_secondary', 'color_accent', 'color_surface') as $field)
		{
			$value = strtoupper(trim((string) ($theme[$field] ?? $defaults['theme'][$field])));
			if ( ! preg_match('/^#[0-9A-F]{6}$/', $value))
			{
				$errors[$field] = 'Warna harus format heksadesimal enam digit, misalnya #174B3A.';
				$value = $defaults['theme'][$field];
			}
			$out['theme'][$field] = $value;
		}

		// Teks putih di atas warna primary/secondary, dan teks gelap di atas surface.
		foreach (array('color_primary', 'color_secondary') as $field)
		{
			if ( ! isset($errors[$field]) && $this->contrast($out['theme'][$field], '#FFFFFF') < self::MIN_CONTRAST)
			{
				$errors[$field] = 'Kontras terhadap teks putih terlalu rendah (minimal '.self::MIN_CONTRAST.':1). Pilih warna lebih gelap.';
			}
		}
		if ( ! isset($errors['color_surface']) && $this->contrast($out['theme']['color_surface'], '#182B24') < self::MIN_CONTRAST)
		{
			$errors['color_surface'] = 'Warna latar terlalu gelap untuk teks isi. Pilih warna lebih terang.';
		}
		if ( ! isset($errors['color_accent']) && $this->contrast($out['theme']['color_accent'], '#182B24') < 3.0)
		{
			$errors['color_accent'] = 'Warna aksen terlalu gelap; teks di atasnya tidak terbaca.';
		}

		foreach (array('font_body', 'font_display') as $field)
		{
			$value = (string) ($theme[$field] ?? $defaults['theme'][$field]);
			if ( ! isset(self::FONTS[$value]))
			{
				$errors[$field] = 'Font itu belum terpasang pada aplikasi.';
				$value = $defaults['theme'][$field];
			}
			$out['theme'][$field] = $value;
		}

		$radius = (string) ($theme['radius'] ?? $defaults['theme']['radius']);
		if ( ! isset(self::RADIUS[$radius]))
		{
			$errors['radius'] = 'Preset radius tidak dikenal.';
			$radius = $defaults['theme']['radius'];
		}
		$out['theme']['radius'] = $radius;

		$hero = (string) ($theme['hero_mode'] ?? $defaults['theme']['hero_mode']);
		if ( ! isset(self::HERO_MODES[$hero]))
		{
			$errors['hero_mode'] = 'Mode hero tidak dikenal.';
			$hero = $defaults['theme']['hero_mode'];
		}
		$out['theme']['hero_mode'] = $hero;
		$out['theme']['reduced_motion_default'] = ! empty($theme['reduced_motion_default']);
		$out['theme']['placeholder_media_id'] = $this->media_id($theme['placeholder_media_id'] ?? NULL, 'placeholder_media_id', $errors);

		if ($errors)
		{
			throw new DomainRuleException('Periksa kembali isian identitas dan tema.', 422, $errors);
		}
		return $out;
	}

	protected function media_id($raw, $field, array &$errors)
	{
		if (empty($raw))
		{
			return NULL;
		}
		$this->CI->load->model('Content_model', 'content');
		$media = $this->CI->content->media((int) $raw);
		if ( ! $media)
		{
			$errors[$field] = 'Media tidak ditemukan atau belum disetujui untuk publik.';
			return NULL;
		}
		return (int) $raw;
	}

	/** Rasio kontras WCAG antara dua warna hex. */
	public function contrast($hex_a, $hex_b)
	{
		$l1 = $this->luminance($hex_a);
		$l2 = $this->luminance($hex_b);
		if ($l1 === NULL OR $l2 === NULL)
		{
			return 0.0;
		}
		$light = max($l1, $l2);
		$dark = min($l1, $l2);
		return round(($light + 0.05) / ($dark + 0.05), 2);
	}

	protected function luminance($hex)
	{
		if ( ! preg_match('/^#([0-9A-Fa-f]{6})$/', (string) $hex, $m))
		{
			return NULL;
		}
		$channels = array();
		foreach (str_split($m[1], 2) as $pair)
		{
			$value = hexdec($pair) / 255;
			$channels[] = ($value <= 0.03928) ? $value / 12.92 : pow(($value + 0.055) / 1.055, 2.4);
		}
		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}

	// ------------------------------------------------------------------
	// Publikasi
	// ------------------------------------------------------------------

	public function publish($reason, $user_id)
	{
		$draft = $this->draft();
		// Validasi ulang saat terbit: media bisa saja diarsipkan setelah draft disimpan.
		$clean = $this->validate($draft);
		$now = utc_now();
		$revision = NULL;

		db_transaction(function () use ($clean, $reason, $user_id, $now, &$revision) {
			$revision = (int) ($this->CI->db->select_max('revision_no')
				->where(array('target_type' => 'site', 'target_id' => 0))
				->get('cms_publication_snapshots')->row('revision_no') ?: 0) + 1;
			db_must($this->CI->db->where(array('target_type' => 'site', 'target_id' => 0))
				->where('superseded_at IS NULL', NULL, FALSE)
				->update('cms_publication_snapshots', array('superseded_at' => $now)), 'cms_snapshots.site_supersede');
			db_must($this->CI->db->insert('cms_publication_snapshots', array(
				'target_type' => 'site',
				'target_id' => 0,
				'revision_no' => $revision,
				'snapshot_json' => json_encode($clean, JSON_UNESCAPED_UNICODE),
				'reason' => ($reason === '' OR $reason === NULL) ? NULL : mb_substr((string) $reason, 0, 500),
				'published_by' => $user_id ? (int) $user_id : NULL,
				'published_at' => $now,
			)), 'cms_snapshots.site_insert');
		});

		$this->CI->audit->log('cms.site_published', 'setting', 'site', array('revision' => $revision), FALSE, 'cms');
		$this->CI->audit->event('info', 'Identitas dan tema situs diterbitkan (revisi '.$revision.').', 'cms', array());
		$this->CI->public_cache->invalidate_site();
		return $revision;
	}

	public function rollback($snapshot_id, $reason, $user_id)
	{
		$reason = trim((string) $reason);
		if (mb_strlen($reason) < 10)
		{
			throw new DomainRuleException('Tuliskan alasan rollback (minimal 10 karakter).', 422, array('reason' => 'Alasan wajib diisi.'));
		}
		$source = $this->CI->db->get_where('cms_publication_snapshots', array(
			'id' => (int) $snapshot_id, 'target_type' => 'site', 'target_id' => 0,
		))->row();
		if ( ! $source)
		{
			throw new DomainRuleException('Revisi tidak ditemukan.', 404);
		}
		$now = utc_now();
		$revision = NULL;
		db_transaction(function () use ($source, $reason, $user_id, $now, &$revision) {
			$revision = (int) ($this->CI->db->select_max('revision_no')
				->where(array('target_type' => 'site', 'target_id' => 0))
				->get('cms_publication_snapshots')->row('revision_no') ?: 0) + 1;
			db_must($this->CI->db->where(array('target_type' => 'site', 'target_id' => 0))
				->where('superseded_at IS NULL', NULL, FALSE)
				->update('cms_publication_snapshots', array('superseded_at' => $now)), 'cms_snapshots.site_supersede');
			db_must($this->CI->db->insert('cms_publication_snapshots', array(
				'target_type' => 'site',
				'target_id' => 0,
				'revision_no' => $revision,
				'snapshot_json' => $source->snapshot_json,
				'reason' => mb_substr($reason, 0, 500),
				'rolled_back_from' => (int) $source->id,
				'published_by' => $user_id ? (int) $user_id : NULL,
				'published_at' => $now,
			)), 'cms_snapshots.site_rollback');
		});
		// Draft ikut dikembalikan agar dashboard tidak menampilkan isi yang berbeda dari publik.
		$restored = json_decode((string) $source->snapshot_json, TRUE);
		if (is_array($restored))
		{
			$this->CI->settings->set(self::DRAFT_KEY, $this->merge_defaults($restored), 'site', FALSE, $user_id ? (int) $user_id : NULL);
		}
		$this->CI->audit->log('cms.site_rolled_back', 'setting', 'site', array(
			'revision' => $revision, 'from_revision' => (int) $source->revision_no,
		), FALSE, 'cms');
		$this->CI->public_cache->invalidate_site();
		return $revision;
	}

	public function snapshots($limit = 10)
	{
		return $this->CI->db->select('s.*, u.display_name AS publisher')
			->from('cms_publication_snapshots s')->join('users u', 'u.id = s.published_by', 'left')
			->where(array('s.target_type' => 'site', 's.target_id' => 0))
			->order_by('s.revision_no', 'DESC')->limit((int) $limit)->get()->result();
	}

	/** Nilai yang dipakai situs publik; jatuh ke bawaan bila belum pernah diterbitkan. */
	public function published()
	{
		$cached = $this->CI->public_cache->get('site', 'current');
		if (is_array($cached))
		{
			return $cached;
		}
		$row = $this->CI->db->where(array('target_type' => 'site', 'target_id' => 0))
			->where('superseded_at IS NULL', NULL, FALSE)
			->order_by('revision_no', 'DESC')->limit(1)->get('cms_publication_snapshots')->row();
		$values = $row ? json_decode((string) $row->snapshot_json, TRUE) : NULL;
		$values = $this->merge_defaults(is_array($values) ? $values : array());
		$values['identity'] = array_merge($values['identity'], $this->media_urls($values['identity']));
		$values['revision_no'] = $row ? (int) $row->revision_no : 0;
		$this->CI->public_cache->set('site', 'current', $values, 1800);
		return $values;
	}

	/** URL publik logo/favicon/social image; kosong bila medianya sudah tidak tersedia. */
	protected function media_urls(array $identity)
	{
		$this->CI->load->model('Content_model', 'content');
		$out = array();
		foreach (array('logo' => 'logo_media_id', 'favicon' => 'favicon_media_id', 'social_image' => 'social_image_media_id') as $name => $field)
		{
			$media = empty($identity[$field]) ? NULL : $this->CI->content->media((int) $identity[$field]);
			$out[$name.'_url'] = $media ? media_url($media) : NULL;
		}
		return $out;
	}

	/** Apakah draft berbeda dari yang sedang tampil publik. */
	public function has_unpublished_changes()
	{
		$published = $this->published();
		unset($published['revision_no'], $published['identity']['logo_url'],
			$published['identity']['favicon_url'], $published['identity']['social_image_url']);
		return json_encode($published) !== json_encode($this->draft());
	}

	/** Token tema sebagai CSS custom property untuk disuntikkan ke <head>. */
	public function css_variables(array $theme)
	{
		$radius = self::RADIUS[isset(self::RADIUS[$theme['radius']]) ? $theme['radius'] : 'sedang'];
		$fonts = array(
			'manrope' => '"Manrope", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
			'playfair' => '"Playfair Display", Georgia, "Times New Roman", serif',
			'system' => 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
		);
		$primary = $theme['color_primary'];
		return array(
			'--color-primary' => $primary,
			'--color-primary-dark' => $theme['color_secondary'],
			'--color-accent' => $theme['color_accent'],
			'--color-surface' => $theme['color_surface'],
			'--font-body' => $fonts[$theme['font_body']],
			'--font-display' => $fonts[$theme['font_display']],
			'--radius-input' => $radius['input'].'px',
			'--radius-card' => $radius['card'].'px',
			'--bs-primary' => $primary,
			'--bs-link-color' => $primary,
			'--bs-link-hover-color' => $theme['color_secondary'],
			'--bs-body-bg' => $theme['color_surface'],
		);
	}
}
