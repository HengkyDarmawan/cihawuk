<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="page-heading">
	<div>
		<h1>Role, Izin, dan Menu</h1>
		<p>Atur role pengelola, izin yang dipegang tiap role, dan menu dashboard yang terlihat.</p>
	</div>
	<div><a class="btn btn-primary" href="<?= site_url('admin/rbac/role/baru') ?>"><i class="fas fa-plus fa-sm mr-1" aria-hidden="true"></i> Tambah role</a></div>
</div>

<?php $this->load->view('admin/rbac_tabs', array('tab' => $tab)); ?>

<div class="card shadow-sm">
	<div class="table-responsive">
		<table class="table mb-0 align-middle">
			<thead><tr><th scope="col">Role</th><th scope="col">Kode</th><th scope="col">Jenis</th><th scope="col" class="text-right">Izin</th><th scope="col" class="text-right">Pengguna</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
			<tbody>
			<?php foreach ($roles as $role): ?>
				<tr>
					<th scope="row">
						<?= e($role->name) ?>
						<?php if ((int) $role->is_system === 1): ?><span class="chip-flag">bawaan</span><?php endif; ?>
						<?php if ($role->description): ?><div class="small text-muted font-weight-normal"><?= e($role->description) ?></div><?php endif; ?>
					</th>
					<td><code><?= e($role->code) ?></code></td>
					<td><?= (int) $role->is_staff === 1 ? 'Pengelola' : 'Warga' ?></td>
					<td class="text-right"><?= (int) $role->permission_count ?></td>
					<td class="text-right"><?= (int) $role->user_count ?></td>
					<td class="text-right"><a class="btn btn-sm btn-outline-primary" href="<?= site_url('admin/rbac/role/'.rawurlencode($role->code)) ?>">Atur</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
