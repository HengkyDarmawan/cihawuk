<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Satu transaksi gudang beserta barisnya. */
$base = site_url('admin/gudang/transaksi/'.rawurlencode($transaction->public_id));
$item_options = array();
foreach ($items as $item) { $item_options[$item->public_id] = $item->sku.' — '.$item->name.' ('.$item->base_unit.')'; }
$qty = function ($value) { return rtrim(rtrim(number_format((float) $value, 3, ',', '.'), '0'), ','); };
$is_adjustment = ($transaction->transaction_type === 'adjustment');
?>
<div class="page-heading">
	<div>
		<h1><?= e($types[$transaction->transaction_type] ?? $transaction->transaction_type) ?></h1>
		<p>Status: <?= e($statuses[$transaction->status] ?? $transaction->status) ?>
			<?= $transaction->reference_no ? '· rujukan '.e($transaction->reference_no) : '' ?></p>
	</div>
	<div><a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/gudang') ?>">Kembali</a></div>
</div>

<?= ui_error_summary($this->form_errors) ?>

<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Baris barang</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">SKU</th><th scope="col">Nama</th><th scope="col" class="num">Jumlah masuk</th><th scope="col" class="num">Jumlah satuan dasar</th><th scope="col">Catatan</th></tr></thead>
			<tbody>
			<?php foreach ($lines as $line): ?>
				<tr>
					<th scope="row"><code><?= e($line->sku) ?></code></th>
					<td><?= e($line->name) ?></td>
					<td class="num"><?= $line->input_quantity === NULL ? '&mdash;' : e($qty($line->input_quantity).' '.$line->input_unit) ?></td>
					<td class="num"><?= e($qty($line->quantity_base).' '.$line->base_unit) ?></td>
					<td class="small text-muted"><?= e($line->note ?: '—') ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($lines)): ?><tr><td colspan="5" class="text-muted">Belum ada baris.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php if ($can_edit && $transaction->status !== 'posted'): ?>
	<div class="card-body border-top">
		<form method="post" action="<?= $base ?>/baris" data-once>
			<?= csrf_field() ?>
			<div class="form-row">
				<div class="col-md-5"><?= ui_select(array('name' => 'item_public_id', 'label' => 'Barang', 'options' => $item_options, 'required' => TRUE)) ?></div>
				<div class="col-md-2"><?= ui_input(array('name' => 'quantity', 'label' => $is_adjustment ? 'Selisih (boleh minus)' : 'Jumlah', 'required' => TRUE)) ?></div>
				<?php if ( ! $is_adjustment): ?>
				<div class="col-md-2"><?= ui_input(array('name' => 'input_unit', 'label' => 'Satuan', 'maxlength' => 30,
					'help' => 'Kosongkan untuk satuan dasar.')) ?></div>
				<div class="col-md-3"><?= ui_input(array('name' => 'batch_no', 'label' => 'Nomor batch', 'maxlength' => 60)) ?></div>
				<?php else: ?>
				<div class="col-md-5"><?= ui_input(array('name' => 'note', 'label' => 'Alasan penyesuaian', 'maxlength' => 255, 'required' => TRUE)) ?></div>
				<?php endif; ?>
			</div>
			<button class="btn btn-outline-primary" type="submit">Tambah baris</button>
		</form>
	</div>
	<?php endif; ?>
</div>

<?php if ($can_edit && $transaction->status !== 'posted'): ?>
<form class="card shadow-sm" method="post" action="<?= $base ?>/posting" data-once>
	<div class="card-body">
		<?= csrf_field() ?>
		<p class="mb-2">Posting menulis ledger dan mengunci barisnya. Transaksi yang sudah diposting tidak dapat diubah.</p>
		<button class="btn btn-primary" type="submit">Posting transaksi</button>
	</div>
</form>
<?php endif; ?>

<?php if ($transaction->status === 'posted'): ?>
<p class="small text-muted">Diposting <?= e(format_wib($transaction->posted_at, 'short')) ?>. Koreksi dilakukan lewat transaksi penyesuaian baru.</p>
<?php endif; ?>
