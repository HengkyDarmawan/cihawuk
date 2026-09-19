<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Satu unit fisik: status, QR, mutasi, peminjaman, pemeliharaan, dan historinya. */
$base = site_url('admin/aset/unit/'.rawurlencode($unit->public_id));
$location_options = array('' => '— belum ditentukan —');
foreach ($locations as $location) { $location_options[(string) $location->id] = $location->name; }
?>
<div class="page-heading">
	<div>
		<h1><code><?= e($unit->asset_tag) ?></code></h1>
		<p><?= e($register->name) ?> · status <?= e($lifecycle[$unit->lifecycle_status] ?? $unit->lifecycle_status) ?>
			· kondisi <?= e($conditions[$unit->condition_status] ?? $unit->condition_status) ?></p>
	</div>
	<div><a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/aset/'.rawurlencode($register->public_id)) ?>">Kembali ke register</a></div>
</div>

<?= ui_error_summary($this->form_errors) ?>

<?php if ($issued_token): ?>
<div class="alert alert-success" role="status">
	<strong>Token QR baru.</strong> Salin sekarang; token ini tidak disimpan dan tidak dapat ditampilkan lagi.<br>
	<code><?= e(site_url('aset/q/'.$issued_token)) ?></code>
</div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">QR</h2></div>
	<div class="card-body">
		<?php if ($token): ?>
			<p class="mb-2">Token aktif versi <?= (int) $token->token_version ?>, diterbitkan <?= e(format_wib($token->issued_at, 'short')) ?>.
				<?= $token->last_scanned_at ? 'Terakhir dipindai '.e(format_wib($token->last_scanned_at, 'short')).'.' : 'Belum pernah dipindai.' ?></p>
		<?php else: ?>
			<p class="mb-2 text-muted">Unit ini belum punya token QR aktif.</p>
		<?php endif; ?>
		<?php if ($can_labels): ?>
		<form class="form-inline d-inline-block mr-2" method="post" action="<?= $base ?>/qr/terbitkan" data-once>
			<?= csrf_field() ?>
			<input class="form-control form-control-sm mr-1" type="text" name="reason" maxlength="200" placeholder="Alasan" aria-label="Alasan penerbitan token">
			<button class="btn btn-sm btn-primary" type="submit">Terbitkan token baru</button>
		</form>
		<?php if ($token): ?>
		<form class="form-inline d-inline-block" method="post" action="<?= $base ?>/qr/cabut" data-once>
			<?= csrf_field() ?>
			<input class="form-control form-control-sm mr-1" type="text" name="reason" maxlength="200" placeholder="Alasan pencabutan" aria-label="Alasan pencabutan token">
			<button class="btn btn-sm btn-outline-danger" type="submit">Cabut token</button>
		</form>
		<?php endif; ?>
		<?php endif; ?>
	</div>
</div>

<?php if ($can_status): ?>
<form class="card shadow-sm mb-4" method="post" action="<?= $base ?>/status" data-once>
	<div class="card-header"><h2 class="h6 mb-0">Ubah status atau kondisi</h2></div>
	<div class="card-body">
		<?= csrf_field() ?>
		<div class="form-row">
			<div class="col-md-3"><?= ui_select(array('name' => 'to_lifecycle', 'label' => 'Status lifecycle', 'options' => $lifecycle, 'value' => $unit->lifecycle_status)) ?></div>
			<div class="col-md-3"><?= ui_select(array('name' => 'to_condition', 'label' => 'Kondisi', 'options' => $conditions, 'value' => $unit->condition_status)) ?></div>
			<div class="col-md-2"><?= ui_input(array('name' => 'effective_at', 'label' => 'Tanggal efektif', 'type' => 'date')) ?></div>
			<div class="col-md-4"><?= ui_input(array('name' => 'reason', 'label' => 'Alasan', 'maxlength' => 500, 'required' => TRUE)) ?></div>
		</div>
		<button class="btn btn-primary" type="submit">Simpan perubahan</button>
	</div>
</form>
<?php endif; ?>

<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Riwayat status</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Waktu efektif</th><th scope="col">Peristiwa</th><th scope="col">Perubahan</th><th scope="col">Alasan</th></tr></thead>
			<tbody>
			<?php foreach ($events as $event): ?>
				<tr>
					<th scope="row"><?= e(format_wib($event->effective_at, 'short')) ?></th>
					<td><?= e($event->event_type) ?></td>
					<td class="small">
						<?= $event->from_lifecycle ? e($event->from_lifecycle).' &rarr; '.e((string) $event->to_lifecycle) : '' ?>
						<?= $event->from_condition ? '<br>'.e($event->from_condition).' &rarr; '.e((string) $event->to_condition) : '' ?>
					</td>
					<td class="small text-muted"><?= e($event->reason) ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($events)): ?><tr><td colspan="4" class="text-muted">Belum ada perubahan status.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<?php if ($can_move): ?>
