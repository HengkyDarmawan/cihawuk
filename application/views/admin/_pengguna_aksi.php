<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Aksi cepat satu pengguna: ganti role dan login sebagai.
 * Variabel: $u (id, public_id, display_name, account_status, role_codes), $role_options (kode => nama),
 * $return_uri (URI tujuan setelah simpan), $part ('role' | 'actions').
 */
$u_base = site_url('admin/pengguna/'.rawurlencode($u->public_id));
$is_self = (int) $u->id === (int) $user->id;
$current_role = $u->role_codes[0] ?? '';
?>
<?php if ($part === 'role'): ?>
	<?php if (in_array('users.assign_roles', $permissions, TRUE) && ! $is_self && empty($impersonator)): ?>
	<form method="post" action="<?= $u_base ?>/role" class="m-0" data-confirm="Ubah role <?= e($u->display_name) ?>? Sesi pengguna tersebut akan dikeluarkan." data-confirm-ok="Ubah role">
		<?= csrf_field() ?>
		<input type="hidden" name="operation" value="set">
		<input type="hidden" name="kembali" value="<?= e($return_uri) ?>">
		<select class="custom-select custom-select-sm role-select" name="role_code" aria-label="Role <?= e($u->display_name) ?>" data-autosubmit data-original="<?= e($current_role) ?>">
			<?php if ($current_role === ''): ?><option value="" selected disabled>Belum ada role</option><?php endif; ?>
			<?php foreach ($role_options as $code => $name): ?>
				<option value="<?= e($code) ?>" <?= $code === $current_role ? 'selected' : '' ?>><?= e($name) ?></option>
			<?php endforeach; ?>
		</select>
		<?php if (count($u->role_codes) > 1): ?><div class="small text-warning mt-1">Memegang <?= count($u->role_codes) ?> role; memilih satu role akan merapikannya.</div><?php endif; ?>
		<noscript><button class="btn btn-sm btn-outline-primary mt-1" type="submit">Simpan</button></noscript>
	</form>
	<?php else: ?>
		<?php foreach ($u->role_codes as $code): ?><span class="chip-flag"><?= e($role_options[$code] ?? $code) ?></span> <?php endforeach; ?>
		<?php if (empty($u->role_codes)): ?><span class="text-muted small">Belum ada role</span><?php endif; ?>
	<?php endif; ?>
<?php else: ?>
	<div class="d-flex flex-wrap justify-content-end" style="gap:.35rem">
		<?php if (in_array('users.impersonate', $permissions, TRUE) && empty($impersonator) && ! $is_self
			&& $u->account_status === 'active' && ! in_array('super_admin', $u->role_codes, TRUE)): ?>
		<form method="post" action="<?= $u_base ?>/login-sebagai" class="m-0" data-confirm="Login sebagai <?= e($u->display_name) ?>? Anda akan melihat aplikasi persis seperti pengguna ini selama paling lama <?= (int) AuthService::IMPERSONATION_TTL ?> menit. Semua tindakan tercatat atas nama Anda." data-confirm-ok="Login sebagai">
			<?= csrf_field() ?>
			<button class="btn btn-warning btn-sm" type="submit"><i class="fas fa-user-secret fa-sm mr-1" aria-hidden="true"></i>Login sebagai</button>
		</form>
		<?php endif; ?>
		<a class="btn btn-outline-secondary btn-sm" href="<?= $u_base ?>">Detail</a>
	</div>
<?php endif; ?>
