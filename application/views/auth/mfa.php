<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="auth-shell">
	<div class="card-soft auth-card">
		<p class="eyebrow">Keamanan akun</p>
		<h1>Verifikasi dua langkah</h1>
		<p class="lead">Masukkan kode 6 digit dari aplikasi autentikator, atau salah satu kode pemulihan.</p>
		<?php if ( ! empty($mfa_error)): ?>
			<div class="alert alert-danger d-flex gap-2" role="alert"><?= icon('alert-circle') ?><div><?= e($mfa_error) ?></div></div>
		<?php endif; ?>
		<form method="post" action="<?= site_url('mfa') ?>" data-once>
			<?= csrf_field() ?>
			<div class="mb-4">
				<label class="form-label" for="code">Kode verifikasi</label>
				<input class="form-control form-control-lg" id="code" name="code" type="text" inputmode="text" autocomplete="one-time-code" autocapitalize="characters" spellcheck="false" required maxlength="20" aria-describedby="code-help">
				<div class="form-text" id="code-help">Kode autentikator berganti setiap 30 detik. Kode pemulihan hanya dapat dipakai sekali.</div>
			</div>
			<button class="btn btn-primary btn-lg w-100" type="submit">Verifikasi</button>
		</form>
		<p class="auth-alt"><a href="<?= site_url('masuk') ?>">Kembali ke halaman masuk</a></p>
	</div>
</section>
