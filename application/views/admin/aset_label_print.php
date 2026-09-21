<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Lembar label QR aset siap cetak: A4, 3 kolom x 8 baris (template label.a4_24).
 * Halaman berdiri sendiri tanpa sidebar supaya pratinjau cetak bersih.
 */
$printable = array();
foreach ($items as $item)
{
	for ($i = 0; $item['url'] !== NULL && $i < $item['copies']; $i++) { $printable[] = $item; }
}
$labels = $printable;
$missing = count(array_filter($items, function ($item) { return $item['url'] === NULL; }));
?><!doctype html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title>Label QR aset — <?= count($labels) ?> label</title>
	<link rel="stylesheet" href="<?= asset_url('admin/css/asset-labels.css') ?>">
</head>
<body class="label-page">
	<header class="label-toolbar">
		<div>
			<strong>Label QR aset</strong> · <?= count($labels) ?> label · dibuat <?= e(format_wib($batch->created_at, 'short')) ?>
			<?php if ($missing): ?><div class="label-warning"><?= (int) $missing ?> unit QR-nya sudah dicabut/diganti sejak batch ini dibuat dan tidak dicetak. Buat batch baru dari halaman register.</div><?php endif; ?>
			<div class="label-hint">Gunakan kertas A4 (label 3 x 8). Pada dialog cetak pilih skala 100% / "Actual size" dan matikan header-footer.</div>
		</div>
		<div class="label-toolbar-actions">
			<button type="button" class="label-btn label-btn-primary" data-print>Cetak sekarang</button>
			<a class="label-btn" href="<?= site_url('admin/aset') ?>">Kembali ke Aset</a>
		</div>
	</header>

	<main>
		<?php foreach (array_chunk($printable, 24) as $page): ?>
		<section class="label-sheet">
			<?php foreach ($page as $item): ?>
			<div class="label-cell">
				<div class="label-qr" data-qr="<?= e($item['url']) ?>" role="img" aria-label="QR <?= e($item['unit']->asset_tag) ?>"></div>
				<div class="label-text">
					<div class="label-owner">Pemerintah Desa Cihawuk</div>
					<div class="label-name"><?= e(str_limit_id($item['name'], 48)) ?></div>
					<div class="label-tag"><?= e($item['unit']->asset_tag) ?></div>
					<div class="label-foot">Pindai untuk info / lapor kerusakan</div>
				</div>
			</div>
			<?php endforeach; ?>
		</section>
		<?php endforeach; ?>
		<?php if (empty($printable)): ?><p class="label-empty">Tidak ada label yang dapat dicetak.</p><?php endif; ?>
	</main>

	<script src="<?= asset_url('vendor/qrcode-generator/qrcode.js') ?>"></script>
	<script src="<?= asset_url('admin/js/asset-qr.js') ?>"></script>
</body>
</html>
