<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="page-heading">
	<div>
		<h1>Input laporan loket</h1>
		<p>Catat laporan warga yang disampaikan langsung di kantor desa. Anda tercatat sebagai pencatat, bukan pelapor.</p>
	</div>
	<a class="btn btn-outline-primary" href="<?= site_url('admin/laporan') ?>">Kembali ke daftar</a>
</div>

<?php if ( ! empty($submit_error)): ?><div class="alert alert-danger" role="alert"><?= e($submit_error) ?></div><?php endif; ?>
<?= ui_error_summary(array('reporter_mode' => 'Jenis pelapor', 'reporter_user_id' => 'Akun warga', 'contact_name' => 'Nama pelapor', 'report_type' => 'Jenis laporan', 'category_id' => 'Kategori', 'title' => 'Judul', 'description' => 'Uraian', 'location_text' => 'Lokasi', 'lampiran' => 'Lampiran')) ?>

<form class="card shadow-sm" method="post" action="<?= site_url('admin/laporan') ?>" enctype="multipart/form-data" novalidate data-report-type-form data-once>
	<div class="card-body">
		<?= csrf_field() ?>
		<input type="hidden" name="idempotency_key" value="<?= e($idempotency_key) ?>">
		<script type="application/json" id="field-rules"><?= json_encode($field_rules) ?></script>

		<h2 class="h6">1. Pelapor</h2>
		<fieldset class="form-group">
			<legend class="sr-only">Jenis pelapor</legend>
			<div class="custom-control custom-radio">
				<input class="custom-control-input" type="radio" name="reporter_mode" id="rm-account" value="account" data-toggle-panel="reporter" <?= old('reporter_mode') === 'account' ? 'checked' : '' ?>>
				<label class="custom-control-label" for="rm-account">Pelapor memiliki akun warga</label>
			</div>
			<div class="custom-control custom-radio">
				<input class="custom-control-input" type="radio" name="reporter_mode" id="rm-identified" value="identified" data-toggle-panel="reporter" <?= old('reporter_mode') === 'identified' ? 'checked' : '' ?>>
				<label class="custom-control-label" for="rm-identified">Tanpa akun, bersedia memberi identitas</label>
			</div>
			<div class="custom-control custom-radio">
				<input class="custom-control-input" type="radio" name="reporter_mode" id="rm-anonymous" value="anonymous" data-toggle-panel="reporter" <?= old('reporter_mode') === 'anonymous' || old('reporter_mode') === '' ? 'checked' : '' ?>>
				<label class="custom-control-label" for="rm-anonymous">Pelapor anonim (tidak memberi identitas)</label>
			</div>
			<?= field_error('reporter_mode') ?>
		</fieldset>

		<div data-panel-for="reporter" data-panel-value="account" hidden>
			<div class="form-group">
				<label for="resident-search">Cari akun warga</label>
				<input class="form-control" type="search" id="resident-search" maxlength="60" autocomplete="off" aria-describedby="resident-help">
				<small class="form-text" id="resident-help">Ketik minimal 3 karakter nama atau username. Hasil dibatasi dan kontak ditampilkan tersamar.</small>
				<div id="resident-results" class="mt-2" role="status"></div>
			</div>
			<input type="hidden" id="reporter_user_id" name="reporter_user_id" value="<?= e(old('reporter_user_id')) ?>">
			<?= field_error('reporter_user_id') ?>
		</div>

		<div data-panel-for="reporter" data-panel-value="identified" hidden>
			<div class="alert alert-info small">Kontak pelapor disimpan terenkripsi dan terpisah dari isi laporan. Isi hanya bila pelapor bersedia.</div>
			<?= ui_input(array('name' => 'contact_name', 'label' => 'Nama pelapor', 'maxlength' => 100, 'raw_attrs' => 'data-required-when-visible')) ?>
			<?= ui_input(array('name' => 'contact_phone', 'label' => 'Telepon', 'maxlength' => 30, 'inputmode' => 'tel')) ?>
			<?= ui_input(array('name' => 'contact_email', 'label' => 'Email', 'type' => 'email', 'maxlength' => 191)) ?>
		</div>

		<div data-panel-for="reporter" data-panel-value="anonymous" hidden>
			<div class="alert alert-warning small mb-0">Sistem akan membuat kode akses rahasia. Kode hanya ditampilkan sekali setelah laporan tersimpan — berikan kepada pelapor dan jangan disimpan petugas.</div>
		</div>

		<hr>
		<h2 class="h6">2. Isi laporan</h2>
		<fieldset class="form-group">
			<legend class="col-form-label p-0">Jenis laporan <span class="required-mark" aria-hidden="true">*</span></legend>
			<?php foreach ($report_types as $value => $label): ?>
			<div class="custom-control custom-radio custom-control-inline">
				<input class="custom-control-input" type="radio" name="report_type" id="rt_<?= e($value) ?>" value="<?= e($value) ?>" required <?= old('report_type') === $value ? 'checked' : '' ?>>
				<label class="custom-control-label" for="rt_<?= e($value) ?>"><?= e($label) ?></label>
			</div>
			<?php endforeach; ?>
			<?= field_error('report_type') ?>
		</fieldset>

		<?= ui_select(array('name' => 'category_id', 'label' => 'Kategori', 'required' => TRUE, 'options' => $categories, 'placeholder_option' => 'Pilih kategori…')) ?>
		<?= ui_input(array('name' => 'title', 'label' => 'Judul laporan', 'required' => TRUE, 'minlength' => 10, 'maxlength' => 180)) ?>
		<?= ui_textarea(array('name' => 'description', 'label' => 'Uraian (sesuai penyampaian warga)', 'required' => TRUE, 'minlength' => 30, 'maxlength' => 10000, 'rows' => 6)) ?>
		<div data-field-group="incident_date">
			<?= ui_input(array('name' => 'incident_date', 'label' => 'Tanggal kejadian', 'type' => 'date', 'max' => $this->clock->now()->setTimezone(local_tz())->format('Y-m-d'))) ?>
		</div>
		<div data-field-group="location">
			<?= ui_input(array('name' => 'location_text', 'label' => 'Lokasi', 'maxlength' => 255)) ?>
		</div>
		<div class="form-group">
			<label for="lampiran">Lampiran <span class="text-muted font-weight-normal">(opsional)</span></label>
			<input class="form-control-file" type="file" id="lampiran" name="lampiran[]" multiple
				accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"
				data-max-files="<?= (int) $upload_rules['max_files'] ?>" data-max-bytes="<?= (int) $upload_rules['max_file_bytes'] ?>" data-max-total="<?= (int) $upload_rules['max_total_bytes'] ?>"
				aria-describedby="lampiran-help">
			<small class="form-text" id="lampiran-help">Maksimal <?= (int) $upload_rules['max_files'] ?> berkas: JPG, PNG, WebP, PDF.</small>
			<?= field_error('lampiran') ?>
		</div>
		<div class="custom-control custom-checkbox">
			<input class="custom-control-input" type="checkbox" value="1" id="confidential" name="confidential" <?= old('confidential') ? 'checked' : '' ?>>
			<label class="custom-control-label" for="confidential">Tandai sebagai laporan rahasia</label>
		</div>
	</div>
	<div class="card-footer bg-white text-right">
		<button class="btn btn-primary" type="submit"><i class="fas fa-save mr-1" aria-hidden="true"></i> Simpan laporan</button>
	</div>
</form>

<script src="<?= asset_url('admin/js/front-desk.js') ?>" defer></script>
