<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="page-heading">
	<div>
		<h1>Buat laporan</h1>
		<p>Laporan ini terhubung dengan akun Anda sehingga dapat dipantau dari dashboard.</p>
	</div>
	<a class="btn btn-outline-primary" href="<?= site_url('warga/laporan') ?>">Kembali ke daftar</a>
</div>

<?php if ( ! empty($submit_error)): ?>
	<div class="alert alert-danger" role="alert"><?= e($submit_error) ?></div>
<?php endif; ?>
<?= ui_error_summary(array('report_type' => 'Jenis laporan', 'category_id' => 'Kategori', 'title' => 'Judul', 'description' => 'Uraian', 'incident_date' => 'Tanggal kejadian', 'location_text' => 'Lokasi', 'lampiran' => 'Lampiran', 'statement' => 'Pernyataan')) ?>

<form class="card shadow-sm" method="post" action="<?= site_url('warga/laporan') ?>" enctype="multipart/form-data" novalidate data-report-type-form data-once>
	<div class="card-body">
		<?= csrf_field() ?>
		<input type="hidden" name="idempotency_key" value="<?= e($idempotency_key) ?>">
		<script type="application/json" id="field-rules"><?= json_encode($field_rules) ?></script>

		<fieldset class="form-group">
			<legend class="col-form-label p-0">Jenis laporan <span class="required-mark" aria-hidden="true">*</span></legend>
			<?php foreach ($report_types as $value => $label): ?>
			<div class="custom-control custom-radio">
				<input class="custom-control-input" type="radio" name="report_type" id="rt_<?= e($value) ?>" value="<?= e($value) ?>" required <?= old('report_type') === $value ? 'checked' : '' ?>>
				<label class="custom-control-label" for="rt_<?= e($value) ?>"><?= e($label) ?></label>
			</div>
			<?php endforeach; ?>
			<?= field_error('report_type') ?>
		</fieldset>

		<?= ui_select(array('name' => 'category_id', 'label' => 'Kategori', 'required' => TRUE, 'options' => $categories, 'placeholder_option' => 'Pilih kategori…')) ?>
		<?= ui_input(array('name' => 'title', 'label' => 'Judul laporan', 'required' => TRUE, 'minlength' => 10, 'maxlength' => 180, 'help' => '10–180 karakter.')) ?>
		<?= ui_textarea(array('name' => 'description', 'label' => 'Uraian', 'required' => TRUE, 'minlength' => 30, 'maxlength' => 10000, 'rows' => 7,
			'help' => 'Jelaskan kejadian, waktu, dan dampaknya. Hindari data pribadi yang tidak diperlukan.')) ?>

		<div data-field-group="incident_date">
			<?= ui_input(array('name' => 'incident_date', 'label' => 'Tanggal kejadian', 'type' => 'date', 'max' => $this->clock->now()->setTimezone(local_tz())->format('Y-m-d'))) ?>
		</div>
		<div data-field-group="location">
			<?= ui_input(array('name' => 'location_text', 'label' => 'Lokasi', 'maxlength' => 255, 'help' => 'Sebutkan dusun/RT/RW atau patokan.')) ?>
		</div>
		<div class="form-group" data-field-group="map_point">
			<span class="col-form-label p-0 d-block">Titik lokasi <span class="text-muted font-weight-normal">(opsional)</span></span>
			<input type="hidden" id="latitude" name="latitude" value="<?= e(old('latitude')) ?>">
			<input type="hidden" id="longitude" name="longitude" value="<?= e(old('longitude')) ?>">
			<button class="btn btn-outline-primary btn-sm" type="button" data-geolocate data-status="geo-status"><i class="fas fa-crosshairs mr-1" aria-hidden="true"></i> Gunakan lokasi perangkat</button>
			<button class="btn btn-link btn-sm" type="button" data-geoclear data-status="geo-status" <?= old('latitude') === '' ? 'hidden' : '' ?>>Hapus titik</button>
			<p class="form-text small mb-0" id="geo-status" role="status">Lokasi hanya diambil setelah Anda menekan tombol.</p>
		</div>

		<div class="form-group">
			<label for="lampiran">Lampiran <span class="text-muted font-weight-normal">(opsional)</span></label>
			<input class="form-control-file" type="file" id="lampiran" name="lampiran[]" multiple
				accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"
				data-max-files="<?= (int) $upload_rules['max_files'] ?>" data-max-bytes="<?= (int) $upload_rules['max_file_bytes'] ?>" data-max-total="<?= (int) $upload_rules['max_total_bytes'] ?>"
				aria-describedby="lampiran-help lampiran-status">
			<small class="form-text" id="lampiran-help">Maksimal <?= (int) $upload_rules['max_files'] ?> berkas (masing-masing <?= format_bytes_id($upload_rules['max_file_bytes']) ?>, total <?= format_bytes_id($upload_rules['max_total_bytes']) ?>): JPG, PNG, WebP, PDF. Metadata lokasi foto dihapus otomatis.</small>
			<p class="small mb-0" id="lampiran-status" role="status"></p>
			<?= field_error('lampiran') ?>
		</div>

		<div class="custom-control custom-checkbox mb-2">
			<input class="custom-control-input" type="checkbox" value="1" id="confidential" name="confidential" <?= old('confidential') ? 'checked' : '' ?>>
			<label class="custom-control-label" for="confidential">Perlakukan isi laporan sebagai rahasia (akses petugas dibatasi dan tercatat).</label>
		</div>
		<div class="custom-control custom-checkbox mb-3">
			<input class="custom-control-input" type="checkbox" value="1" id="hide_identity" name="hide_identity" <?= old('hide_identity') ? 'checked' : '' ?>>
			<label class="custom-control-label" for="hide_identity">Sembunyikan identitas saya dari petugas biasa. <span class="text-muted d-block small">Sistem tetap mengetahui pemilik akun; pembukaan identitas memerlukan izin khusus dan tercatat pada audit. Ini bukan laporan anonim penuh — gunakan <a href="<?= site_url('lapor') ?>">formulir publik</a> bila Anda tidak ingin laporan dikaitkan dengan akun.</span></label>
		</div>

		<div class="custom-control custom-checkbox mb-3">
			<input class="custom-control-input<?= isset($this->form_errors['statement']) ? ' is-invalid' : '' ?>" type="checkbox" value="1" id="statement" name="statement" required <?= old('statement') ? 'checked' : '' ?>>
			<label class="custom-control-label" for="statement">Saya memastikan isi laporan sesuai yang ingin saya sampaikan dan memahami ketentuan layanan.</label>
			<?= field_error('statement') ?>
		</div>
	</div>
	<div class="card-footer bg-white d-flex justify-content-end gap-2">
		<a class="btn btn-outline-secondary mr-2" href="<?= site_url('warga/laporan') ?>">Batal</a>
		<button class="btn btn-primary" type="submit"><i class="fas fa-paper-plane mr-1" aria-hidden="true"></i> Kirim laporan</button>
	</div>
</form>
