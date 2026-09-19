<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| Konfigurasi utama CodeIgniter 3 untuk Desa Cihawuk.
| Nilai sensitif dibaca dari environment (lihat .env.example); jangan
| menuliskan rahasia di file ini.
*/

// base_url tidak pernah dibangun bebas dari Host header: hanya APP_BASE_URL atau
// salah satu alamat yang terdaftar di APP_BASE_URL_ALIASES (dipisah koma).
$config['base_url'] = rtrim((string) app_env('APP_BASE_URL', 'http://localhost/'), '/').'/';
if (PHP_SAPI !== 'cli' && isset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']))
{
	$request_https = ( ! empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');
	foreach (array_filter(array_map('trim', explode(',', (string) app_env('APP_BASE_URL_ALIASES', '')))) as $alias)
	{
		$parts = parse_url($alias);
		if ( ! isset($parts['scheme'], $parts['host']))
		{
			continue;
		}
		$host = strtolower($parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '');
		$path = '/'.trim($parts['path'] ?? '', '/');
		$path_matches = ($path === '/')
			|| strpos((string) $_SERVER['REQUEST_URI'].'/', $path.'/') === 0;
		if (strtolower((string) $_SERVER['HTTP_HOST']) === $host
			&& ($parts['scheme'] === 'https') === $request_https
			&& $path_matches)
		{
			$config['base_url'] = $parts['scheme'].'://'.$host.rtrim($path, '/').'/';
			break;
		}
	}
}
$config['index_page'] = '';
$config['uri_protocol'] = 'REQUEST_URI';
$config['url_suffix'] = '';
$config['language'] = 'indonesian';
$config['charset'] = 'UTF-8';
$config['enable_hooks'] = FALSE;
$config['subclass_prefix'] = 'MY_';

// Autoload Composer sudah dimuat oleh bootstrap/app.php.
$config['composer_autoload'] = FALSE;

$config['permitted_uri_chars'] = 'a-z 0-9~%.:_\-';
$config['enable_query_strings'] = FALSE;
$config['controller_trigger'] = 'c';
$config['function_trigger'] = 'm';
$config['directory_trigger'] = 'd';
$config['allow_get_array'] = TRUE;

$config['log_threshold'] = 1;
$config['log_path'] = ROOTPATH.'storage/logs/';
$config['log_file_extension'] = 'log';
$config['log_file_permissions'] = 0640;
$config['log_date_format'] = 'Y-m-d H:i:s';
$config['error_views_path'] = '';
$config['cache_path'] = ROOTPATH.'storage/cache/';
$config['cache_query_string'] = FALSE;

$config['encryption_key'] = (string) app_env('APP_CI_ENCRYPTION_KEY', '');

// Sesi CI3 disimpan di database. Idle/absolute timeout per area diperiksa aplikasi
// (AuthService); sess_expiration adalah batas atas cookie.
$config['sess_driver'] = (string) app_env('SESSION_DRIVER', 'database');
$config['sess_cookie_name'] = 'chw_session';
$config['sess_samesite'] = 'Lax';
$config['sess_expiration'] = 43200;
$config['sess_save_path'] = (string) app_env('SESSION_SAVE_PATH', 'ci_sessions');
$config['sess_match_ip'] = FALSE;
$config['sess_time_to_update'] = 300;
$config['sess_regenerate_destroy'] = TRUE;

$config['cookie_prefix'] = '';
$config['cookie_domain'] = '';
$config['cookie_path'] = '/';
$config['cookie_secure'] = app_env_bool('COOKIE_SECURE', FALSE);
$config['cookie_httponly'] = TRUE;
$config['cookie_samesite'] = 'Lax';

$config['standardize_newlines'] = FALSE;
// Tidak bergantung pada xss_clean; output di-escape sesuai konteks.
$config['global_xss_filtering'] = FALSE;

$config['csrf_protection'] = TRUE;
$config['csrf_token_name'] = 'csrf_chw';
$config['csrf_cookie_name'] = 'csrf_chw_cookie';
$config['csrf_expire'] = 7200;
$config['csrf_regenerate'] = TRUE;
$config['csrf_exclude_uris'] = array();

$config['compress_output'] = FALSE;
$config['time_reference'] = 'gmt';
$config['rewrite_short_tags'] = FALSE;

// Hanya proxy yang dikonfigurasi yang dipercaya untuk X-Forwarded-For.
$config['proxy_ips'] = (string) app_env('APP_TRUSTED_PROXIES', '');
