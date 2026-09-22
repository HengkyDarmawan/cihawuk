<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="auth-shell">
	<div class="auth-split">
		<?php $this->load->view('auth/_brand'); ?>
		<div class="card-soft auth-card">
			<div class="auth-form-inner">
				<p class="eyebrow">Pendaftaran diterima</p>
				<h1>Langkah berikutnya</h1>
				<?php if ($activation === 'email_sent'): ?>
					<p>Kami mengirim tautan aktivasi ke email Anda. Tautan berlaku 3 hari dan hanya dapat dipakai sekali.</p>
				<?php else: ?>
					<p>Akun Anda berstatus <strong>menunggu aktivasi</strong>. Petugas pelayanan desa akan mereview pendaftaran. Anda dapat menanyakan status aktivasi di kantor desa.</p>
				<?php endif; ?>
				<div class="privacy-panel my-4">
					<h2>Sambil menunggu</h2>
					<ul>
						<li>Anda tetap dapat mengirim laporan <strong>tanpa akun</strong> dan melacaknya dengan kode akses.</li>
						<li>Setelah akun aktif, masuk menggunakan username yang Anda daftarkan.</li>
					</ul>
				</div>
				<div class="d-grid gap-2">
					<a class="btn btn-primary" href="<?= site_url('lapor') ?>">Buat laporan tanpa akun</a>
					<a class="btn btn-outline-primary" href="<?= site_url('masuk') ?>">Ke halaman masuk</a>
				</div>
			</div>
		</div>
	</div>
</section>
