<?php defined('BASEPATH') OR exit('No direct script access allowed');
$is_new = ($role === NULL);
$action = $is_new ? site_url('admin/rbac/role/baru') : site_url('admin/rbac/role/'.rawurlencode($role->code));
$locked_staff = ! $is_new && in_array($role->code, RbacService::PROTECTED_ROLES, TRUE);
$is_super = ! $is_new && $role->code === 'super_admin';
$active_tab = $is_new ? 'akses' : ($active_tab ?? 'akses');
$can_assign = in_array('users.assign_roles', $permissions, TRUE) && empty($impersonator);
?>
<div class="page-heading">
	<div>
		<h1><?= $is_new ? 'Tambah role' : e($role->name) ?></h1>
		<p><?= $is_new ? 'Role baru belum dipegang siapa pun.' : e((string) $role->description) ?></p>
	</div>
	<div><a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/pengguna'.($is_new ? '' : '?role='.rawurlencode($role->code))) ?>"><i class="fas fa-arrow-left fa-sm mr-1" aria-hidden="true"></i> Pengguna &amp; Akses</a></div>
</div>

<?= ui_error_summary() ?>

<?php if ( ! $is_new): ?>
<ul class="nav nav-tabs mb-4" role="tablist">
	<li class="nav-item"><a class="nav-link<?= $active_tab === 'akses' ? ' active' : '' ?>" id="tab-akses" data-toggle="tab" href="#panel-akses" role="tab" aria-controls="panel-akses" aria-selected="<?= $active_tab === 'akses' ? 'true' : 'false' ?>"><i class="fas fa-key fa-sm mr-1" aria-hidden="true"></i> Akses</a></li>
	<li class="nav-item"><a class="nav-link<?= $active_tab === 'anggota' ? ' active' : '' ?>" id="tab-anggota" data-toggle="tab" href="#panel-anggota" role="tab" aria-controls="panel-anggota" aria-selected="<?= $active_tab === 'anggota' ? 'true' : 'false' ?>"><i class="fas fa-users fa-sm mr-1" aria-hidden="true"></i> Anggota <span class="badge badge-secondary ml-1"><?= (int) $user_count ?></span></a></li>
</ul>
<?php endif; ?>

<div class="tab-content">
<div class="tab-pane fade<?= $active_tab === 'akses' ? ' show active' : '' ?>" id="panel-akses" role="tabpanel" aria-labelledby="tab-akses">
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
				<label class="form-check-label" for="is_staff">Boleh membuka dashboard pengelola (/admin)</label>
			</div>
		</div>
	</div>

	<div class="card shadow-sm mb-4">
		<div class="card-header d-flex justify-content-between align-items-center">
			<h2 class="h6 mb-0">Akses per modul</h2>
			<?php if ( ! $is_super): ?><span class="small text-muted">Centang "Semua" untuk memberi seluruh akses satu modul.</span><?php endif; ?>
		</div>
		<div class="card-body">
			<?php if ($is_super): ?>
				<div class="alert alert-success mb-0">
					<i class="fas fa-circle-check fa-check-circle mr-1" aria-hidden="true"></i>
					<strong>Super Admin otomatis memegang semua akses</strong>, termasuk modul yang ditambahkan kemudian. Tidak ada yang perlu dicentang.
				</div>
			<?php else: ?>
			<div class="row">
			<?php $g = 0; foreach ($groups as $label => $perms): $g++; $gid = 'grp-'.$g; ?>
				<div class="col-md-6 col-xl-4 mb-3">
					<fieldset class="perm-group h-100" data-check-group>
						<legend class="perm-group-head">
							<span class="custom-control custom-checkbox">
								<input class="custom-control-input" type="checkbox" id="<?= $gid ?>" data-check-all>
								<label class="custom-control-label font-weight-bold" for="<?= $gid ?>"><?= e($label) ?> <span class="text-muted font-weight-normal small">(semua)</span></label>
							</span>
							<span class="badge badge-light" data-check-count></span>
						</legend>
						<?php foreach ($perms as $perm): $id = 'perm-'.(int) $perm->id; ?>
						<div class="custom-control custom-checkbox mb-1">
							<input class="custom-control-input" type="checkbox" id="<?= $id ?>" name="permissions[]" value="<?= e($perm->code) ?>" data-check-item <?= in_array((int) $perm->id, $held, TRUE) ? 'checked' : '' ?>>
							<label class="custom-control-label" for="<?= $id ?>">
								<?= e($perm->description) ?>
								<span class="d-block small text-muted"><code class="text-muted"><?= e($perm->code) ?></code><?php if ((int) $perm->is_custom === 1): ?> · buatan<?php endif; ?></span>
							</label>
						</div>
						<?php endforeach; ?>
					</fieldset>
				</div>
			<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<button class="btn btn-primary" type="submit"><i class="fas fa-save fa-sm mr-1" aria-hidden="true"></i> <?= $is_new ? 'Buat role' : 'Simpan akses role' ?></button>