<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Mutasi dan peminjaman</h2></div>
	<div class="card-body">
		<h3 class="h6">Ajukan mutasi</h3>
		<p class="small text-muted">Lokasi unit baru berubah setelah serah terima diterima.</p>
		<form method="post" action="<?= $base ?>/mutasi" data-once>
			<?= csrf_field() ?>
			<div class="form-row">
				<div class="col-md-4"><?= ui_select(array('name' => 'to_location_id', 'label' => 'Lokasi tujuan', 'options' => array_slice($location_options, 1, NULL, TRUE), 'required' => TRUE)) ?></div>
				<div class="col-md-8"><?= ui_input(array('name' => 'reason', 'label' => 'Alasan mutasi', 'maxlength' => 500, 'required' => TRUE)) ?></div>
			</div>
			<button class="btn btn-outline-primary" type="submit">Ajukan</button>
		</form>

		<?php foreach ($movements as $movement): if ($movement->status === 'accepted') { continue; } ?>
		<form class="form-inline mt-2" method="post" action="<?= $base ?>/mutasi/<?= e($movement->public_id) ?>/terima" data-once>
			<?= csrf_field() ?>
			<span class="mr-2 small">Mutasi <?= e(substr($movement->public_id, 0, 8)) ?> menunggu serah terima.</span>
			<button class="btn btn-sm btn-outline-secondary" type="submit">Terima mutasi</button>
		</form>
		<?php endforeach; ?>

		<hr>
		<h3 class="h6">Catat peminjaman</h3>
		<form method="post" action="<?= $base ?>/pinjam" data-once>
			<?= csrf_field() ?>
			<div class="form-row">
				<div class="col-md-3"><?= ui_input(array('name' => 'borrower_name', 'label' => 'Nama peminjam', 'maxlength' => 180, 'required' => TRUE,
					'help' => 'Disimpan terenkripsi dan tidak tampil publik.')) ?></div>
				<div class="col-md-3"><?= ui_input(array('name' => 'borrower_unit', 'label' => 'Unit peminjam', 'maxlength' => 180)) ?></div>
				<div class="col-md-4"><?= ui_input(array('name' => 'purpose', 'label' => 'Tujuan', 'maxlength' => 500, 'required' => TRUE)) ?></div>
				<div class="col-md-2"><?= ui_input(array('name' => 'due_at', 'label' => 'Rencana kembali', 'type' => 'date')) ?></div>
			</div>
			<button class="btn btn-outline-primary" type="submit">Catat peminjaman</button>
		</form>

		<?php foreach ($loans as $loan): if ($loan->status !== 'out') { continue; } ?>
		<form class="form-inline mt-2" method="post" action="<?= $base ?>/pinjam/<?= e($loan->public_id) ?>/kembali" data-once>
			<?= csrf_field() ?>
			<span class="mr-2 small">Sedang dipinjam sejak <?= e(format_wib($loan->checked_out_at, 'short')) ?>.</span>
			<select class="form-select form-select-sm mr-1" name="return_condition" aria-label="Kondisi kembali">
				<?php foreach ($conditions as $code => $label): ?><option value="<?= e($code) ?>"><?= e($label) ?></option><?php endforeach; ?>
			</select>
			<button class="btn btn-sm btn-outline-secondary" type="submit">Catat pengembalian</button>
		</form>
		<?php endforeach; ?>
	</div>
</div>
<?php endif; ?>

<?php if ($can_maintain): ?>
<div class="card shadow-sm">
	<div class="card-header"><h2 class="h6 mb-0">Pemeliharaan</h2></div>
	<div class="card-body">
		<form method="post" action="<?= $base ?>/pemeliharaan" data-once>
			<?= csrf_field() ?>
			<div class="form-row">
				<div class="col-md-6"><?= ui_input(array('name' => 'complaint', 'label' => 'Keluhan', 'maxlength' => 600, 'required' => TRUE)) ?></div>
				<div class="col-md-3"><?= ui_input(array('name' => 'vendor', 'label' => 'Vendor', 'maxlength' => 180)) ?></div>
				<div class="col-md-3"><?= ui_input(array('name' => 'planned_at', 'label' => 'Rencana', 'type' => 'date')) ?></div>
			</div>
			<button class="btn btn-outline-primary" type="submit">Catat pemeliharaan</button>
		</form>

		<?php foreach ($maintenances as $maintenance): if ($maintenance->status === 'completed') { continue; } ?>
		<form class="form-inline mt-3" method="post" action="<?= $base ?>/pemeliharaan/<?= e($maintenance->public_id) ?>/selesai" data-once>
			<?= csrf_field() ?>
			<span class="mr-2 small"><?= e(str_limit_id($maintenance->complaint, 60)) ?></span>
			<input class="form-control form-control-sm mr-1" type="text" name="action_taken" maxlength="600" placeholder="Tindakan" aria-label="Tindakan yang dilakukan" required>
			<select class="form-select form-select-sm mr-1" name="condition_after" aria-label="Kondisi setelah pemeliharaan">
				<?php foreach ($conditions as $code => $label): ?><option value="<?= e($code) ?>"><?= e($label) ?></option><?php endforeach; ?>
			</select>
			<button class="btn btn-sm btn-outline-secondary" type="submit">Tutup</button>
		</form>
		<?php endforeach; ?>
		<p class="small text-muted mt-2 mb-0">Menutup pemeliharaan tidak otomatis membuat kondisi menjadi baik; kondisinya dinilai terpisah.</p>
	</div>
</div>
<?php endif; ?>
