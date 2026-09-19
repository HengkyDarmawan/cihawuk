<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Satu sesi stock opname; selisih dihitung server, bukan diketik petugas. */
$base = site_url('admin/gudang/opname/'.rawurlencode($stocktake->public_id));
$qty = function ($value) { return $value === NULL ? '—' : rtrim(rtrim(number_format((float) $value, 3, ',', '.'), '0'), ','); };
$pending = 0;
foreach ($lines as $line) { if ($line->counted_quantity_base === NULL) { $pending++; } }
?>
<div class="page-heading">
	<div>
		<h1><?= e($stocktake->name) ?></h1>
		<p>Status: <?= e($stocktake->status) ?> · saldo dibekukan <?= e(format_wib($stocktake->snapshot_at, 'short')) ?></p>
	</div>
	<div><a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/gudang') ?>">Kembali</a></div>
</div>

<?= ui_error_summary($this->form_errors) ?>

<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Daftar hitung</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">SKU</th><th scope="col">Nama</th><th scope="col" class="num">Seharusnya</th><th scope="col" class="num">Dihitung</th><th scope="col" class="num">Selisih</th><th scope="col">Aksi</th></tr></thead>
			<tbody>
			<?php foreach ($lines as $line): ?>
				<tr>
					<th scope="row"><code><?= e($line->sku) ?></code></th>
					<td><?= e($line->name) ?></td>
					<td class="num"><?= e($qty($line->expected_quantity_base)) ?></td>
					<td class="num"><?= e($qty($line->counted_quantity_base)) ?></td>
					<td class="num"><?= e($qty($line->variance_quantity_base)) ?></td>
					<td>
						<?php if ($can_count && $stocktake->status === 'open'): ?>
						<form class="form-inline" method="post" action="<?= $base ?>/hitung" data-once>
							<?= csrf_field() ?>
							<input type="hidden" name="line_id" value="<?= (int) $line->id ?>">
							<input class="form-control form-control-sm mr-1" type="text" name="counted" placeholder="Jumlah" aria-label="Jumlah hasil hitung" required>
							<button class="btn btn-sm btn-outline-secondary" type="submit">Simpan</button>
						</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($lines)): ?><tr><td colspan="6" class="text-muted">Tidak ada barang bersaldo pada lokasi ini.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<?php if ($can_count && $stocktake->status === 'open'): ?>
<form class="card shadow-sm" method="post" action="<?= $base ?>/tutup" data-once>
	<div class="card-body">
		<?= csrf_field() ?>
		<?php if ($pending > 0): ?>
			<p class="mb-2 text-muted">Masih ada <?= (int) $pending ?> barang yang belum dihitung.</p>
		<?php endif; ?>
		<p class="mb-2">Menutup opname membuat transaksi penyesuaian dari selisih yang sudah dihitung.</p>
		<button class="btn btn-primary" type="submit" <?= $pending > 0 ? 'disabled' : '' ?>>Tutup opname</button>
	</div>
</form>
<?php endif; ?>
