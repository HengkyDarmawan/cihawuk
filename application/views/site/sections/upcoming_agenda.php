<?php defined('BASEPATH') OR exit('No direct script access allowed');
$items = $d['items'] ?? array();
$months_short = array(1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des');
?>
<section class="section" aria-labelledby="agenda-title">
	<div class="container-wide">
		<p class="eyebrow"><?= e($s['subtitle'] ?: 'Agenda') ?></p>
		<h2 class="section-title" id="agenda-title"><?= e($s['title'] ?: 'Kegiatan mendatang') ?></h2>
		<?php if ($items): ?>
		<div class="agenda-list">
			<?php foreach ($items as $ev):
				$local = (new DateTimeImmutable($ev->starts_at, new DateTimeZone('UTC')))->setTimezone(local_tz()); ?>
			<a class="agenda-item" href="<?= site_url('agenda/'.rawurlencode($ev->slug)) ?>">
				<span class="agenda-date" aria-hidden="true"><span class="d"><?= $local->format('j') ?></span><span class="m"><?= $months_short[(int) $local->format('n')] ?></span></span>
				<span>
					<h3><?= e($ev->title) ?></h3>
					<span class="meta">
						<span><?= icon('clock') ?> <?= format_wib($ev->starts_at) ?></span>
						<?php if ($ev->location_text): ?><span><?= icon('map-pin') ?> <?= e($ev->location_text) ?></span><?php endif; ?>
					</span>
				</span>
			</a>
			<?php endforeach; ?>
		</div>
		<a class="link-arrow mt-3" href="<?= site_url('agenda') ?>">Lihat semua agenda <?= icon('arrow-right') ?></a>
		<?php else: ?>
		<div class="empty-state">
			<?= icon('calendar') ?>
			<h3>Belum ada agenda terjadwal</h3>
			<p class="mb-0">Agenda kegiatan desa akan diumumkan di sini.</p>
		</div>
		<?php endif; ?>
	</div>
</section>
