<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$active_group = 'default';
$query_builder = TRUE;

$_chw_db_base = array(
	'dsn'      => '',
	'hostname' => (string) app_env('DB_HOST', '127.0.0.1'),
	'port'     => app_env_int('DB_PORT', 3306),
	'database' => (string) app_env('DB_DATABASE', 'cihawuk_digital'),
	'dbdriver' => 'mysqli',
	'dbprefix' => '',
	'pconnect' => FALSE,
	// Error DB tidak ditampilkan ke pengguna; dicatat melalui log.
	'db_debug' => FALSE,
	'cache_on' => FALSE,
	'cachedir' => '',
	'char_set' => 'utf8mb4',
	'dbcollat' => 'utf8mb4_unicode_ci',
	'swap_pre' => '',
	'encrypt'  => FALSE,
	'compress' => FALSE,
	'stricton' => TRUE,
	'failover' => array(),
	'save_queries' => (ENVIRONMENT === 'development'),
);

// Akun aplikasi: hanya DML pada database proyek.
$db['default'] = array_merge($_chw_db_base, array(
	'username' => (string) app_env('DB_USERNAME', ''),
	'password' => (string) app_env('DB_PASSWORD', ''),
));

// Akun deployment untuk migration (DDL). Hanya dipakai lewat CLI tools.
$db['migrate'] = array_merge($_chw_db_base, array(
	'username' => (string) app_env('DB_MIGRATE_USERNAME', ''),
	'password' => (string) app_env('DB_MIGRATE_PASSWORD', ''),
	'save_queries' => FALSE,
));

unset($_chw_db_base);
