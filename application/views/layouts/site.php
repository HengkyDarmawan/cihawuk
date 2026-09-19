<?php defined('BASEPATH') OR exit('No direct script access allowed');
$site = isset($site) && is_array($site) ? $site : array('identity' => array(), 'theme' => array());
$identity = $site['identity'] ?? array();
$app_name = $identity['site_name'] ?? 'Desa Cihawuk';
$site_region = trim('Kecamatan '.($identity['district'] ?? '').', '.($identity['regency'] ?? ''), ' ,');
$title = empty($page_title) ? $app_name.' — '.$site_region : $page_title.' — '.$app_name;
$theme_css = isset($theme_css) && is_array($theme_css) ? $theme_css : array();
$favicon = $identity['favicon_url'] ?? NULL;
$social_image = $identity['social_image_url'] ?? NULL;
$flash = isset($this->session) ? $this->session->flashdata('flash') : NULL;
$extra_css = isset($extra_css) ? (array) $extra_css : array();
$extra_js = isset($extra_js) ? (array) $extra_js : array();
?><!doctype html>
<html lang="id" class="no-js">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= e($title) ?></title>
	<meta name="description" content="<?= e( ! empty($meta_description) ? $meta_description : 'Profil dan layanan digital Desa Cihawuk, Kecamatan Kertasari, Kabupaten Bandung, Jawa Barat.') ?>">
	<?php if ( ! empty($noindex) OR ! empty($preview_mode) OR ! empty($demo_mode)): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
	<meta name="theme-color" content="<?= e($site['theme']['color_primary'] ?? '#174B3A') ?>">
	<meta property="og:site_name" content="<?= e($app_name) ?>">
	<meta property="og:title" content="<?= e($title) ?>">
	<?php if ($social_image): ?><meta property="og:image" content="<?= e($social_image) ?>"><?php endif; ?>
	<link rel="preload" href="<?= base_url('assets/vendor/fonts/manrope/manrope-latin-400.woff2') ?>" as="font" type="font/woff2" crossorigin>
	<link rel="stylesheet" href="<?= asset_url('vendor/bootstrap5/css/bootstrap.min.css') ?>">
	<?php foreach ($extra_css as $css): ?><link rel="stylesheet" href="<?= asset_url($css) ?>"><?php endforeach; ?>
	<link rel="stylesheet" href="<?= asset_url('site/css/site.css') ?>">
	<?php if ($favicon): ?>
	<link rel="icon" href="<?= e($favicon) ?>">
	<?php else: ?>
	<link rel="icon" href="<?= base_url('assets/site/img/favicon.svg') ?>" type="image/svg+xml">
	<?php endif; ?>
	<?php if ($theme_css): ?>
	<?php
	// Token tema dari snapshot situs. Nilainya sudah divalidasi service (hex, font allowlist,
	// preset radius); di sini disaring sekali lagi supaya tidak mungkin keluar dari blok <style>.
	$theme_rules = '';
	foreach ($theme_css as $token => $value)
	{
		$token = preg_replace('/[^a-zA-Z0-9\-]/', '', (string) $token);
		$value = preg_replace('/[^a-zA-Z0-9 #,.%\-_"\'()]/', '', (string) $value);
		if ($token !== '' && $value !== '')
		{
			$theme_rules .= $token.':'.$value.';';
		}
	}
	?>
	<?php if ($theme_rules !== ''): ?><style>:root{<?= $theme_rules ?>}</style><?php endif; ?>
	<?php endif; ?>
</head>
<body class="<?= e($body_class ?? '') ?><?= ! empty($transparent_header) ? ' has-hero' : '' ?>">
	<a class="skip-link" href="#konten">Lewati ke konten utama</a>

	<?php if ( ! empty($demo_mode)): ?>
	<div class="demo-bar" role="status">
		<?= icon('alert-triangle') ?> <strong>Mode demonstrasi</strong> — sebagian angka, nama usaha, dan foto pada halaman ini adalah <strong>contoh</strong>, bukan data resmi Desa Cihawuk. Jangan dikutip sebagai rujukan.
	</div>
	<?php endif; ?>

	<?php if ( ! empty($preview_mode)): ?>
	<div class="preview-bar" role="status">
		<?= icon('eye') ?> <strong>Mode pratinjau</strong> — konten draft ikut ditampilkan dan diberi tanda. Hanya terlihat oleh editor yang login.
	</div>
	<?php endif; ?>

	<?php $this->load->view('partials/site_header', array('nav_items' => $nav_items ?? array(), 'transparent_header' => ! empty($transparent_header))); ?>

	<main id="konten" tabindex="-1">
		<?php if ( ! empty($flash) && is_array($flash)): ?>
		<div class="container-site pt-4">
			<div class="alert alert-<?= e($flash['type'] === 'error' ? 'danger' : $flash['type']) ?> d-flex gap-2 align-items-start" role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>">
				<?= icon($flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'alert-octagon' : 'info')) ?>
				<div><?= e($flash['message']) ?></div>
			</div>
		</div>
		<?php endif; ?>
		<?= $content ?>
	</main>

	<?php $this->load->view('partials/site_footer', array('footer_items' => $footer_items ?? array(), 'village' => $village ?? NULL, 'site_settings' => $site_settings ?? array())); ?>

	<script src="<?= asset_url('vendor/bootstrap5/js/bootstrap.bundle.min.js') ?>" defer></script>
	<?php foreach ($extra_js as $js): ?><script src="<?= asset_url($js) ?>" defer></script><?php endforeach; ?>
	<script src="<?= asset_url('site/js/site.js') ?>" defer></script>
</body>
</html>
