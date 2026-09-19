<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Peta hanya dirender bila ada titik terverifikasi; selain itu tampil alamat teks. */
$features = $d['features'] ?? array();
$cfg = $s['config'];
?>
<section class="section" aria-labelledby="peta-title">
	<div class="container-wide">
		<p class="eyebrow"><?= e($s['subtitle'] ?: 'Lokasi layanan') ?></p>
		<h2 class="section-title" id="peta-title"><?= e($s['title'] ?: 'Kantor Desa Cihawuk') ?></h2>
		<?php if ( ! empty($cfg['body'])): ?><p class="section-lead"><?= e($cfg['body']) ?></p><?php endif; ?>
		<div class="map-box">
			<?php if ($features): ?>
				<div data-map
					data-features="<?= e(json_encode(array_map(function ($f) { return array('title' => $f->title, 'geometry' => json_decode($f->geometry_json, TRUE)); }, $features))) ?>"
					data-tile="<?= e(app_env('MAP_TILE_URL')) ?>" data-attribution="<?= e(app_env('MAP_ATTRIBUTION')) ?>"
					style="height:100%;min-height:380px"></div>
			<?php else: ?>
			<div class="map-fallback">
				<div>
					<?= icon('map') ?>
					<h3 class="h5">Peta sedang dilengkapi</h3>
					<p class="mb-1">Desa Cihawuk, Kecamatan Kertasari, Kabupaten Bandung, Jawa Barat.</p>
					<p class="small mb-0">Titik lokasi akan ditampilkan setelah koordinatnya dikonfirmasi.</p>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</div>
</section>
