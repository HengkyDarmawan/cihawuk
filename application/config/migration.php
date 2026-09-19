<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Migration hanya dijalankan lewat CLI: php public/index.php tools migrate
$config['migration_enabled'] = is_cli();
$config['migration_type'] = 'sequential';
$config['migration_table'] = 'migrations';
$config['migration_auto_latest'] = FALSE;
$config['migration_version'] = 0;
$config['migration_path'] = APPPATH.'migrations/';
