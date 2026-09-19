<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Helper tampilan: escape, format tanggal/angka, CSRF dan URL aset.
 * Bukan tempat logika bisnis.
 */

if ( ! function_exists('e'))
{
	/** Escape untuk konteks HTML body dan atribut ber-kutip. */
	function e($value)
	{
		return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
	}
}

if ( ! function_exists('e_url'))
{
	/** URL untuk atribut href/src: hanya http(s), path relatif, mailto dan tel. */
	function e_url($url)
	{
		$url = trim((string) $url);
		if ($url === '' OR ! app_is_safe_url($url))
		{
			return '#';
		}
		return e($url);
	}
}

if ( ! function_exists('app_is_safe_url'))
{
	function app_is_safe_url($url, $allow_external = TRUE)
	{
		$url = trim((string) $url);
		if ($url === '' OR preg_match('/[\x00-\x1F\x7F\\\\]/', $url))
		{
			return FALSE;
		}
		if ($url[0] === '/' && (strlen($url) === 1 OR $url[1] !== '/'))
		{
			return TRUE;
		}
		if (preg_match('#^(mailto|tel):#i', $url))
		{
			return $allow_external;
		}
		$scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
		return $allow_external && in_array($scheme, array('http', 'https'), TRUE) && filter_var($url, FILTER_VALIDATE_URL) !== FALSE;
	}
}

if ( ! function_exists('app_safe_redirect_path'))
{
	/** Path redirect internal saja (mencegah open redirect). */
	function app_safe_redirect_path($path, $default = '/')
	{
		$path = (string) $path;
		if ($path === '' OR $path[0] !== '/' OR (isset($path[1]) && ($path[1] === '/' OR $path[1] === '\\')) OR preg_match('/[\x00-\x1F\x7F\\\\]/', $path))
		{
			return $default;
		}
		$parts = parse_url($path);
		if ($parts === FALSE OR isset($parts['scheme']) OR isset($parts['host']))
		{
			return $default;
		}
		foreach (array('/warga', '/admin', '/lacak', '/lapor', '/akun') as $prefix)
		{
			if ($parts['path'] === $prefix OR strpos($parts['path'], $prefix.'/') === 0)
			{
				return $parts['path'];
			}
		}
		return $default;
	}
}

if ( ! function_exists('csrf_field'))
{
	function csrf_field()
	{
		$CI =& get_instance();
		return '<input type="hidden" name="'.e($CI->security->get_csrf_token_name()).'" value="'.e($CI->security->get_csrf_hash()).'">';
	}
}

if ( ! function_exists('asset_url'))
{
	/** URL aset lokal dengan cache-busting berdasar waktu ubah file. */
	function asset_url($path)
	{
		$path = ltrim((string) $path, '/');
		$file = FCPATH.'assets/'.$path;
		$v = is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : '0';
		return base_url('assets/'.$path).'?v='.$v;
	}
}

if ( ! function_exists('icon'))
{
	/** Ikon SVG dari sprite Feather Icons (dekoratif; label teks tetap wajib di sekitarnya). */
	function icon($name, $class = '')
	{
		$name = preg_replace('/[^a-z0-9\-]/', '', (string) $name);
		return '<svg class="icon '.e($class).'" aria-hidden="true" focusable="false"><use href="'.e(base_url('assets/vendor/feather-icons/feather-sprite.svg')).'#'.$name.'"></use></svg>';
	}
}

if ( ! function_exists('app_now'))
{
	/** Waktu UTC saat ini (dapat di-override untuk pengujian melalui Clock). */
	function app_now()
	{
		$CI =& get_instance();
		if (isset($CI->clock))
		{
			return $CI->clock->now();
		}
		return new DateTimeImmutable('now', new DateTimeZone('UTC'));
	}
}

if ( ! function_exists('utc_now'))
{
	/** String DATETIME UTC untuk kolom database. */
	function utc_now()
	{
		return app_now()->format('Y-m-d H:i:s');
	}
}

if ( ! function_exists('local_tz'))
{
	function local_tz()
	{
		static $tz = NULL;
		if ($tz === NULL)
		{
			$tz = new DateTimeZone((string) app_env('APP_TIMEZONE', 'Asia/Jakarta'));
		}
		return $tz;
	}
}

