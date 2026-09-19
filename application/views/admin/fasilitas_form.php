<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Form satu fasilitas beserta layanan dan alur penerbitannya. */
$hours = $facility->service_hours_json ? json_decode($facility->service_hours_json, TRUE) : array();
$place_options = array('' => '— tanpa lokasi terdaftar —');
foreach ($places as $place) { $place_options[(string) $place->id] = $place->name; }
$source_options = array('' => '— tidak dari dokumen sumber —');
foreach ($sources as $source) { $source_options[(string) $source->id] = $source->source_code.' — '.$source->title; }
?>
<div class="page-heading">
	<div>
		<h1><?= e($facility->name) ?></h1>
		<p>Status: <?= e($statuses[$facility->publication_status] ?? $facility->publication_status) ?>
			<?= $facility->verification_status === 'verified' ? '· terverifikasi' : '· belum diverifikasi' ?></p>
	</div>
	<div><a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/fasilitas') ?>">Kembali ke direktori</a></div>
</div>

<?= ui_error_summary($this->form_errors) ?>

<?php if ($blockers): ?>
<div class="alert alert-warning" role="status">
	<strong>Belum siap terbit.</strong>
	<ul class="mb-0"><?php foreach ($blockers as $blocker): ?><li><?= e($blocker) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form class="card shadow-sm mb-4" method="post" action="<?= site_url('admin/fasilitas/'.rawurlencode($facility->public_id).'/simpan') ?>" data-once>
	<div class="card-body">
		<?= csrf_field() ?>
		<div class="form-row">
			<div class="col-md-6"><?= ui_input(array('name' => 'name', 'label' => 'Nama fasilitas', 'maxlength' => 200, 'value' => $facility->name, 'required' => TRUE)) ?></div>
			<div class="col-md-3"><?= ui_select(array('name' => 'category', 'label' => 'Kategori', 'options' => $categories, 'value' => $facility->category, 'required' => TRUE)) ?></div>
			<div class="col-md-3"><?= ui_input(array('name' => 'slug', 'label' => 'Slug', 'maxlength' => 220, 'value' => $facility->slug)) ?></div>
		</div>
		<?= ui_textarea(array('name' => 'description', 'label' => 'Deskripsi', 'maxlength' => 1000, 'rows' => 4, 'value' => (string) $facility->description)) ?>
		<div class="form-row">
			<div class="col-md-6"><?= ui_input(array('name' => 'manager_name', 'label' => 'Pengelola', 'maxlength' => 180, 'value' => (string) $facility->manager_name)) ?></div>
			<div class="col-md-6"><?= ui_select(array('name' => 'place_id', 'label' => 'Lokasi terdaftar', 'options' => $place_options, 'value' => (string) $facility->place_id)) ?></div>
		</div>
		<?= ui_input(array('name' => 'address', 'label' => 'Alamat', 'maxlength' => 400, 'value' => (string) $facility->address,
			'help' => 'Isi alamat atau pilih lokasi terdaftar; salah satunya wajib ada.')) ?>
		<?= ui_textarea(array('name' => 'accessibility', 'label' => 'Aksesibilitas', 'maxlength' => 600, 'rows' => 3, 'value' => (string) $facility->accessibility)) ?>

		<fieldset class="form-group">
			<legend class="form-label">Jam layanan</legend>
			<?php for ($i = 0; $i < 8; $i++): $row = $hours[$i] ?? array('label' => '', 'value' => ''); ?>
			<div class="form-row mb-2">
				<div class="col-5"><input class="form-control" type="text" name="service_hours[<?= $i ?>][label]" maxlength="60" value="<?= e($row['label']) ?>" placeholder="Hari" aria-label="Hari baris <?= $i + 1 ?>"></div>
				<div class="col-7"><input class="form-control" type="text" name="service_hours[<?= $i ?>][value]" maxlength="80" value="<?= e($row['value']) ?>" placeholder="Jam" aria-label="Jam baris <?= $i + 1 ?>"></div>
			</div>
			<?php endfor; ?>
		</fieldset>

		<div class="form-row">
			<div class="col-md-6"><?= ui_input(array('name' => 'public_contact', 'label' => 'Kontak publik', 'maxlength' => 255, 'value' => (string) $facility->public_contact)) ?></div>
			<div class="col-md-6 d-flex align-items-center">
				<div class="form-check mt-3">
					<input class="form-check-input" type="checkbox" id="contact_permission" name="contact_permission" value="1" <?= (int) $facility->contact_permission === 1 ? 'checked' : '' ?>>
					<label class="form-check-label" for="contact_permission">Izin publikasi kontak sudah dicatat</label>
				</div>
			</div>
		</div>
		<div class="form-row">
			<div class="col-md-3"><?= ui_input(array('name' => 'source_year', 'label' => 'Tahun data', 'value' => (string) $facility->source_year)) ?></div>
			<div class="col-md-5"><?= ui_select(array('name' => 'source_id', 'label' => 'Dokumen sumber', 'options' => $source_options, 'value' => (string) $facility->source_id)) ?></div>
			<div class="col-md-4"><?= ui_input(array('name' => 'source_note', 'label' => 'Catatan sumber', 'maxlength' => 255, 'value' => (string) $facility->source_note)) ?></div>
		</div>
		<div class="form-check mb-0">
			<input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (int) $facility->is_active === 1 ? 'checked' : '' ?>>
			<label class="form-check-label" for="is_active">Fasilitas masih aktif</label>
		</div>
	</div>
	<div class="card-footer">
		<?php if ($can_edit): ?>
			<button class="btn btn-primary" type="submit">Simpan</button>
			<span class="small text-muted ml-2">Menyimpan mencabut verifikasi sebelumnya karena isinya berubah.</span>
		<?php else: ?>
			<p class="mb-0 small text-muted">Anda tidak punya izin menyunting fasilitas.</p>
		<?php endif; ?>
	</div>
