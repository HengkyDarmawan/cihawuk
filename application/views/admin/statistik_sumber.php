<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="page-heading">
	<div>
		<h1>Dokumen sumber</h1>
		<p>Register empat dokumen desa beserta status impornya. Berkas sumber tersimpan privat di <code>reference/documents/</code> dan tidak dilayani web.</p>
	</div>
	<a class="btn btn-outline-primary" href="<?= site_url('admin/statistik') ?>">Kembali</a>
</div>

<?php foreach ($sources as $source): ?>
<div class="card shadow-sm mb-3">
	<div class="card-body">
		<div class="d-flex flex-wrap justify-content-between gap-3">
			<div>
				<h2 class="h6 mb-1"><?= e($source->source_code) ?> — <?= e($source->title) ?></h2>
				<p class="small text-muted mb-1">
					<?= e($source->original_filename) ?>
					<?= $source->source_year ? ' · tahun isi '.(int) $source->source_year : '' ?>
					· <?= (int) $source->observation_count ?> observasi
				</p>
				<?php if ($source->usage_note): ?><p class="small mb-1"><?= e($source->usage_note) ?></p><?php endif; ?>
				<p class="small mb-0">
					<span class="chip-flag <?= $source->import_status === 'imported' ? 'is-info' : ($source->import_status === 'needs_conversion' ? 'is-warning' : '') ?>">
						<?php
						$labels = array('not_imported' => 'belum diimpor', 'imported' => 'sudah diimpor', 'needs_conversion' => 'perlu konversi .doc → .docx');
						echo e($labels[$source->import_status] ?? $source->import_status);
						?>
					</span>
					<?php if ( ! $source->file_present): ?><span class="chip-flag is-danger">berkas tidak ditemukan</span><?php endif; ?>
					<?php if ($source->checksum): ?><span class="text-muted">SHA-256 <?= e(substr($source->checksum, 0, 12)) ?>…</span><?php endif; ?>
					<?php if ($source->imported_at): ?><span class="text-muted">· impor terakhir <?= e(format_wib($source->imported_at, 'short')) ?></span><?php endif; ?>
				</p>
			</div>
			<div class="text-right">
				<?php if ($can_review && $source->file_present): ?>
				<form method="post" action="<?= site_url('admin/statistik/impor') ?>" class="mb-2" data-confirm="Impor ulang dokumen ini? Nilai mentah lama tidak ditimpa; perbedaan dicatat sebagai konflik." data-confirm-ok="Impor">
					<?= csrf_field() ?>
					<input type="hidden" name="source_code" value="<?= e($source->source_code) ?>">
					<button class="btn btn-primary btn-sm" type="submit">Impor / impor ulang</button>
				</form>
				<?php endif; ?>
				<a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/statistik/sumber/'.(int) $source->id) ?>">Lihat observasi</a>
			</div>
		</div>

		<?php if ( ! empty($source->batches)): ?>
		<div class="table-responsive mt-3">
			<table class="table table-sm mb-0">
				<caption class="sr-only">Riwayat batch impor</caption>
				<thead><tr><th scope="col">Batch</th><th scope="col">Status</th><th scope="col">Baris</th><th scope="col">Baru</th><th scope="col">Konflik</th><th scope="col">Kosong</th><th scope="col">Waktu</th></tr></thead>
				<tbody>
				<?php foreach ($source->batches as $batch): ?>
					<tr>
						<td>#<?= (int) $batch->id ?></td>
						<td><?= e($batch->status) ?></td>
						<td><?= (int) $batch->row_count ?></td>
						<td><?= (int) $batch->accepted_count ?></td>
						<td><?= (int) $batch->conflict_count ?></td>
						<td><?= (int) $batch->empty_count ?></td>
						<td class="small"><?= e(format_wib($batch->started_at, 'short')) ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>
	</div>
</div>
<?php endforeach; ?>

<div class="alert alert-info small">
	Dokumen <code>.doc</code> lama (S3) tidak diparse langsung oleh aplikasi. Konversi ke <code>.docx</code> dilakukan di tahap staging
	(misalnya dengan LibreOffice) sebelum diimpor; aplikasi web tidak pernah menjalankan konversi dokumen per request.
</div>
