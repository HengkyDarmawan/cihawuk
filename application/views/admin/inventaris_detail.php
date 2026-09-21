<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Detail barang: data, QR, aksi cepat, dan riwayat. */
$base = site_url('admin/inventaris/'.rawurlencode($item->public_id));
$active = $item->lifecycle_status === 'active';
$retired = in_array($item->lifecycle_status, array('disposed', 'lost'), TRUE);
$conditions = InventoryService::CONDITIONS;
$modal = function ($id, $title, $action, $body, $submit, $confirm = NULL) {
	return '<div class="modal fade" id="'.$id.'" tabindex="-1" role="dialog" aria-labelledby="'.$id.'-title" aria-hidden="true">'
		.'<div class="modal-dialog modal-dialog-centered" role="document"><form class="modal-content" method="post" action="'.$action.'"'
		.($confirm ? ' data-confirm="'.e($confirm).'" data-confirm-ok="Ya, lanjutkan"' : ' data-once').'>'
		.csrf_field()
		.'<div class="modal-header"><h2 class="modal-title h6" id="'.$id.'-title">'.e($title).'</h2>'
		.'<button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button></div>'
		.'<div class="modal-body">'.$body.'</div>'
		.'<div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Batal</button>'
		.'<button type="submit" class="btn btn-primary">'.e($submit).'</button></div></form></div></div>';
};
$condition_select = function ($id, $selected) use ($conditions) {
	$html = '<div class="form-group"><label for="'.$id.'">Kondisi</label><select class="custom-select" id="'.$id.'" name="kondisi">';
	foreach ($conditions as $code => $label)
	{
		$html .= '<option value="'.e($code).'"'.($code === $selected ? ' selected' : '').'>'.e($label).'</option>';
	}
	return $html.'</select></div>';
};
$note = function ($id, $label = 'Catatan', $placeholder = '') {
	return '<div class="form-group mb-0"><label for="'.$id.'">'.e($label).' <span class="text-muted small">(opsional)</span></label>'
		.'<input class="form-control" type="text" id="'.$id.'" name="catatan" maxlength="500" placeholder="'.e($placeholder).'"></div>';
};
$type_class = array('created' => 'success', 'updated' => 'secondary', 'status' => 'warning', 'move' => 'info', 'loan' => 'primary',
	'return' => 'primary', 'repair' => 'warning', 'repair_done' => 'success', 'qr' => 'dark', 'audit' => 'success');
?>
<div class="page-heading">
	<div>
		<h1><?= e($item->name) ?></h1>
		<p><code><?= e($item->asset_tag) ?></code> · <?= e($item->category_name) ?>
			· <?= AssetService::badge('condition', $item->condition_status) ?> <?= AssetService::badge('lifecycle', $item->lifecycle_status) ?>
			<?php if ($loan): ?><span class="badge badge-pill badge-info asset-badge">Dipinjam</span><?php endif; ?></p>
	</div>
	<div class="d-flex flex-wrap asset-actions">
		<?php if ($can_edit): ?><a class="btn btn-outline-primary btn-sm" href="<?= $base ?>/ubah"><i class="fas fa-pen fa-sm mr-1" aria-hidden="true"></i> Ubah data</a><?php endif; ?>
		<a class="btn btn-outline-secondary btn-sm" href="<?= site_url('admin/inventaris') ?>"><i class="fas fa-arrow-left fa-sm mr-1" aria-hidden="true"></i> Daftar</a>
	</div>
</div>

<?php if ($item->lifecycle_status === 'draft'): ?>
<div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center">
	<span><strong>Barang ini belum aktif</strong> (data lama). Aktifkan supaya QR-nya dapat dipindai publik.</span>
	<?php if ($can_status): ?>
	<form method="post" action="<?= $base ?>/aktifkan" class="m-0" data-once><?= csrf_field() ?><button class="btn btn-warning btn-sm" type="submit">Aktifkan sekarang</button></form>
	<?php endif; ?>
</div>
<?php endif; ?>

