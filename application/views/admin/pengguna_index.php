<?php defined('BASEPATH') OR exit('No direct script access allowed');
$query = function (array $params) use ($filters, $page) {
	$base = array_merge($filters, array('hal' => $page), $params);
	return site_url('admin/pengguna').'?'.http_build_query(array_filter($base, function ($v) { return $v !== '' && $v !== NULL; }));
};
?>
<div class="page-heading">
	<div>
		<h1>Pengguna</h1>
		<p><?= (int) $total ?> akun terdaftar.</p>
	</div>
	<?php if (in_array('users.create_resident', $permissions, TRUE)): ?>
		<a class="btn btn-primary" href="<?= site_url('admin/pengguna/buat') ?>"><i class="fas fa-user-plus mr-1" aria-hidden="true"></i> Daftarkan warga</a>
	<?php endif; ?>
</div>

<div class="card shadow-sm mb-4">
	<div class="card-body">
		<form method="get" action="<?= site_url('admin/pengguna') ?>" class="form-row align-items-end">
			<div class="col-md-4 mb-2">
				<label for="q">Cari nama / username</label>
				<input class="form-control" type="search" id="q" name="q" maxlength="60" value="<?= e($filters['q']) ?>">
			</div>
			<div class="col-md-3 mb-2">
				<label for="status">Status akun</label>
				<select class="form-control" id="status" name="status">
					<option value="">Semua status</option>
					<?php foreach ($statuses as $code => $label): ?>
						<option value="<?= e($code) ?>" <?= $filters['status'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-3 mb-2">
				<label for="role">Role</label>
				<select class="form-control" id="role" name="role">
					<option value="">Semua role</option>
					<?php foreach ($roles as $r): ?>
						<option value="<?= e($r->code) ?>" <?= $filters['role'] === $r->code ? 'selected' : '' ?>><?= e($r->name) ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-2 mb-2">
				<button class="btn btn-primary btn-block" type="submit">Terapkan</button>
			</div>
		</form>
	</div>
</div>

<div class="card shadow-sm">
	<div class="card-body">
		<?php if (empty($users)): ?>
			<div class="empty-box"><i class="fas fa-users" aria-hidden="true"></i><p class="mb-0">Tidak ada akun yang cocok.</p></div>
		<?php else: ?>
		<div class="table-responsive">
			<table class="table table-hover mb-0">
				<caption class="sr-only">Daftar akun pengguna</caption>
				<thead><tr><th scope="col">Nama</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col">Terakhir masuk</th><th scope="col">Dibuat</th></tr></thead>
				<tbody>
				<?php foreach ($users as $u): ?>
					<tr>
						<td>
							<a class="font-weight-bold" href="<?= site_url('admin/pengguna/'.rawurlencode($u->public_id)) ?>"><?= e($u->display_name) ?></a>
							<div class="small text-muted"><?= e($u->username) ?><?= $u->email ? ' · '.e($u->email) : '' ?></div>
						</td>
						<td><?php foreach ($u->roles as $r): ?><span class="chip-flag"><?= e($r->name) ?></span> <?php endforeach; ?></td>
						<td><span class="chip-flag <?= $u->account_status === 'active' ? 'is-info' : ($u->account_status === 'pending_activation' ? 'is-warning' : 'is-danger') ?>"><?= e(config_label('account_statuses', $u->account_status)) ?></span></td>
						<td class="small"><?= e($u->last_login_at ? format_wib($u->last_login_at, 'short') : 'belum pernah') ?></td>
						<td class="small"><?= e(format_wib($u->created_at, 'short')) ?></td>
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
