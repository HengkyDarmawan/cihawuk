<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="page-hero">
	<div class="container-site">
		<nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-2"><li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li><li class="breadcrumb-item active" aria-current="page">Dokumen Publik</li></ol></nav>
		<h1>Dokumen publik</h1>
		<p>Dokumen yang sudah disetujui untuk dipublikasikan. Dokumen sumber internal tidak ditampilkan di sini.</p>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<?php if ( ! empty($apbdes)):
			$short = function ($value) {
				$sign = (float) $value < 0 ? '−' : '';
				$value = abs((float) $value);
				if ($value >= 1e9) { return $sign.'Rp'.rtrim(rtrim(number_format($value / 1e9, 2, ',', '.'), '0'), ',').' miliar'; }
				if ($value >= 1e6) { return $sign.'Rp'.rtrim(rtrim(number_format($value / 1e6, 1, ',', '.'), '0'), ',').' juta'; }
				return $sign.'Rp'.number_format($value, 0, ',', '.');
			};
			$names = array_map(function ($b) {
				foreach (array('/pemerintahan/i' => 'Pemerintahan', '/pembangunan/i' => 'Pembangunan', '/pembinaan/i' => 'Pembinaan', '/pemberdayaan/i' => 'Pemberdayaan', '/bencana|darurat|mendesak/i' => 'Bencana & mendesak') as $p => $n)
				{
					if (preg_match($p, $b['name'])) { return $n; }
				}
				return $b['name'];
			}, $apbdes['bidang']); ?>
		<article class="apbdes-card" aria-labelledby="apbdes-title">
			<div>
				<p class="eyebrow mb-1">Transparansi anggaran</p>
				<h2 id="apbdes-title">APBDes <?= (int) $apbdes['fiscal_year'] ?> — ringkasan &amp; rincian<?= $apbdes['is_demo'] ? ' <span class="apbdes-demo">Data contoh</span>' : '' ?></h2>
				<p class="mb-0">Lihat dari mana uang desa berasal, dipakai untuk apa, dan rincian setiap kegiatan — lengkap dengan grafik dan penjelasan sederhana.</p>
				<div class="apbdes-stats">
					<div><span>Pendapatan</span><strong><?= e($short($apbdes['totals']['income'])) ?></strong></div>
					<div><span>Belanja</span><strong><?= e($short($apbdes['totals']['expenditure'])) ?></strong></div>
					<div><span>Bidang belanja</span><strong><?= count($apbdes['bidang']) ?> bidang</strong></div>
				</div>
				<a class="btn btn-accent" href="<?= site_url('transparansi/anggaran') ?>">Buka rincian anggaran <?= icon('arrow-right') ?></a>
			</div>
			<?php if ($apbdes['bidang']): ?>
			<figure class="apbdes-chart mb-0">
				<div class="budget-chart-box"><canvas id="chart-dokumen-apbdes" role="img" aria-label="Grafik belanja APBDes <?= (int) $apbdes['fiscal_year'] ?> per bidang. Rinciannya ada di halaman transparansi anggaran."></canvas></div>
				<script type="application/json" id="chart-dokumen-apbdes-data"><?= json_encode(array('type' => 'donut', 'format' => 'rupiah', 'labels' => $names,
					'series' => array(array('label' => 'Belanja', 'data' => array_column($apbdes['bidang'], 'total')))), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
				<figcaption class="small text-muted text-center mt-2">Belanja per bidang (<?= e($apbdes['label']) ?>)</figcaption>
			</figure>
			<?php endif; ?>
		</article>
		<?php endif; ?>

		<nav class="filter-chips" aria-label="Filter dokumen">
			<a class="chip" href="<?= site_url('dokumen') ?>" <?= $active_category === '' && ! $active_year ? 'aria-current="true"' : '' ?>>Semua</a>
			<?php foreach ($categories as $cat): ?>
				<a class="chip" href="<?= site_url('dokumen?kategori='.rawurlencode($cat->slug)) ?>" <?= $active_category === $cat->slug ? 'aria-current="true"' : '' ?>><?= e($cat->name) ?></a>
			<?php endforeach; ?>
			<?php foreach ($years as $year): ?>
				<a class="chip" href="<?= site_url('dokumen?tahun='.(int) $year) ?>" <?= $active_year === (int) $year ? 'aria-current="true"' : '' ?>><?= (int) $year ?></a>
			<?php endforeach; ?>
		</nav>

		<?php if (empty($documents)): ?>
			<div class="empty-state">
				<?= icon('folder') ?>
				<h2 class="h5">Belum ada dokumen publik</h2>
				<p class="mb-0">Dokumen akan tersedia setelah pengelola menyetujui versi yang boleh dipublikasikan.</p>
			</div>
		<?php else: ?>
			<div class="d-grid gap-3">
				<?php foreach ($documents as $doc): ?>
				<div class="doc-item">
					<span class="doc-icon" aria-hidden="true"><?= icon('file-text') ?></span>
					<div class="flex-grow-1">
						<h2 class="h6 mb-1"><?= e($doc->title) ?></h2>
						<div class="meta">
							<?php if ($doc->category_name): ?><span><?= e($doc->category_name) ?></span><?php endif; ?>
							<?php if ($doc->source_year): ?><span>Tahun <?= (int) $doc->source_year ?></span><?php endif; ?>
							<span>Versi <?= e($doc->version_label) ?></span>
							<span><?= e(format_bytes_id($doc->byte_size)) ?></span>
						</div>
						<?php if ($doc->description): ?><p class="small text-muted mb-0 mt-1"><?= e($doc->description) ?></p><?php endif; ?>
					</div>
					<a class="btn btn-outline-primary btn-sm" href="<?= site_url('dokumen/'.(int) $doc->id.'/unduh') ?>"><?= icon('download') ?> Unduh</a>
				</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
