<?php defined('BASEPATH') OR exit('No direct script access allowed');
$is_new = ($role === NULL);
$action = $is_new ? site_url('admin/rbac/role/baru') : site_url('admin/rbac/role/'.rawurlencode($role->code));
$locked_staff = ! $is_new && in_array($role->code, RbacService::PROTECTED_ROLES, TRUE);
$required = ( ! $is_new && $role->code === 'super_admin') ? RbacService::SUPER_ADMIN_REQUIRED : array();
?>
<div class="page-heading">
	<div>
		<h1><?= $is_new ? 'Tambah role' : e($role->name) ?></h1>
		<p><?= $is_new ? 'Role baru belum dipegang siapa pun. Berikan ke pengguna dari halaman Pengguna.' : 'Kode <code>'.e($role->code).'</code> · dipakai '.(int) $user_count.' pengguna' ?></p>
	</div>
	<div><a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/rbac') ?>">Kembali</a></div>
</div>

<?php $this->load->view('admin/rbac_tabs', array('tab' => $tab)); ?>
<?= ui_error_summary() ?>

<form method="post" action="<?= $action ?>" data-once>
	<?= csrf_field() ?>
	<div class="card shadow-sm mb-4">
		<div class="card-header"><h2 class="h6 mb-0">Data role</h2></div>
		<div class="card-body">
			<div class="form-row">
				<?php if ($is_new): ?>
				<div class="col-md-3"><?= ui_input(array('name' => 'code', 'label' => 'Kode', 'required' => TRUE, 'maxlength' => 40, 'pattern' => '[a-z][a-z0-9_]{2,39}', 'help' => 'Huruf kecil, angka, garis bawah. Tidak dapat diubah.')) ?></div>
				<?php endif; ?>
				<div class="col-md-4"><?= ui_input(array('name' => 'name', 'label' => 'Nama role', 'required' => TRUE, 'maxlength' => 100, 'value' => $is_new ? old('name') : $role->name)) ?></div>
				<div class="col-md-5"><?= ui_input(array('name' => 'description', 'label' => 'Deskripsi', 'maxlength' => 255, 'value' => $is_new ? old('description') : (string) $role->description)) ?></div>
			</div>
			<div class="form-check">
				<?php if ($locked_staff): ?><input type="hidden" name="is_staff" value="<?= (int) $role->is_staff ?>"><?php endif; ?>
				<input class="form-check-input" type="checkbox" id="is_staff" name="is_staff" value="1" <?= ($is_new || (int) $role->is_staff === 1) ? 'checked' : '' ?> <?= $locked_staff ? 'disabled' : '' ?>>
				<label class="form-check-label" for="is_staff">Role pengelola (boleh membuka dashboard /admin)</label>
			</div>
		</div>
	</div>

	<div class="card shadow-sm mb-4">
		<div class="card-header d-flex justify-content-between align-items-center">
			<h2 class="h6 mb-0">Izin</h2>
			<span class="small text-muted">Centang izin yang dipegang role ini.</span>
		</div>
		<div class="card-body">
			<?php if ($required): ?>
				<div class="alert alert-info small">Super Admin wajib tetap memegang <code><?= e(implode('</code>, <code>', $required)) ?></code> supaya selalu ada yang dapat mengelola akses.</div>
			<?php endif; ?>
			<div class="row">
			<?php foreach ($groups as $prefix => $perms): ?>
				<fieldset class="col-md-6 col-xl-4 mb-3">
					<legend class="h6 text-uppercase small font-weight-bold text-muted mb-2"><?= e($prefix) ?></legend>
					<?php foreach ($perms as $perm): $id = 'perm-'.(int) $perm->id; ?>
					<div class="form-check mb-1">
						<input class="form-check-input" type="checkbox" id="<?= $id ?>" name="permissions[]" value="<?= e($perm->code) ?>" <?= in_array((int) $perm->id, $held, TRUE) ? 'checked' : '' ?>>
						<label class="form-check-label" for="<?= $id ?>">
							<code class="small"><?= e($perm->code) ?></code><?php if ((int) $perm->is_custom === 1): ?> <span class="chip-flag">buatan</span><?php endif; ?>
							<span class="d-block small text-muted"><?= e($perm->description) ?></span>
						</label>
					</div>
					<?php endforeach; ?>
				</fieldset>
			<?php endforeach; ?>
			</div>
		</div>
	</div>

	<button class="btn btn-primary" type="submit"><?= $is_new ? 'Buat role' : 'Simpan role dan izin' ?></button>
</form>

<?php if ( ! $is_new && (int) $role->is_system !== 1): ?>
<form method="post" action="<?= site_url('admin/rbac/role/'.rawurlencode($role->code).'/hapus') ?>" class="mt-4" data-confirm="Hapus role <?= e($role->name) ?>? Tindakan ini tidak dapat dibatalkan." data-confirm-ok="Hapus role">
	<?= csrf_field() ?>
	<button class="btn btn-outline-danger btn-sm" type="submit"<?= (int) $user_count > 0 ? ' disabled title="Masih dipakai pengguna"' : '' ?>>Hapus role</button>
	<?php if ((int) $user_count > 0): ?><span class="small text-muted ml-2">Cabut role dari <?= (int) $user_count ?> pengguna dulu sebelum menghapus.</span><?php endif; ?>
</form>
<?php endif; ?>
