<?php defined('BASEPATH') OR exit('No direct script access allowed');
$query = function (array $params) use ($filters, $page) {
	$base = array_merge($filters, array('hal' => $page), $params);
	return site_url('admin/pengguna').'?'.http_build_query(array_filter($base, function ($v) { return $v !== '' && $v !== NULL; }));
};
// Urutan tab: empat role utama dulu, role buatan pengelola sesudahnya.
$rank = array('super_admin' => 0, 'admin_desa' => 1, 'petugas' => 2, 'resident' => 3);
usort($roles, function ($a, $b) use ($rank) {
	return array(($rank[$a->code] ?? 9), $a->name) <=> array(($rank[$b->code] ?? 9), $b->name);
});
$role_options = array();
foreach ($roles as $r) { $role_options[$r->code] = $r->name; }
$can_manage_roles = in_array('roles.manage', $permissions, TRUE);
?>
<div class="page-heading">
	<div>
		<h1>Pengguna &amp; Akses</h1>
		<p>Pilih tab role untuk melihat anggotanya dan mengatur aksesnya. Role pengguna dapat diganti langsung dari tabel.</p>
	</div>
	<?php if (in_array('users.create_resident', $permissions, TRUE)): ?>
	<div class="page-actions">
		<a class="btn btn-primary btn-add" href="<?= site_url('admin/pengguna/buat') ?>"><i class="fas fa-plus mr-1" aria-hidden="true"></i> Daftarkan warga</a>
	</div>
	<?php endif; ?>
</div>

<ul class="nav nav-pills role-tabs mb-3" aria-label="Filter role">
	<li class="nav-item">
		<a class="nav-link<?= $filters['role'] === '' ? ' active' : '' ?>" href="<?= $query(array('role' => '', 'hal' => 1)) ?>">Semua <span class="badge badge-light ml-1"><?= (int) $all_count ?></span></a>
	</li>
	<?php foreach ($roles as $r): ?>
	<li class="nav-item">
		<a class="nav-link<?= $filters['role'] === $r->code ? ' active' : '' ?>" href="<?= $query(array('role' => $r->code, 'hal' => 1)) ?>"<?= $filters['role'] === $r->code ? ' aria-current="page"' : '' ?>>
			<?= e($r->name) ?> <span class="badge badge-light ml-1"><?= (int) $r->user_count ?></span>
		</a>
	</li>
	<?php endforeach; ?>
</ul>

<?php if ($active_role): ?>
<div class="card shadow-sm mb-4 border-left-primary">
	<div class="card-body d-md-flex justify-content-between align-items-start">
		<div class="mr-md-4">
			<h2 class="h5 mb-1"><?= e($active_role->name) ?></h2>
			<p class="text-muted mb-2"><?= e((string) $active_role->description) ?></p>
			<div class="small">
				<strong>Bisa mengakses:</strong>
				<?php foreach ($active_role->modules as $module): ?><span class="chip-flag is-info"><?= e($module) ?></span> <?php endforeach; ?>
				<?php if (empty($active_role->modules)): ?><span class="text-muted">hanya fitur akun sendiri</span><?php endif; ?>
			</div>
		</div>
		<?php if ($can_manage_roles): ?>
		<a class="btn btn-primary mt-3 mt-md-0 text-nowrap" href="<?= site_url('admin/rbac/role/'.rawurlencode($active_role->code)) ?>"><i class="fas fa-user-shield mr-1" aria-hidden="true"></i> Atur akses role ini</a>
		<?php endif; ?>
	</div>
</div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
	<div class="card-body py-3">
		<form method="get" action="<?= site_url('admin/pengguna') ?>" class="form-row align-items-end">
			<input type="hidden" name="role" value="<?= e($filters['role']) ?>">
			<div class="col-md-6 mb-2">
				<label for="q">Cari nama / username</label>
				<input class="form-control" type="search" id="q" name="q" maxlength="60" value="<?= e($filters['q']) ?>">
			</div>
			<div class="col-md-4 mb-2">
				<label for="status">Status akun</label>
				<select class="form-control" id="status" name="status">
					<option value="">Semua status</option>
					<?php foreach ($statuses as $code => $label): ?>
						<option value="<?= e($code) ?>" <?= $filters['status'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-2 mb-2">
				<button class="btn btn-primary btn-block" type="submit">Cari</button>
			</div>
		</form>
	</div>
</div>

<div class="card shadow-sm">
	<div class="card-body">
		<p class="small text-muted"><?= (int) $total ?> akun ditemukan.</p>
		<?php if (empty($users)): ?>
			<div class="empty-box"><i class="fas fa-users" aria-hidden="true"></i><p class="mb-0">Tidak ada akun yang cocok.</p></div>
		<?php else: ?>
		<div class="table-responsive">
			<table class="table table-hover mb-0 align-middle">
				<caption class="sr-only">Daftar akun pengguna</caption>
				<thead><tr><th scope="col">Nama</th><th scope="col" style="min-width:12rem">Role</th><th scope="col">Status</th><th scope="col">Terakhir masuk</th><th scope="col" class="text-right">Aksi</th></tr></thead>
				<tbody>
				<?php foreach ($users as $u): ?>
					<tr>
						<td>
							<a class="font-weight-bold" href="<?= site_url('admin/pengguna/'.rawurlencode($u->public_id)) ?>"><?= e($u->display_name) ?></a>
							<div class="small text-muted"><?= e($u->username) ?><?= $u->email ? ' · '.e($u->email) : '' ?></div>
						</td>
						<td><?php $this->load->view('admin/_pengguna_aksi', array('u' => $u, 'role_options' => $role_options, 'return_uri' => $current_uri, 'part' => 'role')); ?></td>
						<td><span class="chip-flag <?= $u->account_status === 'active' ? 'is-info' : ($u->account_status === 'pending_activation' ? 'is-warning' : 'is-danger') ?>"><?= e(config_label('account_statuses', $u->account_status)) ?></span></td>
						<td class="small"><?= e($u->last_login_at ? format_wib($u->last_login_at, 'short') : 'belum pernah') ?></td>
						<td class="text-right"><?php $this->load->view('admin/_pengguna_aksi', array('u' => $u, 'role_options' => $role_options, 'return_uri' => $current_uri, 'part' => 'actions')); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>
	</div>
	<?php if ($pages > 1): ?>
	<div class="card-footer bg-white">
		<nav aria-label="Halaman pengguna">
			<ul class="pagination mb-0 justify-content-center">
				<li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $query(array('hal' => $page - 1)) ?>">Sebelumnya</a></li>
				<li class="page-item disabled"><span class="page-link">Halaman <?= (int) $page ?> dari <?= (int) $pages ?></span></li>
				<li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $query(array('hal' => $page + 1)) ?>">Berikutnya</a></li>
			</ul>
		</nav>
	</div>
	<?php endif; ?>
</div>
