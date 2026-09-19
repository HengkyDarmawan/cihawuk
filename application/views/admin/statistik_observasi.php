<?php defined('BASEPATH') OR exit('No direct script access allowed');
$indicator_options = array();
foreach ($indicators as $i) { $indicator_options[(string) $i->id] = $i->label; }
$area_options = array();
foreach ($areas as $a) { $area_options[(string) $a->id] = $a->name; }
$page_url = function ($n) use ($source, $filters) {
	return site_url('admin/statistik/sumber/'.(int) $source->id).'?'.http_build_query(array_filter(array('status' => $filters['status'], 'q' => $filters['keyword'], 'hal' => $n)));
};
$status_labels = array('pending' => 'Menunggu review', 'accepted' => 'Diterima', 'rejected' => 'Ditolak', 'corrected' => 'Dikoreksi', 'conflict' => 'Konflik');
?>
<div class="page-heading">
	<div>
		<h1>Observasi <?= e($source->source_code) ?></h1>
		<p><?= e($source->title) ?> · <?= (int) $total ?> observasi sesuai filter. Nilai mentah tidak pernah ditimpa.</p>
	</div>
	<a class="btn btn-outline-primary" href="<?= site_url('admin/statistik/sumber') ?>">Kembali</a>
</div>

<div class="card shadow-sm mb-4">
	<div class="card-body">
		<form method="get" action="<?= site_url('admin/statistik/sumber/'.(int) $source->id) ?>" class="form-row align-items-end">
			<div class="col-md-5 mb-2">
				<label for="q">Cari label atau nilai</label>
				<input class="form-control" type="search" id="q" name="q" maxlength="80" value="<?= e($filters['keyword']) ?>">
			</div>
			<div class="col-md-4 mb-2">
				<label for="status">Status review</label>
				<select class="form-control" id="status" name="status">
					<option value="">Semua status</option>
					<?php foreach ($status_labels as $code => $label): ?>
						<option value="<?= e($code) ?>" <?= $filters['status'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-3 mb-2"><button class="btn btn-primary btn-block" type="submit">Terapkan</button></div>
		</form>
	</div>
</div>

<div class="card shadow-sm">
	<div class="card-body">
		<?php if (empty($observations)): ?>
			<div class="empty-box"><i class="fas fa-database" aria-hidden="true"></i><p class="mb-0">Tidak ada observasi yang cocok.</p></div>
		<?php else: ?>
		<div class="table-responsive">
			<table class="table table-sm table-hover mb-0">
				<caption class="sr-only">Observasi hasil impor dokumen sumber</caption>
				<thead>
					<tr>
						<th scope="col">Label &amp; lokasi</th>
						<th scope="col">Nilai mentah</th>
						<th scope="col">Normalisasi</th>
						<th scope="col">Status</th>
						<th scope="col" style="min-width:320px">Review</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($observations as $obs): ?>
					<tr>
						<td>
							<?= e($obs->field_label ?: $obs->field_key) ?>
							<div class="small text-muted"><?= e($obs->source_locator) ?></div>
						</td>
						<td class="small"><code><?= e(str_limit_id((string) $obs->raw_value, 60)) ?></code></td>
						<td class="small">
							<?= $obs->normalized_value === NULL ? '<span class="text-muted">null</span>' : e($obs->normalized_value) ?>
							<div class="text-muted"><?= e($obs->value_type) ?><?= $obs->unit ? ' · '.e($obs->unit) : '' ?></div>
						</td>
						<td>
							<span class="chip-flag <?= in_array($obs->validation_status, array('accepted', 'corrected'), TRUE) ? 'is-info' : ($obs->validation_status === 'conflict' ? 'is-danger' : '') ?>">
								<?= e($status_labels[$obs->validation_status] ?? $obs->validation_status) ?>
							</span>
							<?php if ($obs->review_note): ?><div class="small text-muted"><?= e(str_limit_id($obs->review_note, 80)) ?></div><?php endif; ?>
						</td>
						<td>
							<?php if ($can_review): ?>
							<form method="post" action="<?= site_url('admin/statistik/observasi/'.(int) $obs->id) ?>" class="form-row align-items-end mb-1">
								<?= csrf_field() ?>
								<input type="hidden" name="action" value="review">
								<div class="col-4">
									<label class="sr-only" for="st-<?= (int) $obs->id ?>">Status</label>
									<select class="form-control form-control-sm" id="st-<?= (int) $obs->id ?>" name="status">
										<?php foreach ($status_labels as $code => $label): ?>
											<option value="<?= e($code) ?>" <?= $obs->validation_status === $code ? 'selected' : '' ?>><?= e($label) ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="col-4">
									<label class="sr-only" for="cv-<?= (int) $obs->id ?>">Nilai koreksi</label>
									<input class="form-control form-control-sm" id="cv-<?= (int) $obs->id ?>" type="text" name="corrected_value" placeholder="Nilai koreksi" maxlength="255" value="<?= e($obs->corrected_value) ?>">
								</div>
								<div class="col-4">
									<label class="sr-only" for="rn-<?= (int) $obs->id ?>">Catatan</label>
									<div class="input-group input-group-sm">
										<input class="form-control" id="rn-<?= (int) $obs->id ?>" type="text" name="review_note" placeholder="Catatan review" maxlength="500">
										<div class="input-group-append"><button class="btn btn-outline-primary" type="submit">Simpan</button></div>
									</div>
								</div>
							</form>
							<?php if (in_array($obs->validation_status, array('accepted', 'corrected'), TRUE)): ?>
							<details>
								<summary class="small">Jadikan nilai statistik</summary>
								<form method="post" action="<?= site_url('admin/statistik/observasi/'.(int) $obs->id) ?>" class="form-row align-items-end mt-1">
									<?= csrf_field() ?>
									<input type="hidden" name="action" value="promote">
									<div class="col-5"><?= ui_select(array('name' => 'indicator_id', 'id' => 'ind-'.$obs->id, 'label' => 'Indikator', 'options' => $indicator_options, 'placeholder_option' => 'Pilih…', 'value' => '', 'wrap_class' => 'mb-1')) ?></div>
									<div class="col-3"><?= ui_input(array('name' => 'source_year', 'id' => 'yr-'.$obs->id, 'label' => 'Tahun', 'type' => 'number', 'value' => (string) $obs->source_year, 'wrap_class' => 'mb-1')) ?></div>
									<div class="col-3"><?= ui_select(array('name' => 'area_id', 'id' => 'ar-'.$obs->id, 'label' => 'Wilayah', 'options' => $area_options, 'value' => '', 'wrap_class' => 'mb-1')) ?></div>
									<div class="col-1 mb-1"><button class="btn btn-primary btn-sm btn-block" type="submit">OK</button></div>
								</form>
							</details>
							<?php endif; ?>
							<?php else: ?>
								<span class="small text-muted">Perlu izin <code>statistics.review</code>.</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>
	</div>
	<?php if ($pages > 1): ?>
	<div class="card-footer bg-white">
		<nav aria-label="Halaman observasi">
			<ul class="pagination mb-0 justify-content-center">
				<li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $page_url($page - 1) ?>">Sebelumnya</a></li>
				<li class="page-item disabled"><span class="page-link">Halaman <?= (int) $page ?> dari <?= (int) $pages ?></span></li>
				<li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $page_url($page + 1) ?>">Berikutnya</a></li>
			</ul>
		</nav>
	</div>
	<?php endif; ?>
</div>
