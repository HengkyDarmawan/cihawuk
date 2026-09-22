<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Satu register aset beserta unit fisik dan cetak label QR-nya. */
$base = site_url('admin/aset/'.rawurlencode($register->public_id));
$category_options = array();
foreach ($categories as $category) { $category_options[(string) $category->id] = $category->name; }
$location_options = array('' => '— belum ditentukan —');
foreach ($locations as $location) { $location_options[(string) $location->id] = $location->name; }
$verified = $register->verification_status === 'verified';
$without_qr = count(array_filter($qr_status, function ($s) { return $s !== 'active'; }));
?>
<div class="page-heading">
	<div>
		<h1><?= e($register->name) ?></h1>
		<p>
			<?= AssetService::badge('lifecycle', $register->lifecycle_status) ?>
			<?= $verified ? '<span class="badge badge-pill badge-success">Terverifikasi</span>' : '<span class="badge badge-pill badge-light border">Belum diverifikasi</span>' ?>
			<?= $register->legacy_asset_code ? '<span class="ml-1">kode lama <code>'.e($register->legacy_asset_code).'</code></span>' : '' ?>
			<span class="ml-1"><?= count($units) ?> unit</span>
		</p>
	</div>
	<div class="d-flex flex-wrap asset-actions">
		<?php if ($can_labels && ! empty($units)): ?>
		<form method="post" action="<?= site_url('admin/aset/label') ?>" target="_blank">
			<?= csrf_field() ?>
			<input type="hidden" name="register" value="<?= e($register->public_id) ?>">
			<button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-qrcode fa-sm mr-1" aria-hidden="true"></i> Cetak QR semua unit</button>
		</form>
		<?php endif; ?>
		<?php if ($can_edit): ?>
		<form method="post" action="<?= $base ?>/verifikasi" data-once>
			<?= csrf_field() ?>
			<input type="hidden" name="verified" value="<?= $verified ? '0' : '1' ?>">
			<button class="btn btn-outline-secondary btn-sm" type="submit"><?= $verified ? 'Cabut verifikasi' : 'Tandai terverifikasi' ?></button>
		</form>
		<?php endif; ?>
		<a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/aset') ?>">Kembali</a>
	</div>
</div>

<?= ui_error_summary($this->form_errors) ?>

<div class="card shadow-sm mb-4">
	<div class="card-header card-header-actions">
		<h2 class="h6 mb-0">Unit fisik</h2>
		<div class="d-flex flex-wrap align-items-center" style="gap:8px">
		<?php if ($can_create): ?>
			<?= ui_add_button('modal-tambah-unit', 'Tambah unit') ?>
			<?= ui_add_button('modal-pecah-unit', 'Buat banyak unit', 'btn-outline-primary') ?>
		<?php endif; ?>
		<?php if ($can_labels && ! empty($units)): ?>
		<div class="d-flex align-items-center">
			<?php if ($without_qr > 0): ?><span class="small text-muted mr-2"><?= (int) $without_qr ?> unit belum ber-QR (dibuat otomatis saat dicetak)</span><?php endif; ?>
			<button class="btn btn-outline-primary btn-sm" type="submit" form="label-form" data-label-selected disabled><i class="fas fa-print fa-sm mr-1" aria-hidden="true"></i> Cetak QR terpilih</button>
		</div>
		<?php endif; ?>
		</div>
	</div>
	<?php if ($can_labels): ?>
	<form id="label-form" method="post" action="<?= site_url('admin/aset/label') ?>" target="_blank"><?= csrf_field() ?></form>
	<?php endif; ?>
	<div class="card-body">
		<div class="table-responsive">
			<table class="table table-hover mb-0"<?= empty($units) ? '' : ' data-local-table data-order-col="'.($can_labels ? 1 : 0).'" data-order-dir="asc"' ?>>
				<thead><tr>
					<?php if ($can_labels): ?><th scope="col" class="asset-check" data-orderable="false"><input type="checkbox" data-check-all aria-label="Pilih semua unit"></th><?php endif; ?>
					<th scope="col">Kode aset</th><th scope="col">Merek/tipe</th><th scope="col">Lokasi</th><th scope="col">Status</th><th scope="col">Kondisi</th><th scope="col">QR</th>
				</tr></thead>
				<tbody>
				<?php foreach ($units as $unit): $has_qr = ($qr_status[(int) $unit->id] ?? 'none') === 'active'; ?>
					<tr>
						<?php if ($can_labels): ?><td class="asset-check"><input type="checkbox" name="unit_ids[]" value="<?= e($unit->public_id) ?>" form="label-form" data-check-item aria-label="Pilih <?= e($unit->asset_tag) ?>"></td><?php endif; ?>
						<td><a class="font-weight-bold" href="<?= site_url('admin/aset/unit/'.rawurlencode($unit->public_id)) ?>"><?= e($unit->asset_tag) ?></a></td>
						<td><?= e(trim($unit->brand.' '.$unit->model) ?: '—') ?></td>
						<td><?= e($unit->location_name ?: '—') ?><?= (int) $unit->location_sensitive === 1 ? ' <span class="badge badge-warning">sensitif</span>' : '' ?></td>
						<td><?= AssetService::badge('lifecycle', $unit->lifecycle_status) ?></td>
						<td><?= AssetService::badge('condition', $unit->condition_status) ?></td>
						<td><?= $has_qr ? '<span class="badge badge-pill badge-success"><i class="fas fa-qrcode" aria-hidden="true"></i> Ada</span>' : '<span class="badge badge-pill badge-light border">Belum</span>' ?></td>
					</tr>
				<?php endforeach; ?>
				<?php if (empty($units)): ?><tr><td colspan="<?= $can_labels ? 7 : 6 ?>" class="text-muted text-center py-4">Belum ada unit fisik. Klik <strong>Tambah unit</strong> di atas.</td></tr><?php endif; ?>
				</tbody>
			</table>
		</div>
		<p class="small text-muted mt-3 mb-0">Unit berstatus <strong>Draft</strong> belum tampil di halaman publik saat QR-nya dipindai. Ubah statusnya menjadi <strong>Aktif</strong> di halaman unit.</p>
	</div>
