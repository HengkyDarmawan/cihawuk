<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Kalender bulan agenda. Dipakai halaman /agenda dan section beranda (varian ringkas).
 * @var array $month dari agenda_month() @var array $weeks dari agenda_month_grid() @var bool $compact
 * Setiap kegiatan tetap berupa tautan ke halaman detail; calendar.js mengubah klik menjadi popup.
 */
$compact = ! empty($compact);
$day_names = array('Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min');
$max_visible = $compact ? 0 : 2;
$months_long = array(1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember');
?>
<div class="cal<?= $compact ? ' cal-compact' : '' ?>">
	<div class="cal-head">
		<?php if ($compact): ?>
			<h3 class="cal-title"><?= e($month['label']) ?></h3>
			<a class="cal-link" href="<?= site_url('agenda') ?>">Buka kalender <?= icon('arrow-right') ?></a>
		<?php else: ?>
			<a class="cal-nav" href="<?= site_url('agenda?bulan='.$month['prev']) ?>" aria-label="Bulan sebelumnya"><?= icon('chevron-left') ?></a>
			<h2 class="cal-title" id="cal-title"><?= e($month['label']) ?></h2>
			<a class="cal-nav" href="<?= site_url('agenda?bulan='.$month['next']) ?>" aria-label="Bulan berikutnya"><?= icon('chevron-right') ?></a>
			<?php if ( ! $month['is_current']): ?><a class="cal-today-btn" href="<?= site_url('agenda') ?>">Hari ini</a><?php endif; ?>
		<?php endif; ?>
	</div>
	<table class="cal-grid">
		<caption class="visually-hidden">Kalender kegiatan <?= e($month['label']) ?></caption>
		<thead><tr><?php foreach ($day_names as $i => $name): ?><th scope="col"<?= $i >= 5 ? ' class="is-weekend"' : '' ?>><?= $name ?></th><?php endforeach; ?></tr></thead>
		<tbody>
		<?php foreach ($weeks as $week): ?>
			<tr>
			<?php foreach ($week as $i => $cell):
				$count = count($cell['events']);
				$d = new DateTimeImmutable($cell['date']);
				$day_label = $cell['day'].' '.$months_long[(int) $d->format('n')].' '.$d->format('Y');
				$payload = $count ? e(json_encode(array('date' => $day_label, 'events' => array_map('agenda_event_payload', $cell['events'])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) : '';
				$classes = 'cal-day'.($cell['in_month'] ? '' : ' is-out').($cell['is_today'] ? ' is-today' : '').($count ? ' has-events' : '').($i >= 5 ? ' is-weekend' : ''); ?>
				<td class="<?= $classes ?>">
					<?php if ($count): ?>
						<button class="cal-date" type="button" data-day-events="<?= $payload ?>" aria-label="<?= e($day_label.', '.$count.' kegiatan') ?>">
							<span class="cal-num"><?= $cell['day'] ?></span><span class="cal-dot" aria-hidden="true"></span>
						</button>
					<?php else: ?>
						<span class="cal-date"><span class="cal-num"><?= $cell['day'] ?></span></span>
					<?php endif; ?>
					<?php if ($max_visible && $count): ?>
						<div class="cal-events">
							<?php foreach (array_slice($cell['events'], 0, $max_visible) as $ev): $p = agenda_event_payload($ev); ?>
								<a class="cal-pill" href="<?= e($p['url']) ?>" data-event="<?= e(json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
									<span class="cal-pill-time"><?= e($p['time']) ?></span> <?= e($ev->title) ?>
								</a>
							<?php endforeach; ?>
							<?php if ($count > $max_visible): ?>
								<button class="cal-more" type="button" data-day-events="<?= $payload ?>">+<?= $count - $max_visible ?> lainnya</button>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</td>
			<?php endforeach; ?>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
