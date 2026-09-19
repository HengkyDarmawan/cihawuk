<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Satu register aset beserta unit fisiknya. */
$base = site_url('admin/aset/'.rawurlencode($register->public_id));
$category_options = array();
foreach ($categories as $category) { $category_options[(string) $category->id] = $category->name; }
$location_options = array('' => '— belum ditentukan —');
foreach ($locations as $location) { $location_options[(string) $location->id] = $location->name; }
?>
<div class="page-heading">
	<div>
		<h1><?= e($register->name) ?></h1>
		<p>Status: <?= e($register->lifecycle_status) ?> · verifikasi <?= e($register->verification_status) ?>
			<?= $register->legacy_asset_code ? '· kode lama <code>'.e($register->legacy_asset_code).'</code>' : '' ?></p>
	</div>
	<div><a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/aset') ?>">Kembali</a></div>
</div>

<?= ui_error_summary($this->form_errors) ?>

<form class="card shadow-sm mb-4" method="post" action="<?= $base ?>/simpan" data-once>
	<div class="card-body">
		<?= csrf_field() ?>
		<div class="form-row">
			<div class="col-md-5"><?= ui_input(array('name' => 'name', 'label' => 'Nama barang', 'maxlength' => 220, 'value' => $register->name, 'required' => TRUE)) ?></div>
			<div class="col-md-3"><?= ui_select(array('name' => 'category_id', 'label' => 'Kategori', 'options' => $category_options, 'value' => (string) $register->category_id, 'required' => TRUE)) ?></div>
			<div class="col-md-2"><?= ui_input(array('name' => 'legacy_asset_code', 'label' => 'Kode lama', 'maxlength' => 60, 'value' => (string) $register->legacy_asset_code)) ?></div>
			<div class="col-md-2"><?= ui_input(array('name' => 'acquisition_year', 'label' => 'Tahun perolehan', 'value' => (string) $register->acquisition_year)) ?></div>
		</div>
		<?= ui_textarea(array('name' => 'description', 'label' => 'Uraian', 'maxlength' => 1000, 'rows' => 3, 'value' => (string) $register->description)) ?>
		<div class="form-row">
			<div class="col-md-3"><?= ui_input(array('name' => 'source_volume_raw', 'label' => 'Volume sumber (apa adanya)', 'maxlength' => 120, 'value' => (string) $register->source_volume_raw)) ?></div>
			<div class="col-md-3"><?= ui_input(array('name' => 'acquisition_source', 'label' => 'Asal-usul', 'maxlength' => 180, 'value' => (string) $register->acquisition_source)) ?></div>
			<div class="col-md-3"><?= ui_select(array('name' => 'ownership_status', 'label' => 'Status kepemilikan', 'options' => $ownership, 'value' => $register->ownership_status)) ?></div>
			<?php if ($can_financial): ?>
			<div class="col-md-3"><?= ui_input(array('name' => 'acquisition_value', 'label' => 'Nilai perolehan', 'value' => $register->acquisition_value === NULL ? '' : (string) $register->acquisition_value,
				'help' => 'Hanya terlihat oleh pemegang izin nilai aset.')) ?></div>
			<?php endif; ?>
		</div>
		<?= ui_input(array('name' => 'source_locator', 'label' => 'Lokasi data pada dokumen sumber', 'maxlength' => 255, 'value' => (string) $register->source_locator)) ?>
	</div>
	<div class="card-footer">
		<?php if ($can_edit): ?>
			<button class="btn btn-primary" type="submit">Simpan register</button>
			<span class="small text-muted ml-2">Menyimpan mencabut verifikasi sebelumnya.</span>
		<?php else: ?>
			<p class="mb-0 small text-muted">Anda tidak punya izin menyunting register.</p>
		<?php endif; ?>
	</div>
</form>

<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Unit fisik</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Asset tag</th><th scope="col">Merek/tipe</th><th scope="col">Lokasi</th><th scope="col">Status</th><th scope="col">Kondisi</th></tr></thead>
			<tbody>
			<?php foreach ($units as $unit): ?>
				<tr>
					<th scope="row"><a href="<?= site_url('admin/aset/unit/'.rawurlencode($unit->public_id)) ?>"><code><?= e($unit->asset_tag) ?></code></a></th>
					<td><?= e(trim($unit->brand.' '.$unit->model) ?: '—') ?></td>
					<td><?= e($unit->location_name ?: '—') ?><?= (int) $unit->location_sensitive === 1 ? ' <span class="badge badge-warning">sensitif</span>' : '' ?></td>
					<td><?= e($unit->lifecycle_status) ?></td>
					<td><?= e($unit->condition_status) ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($units)): ?><tr><td colspan="5" class="text-muted">Belum ada unit fisik.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php if ($can_create): ?>
	<div class="card-body border-top">
		<h3 class="h6">Tambah satu unit</h3>
		<form method="post" action="<?= $base ?>/unit" data-once>
			<?= csrf_field() ?>
			<div class="form-row">
				<div class="col-md-3"><?= ui_input(array('name' => 'asset_tag', 'label' => 'Asset tag', 'maxlength' => 60, 'required' => TRUE)) ?></div>
				<div class="col-md-3"><?= ui_input(array('name' => 'brand', 'label' => 'Merek', 'maxlength' => 120)) ?></div>
				<div class="col-md-3"><?= ui_input(array('name' => 'model', 'label' => 'Tipe', 'maxlength' => 120)) ?></div>
				<div class="col-md-3"><?= ui_select(array('name' => 'location_id', 'label' => 'Lokasi', 'options' => $location_options)) ?></div>
			</div>
			<button class="btn btn-outline-primary" type="submit">Tambah unit</button>
		</form>

		<hr>
		<h3 class="h6">Usulan pemecahan unit</h3>
		<p class="small text-muted">Angka volume sumber tidak dipecah otomatis. Tuliskan alasan pemecahannya.</p>
		<form method="post" action="<?= $base ?>/pecah-unit" data-once>
			<?= csrf_field() ?>
			<div class="form-row">
				<div class="col-md-2"><?= ui_input(array('name' => 'count', 'label' => 'Jumlah unit', 'required' => TRUE)) ?></div>
				<div class="col-md-3"><?= ui_input(array('name' => 'tag_prefix', 'label' => 'Awalan tag', 'maxlength' => 30)) ?></div>
				<div class="col-md-7"><?= ui_input(array('name' => 'reason', 'label' => 'Alasan pemecahan', 'maxlength' => 500, 'required' => TRUE)) ?></div>
			</div>
			<button class="btn btn-outline-primary" type="submit">Buat unit</button>
		</form>
	</div>
	<?php endif; ?>
</div>

<?php if ($can_edit): ?>
<form class="card shadow-sm" method="post" action="<?= $base ?>/verifikasi" data-once>
	<div class="card-body">
		<?= csrf_field() ?>
		<input type="hidden" name="verified" value="<?= $register->verification_status === 'verified' ? '0' : '1' ?>">
		<button class="btn btn-outline-secondary" type="submit"><?= $register->verification_status === 'verified' ? 'Cabut verifikasi register' : 'Tandai register terverifikasi' ?></button>
	</div>
</form>
<?php endif; ?>
