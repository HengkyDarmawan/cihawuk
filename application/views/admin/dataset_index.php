<?php defined('BASEPATH') OR exit('No direct script access allowed');
$can = function ($p) use ($permissions) { return in_array($p, $permissions, TRUE); };
?>
<div class="page-heading">
	<div>
		<h1>Dataset dan Statistik</h1>
		<p>Halaman Data Desa hanya menampilkan dataset yang sudah diterbitkan. Angka wajib diverifikasi lebih dulu.</p>
	</div>
	<div class="text-right">
		<span class="d-block small text-muted"><?= (int) $pending_values ?> nilai menunggu verifikasi</span>
		<span class="d-block small text-muted"><?= (int) $open_issues ?> masalah data terbuka</span>
	</div>
</div>

<?= ui_error_summary($this->form_errors) ?>

<div class="row">
	<div class="col-lg-8">
		<div class="card shadow-sm mb-4">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h2 class="h6 mb-0">Daftar dataset</h2>
				<form method="get" action="<?= site_url('admin/dataset') ?>" class="form-inline">
					<label class="sr-only" for="tema">Tema</label>
					<select class="form-control form-control-sm form-select mr-2" id="tema" name="tema" onchange="this.form.submit()">
						<option value="">Semua tema</option>
						<?php foreach ($themes as $code => $label): ?>
							<option value="<?= e($code) ?>" <?= $theme_filter === $code ? 'selected' : '' ?>><?= e($label) ?></option>
						<?php endforeach; ?>
					</select>
					<noscript><button class="btn btn-outline-primary btn-sm" type="submit">Saring</button></noscript>
				</form>
			</div>
			<div class="table-responsive">
				<table class="table table-sm mb-0">
					<caption class="sr-only">Daftar dataset statistik</caption>
					<thead><tr><th scope="col">Dataset</th><th scope="col">Tema</th><th scope="col">Tahun</th><th scope="col">Indikator</th><th scope="col">Status</th><th scope="col"></th></tr></thead>
					<tbody>
					<?php foreach ($datasets as $row): ?>
						<tr>
							<td>
								<span class="font-weight-bold"><?= e($row->name) ?></span>
								<span class="d-block small text-muted"><code>/<?= e($row->slug) ?></code> · <?= e($sensitivities[$row->sensitivity] ?? $row->sensitivity) ?></span>
							</td>
							<td class="small"><?= e($themes[$row->theme] ?? $row->theme) ?></td>
							<td class="small"><?= $row->period_year ? (int) $row->period_year : '—' ?></td>
							<td class="small"><?= (int) $row->series_count ?></td>
							<td>
								<span class="chip-flag <?= $row->status === 'published' ? 'is-info' : '' ?>"><?= e($statuses[$row->status] ?? $row->status) ?></span>
								<?php if ($row->validation_status === 'failed'): ?><span class="chip-flag is-warning">perlu perbaikan</span><?php endif; ?>
							</td>
							<td class="text-right"><a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/dataset/'.rawurlencode($row->public_id)) ?>">Kelola</a></td>
						</tr>
					<?php endforeach; ?>
					<?php if (empty($datasets)): ?>
						<tr><td colspan="6" class="dt-empty text-center">Belum ada dataset.</td></tr>
					<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		<p class="small text-muted">
			Nilai statistik mentah dan observasi dokumen sumber tetap dikelola di
			<a href="<?= site_url('admin/statistik') ?>">Data &amp; Statistik</a>. Dataset di sini merangkai nilai
			yang sudah diverifikasi menjadi satu terbitan yang dapat dibaca publik.
		</p>
	</div>

	<?php if ($can('data.review')): ?>
	<div class="col-lg-4">
		<form class="card shadow-sm" method="post" action="<?= site_url('admin/dataset/buat') ?>" data-once>
			<div class="card-header"><h2 class="h6 mb-0">Dataset baru</h2></div>
			<div class="card-body">
				<?= csrf_field() ?>
				<?= ui_input(array('name' => 'name', 'label' => 'Nama dataset', 'required' => TRUE, 'maxlength' => 160)) ?>
				<?= ui_select(array('name' => 'theme', 'label' => 'Tema', 'required' => TRUE, 'options' => $themes)) ?>
				<?= ui_input(array('name' => 'period_year', 'label' => 'Tahun periode', 'type' => 'number', 'required' => TRUE, 'min' => 1900, 'max' => 2100)) ?>
				<?= ui_input(array('name' => 'coverage', 'label' => 'Cakupan wilayah', 'value' => 'Desa Cihawuk', 'maxlength' => 120)) ?>
				<?= ui_select(array('name' => 'sensitivity', 'label' => 'Sensitivitas', 'options' => $sensitivities, 'value' => 'public')) ?>
				<?= ui_select(array('name' => 'source_id', 'label' => 'Dokumen sumber', 'options' => array_reduce($sources, function ($acc, $s) {
					$acc[(string) $s->id] = $s->source_code.' — '.$s->title; return $acc; }, array()), 'placeholder_option' => 'Pilih nanti')) ?>
				<?= ui_textarea(array('name' => 'methodology', 'label' => 'Metodologi', 'rows' => 3, 'maxlength' => 1000,
					'help' => 'Wajib diisi sebelum dataset dapat diterbitkan.')) ?>
			</div>
			<div class="card-footer bg-white text-right"><button class="btn btn-primary btn-sm" type="submit">Buat draft</button></div>
		</form>
	</div>
	<?php endif; ?>
</div>
