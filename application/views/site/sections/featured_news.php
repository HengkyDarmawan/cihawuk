<?php defined('BASEPATH') OR exit('No direct script access allowed');
$items = $d['items'] ?? array();
$links = $s['config']['cta'] ?? array();
?>
<section class="section section-white" aria-labelledby="berita-title">
	<div class="container-wide">
		<div class="section-head">
			<div>
				<p class="eyebrow"><?= e($s['subtitle'] ?: 'Cerita desa') ?></p>
				<h2 class="section-title" id="berita-title"><?= e($s['title'] ?: 'Berita dan pengumuman') ?></h2>
			</div>
			<a class="link-arrow" href="<?= $links ? nav_href($links[0]['url']) : site_url('berita') ?>"><?= e($links ? $links[0]['label'] : 'Semua berita') ?> <?= icon('arrow-right') ?></a>
		</div>
		<?php if ($items): $list = $items; $feature = array_shift($list); ?>
		<div class="news-layout">
			<a class="news-feature reveal" href="<?= site_url('berita/'.rawurlencode($feature->slug)) ?>">
				<div class="media-frame"><?= media_img($feature->cover, 'Foto berita belum tersedia', FALSE, '(min-width: 992px) 55vw, 100vw') ?></div>
				<div class="meta mb-2">
					<span><?= e($feature->category_name ?: ($feature->type === 'announcement' ? 'Pengumuman' : 'Berita')) ?></span>
					<span><?= icon('calendar') ?> <?= format_wib($feature->published_at, 'date') ?></span>
					<?= preview_badge($feature->publication_status) ?>
				</div>
				<h3><?= e($feature->title) ?></h3>
				<p class="text-muted mb-0"><?= e(str_limit_id($feature->excerpt, 220)) ?></p>
			</a>
			<div class="news-list">
				<?php foreach ($list as $post): ?>
				<a class="news-item" href="<?= site_url('berita/'.rawurlencode($post->slug)) ?>">
					<div class="media-frame"><?= media_img($post->cover, 'Foto', FALSE, '112px') ?></div>
					<div>
						<h3><?= e($post->title) ?></h3>
						<div class="meta"><span><?= format_wib($post->published_at, 'date') ?></span><?= preview_badge($post->publication_status) ?></div>
					</div>
				</a>
				<?php endforeach; ?>
			</div>
		</div>
		<?php else: ?>
		<div class="empty-state">
			<?= icon('file-text') ?>
			<h3>Belum ada berita terbit</h3>
			<p class="mb-0">Kabar kegiatan desa akan ditampilkan di sini setelah diterbitkan pengelola.</p>
		</div>
		<?php endif; ?>
	</div>
</section>
