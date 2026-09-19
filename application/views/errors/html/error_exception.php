<?php defined('BASEPATH') OR exit('No direct script access allowed');
if (ENVIRONMENT === 'development' && ini_get('display_errors')): ?>
<div style="border:1px solid #990000;padding:12px;margin:12px;font:13px/1.5 monospace;background:#fff">
<strong>Exception (development):</strong> <?= htmlspecialchars(get_class($exception), ENT_QUOTES, 'UTF-8') ?><br>
<?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?><br>
<?= htmlspecialchars($exception->getFile(), ENT_QUOTES, 'UTF-8') ?>:<?= (int) $exception->getLine() ?>
</div>
<?php else:
$chw_title = 'Terjadi kesalahan';
$chw_message = 'Permintaan tidak dapat diproses. Kesalahan telah dicatat untuk pemeriksaan pengelola.';
include __DIR__.'/_page.php';
endif;
