<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="page-heading">
	<div>
		<h1>Pengaturan</h1>
		<p>Konfigurasi operasional. Rahasia (key enkripsi, kredensial SMTP/DB) berada di environment dan tidak ditampilkan di sini.</p>
	</div>
</div>

<ul class="nav nav-pills mb-4" role="tablist">
	<?php foreach ($sections as $key => $label): ?>
	<li class="nav-item mr-2 mb-2">
		<a class="nav-link <?= $section === $key ? 'active' : '' ?>" href="<?= site_url('admin/pengaturan/'.$key) ?>" <?= $section === $key ? 'aria-current="page"' : '' ?>><?= $label ?></a>
	</li>
	<?php endforeach; ?>
</ul>

<?php if ($section === 'umum'): ?>
<form class="card shadow-sm" method="post" action="<?= site_url('admin/pengaturan/umum') ?>" data-once>
	<div class="card-body">
		<?= csrf_field() ?>
		<?= ui_input(array('name' => 'tagline', 'label' => 'Tagline situs', 'maxlength' => 150, 'value' => $values['tagline'])) ?>
		<?= ui_select(array('name' => 'hero_mode', 'label' => 'Mode hero beranda', 'options' => array('three' => 'Animasi Three.js (dengan fallback)', 'image' => 'Foto statis', 'video' => 'Video (butuh media video)'), 'value' => $values['hero_mode'],
			'help' => 'Mode animasi tetap memiliki fallback otomatis untuk perangkat terbatas, reduced-motion, Save-Data, atau tanpa WebGL.')) ?>
		<?= ui_select(array('name' => 'default_sla', 'label' => 'Kebijakan SLA bawaan', 'options' => $sla_codes, 'value' => $values['default_sla'])) ?>
		<div class="custom-control custom-checkbox">
			<input class="custom-control-input" type="checkbox" id="anonymous_enabled" name="anonymous_enabled" value="1" <?= $values['anonymous_enabled'] ? 'checked' : '' ?>>
			<label class="custom-control-label" for="anonymous_enabled">Izinkan laporan tanpa akun (anonim)</label>
		</div>
	</div>
	<div class="card-footer bg-white text-right"><button class="btn btn-primary" type="submit">Simpan</button></div>
</form>

<?php elseif ($section === 'modul'): ?>
<div class="alert alert-info" role="status">
	Modul yang <strong>nonaktif</strong> tidak dapat dibuka walaupun URL-nya diketahui. Menonaktifkan modul tidak
	menghapus data, berkas, histori, maupun log audit.
</div>
<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Status modul</h2></div>
	<div class="table-responsive">
		<table class="table table-sm datatable-plain mb-0">
			<thead>
				<tr><th scope="col">Modul</th><th scope="col">Status</th><th scope="col">Bergantung pada</th><th scope="col">Ubah status</th></tr>
			</thead>
			<tbody>
			<?php foreach ($modules as $module): ?>
				<tr>
					<td>
						<span class="font-weight-bold"><?= e($module->name) ?></span>
						<span class="d-block small text-muted"><code><?= e($module->code) ?></code></span>
					</td>
					<td><span class="chip-flag <?= $module->state === 'active' ? 'is-info' : '' ?>"><?= e($module_states[$module->state] ?? $module->state) ?></span></td>
					<td class="small text-muted"><?= $module->depends_on ? e(implode(', ', $module->depends_on)) : '—' ?></td>
					<td>
						<form method="post" action="<?= site_url('admin/pengaturan/modul') ?>" data-once>
							<?= csrf_field() ?>
							<input type="hidden" name="code" value="<?= e($module->code) ?>">
							<div class="form-row">
								<div class="col-12 col-md-4 mb-2">
									<label class="sr-only" for="state-<?= e($module->code) ?>">Status baru untuk <?= e($module->name) ?></label>
									<select class="form-control form-control-sm form-select" id="state-<?= e($module->code) ?>" name="state">
										<?php foreach ($module_states as $value => $label): ?>
											<option value="<?= e($value) ?>" <?= $module->state === $value ? 'selected' : '' ?>><?= e($label) ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="col-12 col-md-6 mb-2">
									<label class="sr-only" for="reason-<?= e($module->code) ?>">Alasan perubahan</label>
									<input class="form-control form-control-sm" id="reason-<?= e($module->code) ?>" name="reason" maxlength="500" placeholder="Alasan perubahan (wajib, minimal 10 karakter)">
								</div>
								<div class="col-12 col-md-2 mb-2">
									<button class="btn btn-outline-primary btn-sm btn-block" type="submit">Simpan</button>
								</div>
							</div>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
