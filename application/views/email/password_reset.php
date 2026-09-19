<?php defined('BASEPATH') OR exit('No direct script access allowed');
if ($format === 'text'): ?>
Halo <?= $name ?>,

Kami menerima permintaan atur ulang password untuk akun Layanan Desa Cihawuk Anda. Gunakan tautan berikut dalam 30 menit (sekali pakai):

<?= $url ?>

Jika Anda tidak meminta reset, abaikan email ini. Password Anda tidak berubah.

Pemerintah Desa Cihawuk
<?php else: ?>
<div style="font-family:Arial,sans-serif;color:#182B24;line-height:1.6;max-width:560px">
	<p>Halo <?= e($name) ?>,</p>
	<p>Kami menerima permintaan atur ulang password untuk akun Layanan Desa Cihawuk Anda. Tautan berlaku 30 menit dan hanya dapat dipakai sekali.</p>
	<p><a href="<?= e($url) ?>" style="display:inline-block;background:#174B3A;color:#fff;padding:12px 22px;border-radius:999px;text-decoration:none;font-weight:bold">Atur ulang password</a></p>
	<p style="color:#596A62;font-size:13px">Jika Anda tidak meminta reset, abaikan email ini. Password Anda tidak berubah.</p>
	<p>Pemerintah Desa Cihawuk</p>
</div>
<?php endif;
