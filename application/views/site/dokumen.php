<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="page-hero">
	<div class="container-site">
		<nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-2"><li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li><li class="breadcrumb-item active" aria-current="page">Dokumen Publik</li></ol></nav>
		<h1>Dokumen publik</h1>
		<p>Dokumen yang sudah disetujui untuk dipublikasikan. Dokumen sumber internal tidak ditampilkan di sini.</p>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<nav class="filter-chips" aria-label="Filter dokumen">
			<a class="chip" href="<?= site_url('dokumen') ?>" <?= $active_category === '' && ! $active_year ? 'aria-current="true"' : '' ?>>Semua</a>
			<?php foreach ($categories as $cat): ?>
				<a class="chip" href="<?= site_url('dokumen?kategori='.rawurlencode($cat->slug)) ?>" <?= $active_category === $cat->slug ? 'aria-current="true"' : '' ?>><?= e($cat->name) ?></a>
			<?php endforeach; ?>
			<?php foreach ($years as $year): ?>
				<a class="chip" href="<?= site_url('dokumen?tahun='.(int) $year) ?>" <?= $active_year === (int) $year ? 'aria-current="true"' : '' ?>><?= (int) $year ?></a>
			<?php endforeach; ?>
		</nav>

		<?php if (empty($documents)): ?>
			<div class="empty-state">
				<?= icon('folder') ?>
				<h2 class="h5">Belum ada dokumen publik</h2>
				<p class="mb-0">Dokumen akan tersedia setelah pengelola menyetujui versi yang boleh dipublikasikan.</p>
			</div>
		<?php else: ?>
			<div class="d-grid gap-3">
				<?php foreach ($documents as $doc): ?>
				<div class="doc-item">
					<span class="doc-icon" aria-hidden="true"><?= icon('file-text') ?></span>
					<div class="flex-grow-1">
						<h2 class="h6 mb-1"><?= e($doc->title) ?></h2>
						<div class="meta">
							<?php if ($doc->category_name): ?><span><?= e($doc->category_name) ?></span><?php endif; ?>
							<?php if ($doc->source_year): ?><span>Tahun <?= (int) $doc->source_year ?></span><?php endif; ?>
							<span>Versi <?= e($doc->version_label) ?></span>
							<span><?= e(format_bytes_id($doc->byte_size)) ?></span>
						</div>
						<?php if ($doc->description): ?><p class="small text-muted mb-0 mt-1"><?= e($doc->description) ?></p><?php endif; ?>
					</div>
					<a class="btn btn-outline-primary btn-sm" href="<?= site_url('dokumen/'.(int) $doc->id.'/unduh') ?>"><?= icon('download') ?> Unduh</a>
				</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