if ( ! function_exists('format_wib'))
{
	/**
	 * Format DATETIME UTC ke waktu lokal (WIB) berbahasa Indonesia.
	 * $style: datetime | date | time | short
	 */
	function format_wib($utc, $style = 'datetime')
	{
		if ($utc === NULL OR $utc === '')
		{
			return '—';
		}
		try
		{
			$dt = ($utc instanceof DateTimeInterface)
				? DateTimeImmutable::createFromInterface($utc)
				: new DateTimeImmutable((string) $utc, new DateTimeZone('UTC'));
		}
		catch (Exception $e)
		{
			return '—';
		}
		$dt = $dt->setTimezone(local_tz());
		$months = array(1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember');
		$date = $dt->format('j').' '.$months[(int) $dt->format('n')].' '.$dt->format('Y');
		switch ($style)
		{
			case 'date':
				return $date;
			case 'time':
				return $dt->format('H.i').' WIB';
			case 'short':
				return $dt->format('d/m/Y H.i');
			default:
				return $date.', '.$dt->format('H.i').' WIB';
		}
	}
}

if ( ! function_exists('format_date_id'))
{
	/** Format tanggal DATE (tanpa konversi zona) berbahasa Indonesia. */
	function format_date_id($date)
	{
		if (empty($date))
		{
			return '—';
		}
		$months = array(1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember');
		$ts = strtotime((string) $date.' 00:00:00 UTC');
		if ($ts === FALSE)
		{
			return '—';
		}
		return gmdate('j', $ts).' '.$months[(int) gmdate('n', $ts)].' '.gmdate('Y', $ts);
	}
}

if ( ! function_exists('local_to_utc'))
{
	/** Konversi input datetime-local (WIB) menjadi DATETIME UTC; NULL bila tidak valid. */
	function local_to_utc($local)
	{
		$local = trim((string) $local);
		if ($local === '')
		{
			return NULL;
		}
		$dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $local, local_tz());
		if ($dt === FALSE)
		{
			$dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $local, local_tz());
		}
		if ($dt === FALSE)
		{
			return NULL;
		}
		return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
	}
}

if ( ! function_exists('utc_to_local_input'))
{
	function utc_to_local_input($utc)
	{
		if (empty($utc))
		{
			return '';
		}
		$dt = new DateTimeImmutable((string) $utc, new DateTimeZone('UTC'));
		return $dt->setTimezone(local_tz())->format('Y-m-d\TH:i');
	}
}

if ( ! function_exists('format_number_id'))
{
	function format_number_id($number, $decimals = 0)
	{
		if ($number === NULL OR $number === '')
		{
			return '—';
		}
		return number_format((float) $number, (int) $decimals, ',', '.');
	}
}

if ( ! function_exists('format_bytes_id'))
{
	function format_bytes_id($bytes)
	{
		$bytes = (int) $bytes;
		if ($bytes >= 1048576)
		{
			return format_number_id($bytes / 1048576, 1).' MB';
		}
		if ($bytes >= 1024)
		{
			return format_number_id($bytes / 1024, 0).' KB';
		}
		return $bytes.' B';
	}
}

if ( ! function_exists('ticket_status_badge'))
{
	/** Badge status dengan ikon + label teks (warna bukan satu-satunya penanda). */
	function ticket_status_badge($status, $audience = 'reporter')
	{
		$statuses = app_config('ticket_statuses', array());
		if ( ! isset($statuses[$status]))
		{
			return '<span class="badge badge-secondary">'.e($status).'</span>';
		}
		$s = $statuses[$status];
		$label = ($audience === 'staff') ? $s['staff'] : $s['label'];
		return '<span class="status-badge status-'.e($s['tone']).'">'.icon($s['icon']).'<span>'.e($label).'</span></span>';
	}
}

if ( ! function_exists('config_label'))
{
	function config_label($config_key, $code, $fallback = '—')
	{
		$items = app_config($config_key);
		if (is_array($items) && isset($items[$code]))
		{
			return is_array($items[$code]) ? $items[$code]['label'] : $items[$code];
		}
		return ($code === NULL OR $code === '') ? $fallback : $code;
	}
}

if ( ! function_exists('mask_text'))
{
	/** Masking untuk tampilan pencarian terbatas (mis. nama/username). */
	function mask_text($value, $visible = 2)
	{
		$value = (string) $value;
		$len = mb_strlen($value);
		if ($len <= $visible)
		{
			return str_repeat('•', max(1, $len));
		}
		return mb_substr($value, 0, $visible).str_repeat('•', min(6, $len - $visible));
	}
}

