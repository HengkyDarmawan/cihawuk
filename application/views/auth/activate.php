<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="auth-shell">
	<div class="card-soft auth-card" style="max-width: 540px;">
		<p class="eyebrow">Akun warga</p>
		<h1>Aktivasi akun</h1>
		<p class="lead">Masukkan kode aktivasi dari email atau yang diberikan petugas loket, lalu tetapkan password Anda sendiri.</p>

		<?php if ( ! empty($token_invalid)): ?>
			<div class="alert alert-warning d-flex gap-2" role="alert"><?= icon('alert-triangle') ?><div>Kode aktivasi tidak valid, sudah digunakan, atau kedaluwarsa. Minta kode baru di kantor desa.</div></div>
		<?php endif; ?>
		<?php if ( ! empty($activate_error)): ?>
			<div class="alert alert-danger d-flex gap-2" role="alert"><?= icon('alert-circle') ?><div><?= e($activate_error) ?></div></div>
		<?php endif; ?>

		<form method="post" action="<?= site_url('aktivasi') ?>" novalidate data-once>
			<?= csrf_field() ?>
			<?= ui_input(array('name' => 'token', 'label' => 'Kode aktivasi', 'required' => TRUE, 'value' => $token, 'autocomplete' => 'one-time-code', 'maxlength' => 100,
				'help' => 'Salin persis seperti yang diberikan, termasuk titik di tengah.', 'raw_attrs' => 'autocapitalize="none" spellcheck="false"')) ?>
			<?php if ($needs_password): ?>
				<?= ui_password(array('name' => 'password', 'label' => 'Password baru', 'autocomplete' => 'new-password', 'required' => TRUE, 'minlength' => 12, 'help' => 'Minimal 12 karakter. Bila akun sudah memiliki password dari pendaftaran mandiri, isian ini diabaikan.')) ?>
				<?= ui_password(array('name' => 'password_confirm', 'label' => 'Ulangi password', 'autocomplete' => 'new-password', 'required' => TRUE)) ?>
			<?php endif; ?>
			<button class="btn btn-primary btn-lg w-100" type="submit"><span data-loading-label="Memproses…">Aktifkan akun</span></button>
		</form>
		<p class="auth-alt"><a href="<?= site_url('bantuan-akun') ?>">Bantuan akun</a></p>
	</div>
</section>
