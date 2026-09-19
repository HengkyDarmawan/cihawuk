<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Visi dan misi: teks aksesibel, bukan poster gambar (modul-frontend 5.4). */
$vision = $blocks['vision']['body'] ?? array();
$mission = $blocks['mission']['body'] ?? array();
?>
<section class="page-hero">
	<div class="container-site">
		<nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-2"><li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li><li class="breadcrumb-item"><a href="<?= site_url('profil') ?>">Profil Desa</a></li><li class="breadcrumb-item active" aria-current="page">Visi dan Misi</li></ol></nav>
		<h1>Visi dan Misi</h1>
		<p>Visi desa dan visi kecamatan disimpan terpisah; keduanya dokumen yang berbeda.</p>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<?php if (empty($vision) && empty($mission)): ?>
			<div class="empty-state">
				<?= icon('compass') ?>
				<h2 class="h5">Visi dan misi belum diterbitkan</h2>
				<p class="mb-0">Naskah visi dan misi beserta periode berlakunya sedang diperiksa pengelola desa.</p>
			</div>
		<?php else: ?>
			<div class="prose">
				<?php if ( ! empty($vision['village_vision'])): ?>
					<h2>Visi Desa Cihawuk</h2>
					<blockquote class="blockquote"><p><?= e($vision['village_vision']) ?></p></blockquote>
					<?php if ( ! empty($blocks['vision']['period_start'])): ?>
						<p class="small text-muted">Berlaku <?= (int) $blocks['vision']['period_start'] ?><?= empty($blocks['vision']['period_end']) ? '' : '–'.(int) $blocks['vision']['period_end'] ?>.</p>
					<?php endif; ?>
					<?php if ( ! empty($vision['basis_document'])): ?>
						<p class="small text-muted">Dokumen dasar: <?= e($vision['basis_document']) ?>.</p>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( ! empty($mission['items'])): ?>
					<h2>Misi</h2>
					<ol>
						<?php foreach ($mission['items'] as $item): ?><li><?= e($item) ?></li><?php endforeach; ?>
					</ol>
					<?php if ( ! empty($mission['basis_document'])): ?>
						<p class="small text-muted">Dokumen dasar: <?= e($mission['basis_document']) ?>.</p>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( ! empty($vision['district_vision'])): ?>
					<h2>Visi Kecamatan Kertasari</h2>
					<p class="small text-muted">Dicantumkan sebagai konteks wilayah, bukan visi Desa Cihawuk.</p>
					<blockquote class="blockquote"><p><?= e($vision['district_vision']) ?></p></blockquote>
				<?php endif; ?>
			</div>
			<p class="small text-muted mt-4">Revisi <?= (int) ($snapshot['revision_no'] ?? 0) ?>, diterbitkan <?= e(format_wib($snapshot['published_at'] ?? NULL, 'short')) ?>.</p>
		<?php endif; ?>
	</div>
</section>