<div class="row">
	<div class="col-lg-8 mb-4">
		<div class="card shadow-sm mb-4">
			<div class="card-body">
				<div class="row">
					<?php if ($media): ?>
					<div class="col-md-4 mb-3 mb-md-0"><img class="img-fluid rounded border" src="<?= e(media_url($media)) ?>" alt="<?= e($media->alt_text) ?>" loading="lazy"></div>
					<?php endif; ?>
					<div class="<?= $media ? 'col-md-8' : 'col-12' ?>">
						<dl class="dl-grid mb-0">
							<dt>Kode</dt><dd><code><?= e($item->asset_tag) ?></code></dd>
							<dt>Kategori</dt><dd><?= e($item->category_name) ?></dd>
							<dt>Merk / tipe</dt><dd><?= e(trim($item->brand.' '.$item->model) ?: '—') ?></dd>
							<dt>Lokasi</dt><dd><?= $item->location_name ? e($item->location_name) : '<span class="text-muted">belum ditentukan</span>' ?></dd>
							<dt>Tahun perolehan</dt><dd><?= $item->acquisition_year ? (int) $item->acquisition_year : '—' ?></dd>
							<?php if ($can_financial): ?>
							<dt>Sumber dana</dt><dd><?= e($item->acquisition_source ?: '—') ?></dd>
							<dt>Harga perolehan</dt><dd><?= $item->acquisition_value !== NULL ? 'Rp '.number_format((float) $item->acquisition_value, 0, ',', '.') : '—' ?></dd>
							<?php endif; ?>
							<?php if ($loan): ?><dt>Dipinjam</dt><dd>sejak <?= e(format_wib($loan->checked_out_at, 'date')) ?><?= $loan->due_at ? ', rencana kembali '.e(format_wib($loan->due_at, 'date')) : '' ?></dd><?php endif; ?>
							<?php if ($maintenance): ?><dt>Perbaikan</dt><dd><?= e($maintenance->complaint) ?></dd><?php endif; ?>
							<?php if ($item->register_description): ?><dt>Keterangan</dt><dd><?= nl2br(e($item->register_description)) ?></dd><?php endif; ?>
						</dl>
					</div>
				</div>
			</div>
			<?php if ( ! $retired && ($can_move || $can_maintain || $can_status)): ?>
			<div class="card-footer bg-white d-flex flex-wrap asset-actions">
				<?php if ($can_move): ?>
					<button type="button" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#m-pindah"><i class="fas fa-truck fa-sm mr-1" aria-hidden="true"></i> Pindah lokasi</button>
					<?php if ($loan): ?>
						<button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#m-kembali"><i class="fas fa-undo fa-sm mr-1" aria-hidden="true"></i> Catat pengembalian</button>
					<?php elseif ($active): ?>
						<button type="button" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#m-pinjam"><i class="fas fa-hand-holding fa-sm mr-1" aria-hidden="true"></i> Pinjamkan</button>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ($can_maintain): ?>
					<?php if ($maintenance): ?>
						<button type="button" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#m-selesai"><i class="fas fa-check fa-sm mr-1" aria-hidden="true"></i> Perbaikan selesai</button>
					<?php else: ?>
						<button type="button" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#m-perbaikan"><i class="fas fa-tools fa-sm mr-1" aria-hidden="true"></i> Catat perbaikan</button>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ($can_status): ?>
					<button type="button" class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#m-kondisi"><i class="fas fa-clipboard-check fa-sm mr-1" aria-hidden="true"></i> Ubah kondisi / status</button>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</div>

		<div class="card shadow-sm">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h2 class="h6 mb-0"><i class="fas fa-history mr-1" aria-hidden="true"></i> Riwayat</h2>
				<span class="badge badge-light"><?= count($history) ?> catatan</span>
			</div>
			<div class="card-body">
				<?php if (empty($history)): ?>
					<p class="text-muted mb-0">Belum ada riwayat.</p>
				<?php else: ?>
				<ul class="inv-timeline">
					<?php foreach ($history as $h): ?>
					<li>
						<span class="inv-timeline-icon bg-<?= $type_class[$h['type']] ?? 'secondary' ?>"><i class="fas <?= e($h['icon']) ?>" aria-hidden="true"></i></span>
						<div class="inv-timeline-body">
							<div class="font-weight-bold"><?= e($h['title']) ?></div>
							<?php if ($h['detail'] !== ''): ?><div class="small"><?= e($h['detail']) ?></div><?php endif; ?>
							<div class="small text-muted"><?= e(format_wib($h['at'], 'short')) ?><?= $h['actor'] ? ' · '.e($h['actor']) : '' ?></div>
						</div>
					</li>
					<?php endforeach; ?>
				</ul>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="col-lg-4 mb-4">
		<div class="card shadow-sm">
			<div class="card-header d-flex align-items-center justify-content-between">
				<h2 class="h6 mb-0"><i class="fas fa-qrcode mr-1" aria-hidden="true"></i> QR barang</h2>
				<?php if ($qr_url): ?><span class="badge badge-pill badge-success">Aktif</span><?php endif; ?>
			</div>
			<div class="card-body text-center">
				<?php if ($qr_url): ?>
					<div class="qr-box mx-auto mb-2" data-qr="<?= e($qr_url) ?>" role="img" aria-label="Kode QR untuk <?= e($item->asset_tag) ?>"></div>
					<div class="font-weight-bold"><?= e($item->asset_tag) ?></div>
					<p class="small text-muted mb-3"><?= $token->last_scanned_at ? 'Terakhir dipindai '.e(format_wib($token->last_scanned_at, 'short')) : 'Belum pernah dipindai' ?></p>
					<input type="text" class="form-control form-control-sm mb-2 text-center" id="qr-url" value="<?= e($qr_url) ?>" readonly aria-label="Tautan QR">
					<div class="d-flex flex-wrap justify-content-center asset-actions mb-3">
						<?php if ($can_labels): ?>
						<form method="post" action="<?= site_url('admin/inventaris/cetak-qr') ?>" target="_blank">
							<?= csrf_field() ?>
							<input type="hidden" name="barang" value="<?= e($item->public_id) ?>">
							<button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-print fa-sm mr-1" aria-hidden="true"></i> Cetak label</button>
						</form>
						<?php endif; ?>
						<button class="btn btn-outline-primary btn-sm" type="button" data-qr-download="<?= e($item->asset_tag) ?>"><i class="fas fa-download fa-sm mr-1" aria-hidden="true"></i> Unduh PNG</button>
						<button class="btn btn-outline-primary btn-sm" type="button" data-copy-value="#qr-url"><i class="fas fa-copy fa-sm mr-1" aria-hidden="true"></i> Salin tautan</button>
					</div>
					<p class="small text-muted mb-0">Siapa pun yang memindai QR ini melihat nama, kode, kondisi, dan lokasi umum barang. Harga tidak ditampilkan.
						<a href="<?= e($qr_url) ?>" target="_blank" rel="noopener">Lihat halamannya</a>.</p>
				<?php else: ?>
					<div class="empty-box py-3"><i class="fas fa-qrcode" aria-hidden="true"></i><?= $token ? 'QR lama tidak dapat ditampilkan ulang. Buat QR baru lalu cetak labelnya.' : 'Barang ini belum punya QR.' ?></div>
				<?php endif; ?>
				<?php if ($can_labels && ! $retired): ?>
				<form method="post" action="<?= $base ?>/qr-baru" class="mt-3"<?= $qr_url ? ' data-confirm="Buat QR baru? Label yang sudah tertempel tidak berlaku lagi dan harus diganti." data-confirm-ok="Buat QR baru"' : ' data-once' ?>>
					<?= csrf_field() ?>
					<button class="btn btn-<?= $qr_url ? 'link btn-sm text-muted' : 'primary' ?>" type="submit"><?= $qr_url ? 'Label hilang/rusak? Buat QR baru' : 'Buat QR sekarang' ?></button>
				</form>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<?php
