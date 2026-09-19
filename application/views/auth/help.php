<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="page-hero">
	<div class="container-site">
		<h1>Bantuan akun</h1>
		<p>Panduan singkat pendaftaran, aktivasi, dan pemulihan akun Layanan Desa Cihawuk.</p>
	</div>
</section>
<section class="page-body">
	<div class="container-site">
		<div class="row g-4">
			<div class="col-lg-4">
				<div class="card-soft card-pad h-100">
					<h2 class="h5"><?= icon('user-plus') ?> Mendaftar</h2>
					<p>Daftar mandiri cukup dengan nama, username, dan password. Email dan telepon opsional. NIK tidak diminta.</p>
					<a class="link-arrow" href="<?= site_url('daftar') ?>">Daftar akun <?= icon('arrow-right') ?></a>
				</div>
			</div>
			<div class="col-lg-4">
				<div class="card-soft card-pad h-100">
					<h2 class="h5"><?= icon('key') ?> Aktivasi</h2>
					<p>Akun diaktifkan melalui tautan email (bila tersedia) atau review petugas. Warga yang didaftarkan petugas menerima kode aktivasi sekali pakai dan menetapkan password sendiri.</p>
					<a class="link-arrow" href="<?= site_url('aktivasi') ?>">Masukkan kode <?= icon('arrow-right') ?></a>
				</div>
			</div>
			<div class="col-lg-4">
				<div class="card-soft card-pad h-100">
					<h2 class="h5"><?= icon('life-buoy') ?> Lupa password</h2>
					<p>Gunakan email terverifikasi, atau datang ke kantor desa untuk pemeriksaan identitas dan kode reset sekali pakai.</p>
					<a class="link-arrow" href="<?= site_url('lupa-password') ?>">Pulihkan akun <?= icon('arrow-right') ?></a>
				</div>
			</div>
		</div>
		<p class="mt-4 text-muted">Laporan tanpa akun tetap tersedia kapan saja melalui <a href="<?= site_url('lapor') ?>">formulir laporan</a>.</p>
	</div>
</section>
