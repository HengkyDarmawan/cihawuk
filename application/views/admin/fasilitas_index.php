<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Daftar fasilitas dan lokasi. Angka agregat tidak boleh dijadikan entri di sini. */
?>
<div class="page-heading">
	<div>
		<h1>Direktori Fasilitas</h1>
		<p>Satu entri untuk satu objek yang benar-benar ada. Angka agregat seperti &ldquo;4 SD&rdquo; tetap berada di Data Desa.</p>
	</div>
</div>

<form class="filter-bar mb-4" method="get" action="<?= site_url('admin/fasilitas') ?>">
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

<?= ui_error_summary($this->form_errors) ?>

<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Fasilitas</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Nama</th><th scope="col">Kategori</th><th scope="col">Status</th><th scope="col">Belum siap terbit</th></tr></thead>
			<tbody>
			<?php foreach ($facilities as $facility): ?>
				<tr>
					<th scope="row"><a href="<?= site_url('admin/fasilitas/'.rawurlencode($facility->public_id)) ?>"><?= e($facility->name) ?></a></th>
					<td><?= e($categories[$facility->category] ?? $facility->category) ?></td>
					<td><?= e($statuses[$facility->publication_status] ?? $facility->publication_status) ?>
						<?= $facility->verification_status === 'verified' ? ' <span class="badge badge-success">terverifikasi</span>' : '' ?></td>
					<td class="small text-muted">
						<?php if ($facility->blockers): ?>
							<?= e(implode(' ', $facility->blockers)) ?>
						<?php else: ?>
							<span class="text-success">siap</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($facilities)): ?><tr><td colspan="4" class="text-muted">Belum ada fasilitas.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php if ($can_edit): ?>
	<div class="card-body border-top">
		<h3 class="h6">Tambah fasilitas</h3>
		<form method="post" action="<?= site_url('admin/fasilitas/buat') ?>" data-once>
			<?= csrf_field() ?>
			<div class="form-row">
				<div class="col-md-5"><?= ui_input(array('name' => 'name', 'label' => 'Nama fasilitas', 'maxlength' => 200, 'required' => TRUE)) ?></div>
				<div class="col-md-4"><?= ui_select(array('name' => 'category', 'label' => 'Kategori', 'options' => $categories, 'required' => TRUE)) ?></div>
				<div class="col-md-3"><?= ui_input(array('name' => 'address', 'label' => 'Alamat', 'maxlength' => 400)) ?></div>
			</div>
			<button class="btn btn-primary" type="submit">Buat draft</button>
		</form>
	</div>
	<?php endif; ?>
</div>

<div class="card shadow-sm">
	<div class="card-header"><h2 class="h6 mb-0">Lokasi publik</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Nama</th><th scope="col">Jenis</th><th scope="col">Koordinat</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead>
			<tbody>
			<?php foreach ($places as $place): ?>
				<tr>
					<th scope="row"><?= e($place->name) ?></th>
					<td><?= e($place_types[$place->place_type] ?? $place->place_type) ?></td>
					<td><?= $place->latitude === NULL ? '—' : e($place->latitude.', '.$place->longitude) ?>
						<?= (int) $place->is_sensitive === 1 ? '<br><span class="small text-muted">ditandai sensitif, tidak dipetakan publik</span>' : '' ?></td>
					<td><?= $place->verification_status === 'verified' ? '<span class="badge badge-success">terverifikasi</span>' : '<span class="badge badge-secondary">belum</span>' ?></td>
					<td>
						<?php if ($can_publish): ?>
						<form class="d-inline" method="post" action="<?= site_url('admin/fasilitas/lokasi/'.rawurlencode($place->public_id).'/verifikasi') ?>" data-once>
							<?= csrf_field() ?>
							<input type="hidden" name="verified" value="<?= $place->verification_status === 'verified' ? '0' : '1' ?>">
							<button class="btn btn-sm btn-outline-secondary" type="submit"><?= $place->verification_status === 'verified' ? 'Cabut' : 'Verifikasi' ?></button>
						</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($places)): ?><tr><td colspan="5" class="text-muted">Belum ada lokasi.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php if ($can_edit): ?>
	<div class="card-body border-top">
		<h3 class="h6">Tambah lokasi</h3>
		<form method="post" action="<?= site_url('admin/fasilitas/lokasi/simpan') ?>" data-once>
			<?= csrf_field() ?>
			<div class="form-row">
				<div class="col-md-4"><?= ui_input(array('name' => 'name', 'label' => 'Nama lokasi', 'maxlength' => 180, 'required' => TRUE)) ?></div>
				<div class="col-md-3"><?= ui_select(array('name' => 'place_type', 'label' => 'Jenis', 'options' => $place_types, 'required' => TRUE)) ?></div>
				<div class="col-md-5"><?= ui_input(array('name' => 'address', 'label' => 'Alamat', 'maxlength' => 400)) ?></div>
			</div>
			<div class="form-row">
				<div class="col-md-3"><?= ui_input(array('name' => 'latitude', 'label' => 'Lintang')) ?></div>
				<div class="col-md-3"><?= ui_input(array('name' => 'longitude', 'label' => 'Bujur')) ?></div>
				<div class="col-md-6 d-flex align-items-center">
					<div class="form-check mt-3">
						<input class="form-check-input" type="checkbox" id="is_sensitive" name="is_sensitive" value="1">
						<label class="form-check-label" for="is_sensitive">Lokasi sensitif (tidak ditampilkan di peta publik)</label>
					</div>
				</div>
			</div>
			<button class="btn btn-outline-primary" type="submit">Simpan lokasi</button>
		</form>
	</div>
	<?php endif; ?>
</div>
