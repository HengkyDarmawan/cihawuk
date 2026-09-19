<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Angka beranda berasal dari snapshot dataset yang sudah terbit (modul-frontend 4.2).
 * Label, satuan, tahun, sumber dan tanggal publikasi ikut dataset supaya kartu tidak
 * pernah menyimpang dari halaman Data Desa.
 */
$cfg = $s['config'];
$series = $d['series'] ?? array();
$dataset = $d['dataset'] ?? NULL;
$version = $d['version'] ?? NULL;
$year = $version ? (int) $version['period_year'] : 0;
$source = $version ? trim((string) ($version['source_code'] ?: $version['source_note'])) : '';
$note = trim((string) ($cfg['source_note'] ?? ''));
if ($note === '' && $dataset)
{
	$note = $dataset['name'].' '.$year;
}
?>
<section class="section-sm" aria-labelledby="stat-title">
	<div class="container-wide">
		<div class="stats-band reveal">
			<p class="eyebrow"><?= e($s['subtitle'] ?: 'Data desa') ?></p>
			<h2 class="section-title" id="stat-title"><?= e($s['title'] ?: 'Cihawuk dalam angka') ?></h2>
			<?php if ( ! empty($series)): ?>
				<div class="stats-grid">
					<?php foreach ($series as $row): ?>
					<div class="stat">
						<?php if ( ! empty($row['suppressed'])): ?>
							<div class="stat-value" aria-hidden="true">&mdash;</div>
							<div class="stat-label"><?= e($row['label']) ?> <span class="visually-hidden">disamarkan karena jumlahnya kecil</span></div>
						<?php elseif ($row['value'] === NULL): ?>
							<div class="stat-value" aria-hidden="true">&mdash;</div>
							<div class="stat-label"><?= e($row['label']) ?> <span class="visually-hidden">belum diketahui</span></div>
						<?php else: ?>
							<div class="stat-value"><?= format_number_id($row['value'], (($row['value_type'] ?? 'integer') === 'integer') ? 0 : 2) ?></div>
							<div class="stat-label"><?= e($row['label']) ?><?php if ( ! empty($row['unit'])): ?> (<?= e($row['unit']) ?>)<?php endif; ?></div>
						<?php endif; ?>
					</div>
					<?php endforeach; ?>
				</div>
				<p class="stats-source">
					<span><?= icon('file-text') ?> <?= e($note) ?><?php if ($source !== ''): ?> &middot; sumber <?= e($source) ?><?php endif; ?><?php if ( ! empty($d['published_at'])): ?> &middot; terbit <?= e(format_wib($d['published_at'], 'date')) ?><?php endif; ?></span>
					<a class="text-white" href="<?= site_url('data-desa') ?>">Lihat tabel lengkap</a>
				</p>
			<?php else: ?>
				<div class="empty-state mt-3">
					<?= icon('bar-chart-2') ?>
					<h3>Statistik sedang direview</h3>
					<p class="mb-0">Angka dari dokumen profil desa akan tampil setelah datasetnya diverifikasi dan diterbitkan pengelola.</p>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
