<?php defined('BASEPATH') OR exit('No direct script access allowed');
$cfg = $s['config'];
$severity = $cfg['severity'] ?? 'info';
$class = array('info' => 'alert-info', 'warning' => 'alert-warning', 'urgent' => 'alert-danger');
?>
<section class="section-sm" aria-label="<?= e($s['title'] ?: 'Pengumuman') ?>">
	<div class="container-site">
		<div class="alert <?= $class[$severity] ?? 'alert-info' ?>" role="status">
			<?php if ($s['title']): ?><strong class="d-block"><?= e($s['title']) ?></strong><?php endif; ?>
			<?= nl2br(e($cfg['body'] ?? '')) ?>
		</div>
	</div>
</section>
