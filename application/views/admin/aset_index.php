<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Ringkasan aset, register, kategori, lokasi, dan riwayat cetak label QR. */
$category_options = array('' => '— semua kategori —');
foreach ($categories as $category) { $category_options[(string) $category->id] = $category->name; }
$stats = array(
	array('label' => 'Register', 'value' => $summary['registers'], 'icon' => 'fa-clipboard-list', 'class' => ''),
	array('label' => 'Unit fisik', 'value' => $summary['units'], 'icon' => 'fa-boxes', 'class' => ''),
	array('label' => 'Unit belum ber-QR', 'value' => $summary['without_qr'], 'icon' => 'fa-qrcode', 'class' => $summary['without_qr'] > 0 ? 'is-warning' : ''),
	array('label' => 'Register belum diverifikasi', 'value' => $summary['incomplete'], 'icon' => 'fa-clipboard-check', 'class' => $summary['incomplete'] > 0 ? 'is-warning' : ''),
);
$batch_status = array('ready' => 'Siap cetak', 'printed' => 'Sudah dibuka untuk cetak');
?>
<div class="page-heading">
	<div>
		<h1>Aset dan QR</h1>
		<p>Satu register = satu jenis barang. Di dalamnya ada unit fisik yang diberi label QR.</p>
	</div>
	<?php if ($can_create): ?>
	<div class="page-actions"><?= ui_add_button('tambah-register', 'Tambah register') ?></div>
	<?php endif; ?>
</div>

<?= ui_error_summary($this->form_errors) ?>

<div class="row mb-2">
	<?php foreach ($stats as $stat): ?>
	<div class="col-sm-6 col-xl-3 mb-3">
		<div class="card stat-card shadow-sm h-100 <?= e($stat['class']) ?>">
			<div class="card-body d-flex align-items-center justify-content-between">
				<div>
					<div class="stat-label"><?= e($stat['label']) ?></div>
					<div class="stat-value"><?= (int) $stat['value'] ?></div>
				</div>
				<span class="stat-icon"><i class="fas <?= e($stat['icon']) ?>" aria-hidden="true"></i></span>
			</div>
		</div>
	</div>
	<?php endforeach; ?>
</div>

<?php if ($summary['without_qr'] > 0 && $can_labels): ?>
<div class="alert alert-light border d-flex flex-wrap align-items-center justify-content-between" role="status">
	<span><i class="fas fa-lightbulb text-warning mr-1" aria-hidden="true"></i> Untuk mencetak QR: buka register, lalu klik <strong>Cetak QR semua unit</strong>. QR yang belum ada dibuat otomatis.</span>
</div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
	<div class="card-header d-flex flex-wrap align-items-center justify-content-between">
		<h2 class="h6 mb-0">Register aset</h2>
		<form class="form-inline asset-filter" method="get" action="<?= site_url('admin/aset') ?>">
			<label class="sr-only" for="kategori">Kategori</label>
			<select class="custom-select custom-select-sm mr-2 mb-1" id="kategori" name="kategori">
				<?php foreach ($category_options as $id => $label): ?>
					<option value="<?= e($id) ?>" <?= (string) $filters['kategori'] === (string) $id ? 'selected' : '' ?>><?= e($label) ?></option>
				<?php endforeach; ?>
			</select>
			<label class="sr-only" for="q">Cari nama atau kode lama</label>
			<input class="form-control form-control-sm mr-2 mb-1" type="search" id="q" name="q" value="<?= e($filters['q']) ?>" maxlength="60" placeholder="Cari nama / kode lama">
			<button class="btn btn-outline-primary btn-sm mb-1" type="submit">Saring</button>
		</form>
	</div>
	<div class="card-body">
		<div class="table-responsive">
			<table class="table table-hover mb-0"<?= empty($registers) ? '' : ' data-local-table data-order-col="0" data-order-dir="asc"' ?>>
				<thead><tr><th scope="col">Nama barang</th><th scope="col">Kategori</th><th scope="col">Kode lama</th><th scope="col">Tahun</th><?php if ($can_financial): ?><th scope="col" class="text-right">Nilai</th><?php endif; ?><th scope="col">Verifikasi</th></tr></thead>
				<tbody>
				<?php foreach ($registers as $register): ?>
					<tr>
						<td><a class="font-weight-bold" href="<?= site_url('admin/aset/'.rawurlencode($register->public_id)) ?>"><?= e($register->name) ?></a></td>
						<td><?= e($register->category_name) ?></td>
						<td><?= $register->legacy_asset_code ? '<code>'.e($register->legacy_asset_code).'</code>' : '<span class="text-muted">—</span>' ?></td>
						<td><?= $register->acquisition_year ? (int) $register->acquisition_year : '<span class="text-muted">—</span>' ?></td>
						<?php if ($can_financial): ?><td class="text-right text-nowrap"><?= $register->acquisition_value === NULL ? '<span class="text-muted">—</span>' : 'Rp '.e(number_format((float) $register->acquisition_value, 0, ',', '.')) ?></td><?php endif; ?>
						<td><?= $register->verification_status === 'verified' ? '<span class="badge badge-pill badge-success">Terverifikasi</span>' : '<span class="badge badge-pill badge-light border">Belum</span>' ?></td>
					</tr>
				<?php endforeach; ?>
				<?php if (empty($registers)): ?><tr><td colspan="<?= $can_financial ? 6 : 5 ?>" class="text-muted text-center py-4">Belum ada register aset.</td></tr><?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-lg-6 mb-4">
		<div class="card shadow-sm h-100">
			<div class="card-header"><h2 class="h6 mb-0">Kategori</h2></div>
			<div class="card-body">
				<div class="asset-chip-list">
					<?php foreach ($categories as $category): ?>
						<span class="asset-chip"><code><?= e($category->code) ?></code> <?= e($category->name) ?></span>
					<?php endforeach; ?>
					<?php if (empty($categories)): ?><span class="text-muted">Belum ada kategori.</span><?php endif; ?>
				</div>
			</div>
		</div>
	</div>
	<div class="col-lg-6 mb-4">
		<div class="card shadow-sm h-100">
			<div class="card-header"><h2 class="h6 mb-0">Lokasi</h2></div>
			<div class="card-body">
				<div class="asset-chip-list">
					<?php foreach ($locations as $location): ?>
						<span class="asset-chip"><code><?= e($location->code) ?></code> <?= e($location->name) ?>
							<?= (int) $location->is_sensitive === 1 ? '<span class="badge badge-warning ml-1">sensitif</span>' : '' ?></span>
					<?php endforeach; ?>
					<?php if (empty($locations)): ?><span class="text-muted">Belum ada lokasi.</span><?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>

