<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Transparansi APBDes bertingkat (modul-frontend 11):
 * ringkasan -> perbandingan murni/perubahan/realisasi -> drilldown -> progres -> dokumen.
 */
$rupiah = function ($value) { return 'Rp'.number_format((float) $value, 0, ',', '.'); };
$revisions = $budget['revisions'] ?? array();
$base = ($revisions['amended'] ?? NULL) ?: ($revisions['original'] ?? NULL);
$order = array('original', 'amended', 'realization');
?>
<section class="page-hero">
	<div class="container-site">
		<nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-2"><li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li><li class="breadcrumb-item active" aria-current="page">Transparansi Anggaran</li></ol></nav>
		<h1>Transparansi anggaran desa</h1>
		<p>Anggaran murni, anggaran perubahan, dan realisasi ditampilkan terpisah supaya tidak tercampur menjadi satu angka.</p>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<?php if ( ! $budget): ?>
			<div class="empty-state">
				<?= icon('bar-chart-2') ?>
				<h2 class="h5">Belum ada APBDes yang diterbitkan</h2>
				<p class="mb-0">Data anggaran tampil setelah direkonsiliasi, diverifikasi, dan disetujui untuk publikasi oleh pengelola desa.</p>
			</div>
		<?php else: ?>
			<?php if (count($years) > 1): ?>
			<form class="filter-bar mb-4" method="get" action="<?= site_url('transparansi/anggaran') ?>">
				<div>
					<label class="form-label" for="tahun">Tahun anggaran</label>
					<select class="form-select" id="tahun" name="tahun">
						<?php foreach ($years as $year): ?>
							<option value="<?= (int) $year['fiscal_year'] ?>" <?= $selected === (int) $year['fiscal_year'] ? 'selected' : '' ?>><?= (int) $year['fiscal_year'] ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div><button class="btn btn-primary" type="submit">Tampilkan</button></div>
			</form>
			<?php endif; ?>

			<?php if ( ! empty($budget['year']['is_provisional'])): ?>
			<div class="alert alert-warning" role="status">
				<strong>Angka sementara.</strong> Data tahun <?= (int) $budget['year']['fiscal_year'] ?> belum final dan masih dapat berubah.
			</div>
			<?php endif; ?>

			<h2 class="section-title h3">Ringkasan <?= (int) $budget['year']['fiscal_year'] ?></h2>
			<?php if ($base): ?>
			<div class="data-stat-grid mb-4">
				<div class="data-stat"><span class="data-stat-label">Pendapatan</span><span class="data-stat-value"><?= e($rupiah($base['totals']['income'])) ?></span></div>
				<div class="data-stat"><span class="data-stat-label">Belanja</span><span class="data-stat-value"><?= e($rupiah($base['totals']['expenditure'])) ?></span></div>
				<div class="data-stat"><span class="data-stat-label">Surplus/defisit</span><span class="data-stat-value"><?= e($rupiah($base['totals']['surplus'])) ?></span></div>
				<div class="data-stat"><span class="data-stat-label">Pembiayaan neto</span><span class="data-stat-value"><?= e($rupiah($base['totals']['financing_net'])) ?></span></div>
			</div>
			<p class="small text-muted">Angka ringkasan mengikuti <?= e($base['label']) ?>.</p>
			<?php endif; ?>

			<h2 class="section-title h3 mt-5">Perbandingan</h2>
			<div class="table-wrap mb-4">
				<table class="table table-data mb-0">
					<caption class="visually-hidden">Perbandingan anggaran murni, perubahan, dan realisasi</caption>
					<thead>
						<tr><th scope="col">Kelompok</th>
						<?php foreach ($order as $type): if (empty($revisions[$type])) { continue; } ?>
							<th scope="col" class="num"><?= e($revisions[$type]['label']) ?></th>
						<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
					<?php foreach (array('income' => 'Pendapatan', 'expenditure' => 'Belanja', 'surplus' => 'Surplus/defisit', 'financing_net' => 'Pembiayaan neto') as $key => $label): ?>
						<tr>
							<th scope="row"><?= e($label) ?></th>
							<?php foreach ($order as $type): if (empty($revisions[$type])) { continue; } ?>
								<td class="num"><?= e($rupiah($revisions[$type]['totals'][$key] ?? 0)) ?></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php if (empty($revisions['realization'])): ?>
				<p class="small text-muted">Realisasi tahun ini belum diterbitkan, jadi kolomnya belum ada.</p>
			<?php endif; ?>

			<h2 class="section-title h3 mt-5">Rincian</h2>
			<?php foreach ($order as $type): if (empty($revisions[$type])) { continue; } $revision = $revisions[$type]; ?>
			<details class="mb-3" <?= $type === 'original' ? 'open' : '' ?>>
				<summary><strong><?= e($revision['label']) ?></strong><?= $revision['document_year'] ? ' — dokumen tahun '.(int) $revision['document_year'] : '' ?></summary>
				<div class="table-wrap mt-2">
					<table class="table table-data mb-0">
						<caption class="visually-hidden">Rincian <?= e($revision['label']) ?></caption>
						<thead><tr><th scope="col">Kelompok</th><th scope="col">Uraian</th><th scope="col" class="num">Jumlah</th><th scope="col">Catatan selisih</th></tr></thead>
						<tbody>
						<?php foreach ($revision['lines'] as $line): ?>
							<tr>
								<td><?= e($sections[$line['section']] ?? $line['section']) ?></td>
								<th scope="row" style="padding-left: <?= (int) $line['level'] * 12 ?>px">
									<?= $line['code'] ? '<code>'.e($line['code']).'</code> ' : '' ?><?= e($line['name']) ?>
								</th>
								<td class="num"><?= e($rupiah($line['amount'])) ?></td>
								<td class="small text-muted"><?= e($line['variance_note'] ?: '—') ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<p class="mt-2">
					<a class="btn btn-outline-primary btn-sm" href="<?= site_url('transparansi/anggaran/'.(int) $budget['year']['fiscal_year'].'/'.rawurlencode($type).'/unduh.csv') ?>"><?= icon('download') ?> Unduh CSV</a>
				</p>
			</details>
			<?php endforeach; ?>

			<?php if ( ! empty($budget['projects'])): ?>
			<h2 class="section-title h3 mt-5">Kegiatan dan progres fisik</h2>
			<div class="row g-4">
				<?php foreach ($budget['projects'] as $project): $last = $project['progress'] ? end($project['progress']) : NULL; ?>
				<div class="col-md-6">
					<article class="card-soft card-pad h-100">
						<h3 class="h6"><?= e($project['name']) ?></h3>
						<?php if ($project['location_public']): ?><p class="small mb-1"><?= icon('map-pin') ?> <?= e($project['location_public']) ?></p><?php endif; ?>
						<?php if ($project['target_output']): ?><p class="small mb-1">Target: <?= e($project['target_output']) ?></p><?php endif; ?>
						<?php if ($last): ?>
							<p class="mb-1">Progres fisik: <strong><?= e(number_format((float) $last['physical_percent'], 2, ',', '.')) ?>%</strong>
								<span class="small text-muted">per <?= e(format_date_id($last['reported_on'])) ?></span></p>
						<?php else: ?>
							<p class="small text-muted mb-1">Belum ada laporan progres.</p>
						<?php endif; ?>
						<?php if ($project['outcome_note']): ?><p class="small mb-0">Hasil: <?= e($project['outcome_note']) ?></p><?php endif; ?>
					</article>
				</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<?php if ( ! empty($budget['documents'])): ?>
			<h2 class="section-title h3 mt-5">Dokumen publik</h2>
			<ul>
				<?php foreach ($budget['documents'] as $document): ?>
					<li><a href="<?= site_url('dokumen/'.(int) $document['public_document_id'].'/unduh') ?>"><?= e($document['title']) ?></a>
						<?= $document['document_year'] ? ' ('.(int) $document['document_year'].')' : '' ?></li>
				<?php endforeach; ?>
			</ul>
			<p class="small text-muted">Hanya dokumen yang sudah disamarkan dan disetujui yang dapat diunduh di sini.</p>
			<?php endif; ?>

			<p class="small text-muted mt-4">
				Revisi <?= (int) $budget['revision_no'] ?>, diterbitkan <?= e(format_wib($budget['published_at'], 'short')) ?>.
				<a href="<?= site_url('transparansi/anggaran/'.(int) $budget['year']['fiscal_year'].'/unduh.json') ?>">Unduh JSON</a>
			</p>
		<?php endif; ?>
	</div>
</section>
