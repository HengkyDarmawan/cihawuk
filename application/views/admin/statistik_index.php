<?php defined('BASEPATH') OR exit('No direct script access allowed');
$indicator_options = array();
foreach ($indicators as $i) { $indicator_options[(string) $i->id] = $i->label.' ('.$i->unit.')'; }
$area_options = array();
foreach ($areas as $a) { $area_options[(string) $a->id] = $a->name; }
?>
<div class="page-heading">
	<div>
		<h1>Data &amp; statistik</h1>
		<p>Nilai hanya tampil di situs publik bila <strong>terverifikasi</strong> dan <strong>terbit</strong>.</p>
	</div>
	<div>
		<a class="btn btn-outline-primary mr-2" href="<?= site_url('admin/statistik/sumber') ?>">Dokumen sumber</a>
		<a class="btn btn-outline-primary" href="<?= site_url('admin/statistik/isu') ?>">Masalah data (<?= (int) $open_issues ?>)</a>
	</div>
</div>

<div class="row">
	<div class="col-md-4 mb-4">
		<div class="card stat-card shadow-sm h-100"><div class="card-body">
			<div class="stat-label">Observasi menunggu review</div>
			<div class="stat-value"><?= (int) $pending_observations ?></div>
			<a class="small" href="<?= site_url('admin/statistik/sumber') ?>">Buka daftar sumber</a>
		</div></div>
	</div>
	<div class="col-md-4 mb-4">
		<div class="card stat-card shadow-sm h-100 is-warning"><div class="card-body">
			<div class="stat-label">Masalah data terbuka</div>
			<div class="stat-value"><?= (int) $open_issues ?></div>
			<a class="small" href="<?= site_url('admin/statistik/isu') ?>">Tinjau masalah</a>
		</div></div>
	</div>
	<div class="col-md-4 mb-4">
		<div class="card stat-card shadow-sm h-100"><div class="card-body">
			<div class="stat-label">Nilai statistik tersimpan</div>
			<div class="stat-value"><?= count($values) ?></div>
			<span class="small text-muted">Termasuk draft dan arsip</span>
		</div></div>
	</div>
</div>

<div class="row">
	<div class="col-lg-8 mb-4">
		<div class="card shadow-sm">
			<div class="card-header card-header-actions">
				<h2>Nilai statistik</h2>
				<?php if ($can_review): ?><?= ui_add_button('modal-nilai-manual', 'Isi nilai manual') ?><?php endif; ?>
			</div>
			<div class="card-body">
				<?php if (empty($values)): ?>
					<div class="empty-box"><i class="fas fa-chart-bar" aria-hidden="true"></i><p class="mb-0">Belum ada nilai statistik.</p></div>
				<?php else: ?>
				<div class="table-responsive">
					<table class="table table-sm mb-0">
						<caption class="sr-only">Daftar nilai statistik beserta status</caption>
						<thead><tr><th scope="col">Indikator</th><th scope="col">Tahun</th><th scope="col" class="text-right">Nilai</th><th scope="col">Sumber</th><th scope="col">Verifikasi</th><th scope="col">Publikasi</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
						<tbody>
						<?php foreach ($values as $v): ?>
							<tr>
								<td><?= e($v->label) ?><div class="small text-muted"><?= e($v->code) ?></div></td>
								<td><?= (int) $v->source_year ?><?php if ($v->year_label): ?><div class="small text-muted"><?= e($v->year_label) ?></div><?php endif; ?></td>
								<td class="text-right"><?= $v->numeric_value !== NULL ? e(format_number_id($v->numeric_value, $v->value_type === 'decimal' ? 2 : 0)) : e($v->text_value ?: '—') ?></td>
								<td class="small"><?= e($v->source_code ?: '—') ?></td>
								<td><span class="chip-flag <?= $v->verification_status === 'verified' ? 'is-info' : 'is-warning' ?>"><?= e(config_label('verification_statuses', $v->verification_status)) ?></span></td>
								<td><span class="chip-flag <?= $v->publication_status === 'published' ? 'is-info' : '' ?>"><?= e(config_label('publication_statuses', $v->publication_status)) ?></span></td>
								<td class="text-right">
									<?php if ($can_publish OR $can_review): ?>
									<form method="post" action="<?= site_url('admin/statistik/nilai/'.(int) $v->id.'/status') ?>" class="form-inline justify-content-end">
										<?= csrf_field() ?>
										<label class="sr-only" for="st-<?= (int) $v->id ?>">Status publikasi</label>
										<select class="form-control form-control-sm mr-1" id="st-<?= (int) $v->id ?>" name="publication_status">
											<?php foreach (app_config('publication_statuses') as $code => $label): ?>
												<?php if (in_array($code, array('published', 'archived'), TRUE) && ! $can_publish) { continue; } ?>
												<option value="<?= e($code) ?>" <?= $v->publication_status === $code ? 'selected' : '' ?>><?= e($label) ?></option>
											<?php endforeach; ?>
										</select>
										<button class="btn btn-outline-primary btn-sm" type="submit">Simpan</button>
									</form>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="col-lg-4 mb-4">
		<div class="card shadow-sm">
			<div class="card-header"><h2>Dokumen sumber</h2></div>
			<div class="card-body">
				<ul class="list-unstyled mb-0 small">
					<?php foreach ($sources as $s): ?>
					<li class="border-bottom py-2">
						<strong><?= e($s->source_code) ?></strong> — <?= e($s->title) ?>
						<div class="text-muted"><?= e($s->original_filename) ?> · <?= e($s->import_status) ?><?= $s->source_year ? ' · tahun '.(int) $s->source_year : '' ?></div>
					</li>
					<?php endforeach; ?>
				</ul>
				<a class="btn btn-outline-primary btn-sm mt-3" href="<?= site_url('admin/statistik/sumber') ?>">Kelola sumber</a>
			</div>
		</div>
	</div>
</div>

<?php if ($can_review): ?>
<?= ui_modal_open('modal-nilai-manual', 'Isi nilai manual') ?>
	<form method="post" action="<?= site_url('admin/statistik/nilai') ?>" data-once>
		<?= csrf_field() ?>
		<?= ui_select(array('name' => 'indicator_id', 'label' => 'Indikator', 'required' => TRUE, 'options' => $indicator_options, 'placeholder_option' => 'Pilih indikator…', 'value' => '')) ?>
		<div class="form-row">
			<div class="col-md-6"><?= ui_input(array('name' => 'source_year', 'label' => 'Tahun sumber', 'type' => 'number', 'required' => TRUE, 'min' => 1900, 'max' => 2100, 'value' => '')) ?></div>
			<div class="col-md-6"><?= ui_select(array('name' => 'area_id', 'label' => 'Wilayah', 'required' => TRUE, 'options' => $area_options, 'value' => '')) ?></div>
		</div>
		<?= ui_input(array('name' => 'numeric_value', 'label' => 'Nilai', 'value' => '', 'help' => 'Angka saja, contoh 6809 atau 932.35.')) ?>
		<?= ui_input(array('name' => 'year_label', 'label' => 'Label tahun', 'maxlength' => 100, 'value' => '', 'help' => 'Contoh: "Tahun lalu (label S2; pemetaan inferensi)".')) ?>
		<?= ui_modal_actions('Simpan nilai') ?>
	</form>
<?= ui_modal_close() ?>
<?php endif; ?>
