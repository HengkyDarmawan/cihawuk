<?php defined('BASEPATH') OR exit('No direct script access allowed');
if ($format === 'text'): ?>
Halo <?= $name ?>,

<?= $summary ?>


Lihat detail setelah masuk: <?= $url ?>

Email ini tidak memuat isi laporan demi menjaga privasi.
Pemerintah Desa Cihawuk
<?php else: ?>
<div style="font-family:Arial,sans-serif;color:#182B24;line-height:1.6;max-width:560px">
	<p>Halo <?= e($name) ?>,</p>
	<p><?= e($summary) ?></p>
	<p><a href="<?= e($url) ?>" style="display:inline-block;background:#174B3A;color:#fff;padding:12px 22px;border-radius:999px;text-decoration:none;font-weight:bold">Lihat detail</a></p>
	<p style="color:#596A62;font-size:13px">Email ini tidak memuat isi laporan demi menjaga privasi.</p>
	<p>Pemerintah Desa Cihawuk</p>
</div>
<?php endif;
