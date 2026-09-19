<?php
/**
 * Front controller Desa Cihawuk (CodeIgniter 3).
 *
 * application/, system/, vendor/, storage/ dan .env berada di luar folder public.
 */

require dirname(__DIR__).'/bootstrap/app.php';

$app_env = app_env('APP_ENV', 'production');
if ( ! in_array($app_env, array('development', 'testing', 'production'), TRUE))
{
	header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
	echo 'The application environment is not set correctly.';
	exit(1);
}
define('ENVIRONMENT', $app_env);

switch (ENVIRONMENT)
{
	case 'development':
	case 'testing':
		error_reporting(E_ALL);
		ini_set('display_errors', (ENVIRONMENT === 'development' && app_env_bool('APP_DISPLAY_ERRORS', TRUE)) ? '1' : '0');
	break;
	case 'production':
		error_reporting(E_ALL);
		ini_set('display_errors', '0');
	break;
}
ini_set('log_errors', '1');
ini_set('error_log', ROOTPATH.'storage/logs/php-error.log');

$system_path = ROOTPATH.'system';
$application_folder = ROOTPATH.'application';
$view_folder = '';

if (defined('STDIN'))
{
	chdir(__DIR__);
}

$system_path = rtrim(realpath($system_path), '/\\').DIRECTORY_SEPARATOR;
if ( ! is_dir($system_path))
{
	header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
	echo 'System folder is not configured.';
	exit(3);
}

define('SELF', pathinfo(__FILE__, PATHINFO_BASENAME));
define('BASEPATH', $system_path);
define('FCPATH', __DIR__.DIRECTORY_SEPARATOR);
define('SYSDIR', basename(BASEPATH));

$application_folder = realpath($application_folder);
if ($application_folder === FALSE)
{
	header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
	echo 'Application folder is not configured.';
	exit(3);
}
define('APPPATH', $application_folder.DIRECTORY_SEPARATOR);
define('VIEWPATH', APPPATH.'views'.DIRECTORY_SEPARATOR);

require_once BASEPATH.'core/CodeIgniter.php';
