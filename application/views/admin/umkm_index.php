<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Daftar UMKM. Persetujuan pemilik adalah syarat, bukan formalitas. */
?>
<div class="page-heading">
	<div>
		<h1>Direktori UMKM</h1>
		<p>Profil usaha hanya boleh terbit setelah pemiliknya menyetujui dan persetujuannya dicatat.</p>
	</div>
	<?php if ($can_edit): ?><div class="page-actions"><?= ui_add_button('modal-tambah-usaha', 'Tambah usaha') ?></div><?php endif; ?>
</div>

<?= ui_error_summary($this->form_errors) ?>

<form class="filter-bar mb-4" method="get" action="<?= site_url('admin/umkm') ?>">
	<div>
		<label class="form-label" for="kategori">Kategori</label>
		<select class="form-select" id="kategori" name="kategori">
			<option value="">Semua kategori</option>
			<?php foreach ($categories as $code => $label): ?>
				<option value="<?= e($code) ?>" <?= $category_filter === $code ? 'selected' : '' ?>><?= e($label) ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div><button class="btn btn-outline-primary" type="submit">Saring</button></div>
</form>

<div class="card shadow-sm">
	<div class="card-header"><h2 class="h6 mb-0">Usaha</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Nama</th><th scope="col">Kategori</th><th scope="col">Status</th><th scope="col">Persetujuan</th><th scope="col">Belum siap terbit</th></tr></thead>
			<tbody>
			<?php foreach ($businesses as $business): ?>
				<tr>
					<th scope="row"><a href="<?= site_url('admin/umkm/'.rawurlencode($business->public_id)) ?>"><?= e($business->name) ?></a></th>
					<td><?= e($categories[$business->category] ?? $business->category) ?></td>
					<td><?= e($statuses[$business->publication_status] ?? $business->publication_status) ?></td>
					<td><?= (int) $business->owner_consent === 1 ? '<span class="badge badge-success">ada</span>' : '<span class="badge badge-secondary">belum</span>' ?></td>
					<td class="small text-muted"><?= $business->blockers ? e(implode(' ', $business->blockers)) : '<span class="text-success">siap</span>' ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($businesses)): ?><tr><td colspan="5" class="text-muted">Belum ada profil usaha.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<?php if ($can_edit): ?>
<?= ui_modal_open('modal-tambah-usaha', 'Tambah usaha') ?>
	<form method="post" action="<?= site_url('admin/umkm/buat') ?>" data-once>
		<?= csrf_field() ?>
		<?= ui_input(array('name' => 'name', 'label' => 'Nama usaha', 'maxlength' => 200, 'required' => TRUE)) ?>
		<?= ui_select(array('name' => 'category', 'label' => 'Kategori', 'options' => $categories, 'required' => TRUE)) ?>
		<?= ui_input(array('name' => 'owner_name', 'label' => 'Pemilik', 'maxlength' => 180)) ?>
		<p class="small text-muted mb-0">Usaha dibuat sebagai draft; lengkapi profil dan persetujuan pemilik di halaman berikutnya.</p>
		<?= ui_modal_actions('Buat draft') ?>
	</form>
<?= ui_modal_close() ?>
<?php endif; ?>
