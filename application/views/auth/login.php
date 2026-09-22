<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="auth-shell">
	<div class="auth-split">
		<?php $this->load->view('auth/_brand'); ?>
		<div class="card-soft auth-card">
			<div class="auth-form-inner">
				<p class="eyebrow">Layanan Desa Cihawuk</p>
				<h1>Selamat datang</h1>
				<p class="lead">Masuk untuk warga berakun dan petugas pelayanan desa.</p>

				<?php if ( ! empty($login_error)): ?>
					<div class="alert alert-danger d-flex gap-2 auth-inline-error" role="alert" data-swal-alert="error" data-swal-title="Gagal masuk"><?= icon('alert-circle') ?><div><?= e($login_error) ?></div></div>
				<?php endif; ?>

				<form method="post" action="<?= site_url('masuk') ?>" novalidate data-once>
					<?= csrf_field() ?>
					<?= ui_input(array('name' => 'identifier', 'label' => 'Username atau email', 'required' => TRUE, 'autocomplete' => 'username', 'maxlength' => 191, 'raw_attrs' => 'autocapitalize="none" spellcheck="false" placeholder="mis. budi.santoso"')) ?>
					<?= ui_password(array('name' => 'password', 'label' => 'Password', 'autocomplete' => 'current-password', 'required' => TRUE)) ?>
					<div class="auth-links">
						<a href="<?= site_url('lupa-password') ?>">Lupa password?</a>
						<a href="<?= site_url('aktivasi') ?>">Punya kode aktivasi?</a>
					</div>
					<button class="btn btn-primary btn-lg w-100" type="submit"><?= icon('log-in') ?><span data-loading-label="Memproses…">Masuk</span></button>
				</form>

				<div class="divider-text">belum punya akun?</div>
				<div class="d-grid gap-2">
					<a class="btn btn-outline-primary btn-lg" href="<?= site_url('daftar') ?>"><?= icon('user-plus') ?> Daftar akun warga</a>
					<a class="btn btn-link" href="<?= site_url('lapor') ?>">Kirim laporan tanpa akun</a>
				</div>
			</div>
		</div>
	</div>
</section>
