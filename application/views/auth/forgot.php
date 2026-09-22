<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="auth-shell">
	<div class="auth-split">
		<?php $this->load->view('auth/_brand'); ?>
		<div class="card-soft auth-card">
			<div class="auth-form-inner">
				<p class="eyebrow">Pemulihan akun</p>
				<h1>Lupa password</h1>
				<?php if ( ! empty($sent)): ?>
					<div class="alert alert-success d-flex gap-2" role="status" data-swal-alert="success"><?= icon('check-circle') ?><div>Jika email tersebut terdaftar dan sudah terverifikasi, tautan reset telah dikirim. Tautan berlaku 30 menit.</div></div>
				<?php endif; ?>
				<?php if ($mail_enabled): ?>
					<p class="lead">Masukkan email terverifikasi yang terhubung dengan akun Anda.</p>
					<form method="post" action="<?= site_url('lupa-password') ?>" novalidate data-once>
						<?= csrf_field() ?>
						<?= ui_input(array('name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => TRUE, 'autocomplete' => 'email', 'maxlength' => 191, 'value' => '')) ?>
						<button class="btn btn-primary btn-lg w-100" type="submit">Kirim tautan reset</button>
					</form>
					<div class="divider-text">tidak punya email terverifikasi?</div>
				<?php else: ?>
					<p class="lead">Pengiriman email belum tersedia. Pemulihan password dilakukan melalui petugas pelayanan desa.</p>
				<?php endif; ?>
				<div class="privacy-panel">
					<h2>Pemulihan melalui kantor desa</h2>
					<ul>
						<li>Datang ke kantor desa dan sampaikan username Anda.</li>
						<li>Petugas memeriksa identitas sesuai prosedur desa. Mengetahui nama, tanggal lahir, atau NIK saja tidak cukup.</li>
						<li>Petugas memberikan kode reset sekali pakai. Masukkan kode di halaman <a href="<?= site_url('reset-password') ?>">atur ulang password</a>.</li>
					</ul>
				</div>
				<p class="auth-alt"><a href="<?= site_url('masuk') ?>">Kembali ke halaman masuk</a></p>
			</div>
		</div>
	</div>
</section>