<div class="card shadow-sm">
	<div class="card-header"><h2 class="h6 mb-0">Riwayat perubahan terakhir</h2></div>
	<div class="card-body">
		<?php if (empty($module_history)): ?>
			<p class="text-muted mb-0">Belum ada perubahan status modul.</p>
		<?php else: ?>
			<ol class="list-unstyled mb-0">
			<?php foreach ($module_history as $row): ?>
				<li class="mb-3">
					<span class="font-weight-bold"><?= e($row->module_code) ?></span>
					<span class="text-muted">
						<?= e($module_states[$row->from_state] ?? (string) $row->from_state) ?> →
						<?= e($module_states[$row->to_state] ?? $row->to_state) ?>
					</span>
					<span class="d-block small text-muted">
						<?= format_wib($row->created_at) ?> · <?= e($row->actor_name ?: 'sistem') ?>
					</span>
					<span class="d-block small"><?= e($row->reason) ?></span>
				</li>
			<?php endforeach; ?>
			</ol>
		<?php endif; ?>
	</div>
</div>

<?php elseif ($section === 'kalender'): ?>
	<?php foreach ($calendars as $calendar): ?>
	<div class="card shadow-sm mb-4">
		<div class="card-header"><h2><?= e($calendar->name) ?><?= $calendar->is_example ? ' <span class="chip-flag is-warning">contoh</span>' : '' ?></h2></div>
		<div class="card-body">
			<form method="post" action="<?= site_url('admin/pengaturan/kalender') ?>" class="mb-4">
				<?= csrf_field() ?>
				<input type="hidden" name="calendar_id" value="<?= (int) $calendar->id ?>">
				<input type="hidden" name="action" value="schedule">
				<?= ui_input(array('name' => 'name', 'id' => 'cal-name-'.$calendar->id, 'label' => 'Nama kalender', 'maxlength' => 100, 'value' => $calendar->name)) ?>
				<div class="table-responsive">
					<table class="table table-sm mb-2">
						<caption class="sr-only">Jam kerja mingguan</caption>
						<thead><tr><th scope="col">Hari</th><th scope="col">Hari kerja</th><th scope="col">Mulai</th><th scope="col">Selesai</th></tr></thead>
						<tbody>
						<?php foreach ($days as $number => $label):
							$window = $calendar->schedule[(string) $number][0] ?? NULL; ?>
							<tr>
								<th scope="row"><?= e($label) ?></th>
								<td>
									<div class="custom-control custom-checkbox">
										<input class="custom-control-input" type="checkbox" id="act-<?= (int) $calendar->id ?>-<?= $number ?>" name="active_<?= $number ?>" value="1" <?= $window ? 'checked' : '' ?>>
										<label class="custom-control-label" for="act-<?= (int) $calendar->id ?>-<?= $number ?>"><span class="sr-only">Hari kerja <?= e($label) ?></span></label>
									</div>
								</td>
								<td><label class="sr-only" for="st-<?= (int) $calendar->id ?>-<?= $number ?>">Jam mulai <?= e($label) ?></label>
									<input class="form-control form-control-sm" type="time" id="st-<?= (int) $calendar->id ?>-<?= $number ?>" name="start_<?= $number ?>" value="<?= e($window['start'] ?? '08:00') ?>"></td>
								<td><label class="sr-only" for="en-<?= (int) $calendar->id ?>-<?= $number ?>">Jam selesai <?= e($label) ?></label>
									<input class="form-control form-control-sm" type="time" id="en-<?= (int) $calendar->id ?>-<?= $number ?>" name="end_<?= $number ?>" value="<?= e($window['end'] ?? '16:00') ?>"></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<div class="custom-control custom-checkbox mb-3">
					<input class="custom-control-input" type="checkbox" id="ex-<?= (int) $calendar->id ?>" name="is_example" value="1" <?= $calendar->is_example ? 'checked' : '' ?>>
					<label class="custom-control-label" for="ex-<?= (int) $calendar->id ?>">Tandai sebagai kalender contoh (belum dikonfirmasi kantor desa)</label>
				</div>
				<button class="btn btn-primary" type="submit">Simpan jadwal</button>
			</form>

			<h3 class="h6">Hari libur</h3>
			<?php if (empty($calendar->holidays)): ?>
				<p class="small text-muted">Belum ada hari libur terdaftar. Tambahkan sesuai kalender resmi agar perhitungan tenggat akurat.</p>
			<?php else: ?>
				<ul class="list-unstyled small">
					<?php foreach ($calendar->holidays as $holiday): ?>
					<li class="d-flex justify-content-between border-bottom py-1">
						<span><?= e(format_date_id($holiday->date)) ?> — <?= e($holiday->label) ?><?= $holiday->is_working_override ? ' <span class="chip-flag is-info">tetap hari kerja</span>' : '' ?></span>
						<form method="post" action="<?= site_url('admin/pengaturan/kalender') ?>">
							<?= csrf_field() ?>
							<input type="hidden" name="calendar_id" value="<?= (int) $calendar->id ?>">
							<input type="hidden" name="action" value="holiday_remove">
							<input type="hidden" name="holiday_id" value="<?= (int) $holiday->id ?>">
							<button class="btn btn-link btn-sm p-0" type="submit">Hapus</button>
						</form>
					</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<form method="post" action="<?= site_url('admin/pengaturan/kalender') ?>" class="form-row align-items-end">
				<?= csrf_field() ?>
				<input type="hidden" name="calendar_id" value="<?= (int) $calendar->id ?>">
				<input type="hidden" name="action" value="holiday_add">
				<div class="col-md-3"><?= ui_input(array('name' => 'date', 'id' => 'hd-'.$calendar->id, 'label' => 'Tanggal', 'type' => 'date', 'value' => '', 'wrap_class' => 'mb-2')) ?></div>
				<div class="col-md-5"><?= ui_input(array('name' => 'label', 'id' => 'hl-'.$calendar->id, 'label' => 'Keterangan', 'maxlength' => 150, 'value' => '', 'wrap_class' => 'mb-2')) ?></div>
				<div class="col-md-2 mb-2">
					<div class="custom-control custom-checkbox">
						<input class="custom-control-input" type="checkbox" id="wo-<?= (int) $calendar->id ?>" name="is_working_override" value="1">
						<label class="custom-control-label small" for="wo-<?= (int) $calendar->id ?>">Tetap kerja</label>
					</div>
				</div>
				<div class="col-md-2 mb-2"><button class="btn btn-outline-primary btn-block" type="submit">Tambah</button></div>
			</form>
		</div>
	</div>
	<?php endforeach; ?>

