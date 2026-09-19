<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Akses cepat: HTML biasa agar tetap berfungsi tanpa JavaScript. */
$links = $s['config']['links'] ?? array();
$icons = array('lapor' => 'edit-3', 'lacak' => 'search', 'data-desa' => 'bar-chart-2', 'layanan' => 'info',
	'potensi' => 'compass', 'berita' => 'file-text', 'agenda' => 'calendar', 'dokumen' => 'folder');
?>
<?php if ($links): ?>
<section class="quick-access" aria-label="<?= e($s['title'] ?: 'Akses cepat layanan') ?>">
	<div class="container-wide">
		<div class="quick-grid">
			<?php foreach ($links as $i => $link):
				$key = trim(parse_url($link['url'], PHP_URL_PATH) ?: '', '/');
				$icon = $icons[$key] ?? 'arrow-right'; ?>
			<a class="quick-card<?= $i === 0 ? ' is-primary' : '' ?>" href="<?= nav_href($link['url']) ?>">
				<span class="quick-icon"><?= icon($icon) ?></span>
				<span class="quick-title"><?= e($link['label']) ?></span>
				<?php if ( ! empty($link['description'])): ?><span class="quick-desc"><?= e($link['description']) ?></span><?php endif; ?>
			</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>
