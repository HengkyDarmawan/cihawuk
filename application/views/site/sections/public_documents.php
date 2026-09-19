<?php defined('BASEPATH') OR exit('No direct script access allowed');
$items = $d['items'] ?? array();
?>
<section class="section" aria-labelledby="dokumen-title">
	<div class="container-wide">
		<div class="section-head">
			<div>
				<p class="eyebrow"><?= e($s['subtitle'] ?: 'Dokumen') ?></p>
				<h2 class="section-title" id="dokumen-title"><?= e($s['title'] ?: 'Dokumen publik') ?></h2>
			</div>
			<a class="link-arrow" href="<?= site_url('dokumen') ?>">Semua dokumen <?= icon('arrow-right') ?></a>
		</div>
		<?php if ($items): ?>
		<ul class="doc-list">
			<?php foreach ($items as $doc): ?>
			<li>
				<a href="<?= site_url('dokumen/'.(int) $doc->id.'/unduh') ?>">
					<?= icon('file-text') ?> <?= e($doc->title) ?>
					<span class="text-muted small"><?= $doc->source_year ? e($doc->source_year).' · ' : '' ?><?= e(format_bytes_id($doc->byte_size ?? 0)) ?></span>
				</a>
			</li>
			<?php endforeach; ?>
		</ul>
		<?php else: ?>
		<div class="empty-state">
			<?= icon('folder') ?>
			<h3>Belum ada dokumen publik</h3>
			<p class="mb-0">Dokumen akan tampil setelah direview dan disetujui untuk publik.</p>
		</div>
		<?php endif; ?>
	</div>
</section>
