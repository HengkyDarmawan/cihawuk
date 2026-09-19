<?php defined('BASEPATH') OR exit('No direct script access allowed');
$chw_title = (isset($heading) && $heading !== 'An Error Was Encountered') ? strip_tags($heading) : 'Terjadi kesalahan';
$chw_message = isset($message) ? $message : 'Permintaan tidak dapat diproses.';
include __DIR__.'/_page.php';