</form>

<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Layanan</h2></div>
	<ul class="list-group list-group-flush">
		<?php foreach ($facility->services as $service): ?>
		<li class="list-group-item d-flex justify-content-between align-items-center">
			<span><strong><?= e($service->label) ?></strong><?= $service->description ? ' — '.e($service->description) : '' ?></span>
			<?php if ($can_edit): ?>
			<form method="post" action="<?= site_url('admin/fasilitas/'.rawurlencode($facility->public_id).'/layanan/hapus') ?>" data-once>
				<?= csrf_field() ?><input type="hidden" name="service_id" value="<?= (int) $service->id ?>">
				<button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
			</form>
			<?php endif; ?>
		</li>
		<?php endforeach; ?>
		<?php if (empty($facility->services)): ?><li class="list-group-item text-muted">Belum ada layanan.</li><?php endif; ?>
	</ul>
	<?php if ($can_edit): ?>
	<div class="card-body border-top">
		<form method="post" action="<?= site_url('admin/fasilitas/'.rawurlencode($facility->public_id).'/layanan/tambah') ?>" data-once>
			<?= csrf_field() ?>
			<div class="form-row">
				<div class="col-md-4"><?= ui_input(array('name' => 'label', 'label' => 'Nama layanan', 'maxlength' => 160, 'required' => TRUE)) ?></div>
				<div class="col-md-6"><?= ui_input(array('name' => 'description', 'label' => 'Keterangan', 'maxlength' => 400)) ?></div>
				<div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary mb-3" type="submit">Tambah</button></div>
			</div>
		</form>
	</div>
	<?php endif; ?>
</div>

<?php if ($can_publish): ?>
<div class="card shadow-sm">
	<div class="card-header"><h2 class="h6 mb-0">Alur publikasi</h2></div>
	<div class="card-body">
		<?php foreach (array(
			'verifikasi' => 'Tandai terverifikasi',
			'batal-verifikasi' => 'Cabut verifikasi',
			'terbitkan' => 'Terbitkan',
			'tarik' => 'Tarik dari publik',
			'arsipkan' => 'Arsipkan',
		) as $action => $label): ?>
		<form class="form-inline d-inline-block mr-2 mb-2" method="post" action="<?= site_url('admin/fasilitas/'.rawurlencode($facility->public_id).'/alur/'.$action) ?>" data-once>
			<?= csrf_field() ?>
			<?php if (in_array($action, array('tarik', 'arsipkan'), TRUE)): ?>
				<input class="form-control form-control-sm mr-2" type="text" name="reason" maxlength="500" placeholder="Alasan" aria-label="Alasan <?= e($label) ?>">
			<?php endif; ?>
			<button class="btn btn-sm btn-outline-secondary" type="submit"><?= e($label) ?></button>
		</form>
		<?php endforeach; ?>
	</div>
</div>
<?php endif; ?>
