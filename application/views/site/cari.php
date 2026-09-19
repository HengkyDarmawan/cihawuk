<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="page-hero">
	<div class="container-site">
		<h1>Pencarian</h1>
		<p>Mencari berita, pengumuman, potensi, agenda, dan dokumen publik yang sudah terbit.</p>
		<form class="search-hero-form" method="get" action="<?= site_url('cari') ?>" role="search">
			<label class="visually-hidden" for="q">Kata kunci pencarian</label>
			<input class="form-control" type="search" id="q" name="q" value="<?= e($keyword) ?>" maxlength="80" placeholder="Misalnya: kerja bakti, pertanian, APBDes" aria-describedby="cari-help">
			<button class="btn btn-accent" type="submit">Cari</button>
		</form>
		<p class="small mt-2" id="cari-help" style="color:rgba(255,255,255,.8)">Pencarian tidak mencakup laporan warga, data akun, atau dokumen internal.</p>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<?php if ($limited): ?>
			<div class="alert alert-warning" role="alert">Terlalu banyak pencarian dari jaringan ini. Coba lagi beberapa saat lagi.</div>
		<?php elseif ($keyword === ''): ?>
			<p class="text-muted">Masukkan kata kunci untuk mulai mencari.</p>
		<?php elseif (mb_strlen($keyword) < 3): ?>
			<p class="text-muted">Kata kunci minimal 3 karakter.</p>
		<?php elseif (empty($results)): ?>
			<div class="empty-state">
				<?= icon('search') ?>
				<h2 class="h5">Tidak ada hasil untuk “<?= e($keyword) ?>”</h2>
				<p class="mb-0">Coba kata kunci lain, atau buka <a href="<?= site_url('berita') ?>">daftar berita</a> dan <a href="<?= site_url('potensi') ?>">potensi desa</a>.</p>
			</div>
		<?php else: ?>
			<p class="text-muted mb-3"><?= count($results) ?> hasil untuk “<?= e($keyword) ?>”.</p>
			<ul class="result-list">
				<?php foreach ($results as $item): ?>
				<li class="result-item">
					<p class="result-type mb-1"><?= e($item['type']) ?></p>
					<h2 class="h5 mb-1"><a href="<?= site_url(ltrim($item['url'], '/')) ?>"><?= e($item['title']) ?></a></h2>
					<?php if ( ! empty($item['summary'])): ?><p class="text-muted mb-1"><?= e(str_limit_id($item['summary'], 160)) ?></p><?php endif; ?>
					<?php if ( ! empty($item['date'])): ?><p class="small text-muted mb-0"><?= e(format_wib($item['date'], 'date')) ?></p><?php endif; ?>
				</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</section>
