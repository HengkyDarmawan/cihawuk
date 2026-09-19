<?php defined('BASEPATH') OR exit('No direct script access allowed');
if ($format === 'text'): ?>
Halo <?= $name ?>,

Terima kasih telah mendaftar di Layanan Desa Cihawuk. Aktifkan akun Anda melalui tautan berikut (berlaku 3 hari, sekali pakai):

<?= $url ?>

Jika Anda tidak merasa mendaftar, abaikan email ini.

Pemerintah Desa Cihawuk
<?php else: ?>
<div style="font-family:Arial,sans-serif;color:#182B24;line-height:1.6;max-width:560px">
	<p>Halo <?= e($name) ?>,</p>
	<p>Terima kasih telah mendaftar di Layanan Desa Cihawuk. Aktifkan akun Anda melalui tombol berikut. Tautan berlaku 3 hari dan hanya dapat dipakai sekali.</p>
	<p><a href="<?= e($url) ?>" style="display:inline-block;background:#174B3A;color:#fff;padding:12px 22px;border-radius:999px;text-decoration:none;font-weight:bold">Aktifkan akun</a></p>
	<p style="color:#596A62;font-size:13px">Jika Anda tidak merasa mendaftar, abaikan email ini.</p>
	<p>Pemerintah Desa Cihawuk</p>
</div>
<?php endif;
