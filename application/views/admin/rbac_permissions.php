<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="page-heading">
	<div>
		<h1>Daftar izin</h1>
		<p>Izin bawaan dipakai langsung oleh kode aplikasi, jadi hanya deskripsinya yang dapat diubah. Izin buatan dapat dihapus selama tidak dipegang role mana pun.</p>
	</div>
	<div class="page-actions"><?= ui_add_button('modal-tambah-izin', 'Tambah izin') ?></div>
</div>

<?php $this->load->view('admin/rbac_tabs', array('tab' => $tab)); ?>
<?= ui_error_summary() ?>

<?php foreach ($groups as $prefix => $perms): ?>
<div class="card shadow-sm mb-3">
	<div class="card-header"><h2 class="h6 mb-0 text-uppercase"><?= e($prefix) ?></h2></div>
	<div class="table-responsive">
		<table class="table table-sm mb-0 align-middle">
			<thead><tr><th scope="col" style="width:28%">Kode</th><th scope="col">Deskripsi</th><th scope="col" class="text-right" style="width:8%">Role</th><th scope="col" style="width:18%"><span class="sr-only">Aksi</span></th></tr></thead>
			<tbody>
			<?php foreach ($perms as $perm): $fid = 'desc-'.(int) $perm->id; ?>
				<tr>
					<th scope="row"><code><?= e($perm->code) ?></code><?php if ((int) $perm->is_custom === 1): ?> <span class="chip-flag">buatan</span><?php endif; ?></th>
					<td>
						<form method="post" action="<?= site_url('admin/rbac/izin/'.rawurlencode($perm->code)) ?>" class="d-flex" style="gap:.5rem" id="form-<?= $fid ?>">
							<?= csrf_field() ?>
							<label class="sr-only" for="<?= $fid ?>">Deskripsi <?= e($perm->code) ?></label>
							<input class="form-control form-control-sm" id="<?= $fid ?>" name="description" maxlength="255" required value="<?= e($perm->description) ?>">
						</form>
					</td>
					<td class="text-right"><?= (int) $perm->role_count ?></td>
					<td class="text-right text-nowrap">
						<button class="btn btn-sm btn-outline-primary" type="submit" form="form-<?= $fid ?>">Simpan</button>
						<?php if ((int) $perm->is_custom === 1): ?>
						<form method="post" action="<?= site_url('admin/rbac/izin/'.rawurlencode($perm->code).'/hapus') ?>" class="d-inline" data-confirm="Hapus izin <?= e($perm->code) ?>?" data-confirm-ok="Hapus">
							<?= csrf_field() ?>
							<button class="btn btn-sm btn-outline-danger" type="submit"<?= (int) $perm->role_count > 0 ? ' disabled title="Masih dipegang role"' : '' ?>>Hapus</button>
						</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
<?php endforeach; ?>

<?= ui_modal_open('modal-tambah-izin', 'Tambah izin') ?>
	<form method="post" action="<?= site_url('admin/rbac/izin') ?>" data-once>
		<?= csrf_field() ?>
		<?= ui_input(array('name' => 'code', 'label' => 'Kode', 'required' => TRUE, 'maxlength' => 60, 'placeholder' => 'arsip.lihat')) ?>
		<?= ui_input(array('name' => 'description', 'label' => 'Deskripsi', 'required' => TRUE, 'maxlength' => 255)) ?>
		<p class="small text-muted mb-0">Izin baru tidak otomatis membuka fitur apa pun; ia berarti setelah dipakai oleh kode atau oleh aturan menu.</p>
		<?= ui_modal_actions('Tambah') ?>
	</form>
<?= ui_modal_close() ?>
