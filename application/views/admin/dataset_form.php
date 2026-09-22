<?php defined('BASEPATH') OR exit('No direct script access allowed');
$can = function ($p) use ($permissions) { return in_array($p, $permissions, TRUE); };
$source_options = array();
foreach ($sources as $s) { $source_options[(string) $s->id] = $s->source_code.' — '.$s->title; }
$indicator_options = array();
foreach ($indicators as $i) { $indicator_options[(string) $i->id] = $i->label.' ('.$i->unit.')'; }
$action = site_url('admin/dataset/'.rawurlencode($dataset->public_id));
?>
<div class="page-heading">
	<div>
		<h1><?= e($dataset->name) ?></h1>
		<p>Dataset <code>/<?= e($dataset->slug) ?></code>. Perubahan tersimpan sebagai versi baru sampai diterbitkan.</p>
	</div>
	<div class="text-right">
		<span class="chip-flag <?= $dataset->status === 'published' ? 'is-info' : '' ?>"><?= e($statuses[$dataset->status] ?? $dataset->status) ?></span>
		<?php if ($dataset->published_at): ?><span class="d-block small text-muted">Terbit terakhir <?= e(format_wib($dataset->published_at, 'short')) ?></span><?php endif; ?>
		<a class="d-block small" href="<?= site_url('admin/dataset') ?>">Semua dataset</a>
	</div>
</div>

<?= ui_error_summary($this->form_errors) ?>

