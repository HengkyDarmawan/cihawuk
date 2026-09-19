<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Satu barang persediaan: saldo per lokasi, konversi satuan, dan kartu stok. */
$base = site_url('admin/gudang/barang/'.rawurlencode($item->public_id));
$qty = function ($value) { return rtrim(rtrim(number_format((float) $value, 3, ',', '.'), '0'), ','); };
?>
<div class="page-heading">
	<div>
		<h1><?= e($item->name) ?></h1>
		<p><code><?= e($item->sku) ?></code> · satuan dasar <?= e($item->base_unit) ?>
			<?= $item->minimum_stock === NULL ? '' : '· minimum '.e($qty($item->minimum_stock)) ?></p>
	</div>
	<div><a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/gudang') ?>">Kembali</a></div>
</div>

<?= ui_error_summary($this->form_errors) ?>

<div class="row g-4 mb-4">
	<div class="col-lg-6">
		<div class="card shadow-sm h-100">
			<div class="card-header"><h2 class="h6 mb-0">Saldo per lokasi</h2></div>
			<ul class="list-group list-group-flush">
				<?php foreach ($balances as $balance): ?>
					<li class="list-group-item d-flex justify-content-between">
						<span><?= e($balance->name) ?></span>
						<span><?= e($qty($balance->saldo)) ?> <?= e($item->base_unit) ?></span>
					</li>
				<?php endforeach; ?>
				<?php if (empty($balances)): ?><li class="list-group-item text-muted">Belum ada saldo.</li><?php endif; ?>
			</ul>
		</div>
	</div>
	<div class="col-lg-6">
		<div class="card shadow-sm h-100">
			<div class="card-header"><h2 class="h6 mb-0">Konversi satuan</h2></div>
			<ul class="list-group list-group-flush">
				<?php foreach ($conversions as $conversion): ?>
					<li class="list-group-item">1 <?= e($conversion->from_unit) ?> =
						<?= (int) $conversion->numerator ?><?= (int) $conversion->denominator === 1 ? '' : '/'.(int) $conversion->denominator ?>
						<?= e($conversion->to_unit) ?></li>
				<?php endforeach; ?>
				<?php if (empty($conversions)): ?><li class="list-group-item text-muted">Belum ada konversi.</li><?php endif; ?>
			</ul>
			<?php if ($can_edit): ?>
			<div class="card-body border-top">
				<form class="form-inline" method="post" action="<?= $base ?>/konversi" data-once>
					<?= csrf_field() ?>
					<input class="form-control form-control-sm mr-1" type="text" name="from_unit" maxlength="30" placeholder="Satuan asal" aria-label="Satuan asal" required>
					<input class="form-control form-control-sm mr-1" type="number" name="numerator" min="1" placeholder="Numerator" aria-label="Numerator" required>
					<input class="form-control form-control-sm mr-1" type="number" name="denominator" min="1" value="1" aria-label="Denominator">
					<button class="btn btn-sm btn-outline-primary" type="submit">Simpan</button>
				</form>
				<p class="small text-muted mt-2 mb-0">Numerator dan denominator harus bilangan bulat positif; konversi yang menghasilkan pecahan ditolak.</p>
			</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<div class="card shadow-sm">
	<div class="card-header"><h2 class="h6 mb-0">Kartu stok</h2></div>
	<div class="card-body border-bottom">
		<form class="form-inline" method="get" action="<?= $base ?>">
			<label class="mr-2" for="lokasi">Lokasi</label>
			<select class="form-select mr-2" id="lokasi" name="lokasi">
				<option value="">— pilih —</option>
				<?php foreach ($locations as $location): ?>
					<option value="<?= (int) $location->id ?>" <?= $selected_location === (int) $location->id ? 'selected' : '' ?>><?= e($location->name) ?></option>
				<?php endforeach; ?>
			</select>
			<button class="btn btn-outline-primary btn-sm" type="submit">Tampilkan</button>
		</form>
	</div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Waktu</th><th scope="col">Jenis</th><th scope="col" class="num">Pergerakan</th><th scope="col">Rujukan</th></tr></thead>
			<tbody>
			<?php foreach ($card as $row): ?>
				<tr>
					<th scope="row"><?= e(format_wib($row->posted_at, 'short')) ?></th>
					<td><?= e($types[$row->transaction_type] ?? $row->transaction_type) ?></td>
					<td class="num"><?= (float) $row->quantity_delta > 0 ? '+' : '' ?><?= e($qty($row->quantity_delta)) ?></td>
					<td class="small"><?= e($row->reference_no ?: substr($row->transaction_public_id, 0, 8)) ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($card)): ?><tr><td colspan="4" class="text-muted">Pilih lokasi untuk melihat kartu stok.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
