<?php defined('BASEPATH') OR exit('No direct script access allowed');
$base = site_url('admin/pengguna/'.rawurlencode($account->public_id));
$can = function ($p) use ($permissions) { return in_array($p, $permissions, TRUE); };
$has_role = function ($code) use ($user_roles) {
	foreach ($user_roles as $r) { if ($r->code === $code) { return TRUE; } }
	return FALSE;
};
?>
<div class="page-heading">
	<div>
		<h1><?= e($account->display_name) ?></h1>
		<p><?= e($account->username) ?><?= $account->email ? ' · '.e($account->email) : '' ?> · dibuat <?= e(format_wib($account->created_at, 'date')) ?></p>
	</div>
	<div class="text-right">
		<span class="chip-flag <?= $account->account_status === 'active' ? 'is-info' : 'is-warning' ?>"><?= e(config_label('account_statuses', $account->account_status)) ?></span>
		<?php if ($mfa_enabled): ?><span class="chip-flag is-info">MFA aktif</span><?php endif; ?>
		<div class="mt-2 d-flex justify-content-end flex-wrap" style="gap:.5rem">
			<?php if ($can('users.impersonate') && empty($impersonator) && (int) $account->id !== (int) $user->id
				&& $account->account_status === 'active' && ! $has_role('super_admin')): ?>
			<form method="post" action="<?= $base ?>/login-sebagai" data-confirm="Login sebagai <?= e($account->display_name) ?>? Anda akan melihat dashboard persis seperti pengguna ini selama paling lama <?= (int) AuthService::IMPERSONATION_TTL ?> menit. Semua tindakan tercatat atas nama Anda di log audit." data-confirm-ok="Login sebagai">
				<?= csrf_field() ?>
				<button class="btn btn-warning btn-sm" type="submit"><i class="fas fa-user-secret fa-sm mr-1" aria-hidden="true"></i> Login sebagai</button>
			</form>
			<?php endif; ?>
			<a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/pengguna') ?>">Kembali</a>
		</div>
	</div>
</div>

<?php if (is_array($activation_receipt)): ?>
<div class="card shadow-sm mb-4 border-left-accent">
	<div class="card-body">
		<h2 class="h6">Kode aktivasi (tampil sekali)</h2>
		<p class="small">Serahkan kode ini kepada warga secara langsung. Kode berlaku sampai <?= e(format_wib($activation_receipt['expires_at'])) ?> dan hanya dapat dipakai satu kali.</p>
		<div class="code-display" id="activation-code"><?= e($activation_receipt['token']) ?></div>
		<button class="btn btn-outline-primary btn-sm mt-2" type="button" data-copy-target="activation-code">Salin kode</button>
		<p class="small text-muted mt-2 mb-0">Tautan aktivasi: <?= e($activation_receipt['url']) ?></p>
	</div>
</div>
<?php endif; ?>

<?php if (is_array($recovery_receipt)): ?>
<div class="card shadow-sm mb-4 border-left-accent">
	<div class="card-body">
		<h2 class="h6">Kode pemulihan (tampil sekali)</h2>
		<p class="small">Kode <?= $recovery_receipt['purpose'] === 'activation' ? 'aktivasi' : 'reset password' ?> sekali pakai. Serahkan langsung kepada pemilik akun setelah pemeriksaan identitas.</p>
		<div class="code-display" id="recovery-code"><?= e($recovery_receipt['token']) ?></div>
		<button class="btn btn-outline-primary btn-sm mt-2" type="button" data-copy-target="recovery-code">Salin kode</button>
	</div>
</div>
<?php endif; ?>

