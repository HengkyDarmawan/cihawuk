<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Panel identitas desa di sisi kiri halaman masuk/akun. Disembunyikan di layar kecil. */
?>
<aside class="auth-brand" aria-hidden="true">
	<div class="auth-brand-inner">
		<div class="auth-brand-logo">
			<svg viewBox="0 0 40 40" width="52" height="52" focusable="false"><rect width="40" height="40" rx="10" fill="#D7AF67" opacity=".22"/><path d="M6 29 15 17l6 7 4-5 9 10H6Z" fill="#D7AF67"/><circle cx="28" cy="12" r="3.2" fill="#fff"/></svg>
			<div><strong>Desa Cihawuk</strong><span>Kecamatan Kertasari · Kabupaten Bandung</span></div>
		</div>
		<h2>Layanan desa dalam satu pintu.</h2>
		<p>Sampaikan laporan, pantau tindak lanjutnya, dan kelola layanan desa dengan aman.</p>
		<ul class="auth-brand-points">
			<li><?= icon('message-square') ?><span>Kirim laporan dan aspirasi warga</span></li>
			<li><?= icon('activity') ?><span>Pantau status tiket secara terbuka</span></li>
			<li><?= icon('shield') ?><span>Akun terlindungi, setiap aksi tercatat</span></li>
		</ul>
	</div>
	<svg class="auth-brand-hills" viewBox="0 0 600 160" preserveAspectRatio="none" focusable="false"><path d="M0 120 90 70l70 40 90-80 110 90 80-50 160 80v30H0Z" fill="rgba(255,255,255,.06)"/><path d="M0 140 120 100l100 30 120-60 120 70 140-40v60H0Z" fill="rgba(215,175,103,.14)"/></svg>
</aside>