<?php elseif ($section === 'sla'): ?>
	<?php foreach ($policies as $policy):
		$rules = json_decode($policy->pause_rules_json, TRUE) ?: array(); ?>
	<form class="card shadow-sm mb-4" method="post" action="<?= site_url('admin/pengaturan/sla') ?>">
		<div class="card-header"><h2><?= e($policy->name) ?> <span class="text-muted small">(<?= e($policy->code) ?>)</span></h2></div>
		<div class="card-body">
			<?= csrf_field() ?>
			<input type="hidden" name="policy_id" value="<?= (int) $policy->id ?>">
			<?= ui_input(array('name' => 'name', 'id' => 'pn-'.$policy->id, 'label' => 'Nama kebijakan', 'maxlength' => 100, 'value' => $policy->name)) ?>
			<div class="form-row">
				<div class="col-md-3"><?= ui_input(array('name' => 'verification_days', 'id' => 'vd-'.$policy->id, 'label' => 'Verifikasi (hari kerja)', 'type' => 'number', 'min' => 1, 'max' => 365, 'value' => (string) $policy->verification_days)) ?></div>
				<div class="col-md-3"><?= ui_input(array('name' => 'first_response_days', 'id' => 'fr-'.$policy->id, 'label' => 'Respons awal (hari kerja)', 'type' => 'number', 'min' => 1, 'max' => 365, 'value' => (string) $policy->first_response_days)) ?></div>
				<div class="col-md-3"><?= ui_input(array('name' => 'confirmation_days', 'id' => 'cd-'.$policy->id, 'label' => 'Tanggapan pelapor (hari kalender)', 'type' => 'number', 'min' => 1, 'max' => 365, 'value' => (string) $policy->confirmation_days)) ?></div>
				<div class="col-md-3"><?= ui_input(array('name' => 'resolution_days', 'id' => 'rd-'.$policy->id, 'label' => 'Penyelesaian (hari kerja)', 'type' => 'number', 'min' => 1, 'max' => 365, 'value' => (string) $policy->resolution_days, 'help' => 'Kosongkan bila tidak ditetapkan.')) ?></div>
			</div>
			<div class="custom-control custom-checkbox mb-2">
				<input class="custom-control-input" type="checkbox" id="pn2-<?= (int) $policy->id ?>" name="pause_on_needs_information" value="1" <?= ! empty($rules['pause_resolution_on_needs_information']) ? 'checked' : '' ?>>
				<label class="custom-control-label" for="pn2-<?= (int) $policy->id ?>">Hentikan sementara SLA penyelesaian saat status “perlu dilengkapi”</label>
			</div>
			<div class="custom-control custom-checkbox mb-2">
				<input class="custom-control-input" type="checkbox" id="ac-<?= (int) $policy->id ?>" name="auto_close_enabled" value="1" <?= $policy->auto_close_enabled ? 'checked' : '' ?>>
				<label class="custom-control-label" for="ac-<?= (int) $policy->id ?>">Aktifkan penutupan otomatis setelah batas tanggapan</label>
			</div>
			<p class="small text-muted mb-0">
				Penutupan otomatis <strong>tidak dijalankan</strong> pada versi ini walau dicentang: job hanya mengirim pengingat dan memasukkan laporan ke daftar review.
				Mengaktifkan penutupan otomatis memerlukan aturan, pemberitahuan, dan jalur reopen yang disepakati terlebih dahulu.
			</p>
		</div>
		<div class="card-footer bg-white text-right"><button class="btn btn-primary" type="submit">Simpan kebijakan</button></div>
	</form>
	<?php endforeach; ?>
	<div class="alert alert-info small">Perubahan kebijakan tidak mengubah tenggat kasus yang sudah berjalan: setiap episode menyimpan snapshot kebijakan saat dimulai.</div>