<div class="row">
	<div class="col-lg-6 mb-4">
		<div class="card shadow-sm h-100">
			<div class="card-header"><h2>Informasi akun</h2></div>
			<div class="card-body">
				<dl class="dl-grid mb-0">
					<dt>ID publik</dt><dd><code><?= e($account->public_id) ?></code></dd>
					<dt>Status</dt><dd><?= e(config_label('account_statuses', $account->account_status)) ?><?= $account->status_reason ? ' — '.e($account->status_reason) : '' ?></dd>
					<dt>Email</dt><dd><?= $account->email ? e($account->email).($account->email_verified_at ? ' <span class="chip-flag is-info">terverifikasi</span>' : ' <span class="chip-flag">belum</span>') : '—' ?></dd>
					<dt>Telepon</dt><dd><?= $account->phone ? e($account->phone) : '—' ?></dd>
					<dt>Kanal pendaftaran</dt><dd><?= e($account->registration_channel) ?></dd>
					<dt>Terakhir masuk</dt><dd><?= e($account->last_login_at ? format_wib($account->last_login_at) : 'belum pernah') ?></dd>
					<dt>Sesi aktif</dt><dd><?= count($sessions) ?></dd>
					<?php if ($profile): ?>
						<dt>Verifikasi warga</dt><dd><?= e(config_label('resident_verification_statuses', $profile->verification_status)) ?></dd>
						<dt>Alamat</dt><dd><?= $profile->address ? e($profile->address) : '—' ?></dd>
					<?php endif; ?>
				</dl>
			</div>
		</div>
	</div>

	<div class="col-lg-6 mb-4">
		<div class="card shadow-sm h-100">
			<div class="card-header"><h2>Role &amp; lingkup</h2></div>
			<div class="card-body">
				<p class="mb-2">
					<?php foreach ($user_roles as $r): ?><span class="chip-flag is-info"><?= e($r->name) ?></span> <?php endforeach; ?>
					<?php if (empty($user_roles)): ?><span class="text-muted">Belum ada role.</span><?php endif; ?>
				</p>
				<?php if ($is_last_super_admin): ?>
					<div class="alert alert-warning small">Ini adalah Super Admin aktif terakhir. Role dan status akun tidak dapat dicabut sebelum ada penggantinya.</div>
				<?php endif; ?>

				<?php if ( ! empty($access_modules)): ?>
				<p class="small mb-3"><strong>Bisa mengakses:</strong>
					<?php foreach ($access_modules as $module): ?><span class="chip-flag is-info"><?= e($module) ?></span> <?php endforeach; ?>
				</p>
				<?php endif; ?>

				<?php if ($can('users.assign_roles')): ?>
				<?php if ((int) $account->id !== (int) $user->id): ?>
				<form method="post" action="<?= $base ?>/role" class="mb-3" data-confirm="Ubah role akun ini? Seluruh sesi pengguna tersebut akan dikeluarkan." data-confirm-ok="Ubah role">
					<?= csrf_field() ?>
					<input type="hidden" name="operation" value="set">
					<fieldset>
						<legend class="h6">Ganti role</legend>
						<?php $current_codes = array_map(function ($r) { return $r->code; }, $user_roles); ?>
						<?php foreach ($assignable_roles as $code => $name): $rid = 'role-'.$code; ?>
						<div class="custom-control custom-radio mb-1">
							<input class="custom-control-input" type="radio" id="<?= e($rid) ?>" name="role_code" value="<?= e($code) ?>" <?= $current_codes === array($code) ? 'checked' : '' ?> required>
							<label class="custom-control-label" for="<?= e($rid) ?>"><?= e($name) ?><?php if ( ! empty($role_descriptions[$code])): ?><span class="d-block small text-muted"><?= e($role_descriptions[$code]) ?></span><?php endif; ?></label>
						</div>
						<?php endforeach; ?>
					</fieldset>
					<button class="btn btn-primary btn-sm mt-2" type="submit">Simpan role</button>
				</form>
				<?php else: ?>
					<p class="small text-muted">Role akun Anda sendiri hanya dapat diubah oleh Super Admin lain.</p>
				<?php endif; ?>

				<details class="mt-3">
				<summary class="small font-weight-bold text-muted">Pengaturan lanjutan: lingkup unit</summary>
				<p class="small text-muted mt-2">Hanya diperlukan untuk role kustom yang memantau laporan per unit.</p>
				<h3 class="h6">Lingkup unit</h3>
				<ul class="list-unstyled small">
					<?php foreach ($scopes as $s): ?>
					<li class="d-flex justify-content-between border-bottom py-1">
						<span><?= e($s->unit_name) ?> · <?= e($s->scope_type) ?></span>
						<form method="post" action="<?= $base ?>/lingkup">
							<?= csrf_field() ?>
							<input type="hidden" name="unit_id" value="<?= (int) $s->unit_id ?>">
							<input type="hidden" name="scope_type" value="<?= e($s->scope_type) ?>">
							<input type="hidden" name="operation" value="remove">
							<button class="btn btn-link btn-sm p-0" type="submit">Hapus</button>
						</form>
					</li>
					<?php endforeach; ?>
					<?php if (empty($scopes)): ?><li class="text-muted">Belum ada lingkup unit.</li><?php endif; ?>
				</ul>
				<form method="post" action="<?= $base ?>/lingkup" class="form-row align-items-end">
					<?= csrf_field() ?>
					<input type="hidden" name="operation" value="add">
					<div class="col-6"><?= ui_select(array('name' => 'unit_id', 'label' => 'Unit', 'required' => TRUE, 'options' => $units, 'placeholder_option' => 'Pilih unit…', 'value' => '', 'wrap_class' => 'mb-2')) ?></div>
					<div class="col-3"><?= ui_select(array('name' => 'scope_type', 'label' => 'Jenis', 'options' => array('member' => 'Anggota', 'monitor' => 'Pemantau', 'all' => 'Seluruh unit'), 'value' => 'member', 'wrap_class' => 'mb-2')) ?></div>
					<div class="col-3 mb-2"><button class="btn btn-outline-primary btn-block" type="submit">Tambah</button></div>
				</form>
				</details>
				<?php else: ?>
					<p class="small text-muted mb-0">Anda tidak memiliki izin mengubah role (<code>users.assign_roles</code>).</p>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<?php if ($can('residents.verify')): ?>
	<div class="col-lg-6 mb-4">
		<div class="card shadow-sm h-100">
			<div class="card-header"><h2>Aktivasi &amp; verifikasi warga</h2></div>
			<div class="card-body">
				<?php if ($account->account_status === 'pending_activation'): ?>
				<form method="post" action="<?= $base ?>/aktivasi" class="mb-3">
					<?= csrf_field() ?>
					<?= ui_input(array('name' => 'note', 'label' => 'Catatan review', 'maxlength' => 255, 'value' => '',
						'help' => $account->password_hash === NULL ? 'Akun belum punya password: tombol ini menerbitkan kode aktivasi baru.' : 'Akun sudah punya password: tombol ini mengaktifkan akun setelah review.')) ?>
					<button class="btn btn-primary" type="submit"><?= $account->password_hash === NULL ? 'Terbitkan kode aktivasi' : 'Aktifkan akun' ?></button>
				</form>
				<?php endif; ?>
				<?php if ($profile): ?>
				<form method="post" action="<?= $base ?>/verifikasi" class="form-row align-items-end">
					<?= csrf_field() ?>
					<div class="col-6"><?= ui_select(array('name' => 'verification_status', 'label' => 'Status verifikasi', 'options' => app_config('resident_verification_statuses'), 'value' => $profile->verification_status, 'wrap_class' => 'mb-2')) ?></div>
					<div class="col-6"><?= ui_input(array('name' => 'reason', 'label' => 'Catatan', 'maxlength' => 255, 'value' => '', 'wrap_class' => 'mb-2')) ?></div>
					<div class="col-12"><button class="btn btn-outline-primary btn-sm" type="submit">Simpan status verifikasi</button></div>
				</form>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<?php if ($can('users.manage')): ?>
	<div class="col-lg-6 mb-4">
		<div class="card shadow-sm h-100">
			<div class="card-header"><h2>Tindakan administratif</h2></div>
			<div class="card-body">
				<form method="post" action="<?= $base ?>/status" class="form-row align-items-end mb-3" data-confirm="Ubah status akun ini?" data-confirm-ok="Ubah status">
					<?= csrf_field() ?>
					<div class="col-5"><?= ui_select(array('name' => 'account_status', 'label' => 'Status akun', 'options' => $statuses, 'value' => $account->account_status, 'wrap_class' => 'mb-2')) ?></div>
					<div class="col-5"><?= ui_input(array('name' => 'reason', 'label' => 'Alasan', 'required' => TRUE, 'maxlength' => 255, 'value' => '', 'wrap_class' => 'mb-2')) ?></div>
					<div class="col-2 mb-2"><button class="btn btn-primary btn-block" type="submit">Simpan</button></div>
				</form>

				<form method="post" action="<?= $base ?>/pemulihan" class="mb-3" data-confirm="Terbitkan kode pemulihan sekali pakai untuk akun ini?" data-confirm-ok="Terbitkan kode">
					<?= csrf_field() ?>
					<?= ui_textarea(array('name' => 'reason', 'label' => 'Dasar pemeriksaan identitas', 'required' => TRUE, 'rows' => 2, 'maxlength' => 500, 'value' => '',
						'help' => 'Mengetahui nama, tanggal lahir, atau NIK saja tidak cukup. Catat prosedur yang dijalankan.')) ?>
					<button class="btn btn-outline-primary btn-sm" type="submit">Terbitkan kode pemulihan</button>
				</form>

				<div class="d-flex flex-wrap gap-2">
					<form method="post" action="<?= $base ?>/sesi" class="mr-2" data-confirm="Keluarkan semua sesi pengguna ini?" data-confirm-ok="Keluarkan">
						<?= csrf_field() ?>
						<button class="btn btn-outline-danger btn-sm" type="submit">Keluarkan semua sesi</button>
					</form>
					<?php if ($mfa_enabled): ?>
					<form method="post" action="<?= $base ?>/mfa-reset" data-confirm="Reset MFA akun ini setelah pemeriksaan identitas?" data-confirm-ok="Reset MFA">
						<?= csrf_field() ?>
						<input type="hidden" name="reason" value="Reset MFA setelah pemeriksaan identitas di kantor desa">
						<button class="btn btn-outline-danger btn-sm" type="submit">Reset MFA</button>
					</form>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
	<?php endif; ?>
</div>

<?php if ( ! empty($audit)): ?>
<div class="card shadow-sm">
	<div class="card-header"><h2>Audit akun terakhir</h2></div>
	<div class="card-body">
		<div class="table-responsive">
			<table class="table table-sm mb-0">
				<caption class="sr-only">Entri audit terkait akun ini</caption>
				<thead><tr><th scope="col">Waktu</th><th scope="col">Aksi</th><th scope="col">Metadata aman</th></tr></thead>
				<tbody>
				<?php foreach ($audit as $row): ?>
					<tr>
						<td class="small"><?= e(format_wib($row->created_at, 'short')) ?></td>
						<td class="small"><code><?= e($row->action) ?></code></td>
						<td class="small"><?= e(str_limit_id($row->safe_metadata_json, 120)) ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
<?php endif; ?>
