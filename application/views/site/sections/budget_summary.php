<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Ringkasan APBDes dari snapshot publik. Halaman ini tidak pernah membaca tabel kerja
 * keuangan; bila snapshot ditarik, kartunya langsung kosong kembali.
 */
$budget = $d['budget'] ?? NULL;
$base = $budget ? (($budget['revisions']['amended'] ?? NULL) ?: ($budget['revisions']['original'] ?? NULL)) : NULL;
$realization = $budget['revisions']['realization'] ?? NULL;
$rupiah = function ($value) { return 'Rp'.number_format((float) $value, 0, ',', '.'); };
?>
<section class="section" aria-labelledby="anggaran-title">
	<div class="container-wide">
		<p class="eyebrow"><?= e($s['subtitle'] ?: 'Transparansi') ?></p>
		<h2 class="section-title" id="anggaran-title"><?= e($s['title'] ?: 'Ringkasan anggaran desa') ?></h2>
		<?php if ( ! $base): ?>
			<div class="empty-state">
				<?= icon('bar-chart-2') ?>
				<h3>Data anggaran belum diterbitkan</h3>
				<p class="mb-0">Ringkasan APBDes tampil setelah direkonsiliasi, diverifikasi, dan disetujui untuk publikasi.</p>
			</div>
		<?php else: ?>
			<?php if ( ! empty($budget['year']['is_provisional'])): ?>
				<p class="badge badge-warning">Angka sementara</p>
			<?php endif; ?>
			<div class="stats-grid">
				<div class="stat">
					<div class="stat-value"><?= e($rupiah($base['totals']['income'])) ?></div>
					<div class="stat-label">Pendapatan (<?= e($base['label']) ?>)</div>
				</div>
				<div class="stat">
					<div class="stat-value"><?= e($rupiah($base['totals']['expenditure'])) ?></div>
					<div class="stat-label">Belanja (<?= e($base['label']) ?>)</div>
				</div>
				<div class="stat">
					<div class="stat-value"><?= e($rupiah($base['totals']['surplus'])) ?></div>
					<div class="stat-label">Surplus atau defisit</div>
				</div>
				<div class="stat">
					<div class="stat-value"><?= $realization ? e($rupiah($realization['totals']['expenditure'])) : '&mdash;' ?></div>
					<div class="stat-label">Realisasi belanja<?= $realization ? '' : ' (belum ada)' ?></div>
				</div>
			</div>
			<p class="stats-source">
				<span><?= icon('file-text') ?> APBDes <?= (int) $budget['year']['fiscal_year'] ?> &middot; terbit <?= e(format_wib($budget['published_at'], 'date')) ?></span>
				<a class="text-white" href="<?= site_url('transparansi/anggaran') ?>">Lihat rinciannya</a>
			</p>
		<?php endif; ?>
	</div>
</section>
