<?php
/**
 * Router untuk PHP built-in server (pengujian HTTP otomatis saja, bukan produksi).
 *   php -S 127.0.0.1:8765 -t public scripts/dev-router.php
 * Hanya file statis di public/ yang dilayani langsung; selain itu ke index.php.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$public = realpath(__DIR__.'/../public');
$file = realpath($public.$path);
if ($path !== '/' && $file !== FALSE && strpos($file, $public) === 0 && is_file($file) && substr($file, -4) !== '.php')
{
	return FALSE;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $public.DIRECTORY_SEPARATOR.'index.php';
require $public.DIRECTORY_SEPARATOR.'index.php';
