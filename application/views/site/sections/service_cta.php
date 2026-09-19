<?php defined('BASEPATH') OR exit('No direct script access allowed');
$cfg = $s['config'];
$links = $cfg['cta'] ?? array();
?>
<section class="section-sm pb-5" aria-labelledby="cta-title">
	<div class="container-wide">
		<div class="cta-banner reveal">
			<div>
				<h2 id="cta-title"><?= e($s['title'] ?: 'Sampaikan keluhan, usulan, atau pertanyaan Anda') ?></h2>
				<?php if ( ! empty($cfg['body'])): ?><p><?= e($cfg['body']) ?></p><?php endif; ?>
			</div>
			<?php if ($links): ?>
			<div class="hero-actions">
				<?php foreach ($links as $i => $link): ?>
					<a class="btn btn-lg <?= $i === 0 ? 'btn-accent' : 'btn-light-glass' ?>" href="<?= nav_href($link['url']) ?>"><?= e($link['label']) ?></a>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>
	</div>
</section>
