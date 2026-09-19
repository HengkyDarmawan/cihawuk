<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="page-hero">
	<div class="container-site">
		<nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-2">
			<li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li>
			<li class="breadcrumb-item"><a href="<?= site_url('berita') ?>">Berita</a></li>
			<?php if ($post->category_slug): ?><li class="breadcrumb-item"><a href="<?= site_url('berita/kategori/'.rawurlencode($post->category_slug)) ?>"><?= e($post->category_name) ?></a></li><?php endif; ?>
		</ol></nav>
		<h1><?= e($post->title) ?></h1>
		<p><?= icon('calendar') ?> <?= e(format_wib($post->published_at)) ?><?= $post->author_name ? ' · '.e($post->author_name) : '' ?></p>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<?= preview_badge($post->publication_status) ?>
		<div class="row g-5">
			<article class="col-lg-8">
				<?php if ($post->cover): ?>
					<figure class="mb-4">
						<div class="media-frame"><?= media_img($post->cover, 'Foto berita', TRUE, '(min-width: 992px) 66vw, 100vw') ?></div>
						<?php if ($post->cover->source_credit): ?><figcaption class="small text-muted mt-2">Foto: <?= e($post->cover->source_credit) ?></figcaption><?php endif; ?>
					</figure>
				<?php endif; ?>
				<?php if ($post->excerpt): ?><p class="section-lead mb-4"><?= e($post->excerpt) ?></p><?php endif; ?>
				<div class="prose"><?= $post->body_html ?></div>
			</article>

			<aside class="col-lg-4">
				<?php if ( ! empty($related)): ?>
				<div class="card-soft card-pad">
					<h2 class="h6">Berita terkait</h2>
					<ul class="list-unstyled mb-0">
						<?php foreach ($related as $item): ?>
						<li class="border-bottom py-2">
							<a href="<?= site_url('berita/'.rawurlencode($item->slug)) ?>"><?= e($item->title) ?></a>
							<div class="small text-muted"><?= e(format_wib($item->published_at, 'date')) ?></div>
						</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php endif; ?>
			</aside>
		</div>
	</div>
</section>
