<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Ringkasan aset, register, kategori, lokasi, dan batch label. */
$category_options = array('' => '— semua kategori —');
foreach ($categories as $category) { $category_options[(string) $category->id] = $category->name; }
?>
<div class="page-heading">
	<div>
		<h1>Aset dan QR</h1>
		<p>Register menyimpan identitas administrasi; unit fisik menyimpan barang yang benar-benar diperiksa dan dilabeli.</p>
	</div>
</div>

<?= ui_error_summary($this->form_errors) ?>

<div class="row g-3 mb-4">
	<div class="col-md-3"><div class="card-soft card-pad"><p class="eyebrow">Register</p><p class="h4 mb-0"><?= (int) $summary['registers'] ?></p></div></div>
	<div class="col-md-3"><div class="card-soft card-pad"><p class="eyebrow">Unit fisik</p><p class="h4 mb-0"><?= (int) $summary['units'] ?></p></div></div>
	<div class="col-md-3"><div class="card-soft card-pad"><p class="eyebrow">Unit tanpa QR aktif</p><p class="h4 mb-0"><?= (int) $summary['without_qr'] ?></p></div></div>
	<div class="col-md-3"><div class="card-soft card-pad"><p class="eyebrow">Register belum diverifikasi</p><p class="h4 mb-0"><?= (int) $summary['incomplete'] ?></p></div></div>
</div>

<form class="filter-bar mb-4" method="get" action="<?= site_url('admin/aset') ?>">
	<div>
		<label class="form-label" for="kategori">Kategori</label>
		<select class="form-select" id="kategori" name="kategori">
			<?php foreach ($category_options as $id => $label): ?>
				<option value="<?= e($id) ?>" <?= (string) $filters['kategori'] === (string) $id ? 'selected' : '' ?>><?= e($label) ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div>
		<label class="form-label" for="q">Cari nama atau kode lama</label>
		<input class="form-control" type="search" id="q" name="q" value="<?= e($filters['q']) ?>" maxlength="60">
	</div>
	<div><button class="btn btn-outline-primary" type="submit">Saring</button></div>
</form>

<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Register aset</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Nama</th><th scope="col">Kategori</th><th scope="col">Kode lama</th><th scope="col">Tahun</th><?php if ($can_financial): ?><th scope="col" class="num">Nilai</th><?php endif; ?><th scope="col">Verifikasi</th></tr></thead>
			<tbody>
			<?php foreach ($registers as $register): ?>
				<tr>
					<th scope="row"><a href="<?= site_url('admin/aset/'.rawurlencode($register->public_id)) ?>"><?= e($register->name) ?></a></th>
					<td><?= e($register->category_name) ?></td>
					<td><?= e($register->legacy_asset_code ?: '—') ?></td>
					<td><?= $register->acquisition_year ? (int) $register->acquisition_year : '&mdash;' ?></td>
					<?php if ($can_financial): ?><td class="num"><?= $register->acquisition_value === NULL ? '&mdash;' : e(number_format((float) $register->acquisition_value, 2, ',', '.')) ?></td><?php endif; ?>
					<td><?= $register->verification_status === 'verified' ? '<span class="badge badge-success">terverifikasi</span>' : '<span class="badge badge-secondary">belum</span>' ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($registers)): ?><tr><td colspan="<?= $can_financial ? 6 : 5 ?>" class="text-muted">Belum ada register aset.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php if ($can_create): ?>
	<div class="card-body border-top">
		<h3 class="h6">Tambah register</h3>
		<form method="post" action="<?= site_url('admin/aset/register') ?>" data-once>
			<?= csrf_field() ?>
			<div class="form-row">
				<div class="col-md-4"><?= ui_input(array('name' => 'name', 'label' => 'Nama barang', 'maxlength' => 220, 'required' => TRUE)) ?></div>
				<div class="col-md-3"><?= ui_select(array('name' => 'category_id', 'label' => 'Kategori', 'options' => array_slice($category_options, 1, NULL, TRUE), 'required' => TRUE)) ?></div>
				<div class="col-md-2"><?= ui_input(array('name' => 'legacy_asset_code', 'label' => 'Kode lama', 'maxlength' => 60)) ?></div>
				<div class="col-md-3"><?= ui_input(array('name' => 'source_volume_raw', 'label' => 'Volume sumber (apa adanya)', 'maxlength' => 120)) ?></div>
			</div>
			<button class="btn btn-primary" type="submit">Buat register</button>
		</form>
	</div>
	<?php endif; ?>