<?php elseif ($section === 'kategori'): ?>
	<div class="card shadow-sm mb-4">
		<div class="card-header"><h2>Kategori aktif</h2></div>
		<div class="card-body">
			<div class="table-responsive">
				<table class="table table-sm mb-0">
					<caption class="sr-only">Kategori layanan</caption>
					<thead><tr><th scope="col">Kode</th><th scope="col">Nama</th><th scope="col">Jenis</th><th scope="col">Unit</th><th scope="col">Sensitif</th><th scope="col">Lokasi wajib</th><th scope="col">Aktif</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
					<tbody>
					<?php foreach ($categories as $cat): ?>
						<tr>
							<td><code><?= e($cat->code) ?></code></td>
							<td>
								<form method="post" action="<?= site_url('admin/pengaturan/kategori') ?>" class="form-row align-items-end">
									<?= csrf_field() ?>
									<input type="hidden" name="category_id" value="<?= (int) $cat->id ?>">
									<input type="hidden" name="action" value="update">
									<div class="col-12"><?= ui_input(array('name' => 'name', 'id' => 'cn-'.$cat->id, 'label' => 'Nama', 'maxlength' => 100, 'value' => $cat->name, 'wrap_class' => 'mb-1')) ?></div>
									<div class="col-12"><?= ui_input(array('name' => 'description', 'id' => 'cdc-'.$cat->id, 'label' => 'Keterangan', 'maxlength' => 255, 'value' => $cat->description, 'wrap_class' => 'mb-1')) ?></div>
									<div class="col-6"><?= ui_select(array('name' => 'default_unit_id', 'id' => 'cu-'.$cat->id, 'label' => 'Unit', 'options' => $units, 'placeholder_option' => 'Tanpa unit', 'value' => (string) $cat->default_unit_id, 'wrap_class' => 'mb-1')) ?></div>
									<div class="col-6"><?= ui_select(array('name' => 'sla_policy_id', 'id' => 'cs-'.$cat->id, 'label' => 'SLA', 'options' => $sla_options, 'placeholder_option' => 'Bawaan', 'value' => (string) $cat->sla_policy_id, 'wrap_class' => 'mb-1')) ?></div>
									<div class="col-4">
										<div class="custom-control custom-checkbox mb-1">
											<input class="custom-control-input" type="checkbox" id="sen-<?= (int) $cat->id ?>" name="is_sensitive" value="1" <?= $cat->is_sensitive ? 'checked' : '' ?>>
											<label class="custom-control-label small" for="sen-<?= (int) $cat->id ?>">Sensitif</label>
										</div>
									</div>
									<div class="col-4">
										<div class="custom-control custom-checkbox mb-1">
											<input class="custom-control-input" type="checkbox" id="loc-<?= (int) $cat->id ?>" name="location_required" value="1" <?= $cat->location_required ? 'checked' : '' ?>>
											<label class="custom-control-label small" for="loc-<?= (int) $cat->id ?>">Lokasi wajib</label>
										</div>
									</div>
									<div class="col-4">
										<div class="custom-control custom-checkbox mb-1">
											<input class="custom-control-input" type="checkbox" id="act-<?= (int) $cat->id ?>" name="active" value="1" <?= $cat->active ? 'checked' : '' ?>>
											<label class="custom-control-label small" for="act-<?= (int) $cat->id ?>">Aktif</label>
										</div>
									</div>
									<input type="hidden" name="sort_order" value="<?= (int) $cat->sort_order ?>">
									<div class="col-12"><button class="btn btn-outline-primary btn-sm" type="submit">Simpan</button></div>
								</form>
							</td>
							<td class="small"><?= e($cat->report_type ? config_label('report_types', $cat->report_type) : 'Semua') ?></td>
							<td class="small"><?= e($cat->unit_name ?: '—') ?></td>
							<td class="small"><?= $cat->is_sensitive ? 'Ya' : 'Tidak' ?></td>
							<td class="small"><?= $cat->location_required ? 'Ya' : 'Tidak' ?></td>
							<td class="small"><?= $cat->active ? 'Ya' : 'Tidak' ?></td>
							<td></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<form class="card shadow-sm" method="post" action="<?= site_url('admin/pengaturan/kategori') ?>" data-once>
		<div class="card-header"><h2>Tambah kategori</h2></div>
		<div class="card-body">
			<?= csrf_field() ?>
			<input type="hidden" name="action" value="create">
			<div class="form-row">
				<div class="col-md-3"><?= ui_input(array('name' => 'code', 'label' => 'Kode', 'required' => TRUE, 'maxlength' => 50, 'value' => '', 'help' => 'HURUF_BESAR tanpa spasi.')) ?></div>
				<div class="col-md-5"><?= ui_input(array('name' => 'name', 'label' => 'Nama kategori', 'required' => TRUE, 'maxlength' => 100, 'value' => '')) ?></div>
				<div class="col-md-4"><?= ui_select(array('name' => 'report_type', 'label' => 'Jenis laporan', 'options' => app_config('report_types'), 'placeholder_option' => 'Semua jenis', 'value' => '')) ?></div>
			</div>
			<?= ui_input(array('name' => 'description', 'label' => 'Keterangan', 'maxlength' => 255, 'value' => '')) ?>
			<div class="form-row">
				<div class="col-md-4"><?= ui_select(array('name' => 'default_unit_id', 'label' => 'Unit tujuan', 'options' => $units, 'placeholder_option' => 'Tanpa unit', 'value' => '')) ?></div>
				<div class="col-md-4"><?= ui_select(array('name' => 'sla_policy_id', 'label' => 'Kebijakan SLA', 'options' => $sla_options, 'placeholder_option' => 'Bawaan', 'value' => '')) ?></div>
				<div class="col-md-4"><?= ui_input(array('name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number', 'min' => 0, 'max' => 9999, 'value' => '100')) ?></div>
			</div>
			<div class="custom-control custom-checkbox">
				<input class="custom-control-input" type="checkbox" id="new_sensitive" name="is_sensitive" value="1">
				<label class="custom-control-label" for="new_sensitive">Kategori sensitif (laporan otomatis rahasia)</label>
			</div>
			<div class="custom-control custom-checkbox">
				<input class="custom-control-input" type="checkbox" id="new_location" name="location_required" value="1">
				<label class="custom-control-label" for="new_location">Lokasi wajib diisi</label>
			</div>
		</div>
		<div class="card-footer bg-white text-right"><button class="btn btn-primary" type="submit">Tambah kategori</button></div>
	</form>