<div class="row">
	<div class="col-lg-7">
		<form class="card shadow-sm mb-4" method="post" action="<?= $action ?>/simpan" data-once>
			<div class="card-header"><h2 class="h6 mb-0">Metadata versi <?= $version ? (int) $version->version_no : 1 ?></h2></div>
			<div class="card-body">
				<?= csrf_field() ?>
				<?= ui_input(array('name' => 'name', 'label' => 'Nama dataset', 'required' => TRUE, 'maxlength' => 160, 'value' => $dataset->name)) ?>
				<div class="row">
					<div class="col-sm-6"><?= ui_select(array('name' => 'theme', 'label' => 'Tema', 'required' => TRUE, 'options' => $themes, 'value' => $dataset->theme)) ?></div>
					<div class="col-sm-6"><?= ui_select(array('name' => 'sensitivity', 'label' => 'Sensitivitas', 'options' => $sensitivities, 'value' => $dataset->sensitivity)) ?></div>
					<div class="col-sm-6"><?= ui_input(array('name' => 'period_year', 'label' => 'Tahun periode', 'type' => 'number', 'required' => TRUE, 'min' => 1900, 'max' => 2100, 'value' => $version ? (int) $version->period_year : '')) ?></div>
					<div class="col-sm-6"><?= ui_input(array('name' => 'period_label', 'label' => 'Keterangan periode', 'maxlength' => 60, 'value' => $version ? $version->period_label : '',
						'help' => 'Mis. "keadaan Desember 2023".')) ?></div>
				</div>
				<?= ui_input(array('name' => 'coverage', 'label' => 'Cakupan wilayah', 'maxlength' => 120, 'value' => $dataset->coverage)) ?>
				<?= ui_textarea(array('name' => 'description', 'label' => 'Deskripsi', 'rows' => 2, 'maxlength' => 600, 'value' => $dataset->description)) ?>
				<?= ui_textarea(array('name' => 'methodology', 'label' => 'Metodologi', 'rows' => 3, 'maxlength' => 1000, 'value' => $version ? $version->methodology : '',
					'help' => 'Minimal 20 karakter; wajib sebelum terbit.')) ?>
				<?= ui_textarea(array('name' => 'quality_note', 'label' => 'Catatan kualitas', 'rows' => 2, 'maxlength' => 1000, 'value' => $version ? $version->quality_note : '')) ?>
				<div class="row">
					<div class="col-sm-6"><?= ui_select(array('name' => 'source_id', 'label' => 'Dokumen sumber', 'options' => $source_options,
						'placeholder_option' => 'Tidak dari dokumen sumber', 'value' => $version && $version->source_id ? (string) $version->source_id : '')) ?></div>
					<div class="col-sm-6"><?= ui_input(array('name' => 'source_note', 'label' => 'Keterangan sumber', 'maxlength' => 255, 'value' => $version ? $version->source_note : '')) ?></div>
				</div>
			</div>
			<div class="card-footer bg-white text-right">
				<button class="btn btn-primary btn-sm" type="submit" <?= $can('data.review') ? '' : 'disabled' ?>>Simpan versi baru</button>
			</div>
		</form>

		<div class="card shadow-sm mb-4">
			<div class="card-header card-header-actions">
				<h2 class="h6 mb-0">Indikator dan nilai <span class="small text-muted font-weight-normal">· <?= count($series) ?> indikator · tahun <?= $version ? (int) $version->period_year : '—' ?></span></h2>
				<?php if ($can('data.review')): ?><?= ui_add_button('modal-tambah-indikator', 'Tambah indikator') ?><?php endif; ?>
			</div>
			<?php if (empty($series)): ?>
				<div class="card-body empty-box"><i class="fas fa-chart-column fa-chart-bar" aria-hidden="true"></i><p class="mb-0">Belum ada indikator pada dataset ini.</p></div>
			<?php else: ?>
			<div class="table-responsive">
				<table class="table table-sm mb-0">
					<caption class="sr-only">Indikator dataset dan status verifikasi nilainya</caption>
					<thead><tr><th scope="col">Indikator</th><th scope="col">Nilai <?= $version ? (int) $version->period_year : '' ?></th><th scope="col">Grafik</th><th scope="col">Verifikasi</th><th scope="col"></th></tr></thead>
					<tbody>
					<?php foreach ($series as $index => $row): ?>
						<tr>
							<td>
								<span class="font-weight-bold"><?= e($row->label) ?></span>
								<span class="d-block small text-muted"><code><?= e($row->code) ?></code> · <?= e($row->unit) ?><?= $row->composition_group ? ' · komposisi '.e($row->composition_group) : '' ?></span>
							</td>
							<td class="small">
								<?php if ( ! $row->value): ?>
									<span class="text-danger">belum ada</span>
								<?php elseif ($row->value->numeric_value === NULL): ?>
									<span class="text-muted">kosong (tidak diketahui)</span>
								<?php else: ?>
									<?= e(rtrim(rtrim(number_format((float) $row->value->numeric_value, 2, ',', '.'), '0'), ',')) ?>
								<?php endif; ?>
							</td>
							<td class="small"><?= e($chart_types[$row->chart_type] ?? $row->chart_type) ?></td>
							<td class="small">
								<?php if ($row->value && $row->value->verification_status === 'verified'): ?>
									<span class="chip-flag is-info">terverifikasi</span>
								<?php else: ?>
									<span class="chip-flag">belum</span>
								<?php endif; ?>
							</td>
							<td class="text-right">
								<?php if ($can('data.review')): ?>
								<div class="d-flex flex-wrap justify-content-end" style="gap:.35rem">
									<form method="post" action="<?= $action ?>/seri/naik">
										<?= csrf_field() ?><input type="hidden" name="series_id" value="<?= (int) $row->id ?>">
										<button class="btn btn-outline-primary btn-sm" type="submit" <?= $index === 0 ? 'disabled' : '' ?> aria-label="Naikkan <?= e($row->label) ?>">↑</button>
									</form>
									<form method="post" action="<?= $action ?>/seri/turun">
										<?= csrf_field() ?><input type="hidden" name="series_id" value="<?= (int) $row->id ?>">
										<button class="btn btn-outline-primary btn-sm" type="submit" <?= $index === count($series) - 1 ? 'disabled' : '' ?> aria-label="Turunkan <?= e($row->label) ?>">↓</button>
									</form>
									<?php if ($row->value): ?>
									<form method="post" action="<?= $action ?>/verifikasi">
										<?= csrf_field() ?>
										<input type="hidden" name="indicator_id" value="<?= (int) $row->indicator_id ?>">
										<input type="hidden" name="verified" value="<?= ($row->value->verification_status === 'verified') ? '0' : '1' ?>">
										<button class="btn btn-outline-primary btn-sm" type="submit"><?= ($row->value->verification_status === 'verified') ? 'Cabut verifikasi' : 'Tandai terverifikasi' ?></button>
									</form>
									<?php endif; ?>
									<form method="post" action="<?= $action ?>/seri/hapus"
										data-confirm="Keluarkan &quot;<?= e($row->label) ?>&quot; dari dataset ini? Nilai statistiknya tetap tersimpan.">
										<?= csrf_field() ?><input type="hidden" name="series_id" value="<?= (int) $row->id ?>">
										<button class="btn btn-outline-danger btn-sm" type="submit">Hapus</button>
									</form>
								</div>
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

	<div class="col-lg-5">
		<div class="card shadow-sm mb-4">
			<div class="card-header"><h2 class="h6 mb-0">Pemeriksaan sebelum terbit</h2></div>
			<div class="card-body">
				<?php if ($report === NULL): ?>
					<p class="small text-muted">Belum diperiksa sejak perubahan terakhir.</p>
				<?php else: ?>
					<?php if (empty($report['errors'])): ?>
						<p class="small text-success mb-2"><strong>Lolos pemeriksaan</strong> pada <?= e(format_wib($report['checked_at'], 'short')) ?>.</p>
					<?php else: ?>
						<p class="small text-danger mb-2"><strong><?= count($report['errors']) ?> masalah</strong> harus diperbaiki:</p>
						<ul class="small text-danger"><?php foreach ($report['errors'] as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
					<?php endif; ?>
					<?php if ( ! empty($report['warnings'])): ?>
						<p class="small mb-1"><strong>Catatan:</strong></p>
						<ul class="small text-muted"><?php foreach ($report['warnings'] as $warning): ?><li><?= e($warning) ?></li><?php endforeach; ?></ul>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ($can('data.review')): ?>
				<form method="post" action="<?= $action ?>/periksa">
					<?= csrf_field() ?><button class="btn btn-outline-primary btn-sm" type="submit">Periksa sekarang</button>
				</form>
				<?php endif; ?>
			</div>
		</div>

		<div class="card shadow-sm mb-4">
			<div class="card-header"><h2 class="h6 mb-0">Alur publikasi</h2></div>
			<div class="card-body">
				<?php if ($can('data.review') && in_array($dataset->status, array('draft', 'superseded'), TRUE)): ?>
				<form method="post" action="<?= $action ?>/alur/ajukan" class="mb-3">
					<?= csrf_field() ?><button class="btn btn-outline-primary btn-sm" type="submit">Ajukan untuk review</button>
				</form>
				<?php endif; ?>

				<?php if ($can('data.publish')): ?>
					<?php if ($dataset->status !== 'published'): ?>
					<form method="post" action="<?= $action ?>/alur/terbitkan" data-once
						data-confirm="Terbitkan dataset ini? Angkanya langsung tampil di halaman Data Desa."
						data-confirm-ok="Terbitkan">
						<?= csrf_field() ?>
						<?= ui_input(array('name' => 'reason', 'label' => 'Catatan publikasi', 'maxlength' => 200)) ?>
						<button class="btn btn-primary btn-sm" type="submit">Terbitkan sekarang</button>
					</form>
					<?php else: ?>
					<form method="post" action="<?= $action ?>/alur/tarik"
						data-confirm="Tarik dataset ini dari halaman publik? Pengunjung tidak lagi melihat angkanya."
						data-confirm-ok="Tarik">
						<?= csrf_field() ?>
						<?= ui_input(array('name' => 'reason', 'label' => 'Alasan penarikan', 'required' => TRUE, 'maxlength' => 200)) ?>
						<button class="btn btn-outline-danger btn-sm" type="submit">Tarik dari publik</button>
					</form>
					<?php endif; ?>
				<?php else: ?>
					<p class="small text-muted mb-0">Anda dapat menyusun dan memverifikasi, tetapi penerbitan memerlukan izin penerbit data.</p>
				<?php endif; ?>
			</div>
		</div>

		<div class="card shadow-sm mb-4">
			<div class="card-header"><h2 class="h6 mb-0">Riwayat publikasi</h2></div>
			<?php if (empty($snapshots)): ?>
				<div class="card-body"><p class="small text-muted mb-0">Belum pernah diterbitkan.</p></div>
			<?php else: ?>
			<div class="table-responsive">
				<table class="table table-sm mb-0">
					<caption class="sr-only">Riwayat publikasi dataset</caption>
					<thead><tr><th scope="col">Revisi</th><th scope="col">Diterbitkan</th><th scope="col">Oleh</th><th scope="col"></th></tr></thead>
					<tbody>
					<?php foreach ($snapshots as $snapshot): ?>
						<tr>
							<td><?= (int) $snapshot->revision_no ?><?= $snapshot->superseded_at === NULL ? ' <span class="chip-flag is-info">aktif</span>' : '' ?></td>
							<td class="small"><?= e(format_wib($snapshot->published_at, 'short')) ?></td>
							<td class="small text-muted"><?= e($snapshot->publisher ?: '—') ?></td>
							<td class="text-right">
								<?php if ($can('data.publish') && $snapshot->superseded_at !== NULL): ?>
								<form method="post" action="<?= $action ?>/alur/rollback"
									data-confirm="Kembalikan dataset ke revisi <?= (int) $snapshot->revision_no ?>? Ini membuat revisi baru, riwayat tidak dihapus.">
									<?= csrf_field() ?>
									<input type="hidden" name="snapshot_id" value="<?= (int) $snapshot->id ?>">
									<input type="hidden" name="reason" value="Rollback dataset ke revisi <?= (int) $snapshot->revision_no ?>">
									<button class="btn btn-outline-primary btn-sm" type="submit">Kembalikan</button>
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

		<div class="card shadow-sm">
			<div class="card-header"><h2 class="h6 mb-0">Versi dataset</h2></div>
			<div class="card-body">
				<ul class="list-unstyled small mb-0">
					<?php foreach ($versions as $row): ?>
					<li class="mb-1">
						Versi <?= (int) $row->version_no ?> · tahun <?= (int) $row->period_year ?>
						<?php if ((int) $row->id === (int) $dataset->current_version_id): ?><span class="chip-flag">draft aktif</span><?php endif; ?>
						<?php if ((int) $row->id === (int) $dataset->published_version_id): ?><span class="chip-flag is-info">terbit</span><?php endif; ?>
					</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	</div>
</div>

<?php if ($can('data.review')): ?>
<?= ui_modal_open('modal-tambah-indikator', 'Tambah indikator') ?>
	<form method="post" action="<?= $action ?>/seri/tambah">
		<?= csrf_field() ?>
		<div class="form-group">
			<label for="indicator_id">Indikator</label>
			<select class="form-control form-select" id="indicator_id" name="indicator_id">
				<?php foreach ($indicator_options as $id => $label): ?>
					<option value="<?= e($id) ?>"><?= e($label) ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="form-group mb-0">
			<label for="chart_type">Jenis grafik</label>
			<select class="form-control form-select" id="chart_type" name="chart_type">
				<?php foreach ($chart_types as $code => $label): ?>
					<option value="<?= e($code) ?>"><?= e($label) ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?= ui_modal_actions('Tambah indikator') ?>
	</form>
<?= ui_modal_close() ?>
<?php endif; ?>