if ($can_move)
{
	$loc_options = '<option value="">Pilih lokasi…</option>';
	foreach ($locations as $id => $name)
	{
		if ((int) $id === (int) $item->location_id) { continue; }
		$loc_options .= '<option value="'.e($id).'">'.e($name).'</option>';
	}
	echo $modal('m-pindah', 'Pindah lokasi', $base.'/pindah',
		'<div class="form-group"><label for="p-lokasi">Lokasi tujuan</label><select class="custom-select" id="p-lokasi" name="location_id">'.$loc_options.'</select></div>'
		.'<div class="form-group"><label for="p-lokasi-baru">atau lokasi baru</label><input class="form-control" type="text" id="p-lokasi-baru" name="lokasi_baru" maxlength="180" placeholder="mis. Posyandu Melati"></div>'
		.$note('p-catatan', 'Catatan', 'mis. dipakai kegiatan posyandu'), 'Pindahkan');
	echo $modal('m-pinjam', 'Pinjamkan barang', $base.'/pinjam',
		'<div class="form-group"><label for="l-nama">Nama peminjam</label><input class="form-control" type="text" id="l-nama" name="peminjam" maxlength="180" required></div>'
		.'<div class="form-group"><label for="l-instansi">Dari / instansi <span class="text-muted small">(opsional)</span></label><input class="form-control" type="text" id="l-instansi" name="instansi" maxlength="180" placeholder="mis. Karang Taruna RW 03"></div>'
		.'<div class="form-row"><div class="col-sm-7 form-group"><label for="l-keperluan">Keperluan</label><input class="form-control" type="text" id="l-keperluan" name="keperluan" maxlength="500"></div>'
		.'<div class="col-sm-5 form-group"><label for="l-tgl">Rencana kembali</label><input class="form-control" type="date" id="l-tgl" name="kembali_tanggal"></div></div>'
		.'<p class="small text-muted mb-0">Nama peminjam disimpan terenkripsi dan tidak tampil publik.</p>', 'Catat peminjaman');
	echo $modal('m-kembali', 'Catat pengembalian', $base.'/kembali', $condition_select('k-kondisi', $item->condition_status).$note('k-catatan'), 'Simpan');
}
if ($can_maintain)
{
	echo $modal('m-perbaikan', 'Catat perbaikan', $base.'/perbaikan',
		'<div class="form-group"><label for="r-keluhan">Kerusakan / keluhan</label><input class="form-control" type="text" id="r-keluhan" name="keluhan" maxlength="600" required placeholder="mis. layar tidak menyala"></div>'
		.'<div class="form-group mb-0"><label for="r-bengkel">Diperbaiki di <span class="text-muted small">(opsional)</span></label><input class="form-control" type="text" id="r-bengkel" name="bengkel" maxlength="180"></div>', 'Simpan');
	echo $modal('m-selesai', 'Perbaikan selesai', $base.'/selesai-perbaikan',
		'<div class="form-group"><label for="s-tindakan">Yang dikerjakan <span class="text-muted small">(opsional)</span></label><input class="form-control" type="text" id="s-tindakan" name="tindakan" maxlength="600"></div>'
		.$condition_select('s-kondisi', 'good')
		.'<div class="form-group mb-0"><label for="s-biaya">Biaya (Rp) <span class="text-muted small">(opsional)</span></label><input class="form-control" type="text" inputmode="decimal" id="s-biaya" name="biaya" maxlength="20"></div>', 'Simpan');
}
if ($can_status)
{
	$status_options = '';
	foreach (array('active' => 'Aktif / dipakai', 'lost' => 'Hilang', 'disposed' => 'Dihapuskan (tidak dipakai lagi)') as $code => $label)
	{
		$status_options .= '<option value="'.$code.'"'.($item->lifecycle_status === $code ? ' selected' : '').'>'.e($label).'</option>';
	}
	if ( ! in_array($item->lifecycle_status, array('active', 'lost', 'disposed'), TRUE))
	{
		$status_options = '<option value="'.e($item->lifecycle_status).'" selected>'.e(InventoryService::STATUSES[$item->lifecycle_status] ?? $item->lifecycle_status).' (tetap)</option>'.$status_options;
	}
	echo $modal('m-kondisi', 'Ubah kondisi / status', $base.'/kondisi',
		$condition_select('c-kondisi', $item->condition_status)
		.'<div class="form-group"><label for="c-status">Status</label><select class="custom-select" id="c-status" name="status">'.$status_options.'</select></div>'
		.$note('c-catatan', 'Catatan', 'mis. hasil pengecekan bulanan'), 'Simpan');
}
?>
