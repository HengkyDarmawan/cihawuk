<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Data Desa: satu kartu per dataset terbit. Setiap grafik selalu disertai tabel angka,
 * satuan, tahun, sumber, dan unduhan CSV.
 */
$service = $this->datasets;
$number = function ($value) {
	return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
};
?>
<section class="page-hero">
	<div class="container-site">
		<nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-2"><li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li><li class="breadcrumb-item active" aria-current="page">Data Desa</li></ol></nav>
		<h1><?= $locked_theme === NULL ? 'Data desa' : e($themes[$locked_theme]) ?></h1>
		<p>Angka berikut berasal dari dokumen resmi desa, sudah diverifikasi pemeriksa, dan mencantumkan tahun sumbernya — bukan data realtime.</p>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<?php if ($total === 0): ?>
			<div class="empty-state">
				<?= icon('bar-chart-2') ?>
				<h2 class="h5">Belum ada dataset yang diterbitkan</h2>
				<p class="mb-0">Statistik dari dokumen profil desa sedang diverifikasi. Data tampil setelah pemeriksa menerima nilainya dan pengelola menerbitkannya.</p>
			</div>
		<?php else: ?>
			<form method="get" action="<?= site_url('data-desa') ?>" class="filter-bar mb-4">
				<div>
					<label class="form-label" for="tahun">Tahun</label>
					<select class="form-select" id="tahun" name="tahun">
						<option value="">Semua tahun</option>
						<?php foreach ($years as $year): ?>
							<option value="<?= (int) $year ?>" <?= (int) $filters['tahun'] === (int) $year ? 'selected' : '' ?>><?= (int) $year ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div>
					<label class="form-label" for="tema">Tema</label>
					<select class="form-select" id="tema" name="tema">
						<option value="">Semua tema</option>
						<?php foreach ($themes_present as $code): ?>
							<option value="<?= e($code) ?>" <?= $filters['tema'] === $code ? 'selected' : '' ?>><?= e($themes[$code] ?? $code) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php if ($sources_present): ?>
				<div>
					<label class="form-label" for="sumber">Sumber</label>
					<select class="form-select" id="sumber" name="sumber">
						<option value="">Semua sumber</option>
						<?php foreach ($sources_present as $code): ?>
							<option value="<?= e($code) ?>" <?= $filters['sumber'] === $code ? 'selected' : '' ?>><?= e($code) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php endif; ?>
				<?php if ($indicators_present): ?>
				<div>
					<label class="form-label" for="indikator">Indikator</label>
					<select class="form-select" id="indikator" name="indikator">
						<option value="">Semua indikator</option>
						<?php foreach ($indicators_present as $code => $label): ?>
							<option value="<?= e($code) ?>" <?= $filters['indikator'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php endif; ?>
				<div>
					<label class="form-label" for="q">Cari indikator</label>
					<input class="form-control" type="search" id="q" name="q" value="<?= e($filters['q']) ?>" maxlength="60">
				</div>
				<div><button class="btn btn-primary" type="submit">Tampilkan</button></div>
			</form>

			<?php if (empty($datasets)): ?>
				<div class="empty-state">
					<?= icon('search') ?>
					<h2 class="h5">Tidak ada dataset yang cocok</h2>
					<p class="mb-0">Ubah filter tahun, tema, atau kata kunci pencarian.</p>
				</div>
			<?php endif; ?>

			<?php foreach ($datasets as $index => $snapshot):
				$view = $service->presentation($snapshot);
				$meta = $snapshot['version'];
				$info = $snapshot['dataset'];
				$anchor = 'dataset-'.e($info['slug']); ?>
			<article class="data-card mb-5" aria-labelledby="<?= $anchor ?>">
				<header class="mb-3">
					<p class="eyebrow"><?= e($themes[$info['theme']] ?? $info['theme']) ?> · <?= (int) $meta['period_year'] ?><?= $meta['period_label'] ? ' · '.e($meta['period_label']) : '' ?></p>
					<h2 class="section-title h3" id="<?= $anchor ?>"><?= e($info['name']) ?></h2>
					<?php if ($info['description']): ?><p class="section-lead"><?= e($info['description']) ?></p><?php endif; ?>
				</header>

				<?php if ($view['cards']): ?>
				<div class="data-stat-grid mb-4">
					<?php foreach ($view['cards'] as $card): ?>
					<div class="data-stat">
						<span class="data-stat-label"><?= e($card['label']) ?></span>
						<span class="data-stat-value">
							<?php if ($card['suppressed']): ?>
								<abbr title="Disamarkan karena jumlahnya terlalu kecil untuk ditampilkan">&lt;<?= (int) $service->small_count_threshold() ?></abbr>
							<?php elseif ($card['value'] === NULL): ?>
								<span class="text-muted">tidak diketahui</span>
							<?php else: ?>
								<?= e($number($card['value'])) ?>
							<?php endif; ?>
						</span>
						<span class="data-stat-unit"><?= e($card['unit']) ?></span>
					</div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<?php foreach ($view['charts'] as $c => $chart): $id = 'chart-'.e($info['slug']).'-'.$c; ?>
				<figure class="chart-block mb-4">
					<figcaption class="small text-muted mb-2">
						<?= e($chart['title']) ?> — satuan <?= e($chart['unit']) ?>, tahun <?= (int) $meta['period_year'] ?>.
					</figcaption>
					<div class="chart-canvas"><canvas id="<?= $id ?>" height="260" role="img"
						aria-label="Grafik <?= e($chart['title']) ?>. Angka yang sama tersedia pada tabel di bawah."></canvas></div>
					<script type="application/json" id="<?= $id ?>-data"><?= json_encode(array(
						'type' => $chart['type'],
						'labels' => $chart['labels'],
						'series' => array(array('label' => $chart['unit'], 'data' => $chart['values'])),
					), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
				</figure>
				<?php endforeach; ?>

				<div class="table-wrap mb-3">
					<table class="table table-data mb-0">
						<caption class="visually-hidden">Angka <?= e($info['name']) ?> tahun <?= (int) $meta['period_year'] ?></caption>
						<thead><tr><th scope="col">Indikator</th><th scope="col" class="num">Nilai</th><th scope="col">Satuan</th><th scope="col">Catatan</th></tr></thead>
						<tbody>
						<?php foreach ($view['table'] as $row): ?>
							<tr>
								<th scope="row"><?= e($row['label']) ?></th>
								<td class="num">
									<?php if ($row['suppressed']): ?>
										&lt;<?= (int) $service->small_count_threshold() ?>
									<?php elseif ($row['value'] !== NULL): ?>
										<?= e($number($row['value'])) ?>
									<?php elseif ($row['text_value']): ?>
										<?= e($row['text_value']) ?>
									<?php else: ?>
										<span class="text-muted">tidak diketahui</span>
									<?php endif; ?>
								</td>
								<td><?= e($row['unit']) ?></td>
								<td class="small text-muted"><?= e($row['note'] ?: $row['definition']) ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<footer class="data-meta small text-muted">
					<p class="mb-1">
						<strong>Sumber:</strong>
						<?= e($meta['source_code'] ? $meta['source_code'].' — '.$meta['source_title'] : ($meta['source_note'] ?: 'tidak dicantumkan')) ?>
						· <strong>Cakupan:</strong> <?= e($info['coverage']) ?>
						· <strong>Terbit:</strong> <?= e(format_wib($snapshot['published_at'], 'short')) ?> (revisi <?= (int) $snapshot['revision_no'] ?>)
					</p>
					<?php if ($meta['methodology']): ?><p class="mb-1"><strong>Metodologi:</strong> <?= e($meta['methodology']) ?></p><?php endif; ?>
					<?php if ($meta['quality_note']): ?><p class="mb-1"><strong>Catatan kualitas:</strong> <?= e($meta['quality_note']) ?></p><?php endif; ?>
					<p class="mb-0"><a class="btn btn-outline-primary btn-sm" href="<?= site_url('data-desa/'.rawurlencode($info['slug']).'/unduh.csv') ?>"><?= icon('download') ?> Unduh CSV</a></p>
				</footer>
			</article>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</section>
