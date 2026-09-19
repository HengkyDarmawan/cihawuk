<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Ringkasan gudang: barang, saldo, transaksi terakhir, dan opname. */
$location_options = array();
foreach ($locations as $location) { $location_options[(string) $location->id] = $location->name; }
$qty = function ($value) { return rtrim(rtrim(number_format((float) $value, 3, ',', '.'), '0'), ','); };
?>
<div class="page-heading">
	<div>
		<h1>Gudang Persediaan</h1>
		<p>Saldo dihitung dari ledger yang hanya bertambah, bukan dari kolom yang dapat disunting.</p>
	</div>
</div>

<?= ui_error_summary($this->form_errors) ?>

<?php if ($below_minimum): ?>
<div class="alert alert-warning" role="status">
	<strong>Di bawah stok minimum:</strong>
	<?php $names = array(); foreach ($below_minimum as $row) { $names[] = $row->name.' ('.$qty($row->total_balance).' '.$row->base_unit.')'; } ?>
	<?= e(implode('; ', $names)) ?>.
</div>
<?php endif; ?>

<form class="filter-bar mb-4" method="get" action="<?= site_url('admin/gudang') ?>">
	<div>
		<label class="form-label" for="q">Cari barang</label>
		<input class="form-control" type="search" id="q" name="q" value="<?= e($filters['q']) ?>" maxlength="60">
	</div>
	<div><button class="btn btn-outline-primary" type="submit">Cari</button></div>
</form>

<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Barang</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">SKU</th><th scope="col">Nama</th><th scope="col">Satuan dasar</th><th scope="col">Saldo per lokasi</th><th scope="col" class="num">Minimum</th></tr></thead>
			<tbody>
			<?php foreach ($items as $item): ?>
				<tr>
					<th scope="row"><a href="<?= site_url('admin/gudang/barang/'.rawurlencode($item->public_id)) ?>"><code><?= e($item->sku) ?></code></a></th>
					<td><?= e($item->name) ?></td>
					<td><?= e($item->base_unit) ?></td>
					<td class="small">
						<?php if (empty($item->balances)): ?><span class="text-muted">kosong</span><?php endif; ?>
						<?php foreach ($item->balances as $balance): ?>
							<?= e($balance->name) ?>: <?= e($qty($balance->saldo)) ?><br>
						<?php endforeach; ?>
					</td>
					<td class="num"><?= $item->minimum_stock === NULL ? '&mdash;' : e($qty($item->minimum_stock)) ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($items)): ?><tr><td colspan="5" class="text-muted">Belum ada barang persediaan.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php if ($can_receive): ?>
	<div class="card-body border-top">
		<h3 class="h6">Tambah barang</h3>
		<form method="post" action="<?= site_url('admin/gudang/barang') ?>" data-once>
			<?= csrf_field() ?>
			<div class="form-row">
				<div class="col-md-2"><?= ui_input(array('name' => 'sku', 'label' => 'SKU', 'maxlength' => 60, 'required' => TRUE)) ?></div>
				<div class="col-md-4"><?= ui_input(array('name' => 'name', 'label' => 'Nama barang', 'maxlength' => 200, 'required' => TRUE)) ?></div>
				<div class="col-md-2"><?= ui_input(array('name' => 'base_unit', 'label' => 'Satuan dasar', 'maxlength' => 30, 'required' => TRUE)) ?></div>
				<div class="col-md-2"><?= ui_input(array('name' => 'minimum_stock', 'label' => 'Stok minimum')) ?></div>
				<div class="col-md-2 d-flex align-items-center">
					<div class="form-check mt-3">
						<input class="form-check-input" type="checkbox" id="track_batch" name="track_batch" value="1">
						<label class="form-check-label" for="track_batch">Pakai batch</label>
					</div>
				</div>
			</div>
			<button class="btn btn-primary" type="submit">Simpan barang</button>
		</form>

		<hr>
		<h3 class="h6">Tambah lokasi gudang</h3>
		<form class="form-inline" method="post" action="<?= site_url('admin/gudang/lokasi') ?>" data-once>
			<?= csrf_field() ?>
			<input class="form-control mr-2" type="text" name="code" maxlength="40" placeholder="Kode" aria-label="Kode lokasi" required>
			<input class="form-control mr-2" type="text" name="name" maxlength="180" placeholder="Nama lokasi" aria-label="Nama lokasi" required>
			<button class="btn btn-outline-primary" type="submit">Simpan lokasi</button>
		</form>
	</div>
	<?php endif; ?>
