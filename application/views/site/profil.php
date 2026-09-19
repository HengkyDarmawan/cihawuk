<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Profil desa dari snapshot terbit; tidak ada angka atau teks yang ditulis di view. */
$identity = $blocks['identity']['body'] ?? array();
$greeting = $blocks['greeting']['body'] ?? array();
$contact = $blocks['contact']['body'] ?? array();
$source_line = function ($block) {
	if ( ! $block) { return ''; }
	$parts = array();
	if ( ! empty($block['source_code'])) { $parts[] = $block['source_code'].' — '.$block['source_title']; }
	elseif ( ! empty($block['source_note'])) { $parts[] = $block['source_note']; }
	if ( ! empty($block['period_start'])) { $parts[] = 'periode '.$block['period_start'].(empty($block['period_end']) ? '' : '–'.$block['period_end']); }
	return implode(' · ', $parts);
};
?>
<section class="page-hero">
	<div class="container-site">
		<nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-2"><li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li><li class="breadcrumb-item active" aria-current="page">Profil Desa</li></ol></nav>
		<h1>Profil Desa Cihawuk</h1>
		<p>Identitas, sambutan, dan kontak desa berdasarkan dokumen profil desa beserta tahun sumbernya.</p>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<?php if (empty($blocks)): ?>
			<div class="empty-state">
				<?= icon('file-text') ?>
				<h2 class="h5">Profil desa sedang disiapkan</h2>
				<p class="mb-0">Pengelola desa sedang memverifikasi isi profil sebelum diterbitkan. Layanan pengaduan dan permintaan informasi tetap dapat digunakan.</p>
			</div>
		<?php else: ?>
			<div class="row g-5">
				<div class="col-lg-7">
					<div class="prose">
						<?php if ($identity): ?>
						<h2>Identitas</h2>
						<?php if ( ! empty($identity['summary'])): ?><p><?= e($identity['summary']) ?></p><?php endif; ?>
						<table class="table table-sm">
							<caption class="visually-hidden">Identitas administratif Desa Cihawuk</caption>
							<tbody>
								<tr><th scope="row">Desa</th><td><?= e($identity['village_name'] ?? '—') ?></td></tr>
								<tr><th scope="row">Kecamatan</th><td><?= e($identity['district'] ?? '—') ?></td></tr>
								<tr><th scope="row">Kabupaten</th><td><?= e($identity['regency'] ?? '—') ?></td></tr>
								<tr><th scope="row">Provinsi</th><td><?= e($identity['province'] ?? '—') ?></td></tr>
								<?php if ( ! empty($identity['pum_code'])): ?>
								<tr><th scope="row">Kode PUM</th><td><code><?= e($identity['pum_code']) ?></code></td></tr>
								<?php endif; ?>
								<?php if ( ! empty($identity['coordinate_latitude_raw'])): ?>
								<tr><th scope="row">Koordinat (nilai mentah)</th>
									<td><?= e($identity['coordinate_latitude_raw']) ?>, <?= e($identity['coordinate_longitude_raw'] ?? '—') ?>
									<br><span class="small text-muted">Ditulis apa adanya dari dokumen sumber; tanda lintang belum dipastikan.</span></td></tr>
								<?php endif; ?>
							</tbody>
						</table>
						<p class="small text-muted"><?= e($source_line($blocks['identity'] ?? NULL)) ?></p>
						<?php endif; ?>

						<?php if ($greeting): ?>
						<h2 id="sambutan">Sambutan</h2>
						<p class="small text-muted">
							<?= e($greeting['author_name'] ?? '') ?><?= empty($greeting['author_position']) ? '' : ', '.e($greeting['author_position']) ?>
							<?php if ( ! empty($blocks['greeting']['period_start'])): ?>
								— jabatan tersebut berlaku pada <?= (int) $blocks['greeting']['period_start'] ?>, bukan otomatis saat ini.
							<?php endif; ?>
						</p>
						<?php foreach (preg_split('/\r?\n\r?\n/', (string) ($greeting['edited_text'] ?: $greeting['original_text'] ?? '')) as $paragraph): ?>
							<?php if (trim($paragraph) !== ''): ?><p><?= e(trim($paragraph)) ?></p><?php endif; ?>
						<?php endforeach; ?>
						<p class="small text-muted"><?= e($source_line($blocks['greeting'] ?? NULL)) ?></p>
						<?php endif; ?>
					</div>
				</div>

				<div class="col-lg-5">
					<div class="card shadow-sm mb-4">
						<div class="card-body">
							<h2 class="h6">Halaman profil lainnya</h2>
							<ul class="list-unstyled mb-0">
								<li><a href="<?= site_url('profil/sejarah') ?>">Sejarah dan linimasa kepemimpinan</a></li>
								<li><a href="<?= site_url('profil/visi-misi') ?>">Visi dan misi</a></li>
								<li><a href="<?= site_url('profil/geografi') ?>">Geografi dan penggunaan lahan</a></li>
							</ul>
						</div>
					</div>

					<?php if ($contact): ?>
					<div class="card shadow-sm mb-4">
						<div class="card-body">
							<h2 class="h6">Kontak dan pelayanan</h2>
							<?php if ( ! empty($contact['office_address'])): ?><p class="mb-1"><?= e($contact['office_address']) ?></p><?php endif; ?>
							<?php if ( ! empty($contact['phone_public'])): ?><p class="mb-1">Telepon: <?= e($contact['phone_public']) ?></p><?php endif; ?>
							<?php if ( ! empty($contact['email_public'])): ?><p class="mb-1">Surel: <?= e($contact['email_public']) ?></p><?php endif; ?>
							<?php foreach ((array) ($contact['service_hours'] ?? array()) as $row): ?>
								<p class="mb-1 small"><?= e($row['label']) ?>: <?= e($row['value']) ?></p>
							<?php endforeach; ?>
						</div>
					</div>
					<?php endif; ?>

					<?php if ($areas): ?>
					<div class="card shadow-sm">
						<div class="card-body">
							<h2 class="h6">Wilayah terverifikasi</h2>
							<ul class="mb-0"><?php foreach ($areas as $area): ?><li><?= e($area->name) ?></li><?php endforeach; ?></ul>
						</div>
					</div>
					<?php endif; ?>
				</div>
			</div>

			<p class="small text-muted mt-4">
				Profil ini adalah revisi <?= (int) ($snapshot['revision_no'] ?? 0) ?>, diterbitkan
				<?= e(format_wib($snapshot['published_at'] ?? NULL, 'short')) ?>.
			</p>
		<?php endif; ?>
	</div>
</section>