<?php elseif ($section === 'pemeliharaan'): $m = $maintenance; ?>
<div class="row g-3 mb-4">
	<div class="col-md-3"><div class="card-soft card-pad"><p class="eyebrow">Outbox gagal</p><p class="h4 mb-0"><?= (int) $m['outbox_failed'] ?></p></div></div>
	<div class="col-md-3"><div class="card-soft card-pad"><p class="eyebrow">Ekspor menunggu</p><p class="h4 mb-0"><?= (int) $m['exports_pending'] ?></p></div></div>
	<div class="col-md-3"><div class="card-soft card-pad"><p class="eyebrow">Unit aset tanpa QR</p><p class="h4 mb-0"><?= (int) $m['assets_without_qr'] ?></p></div></div>
	<div class="col-md-3"><div class="card-soft card-pad"><p class="eyebrow">Tautan rusak</p><p class="h4 mb-0"><?= count($m['broken_links']) ?></p></div></div>
</div>

<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Pekerjaan terjadwal</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Pekerjaan</th><th scope="col">Terakhir dikunci</th><th scope="col">Kedaluwarsa kunci</th></tr></thead>
			<tbody>
			<?php foreach ($m['jobs'] as $job): ?>
				<tr>
					<th scope="row"><?= e($job->job_key) ?></th>
					<td><?= e(format_wib($job->locked_at ?? NULL, 'short')) ?></td>
					<td><?= e(format_wib($job->expires_at ?? NULL, 'short')) ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($m['jobs'])): ?><tr><td colspan="3" class="text-muted">Belum ada pekerjaan yang pernah berjalan.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
	<div class="card-body border-top small text-muted">
		Jalankan dengan <code>php public/index.php tools run_jobs</code>, atau satu pekerjaan saja,
		misalnya <code>tools run_jobs link_check</code>.
	</div>
