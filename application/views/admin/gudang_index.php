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
	<div class="card-header card-header-actions">
		<h2 class="h6 mb-0">Barang</h2>
		<?php if ($can_receive): ?><div class="d-flex flex-wrap" style="gap:8px"><?= ui_add_button('modal-tambah-barang', 'Tambah barang') ?><?= ui_add_button('modal-tambah-lokasi', 'Tambah lokasi', 'btn-outline-primary') ?></div><?php endif; ?>
	</div>
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
</div>

<div class="card shadow-sm mb-4">
	<div class="card-header card-header-actions">
		<h2 class="h6 mb-0">Transaksi terakhir</h2>
		<?php if ($can_receive): ?><?= ui_add_button('modal-buat-transaksi', 'Buat transaksi') ?><?php endif; ?>
	</div>
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
</div>

<div class="card shadow-sm">
	<div class="card-header card-header-actions">
		<h2 class="h6 mb-0">Stock opname</h2>
		<?php if ($can_stocktake && $location_options): ?><?= ui_add_button('modal-buka-opname', 'Buka opname') ?><?php endif; ?>
	</div>
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
</div>

<?php if ($can_receive): ?>
<?= ui_modal_open('modal-tambah-barang', 'Tambah barang') ?>
	<form method="post" action="<?= site_url('admin/gudang/barang') ?>" data-once>
		<?= csrf_field() ?>
		<?= ui_input(array('name' => 'sku', 'label' => 'SKU', 'maxlength' => 60, 'required' => TRUE)) ?>
		<?= ui_input(array('name' => 'name', 'id' => 'item_name', 'label' => 'Nama barang', 'maxlength' => 200, 'required' => TRUE)) ?>
		<div class="form-row">
			<div class="col-sm-6"><?= ui_input(array('name' => 'base_unit', 'label' => 'Satuan dasar', 'maxlength' => 30, 'required' => TRUE)) ?></div>
			<div class="col-sm-6"><?= ui_input(array('name' => 'minimum_stock', 'label' => 'Stok minimum')) ?></div>
		</div>
		<div class="form-check">
			<input class="form-check-input" type="checkbox" id="track_batch" name="track_batch" value="1">
			<label class="form-check-label" for="track_batch">Pakai batch</label>
		</div>
		<?= ui_modal_actions('Simpan barang') ?>
	</form>
<?= ui_modal_close() ?>

<?= ui_modal_open('modal-tambah-lokasi', 'Tambah lokasi gudang') ?>
	<form method="post" action="<?= site_url('admin/gudang/lokasi') ?>" data-once>
		<?= csrf_field() ?>
		<?= ui_input(array('name' => 'code', 'id' => 'location_code', 'label' => 'Kode lokasi', 'maxlength' => 40, 'required' => TRUE)) ?>
		<?= ui_input(array('name' => 'name', 'id' => 'location_name', 'label' => 'Nama lokasi', 'maxlength' => 180, 'required' => TRUE)) ?>
		<?= ui_modal_actions('Simpan lokasi') ?>
	</form>
<?= ui_modal_close() ?>

<?= ui_modal_open('modal-buat-transaksi', 'Buat transaksi') ?>
	<form method="post" action="<?= site_url('admin/gudang/transaksi') ?>" data-once>
		<?= csrf_field() ?>
		<?= ui_select(array('name' => 'transaction_type', 'label' => 'Jenis', 'options' => $types, 'required' => TRUE)) ?>
		<div class="form-row">
			<div class="col-sm-6"><?= ui_select(array('name' => 'from_location_id', 'label' => 'Lokasi asal', 'options' => array('' => '—') + $location_options)) ?></div>
			<div class="col-sm-6"><?= ui_select(array('name' => 'to_location_id', 'label' => 'Lokasi tujuan', 'options' => array('' => '—') + $location_options)) ?></div>
		</div>
		<?= ui_input(array('name' => 'reference_no', 'label' => 'Nomor rujukan', 'maxlength' => 60)) ?>
		<p class="small text-muted mb-0">Transaksi dibuat sebagai draft; baris barang diisi di halaman berikutnya.</p>
		<?= ui_modal_actions('Buat draft transaksi') ?>
	</form>
<?= ui_modal_close() ?>
<?php endif; ?>

<?php if ($can_stocktake && $location_options): ?>
<?= ui_modal_open('modal-buka-opname', 'Buka stock opname') ?>
	<form method="post" action="<?= site_url('admin/gudang/opname') ?>" data-once>
		<?= csrf_field() ?>
		<?= ui_select(array('name' => 'location_id', 'id' => 'stocktake_location', 'label' => 'Lokasi opname', 'options' => $location_options, 'required' => TRUE)) ?>
		<?= ui_input(array('name' => 'name', 'id' => 'stocktake_name', 'label' => 'Nama opname', 'maxlength' => 200, 'required' => TRUE)) ?>
		<?= ui_modal_actions('Buka opname') ?>
	</form>
<?= ui_modal_close() ?>
<?php endif; ?>
