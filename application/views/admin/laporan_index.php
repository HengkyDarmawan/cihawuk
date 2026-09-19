<?php defined('BASEPATH') OR exit('No direct script access allowed');
$presets = array(
	'submitted' => array('status' => 'submitted'),
	'unassigned' => array('status' => 'verifying', 'unassigned' => 1),
	'needs_information' => array('status' => 'needs_information'),
	'mine' => array('mine' => 1),
	'overdue' => array('overdue' => 1),
);
$active = $presets[$preset] ?? array();
?>
<div class="page-heading">
	<div>
		<h1>Daftar laporan</h1>
		<p>Hanya laporan dalam lingkup akses Anda yang ditampilkan.</p>
	</div>
	<?php if (in_array('tickets.create_on_behalf', $permissions, TRUE)): ?>
		<a class="btn btn-primary" href="<?= site_url('admin/laporan/buat') ?>"><i class="fas fa-edit mr-1" aria-hidden="true"></i> Input loket</a>
	<?php endif; ?>
</div>

<div class="card shadow-sm mb-4">
	<div class="card-body">
		<div class="form-row">
			<div class="col-md-3 mb-2">
				<label for="flt-status">Status</label>
				<select class="form-control" id="flt-status" data-dt-filter="status">
					<option value="">Semua status</option>
					<?php foreach ($statuses as $code => $meta): ?>
						<option value="<?= e($code) ?>" <?= ($active['status'] ?? '') === $code ? 'selected' : '' ?>><?= e($meta['staff']) ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-3 mb-2">
				<label for="flt-type">Jenis</label>
				<select class="form-control" id="flt-type" data-dt-filter="report_type">
					<option value="">Semua jenis</option>
					<?php foreach ($report_types as $code => $label): ?><option value="<?= e($code) ?>"><?= e($label) ?></option><?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-3 mb-2">
				<label for="flt-category">Kategori</label>
				<select class="form-control" id="flt-category" data-dt-filter="category_id">
					<option value="">Semua kategori</option>
					<?php foreach ($categories as $cat): ?><option value="<?= (int) $cat->id ?>"><?= e($cat->name) ?></option><?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-3 mb-2">
				<label for="flt-channel">Kanal</label>
				<select class="form-control" id="flt-channel" data-dt-filter="channel">
					<option value="">Semua kanal</option>
					<?php foreach ($channels as $code => $label): ?><option value="<?= e($code) ?>"><?= e($label) ?></option><?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-3 mb-2">
				<label for="flt-from">Dari tanggal</label>
				<input class="form-control" type="date" id="flt-from" data-dt-filter="from">
			</div>
			<div class="col-md-3 mb-2">
				<label for="flt-to">Sampai tanggal</label>
				<input class="form-control" type="date" id="flt-to" data-dt-filter="to">
			</div>
			<div class="col-md-3 mb-2">
				<label for="flt-priority">Prioritas internal</label>
				<select class="form-control" id="flt-priority" data-dt-filter="priority">
					<option value="">Semua prioritas</option>
					<?php foreach ($priorities as $code => $label): ?><option value="<?= e($code) ?>"><?= e($label) ?></option><?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-3 mb-2 d-flex align-items-end">
				<div class="custom-control custom-checkbox mr-3">
					<input class="custom-control-input" type="checkbox" id="flt-overdue" value="1" data-dt-filter="overdue" <?= ! empty($active['overdue']) ? 'checked' : '' ?>>
					<label class="custom-control-label" for="flt-overdue">Terlambat</label>
				</div>
				<div class="custom-control custom-checkbox">
					<input class="custom-control-input" type="checkbox" id="flt-mine" value="1" data-dt-filter="mine" <?= ! empty($active['mine']) ? 'checked' : '' ?>>
					<label class="custom-control-label" for="flt-mine">Tugas saya</label>
				</div>
			</div>
		</div>
		<input type="hidden" id="flt-unassigned" data-dt-filter="unassigned" value="<?= ! empty($active['unassigned']) ? '1' : '' ?>">
	</div>
</div>

<div class="card shadow-sm">
	<div class="card-body">
		<div class="table-responsive">
			<table class="table table-hover w-100" data-datatable data-url="<?= site_url('admin/laporan/data') ?>" data-order-col="5" data-order-dir="desc"
				data-filters='<?= e(json_encode(array('q' => $keyword))) ?>'>
				<caption class="sr-only">Daftar laporan dalam lingkup akses Anda</caption>
				<thead>
					<tr>
						<th scope="col">Nomor</th>
						<th scope="col">Judul</th>
						<th scope="col">Jenis</th>
						<th scope="col">Status</th>
						<th scope="col">Penanggung jawab</th>
						<th scope="col">Diterima</th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
		</div>
	</div>
</div>