</div>

<div class="row g-4 mb-4">
	<div class="col-lg-6">
		<div class="card shadow-sm h-100">
			<div class="card-header"><h2 class="h6 mb-0">Penyimpanan dan cache</h2></div>
			<ul class="list-group list-group-flush">
				<?php foreach ($m['storage'] as $path => $bytes): ?>
					<li class="list-group-item d-flex justify-content-between">
						<span><code><?= e($path) ?></code></span><span><?= e(format_bytes_id($bytes)) ?></span>
					</li>
				<?php endforeach; ?>
				<li class="list-group-item d-flex justify-content-between">
					<span>Cache publik</span>
					<span><?= $m['cache_enabled'] ? 'aktif' : 'nonaktif' ?><?= $m['cache_last_invalidation'] ? ', terakhir dibersihkan '.e(format_wib($m['cache_last_invalidation'], 'short')) : '' ?></span>
				</li>
			</ul>
		</div>
	</div>
	<div class="col-lg-6">
		<div class="card shadow-sm h-100">
			<div class="card-header"><h2 class="h6 mb-0">Menunggu tindakan pengelola</h2></div>
			<ul class="list-group list-group-flush">
				<?php foreach ($m['pending'] as $label => $count): ?>
					<li class="list-group-item d-flex justify-content-between">
						<span><?= e($label) ?></span><span><?= (int) $count ?></span>
					</li>
				<?php endforeach; ?>
				<?php if ($m['low_stock']): ?>
					<li class="list-group-item"><strong>Stok di bawah minimum:</strong> <?= e(implode(', ', $m['low_stock'])) ?></li>
				<?php endif; ?>
				<?php if ($m['disabled_modules']): ?>
					<li class="list-group-item"><strong>Modul tidak aktif:</strong> <?= e(implode(', ', $m['disabled_modules'])) ?></li>
				<?php endif; ?>
			</ul>
		</div>
	</div>
