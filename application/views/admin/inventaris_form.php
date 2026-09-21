<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Tambah / ubah barang inventaris dalam satu form. */
$is_new = ($item === NULL);
$action = $is_new ? site_url('admin/inventaris/tambah') : site_url('admin/inventaris/'.rawurlencode($item->public_id).'/ubah');
$val = function ($field, $current = '') use ($is_new) {
	$old = old($field, NULL);
	return $old !== NULL && $old !== '' ? $old : ($is_new ? '' : (string) $current);
};
$years = array('' => '— tidak diketahui —');
for ($y = (int) date('Y'); $y >= 1950; $y--) { $years[(string) $y] = (string) $y; }
?>
<div class="page-heading">
	<div>
		<h1><?= $is_new ? 'Tambah Barang' : 'Ubah '.e($item->asset_tag) ?></h1>
		<p><?= $is_new ? 'Isi data barang lalu simpan. Kode dan QR dibuat otomatis.' : e($item->name) ?></p>
	</div>
	<div><a class="btn btn-outline-primary btn-sm" href="<?= site_url($is_new ? 'admin/inventaris' : 'admin/inventaris/'.rawurlencode($item->public_id)) ?>"><i class="fas fa-arrow-left fa-sm mr-1" aria-hidden="true"></i> Kembali</a></div>
</div>

<?php if ($form_error): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php endif; ?>
<?= ui_error_summary($this->form_errors) ?>

<?php if ($is_new): ?>
<div class="alert alert-info small"><i class="fas fa-info-circle mr-1" aria-hidden="true"></i>
	Kode barang berikutnya: <strong><?= e($next_code) ?></strong>. Bila jumlah lebih dari 1, setiap barang mendapat kode dan QR sendiri supaya bisa ditempel satu per satu.</div>
<?php else: ?>
<div class="alert alert-light border small"><i class="fas fa-lock mr-1" aria-hidden="true"></i> Kode <strong><?= e($item->asset_tag) ?></strong> dan QR tidak berubah saat data diubah, jadi label yang sudah tertempel tetap berlaku.</div>
<?php endif; ?>

<form method="post" action="<?= $action ?>" enctype="multipart/form-data" data-once>
	<?= csrf_field() ?>
	<div class="card shadow-sm mb-4">
		<div class="card-header"><h2 class="h6 mb-0">Data barang</h2></div>
		<div class="card-body">
			<div class="form-row">
				<div class="col-md-6"><?= ui_input(array('name' => 'nama', 'label' => 'Nama barang', 'required' => TRUE, 'maxlength' => 220, 'value' => $val('nama', $is_new ? '' : $item->name), 'placeholder' => 'mis. Laptop, Meja rapat, Motor dinas')) ?></div>
				<div class="col-md-3"><?= ui_select(array('name' => 'kategori_id', 'label' => 'Kategori', 'options' => $categories, 'placeholder_option' => 'Pilih kategori…', 'value' => $val('kategori_id', $is_new ? '' : $item->category_id))) ?></div>
				<div class="col-md-3"><?= ui_input(array('name' => 'kategori_baru', 'label' => 'atau kategori baru', 'maxlength' => 120, 'value' => old('kategori_baru'), 'placeholder' => 'mis. Elektronik')) ?></div>
			</div>
			<div class="form-row">
				<div class="col-md-4"><?= ui_input(array('name' => 'merk', 'label' => 'Merk', 'maxlength' => 120, 'value' => $val('merk', $is_new ? '' : $item->brand))) ?></div>
				<div class="col-md-4"><?= ui_input(array('name' => 'tipe', 'label' => 'Tipe / model', 'maxlength' => 120, 'value' => $val('tipe', $is_new ? '' : $item->model))) ?></div>
				<div class="col-md-4"><?= ui_select(array('name' => 'tahun', 'label' => 'Tahun perolehan', 'options' => $years, 'value' => $val('tahun', $is_new ? '' : $item->acquisition_year))) ?></div>
			</div>
			<?php if ($is_new): ?>
			<div class="form-row">
				<div class="col-md-2"><?= ui_input(array('name' => 'jumlah', 'label' => 'Jumlah', 'type' => 'number', 'required' => TRUE, 'value' => old('jumlah') ?: '1', 'min' => 1, 'max' => InventoryService::MAX_QUANTITY)) ?></div>
				<div class="col-md-4"><?= ui_select(array('name' => 'location_id', 'label' => 'Lokasi', 'options' => $locations, 'placeholder_option' => 'Pilih lokasi…', 'value' => old('location_id'))) ?></div>
				<div class="col-md-3"><?= ui_input(array('name' => 'lokasi_baru', 'label' => 'atau lokasi baru', 'maxlength' => 180, 'value' => old('lokasi_baru'), 'placeholder' => 'mis. Ruang Sekdes')) ?></div>
				<div class="col-md-3"><?= ui_select(array('name' => 'kondisi', 'label' => 'Kondisi', 'required' => TRUE, 'options' => InventoryService::CONDITIONS, 'value' => old('kondisi') ?: 'good')) ?></div>
			</div>
			<?php endif; ?>
			<?php if ($can_financial): ?>
			<div class="form-row">
				<div class="col-md-6"><?= ui_input(array('name' => 'sumber_dana', 'label' => 'Sumber dana', 'maxlength' => 180, 'value' => $val('sumber_dana', $is_new ? '' : $item->acquisition_source), 'placeholder' => 'mis. Dana Desa 2024')) ?></div>
				<div class="col-md-6"><?= ui_input(array('name' => 'harga', 'label' => 'Harga perolehan (Rp)', 'maxlength' => 30, 'inputmode' => 'decimal', 'value' => $val('harga', $is_new || $item->acquisition_value === NULL ? '' : rtrim(rtrim((string) $item->acquisition_value, '0'), '.')), 'help' => 'Tidak ditampilkan di halaman publik.')) ?></div>
			</div>
			<?php endif; ?>
			<?= ui_textarea(array('name' => 'keterangan', 'label' => 'Keterangan', 'rows' => 2, 'maxlength' => 1000, 'value' => $val('keterangan', $is_new ? '' : $item->register_description))) ?>
			<?= ui_input(array('name' => 'catatan_publik', 'label' => 'Catatan untuk halaman QR', 'maxlength' => 500, 'value' => $val('catatan_publik', $is_new ? '' : $item->public_note), 'help' => 'Tampil saat QR dipindai, mis. "Hubungi kantor desa bila rusak".')) ?>
			<div class="mb-0">
				<label for="foto" class="font-weight-bold">Foto <span class="text-muted font-weight-normal small">(opsional)</span></label>
				<input class="form-control-file" type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp">
				<small class="form-text text-muted">JPG/PNG/WEBP. Foto ditampilkan juga di halaman QR.</small>
			</div>
		</div>
	</div>
	<?php if ( ! $is_new): ?>
	<p class="small text-muted">Lokasi, kondisi, dan status diubah lewat tombol di halaman detail supaya tercatat di riwayat.</p>
	<?php endif; ?>
	<button class="btn btn-primary" type="submit"><i class="fas fa-save fa-sm mr-1" aria-hidden="true"></i> <?= $is_new ? 'Simpan barang' : 'Simpan perubahan' ?></button>
</form>
