<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="page-heading">
	<div>
		<h1>Keamanan akun</h1>
		<p>Kelola password, perangkat yang masuk, dan autentikasi dua langkah.</p>
	</div>
</div>

<?php if ( ! empty($recovery_codes)): ?>
<div class="card shadow-sm mb-4 border-left-accent">
	<div class="card-header"><h2>Kode pemulihan MFA (ditampilkan sekali)</h2></div>
	<div class="card-body">
		<p>Simpan kode berikut di tempat aman. Setiap kode hanya dapat dipakai satu kali bila Anda kehilangan akses ke aplikasi autentikator.</p>
		<div class="code-display" id="recovery-codes"><?= e(implode('   ', $recovery_codes)) ?></div>
		<button class="btn btn-outline-primary btn-sm mt-2" type="button" data-copy-target="recovery-codes">Salin kode</button>
	</div>
</div>
<?php endif; ?>

<?php if ( ! empty($mfa_secret)): ?>
<div class="card shadow-sm mb-4 border-left-primary">
	<div class="card-header"><h2>Aktifkan autentikasi dua langkah</h2></div>
	<div class="card-body">
		<ol class="mb-3">
			<li>Buka aplikasi autentikator (mis. Google Authenticator, Aegis, atau 1Password).</li>
			<li>Tambahkan akun secara manual dengan kunci di bawah ini, atau buka tautan <code>otpauth://</code> dari perangkat yang sama.</li>
			<li>Masukkan kode 6 digit yang muncul untuk mengonfirmasi.</li>
		</ol>
		<p class="code-label">Kunci rahasia</p>
		<div class="code-display" id="mfa-secret"><?= e(chunk_split($mfa_secret, 4, ' ')) ?></div>
		<button class="btn btn-outline-primary btn-sm mt-2 mb-3" type="button" data-copy-target="mfa-secret">Salin kunci</button>
		<p class="small text-muted">Tautan aplikasi: <a href="<?= e($mfa_uri) ?>">buka di aplikasi autentikator</a> (hanya berfungsi di perangkat yang memiliki aplikasi tersebut).</p>
		<?php if ( ! empty($mfa_error)): ?><div class="alert alert-danger" role="alert"><?= e($mfa_error) ?></div><?php endif; ?>
		<form method="post" action="<?= site_url($base.'/mfa_aktifkan') ?>" class="form-inline" data-once>
			<?= csrf_field() ?>
			<label class="mr-2" for="code">Kode 6 digit</label>
			<input class="form-control mr-2" type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="10" required>
			<button class="btn btn-primary" type="submit">Aktifkan MFA</button>
		</form>
	</div>
</div>
<?php endif; ?>

