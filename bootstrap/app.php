<?php
/**
 * Bootstrap bersama untuk front controller web dan CLI.
 *
 * - Memuat autoload Composer.
 * - Membaca environment dari `.env` (atau `.env.testing` saat APP_ENV=testing
 *   berasal dari environment proses).
 * - Menetapkan zona waktu PHP ke UTC; tampilan dikonversi ke APP_TIMEZONE.
 */

defined('ROOTPATH') OR define('ROOTPATH', dirname(__DIR__).DIRECTORY_SEPARATOR);

require_once ROOTPATH.'vendor/autoload.php';

if ( ! function_exists('app_env'))
{
	/**
	 * Membaca variabel environment.
	 *
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	function app_env($key, $default = NULL)
	{
		// Variabel environment proses (mis. diset oleh server/scheduler) menang atas file .env.
		$process = getenv($key, TRUE);
		if ($process !== FALSE)
		{
			return $process;
		}
		if (array_key_exists($key, $_ENV))
		{
			return $_ENV[$key];
		}
		if (array_key_exists($key, $_SERVER) && is_string($_SERVER[$key]))
		{
			return $_SERVER[$key];
		}
		$value = getenv($key);
		return ($value === FALSE) ? $default : $value;
	}

	/**
	 * Boolean ketat: hanya "1", "true", "yes", "on" yang dianggap TRUE.
	 * String "false" tidak pernah menjadi TRUE.
	 */
	function app_env_bool($key, $default = FALSE)
	{
		$value = app_env($key, NULL);
		if ($value === NULL OR $value === '')
		{
			return (bool) $default;
		}
		if (is_bool($value))
		{
			return $value;
		}
		return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'on'), TRUE);
	}

	function app_env_int($key, $default = 0)
	{
		$value = app_env($key, NULL);
		return ($value === NULL OR $value === '' OR ! is_numeric($value)) ? (int) $default : (int) $value;
	}
}

(function () {
	$process_env = getenv('APP_ENV');
	$file = ($process_env === 'testing') ? '.env.testing' : '.env';

	if (is_file(ROOTPATH.$file))
	{
		// createImmutable: variabel yang sudah ada di environment proses tidak ditimpa.
		Dotenv\Dotenv::createImmutable(ROOTPATH, $file)->load();
	}
})();

date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');
