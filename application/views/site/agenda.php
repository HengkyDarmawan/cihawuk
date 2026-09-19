<?php defined('BASEPATH') OR exit('No direct script access allowed');
$months_short = array(1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des');
?>
<section class="page-hero">
	<div class="container-site">
		<nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-2"><li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li><li class="breadcrumb-item active" aria-current="page">Agenda</li></ol></nav>
		<h1>Agenda desa</h1>
		<p>Jadwal kegiatan desa dalam waktu WIB.</p>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<nav class="filter-chips" aria-label="Filter agenda">
			<a class="chip" href="<?= site_url('agenda') ?>" <?= ! $archive ? 'aria-current="true"' : '' ?>>Mendatang</a>
			<a class="chip" href="<?= site_url('agenda?arsip=1') ?>" <?= $archive ? 'aria-current="true"' : '' ?>>Arsip</a>
		</nav>

		<?php if (empty($events)): ?>
			<div class="empty-state">
				<?= icon('calendar') ?>
				<h2 class="h5"><?= $archive ? 'Belum ada arsip kegiatan' : 'Belum ada agenda terjadwal' ?></h2>
				<p class="mb-0">Agenda akan tampil di sini setelah diumumkan pengelola desa.</p>
			</div>
		<?php else: ?>
			<div class="agenda-list">
				<?php foreach ($events as $ev):
					$local = (new DateTimeImmutable($ev->starts_at, new DateTimeZone('UTC')))->setTimezone(local_tz()); ?>
				<a class="agenda-item" href="<?= site_url('agenda/'.rawurlencode($ev->slug)) ?>">
					<span class="agenda-date" aria-hidden="true"><span class="d"><?= $local->format('j') ?></span><span class="m"><?= $months_short[(int) $local->format('n')] ?></span></span>
					<span>
						<h2 class="h5 mb-1"><?= e($ev->title) ?></h2>
						<span class="meta">
							<span><?= icon('clock') ?> <?= e(format_wib($ev->starts_at)) ?><?= $ev->ends_at ? ' – '.e(format_wib($ev->ends_at, 'time')) : '' ?></span>
							<?php if ($ev->location_text): ?><span><?= icon('map-pin') ?> <?= e($ev->location_text) ?></span><?php endif; ?>
						</span>
						<?= preview_badge($ev->publication_status) ?>
					</span>
				</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
