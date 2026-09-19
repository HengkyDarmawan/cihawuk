<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->helper('ticket');
$count = function ($status) use ($counts) { return (int) ($counts[$status] ?? 0); };
$can = function ($p) use ($permissions) { return in_array($p, $permissions, TRUE); };
?>
<div class="page-heading">
	<div>
		<h1>Ringkasan pengelola</h1>
		<p>Peran Anda: <?php foreach ($roles as $i => $role): ?><span class="chip-flag"><?= e($role->name) ?></span> <?php endforeach; ?></p>
	</div>
	<?php if ($can('content.edit')): ?>
	<form method="post" action="<?= site_url('admin/pratinjau') ?>">
		<?= csrf_field() ?>
		<input type="hidden" name="enable" value="<?= $this->session->userdata('content_preview') ? '0' : '1' ?>">
		<button class="btn btn-outline-primary btn-sm" type="submit">
			<i class="fas fa-eye mr-1" aria-hidden="true"></i> <?= $this->session->userdata('content_preview') ? 'Matikan pratinjau draft' : 'Aktifkan pratinjau draft' ?>
		</button>
	</form>
	<?php endif; ?>
</div>

<?php if ($has_ticket_access): ?>
<div class="row">
	<?php
	$cards = array();
	if ($can('tickets.verify'))
	{
		$cards[] = array('label' => 'Menunggu verifikasi', 'value' => $count('submitted'), 'icon' => 'fa-inbox', 'url' => 'admin/laporan?preset=submitted', 'class' => '');
		$cards[] = array('label' => 'Perlu disposisi', 'value' => (int) ($unassigned ?? 0), 'icon' => 'fa-share-square', 'url' => 'admin/laporan?preset=unassigned', 'class' => '');
	}
	if ($can('tickets.work_assigned'))
	{
		$cards[] = array('label' => 'Tugas saya (aktif)', 'value' => (int) ($mine_active ?? 0) + (int) ($mine_assigned ?? 0), 'icon' => 'fa-user-check', 'url' => 'admin/laporan?preset=mine', 'class' => '');
	}
	$cards[] = array('label' => 'Perlu kelengkapan pelapor', 'value' => $count('needs_information'), 'icon' => 'fa-question-circle', 'url' => 'admin/laporan?preset=needs_information', 'class' => '');
	$cards[] = array('label' => 'Terlambat (indikator SLA)', 'value' => (int) $overdue, 'icon' => 'fa-clock', 'url' => 'admin/laporan?preset=overdue', 'class' => 'is-danger');
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
<?php endif; ?>

