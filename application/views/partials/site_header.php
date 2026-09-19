<?php defined('BASEPATH') OR exit('No direct script access allowed');
$current = '/'.uri_string();
$is_active = function ($url) use ($current) {
	$path = parse_url((string) $url, PHP_URL_PATH);
	return $path !== NULL && $path !== '/' && ($current === $path OR strpos($current, rtrim($path, '/').'/') === 0);
};
// Item menu berbentuk array {label, href, children} — dari snapshot menu CMS atau navigasi lama.
$site_name = $site['identity']['site_name'] ?? 'Desa Cihawuk';
$site_region = trim(($site['identity']['district'] ?? '').' · '.($site['identity']['regency'] ?? ''), ' ·');
?>
<header class="site-header<?= $transparent_header ? ' is-transparent' : '' ?>" data-site-header>
	<div class="container-site header-inner">
		<a class="brand" href="<?= site_url('/') ?>" aria-label="Beranda <?= e($site_name) ?>">
			<span class="brand-mark" aria-hidden="true">
				<svg viewBox="0 0 40 40" width="40" height="40" focusable="false"><rect width="40" height="40" rx="12" fill="currentColor" opacity=".14"/><path d="M6 29 15 17l6 7 4-5 9 10H6Z" fill="currentColor"/><circle cx="28" cy="12" r="3.2" fill="currentColor"/></svg>
			</span>
			<span class="brand-text">
				<span class="brand-name"><?= e($site_name) ?></span>
				<span class="brand-sub"><?= e($site_region) ?></span>
			</span>
		</a>

		<nav class="main-nav d-none d-xl-block" aria-label="Menu utama">
			<ul class="nav-list">
				<?php foreach ($nav_items as $item): ?>
					<?php if ( ! empty($item['children'])): ?>
					<li class="nav-item dropdown">
						<button class="nav-link dropdown-toggle<?= $is_active($item['href']) ? ' active' : '' ?>" type="button" data-bs-toggle="dropdown" aria-expanded="false"><?= e($item['label']) ?></button>
						<ul class="dropdown-menu">
							<?php foreach ($item['children'] as $child): ?>
							<li><a class="dropdown-item" href="<?= nav_href($child['href']) ?>"<?= $current === parse_url((string) $child['href'], PHP_URL_PATH) ? ' aria-current="page"' : '' ?>><?= e($child['label']) ?></a></li>
							<?php endforeach; ?>
						</ul>
					</li>
					<?php else: ?>
					<li class="nav-item"><a class="nav-link<?= $is_active($item['href']) ? ' active' : '' ?>" href="<?= nav_href($item['href']) ?>"<?= $is_active($item['href']) ? ' aria-current="page"' : '' ?>><?= e($item['label']) ?></a></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		</nav>

		<div class="header-actions">
			<a class="btn-icon d-none d-md-inline-flex" href="<?= site_url('cari') ?>" aria-label="Cari konten">
				<?= icon('search') ?>
			</a>
			<a class="btn btn-accent btn-sm d-none d-sm-inline-flex" href="<?= site_url('lapor') ?>"><?= icon('edit-3') ?><span>Buat Laporan</span></a>
			<a class="btn btn-outline-header btn-sm d-none d-md-inline-flex" href="<?= site_url('masuk') ?>"><?= icon('log-in') ?><span>Masuk</span></a>
			<button class="btn-icon d-xl-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#menuMobile" aria-controls="menuMobile" aria-label="Buka menu">
				<?= icon('menu') ?>
			</button>
		</div>
	</div>
</header>

<div class="offcanvas offcanvas-end mobile-drawer" tabindex="-1" id="menuMobile" aria-labelledby="menuMobileLabel">
	<div class="offcanvas-header">
		<h2 class="offcanvas-title h5" id="menuMobileLabel">Menu</h2>
		<button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup menu"></button>
	</div>
	<div class="offcanvas-body">
		<div class="drawer-quick">
			<a class="btn btn-accent w-100" href="<?= site_url('lapor') ?>"><?= icon('edit-3') ?> Buat Laporan</a>
			<a class="btn btn-outline-primary w-100" href="<?= site_url('lacak') ?>"><?= icon('search') ?> Lacak Laporan</a>
		</div>
		<nav aria-label="Menu utama (seluler)">
			<ul class="drawer-nav">
				<?php foreach ($nav_items as $i => $item): ?>
				<li>
					<?php if ( ! empty($item['children'])): ?>
					<button class="drawer-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#drawer-sub-<?= (int) $i ?>" aria-expanded="false" aria-controls="drawer-sub-<?= (int) $i ?>">
						<span><?= e($item['label']) ?></span><?= icon('chevron-down') ?>
					</button>
					<ul class="collapse drawer-sub" id="drawer-sub-<?= (int) $i ?>">
						<?php foreach ($item['children'] as $child): ?>
						<li><a href="<?= nav_href($child['href']) ?>"><?= e($child['label']) ?></a></li>
						<?php endforeach; ?>
					</ul>
					<?php else: ?>
					<a class="drawer-link" href="<?= nav_href($item['href']) ?>"><?= e($item['label']) ?></a>
					<?php endif; ?>
				</li>
				<?php endforeach; ?>
				<li><a class="drawer-link" href="<?= site_url('cari') ?>">Cari</a></li>
			</ul>
		</nav>
		<div class="drawer-footer">
			<a class="btn btn-primary w-100" href="<?= site_url('masuk') ?>"><?= icon('log-in') ?> Masuk</a>
			<a class="btn btn-link w-100" href="<?= site_url('daftar') ?>">Daftar akun warga</a>
		</div>
	</div>
</div>
