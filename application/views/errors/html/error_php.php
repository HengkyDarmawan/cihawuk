<?php defined('BASEPATH') OR exit('No direct script access allowed');
if (ENVIRONMENT === 'development' && ini_get('display_errors')): ?>
<div style="border:1px solid #990000;padding:8px 12px;margin:8px;font:12px/1.5 monospace;background:#fff;color:#000">
<strong>PHP <?= htmlspecialchars((string) $severity, ENT_QUOTES, 'UTF-8') ?>:</strong> <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
— <?= htmlspecialchars($filepath, ENT_QUOTES, 'UTF-8') ?>:<?= (int) $line ?>
</div>
<?php endif;
