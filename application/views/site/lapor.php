<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="page-hero">
	<div class="container-site">
		<nav class="breadcrumb-nav" aria-label="Breadcrumb">
			<ol class="breadcrumb bg-transparent p-0 mb-2">
				<li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li>
				<li class="breadcrumb-item"><a href="<?= site_url('layanan') ?>">Layanan Warga</a></li>
				<li class="breadcrumb-item active" aria-current="page">Buat Laporan</li>
			</ol>
		</nav>
		<h1>Buat laporan</h1>
		<p>Sampaikan pengaduan, aspirasi, atau permintaan informasi kepada Pemerintah Desa Cihawuk. Formulir ini tidak meminta nama, NIK, atau nomor telepon.</p>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<div class="row g-4">
			<div class="col-lg-8">
				<?php if ( ! empty($submit_error)): ?>
					<div class="alert alert-danger d-flex gap-2" role="alert"><?= icon('alert-circle') ?><div><?= e($submit_error) ?></div></div>
				<?php endif; ?>
				<?= ui_error_summary(array(
					'report_type' => 'Jenis laporan', 'category_id' => 'Kategori', 'title' => 'Judul', 'description' => 'Uraian',
					'incident_date' => 'Tanggal kejadian', 'location_text' => 'Lokasi', 'latitude' => 'Titik lokasi',
					'lampiran' => 'Lampiran', 'statement' => 'Pernyataan',
				)) ?>

				<form class="card-soft card-pad" method="post" action="<?= site_url('lapor') ?>" enctype="multipart/form-data" novalidate data-stepper-form data-report-type-form data-once>
					<?= csrf_field() ?>
					<input type="hidden" name="idempotency_key" value="<?= e($idempotency_key) ?>">
					<div class="visually-hidden" aria-hidden="true">
						<label for="website">Jangan isi field ini</label>
						<input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
					</div>
					<script type="application/json" id="field-rules"><?= json_encode($field_rules) ?></script>

					<ol class="stepper" aria-label="Langkah pengisian">
						<li aria-current="step">Jenis &amp; uraian</li>
						<li>Lokasi &amp; lampiran</li>
						<li>Periksa &amp; kirim</li>
					</ol>

					<!-- Langkah 1 -->
					<div class="form-step">
						<h2 class="form-step-title h5">1. Jenis dan uraian laporan</h2>
						<p class="form-text mb-3">Tuliskan kejadian atau usulan Anda sejelas mungkin.</p>

						<fieldset class="mb-3">
							<legend class="form-label">Jenis laporan <span class="required-mark" aria-hidden="true">*</span></legend>
							<?php foreach ($report_types as $value => $label): ?>
							<div class="form-check">
								<input class="form-check-input<?= isset($this->form_errors['report_type']) ? ' is-invalid' : '' ?>" type="radio" name="report_type" id="report_type_<?= e($value) ?>" value="<?= e($value) ?>" required
									<?= old('report_type') === $value ? 'checked' : '' ?>>
								<label class="form-check-label" for="report_type_<?= e($value) ?>"><?= e($label) ?></label>
							</div>
							<?php endforeach; ?>
							<?= field_error('report_type') ?>
						</fieldset>

						<?= ui_select(array('name' => 'category_id', 'label' => 'Kategori', 'required' => TRUE, 'options' => $categories,
							'placeholder_option' => 'Pilih kategori…', 'help' => 'Kategori sensitif otomatis diperlakukan sebagai laporan rahasia.')) ?>

						<?= ui_input(array('name' => 'title', 'label' => 'Judul laporan', 'required' => TRUE, 'minlength' => 10, 'maxlength' => 180,
							'help' => '10–180 karakter. Contoh: "Lampu jalan mati di jalur menuju kebun".')) ?>

						<?= ui_textarea(array('name' => 'description', 'label' => 'Uraian', 'required' => TRUE, 'minlength' => 30, 'maxlength' => 10000, 'rows' => 8,
							'help' => 'Ceritakan apa yang terjadi, kapan, dan siapa/apa yang terdampak. Hindari menuliskan data pribadi yang tidak perlu.')) ?>

						<div class="step-nav d-flex justify-content-end">
							<button class="btn btn-primary" type="button" data-step-next>Lanjut <?= icon('arrow-right') ?></button>
						</div>
					</div>

					<!-- Langkah 2 -->
					<div class="form-step" hidden>
						<h2 class="form-step-title h5">2. Lokasi dan lampiran</h2>
						<p class="form-text mb-3">Bagian ini opsional, kecuali kategori tertentu membutuhkan lokasi.</p>

						<div data-field-group="incident_date">
							<?= ui_input(array('name' => 'incident_date', 'label' => 'Tanggal kejadian', 'type' => 'date', 'max' => $this->clock->now()->setTimezone(local_tz())->format('Y-m-d'))) ?>
						</div>

						<div data-field-group="location">
							<?= ui_input(array('name' => 'location_text', 'label' => 'Lokasi', 'maxlength' => 255,
								'help' => 'Sebutkan dusun/RT/RW atau patokan yang mudah dikenali.')) ?>
						</div>

						<div class="mb-3" data-field-group="map_point">
							<span class="form-label d-block">Titik lokasi di peta <span class="text-muted fw-normal">(opsional)</span></span>
							<input type="hidden" id="latitude" name="latitude" value="<?= e(old('latitude')) ?>">
							<input type="hidden" id="longitude" name="longitude" value="<?= e(old('longitude')) ?>">
							<div class="d-flex flex-wrap gap-2">
								<button class="btn btn-outline-primary btn-sm" type="button" data-geolocate data-status="geo-status"><?= icon('crosshair') ?> Gunakan lokasi perangkat</button>
								<button class="btn btn-link btn-sm" type="button" data-geoclear data-status="geo-status" <?= old('latitude') === '' ? 'hidden' : '' ?>>Hapus titik lokasi</button>
							</div>
							<p class="form-text" id="geo-status" role="status">Lokasi hanya diambil setelah Anda menekan tombol di atas.</p>
							<?= field_error('latitude') ?>
						</div>

						<div class="mb-3">
							<label class="form-label" for="lampiran">Lampiran <span class="text-muted fw-normal">(opsional)</span></label>
							<input class="form-control<?= isset($this->form_errors['lampiran']) ? ' is-invalid' : '' ?>" type="file" id="lampiran" name="lampiran[]" multiple
								accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"
								data-max-files="<?= (int) $upload_rules['max_files'] ?>" data-max-bytes="<?= (int) $upload_rules['max_file_bytes'] ?>" data-max-total="<?= (int) $upload_rules['max_total_bytes'] ?>"
								aria-describedby="lampiran-help lampiran-status">
							<div class="form-text" id="lampiran-help">Maksimal <?= (int) $upload_rules['max_files'] ?> berkas, masing-masing <?= format_bytes_id($upload_rules['max_file_bytes']) ?>, total <?= format_bytes_id($upload_rules['max_total_bytes']) ?>. Format: JPG, PNG, WebP, atau PDF. Data lokasi pada foto (EXIF) dihapus otomatis saat disimpan.</div>
							<p class="form-text" id="lampiran-status" role="status"></p>
							<?= field_error('lampiran') ?>
						</div>

						<div class="form-check mb-3">
							<input class="form-check-input" type="checkbox" value="1" id="confidential" name="confidential" <?= old('confidential') ? 'checked' : '' ?>>
							<label class="form-check-label" for="confidential">Perlakukan isi laporan ini sebagai rahasia (hanya petugas berizin khusus yang dapat membukanya).</label>
						</div>

						<div class="step-nav d-flex justify-content-between">
							<button class="btn btn-outline-primary" type="button" data-step-prev><?= icon('arrow-left') ?> Kembali</button>
							<button class="btn btn-primary" type="button" data-step-next>Lanjut <?= icon('arrow-right') ?></button>
						</div>
					</div>

					<!-- Langkah 3 -->
					<div class="form-step" hidden>
						<h2 class="form-step-title h5">3. Periksa dan kirim</h2>
						<dl class="fact-list mb-3">
							<div><dt>Jenis</dt><dd data-review-for="report_type">—</dd></div>
							<div><dt>Kategori</dt><dd data-review-for="category_id">—</dd></div>
							<div><dt>Judul</dt><dd data-review-for="title">—</dd></div>
							<div><dt>Lokasi</dt><dd data-review-for="location_text">—</dd></div>
							<div><dt>Tanggal kejadian</dt><dd data-review-for="incident_date">—</dd></div>
							<div><dt>Lampiran</dt><dd data-review-for="lampiran">—</dd></div>
						</dl>

						<div class="privacy-panel mb-3">
							<h3>Sebelum mengirim</h3>
							<ul>
								<li>Laporan ini <strong>tidak dikaitkan dengan akun mana pun</strong>. Kami tidak menyimpan nama, NIK, atau kontak Anda.</li>
								<li>Setelah terkirim, Anda menerima <strong>nomor tiket</strong> dan <strong>kode akses rahasia</strong>. Kode hanya ditampilkan sekali — simpan baik-baik.</li>
								<li>Foto dan narasi yang Anda unggah sendiri dapat memuat identitas Anda; periksa kembali isinya.</li>
								<li>Log jaringan keamanan pada server tetap ada dengan retensi terbatas, seperti pada situs lain.</li>
							</ul>
						</div>

						<div class="form-check mb-4">
							<input class="form-check-input<?= isset($this->form_errors['statement']) ? ' is-invalid' : '' ?>" type="checkbox" value="1" id="statement" name="statement" required <?= old('statement') ? 'checked' : '' ?> <?= isset($this->form_errors['statement']) ? 'aria-describedby="err-statement"' : '' ?>>
							<label class="form-check-label" for="statement">Saya memastikan isi laporan sesuai yang ingin saya sampaikan dan memahami <a href="<?= site_url('privasi') ?>" target="_blank" rel="noopener">kebijakan privasi</a> serta <a href="<?= site_url('ketentuan') ?>" target="_blank" rel="noopener">ketentuan layanan</a>.</label>
							<?= field_error('statement') ?>
						</div>

						<div class="step-nav d-flex justify-content-between">
							<button class="btn btn-outline-primary" type="button" data-step-prev><?= icon('arrow-left') ?> Kembali</button>
						</div>
						<button class="btn btn-primary btn-lg w-100 mt-3" type="submit"><?= icon('send') ?> <span data-loading-label="Mengirim laporan…">Kirim laporan</span></button>
					</div>
				</form>
			</div>

			<aside class="col-lg-4">
				<div class="card-soft card-pad mb-3">
					<h2 class="h6"><?= icon('shield') ?> Privasi laporan anonim</h2>
					<p class="small mb-2">Semua laporan bersifat privat: tidak ditampilkan di halaman publik.</p>
					<p class="small mb-0">Petugas melihat isi laporan untuk menindaklanjuti. Laporan berkategori sensitif hanya dapat dibuka petugas berizin khusus dan setiap aksesnya tercatat.</p>
				</div>
				<div class="card-soft card-pad mb-3">
					<h2 class="h6"><?= icon('key') ?> Kode akses</h2>
					<p class="small mb-0">Kode akses adalah satu-satunya kunci untuk melacak laporan anonim. Jika hilang, kami tidak dapat memastikan kepemilikan laporan dan kode tidak dapat dikirim ulang.</p>
				</div>
				<?php if ( ! empty($logged_in)): ?>
				<div class="card-soft card-pad">
					<h2 class="h6"><?= icon('user') ?> Anda sedang masuk</h2>
					<p class="small">Formulir ini tetap mengirim laporan <strong>tanpa mengaitkan akun Anda</strong>. Untuk laporan yang terhubung akun dan dapat dipantau dari dashboard, gunakan formulir warga.</p>
					<a class="btn btn-outline-primary btn-sm" href="<?= site_url('warga/laporan/buat') ?>">Buat laporan dari dashboard</a>
				</div>
				<?php endif; ?>
			</aside>
		</div>
	</div>
</section>
