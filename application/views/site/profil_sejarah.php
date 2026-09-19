<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Sejarah dan linimasa kepemimpinan dari snapshot profil terbit. */
$history = $blocks['history']['body'] ?? array();
?>
<section class="page-hero">
	<div class="container-site">
		<nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-2"><li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li><li class="breadcrumb-item"><a href="<?= site_url('profil') ?>">Profil Desa</a></li><li class="breadcrumb-item active" aria-current="page">Sejarah</li></ol></nav>
		<h1>Sejarah Desa Cihawuk</h1>
		<p>Narasi sejarah dan daftar kepala desa menurut dokumen profil desa, beserta catatan yang masih perlu verifikasi.</p>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<?php if (empty($history) && empty($terms)): ?>
			<div class="empty-state">
				<?= icon('book-open') ?>
				<h2 class="h5">Sejarah desa sedang disiapkan</h2>
				<p class="mb-0">Naskah sejarah sedang diverifikasi terhadap dokumen sumber sebelum diterbitkan.</p>
			</div>
		<?php else: ?>
			<div class="row g-5">
				<div class="col-lg-7">
					<div class="prose">
						<?php if ( ! empty($history['narrative'])): ?>
							<?php foreach (preg_split('/\r?\n\r?\n/', (string) $history['narrative']) as $paragraph): ?>
								<?php if (trim($paragraph) !== ''): ?><p><?= e(trim($paragraph)) ?></p><?php endif; ?>
							<?php endforeach; ?>
						<?php endif; ?>
						<?php if ( ! empty($history['founded_claim_note'])): ?>
							<div class="alert alert-light border" role="note">
								<strong>Catatan sumber.</strong> <?= e($history['founded_claim_note']) ?>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<div class="col-lg-5">
					<h2 class="h5">Linimasa kepemimpinan</h2>
					<?php if (empty($terms)): ?>
						<p class="text-muted">Daftar kepala desa belum diverifikasi.</p>
					<?php else: ?>
					<div class="table-wrap">
						<table class="table table-sm mb-0">
							<caption class="visually-hidden">Daftar kepala desa menurut dokumen sumber</caption>
							<thead><tr><th scope="col">Periode</th><th scope="col">Nama</th></tr></thead>
							<tbody>
							<?php foreach ($terms as $term): ?>
								<tr>
									<th scope="row">
										<?= (int) $term['year_start'] ?>&ndash;<?php
										if ($term['year_end']) { echo (int) $term['year_end']; }
										elseif ($term['ongoing_claim']) { echo 'tahun dokumen'; }
										else { echo 'belum diketahui'; } ?>
									</th>
									<td>
										<?= e($term['person_name']) ?>
										<?php if ($term['ongoing_claim']): ?>
											<br><span class="small text-muted">Dokumen sumber menulis &ldquo;sampai sekarang&rdquo;, artinya sampai dokumen itu dibuat.</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<p class="small text-muted mt-2">Nama yang sama dapat muncul pada lebih dari satu periode; periodenya tidak digabung tanpa pemeriksaan masa jabatan.</p>
					<?php endif; ?>
				</div>
			</div>
			<p class="small text-muted mt-4">Revisi <?= (int) ($snapshot['revision_no'] ?? 0) ?>, diterbitkan <?= e(format_wib($snapshot['published_at'] ?? NULL, 'short')) ?>.</p>
		<?php endif; ?>
	</div>
</section>