<div class="row">
	<?php if ( ! empty($deadlines)): ?>
	<div class="col-lg-6 mb-4">
		<div class="card shadow-sm h-100">
			<div class="card-header"><h2>Tenggat terdekat pada tugas saya</h2></div>
			<div class="card-body">
				<ul class="list-unstyled mb-0">
					<?php foreach ($deadlines as $d):
						$due = $d->first_response_due_at ?: ($d->resolution_due_at ?: $d->verification_due_at);
						$late = $due !== NULL && strtotime($due.' UTC') < time(); ?>
					<li class="border-bottom py-2">
						<a href="<?= site_url('admin/laporan/'.rawurlencode($d->public_code)) ?>"><?= e($d->public_code) ?></a> — <?= e(str_limit_id($d->title, 60)) ?>
						<div class="small <?= $late ? 'text-danger font-weight-bold' : 'text-muted' ?>">
							<?= $due ? ($late ? 'Terlambat sejak ' : 'Tenggat ').format_wib($due) : 'Tanpa tenggat' ?>
						</div>
					</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<?php if ( ! empty($workload)): ?>
	<div class="col-lg-6 mb-4">
		<div class="card shadow-sm h-100">
			<div class="card-header"><h2>Beban kerja petugas (lingkup Anda)</h2></div>
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-sm mb-0">
						<caption class="sr-only">Jumlah laporan per petugas</caption>
						<thead><tr><th scope="col">Petugas</th><th scope="col" class="text-right">Aktif</th><th scope="col" class="text-right">Total</th></tr></thead>
						<tbody>
						<?php foreach ($workload as $w): ?>
							<tr><td><?= e($w->display_name) ?></td><td class="text-right"><?= (int) $w->aktif ?></td><td class="text-right"><?= (int) $w->total ?></td></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<?php if ( ! empty($trend)): ?>
	<div class="col-lg-7 mb-4">
		<div class="card shadow-sm h-100">
			<div class="card-header"><h2>Laporan masuk per bulan</h2></div>
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-sm mb-0">
						<caption class="sr-only">Jumlah laporan masuk per bulan (WIB)</caption>
						<thead><tr><?php foreach (array_keys($trend) as $month): ?><th scope="col"><?= e($month) ?></th><?php endforeach; ?></tr></thead>
						<tbody><tr><?php foreach ($trend as $total): ?><td><?= (int) $total ?></td><?php endforeach; ?></tr></tbody>
					</table>
				</div>
				<p class="small text-muted mt-2 mb-0">Dihitung dari waktu penerimaan laporan (submitted_at) dalam zona WIB, hanya untuk laporan dalam lingkup akses Anda.</p>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<?php if ( ! empty($categories)): ?>
	<div class="col-lg-5 mb-4">
		<div class="card shadow-sm h-100">
			<div class="card-header"><h2>Kategori terbanyak</h2></div>
			<div class="card-body">
				<ul class="list-unstyled mb-0">
					<?php foreach ($categories as $cat): ?>
					<li class="d-flex justify-content-between border-bottom py-1"><span><?= e($cat->name) ?></span><strong><?= (int) $cat->total ?></strong></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<?php if ($pending_accounts > 0 OR $content_stats OR $job_stats): ?>
	<div class="col-lg-6 mb-4">
		<div class="card shadow-sm h-100">
			<div class="card-header"><h2>Administrasi</h2></div>
			<div class="card-body">
				<ul class="list-unstyled mb-0">
					<?php if ($this->authz->can_any(array('residents.verify', 'users.manage'))): ?>
					<li class="border-bottom py-2 d-flex justify-content-between">
						<span>Akun menunggu aktivasi</span>
						<a href="<?= site_url('admin/pengguna?status=pending_activation') ?>"><strong><?= (int) $pending_accounts ?></strong></a>
					</li>
					<?php endif; ?>
					<?php if ($content_stats): ?>
						<li class="border-bottom py-2 d-flex justify-content-between"><span>Konten draft</span><a href="<?= site_url('admin/konten/berita') ?>"><strong><?= (int) $content_stats['draft'] ?></strong></a></li>
						<li class="border-bottom py-2 d-flex justify-content-between"><span>Menunggu review</span><strong><?= (int) $content_stats['in_review'] ?></strong></li>
						<li class="border-bottom py-2 d-flex justify-content-between"><span>Masalah data terbuka</span><a href="<?= site_url('admin/statistik/isu') ?>"><strong><?= (int) $content_stats['data_issues'] ?></strong></a></li>
					<?php endif; ?>
					<?php if ($job_stats): ?>
						<li class="border-bottom py-2 d-flex justify-content-between"><span>Email antre / gagal</span><strong><?= (int) $job_stats['outbox_pending'] ?> / <?= (int) $job_stats['outbox_failed'] ?></strong></li>
						<li class="py-2">
							Status email: <strong><?= $job_stats['mail_enabled'] ? 'SMTP aktif' : 'tidak dikonfigurasi' ?></strong>
							<?php if ( ! $job_stats['mail_enabled']): ?>
								<div class="small text-muted">Notifikasi dalam aplikasi tetap berjalan; pesan email berstatus <em>not_configured</em> dan tidak dianggap terkirim.</div>
							<?php endif; ?>
						</li>
					<?php endif; ?>
				</ul>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<div class="col-lg-6 mb-4">
		<div class="card shadow-sm h-100">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h2>Notifikasi terbaru</h2>
				<a class="small" href="<?= site_url('admin/notifikasi') ?>">Semua</a>
			</div>
			<div class="card-body">
				<?php if (empty($notifications)): ?>
					<p class="text-muted mb-0">Belum ada notifikasi.</p>
				<?php else: ?>
				<ul class="list-unstyled mb-0">
					<?php foreach ($notifications as $n): ?>
					<li class="border-bottom py-2 <?= $n->read_at === NULL ? 'font-weight-bold' : '' ?>">
						<?php if ($n->link_path): ?><a href="<?= site_url(ltrim($n->link_path, '/')) ?>"><?= e($n->safe_summary) ?></a><?php else: ?><?= e($n->safe_summary) ?><?php endif; ?>
						<div class="small text-muted font-weight-normal"><?= e(format_wib($n->created_at)) ?></div>
					</li>
					<?php endforeach; ?>
				</ul>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<?php if ( ! empty($recent_audit)): ?>
	<div class="col-12 mb-4">
		<div class="card shadow-sm">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h2>Audit terbaru</h2>
				<a class="small" href="<?= site_url('admin/audit') ?>">Lihat log audit</a>
			</div>
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-sm mb-0">
						<caption class="sr-only">Delapan entri audit terakhir</caption>
						<thead><tr><th scope="col">Waktu</th><th scope="col">Aksi</th><th scope="col">Objek</th><th scope="col">Aktor</th></tr></thead>
						<tbody>
						<?php foreach ($recent_audit as $row): ?>
							<tr>
								<td class="small"><?= e(format_wib($row->created_at, 'short')) ?></td>
								<td class="small"><code><?= e($row->action) ?></code></td>
								<td class="small"><?= e($row->entity_type) ?> <?= e($row->entity_id) ?></td>
								<td class="small"><?= e($row->actor_name ?: $row->actor_label ?: 'sistem') ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
	<?php endif; ?>
</div>

<?php if ( ! $has_ticket_access && ! $content_stats && ! $job_stats): ?>
<div class="card shadow-sm">
	<div class="card-body empty-box">
		<i class="fas fa-lock" aria-hidden="true"></i>
		<p class="mb-0">Akun Anda belum memiliki izin untuk modul mana pun. Hubungi Super Admin untuk penetapan peran.</p>
	</div>
</div>
<?php endif; ?>
