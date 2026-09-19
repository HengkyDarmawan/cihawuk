<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Halaman QR aset. Setiap keadaan punya tampilannya sendiri; tidak pernah kosong.
 * Tidak ada harga, dokumen, nomor seri, penanggung jawab, atau catatan internal di sini.
 */
?>
<section class="page-body">
	<div class="container-site" style="max-width: 46rem">
		<?php if ($status === 'unknown'): ?>
			<div class="empty-state">
				<?= icon('alert-triangle') ?>
				<h1 class="h4">QR tidak dikenali</h1>
				<p class="mb-0">Kode ini tidak cocok dengan label aset Desa Cihawuk mana pun. Bila Anda menemukannya tertempel pada barang milik desa, laporkan lewat <a href="<?= site_url('lapor') ?>">formulir pengaduan</a>.</p>
			</div>
		<?php elseif ($status === 'revoked'): ?>
			<div class="empty-state">
				<?= icon('slash') ?>
				<h1 class="h4">Label ini sudah tidak berlaku</h1>
				<p class="mb-0">Token QR pada label ini telah dicabut pengelola aset, misalnya karena label diganti. Hubungi kantor desa bila membutuhkan keterangan barangnya.</p>
			</div>
		<?php else: ?>
			<article class="card-soft card-pad">
				<p class="eyebrow"><?= e($asset['category_name']) ?></p>
				<h1 class="h3"><?= e($asset['name']) ?></h1>
				<p class="mb-3"><code><?= e($asset['asset_tag']) ?></code></p>

				<?php if ($media): ?><figure class="mb-3"><?= media_img($media, 'Foto aset belum tersedia', TRUE) ?></figure><?php endif; ?>

				<?php if ($asset['lifecycle_status'] === 'in_maintenance'): ?>
					<div class="alert alert-info" role="status">Barang ini sedang dalam pemeliharaan.</div>
				<?php elseif ($asset['lifecycle_status'] === 'inactive'): ?>
					<div class="alert alert-secondary" role="status">Barang ini tidak lagi digunakan.</div>
				<?php elseif ($asset['lifecycle_status'] === 'transferred'): ?>
					<div class="alert alert-secondary" role="status">Barang ini sudah dipindahtangankan dari Pemerintah Desa Cihawuk.</div>
				<?php elseif ($asset['lifecycle_status'] === 'disposed'): ?>
					<div class="alert alert-secondary" role="status">Barang ini sudah dihapuskan dari daftar aset desa.</div>
				<?php elseif ($asset['lifecycle_status'] === 'lost'): ?>
					<div class="alert alert-warning" role="status">Barang ini tercatat hilang. Bila Anda menemukannya, mohon laporkan.</div>
				<?php endif; ?>

				<table class="table table-sm">
					<caption class="visually-hidden">Identitas aset</caption>
					<tbody>
						<?php if ($asset['brand'] OR $asset['model']): ?>
						<tr><th scope="row">Merek atau tipe</th><td><?= e(trim($asset['brand'].' '.$asset['model'])) ?></td></tr>
						<?php endif; ?>
						<?php if ($asset['acquisition_year']): ?>
						<tr><th scope="row">Tahun perolehan</th><td><?= (int) $asset['acquisition_year'] ?></td></tr>
						<?php endif; ?>
						<tr><th scope="row">Unit pemilik</th><td><?= e($asset['owner_unit']) ?></td></tr>
						<?php if ($asset['location_name']): ?>
						<tr><th scope="row">Lokasi umum</th><td><?= e($asset['location_name']) ?></td></tr>
						<?php endif; ?>
						<tr><th scope="row">Status</th><td><?= e($asset['lifecycle_label']) ?></td></tr>
						<tr><th scope="row">Kondisi</th><td><?= e($asset['condition_label']) ?></td></tr>
					</tbody>
				</table>

				<?php if ($asset['verified']): ?>
					<p><span class="badge badge-success">Data terverifikasi</span>
						<span class="small text-muted">audit terakhir <?= e(format_wib($asset['verified_at'], 'date')) ?></span></p>
				<?php else: ?>
					<p class="small text-muted">Belum ada hasil audit fisik yang diverifikasi untuk barang ini.</p>
				<?php endif; ?>

				<?php if ($asset['public_note']): ?><p><?= e($asset['public_note']) ?></p><?php endif; ?>

				<p class="small text-muted">
					QR ini adalah identitas inventaris desa. Ia bukan bukti kepemilikan, bukan sertifikat hukum,
					dan bukan tanda tangan elektronik.
				</p>

				<p class="mb-0">
					<a class="btn btn-outline-primary" href="<?= site_url('lapor?aset='.rawurlencode($asset['asset_tag'])) ?>">
						<?= icon('alert-circle') ?> Laporkan ketidaksesuaian atau kerusakan
					</a>
				</p>
			</article>
		<?php endif; ?>
	</div>
</section>
