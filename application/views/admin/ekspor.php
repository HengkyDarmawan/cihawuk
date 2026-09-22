<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="page-heading">
	<div>
		<h1>Ekspor rekap</h1>
		<p>Berkas dibuat oleh job terjadwal, disimpan privat, dan kedaluwarsa otomatis.</p>
	</div>
	<div class="page-actions"><?= ui_add_button('modal-minta-ekspor', 'Minta ekspor baru') ?></div>
</div>

<div class="row">
	<div class="col-12 mb-4">
		<div class="card shadow-sm">
			<div class="card-header"><h2>Riwayat permintaan</h2></div>
			<div class="card-body">
				<?php if (empty($jobs)): ?>
					<div class="empty-box"><i class="fas fa-file-export" aria-hidden="true"></i><p class="mb-0">Belum ada permintaan ekspor. Klik “Minta ekspor baru” untuk membuat rekap.</p></div>
				<?php else: ?>
				<div class="table-responsive">
					<table class="table table-sm mb-0">
						<caption class="sr-only">Riwayat permintaan ekspor Anda</caption>
						<thead><tr><th scope="col">Dibuat</th><th scope="col">Rekap</th><th scope="col">Status</th><th scope="col">Baris</th><th scope="col">Berlaku s.d.</th><th scope="col"><span class="sr-only">Unduh</span></th></tr></thead>
						<tbody>
						<?php foreach ($jobs as $job): ?>
							<tr>
								<td class="small"><?= e(format_wib($job->created_at, 'short')) ?></td>
								<td class="small"><?= e($report_types[$job->report_type] ?? $job->report_type) ?> · <?= e(strtoupper($job->format)) ?></td>
								<td class="small">
									<?php
									$labels = array('pending' => 'antre', 'processing' => 'diproses', 'ready' => 'siap', 'failed' => 'gagal', 'denied' => 'ditolak (izin berubah)', 'expired' => 'kedaluwarsa');
									$tone = ($job->status === 'ready') ? 'is-info' : (in_array($job->status, array('failed', 'denied'), TRUE) ? 'is-danger' : '');
									?>
									<span class="chip-flag <?= e($tone) ?>"><?= e($labels[$job->status] ?? $job->status) ?></span>
									<?php if ($job->error_message): ?><div class="text-muted"><?= e($job->error_message) ?></div><?php endif; ?>
								</td>
								<td class="small"><?= $job->row_count === NULL ? '—' : (int) $job->row_count ?></td>
								<td class="small"><?= e(format_wib($job->expires_at, 'short')) ?></td>
								<td class="text-right">
									<?php if ($job->status === 'ready' && $job->private_file_id): ?>
										<a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/ekspor/'.rawurlencode($job->public_id).'/unduh') ?>">Unduh<?= $job->byte_size ? ' ('.format_bytes_id($job->byte_size).')' : '' ?></a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>
				<p class="small text-muted mt-3 mb-0">Izin diperiksa ulang saat berkas dibuat dan saat diunduh. Bila izin dicabut, permintaan ditandai ditolak.</p>
			</div>
		</div>
	</div>
</div>

<?= ui_modal_open('modal-minta-ekspor', 'Minta rekap baru') ?>
	<form method="post" action="<?= site_url('admin/ekspor') ?>" data-once>
		<?= csrf_field() ?>
		<div class="form-row">
			<div class="col-md-6"><?= ui_select(array('name' => 'report_type', 'label' => 'Jenis rekap', 'required' => TRUE, 'options' => $report_types, 'value' => 'tickets')) ?></div>
			<div class="col-md-6"><?= ui_select(array('name' => 'format', 'label' => 'Format', 'required' => TRUE, 'options' => array('csv' => 'CSV (baris data)', 'pdf' => 'PDF (ringkasan)'), 'value' => 'csv')) ?></div>
			<div class="col-md-6"><?= ui_select(array('name' => 'status', 'label' => 'Status', 'options' => array_map(function ($s) { return $s['staff']; }, $statuses), 'placeholder_option' => 'Semua status', 'value' => '')) ?></div>
			<div class="col-md-6"><?= ui_select(array('name' => 'jenis', 'label' => 'Jenis laporan', 'options' => $report_kinds, 'placeholder_option' => 'Semua jenis', 'value' => '')) ?></div>
			<div class="col-6"><?= ui_input(array('name' => 'from', 'label' => 'Dari tanggal', 'type' => 'date', 'value' => '')) ?></div>
			<div class="col-6"><?= ui_input(array('name' => 'to', 'label' => 'Sampai tanggal', 'type' => 'date', 'value' => '')) ?></div>
		</div>
		<div class="custom-control custom-checkbox mb-2">
			<input class="custom-control-input" type="checkbox" id="overdue" name="overdue" value="1">
			<label class="custom-control-label" for="overdue">Hanya laporan dengan milestone terlambat</label>
		</div>
		<p class="small text-muted mb-0">Rekap tidak memuat identitas atau kontak pelapor dan hanya mencakup laporan dalam lingkup akses Anda saat berkas dibuat.</p>
		<?= ui_modal_actions('Minta ekspor') ?>
	</form>
<?= ui_modal_close() ?>
