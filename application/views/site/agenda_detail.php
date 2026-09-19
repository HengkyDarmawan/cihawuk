<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="page-hero">
	<div class="container-site">
		<nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-2">
			<li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li>
			<li class="breadcrumb-item"><a href="<?= site_url('agenda') ?>">Agenda</a></li>
		</ol></nav>
		<h1><?= e($event->title) ?></h1>
		<p><?= icon('clock') ?> <?= e(format_wib($event->starts_at)) ?><?= $event->ends_at ? ' – '.e(format_wib($event->ends_at)) : '' ?></p>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<div class="row g-5">
			<div class="col-lg-8">
				<?php if ($event->poster): ?>
					<div class="media-frame mb-4"><?= media_img($event->poster, 'Poster kegiatan', TRUE, '(min-width: 992px) 66vw, 100vw') ?></div>
				<?php endif; ?>
				<?php if ($event->summary): ?><p class="section-lead"><?= e($event->summary) ?></p><?php endif; ?>
				<?php if ($event->description_html): ?><div class="prose"><?= $event->description_html ?></div><?php endif; ?>
			</div>
			<aside class="col-lg-4">
				<div class="card-soft card-pad">
					<h2 class="h6">Informasi kegiatan</h2>
					<dl class="fact-list mb-0">
						<div><dt>Mulai</dt><dd><?= e(format_wib($event->starts_at)) ?></dd></div>
						<?php if ($event->ends_at): ?><div><dt>Selesai</dt><dd><?= e(format_wib($event->ends_at)) ?></dd></div><?php endif; ?>
						<?php if ($event->location_text): ?><div><dt>Lokasi</dt><dd><?= e($event->location_text) ?></dd></div><?php endif; ?>
						<?php if ($event->organizer): ?><div><dt>Penyelenggara</dt><dd><?= e($event->organizer) ?></dd></div><?php endif; ?>
					</dl>
				</div>
			</aside>
		</div>
	</div>
</section>
