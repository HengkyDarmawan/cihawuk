<?php defined('BASEPATH') OR exit('No direct script access allowed');
$has_officials = FALSE;
foreach ($positions as $p) { if ( ! empty($p->officials)) { $has_officials = TRUE; break; } }
?>
<section class="page-hero">
	<div class="container-site">
		<nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-2"><li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li><li class="breadcrumb-item active" aria-current="page">Pemerintahan</li></ol></nav>
		<h1>Pemerintahan desa</h1>
		<p>Struktur jabatan dan perangkat desa yang telah direview pengelola.</p>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<?php if (empty($positions)): ?>
			<div class="empty-state">
				<?= icon('users') ?>
				<h2 class="h5">Struktur pemerintahan sedang dilengkapi</h2>
				<p class="mb-0">Data jabatan dan pejabat desa akan ditampilkan setelah diverifikasi pengelola. Dokumen sumber tahun 2023 masih memerlukan konfirmasi untuk kondisi saat ini.</p>
			</div>
		<?php else: ?>
			<div class="org-grid">
				<?php foreach ($positions as $position): ?>
				<article class="card-soft org-card">
					<?php $official = $position->officials[0] ?? NULL; ?>
					<div class="org-photo">
						<?php if ($official && $official->photo): ?>
							<?= media_img($official->photo, 'Foto pejabat', FALSE, '104px') ?>
						<?php else: ?>
							<?= icon('user') ?>
						<?php endif; ?>
					</div>
					<h2 class="h6 mb-1"><?= e($position->title) ?></h2>
					<?php if ($official): ?>
						<p class="mb-1"><strong><?= e($official->name) ?></strong></p>
						<?php if ($official->term_start OR $official->term_end): ?>
							<p class="small text-muted mb-1">Periode <?= e($official->term_start ? format_date_id($official->term_start) : '—') ?><?= $official->term_end ? ' s.d. '.e(format_date_id($official->term_end)) : '' ?></p>
						<?php endif; ?>
						<?php if ($official->source_note): ?><p class="small text-muted mb-0"><?= e($official->source_note) ?></p><?php endif; ?>
					<?php else: ?>
						<p class="small text-muted mb-0">Nama pejabat belum dikonfirmasi.</p>
					<?php endif; ?>
					<?php if ($position->duties_summary): ?><p class="small mt-2 mb-0"><?= e($position->duties_summary) ?></p><?php endif; ?>
				</article>
				<?php endforeach; ?>
			</div>
			<?php if ( ! $has_officials): ?>
				<div class="source-note mt-4"><?= icon('info') ?><div>Nama pejabat pada dokumen profil 2023 belum dikonfirmasi untuk kondisi saat ini, sehingga belum ditampilkan. Pengelola desa dapat memperbaruinya melalui dashboard.</div></div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</section>
