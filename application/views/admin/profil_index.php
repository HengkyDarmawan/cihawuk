<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Ringkasan profil desa: blok, linimasa, pemeriksaan, dan alur penerbitan. */
$by_key = array();
foreach ($blocks as $block) { $by_key[$block->block_key] = $block; }
?>
<div class="page-heading">
	<div>
		<h1>Profil Desa</h1>
		<p>Isi profil disusun sebagai blok terstruktur. Halaman publik hanya berubah setelah profil diterbitkan.</p>
	</div>
</div>

<div class="card shadow-sm mb-4">
	<div class="card-header d-flex justify-content-between align-items-center">
		<h2 class="h6 mb-0">Pemeriksaan sebelum terbit</h2>
		<span class="small text-muted">
			<?php if ($published): ?>Terbit revisi <?= (int) $published['revision_no'] ?> &middot; <?= e(format_wib($published['published_at'], 'short')) ?><?php else: ?>Belum pernah diterbitkan<?php endif; ?>
		</span>
	</div>
	<div class="card-body">
		<?php if (empty($report['errors']) && empty($report['warnings'])): ?>
			<p class="mb-0 text-success"><?= icon('check-circle') ?> Tidak ada masalah yang menghalangi penerbitan.</p>
		<?php endif; ?>
		<?php if ($report['errors']): ?>
			<h3 class="h6">Harus diperbaiki</h3>
			<ul class="mb-3"><?php foreach ($report['errors'] as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
		<?php endif; ?>
		<?php if ($report['warnings']): ?>
			<h3 class="h6">Perlu diperiksa pengelola</h3>
			<ul class="mb-0 text-muted"><?php foreach ($report['warnings'] as $warning): ?><li><?= e($warning) ?></li><?php endforeach; ?></ul>
		<?php endif; ?>
	</div>
	<?php if ($can_publish): ?>
	<div class="card-footer">
		<form class="form-inline" method="post" action="<?= site_url('admin/profil/alur/terbitkan') ?>" data-once>
			<?= csrf_field() ?>
			<input class="form-control mr-2" type="text" name="reason" maxlength="500" placeholder="Alasan penerbitan" aria-label="Alasan penerbitan">
			<button class="btn btn-primary" type="submit" <?= $report['errors'] ? 'disabled' : '' ?>>Terbitkan profil</button>
		</form>
		<?php if ($published): ?>
		<form class="form-inline mt-2" method="post" action="<?= site_url('admin/profil/alur/tarik') ?>" data-once>
			<?= csrf_field() ?>
			<input class="form-control mr-2" type="text" name="reason" maxlength="500" placeholder="Alasan penarikan" aria-label="Alasan penarikan">
			<button class="btn btn-outline-danger" type="submit">Tarik dari publik</button>
		</form>
		<?php endif; ?>
	</div>
	<?php endif; ?>
</div>

<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Blok profil</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Blok</th><th scope="col">Halaman</th><th scope="col">Status</th><th scope="col">Verifikasi</th><th scope="col">Periode</th><th scope="col">Aksi</th></tr></thead>
			<tbody>
			<?php foreach ($definitions as $key => $definition): $block = $by_key[$key] ?? NULL; if ( ! $block) { continue; } ?>
				<tr>
					<th scope="row"><a href="<?= site_url('admin/profil/blok/'.rawurlencode($block->public_id)) ?>"><?= e($definition['label']) ?></a>
						<?php if (empty($block->body)): ?><br><span class="small text-muted">belum ada isi</span><?php endif; ?></th>
					<td><code>/<?= e($definition['page']) ?></code></td>
					<td><?= e($statuses[$block->status] ?? $block->status) ?></td>
					<td><?= $block->verification_status === 'verified'
						? '<span class="badge badge-success">terverifikasi</span>'
						: '<span class="badge badge-secondary">belum</span>' ?></td>
					<td><?= $block->period_start ? (int) $block->period_start.'&ndash;'.($block->period_end ? (int) $block->period_end : 'kini') : '&mdash;' ?></td>
					<td>
						<?php if ($can_edit && $block->status === 'draft' && $block->current_version_id): ?>
						<form class="d-inline" method="post" action="<?= site_url('admin/profil/blok/'.rawurlencode($block->public_id).'/ajukan') ?>" data-once>
							<?= csrf_field() ?><button class="btn btn-sm btn-outline-primary" type="submit">Ajukan</button>
						</form>
						<?php endif; ?>
						<?php if ($can_publish && $block->current_version_id): ?>
						<form class="d-inline" method="post" action="<?= site_url('admin/profil/blok/'.rawurlencode($block->public_id).'/verifikasi') ?>" data-once>
							<?= csrf_field() ?>
							<input type="hidden" name="verified" value="<?= $block->verification_status === 'verified' ? '0' : '1' ?>">
							<button class="btn btn-sm btn-outline-secondary" type="submit"><?= $block->verification_status === 'verified' ? 'Cabut verifikasi' : 'Verifikasi' ?></button>
						</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Linimasa kepemimpinan</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Periode</th><th scope="col">Nama</th><th scope="col">Jabatan</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead>
			<tbody>
			<?php foreach ($terms as $term): ?>
				<tr>
					<th scope="row"><?= (int) $term->year_start ?>&ndash;<?= $term->year_end ? (int) $term->year_end : ($term->ongoing_claim ? 'sampai dokumen dibuat' : '?') ?></th>
					<td><?= e($term->person_name) ?><?php if ($term->ongoing_claim): ?><br><span class="small text-muted">sumber menulis &ldquo;sampai sekarang&rdquo;</span><?php endif; ?></td>
					<td><?= e($term->position_title) ?></td>
					<td><?= e($term->publication_status) ?> &middot; <?= $term->verification_status === 'verified' ? 'terverifikasi' : 'belum diverifikasi' ?></td>
					<td>
						<?php if ($can_publish): ?>
						<form class="d-inline" method="post" action="<?= site_url('admin/profil/periode/'.rawurlencode($term->public_id).'/'.($term->verification_status === 'verified' ? 'batal-verifikasi' : 'verifikasi')) ?>" data-once>
							<?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary" type="submit"><?= $term->verification_status === 'verified' ? 'Cabut' : 'Verifikasi' ?></button>
						</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($terms)): ?><tr><td colspan="5" class="text-muted">Belum ada periode.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php if ($can_edit): ?>
	<div class="card-body border-top">
		<h3 class="h6">Tambah periode</h3>
		<form method="post" action="<?= site_url('admin/profil/periode/simpan') ?>" data-once>
			<?= csrf_field() ?>
			<div class="form-row">
				<div class="col-md-4"><?= ui_input(array('name' => 'person_name', 'label' => 'Nama', 'maxlength' => 150, 'required' => TRUE)) ?></div>
				<div class="col-md-3"><?= ui_input(array('name' => 'position_title', 'label' => 'Jabatan', 'maxlength' => 150, 'value' => 'Kepala Desa')) ?></div>
				<div class="col-md-2"><?= ui_input(array('name' => 'year_start', 'label' => 'Tahun mulai', 'required' => TRUE)) ?></div>
				<div class="col-md-2"><?= ui_input(array('name' => 'year_end', 'label' => 'Tahun selesai')) ?></div>
			</div>
			<div class="form-check mb-3">
				<input class="form-check-input" type="checkbox" id="ongoing_claim" name="ongoing_claim" value="1">
				<label class="form-check-label" for="ongoing_claim">Sumber menulis &ldquo;sampai sekarang&rdquo; (tanpa tahun selesai)</label>
			</div>
			<button class="btn btn-outline-primary" type="submit">Simpan periode</button>
		</form>
	</div>
	<?php endif; ?>