</div>

<div class="row g-4">
	<div class="col-lg-6">
		<div class="card shadow-sm h-100">
			<div class="card-header"><h2 class="h6 mb-0">Kategori</h2></div>
			<ul class="list-group list-group-flush">
				<?php foreach ($categories as $category): ?>
					<li class="list-group-item"><code><?= e($category->code) ?></code> <?= e($category->name) ?></li>
				<?php endforeach; ?>
				<?php if (empty($categories)): ?><li class="list-group-item text-muted">Belum ada kategori.</li><?php endif; ?>
			</ul>
			<?php if ($can_create): ?>
			<div class="card-body border-top">
				<form method="post" action="<?= site_url('admin/aset/kategori') ?>" data-once>
					<?= csrf_field() ?>
					<div class="form-row">
						<div class="col-4"><?= ui_input(array('name' => 'code', 'label' => 'Kode', 'maxlength' => 40, 'required' => TRUE)) ?></div>
						<div class="col-8"><?= ui_input(array('name' => 'name', 'label' => 'Nama kategori', 'maxlength' => 180, 'required' => TRUE)) ?></div>
					</div>
					<button class="btn btn-outline-primary btn-sm" type="submit">Simpan kategori</button>
				</form>
			</div>
			<?php endif; ?>
		</div>
	</div>
	<div class="col-lg-6">
		<div class="card shadow-sm h-100">
			<div class="card-header"><h2 class="h6 mb-0">Lokasi</h2></div>
			<ul class="list-group list-group-flush">
				<?php foreach ($locations as $location): ?>
					<li class="list-group-item"><code><?= e($location->code) ?></code> <?= e($location->name) ?>
						<?= (int) $location->is_sensitive === 1 ? '<span class="badge badge-warning">sensitif</span>' : '' ?></li>
				<?php endforeach; ?>
				<?php if (empty($locations)): ?><li class="list-group-item text-muted">Belum ada lokasi.</li><?php endif; ?>
			</ul>
			<?php if ($can_create): ?>
			<div class="card-body border-top">
				<form method="post" action="<?= site_url('admin/aset/lokasi') ?>" data-once>
					<?= csrf_field() ?>
					<div class="form-row">
						<div class="col-4"><?= ui_input(array('name' => 'code', 'label' => 'Kode', 'maxlength' => 40, 'required' => TRUE)) ?></div>
						<div class="col-8"><?= ui_input(array('name' => 'name', 'label' => 'Nama lokasi', 'maxlength' => 180, 'required' => TRUE)) ?></div>
					</div>
					<div class="form-check mb-2">
						<input class="form-check-input" type="checkbox" id="loc_sensitive" name="is_sensitive" value="1">
						<label class="form-check-label" for="loc_sensitive">Lokasi sensitif (tidak tampil pada QR publik)</label>
					</div>
					<button class="btn btn-outline-primary btn-sm" type="submit">Simpan lokasi</button>
				</form>
			</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<?php if ($batches): ?>
<div class="card shadow-sm mt-4">
	<div class="card-header"><h2 class="h6 mb-0">Batch label QR</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Batch</th><th scope="col">Jumlah</th><th scope="col">Status</th><th scope="col">Alasan cetak ulang</th><th scope="col">Dibuat</th></tr></thead>
			<tbody>
			<?php foreach ($batches as $batch): ?>
				<tr>
					<th scope="row"><code><?= e(substr($batch->public_id, 0, 8)) ?></code></th>
					<td><?= (int) $batch->item_count ?></td>
					<td><?= e($batch->status) ?></td>
					<td><?= e($batch->reprint_reason ?: '—') ?></td>
					<td><?= e(format_wib($batch->created_at, 'short')) ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
<?php endif; ?>