if ( ! function_exists('old'))
{
	/** Nilai input sebelumnya (setelah validasi gagal) yang disimpan controller di $this->old_input. */
	function old($key, $default = '')
	{
		$CI =& get_instance();
		if (isset($CI->old_input) && is_array($CI->old_input) && array_key_exists($key, $CI->old_input))
		{
			return $CI->old_input[$key];
		}
		return $default;
	}
}

if ( ! function_exists('field_error'))
{
	/** Pesan error field yang terhubung via aria-describedby. */
	function field_error($key)
	{
		$CI =& get_instance();
		if (isset($CI->form_errors[$key]) && $CI->form_errors[$key] !== '')
		{
			return '<div class="invalid-feedback d-block" id="err-'.e($key).'">'.e($CI->form_errors[$key]).'</div>';
		}
		return '';
	}
}

if ( ! function_exists('field_invalid'))
{
	function field_invalid($key)
	{
		$CI =& get_instance();
		return isset($CI->form_errors[$key]) ? ' is-invalid" aria-invalid="true" aria-describedby="err-'.e($key) : '';
	}
}

if ( ! function_exists('str_limit_id'))
{
	function str_limit_id($text, $limit = 160)
	{
		$text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $text)));
		return (mb_strlen($text) > $limit) ? rtrim(mb_substr($text, 0, $limit - 1)).'…' : $text;
	}
}

if ( ! function_exists('app_config'))
{
	/** Ambil item konfigurasi domain (file config/app.php dimuat sebagai section "app"). */
	function app_config($key, $default = NULL)
	{
		$CI =& get_instance();
		$value = $CI->config->item($key, 'app');
		if ($value === NULL OR $value === FALSE)
		{
			$value = $CI->config->item($key);
		}
		return ($value === NULL OR $value === FALSE) ? $default : $value;
	}
}

if ( ! function_exists('placeholder_media'))
{
	/** Placeholder rapi yang jelas menandakan foto belum final (bukan foto stok/generatif). */
	function placeholder_media($label = 'Foto belum tersedia')
	{
		return '<div class="ph" role="img" aria-label="'.e($label).'">'
			.'<svg class="ph-hills" viewBox="0 0 400 200" preserveAspectRatio="none" aria-hidden="true" focusable="false">'
			.'<path d="M0 150 C60 110 110 120 160 95 S260 60 310 90 S380 120 400 105 V200 H0Z" fill="rgba(255,255,255,.10)"/>'
			.'<path d="M0 175 C70 140 130 150 200 128 S320 110 400 140 V200 H0Z" fill="rgba(255,255,255,.14)"/></svg>'
			.'<span class="ph-label">'.icon('image').' '.e($label).'</span></div>';
	}
}

if ( ! function_exists('media_url'))
{
	function media_url($media)
	{
		return $media ? base_url('media/'.ltrim($media->storage_key, '/')) : '';
	}
}

if ( ! function_exists('media_img'))
{
	/**
	 * <img> media publik dengan dimensi eksplisit (mencegah CLS) dan lazy-load default.
	 * $media NULL -> placeholder.
	 */
	function media_img($media, $placeholder_label = 'Foto belum tersedia', $eager = FALSE, $sizes = '100vw')
	{
		if ( ! $media)
		{
			return placeholder_media($placeholder_label);
		}
		$attrs = ' width="'.(int) ($media->width ?: 1200).'" height="'.(int) ($media->height ?: 800).'"';
		$attrs .= $eager ? ' fetchpriority="high"' : ' loading="lazy" decoding="async"';
		return '<img src="'.e(media_url($media)).'" alt="'.e($media->alt_text).'"'.$attrs.' sizes="'.e($sizes).'">';
	}
}

if ( ! function_exists('preview_badge'))
{
	function preview_badge($status)
	{
		if ($status === 'published' OR $status === NULL)
		{
			return '';
		}
		$labels = array('draft' => 'Draft', 'in_review' => 'Menunggu review', 'archived' => 'Diarsipkan');
		return '<span class="preview-badge">'.icon('eye').' '.e($labels[$status] ?? $status).'</span>';
	}
}

if ( ! function_exists('nav_href'))
{
	/** href aman untuk tautan menu/CTA: path internal -> URL situs, eksternal http(s) apa adanya. */
	function nav_href($url)
	{
		$url = trim((string) $url);
		if ( ! app_is_safe_url($url))
		{
			return '#';
		}
		if ($url[0] === '/')
		{
			return e(rtrim(base_url(), '/').$url);
		}
		return e($url);
	}
}