</div>

<?php if ($snapshots): ?>
<div class="card shadow-sm">
	<div class="card-header"><h2 class="h6 mb-0">Riwayat publikasi</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Revisi</th><th scope="col">Alasan</th><th scope="col">Penerbit</th><th scope="col">Waktu</th><th scope="col"></th></tr></thead>
			<tbody>
			<?php foreach ($snapshots as $snapshot): ?>
				<tr>
					<th scope="row"><?= (int) $snapshot->revision_no ?><?= $snapshot->superseded_at ? '' : ' <span class="badge badge-success">aktif</span>' ?></th>
					<td><?= e($snapshot->reason) ?></td>
					<td><?= e($snapshot->publisher ?: '—') ?></td>
					<td><?= e(format_wib($snapshot->published_at, 'short')) ?></td>
					<td>
						<?php if ($can_publish && $snapshot->superseded_at): ?>
						<form method="post" action="<?= site_url('admin/profil/alur/rollback') ?>" data-once>
							<?= csrf_field() ?>
							<input type="hidden" name="snapshot_id" value="<?= (int) $snapshot->id ?>">
							<input type="hidden" name="reason" value="Kembali ke revisi <?= (int) $snapshot->revision_no ?>">
							<button class="btn btn-sm btn-outline-secondary" type="submit">Kembalikan</button>
						</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
<?php endif; ?>
