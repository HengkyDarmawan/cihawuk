<?php defined('BASEPATH') OR exit('No direct script access allowed');
$months_short = array(1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des');
?>
<section class="page-hero">
	<div class="container-site">
		<nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-2"><li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li><li class="breadcrumb-item active" aria-current="page">Agenda</li></ol></nav>
		<h1>Agenda desa</h1>
		<p>Kalender kegiatan desa dalam waktu WIB. Klik kegiatan atau tanggal untuk melihat penjelasannya.</p>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<nav class="filter-chips" aria-label="Tampilan agenda">
			<a class="chip" href="<?= site_url('agenda') ?>" <?= ! $archive ? 'aria-current="true"' : '' ?>><?= icon('calendar') ?> Kalender</a>
			<a class="chip" href="<?= site_url('agenda?arsip=1') ?>" <?= $archive ? 'aria-current="true"' : '' ?>><?= icon('archive') ?> Arsip</a>
		</nav>

		<?php if ( ! $archive): ?>
			<?php $this->load->view('site/partials/agenda_calendar', array('month' => $month, 'weeks' => $weeks, 'compact' => FALSE)); ?>

			<div class="cal-legend">
				<span><span class="cal-legend-today" aria-hidden="true"></span> Hari ini</span>
				<span><span class="cal-legend-event" aria-hidden="true"></span> Ada kegiatan</span>
			</div>

			<h2 class="section-title h4 mt-5" id="kegiatan-bulan">Kegiatan <?= e($month['label']) ?></h2>
			<?php if (empty($events)): ?>
				<div class="empty-state">
					<?= icon('calendar') ?>
					<h3 class="h5">Belum ada kegiatan di bulan ini</h3>
					<?php if ( ! empty($next_event)): $nl = (new DateTimeImmutable($next_event->starts_at, new DateTimeZone('UTC')))->setTimezone(local_tz()); ?>
						<p>Kegiatan terdekat: <strong><?= e($next_event->title) ?></strong>, <?= e(format_wib($next_event->starts_at, 'date')) ?>.</p>
						<a class="btn btn-primary" href="<?= site_url('agenda?bulan='.$nl->format('Y-m')) ?>">Lihat bulan <?= e($months_short[(int) $nl->format('n')].' '.$nl->format('Y')) ?></a>
					<?php else: ?>
						<p class="mb-0">Agenda akan tampil di sini setelah diumumkan pengelola desa.</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		<?php elseif (empty($events)): ?>
			<div class="empty-state">
				<?= icon('calendar') ?>
				<h2 class="h5">Belum ada arsip kegiatan</h2>
				<p class="mb-0">Kegiatan yang sudah lewat akan tampil di sini.</p>
			</div>
		<?php endif; ?>

		<?php if ( ! empty($events)): ?>
			<div class="agenda-list">
				<?php foreach ($events as $ev):
					$local = (new DateTimeImmutable($ev->starts_at, new DateTimeZone('UTC')))->setTimezone(local_tz());
					$p = agenda_event_payload($ev); ?>
				<a class="agenda-item" href="<?= e($p['url']) ?>" data-event="<?= e(json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
					<span class="agenda-date" aria-hidden="true"><span class="d"><?= $local->format('j') ?></span><span class="m"><?= $months_short[(int) $local->format('n')] ?></span></span>
					<span>
						<h3 class="h5 mb-1"><?= e($ev->title) ?></h3>
						<span class="meta">
							<span><?= icon('clock') ?> <?= e($p['when']) ?></span>
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