</form>
</div>

<?php if ( ! $is_new): ?>
<div class="tab-pane fade<?= $active_tab === 'anggota' ? ' show active' : '' ?>" id="panel-anggota" role="tabpanel" aria-labelledby="tab-anggota">
	<?php if ($can_assign && ! empty($candidates)): ?>
	<form method="post" action="<?= site_url('admin/rbac/role/'.rawurlencode($role->code).'/anggota') ?>" class="card shadow-sm mb-4" data-confirm="Jadikan pengguna ini <?= e($role->name) ?>? Role lamanya diganti dan sesinya dikeluarkan." data-confirm-ok="Tambahkan">
		<div class="card-body form-row align-items-end">
			<?= csrf_field() ?>
			<div class="col-md-9"><?= ui_select(array('name' => 'user_public_id', 'label' => 'Tambahkan pengguna ke role ini', 'options' => $candidates, 'placeholder_option' => 'Pilih pengguna…', 'value' => '', 'required' => TRUE, 'wrap_class' => 'mb-md-0')) ?></div>
			<div class="col-md-3"><button class="btn btn-primary btn-block" type="submit"><i class="fas fa-user-plus fa-sm mr-1" aria-hidden="true"></i> Tambahkan</button></div>
		</div>
	</form>
	<?php endif; ?>

	<div class="card shadow-sm">
		<div class="card-body">
			<?php if (empty($members)): ?>
				<div class="empty-box"><i class="fas fa-users" aria-hidden="true"></i><p class="mb-0">Belum ada pengguna dengan role ini.</p></div>
			<?php else: ?>
			<div class="table-responsive">
				<table class="table table-hover mb-0">
					<caption class="sr-only">Anggota role <?= e($role->name) ?></caption>
					<thead><tr><th scope="col">Nama</th><th scope="col" style="min-width:12rem">Role</th><th scope="col">Terakhir masuk</th><th scope="col" class="text-right">Aksi</th></tr></thead>
					<tbody>
					<?php foreach ($members as $u): $return_uri = 'admin/rbac/role/'.$role->code.'?tab=anggota'; ?>
						<tr>
							<td><strong><?= e($u->display_name) ?></strong><div class="small text-muted"><?= e($u->username) ?></div></td>
							<td><?php $this->load->view('admin/_pengguna_aksi', array('u' => $u, 'role_options' => $role_options, 'return_uri' => $return_uri, 'part' => 'role')); ?></td>
							<td class="small"><?= e($u->last_login_at ? format_wib($u->last_login_at, 'short') : 'belum pernah') ?></td>
							<td class="text-right"><?php $this->load->view('admin/_pengguna_aksi', array('u' => $u, 'role_options' => $role_options, 'return_uri' => $return_uri, 'part' => 'actions')); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>
		</div>
	</div>
</div>
<?php endif; ?>
</div>

<?php $this->load->view('admin/rbac_tabs', array('tab' => '')); ?>

<?php if ( ! $is_new && (int) $role->is_system !== 1): ?>
<form method="post" action="<?= site_url('admin/rbac/role/'.rawurlencode($role->code).'/hapus') ?>" class="mt-3" data-confirm="Hapus role <?= e($role->name) ?>? Tindakan ini tidak dapat dibatalkan." data-confirm-ok="Hapus role">
	<?= csrf_field() ?>
	<button class="btn btn-outline-danger btn-sm" type="submit"<?= (int) $user_count > 0 ? ' disabled title="Masih dipakai pengguna"' : '' ?>>Hapus role</button>
	<?php if ((int) $user_count > 0): ?><span class="small text-muted ml-2">Pindahkan <?= (int) $user_count ?> pengguna ke role lain dulu sebelum menghapus.</span><?php endif; ?>
</form>
<?php endif; ?>
