<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Inventaris Desa: satu daftar barang, QR per barang, cetak label. */
$has_filter = $filters['q'] !== '' || $filters['kategori'] || $filters['lokasi'] || $filters['kondisi'] !== '' || $filters['status'] !== '';
$cards = array(
	array('Barang tercatat', $summary['total'], 'primary', 'fa-boxes'),
	array('Kondisi baik', $summary['good'], 'success', 'fa-check-circle'),
	array('Rusak ringan', $summary['minor_damage'], 'warning', 'fa-exclamation-triangle'),
	array('Rusak berat / hilang', $summary['major_or_lost'], 'danger', 'fa-times-circle'),
);
?>
<div class="page-heading">
	<div>
		<h1>Inventaris Desa</h1>
		<p>Setiap barang punya kode dan QR sendiri. Klik nama barang untuk melihat QR, riwayat, dan mencatat perubahan.</p>
	</div>
	<div class="page-actions asset-actions">
		<?php if ($can_labels && ! empty($items)): ?>
		<form method="post" action="<?= site_url('admin/inventaris/cetak-qr') ?>" target="_blank">
			<?= csrf_field() ?>
			<?php foreach (array('q', 'kategori', 'lokasi', 'kondisi', 'status') as $f): ?><input type="hidden" name="<?= $f ?>" value="<?= e((string) $filters[$f]) ?>"><?php endforeach; ?>
			<button class="btn btn-outline-primary" type="submit"><i class="fas fa-print mr-1" aria-hidden="true"></i> Cetak <?= $has_filter ? 'QR hasil filter' : 'semua QR' ?></button>
		</form>
		<?php endif; ?>
		<?php if ($can_create): ?>
		<a class="btn btn-primary btn-add" href="<?= site_url('admin/inventaris/tambah') ?>"><i class="fas fa-plus mr-1" aria-hidden="true"></i> Tambah Barang</a>
		<?php endif; ?>
	</div>
</div>

<div class="row">
	<?php foreach ($cards as $c): ?>
	<div class="col-6 col-xl-3 mb-4">
		<div class="card border-left-<?= $c[2] ?> shadow-sm h-100 py-2">
			<div class="card-body d-flex align-items-center justify-content-between">
				<div>
					<div class="text-xs font-weight-bold text-<?= $c[2] ?> text-uppercase mb-1"><?= e($c[0]) ?></div>
					<div class="h4 mb-0 font-weight-bold text-gray-800"><?= (int) $c[1] ?></div>
				</div>
				<i class="fas <?= $c[3] ?> fa-2x text-gray-300" aria-hidden="true"></i>
			</div>
		</div>
	</div>
	<?php endforeach; ?>
</div>

<div class="card shadow-sm mb-4">
	<div class="card-body py-3">
		<form method="get" action="<?= site_url('admin/inventaris') ?>" class="form-row align-items-end">
			<div class="col-md-3 mb-2">
				<label for="f-q" class="small font-weight-bold">Cari</label>
				<input class="form-control" type="search" id="f-q" name="q" maxlength="80" value="<?= e($filters['q']) ?>" placeholder="Nama, kode, merk">
			</div>
			<div class="col-md-2 mb-2">
				<label for="f-kategori" class="small font-weight-bold">Kategori</label>
				<select class="custom-select" id="f-kategori" name="kategori">
					<option value="">Semua</option>
					<?php foreach ($categories as $id => $name): ?><option value="<?= e($id) ?>" <?= (string) $filters['kategori'] === (string) $id ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-2 mb-2">
				<label for="f-lokasi" class="small font-weight-bold">Lokasi</label>
				<select class="custom-select" id="f-lokasi" name="lokasi">
					<option value="">Semua</option>
					<?php foreach ($locations as $id => $name): ?><option value="<?= e($id) ?>" <?= (string) $filters['lokasi'] === (string) $id ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-2 mb-2">
				<label for="f-kondisi" class="small font-weight-bold">Kondisi</label>
				<select class="custom-select" id="f-kondisi" name="kondisi">
					<option value="">Semua</option>
					<?php foreach (InventoryService::CONDITIONS as $code => $label): ?><option value="<?= e($code) ?>" <?= $filters['kondisi'] === $code ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-2 mb-2">
				<label for="f-status" class="small font-weight-bold">Status</label>
				<select class="custom-select" id="f-status" name="status">
					<option value="">Semua kecuali dihapuskan</option>
					<?php foreach (InventoryService::STATUSES as $code => $label): ?><option value="<?= e($code) ?>" <?= $filters['status'] === $code ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
					<option value="semua" <?= $filters['status'] === 'semua' ? 'selected' : '' ?>>Semua status</option>
				</select>
			</div>
			<div class="col-md-1 mb-2 d-flex">
				<button class="btn btn-primary btn-block" type="submit">Saring</button>
			</div>
			<?php if ($has_filter): ?><div class="col-12"><a class="small" href="<?= site_url('admin/inventaris') ?>">Hapus filter</a></div><?php endif; ?>
		</form>
	</div>
</div>

