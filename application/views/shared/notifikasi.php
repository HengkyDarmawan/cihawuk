<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="page-heading">
	<div>
		<h1>Notifikasi</h1>
		<p>Pemberitahuan tidak memuat isi laporan; buka detail untuk melihat perkembangan.</p>
	</div>
	<?php if ( ! empty($items)): ?>
	<form method="post" action="<?= site_url($read_url) ?>">
		<?= csrf_field() ?>
		<button class="btn btn-outline-primary btn-sm" type="submit">Tandai semua dibaca</button>
	</form>
	<?php endif; ?>
</div>

<div class="card shadow-sm">
	<div class="card-body">
		<?php if (empty($items)): ?>
			<div class="empty-box"><i class="fas fa-bell-slash" aria-hidden="true"></i><p class="mb-0">Belum ada notifikasi.</p></div>
		<?php else: ?>
		<ul class="list-unstyled mb-0">
			<?php foreach ($items as $n): ?>
			<li class="border-bottom py-3 d-flex justify-content-between align-items-start gap-3">
				<div>
					<p class="mb-1 <?= $n->read_at === NULL ? 'font-weight-bold' : '' ?>">
						<?php if ($n->link_path): ?><a href="<?= site_url(ltrim($n->link_path, '/')) ?>"><?= e($n->safe_summary) ?></a><?php else: ?><?= e($n->safe_summary) ?><?php endif; ?>
					</p>
					<span class="small text-muted"><?= e(format_wib($n->created_at)) ?><?= $n->read_at === NULL ? ' · belum dibaca' : '' ?></span>
				</div>
				<?php if ($n->read_at === NULL): ?>
				<form method="post" action="<?= site_url($read_url) ?>">
					<?= csrf_field() ?>
					<input type="hidden" name="id" value="<?= (int) $n->id ?>">
					<button class="btn btn-link btn-sm" type="submit">Tandai dibaca</button>
				</form>
				<?php endif; ?>
			</li>
			<?php endforeach; ?>
		</ul>
		<?php endif; ?>
	</div>
</div>
