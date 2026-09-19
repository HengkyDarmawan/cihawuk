<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->helper('ticket');
?>
<div class="page-heading">
	<div>
		<h1>Selamat datang, <?= e($user->display_name) ?></h1>
		<p>Ringkasan laporan Anda di Layanan Digital Desa Cihawuk.</p>
	</div>
	<a class="btn btn-primary" href="<?= site_url('warga/laporan/buat') ?>"><i class="fas fa-edit mr-1" aria-hidden="true"></i> Buat laporan</a>
</div>

<?php if ($profile && $profile->verification_status !== 'verified'): ?>
<div class="alert alert-info d-flex" role="status">
	<i class="fas fa-info-circle mt-1 mr-2" aria-hidden="true"></i>
	<div>Status verifikasi profil Anda: <strong><?= e(config_label('resident_verification_statuses', $profile->verification_status)) ?></strong>. Anda tetap dapat membuat laporan; verifikasi hanya melengkapi data kependudukan pada layanan tertentu.</div>
</div>
<?php endif; ?>

<div class="row">
	<?php
	$cards = array(
		array('label' => 'Total laporan', 'value' => $summary['total'], 'icon' => 'fa-inbox', 'class' => '', 'url' => 'warga/laporan'),
		array('label' => 'Sedang berjalan', 'value' => $summary['active'], 'icon' => 'fa-spinner', 'class' => '', 'url' => 'warga/laporan'),
		array('label' => 'Perlu tanggapan Anda', 'value' => $summary['awaiting'], 'icon' => 'fa-comment-dots', 'class' => 'is-warning', 'url' => 'warga/laporan?status=awaiting_confirmation'),
		array('label' => 'Selesai', 'value' => $summary['resolved'], 'icon' => 'fa-check-circle', 'class' => '', 'url' => 'warga/laporan?status=resolved'),
	);
	foreach ($cards as $card): ?>
	<div class="col-md-6 col-xl-3 mb-4">
		<a class="card stat-card shadow-sm h-100 <?= e($card['class']) ?>" href="<?= site_url($card['url']) ?>">
			<div class="card-body d-flex align-items-center justify-content-between">
				<div>
					<div class="stat-label"><?= e($card['label']) ?></div>
					<div class="stat-value"><?= (int) $card['value'] ?></div>
				</div>
				<span class="stat-icon"><i class="fas <?= e($card['icon']) ?>" aria-hidden="true"></i></span>
			</div>
		</a>
	</div>
	<?php endforeach; ?>
</div>

<div class="row">
	<div class="col-lg-7 mb-4">
		<div class="card shadow-sm h-100">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h2>Laporan terbaru</h2>
				<a class="small" href="<?= site_url('warga/laporan') ?>">Lihat semua</a>
			</div>
			<div class="card-body">
				<?php if (empty($recent)): ?>
					<div class="empty-box">
						<i class="fas fa-inbox" aria-hidden="true"></i>
						<p class="mb-2">Anda belum pernah mengirim laporan dari akun ini.</p>
						<a class="btn btn-primary btn-sm" href="<?= site_url('warga/laporan/buat') ?>">Buat laporan pertama</a>
					</div>
				<?php else: ?>
					<ul class="list-unstyled mb-0">
						<?php foreach ($recent as $t): ?>
						<li class="border-bottom py-2">
							<div class="d-flex justify-content-between flex-wrap gap-2">
								<a class="font-weight-bold" href="<?= site_url('warga/laporan/'.rawurlencode($t->public_code)) ?>"><?= e($t->title) ?></a>
								<?= ticket_status_badge($t->status, 'reporter') ?>
							</div>
							<div class="small text-muted"><?= e($t->public_code) ?> · <?= e(config_label('report_types', $t->report_type)) ?> · <?= e(format_wib($t->submitted_at, 'date')) ?></div>
						</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="col-lg-5 mb-4">
		<?php if ( ! empty($needs_response) OR ! empty($needs_info)): ?>
		<div class="card shadow-sm mb-4 border-left-warning">
			<div class="card-header"><h2>Menunggu tindakan Anda</h2></div>
			<div class="card-body">
				<ul class="list-unstyled mb-0">
					<?php foreach (array_merge($needs_info, $needs_response) as $t): ?>
					<li class="mb-2">
						<a href="<?= site_url('warga/laporan/'.rawurlencode($t->public_code)) ?>"><?= e($t->public_code) ?></a> —
						<?= ($t->status === 'needs_information') ? 'petugas meminta kelengkapan' : 'hasil penanganan menunggu tanggapan Anda' ?>
					</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php endif; ?>

		<div class="card shadow-sm">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h2>Notifikasi</h2>
				<a class="small" href="<?= site_url('warga/notifikasi') ?>">Semua</a>
			</div>
			<div class="card-body">
				<?php if (empty($notifications)): ?>
					<p class="text-muted mb-0">Belum ada notifikasi.</p>
				<?php else: ?>
					<ul class="list-unstyled mb-0">
						<?php foreach ($notifications as $n): ?>
						<li class="mb-2 <?= $n->read_at === NULL ? 'font-weight-bold' : '' ?>">
							<?php if ($n->link_path): ?><a href="<?= site_url(ltrim($n->link_path, '/')) ?>"><?= e($n->safe_summary) ?></a><?php else: ?><?= e($n->safe_summary) ?><?php endif; ?>
							<div class="small text-muted font-weight-normal"><?= e(format_wib($n->created_at)) ?></div>
						</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
