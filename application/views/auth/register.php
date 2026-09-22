<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="auth-shell">
	<div class="auth-split is-wide">
		<?php $this->load->view('auth/_brand'); ?>
		<div class="card-soft auth-card">
			<div class="auth-form-inner">
				<p class="eyebrow">Akun warga</p>
				<h1>Daftar akun</h1>
				<p class="lead">Akun membantu Anda membuat, memantau, dan membalas laporan dari satu dashboard. NIK tidak diperlukan.</p>

				<?= ui_error_summary(array('display_name' => 'Nama', 'username' => 'Username', 'email' => 'Email', 'phone' => 'Telepon', 'password' => 'Password', 'password_confirm' => 'Konfirmasi password', 'agree' => 'Persetujuan')) ?>

				<form method="post" action="<?= site_url('daftar') ?>" novalidate data-once>
					<?= csrf_field() ?>
					<div class="visually-hidden" aria-hidden="true">
						<label for="website">Jangan isi field ini</label>
						<input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
					</div>
					<?= ui_input(array('name' => 'display_name', 'label' => 'Nama', 'required' => TRUE, 'autocomplete' => 'name', 'maxlength' => 100, 'minlength' => 3)) ?>
					<?= ui_input(array('name' => 'username', 'label' => 'Username', 'required' => TRUE, 'autocomplete' => 'username', 'maxlength' => 50, 'minlength' => 4,
						'help' => '4–50 karakter: huruf kecil, angka, titik (.) atau garis bawah (_).', 'raw_attrs' => 'autocapitalize="none" spellcheck="false"')) ?>
					<?= ui_input(array('name' => 'email', 'label' => 'Email', 'type' => 'email', 'autocomplete' => 'email', 'maxlength' => 191,
						'help' => $mail_enabled ? 'Dipakai untuk aktivasi dan pemulihan password.' : 'Aktivasi akun saat ini dilakukan melalui review petugas desa.')) ?>
					<?= ui_input(array('name' => 'phone', 'label' => 'Nomor telepon', 'type' => 'tel', 'autocomplete' => 'tel', 'maxlength' => 20, 'inputmode' => 'tel',
						'help' => 'Contoh: 081234567890. Nomor tidak dipakai untuk WhatsApp otomatis.')) ?>
					<?= ui_password(array('name' => 'password', 'label' => 'Password', 'autocomplete' => 'new-password', 'required' => TRUE, 'minlength' => 12,
						'help' => 'Minimal 12 karakter. Kalimat yang mudah Anda ingat lebih aman daripada kata pendek.')) ?>
					<?= ui_password(array('name' => 'password_confirm', 'label' => 'Ulangi password', 'autocomplete' => 'new-password', 'required' => TRUE)) ?>

					<div class="form-check mb-4">
						<input class="form-check-input<?= isset($this->form_errors['agree']) ? ' is-invalid' : '' ?>" type="checkbox" value="1" id="agree" name="agree" required <?= isset($this->form_errors['agree']) ? 'aria-describedby="err-agree" aria-invalid="true"' : '' ?>>
						<label class="form-check-label" for="agree">Saya telah membaca <a href="<?= site_url('privasi') ?>" target="_blank" rel="noopener">kebijakan privasi</a> dan <a href="<?= site_url('ketentuan') ?>" target="_blank" rel="noopener">ketentuan layanan</a>.</label>
						<?= field_error('agree') ?>
					</div>
					<button class="btn btn-primary btn-lg w-100" type="submit"><span data-loading-label="Mengirim…">Daftar</span></button>
				</form>
				<p class="auth-alt">Sudah punya akun? <a href="<?= site_url('masuk') ?>">Masuk</a></p>
			</div>
		</div>
	</div>
</section>