<div class="row">
	<div class="col-lg-6 mb-4">
		<div class="card shadow-sm h-100">
			<div class="card-header"><h2>Ganti password</h2></div>
			<div class="card-body">
				<?php if ( ! empty($password_error)): ?><div class="alert alert-danger" role="alert"><?= e($password_error) ?></div><?php endif; ?>
				<form method="post" action="<?= site_url($base.'/password') ?>" novalidate data-once>
					<?= csrf_field() ?>
					<?= ui_password(array('name' => 'current_password', 'label' => 'Password saat ini', 'required' => TRUE, 'autocomplete' => 'current-password')) ?>
					<?= ui_password(array('name' => 'password', 'label' => 'Password baru', 'required' => TRUE, 'minlength' => 12, 'autocomplete' => 'new-password', 'help' => 'Minimal 12 karakter.')) ?>
					<?= ui_password(array('name' => 'password_confirm', 'label' => 'Ulangi password baru', 'required' => TRUE, 'autocomplete' => 'new-password')) ?>
					<p class="small text-muted">Setelah berhasil, sesi di perangkat lain akan dikeluarkan.</p>
					<button class="btn btn-primary" type="submit">Simpan password</button>
				</form>
			</div>
		</div>
	</div>

	<div class="col-lg-6 mb-4">
		<div class="card shadow-sm h-100">
			<div class="card-header"><h2>Autentikasi dua langkah (MFA)</h2></div>
			<div class="card-body">
				<?php if ($mfa_enabled): ?>
					<p><span class="chip-flag is-info"><i class="fas fa-shield-alt mr-1" aria-hidden="true"></i> Aktif</span> Sisa kode pemulihan: <strong><?= (int) $recovery_left ?></strong>.</p>
					<form method="post" action="<?= site_url($base.'/mfa_nonaktif') ?>" data-confirm="Nonaktifkan MFA? Akun Anda akan lebih rentan." data-confirm-ok="Nonaktifkan">
						<?= csrf_field() ?>
						<button class="btn btn-outline-danger btn-sm" type="submit">Nonaktifkan MFA</button>
					</form>
				<?php else: ?>
					<p>MFA menambahkan kode sekali pakai dari aplikasi autentikator saat Anda masuk. Sangat disarankan untuk akun pengelola.</p>
					<?php if ($reauth_ok): ?>
					<form method="post" action="<?= site_url($base.'/mfa_mulai') ?>">
						<?= csrf_field() ?>
						<button class="btn btn-primary btn-sm" type="submit">Mulai pengaturan MFA</button>
					</form>
					<?php else: ?>
					<form method="post" action="<?= site_url($base.'/konfirmasi') ?>" class="form-inline" data-once>
						<?= csrf_field() ?>
						<label class="mr-2" for="reauth_password">Konfirmasi password</label>
						<input class="form-control mr-2" type="password" id="reauth_password" name="password" autocomplete="current-password" required>
						<button class="btn btn-primary" type="submit">Konfirmasi</button>
					</form>
					<p class="small text-muted mt-2 mb-0">Konfirmasi password diperlukan sebelum mengubah pengaturan MFA.</p>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<div class="card shadow-sm">
	<div class="card-header d-flex justify-content-between align-items-center">
		<h2>Sesi aktif</h2>
		<form method="post" action="<?= site_url($base.'/sesi_semua') ?>" data-confirm="Keluarkan semua sesi termasuk perangkat ini?" data-confirm-ok="Keluarkan semua">
			<?= csrf_field() ?>
			<button class="btn btn-outline-danger btn-sm" type="submit">Keluar dari semua perangkat</button>
		</form>
	</div>
	<div class="card-body">
		<div class="table-responsive">
			<table class="table mb-0">
				<caption class="sr-only">Daftar sesi login aktif</caption>
				<thead><tr><th scope="col">Perangkat</th><th scope="col">Area</th><th scope="col">Terakhir aktif</th><th scope="col">Berakhir</th><th scope="col"><span class="sr-only">Tindakan</span></th></tr></thead>
				<tbody>
				<?php foreach ($sessions as $s): ?>
					<tr>
						<td><?= e($s->device_label) ?><?= ((int) $s->id === $current_session_id) ? ' <span class="chip-flag is-info">sesi ini</span>' : '' ?></td>
						<td><?= $s->area === 'admin' ? 'Pengelola' : 'Warga' ?></td>
						<td><?= e(format_wib($s->last_seen_at)) ?></td>
						<td><?= e(format_wib($s->expires_at)) ?></td>
						<td class="text-right">
							<?php if ((int) $s->id !== $current_session_id): ?>
							<form method="post" action="<?= site_url($base.'/sesi') ?>">
								<?= csrf_field() ?>
								<input type="hidden" name="session_id" value="<?= (int) $s->id ?>">
								<button class="btn btn-link btn-sm" type="submit">Keluarkan</button>
							</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="small text-muted mt-3 mb-0">Sesi berakhir otomatis setelah periode tidak aktif dan memiliki batas waktu maksimum. Mengganti password akan mengeluarkan sesi lain.</p>
	</div>
</div>