</div>

<div class="card shadow-sm">
	<div class="card-header d-flex justify-content-between align-items-center">
		<h2 class="h6 mb-0">Tautan internal rusak</h2>
		<span class="small text-muted">Pemeriksaan terakhir <?= e(format_wib($m['link_check_last'], 'short')) ?></span>
	</div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Sumber</th><th scope="col">Label</th><th scope="col">Tujuan</th><th scope="col">Keterangan</th></tr></thead>
			<tbody>
			<?php foreach ($m['broken_links'] as $link): ?>
				<tr>
					<th scope="row"><?= e($link->source_type) ?></th>
					<td><?= e($link->source_label) ?></td>
					<td><code><?= e($link->target_path) ?></code></td>
					<td class="small text-muted"><?= e($link->detail) ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($m['broken_links'])): ?>
				<tr><td colspan="4" class="text-success">Tidak ada tautan internal yang rusak pada pemeriksaan terakhir.</td></tr>
			<?php endif; ?>
			</tbody>
		</table>
	</div>
	<div class="card-body border-top small text-muted">
		Tautan eksternal sengaja tidak dipanggil dari server; yang diperiksa hanya path internal.
	</div>
</div>

<?php elseif ($section === 'sistem'): ?>
	<div class="row">
		<div class="col-lg-6 mb-4">
			<div class="card shadow-sm h-100">
				<div class="card-header"><h2>Runtime</h2></div>
				<div class="card-body">
					<dl class="dl-grid mb-0">
						<dt>PHP</dt><dd><?= e($system['php']) ?></dd>
						<dt>CodeIgniter</dt><dd><?= e($system['ci_version']) ?></dd>
						<dt>Database</dt><dd><?= e($system['database']) ?></dd>
						<dt>Environment</dt><dd><?= e($system['environment']) ?></dd>
						<dt>Zona waktu tampilan</dt><dd><?= e($system['timezone']) ?> (penyimpanan UTC)</dd>
						<dt>Versi skema</dt><dd><?= (int) $system['schema_version'] ?></dd>
						<dt>Upload maksimum</dt><dd><?= e($system['upload_max']) ?> / POST <?= e($system['post_max']) ?></dd>
						<dt>Email</dt><dd><?= e($system['mail']) ?></dd>
					</dl>
				</div>
			</div>
		</div>
		<div class="col-lg-6 mb-4">
			<div class="card shadow-sm h-100">
				<div class="card-header"><h2>Feature flag &amp; job</h2></div>
				<div class="card-body">
					<ul class="list-unstyled mb-3">
						<?php foreach ($system['features'] as $flag => $enabled): ?>
						<li><span class="chip-flag <?= $enabled ? 'is-info' : '' ?>"><?= e($flag) ?>: <?= $enabled ? 'aktif' : 'nonaktif' ?></span></li>
						<?php endforeach; ?>
					</ul>
					<p class="small text-muted">Feature flag diatur melalui environment (<code>.env</code>), bukan dari dashboard.</p>
					<h3 class="h6">Job terakhir</h3>
					<?php if (empty($jobs)): ?>
						<p class="small text-muted">Belum ada job yang dijalankan. Jadwalkan <code>php public/index.php tools run_jobs</code>.</p>
					<?php else: ?>
						<ul class="list-unstyled small mb-3">
							<?php foreach ($jobs as $job): ?>
							<li><code><?= e($job->job_key) ?></code> — lock sampai <?= e(format_wib($job->locked_until, 'short')) ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<h3 class="h6">Outbox email</h3>
					<ul class="list-unstyled small mb-0">
						<?php foreach ($outbox as $row): ?>
							<li><?= e($row->status) ?>: <strong><?= (int) $row->total ?></strong></li>
						<?php endforeach; ?>
						<?php if (empty($outbox)): ?><li class="text-muted">Belum ada pesan.</li><?php endif; ?>
					</ul>
				</div>
			</div>
		</div>
	</div>
<?php endif; ?>