<div class="card shadow-sm">
	<div class="card-body">
		<?php if (empty($items)): ?>
			<div class="empty-box"><i class="fas fa-boxes" aria-hidden="true"></i>
				<p class="mb-2"><?= $has_filter ? 'Tidak ada barang yang cocok dengan filter.' : 'Belum ada barang di inventaris.' ?></p>
				<?php if ($can_create && ! $has_filter): ?><a class="btn btn-primary btn-sm" href="<?= site_url('admin/inventaris/tambah') ?>">Tambah barang pertama</a><?php endif; ?>
			</div>
		<?php else: ?>
		<div class="table-responsive">
			<table class="table table-hover mb-0" data-local-table data-order-col="0">
				<caption class="sr-only">Daftar barang inventaris desa</caption>
				<thead><tr>
					<th scope="col">Kode</th><th scope="col">Nama barang</th><th scope="col">Kategori</th><th scope="col">Lokasi</th>
					<th scope="col">Kondisi</th><th scope="col" class="text-center" data-orderable="false">QR</th><th scope="col" class="text-right" data-orderable="false">Aksi</th>
				</tr></thead>
				<tbody>
				<?php foreach ($items as $it): $url = site_url('admin/inventaris/'.rawurlencode($it->public_id)); $qr = $qr_urls[$it->id]; ?>
					<tr>
						<td class="text-nowrap"><a href="<?= $url ?>"><code><?= e($it->asset_tag) ?></code></a></td>
						<td>
							<a class="font-weight-bold" href="<?= $url ?>"><?= e($it->name) ?></a>
							<?php if (trim($it->brand.' '.$it->model) !== ''): ?><div class="small text-muted"><?= e(trim($it->brand.' '.$it->model)) ?></div><?php endif; ?>
							<?php if ($it->lifecycle_status !== 'active'): ?><?= AssetService::badge('lifecycle', $it->lifecycle_status) ?><?php endif; ?>
							<?php if ((int) $it->on_loan > 0): ?><span class="badge badge-pill badge-info asset-badge">Dipinjam</span><?php endif; ?>
						</td>
						<td class="small"><?= e($it->category_name) ?></td>
						<td class="small"><?= $it->location_name ? e($it->location_name) : '<span class="text-muted">—</span>' ?></td>
						<td data-order="<?= e($it->condition_status) ?>"><?= AssetService::badge('condition', $it->condition_status) ?></td>
						<td class="text-center">
							<?php if ($qr): ?>
								<button type="button" class="btn btn-sm btn-outline-primary" data-qr-show="<?= e($qr) ?>" data-qr-code="<?= e($it->asset_tag) ?>" data-qr-name="<?= e($it->name) ?>" data-qr-detail="<?= $url ?>" data-qr-unit="<?= e($it->public_id) ?>" title="Lihat QR <?= e($it->asset_tag) ?>"><i class="fas fa-qrcode" aria-hidden="true"></i><span class="sr-only">Lihat QR <?= e($it->asset_tag) ?></span></button>
							<?php else: ?>
								<a class="small" href="<?= $url ?>">Buat QR</a>
							<?php endif; ?>
						</td>
						<td class="text-right text-nowrap">
							<a class="btn btn-sm btn-outline-secondary" href="<?= $url ?>" title="Detail dan riwayat"><i class="fas fa-eye" aria-hidden="true"></i><span class="sr-only">Detail</span></a>
							<?php if ($can_edit): ?><a class="btn btn-sm btn-outline-secondary" href="<?= $url ?>/ubah" title="Ubah"><i class="fas fa-pen" aria-hidden="true"></i><span class="sr-only">Ubah</span></a><?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>
	</div>
</div>

<div class="modal fade" id="qr-modal" tabindex="-1" role="dialog" aria-labelledby="qr-modal-title" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-sm" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h2 class="modal-title h6" id="qr-modal-title">QR barang</h2>
				<button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body text-center">
				<div class="qr-box mx-auto mb-2" id="qr-modal-box" role="img" aria-label="Kode QR"></div>
				<div class="font-weight-bold" id="qr-modal-code"></div>
				<div class="small text-muted mb-3" id="qr-modal-name"></div>
				<div class="d-flex flex-wrap justify-content-center asset-actions">
					<button class="btn btn-outline-primary btn-sm" type="button" data-qr-download="" data-qr-source="#qr-modal-box"><i class="fas fa-download fa-sm mr-1" aria-hidden="true"></i> Unduh PNG</button>
					<?php if ($can_labels): ?>
					<form method="post" action="<?= site_url('admin/inventaris/cetak-qr') ?>" target="_blank">
						<?= csrf_field() ?>
						<input type="hidden" name="barang" id="qr-modal-unit" value="">
						<button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-print fa-sm mr-1" aria-hidden="true"></i> Cetak label</button>
					</form>
					<?php endif; ?>
					<a class="btn btn-outline-secondary btn-sm" id="qr-modal-detail" href="#">Detail</a>
				</div>
			</div>
		</div>
	</div>
</div>

<p class="small text-muted mt-3 mb-0"><i class="fas fa-sliders-h fa-sm mr-1" aria-hidden="true"></i> Perlu mengelola kategori, lokasi, atau register aset secara rinci? Buka <a href="<?= site_url('admin/aset?lanjutan=1') ?>">mode lanjutan aset</a>.</p>
