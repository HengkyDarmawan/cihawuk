<?php
/**
 * Bootstrap pengujian.
 *
 * - Selalu memakai environment "testing" dan database pengujian tersendiri
 *   (.env.testing). Database aplikasi tidak pernah disentuh oleh tes.
 * - Memuat instance CodeIgniter melalui front controller CLI (route tools/noop),
 *   sehingga service, model, dan helper dapat diuji apa adanya.
 */

putenv('APP_ENV=testing');
$_SERVER['APP_ENV'] = 'testing';
define('CHW_TESTING', TRUE);

$root = dirname(__DIR__);
require_once $root.'/bootstrap/app.php';

$database = (string) app_env('DB_DATABASE', '');
if (strpos($database, '_test') === FALSE)
{
	fwrite(STDERR, "Bootstrap tes dihentikan: DB_DATABASE ('".$database."') bukan database pengujian.\n");
	exit(1);
}

// Siapkan skema + data master pada DB pengujian (sekali per eksekusi).
$php = PHP_BINARY;
$commands = array(
	escapeshellarg($php).' '.escapeshellarg($root.'/public/index.php').' tools migrate 0',
	escapeshellarg($php).' '.escapeshellarg($root.'/public/index.php').' tools migrate',
	escapeshellarg($php).' '.escapeshellarg($root.'/public/index.php').' tools seed',
);
foreach ($commands as $command)
{
	$output = array();
	$code = 0;
	exec('set APP_ENV=testing&& '.$command.' 2>&1', $output, $code);
	if ($code !== 0)
	{
		fwrite(STDERR, "Persiapan DB tes gagal:\n".implode("\n", $output)."\n");
		exit(1);
	}
}

// Muat CodeIgniter dalam mode CLI tanpa efek samping (Tools::noop).
$_SERVER['argv'] = array('index.php', 'tools', 'noop');
$_SERVER['argc'] = 3;
require_once $root.'/public/index.php';

require_once __DIR__.'/CiTestCase.php';
