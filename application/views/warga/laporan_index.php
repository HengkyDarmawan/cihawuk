<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->helper('ticket');
$query = function (array $params) use ($filters, $page) {
	$base = array('status' => $filters['status'], 'jenis' => $filters['report_type'], 'hal' => $page);
	return site_url('warga/laporan').'?'.http_build_query(array_filter(array_merge($base, $params), function ($v) { return $v !== '' && $v !== NULL; }));
};
?>
<div class="page-heading">
	<div>
		<h1>Laporan saya</h1>
		<p><?= (int) $total ?> laporan tercatat atas akun Anda.</p>
	</div>
	<a class="btn btn-primary" href="<?= site_url('warga/laporan/buat') ?>"><i class="fas fa-edit mr-1" aria-hidden="true"></i> Buat laporan</a>
</div>

<div class="card shadow-sm mb-4">
	<div class="card-body">
		<form method="get" action="<?= site_url('warga/laporan') ?>" class="form-row align-items-end">
			<div class="col-md-4 mb-2">
				<label for="f-status">Status</label>
				<select class="form-control" id="f-status" name="status">
					<option value="">Semua status</option>
					<?php foreach ($statuses as $code => $meta): ?>
						<option value="<?= e($code) ?>" <?= $filters['status'] === $code ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-4 mb-2">
				<label for="f-jenis">Jenis laporan</label>
				<select class="form-control" id="f-jenis" name="jenis">
					<option value="">Semua jenis</option>
					<?php foreach ($report_types as $code => $label): ?>
						<option value="<?= e($code) ?>" <?= $filters['report_type'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-4 mb-2 d-flex gap-2">
				<button class="btn btn-primary mr-2" type="submit">Terapkan</button>
				<a class="btn btn-outline-primary" href="<?= site_url('warga/laporan') ?>">Reset</a>
			</div>
		</form>
	</div>
</div>

<div class="card shadow-sm">
	<div class="card-body">
		<?php if (empty($tickets)): ?>
			<div class="empty-box">
				<i class="fas fa-inbox" aria-hidden="true"></i>
				<p class="mb-0">Tidak ada laporan yang cocok dengan filter ini.</p>
			</div>
		<?php else: ?>
		<div class="table-responsive">
			<table class="table table-hover mb-0">
				<caption class="sr-only">Daftar laporan milik Anda</caption>
				<thead>
					<tr>
						<th scope="col">Nomor &amp; judul</th>
						<th scope="col">Jenis</th>
						<th scope="col">Status</th>
						<th scope="col">Dikirim</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($tickets as $t): ?>
					<tr>
						<td>
							<a class="font-weight-bold" href="<?= site_url('warga/laporan/'.rawurlencode($t->public_code)) ?>"><?= e($t->title) ?></a>
							<div class="small text-muted"><?= e($t->public_code) ?> · <?= e($t->category_name) ?></div>
						</td>
						<td><?= e(config_label('report_types', $t->report_type)) ?></td>
						<td><?= ticket_status_badge($t->status, 'reporter') ?></td>
						<td><span title="<?= e(format_wib($t->submitted_at)) ?>"><?= e(format_wib($t->submitted_at, 'date')) ?></span></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>
	</div>
	<?php if ($pages > 1): ?>
	<div class="card-footer bg-white">
		<nav aria-label="Halaman laporan">
			<ul class="pagination mb-0 justify-content-center">
				<li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $query(array('hal' => $page - 1)) ?>">Sebelumnya</a></li>
				<?php for ($i = 1; $i <= $pages; $i++): ?>
					<li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= $query(array('hal' => $i)) ?>"<?= $i === $page ? ' aria-current="page"' : '' ?>><?= $i ?></a></li>
				<?php endfor; ?>
				<li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $query(array('hal' => $page + 1)) ?>">Berikutnya</a></li>
			</ul>
		</nav>
	</div>
	<?php endif; ?>
</div>
