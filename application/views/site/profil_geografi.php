<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Geografi desa. Konflik luas wilayah ditampilkan terbuka sebagai catatan
 * (modul-frontend 14 butir 4); kode tidak memilih satu angka sebagai luas resmi.
 */
$geo = $blocks['geography']['body'] ?? array();
$land_sum = 0.0;
foreach ((array) ($geo['land_use'] ?? array()) as $row)
{
	$land_sum += (float) str_replace(',', '.', (string) $row['value']);
}
?>
<section class="page-hero">
	<div class="container-site">
		<nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-2"><li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li><li class="breadcrumb-item"><a href="<?= site_url('profil') ?>">Profil Desa</a></li><li class="breadcrumb-item active" aria-current="page">Geografi</li></ol></nav>
		<h1>Geografi Desa Cihawuk</h1>
		<p>Ketinggian, iklim, penggunaan lahan, dan batas wilayah menurut dokumen sumber beserta catatan konfliknya.</p>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<?php if (empty($geo)): ?>
			<div class="empty-state">
				<?= icon('map') ?>
				<h2 class="h5">Data geografi belum diterbitkan</h2>
				<p class="mb-0">Angka ketinggian, iklim, dan penggunaan lahan sedang diverifikasi pengelola desa.</p>
			</div>
		<?php else: ?>
			<div class="row g-5">
				<div class="col-lg-6">
					<h2 class="h5">Iklim dan topografi</h2>
					<table class="table table-sm">
						<caption class="visually-hidden">Ketinggian dan iklim</caption>
						<tbody>
							<?php if (isset($geo['elevation_masl'])): ?><tr><th scope="row">Ketinggian</th><td><?= format_number_id($geo['elevation_masl']) ?> mdpl</td></tr><?php endif; ?>
							<?php if (isset($geo['avg_temperature_c'])): ?><tr><th scope="row">Suhu rata-rata harian</th><td><?= format_number_id($geo['avg_temperature_c'], 2) ?> &deg;C</td></tr><?php endif; ?>
							<?php if (isset($geo['rainfall_mm'])): ?><tr><th scope="row">Curah hujan</th><td><?= format_number_id($geo['rainfall_mm']) ?> mm</td></tr><?php endif; ?>
							<?php if (isset($geo['rainy_months'])): ?><tr><th scope="row">Bulan hujan</th><td><?= (int) $geo['rainy_months'] ?> bulan</td></tr><?php endif; ?>
						</tbody>
					</table>

					<?php if ( ! empty($geo['boundaries'])): ?>
					<h2 class="h5">Batas wilayah</h2>
					<table class="table table-sm">
						<caption class="visually-hidden">Batas wilayah menurut dokumen sumber</caption>
						<tbody>
						<?php foreach ($geo['boundaries'] as $row): ?>
							<tr><th scope="row"><?= e($row['label']) ?></th><td><?= e($row['value']) ?></td></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<p class="small text-muted">Ejaan nama desa ditulis apa adanya seperti pada dokumen sumber.</p>
					<?php endif; ?>
				</div>

				<div class="col-lg-6">
					<?php if ( ! empty($geo['land_use'])): ?>
					<h2 class="h5">Penggunaan lahan</h2>
					<div class="table-wrap">
						<table class="table table-sm mb-0">
							<caption class="visually-hidden">Komposisi penggunaan lahan dalam hektare</caption>
							<thead><tr><th scope="col">Jenis</th><th scope="col" class="num">Luas (ha)</th></tr></thead>
							<tbody>
							<?php foreach ($geo['land_use'] as $row): ?>
								<tr><th scope="row"><?= e($row['label']) ?></th><td class="num"><?= e($row['value']) ?></td></tr>
							<?php endforeach; ?>
							</tbody>
							<tfoot><tr><th scope="row">Jumlah komposisi</th><td class="num"><?= format_number_id($land_sum, 2) ?></td></tr></tfoot>
						</table>
					</div>
					<?php endif; ?>

					<?php if ( ! empty($geo['area_conflict_note'])): ?>
					<div class="alert alert-warning mt-3" role="note">
						<strong>Luas wilayah belum punya angka resmi.</strong>
						<p class="mb-0"><?= e($geo['area_conflict_note']) ?></p>
					</div>
					<?php endif; ?>
				</div>
			</div>
			<p class="small text-muted mt-4">Revisi <?= (int) ($snapshot['revision_no'] ?? 0) ?>, diterbitkan <?= e(format_wib($snapshot['published_at'] ?? NULL, 'short')) ?>.</p>
		<?php endif; ?>
	</div>
</section>
