<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Titik terverifikasi bila ada; selain itu peta perkiraan berlabel jelas, atau alamat teks bila tile tidak tersedia. */
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
				<?php $this->load->view('partials/approx_map', array('center' => $d['center'] ?? NULL, 'min_height' => 380)); ?>
			<?php endif; ?>
		</div>
	</div>
</section>
