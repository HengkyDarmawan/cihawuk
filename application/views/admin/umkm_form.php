<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Form satu profil UMKM beserta catatan persetujuan pemilik. */
$base = site_url('admin/umkm/'.rawurlencode($business->public_id));
?>
<div class="page-heading">
	<div>
		<h1><?= e($business->name) ?></h1>
		<p>Status: <?= e($statuses[$business->publication_status] ?? $business->publication_status) ?>
			· persetujuan pemilik <?= (int) $business->owner_consent === 1 ? 'sudah dicatat' : 'belum ada' ?></p>
	</div>
	<div><a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/umkm') ?>">Kembali</a></div>
</div>

<?= ui_error_summary($this->form_errors) ?>

<?php if ($blockers): ?>
<div class="alert alert-warning" role="status">
	<strong>Belum siap terbit.</strong>
	<ul class="mb-0"><?php foreach ($blockers as $blocker): ?><li><?= e($blocker) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form class="card shadow-sm mb-4" method="post" action="<?= $base ?>/simpan" data-once>
	<div class="card-body">
		<?= csrf_field() ?>
		<div class="form-row">
			<div class="col-md-5"><?= ui_input(array('name' => 'name', 'label' => 'Nama usaha', 'maxlength' => 200, 'value' => $business->name, 'required' => TRUE)) ?></div>
			<div class="col-md-4"><?= ui_select(array('name' => 'category', 'label' => 'Kategori', 'options' => $categories, 'value' => $business->category, 'required' => TRUE)) ?></div>
			<div class="col-md-3"><?= ui_input(array('name' => 'slug', 'label' => 'Slug', 'maxlength' => 220, 'value' => $business->slug)) ?></div>
		</div>
		<?= ui_input(array('name' => 'owner_name', 'label' => 'Nama pemilik', 'maxlength' => 180, 'value' => (string) $business->owner_name)) ?>
		<?= ui_textarea(array('name' => 'description', 'label' => 'Deskripsi', 'maxlength' => 1000, 'rows' => 4, 'value' => (string) $business->description)) ?>
		<?= ui_textarea(array('name' => 'products', 'label' => 'Produk', 'maxlength' => 600, 'rows' => 3, 'value' => (string) $business->products)) ?>
		<div class="form-row">
			<div class="col-md-6"><?= ui_input(array('name' => 'public_location', 'label' => 'Lokasi publik', 'maxlength' => 400, 'value' => (string) $business->public_location)) ?></div>
			<div class="col-md-3"><?= ui_input(array('name' => 'opening_hours', 'label' => 'Jam buka', 'maxlength' => 255, 'value' => (string) $business->opening_hours)) ?></div>
			<div class="col-md-3"><?= ui_input(array('name' => 'public_contact', 'label' => 'Kontak publik', 'maxlength' => 255, 'value' => (string) $business->public_contact)) ?></div>
		</div>
		<div class="form-check mb-2">
			<input class="form-check-input" type="checkbox" id="owner_consent" name="owner_consent" value="1" <?= (int) $business->owner_consent === 1 ? 'checked' : '' ?>>
			<label class="form-check-label" for="owner_consent">Pemilik menyetujui pemuatan datanya</label>
		</div>
		<?= ui_textarea(array('name' => 'consent_note', 'label' => 'Catatan persetujuan', 'maxlength' => 500, 'rows' => 2,
			'value' => (string) $business->consent_note, 'help' => 'Tuliskan kapan dan bagaimana persetujuan diperoleh.')) ?>
		<div class="form-check mb-0">
			<input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (int) $business->is_active === 1 ? 'checked' : '' ?>>
			<label class="form-check-label" for="is_active">Usaha masih aktif</label>
		</div>
	</div>
	<div class="card-footer">
		<?php if ($can_edit): ?>
			<button class="btn btn-primary" type="submit">Simpan</button>
		<?php else: ?>
			<p class="mb-0 small text-muted">Anda tidak punya izin menyunting profil usaha.</p>
		<?php endif; ?>
	</div>
</form>

<?php if ($can_publish): ?>
<div class="card shadow-sm">
	<div class="card-header"><h2 class="h6 mb-0">Alur publikasi</h2></div>
	<div class="card-body">
		<?php foreach (array(
			'terbitkan' => 'Terbitkan',
			'tarik' => 'Tarik dari direktori',
			'cabut-persetujuan' => 'Cabut persetujuan pemilik',
			'arsipkan' => 'Arsipkan',
		) as $action => $label): ?>
		<form class="form-inline d-inline-block mr-2 mb-2" method="post" action="<?= $base ?>/alur/<?= e($action) ?>" data-once>
			<?= csrf_field() ?>
			<?php if ($action !== 'terbitkan'): ?>
				<input class="form-control form-control-sm mr-1" type="text" name="reason" maxlength="500" placeholder="Alasan" aria-label="Alasan <?= e($label) ?>">
			<?php endif; ?>
			<button class="btn btn-sm btn-outline-secondary" type="submit"><?= e($label) ?></button>
		</form>
		<?php endforeach; ?>
	</div>
</div>
<?php endif; ?>