</div>

<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Transaksi terakhir</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Jenis</th><th scope="col">Asal</th><th scope="col">Tujuan</th><th scope="col">Status</th><th scope="col">Waktu</th></tr></thead>
			<tbody>
			<?php foreach ($transactions as $transaction): ?>
				<tr>
					<th scope="row"><a href="<?= site_url('admin/gudang/transaksi/'.rawurlencode($transaction->public_id)) ?>"><?= e($types[$transaction->transaction_type] ?? $transaction->transaction_type) ?></a></th>
					<td><?= e($transaction->from_name ?: '—') ?></td>
					<td><?= e($transaction->to_name ?: '—') ?></td>
					<td><?= e($statuses[$transaction->status] ?? $transaction->status) ?></td>
					<td><?= e(format_wib($transaction->transaction_at, 'short')) ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($transactions)): ?><tr><td colspan="5" class="text-muted">Belum ada transaksi.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php if ($can_receive): ?>
	<div class="card-body border-top">
		<h3 class="h6">Buat transaksi</h3>
		<form method="post" action="<?= site_url('admin/gudang/transaksi') ?>" data-once>
			<?= csrf_field() ?>
			<div class="form-row">
				<div class="col-md-3"><?= ui_select(array('name' => 'transaction_type', 'label' => 'Jenis', 'options' => $types, 'required' => TRUE)) ?></div>
				<div class="col-md-3"><?= ui_select(array('name' => 'from_location_id', 'label' => 'Lokasi asal', 'options' => array('' => '—') + $location_options)) ?></div>
				<div class="col-md-3"><?= ui_select(array('name' => 'to_location_id', 'label' => 'Lokasi tujuan', 'options' => array('' => '—') + $location_options)) ?></div>
				<div class="col-md-3"><?= ui_input(array('name' => 'reference_no', 'label' => 'Nomor rujukan', 'maxlength' => 60)) ?></div>
			</div>
			<button class="btn btn-outline-primary" type="submit">Buat draft transaksi</button>
		</form>
	</div>
	<?php endif; ?>
</div>

<div class="card shadow-sm">
	<div class="card-header"><h2 class="h6 mb-0">Stock opname</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Nama</th><th scope="col">Lokasi</th><th scope="col">Status</th><th scope="col">Dibekukan</th></tr></thead>
			<tbody>
			<?php foreach ($stocktakes as $stocktake): ?>
				<tr>
					<th scope="row"><a href="<?= site_url('admin/gudang/opname/'.rawurlencode($stocktake->public_id)) ?>"><?= e($stocktake->name) ?></a></th>
					<td><?= e($stocktake->location_name) ?></td>
					<td><?= e($stocktake->status) ?></td>
					<td><?= e(format_wib($stocktake->snapshot_at, 'short')) ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($stocktakes)): ?><tr><td colspan="4" class="text-muted">Belum ada opname.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php if ($can_stocktake && $location_options): ?>
	<div class="card-body border-top">
		<form class="form-inline" method="post" action="<?= site_url('admin/gudang/opname') ?>" data-once>
			<?= csrf_field() ?>
			<select class="form-select mr-2" name="location_id" aria-label="Lokasi opname" required>
				<?php foreach ($location_options as $id => $label): ?><option value="<?= e($id) ?>"><?= e($label) ?></option><?php endforeach; ?>
			</select>
			<input class="form-control mr-2" type="text" name="name" maxlength="200" placeholder="Nama opname" aria-label="Nama opname" required>
			<button class="btn btn-outline-primary" type="submit">Buka opname</button>
		</form>
	</div>
	<?php endif; ?>
</div>
