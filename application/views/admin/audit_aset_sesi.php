<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Satu sesi audit: target beku, temuan, perbedaan, koreksi, penutupan, addendum. */
$base = site_url('admin/audit-aset/'.rawurlencode($session->public_id));
$target_options = array();
foreach ($targets as $target) { $target_options[(string) $target->id] = $target->asset_tag; }
$location_options = array('' => '— sama seperti seharusnya —');
foreach ($locations as $location) { $location_options[(string) $location->id] = $location->name; }
?>
<div class="page-heading">
	<div>
		<h1><?= e($session->name) ?></h1>
		<p>Status: <?= e($statuses[$session->status] ?? $session->status) ?> · <?= count($targets) ?> target beku</p>
	</div>
	<div><a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/audit-aset') ?>">Kembali</a></div>
</div>

<?= ui_error_summary($this->form_errors) ?>

<?php if ($session->status === 'draft' && $can_verify): ?>
<form class="card shadow-sm mb-4" method="post" action="<?= $base ?>/terbitkan" data-once>
	<div class="card-body">
		<?= csrf_field() ?>
		<p class="mb-2">Menerbitkan sesi membekukan daftar unit yang akan diaudit beserta kondisinya saat ini.</p>
		<button class="btn btn-primary" type="submit">Terbitkan sesi audit</button>
	</div>
</form>
<?php endif; ?>

<?php if ($differences): ?>
<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Perbedaan dari data master</h2></div>
	<div class="card-body">
		<p class="small text-muted">Perbedaan ini hanya informasi. Master berubah hanya setelah temuan diverifikasi lalu dikoreksi.</p>
		<ul class="mb-0">
		<?php foreach ($differences as $row): ?>
			<li><code><?= e($row['finding']->asset_tag) ?></code>:
				<?php foreach ($row['diff'] as $key => $value): ?>
					<?= e($key) ?> (<?= e(is_array($value) ? implode(' &rarr; ', $value) : (string) $value) ?>)
				<?php endforeach; ?>
			</li>
		<?php endforeach; ?>
		</ul>
	</div>
</div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Temuan</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Asset tag</th><th scope="col">Keberadaan</th><th scope="col">Kondisi</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead>
			<tbody>
			<?php foreach ($findings as $finding): ?>
				<tr>
					<th scope="row"><code><?= e($finding->asset_tag) ?></code></th>
					<td><?= e($existence[$finding->existence_result] ?? $finding->existence_result) ?></td>
					<td><?= e($conditions[$finding->observed_condition] ?? $finding->observed_condition) ?></td>
					<td><?php $fs = array('submitted' => array('info', 'Menunggu verifikasi'), 'verified' => array('success', 'Terverifikasi'), 'rejected' => array('danger', 'Ditolak'))[$finding->status] ?? array('secondary', $finding->status); ?>
						<span class="badge badge-pill badge-<?= $fs[0] ?>"><?= e($fs[1]) ?></span></td>
					<td>
						<?php if ($can_verify && $finding->status === 'submitted'): ?>
							<form class="d-inline" method="post" action="<?= $base ?>/temuan/<?= e($finding->public_id) ?>/terima" data-once>
								<?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary" type="submit">Terima</button>
							</form>
							<form class="d-inline" method="post" action="<?= $base ?>/temuan/<?= e($finding->public_id) ?>/tolak" data-once>
								<?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit">Tolak</button>
							</form>
						<?php elseif ($can_verify && $finding->status === 'verified'): ?>
							<form class="d-inline" method="post" action="<?= $base ?>/temuan/<?= e($finding->public_id) ?>/koreksi" data-once>
								<?= csrf_field() ?><button class="btn btn-sm btn-outline-primary" type="submit">Koreksi master</button>
							</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($findings)): ?><tr><td colspan="5" class="text-muted">Belum ada temuan.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php if ($can_perform && $session->status === 'published'): ?>
	<div class="card-body border-top">
		<h3 class="h6">Catat temuan</h3>
		<form method="post" action="<?= $base ?>/temuan" data-once>
			<?= csrf_field() ?>
			<div class="form-row">
				<div class="col-md-3"><?= ui_select(array('name' => 'target_id', 'label' => 'Unit', 'options' => $target_options, 'required' => TRUE)) ?></div>
				<div class="col-md-3"><?= ui_select(array('name' => 'existence_result', 'label' => 'Keberadaan', 'options' => $existence, 'required' => TRUE)) ?></div>
				<div class="col-md-2"><?= ui_select(array('name' => 'observed_condition', 'label' => 'Kondisi', 'options' => $conditions, 'required' => TRUE)) ?></div>
				<div class="col-md-4"><?= ui_select(array('name' => 'observed_location_id', 'label' => 'Lokasi ditemukan', 'options' => $location_options)) ?></div>
			</div>
			<?= ui_input(array('name' => 'note', 'label' => 'Catatan', 'maxlength' => 600)) ?>
			<button class="btn btn-outline-primary" type="submit">Simpan temuan</button>
		</form>
	</div>
	<?php endif; ?>
</div>

<?php if ($can_verify): ?>
<div class="card shadow-sm">
	<div class="card-header"><h2 class="h6 mb-0">Penutupan dan addendum</h2></div>
	<div class="card-body">
		<?php if ($session->status === 'published'): ?>
		<form class="mb-3" method="post" action="<?= $base ?>/tutup" data-once>
			<?= csrf_field() ?>
			<button class="btn btn-outline-secondary" type="submit">Tutup sesi audit</button>
			<span class="small text-muted ml-2">Seluruh temuan harus sudah diverifikasi lebih dulu.</span>
		</form>
		<?php endif; ?>

		<?php if ($session->status === 'closed'): ?>
		<form class="form-inline" method="post" action="<?= $base ?>/addendum" data-once>
			<?= csrf_field() ?>
			<input class="form-control mr-2" type="text" name="reason" maxlength="600" placeholder="Alasan addendum" aria-label="Alasan addendum" required>
			<button class="btn btn-outline-primary" type="submit">Tambah addendum</button>
		</form>
		<?php endif; ?>

		<?php if ($addenda): ?>
		<ul class="mt-3 mb-0">
			<?php foreach ($addenda as $addendum): ?>
				<li><?= e(format_wib($addendum->created_at, 'short')) ?> &mdash; <?= e($addendum->reason) ?></li>
			<?php endforeach; ?>
		</ul>
		<?php endif; ?>
	</div>
</div>
<?php endif; ?>
