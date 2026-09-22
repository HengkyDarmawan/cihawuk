<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="auth-shell">
	<div class="auth-split">
		<?php $this->load->view('auth/_brand'); ?>
		<div class="card-soft auth-card">
			<div class="auth-form-inner">
				<p class="eyebrow">Pemulihan akun</p>
				<h1>Atur ulang password</h1>
				<p class="lead">Gunakan kode reset dari email atau dari petugas desa. Setelah berhasil, semua sesi lama akan dikeluarkan.</p>
				<?php if ( ! empty($token_invalid)): ?>
					<div class="alert alert-warning d-flex gap-2 auth-inline-error" role="alert" data-swal-alert="warning"><?= icon('alert-triangle') ?><div>Tautan reset tidak valid, sudah dipakai, atau kedaluwarsa.</div></div>
				<?php endif; ?>
				<?php if ( ! empty($reset_error)): ?>
					<div class="alert alert-danger d-flex gap-2 auth-inline-error" role="alert" data-swal-alert="error"><?= icon('alert-circle') ?><div><?= e($reset_error) ?></div></div>
				<?php endif; ?>
				<form method="post" action="<?= site_url('reset-password') ?>" novalidate data-once>
					<?= csrf_field() ?>
					<?= ui_input(array('name' => 'token', 'label' => 'Kode reset', 'required' => TRUE, 'value' => $token, 'autocomplete' => 'one-time-code', 'maxlength' => 100, 'raw_attrs' => 'autocapitalize="none" spellcheck="false"')) ?>
					<?= ui_password(array('name' => 'password', 'label' => 'Password baru', 'autocomplete' => 'new-password', 'required' => TRUE, 'minlength' => 12, 'help' => 'Minimal 12 karakter.')) ?>
					<?= ui_password(array('name' => 'password_confirm', 'label' => 'Ulangi password baru', 'autocomplete' => 'new-password', 'required' => TRUE)) ?>
					<button class="btn btn-primary btn-lg w-100" type="submit"><span data-loading-label="Menyimpan…">Simpan password baru</span></button>
				</form>
			</div>
		</div>
	</div>
</section>