</div>

<div class="card shadow-sm">
	<div class="card-header p-0">
		<button class="btn btn-link btn-block text-left font-weight-bold asset-collapse-toggle collapsed" type="button" data-toggle="collapse" data-target="#register-data" aria-expanded="false" aria-controls="register-data">
			<i class="fas fa-chevron-down fa-sm mr-1" aria-hidden="true"></i> Data register (nama, kategori, asal-usul<?= $can_financial ? ', nilai' : '' ?>)
		</button>
	</div>
	<div class="collapse<?= empty($this->form_errors) ? '' : ' show' ?>" id="register-data">
		<form method="post" action="<?= $base ?>/simpan" data-once>
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
					<div class="col-md-3"><?= ui_input(array('name' => 'source_volume_raw', 'label' => 'Jumlah di dokumen', 'maxlength' => 120, 'value' => (string) $register->source_volume_raw)) ?></div>
					<div class="col-md-3"><?= ui_input(array('name' => 'acquisition_source', 'label' => 'Asal-usul', 'maxlength' => 180, 'value' => (string) $register->acquisition_source)) ?></div>
					<div class="col-md-3"><?= ui_select(array('name' => 'ownership_status', 'label' => 'Status kepemilikan', 'options' => $ownership, 'value' => $register->ownership_status)) ?></div>
					<?php if ($can_financial): ?>
					<div class="col-md-3"><?= ui_input(array('name' => 'acquisition_value', 'label' => 'Nilai perolehan', 'value' => $register->acquisition_value === NULL ? '' : (string) $register->acquisition_value,
						'help' => 'Hanya terlihat oleh pemegang izin nilai aset.')) ?></div>
					<?php endif; ?>
				</div>
				<?= ui_input(array('name' => 'source_locator', 'label' => 'Letak data pada dokumen sumber', 'maxlength' => 255, 'value' => (string) $register->source_locator)) ?>
			</div>
			<div class="card-footer bg-white">
				<?php if ($can_edit): ?>
					<button class="btn btn-primary" type="submit">Simpan register</button>
					<span class="small text-muted ml-2">Menyimpan akan mencabut verifikasi sebelumnya.</span>
				<?php else: ?>
					<p class="mb-0 small text-muted">Anda tidak punya izin menyunting register.</p>
				<?php endif; ?>
			</div>
		</form>
	</div>
</div>

<?php if ($can_create): ?>
<?= ui_modal_open('modal-tambah-unit', 'Tambah satu unit') ?>
	<form method="post" action="<?= $base ?>/unit" data-once>
		<?= csrf_field() ?>
		<div class="form-row">
			<div class="col-md-6"><?= ui_input(array('name' => 'asset_tag', 'label' => 'Kode aset (label)', 'maxlength' => 60, 'required' => TRUE)) ?></div>
			<div class="col-md-6"><?= ui_select(array('name' => 'location_id', 'label' => 'Lokasi', 'options' => $location_options)) ?></div>
			<div class="col-md-6"><?= ui_input(array('name' => 'brand', 'label' => 'Merek', 'maxlength' => 120)) ?></div>
			<div class="col-md-6"><?= ui_input(array('name' => 'model', 'label' => 'Tipe', 'maxlength' => 120)) ?></div>
		</div>
		<?= ui_modal_actions('Tambah unit') ?>
	</form>
<?= ui_modal_close() ?>

<?= ui_modal_open('modal-pecah-unit', 'Buat banyak unit sekaligus') ?>
	<form method="post" action="<?= $base ?>/pecah-unit" data-once>
		<?= csrf_field() ?>
		<div class="form-row">
			<div class="col-5"><?= ui_input(array('name' => 'count', 'label' => 'Jumlah', 'type' => 'number', 'required' => TRUE)) ?></div>
			<div class="col-7"><?= ui_input(array('name' => 'tag_prefix', 'label' => 'Awalan kode', 'maxlength' => 30)) ?></div>
		</div>
		<?= ui_input(array('name' => 'reason', 'label' => 'Alasan', 'maxlength' => 500, 'required' => TRUE, 'help' => 'Contoh: dokumen menyebut 2 unit, keduanya ada di kantor.')) ?>
		<?= ui_modal_actions('Buat unit') ?>
	</form>
<?= ui_modal_close() ?>
<?php endif; ?>
