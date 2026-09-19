<?php defined('BASEPATH') OR exit('No direct script access allowed');
$contact = $site_settings['site.contact'] ?? array();
$hours = $site_settings['site.service_hours'] ?? NULL;
$identity = $site['identity'] ?? array();
$site_name = $identity['site_name'] ?? 'Desa Cihawuk';
$region = trim('Kecamatan '.($identity['district'] ?? '').', '.($identity['regency'] ?? '').', '.($identity['province'] ?? ''), ' ,');
$socials = array_filter((array) ($identity['social'] ?? array()));
?>
<footer class="site-footer">
	<div class="container-site">
		<div class="footer-grid">
			<div class="footer-brand">
				<p class="footer-title"><?= e($site_name) ?></p>
				<p class="footer-muted"><?= e($region) ?>.</p>
				<?php $address = $identity['office_address'] ?: ($village->office_address ?? ''); ?>
				<?php if ($address !== ''): ?>
					<p class="footer-muted"><?= icon('map-pin') ?> <?= e($address) ?></p>
				<?php else: ?>
					<p class="footer-muted"><?= icon('map-pin') ?> Alamat kantor desa sedang dilengkapi.</p>
				<?php endif; ?>
				<?php if ($socials): ?>
				<ul class="footer-social">
					<?php foreach ($socials as $network => $url): ?>
					<li><a href="<?= nav_href($url) ?>" rel="noopener noreferrer" target="_blank"><?= e(ucfirst($network)) ?></a></li>
					<?php endforeach; ?>
				</ul>
				<?php endif; ?>
			</div>
			<div>
				<p class="footer-heading">Jam pelayanan</p>
				<?php if ( ! empty($identity['service_hours'])): ?>
					<p class="footer-muted"><?= e($identity['service_hours']) ?></p>
				<?php elseif (is_array($hours) && ! empty($hours['label'])): ?>
					<p class="footer-muted"><?= e($hours['label']) ?></p>
					<?php if ( ! empty($hours['is_example'])): ?><p class="footer-note">Jadwal contoh — menunggu konfirmasi kantor desa.</p><?php endif; ?>
				<?php else: ?>
					<p class="footer-muted">Belum diumumkan.</p>
				<?php endif; ?>
				<?php $phone = $identity['contact_phone'] ?: ( ! empty($contact['confirmed']) ? ($contact['phone'] ?? '') : ''); ?>
				<?php if ($phone !== ''): ?>
					<p class="footer-muted"><?= icon('phone') ?> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= e($phone) ?></a></p>
				<?php endif; ?>
				<?php if ( ! empty($identity['contact_email'])): ?>
					<p class="footer-muted"><?= icon('mail') ?> <a href="mailto:<?= e($identity['contact_email']) ?>"><?= e($identity['contact_email']) ?></a></p>
				<?php endif; ?>
			</div>
			<div>
				<p class="footer-heading">Tautan penting</p>
				<ul class="footer-links">
					<?php foreach ($footer_items as $item): ?>
					<li><a href="<?= nav_href($item['href']) ?>"><?= e($item['label']) ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<div class="footer-cta">
				<p class="footer-heading">Punya keluhan atau usulan?</p>
				<p class="footer-muted">Sampaikan tanpa akun, lalu pantau perkembangannya dengan kode akses.</p>
				<a class="btn btn-accent" href="<?= site_url('lapor') ?>">Buat Laporan</a>
			</div>
		</div>
		<div class="footer-bottom">
			<p>© <?= e(date('Y')) ?> Pemerintah <?= e($site_name) ?>. <?= e($identity['footer_text'] ?? '') ?></p>
			<p><a href="<?= site_url('privasi') ?>">Privasi</a> · <a href="<?= site_url('ketentuan') ?>">Ketentuan</a></p>
		</div>
	</div>
</footer>
