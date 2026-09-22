<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Transparansi APBDes bertingkat (modul-frontend 11), disusun supaya mudah dibaca warga:
 * ringkasan + penjelasan -> grafik -> rincian per bidang -> perbandingan & tabel lengkap.
 * Semua angka berasal dari snapshot publik; $view hanya menyusun ulang (Transparansi::explain).
 */
$rupiah = function ($value) { return ((float) $value < 0 ? '−' : '').'Rp'.number_format(abs((float) $value), 0, ',', '.'); };
$short = function ($value) {
	$sign = (float) $value < 0 ? '−' : '';
	$value = abs((float) $value);
	if ($value >= 1e9) { return $sign.'Rp'.rtrim(rtrim(number_format($value / 1e9, 2, ',', '.'), '0'), ',').' miliar'; }
	if ($value >= 1e6) { return $sign.'Rp'.rtrim(rtrim(number_format($value / 1e6, 1, ',', '.'), '0'), ',').' juta'; }
	return $sign.'Rp'.number_format($value, 0, ',', '.');
};
$pct = function ($share) { return number_format($share * 100, 1, ',', '.').'%'; };
$json = function (array $data) { return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); };
$revisions = $budget['revisions'] ?? array();
$order = array('original', 'amended', 'realization');
$fiscal = $budget ? (int) $budget['year']['fiscal_year'] : 0;
?>
<section class="page-hero">
	<div class="container-site">
		<nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-2"><li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li><li class="breadcrumb-item active" aria-current="page">Transparansi Anggaran</li></ol></nav>
		<h1>Transparansi anggaran desa</h1>
		<p>Dari mana uang desa berasal, dipakai untuk apa, dan rinciannya per kegiatan — dijelaskan dengan grafik dan bahasa sederhana.</p>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<?php if ( ! $budget OR ! $view): ?>
			<div class="empty-state">
				<?= icon('bar-chart-2') ?>
				<h2 class="h5">Belum ada APBDes yang diterbitkan</h2>
				<p class="mb-0">Data anggaran tampil setelah direkonsiliasi, diverifikasi, dan disetujui untuk publikasi oleh pengelola desa.</p>
			</div>
		<?php else: $base = $view['base']; ?>
			<div class="budget-toolbar">
				<?php if (count($years) > 1): ?>
				<form class="budget-year" method="get" action="<?= site_url('transparansi/anggaran') ?>">
					<label class="form-label mb-0" for="tahun">Tahun anggaran</label>
					<select class="form-select" id="tahun" name="tahun">
						<?php foreach ($years as $year): ?>
							<option value="<?= (int) $year['fiscal_year'] ?>" <?= $fiscal === (int) $year['fiscal_year'] ? 'selected' : '' ?>><?= (int) $year['fiscal_year'] ?></option>
						<?php endforeach; ?>
					</select>
					<button class="btn btn-primary" type="submit">Tampilkan</button>
				</form>
				<?php endif; ?>
				<nav class="budget-jump" aria-label="Bagian halaman">
					<a href="#ringkasan">Ringkasan</a><a href="#grafik">Grafik</a><a href="#rincian">Rincian per bidang</a><a href="#tabel">Tabel lengkap</a>
				</nav>
			</div>

			<?php if ($view['is_demo']): ?>
			<div class="budget-demo" role="note"><?= icon('info') ?><div><strong>Data contoh.</strong> Angka dan rincian kegiatan di halaman ini adalah contoh demonstrasi, bukan APBDes resmi Desa Cihawuk.</div></div>
			<?php endif; ?>
			<?php if ( ! empty($budget['year']['is_provisional'])): ?>
			<div class="alert alert-warning" role="status"><strong>Angka sementara.</strong> Data tahun <?= $fiscal ?> belum final dan masih dapat berubah.</div>
			<?php endif; ?>

			<!-- 1. Ringkasan -->
			<h2 class="section-title h3" id="ringkasan">Ringkasan APBDes <?= $fiscal ?></h2>
			<div class="budget-kpis">
				<div class="budget-kpi is-income"><span class="budget-kpi-icon"><?= icon('trending-up') ?></span><span class="budget-kpi-label">Pendapatan</span><span class="budget-kpi-value"><?= e($short($base['totals']['income'])) ?></span><span class="budget-kpi-exact"><?= e($rupiah($base['totals']['income'])) ?></span></div>
				<div class="budget-kpi is-spend"><span class="budget-kpi-icon"><?= icon('pie-chart') ?></span><span class="budget-kpi-label">Belanja</span><span class="budget-kpi-value"><?= e($short($base['totals']['expenditure'])) ?></span><span class="budget-kpi-exact"><?= e($rupiah($base['totals']['expenditure'])) ?></span></div>
				<div class="budget-kpi"><span class="budget-kpi-icon"><?= icon('layers') ?></span><span class="budget-kpi-label">Surplus/defisit</span><span class="budget-kpi-value"><?= e($short($base['totals']['surplus'])) ?></span><span class="budget-kpi-exact"><?= e($rupiah($base['totals']['surplus'])) ?></span></div>
				<div class="budget-kpi"><span class="budget-kpi-icon"><?= icon('dollar-sign') ?></span><span class="budget-kpi-label">Pembiayaan neto</span><span class="budget-kpi-value"><?= e($short($base['totals']['financing_net'])) ?></span><span class="budget-kpi-exact"><?= e($rupiah($base['totals']['financing_net'])) ?></span></div>
			</div>

			<div class="budget-explain">
				<h3 class="h6"><?= icon('info') ?> Penjelasan singkat</h3>
				<p>Pada tahun <?= $fiscal ?>, desa merencanakan pendapatan <strong><?= e($short($base['totals']['income'])) ?></strong> dan belanja <strong><?= e($short($base['totals']['expenditure'])) ?></strong><?= $base['totals']['surplus'] < 0 ? '. Kekurangannya ditutup dari pembiayaan, misalnya sisa anggaran tahun lalu' : '' ?>.
				Angka ini mengikuti <?= e($base['label']) ?>.</p>
				<?php if ($view['per100']): ?>
				<p class="mb-2">Dari setiap <strong>Rp100.000</strong> belanja desa:</p>
				<ul class="budget-per100">
					<?php foreach ($view['per100'] as $row): ?>
						<li><span class="budget-per100-amount"><?= e($rupiah($row['amount'])) ?></span> untuk <?= e(mb_strtolower($row['name'])) ?></li>
					<?php endforeach; ?>
					<?php $rest = 100000 - array_sum(array_column($view['per100'], 'amount')); if ($rest > 0 && count($view['bidang']) > count($view['per100'])): ?>
						<li><span class="budget-per100-amount"><?= e($rupiah($rest)) ?></span> untuk bidang lainnya</li>
					<?php endif; ?>
				</ul>
				<?php endif; ?>
			</div>

			<!-- 2. Grafik -->
			<h2 class="section-title h3 mt-5" id="grafik">Dari mana dan untuk apa</h2>
			<div class="budget-charts">
				<?php if ($view['income']): $top = $view['income']; usort($top, function ($a, $b) { return $b['total'] <=> $a['total']; }); $income_total = array_sum(array_column($view['income'], 'total')); ?>
				<figure class="budget-chart card-soft">
					<figcaption><h3 class="h6 mb-1">Dari mana uangnya?</h3>
						<p class="small text-muted mb-0">Sumber terbesar adalah <strong><?= e($top[0]['name']) ?></strong> (<?= e($pct($income_total > 0 ? $top[0]['total'] / $income_total : 0)) ?> dari seluruh pendapatan).</p></figcaption>
					<div class="budget-chart-box"><canvas id="chart-anggaran-pendapatan" role="img" aria-label="Grafik sumber pendapatan desa. Angka yang sama ada pada tabel di bawahnya."></canvas></div>
					<script type="application/json" id="chart-anggaran-pendapatan-data"><?= $json(array('type' => 'donut', 'format' => 'rupiah',
						'labels' => array_column($view['income'], 'name'), 'series' => array(array('label' => 'Pendapatan', 'data' => array_column($view['income'], 'total'))))) ?></script>
					<details class="budget-table-toggle"><summary>Lihat angka</summary>
						<table class="table table-sm mb-0"><tbody>
						<?php foreach ($view['income'] as $node): ?>
							<tr><th scope="row"><?= e($node['name']) ?></th><td class="num"><?= e($rupiah($node['total'])) ?></td><td class="num"><?= e($pct($income_total > 0 ? $node['total'] / $income_total : 0)) ?></td></tr>
						<?php endforeach; ?>
						</tbody></table>
					</details>
				</figure>
				<?php endif; ?>

				<?php if ($view['bidang']): $big = $view['bidang']; usort($big, function ($a, $b) { return $b['total'] <=> $a['total']; }); ?>
				<figure class="budget-chart card-soft">
					<figcaption><h3 class="h6 mb-1">Dipakai untuk apa?</h3>
						<p class="small text-muted mb-0">Porsi terbesar untuk <strong><?= e(mb_strtolower($big[0]['short'])) ?></strong> (<?= e($pct($big[0]['share'])) ?> dari belanja).</p></figcaption>
					<div class="budget-chart-box"><canvas id="chart-anggaran-belanja" role="img" aria-label="Grafik belanja desa per bidang. Angka yang sama ada pada tabel di bawahnya."></canvas></div>
					<script type="application/json" id="chart-anggaran-belanja-data"><?= $json(array('type' => 'donut', 'format' => 'rupiah',
						'labels' => array_column($view['bidang'], 'short'), 'series' => array(array('label' => 'Belanja', 'data' => array_column($view['bidang'], 'total'))))) ?></script>
					<details class="budget-table-toggle"><summary>Lihat angka</summary>
						<table class="table table-sm mb-0"><tbody>
						<?php foreach ($view['bidang'] as $node): ?>
							<tr><th scope="row"><?= e($node['name']) ?></th><td class="num"><?= e($rupiah($node['total'])) ?></td><td class="num"><?= e($pct($node['share'])) ?></td></tr>
						<?php endforeach; ?>
						</tbody></table>
					</details>
				</figure>
				<?php endif; ?>

				<?php if ($view['compare']): $cmp = $view['compare']; ?>
				<figure class="budget-chart budget-chart-wide card-soft">
					<figcaption><h3 class="h6 mb-1">Rencana dibanding realisasi</h3>
						<p class="small text-muted mb-0">Batang hijau adalah rencana (<?= e($cmp['plan_label']) ?>), batang emas adalah yang benar-benar dibelanjakan (<?= e($cmp['real_label']) ?>). Realisasi di atas rencana wajib diberi penjelasan pada tabel lengkap.</p></figcaption>
					<div class="budget-chart-box is-tall"><canvas id="chart-anggaran-realisasi" role="img" aria-label="Grafik rencana dibanding realisasi belanja per bidang. Angka yang sama ada pada tabel di bawahnya."></canvas></div>
					<script type="application/json" id="chart-anggaran-realisasi-data"><?= $json(array('type' => 'bar', 'format' => 'rupiah', 'labels' => $cmp['labels'],
						'series' => array(array('label' => 'Rencana', 'data' => $cmp['plan']), array('label' => 'Realisasi', 'data' => $cmp['real'])))) ?></script>
					<details class="budget-table-toggle"><summary>Lihat angka</summary>
						<div class="table-responsive"><table class="table table-sm mb-0">
							<thead><tr><th scope="col">Bidang</th><th scope="col" class="num">Rencana</th><th scope="col" class="num">Realisasi</th><th scope="col" class="num">Serapan</th></tr></thead>
							<tbody>
							<?php foreach ($cmp['labels'] as $i => $label): ?>
								<tr><th scope="row"><?= e($label) ?></th><td class="num"><?= e($rupiah($cmp['plan'][$i])) ?></td><td class="num"><?= e($rupiah($cmp['real'][$i])) ?></td>
									<td class="num"><?= $cmp['plan'][$i] > 0 ? e($pct($cmp['real'][$i] / $cmp['plan'][$i])) : '—' ?></td></tr>
							<?php endforeach; ?>
							</tbody>
						</table></div>
					</details>
				</figure>
				<?php elseif ( ! empty($revisions['original']) OR ! empty($revisions['amended'])): ?>
				<p class="small text-muted budget-chart-wide">Realisasi tahun ini belum diterbitkan (APBDes <?= $fiscal ?>), jadi perbandingan rencana dan realisasi belum bisa ditampilkan.</p>
				<?php endif; ?>
			</div>

			<!-- 3. Rincian per bidang -->
			<?php if ($view['highlights']): ?>
			<h2 class="section-title h3 mt-5">Sorotan</h2>
			<div class="budget-highlights">
				<?php foreach ($view['highlights'] as $hl): ?>
				<article class="budget-highlight">
					<div class="budget-highlight-head">
						<span class="budget-highlight-icon"><?= icon($hl['icon']) ?></span>
						<div><h3 class="h5 mb-0"><?= e($hl['title']) ?></h3><p class="small mb-0"><?= e($hl['intro']) ?></p></div>
						<span class="budget-highlight-total"><?= e($short($hl['total'])) ?></span>
					</div>
					<ul class="budget-items">
						<?php foreach ($hl['nodes'] as $node): $leaves = $node['children'] ?: array($node); ?>
							<?php foreach ($leaves as $leaf): ?>
							<li><span class="budget-item-name"><?= e($leaf['name']) ?></span><span class="budget-item-amount"><?= e($short($leaf['total'])) ?></span></li>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</ul>
				</article>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<h2 class="section-title h3 mt-5" id="rincian">Rincian per bidang</h2>
			<p class="section-lead">Belanja desa dibagi ke lima bidang. Buka setiap bidang untuk melihat kegiatan dan barang yang dibiayai beserta anggarannya.</p>
			<div class="budget-bidang-list">
				<?php foreach ($view['bidang'] as $i => $node): ?>
				<article class="budget-bidang">
					<div class="budget-bidang-head">
						<span class="budget-bidang-no"><?= $i + 1 ?></span>
						<div class="budget-bidang-title">
							<h3 class="h5 mb-1"><?= e($node['name']) ?></h3>
							<?php if ($node['about']): ?><p class="small text-muted mb-0"><?= e($node['about']) ?></p><?php endif; ?>
						</div>
						<div class="budget-bidang-sum"><strong><?= e($short($node['total'])) ?></strong><span><?= e($pct($node['share'])) ?> dari belanja</span></div>
					</div>
					<div class="budget-bar" aria-hidden="true"><span style="width: <?= round($node['share'] * 100, 1) ?>%"></span></div>
					<?php if ($node['children']): ?>
					<details class="budget-bidang-detail">
						<summary>Lihat <?= count($node['children']) ?> rincian</summary>
						<?php foreach ($node['children'] as $sub): ?>
							<?php if ($sub['children']): ?>
							<div class="budget-sub">
								<div class="budget-sub-head"><span><?= e($sub['name']) ?></span><strong><?= e($short($sub['total'])) ?></strong></div>
								<ul class="budget-items">
									<?php foreach ($sub['children'] as $leaf): $share = $sub['total'] > 0 ? $leaf['total'] / $sub['total'] : 0; ?>
									<li>
										<span class="budget-item-name"><?= e($leaf['name']) ?></span>
										<span class="budget-item-amount" title="<?= e($rupiah($leaf['total'])) ?>"><?= e($short($leaf['total'])) ?></span>
										<span class="budget-item-bar" aria-hidden="true"><span style="width: <?= round($share * 100, 1) ?>%"></span></span>
									</li>
									<?php endforeach; ?>
								</ul>
							</div>
							<?php else: $share = $node['total'] > 0 ? $sub['total'] / $node['total'] : 0; ?>
							<ul class="budget-items">
								<li>
									<span class="budget-item-name"><?= e($sub['name']) ?></span>
									<span class="budget-item-amount" title="<?= e($rupiah($sub['total'])) ?>"><?= e($short($sub['total'])) ?></span>
									<span class="budget-item-bar" aria-hidden="true"><span style="width: <?= round($share * 100, 1) ?>%"></span></span>
								</li>
							</ul>
							<?php endif; ?>
						<?php endforeach; ?>
					</details>
					<?php endif; ?>
				</article>
				<?php endforeach; ?>
			</div>

			<!-- 4. Perbandingan & tabel lengkap -->
			<h2 class="section-title h3 mt-5" id="tabel">Perbandingan dan tabel lengkap</h2>
			<div class="table-wrap mb-3">
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

			<?php foreach ($order as $type): if (empty($revisions[$type])) { continue; } $revision = $revisions[$type]; ?>
			<details class="budget-full mb-3">
				<summary><strong>Tabel lengkap <?= e($revision['label']) ?></strong><?= $revision['document_year'] ? ' — dokumen tahun '.(int) $revision['document_year'] : '' ?></summary>
				<div class="table-wrap mt-2">
					<table class="table table-data mb-0">
						<caption class="visually-hidden">Rincian <?= e($revision['label']) ?></caption>
						<thead><tr><th scope="col">Kelompok</th><th scope="col">Uraian</th><th scope="col" class="num">Jumlah</th><th scope="col">Catatan selisih</th></tr></thead>
						<tbody>
						<?php foreach ($view['ordered'][$type] as $line): ?>
							<tr class="<?= $line['depth'] === 0 ? 'budget-row-top' : '' ?>">
								<td><?= e($sections[$line['section']] ?? $line['section']) ?></td>
								<th scope="row" style="padding-left: <?= 16 + (int) $line['depth'] * 20 ?>px">
									<?= $line['code'] ? '<code>'.e($line['code']).'</code> ' : '' ?><?= e($line['name']) ?>
								</th>
								<td class="num"><?= e($rupiah($line['amount'])) ?></td>
								<td class="small text-muted"><?= e($line['variance_note'] ?: '—') ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<p class="mt-2 mb-0">
					<a class="btn btn-outline-primary btn-sm" href="<?= site_url('transparansi/anggaran/'.$fiscal.'/'.rawurlencode($type).'/unduh.csv') ?>"><?= icon('download') ?> Unduh CSV</a>
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
				<a href="<?= site_url('transparansi/anggaran/'.$fiscal.'/unduh.json') ?>">Unduh JSON</a>
			</p>
		<?php endif; ?>
	</div>
</section>
