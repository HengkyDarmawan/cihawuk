<?php defined('BASEPATH') OR exit('No direct script access allowed');
$status_labels = array(
	'draft' => 'Draft', 'in_review' => 'Menunggu review', 'changes_requested' => 'Perlu perbaikan',
	'approved' => 'Disetujui', 'scheduled' => 'Terjadwal', 'published' => 'Terbit',
	'unpublished' => 'Ditarik', 'archived' => 'Diarsipkan',
);
$can = function ($p) use ($permissions) { return in_array($p, $permissions, TRUE); };
$ordered_ids = implode(',', array_map(function ($s) { return $s->public_id; }, $sections));
?>
<div class="page-heading">
	<div>
		<h1>Pengaturan Beranda</h1>
		<p>
			Susunan beranda berasal dari section di bawah. Perubahan tersimpan sebagai draft dan
			<strong>belum tampil publik</strong> sampai halaman diterbitkan.
		</p>
	</div>
	<div class="text-right">
		<span class="chip-flag <?= $page->status === 'published' ? 'is-info' : '' ?>"><?= e($status_labels[$page->status] ?? $page->status) ?></span>
		<?php if ($page->published_at): ?><span class="d-block small text-muted">Terbit terakhir <?= e(format_wib($page->published_at, 'short')) ?></span><?php endif; ?>
	</div>
</div>

<?= ui_error_summary($this->form_errors) ?>

