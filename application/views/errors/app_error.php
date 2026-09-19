<?php defined('BASEPATH') OR exit('No direct script access allowed');
$icons = array(401 => 'lock', 403 => 'slash', 404 => 'compass', 405 => 'slash', 409 => 'git-merge', 413 => 'file', 419 => 'clock', 422 => 'alert-circle', 428 => 'lock', 429 => 'clock', 500 => 'alert-octagon');
$is_dashboard = isset($area);
?>
<?php if ($is_dashboard): ?>
<div class="container-fluid">
	<div class="card shadow-sm border-0 my-4" style="max-width: 720px;">
		<div class="card-body p-4">
			<h1 class="h3 mb-2 text-gray-800"><?= icon($icons[$status] ?? 'alert-circle') ?> <?= e($title) ?></h1>
			<p class="mb-3"><?= e($message) ?></p>
			<?php if ( ! empty($errors)): ?>
			<ul class="mb-3">
				<?php foreach ($errors as $field => $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
			</ul>
			<?php endif; ?>
			<?php if ((int) $status === 428): ?>
				<form method="post" action="<?= site_url($area === 'admin' ? 'admin/akun/konfirmasi' : 'warga/akun/konfirmasi') ?>" class="mb-2" style="max-width: 360px;">
					<?= csrf_field() ?>
					<label class="form-label font-weight-bold" for="reauth_password">Password Anda</label>
					<input class="form-control mb-2" type="password" id="reauth_password" name="password" autocomplete="current-password" required>
					<button class="btn btn-primary" type="submit">Konfirmasi</button>
				</form>
			<?php endif; ?>
			<a class="btn btn-outline-primary" href="<?= site_url($area === 'admin' ? 'admin' : 'warga') ?>">Ke dashboard</a>
		</div>
	</div>
</div>
<?php else: ?>
<section class="page-body">
	<div class="container-site">
		<div class="card-soft card-pad mx-auto" style="max-width: 640px;">
			<p class="eyebrow">Kode <?= (int) $status ?></p>
			<h1 class="display-serif h2"><?= e($title) ?></h1>
			<p class="text-muted"><?= e($message) ?></p>
			<?php if ( ! empty($errors)): ?>
			<div class="error-summary mb-3" role="alert">
				<ul class="mb-0">
					<?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
				</ul>
			</div>
			<?php endif; ?>
			<div class="d-flex flex-wrap gap-2">
				<a class="btn btn-primary" href="<?= site_url('/') ?>">Ke beranda</a>
				<a class="btn btn-outline-primary" href="<?= site_url('layanan') ?>">Layanan warga</a>
			</div>
		</div>
	</div>
</section>
<?php endif; ?>
