<?php defined('BASEPATH') OR exit('No direct script access allowed');
$page_url = function ($n) use ($active_category) {
	$base = $active_category ? site_url('berita/kategori/'.rawurlencode($active_category)) : site_url('berita');
	return $base.($n > 1 ? '?hal='.(int) $n : '');
};
?>
<section class="page-hero">
	<div class="container-site">
		<nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-2"><li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li><li class="breadcrumb-item active" aria-current="page">Berita</li></ol></nav>
		<h1>Berita dan pengumuman</h1>
		<p>Kabar kegiatan dan informasi resmi dari Pemerintah Desa Cihawuk.</p>
	</div>
</section>

<section class="page-body">
	<div class="container-wide">
		<nav class="filter-chips" aria-label="Kategori berita">
			<a class="chip" href="<?= site_url('berita') ?>" <?= $active_category === NULL ? 'aria-current="true"' : '' ?>>Semua</a>
			<?php foreach ($categories as $cat): ?>
				<a class="chip" href="<?= site_url('berita/kategori/'.rawurlencode($cat->slug)) ?>" <?= $active_category === $cat->slug ? 'aria-current="true"' : '' ?>><?= e($cat->name) ?></a>
			<?php endforeach; ?>
		</nav>

		<?php if (empty($posts)): ?>
			<div class="empty-state">
				<?= icon('file-text') ?>
				<h2 class="h5">Belum ada berita terbit</h2>
				<p class="mb-0">Informasi kegiatan desa akan tampil di sini setelah diterbitkan pengelola.</p>
			</div>
		<?php else: ?>
			<div class="news-grid">
				<?php foreach ($posts as $post): ?>
				<a class="news-card" href="<?= site_url('berita/'.rawurlencode($post->slug)) ?>">
					<div class="media-frame"><?= media_img($post->cover, 'Foto berita belum tersedia', FALSE, '(min-width: 1200px) 33vw, (min-width: 768px) 50vw, 100vw') ?></div>
					<div class="news-card-body">
						<div class="meta">
							<span><?= e($post->category_name ?: ($post->type === 'announcement' ? 'Pengumuman' : 'Berita')) ?></span>
							<span><?= icon('calendar') ?> <?= e(format_wib($post->published_at, 'date')) ?></span>
						</div>
						<h2 class="h5 mb-0"><?= e($post->title) ?></h2>
						<p class="text-muted mb-0"><?= e(str_limit_id($post->excerpt, 140)) ?></p>
						<?= preview_badge($post->publication_status) ?>
					</div>
				</a>
				<?php endforeach; ?>
			</div>

			<?php if ($pages > 1): ?>
			<nav class="mt-5" aria-label="Halaman berita">
				<ul class="pagination justify-content-center">
					<li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $page_url($page - 1) ?>">Sebelumnya</a></li>
					<?php for ($i = 1; $i <= $pages; $i++): ?>
						<li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= $page_url($i) ?>" <?= $i === $page ? 'aria-current="page"' : '' ?>><?= $i ?></a></li>
					<?php endfor; ?>
					<li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $page_url($page + 1) ?>">Berikutnya</a></li>
				</ul>
			</nav>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</section>