<div class="row">
	<div class="col-lg-8">
		<div class="card shadow-sm mb-4">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h2 class="h6 mb-0">Section pada halaman</h2>
				<span class="small text-muted"><?= count($sections) ?> section</span>
			</div>
			<?php if (empty($sections)): ?>
				<div class="card-body empty-box"><i class="fas fa-layer-group" aria-hidden="true"></i><p class="mb-0">Belum ada section. Tambahkan dari panel kanan.</p></div>
			<?php else: ?>
			<ul class="list-group list-group-flush" data-sortable data-sortable-form="form-urutan">
				<?php foreach ($sections as $index => $section):
					$definition = $this->cms->section_type($section->section_type);
					$available = $this->cms->section_available($section->section_type); ?>
				<li class="list-group-item" data-sortable-item="<?= e($section->public_id) ?>">
					<div class="d-flex flex-wrap justify-content-between align-items-start">
						<div class="mr-3 mb-2">
							<span class="font-weight-bold"><?= e($definition['label'] ?? $section->section_type) ?></span>
							<?php if ( ! $section->is_enabled): ?><span class="chip-flag">nonaktif</span><?php endif; ?>
							<?php if ( ! $available): ?><span class="chip-flag is-warning">modul mati — tidak ikut terbit</span><?php endif; ?>
							<span class="d-block small text-muted">
								<?= e($section->title ?: '(tanpa judul)') ?> · <code><?= e($section->layout_variant) ?></code> · versi <?= (int) $section->version_no ?>
							</span>
						</div>
						<div class="d-flex flex-wrap align-items-center" style="gap:.35rem">
							<?php if ($can('cms.page.edit')): ?>
							<form method="post" action="<?= site_url('admin/cms/section/'.rawurlencode($section->public_id).'/pindah') ?>">
								<?= csrf_field() ?><input type="hidden" name="direction" value="up">
								<button class="btn btn-outline-primary btn-sm" type="submit" <?= $index === 0 ? 'disabled' : '' ?> aria-label="Naikkan <?= e($definition['label']) ?>">↑</button>
							</form>
							<form method="post" action="<?= site_url('admin/cms/section/'.rawurlencode($section->public_id).'/pindah') ?>">
								<?= csrf_field() ?><input type="hidden" name="direction" value="down">
								<button class="btn btn-outline-primary btn-sm" type="submit" <?= $index === count($sections) - 1 ? 'disabled' : '' ?> aria-label="Turunkan <?= e($definition['label']) ?>">↓</button>
							</form>
							<a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/cms/section/'.rawurlencode($section->public_id)) ?>">Ubah</a>
							<form method="post" action="<?= site_url('admin/cms/section/'.rawurlencode($section->public_id).'/status') ?>">
								<?= csrf_field() ?><input type="hidden" name="enabled" value="<?= $section->is_enabled ? '0' : '1' ?>">
								<button class="btn btn-outline-primary btn-sm" type="submit"><?= $section->is_enabled ? 'Nonaktifkan' : 'Aktifkan' ?></button>
							</form>
							<form method="post" action="<?= site_url('admin/cms/section/'.rawurlencode($section->public_id).'/arsip') ?>"
								data-confirm="Arsipkan section &quot;<?= e($definition['label']) ?>&quot;? Section hilang dari draft beranda; riwayat versinya tetap tersimpan."
								data-confirm-ok="Arsipkan section">
								<?= csrf_field() ?>
								<button class="btn btn-outline-danger btn-sm" type="submit">Arsipkan</button>
							</form>
							<?php endif; ?>
						</div>
					</div>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
			<?php if ($can('cms.page.edit') && $sections): ?>
			<div class="card-footer bg-white">
				<form method="post" action="<?= site_url('admin/cms/section/urutkan') ?>" id="form-urutan" class="d-flex flex-wrap align-items-center" style="gap:.5rem">
					<?= csrf_field() ?>
					<input type="hidden" name="page_key" value="<?= e($page->page_key) ?>">
					<label class="sr-only" for="urutan">Urutan section</label>
					<input class="form-control form-control-sm flex-grow-1" type="text" id="urutan" name="order" value="<?= e($ordered_ids) ?>" data-sortable-input>
					<button class="btn btn-outline-primary btn-sm" type="submit">Simpan urutan</button>
					<span class="small text-muted w-100">Urutan dapat diubah dengan tombol panah, seret-dan-lepas, atau menyunting daftar ID ini.</span>
				</form>
			</div>
			<?php endif; ?>
		</div>

		<div class="card shadow-sm">
			<div class="card-header"><h2 class="h6 mb-0">Riwayat publikasi</h2></div>
			<div class="card-body">
				<?php if (empty($snapshots)): ?>
					<p class="text-muted mb-0">Belum ada publikasi.</p>
				<?php else: ?>
				<ol class="list-unstyled mb-0">
					<?php foreach ($snapshots as $snapshot): ?>
					<li class="mb-3 d-flex justify-content-between align-items-start">
						<div>
							<span class="font-weight-bold">Revisi <?= (int) $snapshot->revision_no ?></span>
							<?php if ($snapshot->superseded_at === NULL): ?><span class="chip-flag is-info">aktif</span><?php endif; ?>
							<?php if ($snapshot->rolled_back_from): ?><span class="chip-flag">hasil rollback</span><?php endif; ?>
							<span class="d-block small text-muted"><?= e(format_wib($snapshot->published_at, 'short')) ?> · <?= e($snapshot->publisher ?: 'sistem') ?></span>
							<?php if ($snapshot->reason): ?><span class="d-block small"><?= e($snapshot->reason) ?></span><?php endif; ?>
						</div>
						<?php if ($can('cms.page.rollback') && $snapshot->superseded_at !== NULL): ?>
						<form method="post" action="<?= site_url('admin/cms/halaman/'.rawurlencode($page->public_id).'/alur/rollback') ?>" class="form-inline">
							<?= csrf_field() ?>
							<input type="hidden" name="snapshot_id" value="<?= (int) $snapshot->id ?>">
							<label class="sr-only" for="alasan-rollback-<?= (int) $snapshot->id ?>">Alasan rollback</label>
							<input class="form-control form-control-sm mr-2" id="alasan-rollback-<?= (int) $snapshot->id ?>" name="reason" maxlength="500" placeholder="Alasan (min. 10 karakter)">
							<button class="btn btn-outline-danger btn-sm" type="submit">Kembalikan</button>
						</form>
						<?php endif; ?>
					</li>
					<?php endforeach; ?>
				</ol>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="col-lg-4">
		<?php $this->load->view('admin/cms_workflow_panel', array(
			'page' => $page, 'version' => $version, 'status_labels' => $status_labels,
			'reviews' => $reviews, 'schedules' => $schedules, 'permissions' => $permissions,
		)); ?>

		<?php if ($can('cms.page.create')): ?>
		<div class="card shadow-sm">
			<div class="card-header"><h2 class="h6 mb-0">Tambah section</h2></div>
			<form method="post" action="<?= site_url('admin/cms/section/tambah') ?>">
				<div class="card-body">
					<?= csrf_field() ?>
					<input type="hidden" name="page_key" value="<?= e($page->page_key) ?>">
					<div class="form-group mb-2">
						<label for="section_type">Jenis section</label>
						<select class="form-control form-select" id="section_type" name="section_type" required>
							<?php foreach ($section_types as $code => $definition): ?>
								<option value="<?= e($code) ?>"><?= e($definition['label']) ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<p class="small text-muted mb-0">
						Jenis section berasal dari daftar yang disediakan pengembang. Tidak ada HTML, CSS, atau JavaScript bebas.
						Section yang modulnya belum aktif tidak muncul di daftar ini.
					</p>
				</div>
				<div class="card-footer bg-white text-right"><button class="btn btn-primary btn-sm" type="submit">Tambahkan</button></div>
			</form>
		</div>
		<?php endif; ?>
	</div>
</div>