<?php if ($batches): ?>
<div class="card shadow-sm">
	<div class="card-header"><h2 class="h6 mb-0">Riwayat cetak label QR</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Dibuat</th><th scope="col">Jumlah unit</th><th scope="col">Status</th><th scope="col">Oleh</th><th scope="col" class="text-right">Aksi</th></tr></thead>
			<tbody>
			<?php foreach ($batches as $batch): ?>
				<tr>
					<td><?= e(format_wib($batch->created_at, 'short')) ?></td>
					<td><?= (int) $batch->item_count ?></td>
					<td><span class="badge badge-pill <?= $batch->status === 'printed' ? 'badge-success' : 'badge-info' ?>"><?= e($batch_status[$batch->status] ?? $batch->status) ?></span></td>
					<td><?= e($batch->requester ?: '—') ?></td>
					<td class="text-right"><?php if ($can_labels): ?><a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/aset/label/'.rawurlencode($batch->public_id)) ?>" target="_blank" rel="noopener"><i class="fas fa-print fa-sm mr-1" aria-hidden="true"></i> Cetak ulang</a><?php endif; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
<?php endif; ?>

<?php if ($can_create): ?>
<?= ui_modal_open('tambah-register', 'Tambah register', 'modal-lg') ?>
	<form method="post" action="<?= site_url('admin/aset/register') ?>" data-once>
		<?= csrf_field() ?>
		<?= ui_input(array('name' => 'name', 'label' => 'Nama barang', 'maxlength' => 220, 'required' => TRUE)) ?>
		<div class="form-row">
			<div class="col-md-6"><?= ui_select(array('name' => 'category_id', 'label' => 'Kategori', 'options' => array_slice($category_options, 1, NULL, TRUE), 'required' => TRUE)) ?></div>
			<div class="col-md-6"><?= ui_input(array('name' => 'legacy_asset_code', 'label' => 'Kode lama', 'maxlength' => 60)) ?></div>
		</div>
		<?= ui_input(array('name' => 'source_volume_raw', 'label' => 'Jumlah di dokumen', 'maxlength' => 120, 'help' => 'Contoh: 2 Unit')) ?>
		<?= ui_modal_actions('Buat register') ?>
	</form>
<?= ui_modal_close() ?>

<?= ui_modal_open('modal-tambah-kategori', 'Tambah kategori') ?>
	<form method="post" action="<?= site_url('admin/aset/kategori') ?>" data-once>
		<?= csrf_field() ?>
		<?= ui_input(array('name' => 'code', 'id' => 'cat_code', 'label' => 'Kode', 'maxlength' => 40, 'required' => TRUE)) ?>
		<?= ui_input(array('name' => 'name', 'id' => 'cat_name', 'label' => 'Nama kategori', 'maxlength' => 180, 'required' => TRUE)) ?>
		<?= ui_modal_actions('Simpan kategori') ?>
	</form>
<?= ui_modal_close() ?>

<?= ui_modal_open('modal-tambah-lokasi', 'Tambah lokasi') ?>
	<form method="post" action="<?= site_url('admin/aset/lokasi') ?>" data-once>
		<?= csrf_field() ?>
		<?= ui_input(array('name' => 'code', 'id' => 'loc_code', 'label' => 'Kode', 'maxlength' => 40, 'required' => TRUE)) ?>
		<?= ui_input(array('name' => 'name', 'id' => 'loc_name', 'label' => 'Nama lokasi', 'maxlength' => 180, 'required' => TRUE)) ?>
		<div class="custom-control custom-checkbox">
			<input class="custom-control-input" type="checkbox" id="loc_sensitive" name="is_sensitive" value="1">
			<label class="custom-control-label font-weight-normal" for="loc_sensitive">Lokasi sensitif (tidak tampil di halaman QR publik)</label>
		</div>
		<?= ui_modal_actions('Simpan lokasi') ?>
	</form>
<?= ui_modal_close() ?>
<?php endif; ?>
