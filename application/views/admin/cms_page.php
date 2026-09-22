<?php defined('BASEPATH') OR exit('No direct script access allowed');
$can = function ($p) use ($permissions) { return in_array($p, $permissions, TRUE); };
$status_labels = array(
	'draft' => 'Draft', 'in_review' => 'Menunggu review', 'changes_requested' => 'Perlu perbaikan',
	'approved' => 'Disetujui', 'scheduled' => 'Terjadwal', 'published' => 'Terbit',
	'unpublished' => 'Ditarik', 'archived' => 'Diarsipkan',
);
?>
<div class="page-heading">
	<div>
		<h1><?= e($version ? $version->title : $page->page_key) ?></h1>
		<p>Kunci halaman <code><?= e($page->page_key) ?></code>. Perubahan tersimpan sebagai versi draft sampai diterbitkan.</p>
	</div>
	<div><a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/cms/halaman') ?>">Semua halaman</a></div>
</div>

<?= ui_error_summary($this->form_errors) ?>

<div class="row">
	<div class="col-lg-8">
		<form class="card shadow-sm mb-4" method="post" action="<?= site_url('admin/cms/halaman/'.rawurlencode($page->public_id).'/simpan') ?>" data-once>
			<div class="card-header"><h2 class="h6 mb-0">Detail halaman</h2></div>
			<div class="card-body">
				<?= csrf_field() ?>
				<?= ui_input(array('name' => 'title', 'label' => 'Judul halaman', 'required' => TRUE, 'maxlength' => 180, 'value' => $version ? $version->title : '')) ?>
				<?= ui_input(array('name' => 'nav_title', 'label' => 'Judul pada navigasi', 'maxlength' => 120, 'value' => $version ? $version->nav_title : '')) ?>
				<?= ui_input(array('name' => 'slug', 'label' => 'Slug URL', 'maxlength' => 180, 'value' => $version ? $version->slug : '',
					'help' => 'Mengubah slug setelah halaman terbit membuat pengalihan 301 dari alamat lama.')) ?>
				<?= ui_textarea(array('name' => 'summary', 'label' => 'Ringkasan', 'maxlength' => 500, 'rows' => 2, 'value' => $version ? $version->summary : '')) ?>
				<?= ui_select(array('name' => 'template_code', 'label' => 'Template', 'options' => $templates, 'value' => $version ? $version->template_code : 'page.standard')) ?>
				<?= ui_input(array('name' => 'seo_title', 'label' => 'Judul SEO', 'maxlength' => 200, 'value' => $version ? $version->seo_title : '')) ?>
				<?= ui_textarea(array('name' => 'seo_description', 'label' => 'Deskripsi SEO', 'maxlength' => 300, 'rows' => 2, 'value' => $version ? $version->seo_description : '')) ?>
				<div class="custom-control custom-checkbox">
					<input class="custom-control-input" type="checkbox" id="search_indexable" name="search_indexable" value="1" <?= ( ! $version OR $version->search_indexable) ? 'checked' : '' ?>>
					<label class="custom-control-label" for="search_indexable">Boleh diindeks mesin pencari</label>
				</div>
			</div>
			<div class="card-footer bg-white text-right">
				<button class="btn btn-primary" type="submit" <?= $can('cms.page.edit') ? '' : 'disabled' ?>>Simpan versi draft</button>
			</div>
		</form>

		<div class="card shadow-sm">
			<div class="card-header card-header-actions">
				<h2 class="h6 mb-0">Section pada halaman</h2>
				<?php if ($can('cms.page.create')): ?><?= ui_add_button('modal-tambah-section', 'Tambah section') ?><?php endif; ?>
			</div>
			<?php if (empty($sections)): ?>
				<div class="card-body empty-box"><i class="fas fa-layer-group" aria-hidden="true"></i><p class="mb-0">Belum ada section.</p></div>
			<?php else: ?>
			<ul class="list-group list-group-flush">
				<?php foreach ($sections as $index => $section):
					$definition = $this->cms->section_type($section->section_type);
					$available = $this->cms->section_available($section->section_type); ?>
				<li class="list-group-item">
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
								<button class="btn btn-outline-primary btn-sm" type="submit" <?= $index === 0 ? 'disabled' : '' ?> aria-label="Naikkan <?= e($definition['label'] ?? $section->section_type) ?>">↑</button>
							</form>
							<form method="post" action="<?= site_url('admin/cms/section/'.rawurlencode($section->public_id).'/pindah') ?>">
								<?= csrf_field() ?><input type="hidden" name="direction" value="down">
								<button class="btn btn-outline-primary btn-sm" type="submit" <?= $index === count($sections) - 1 ? 'disabled' : '' ?> aria-label="Turunkan <?= e($definition['label'] ?? $section->section_type) ?>">↓</button>
							</form>
							<a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/cms/section/'.rawurlencode($section->public_id)) ?>">Ubah</a>
							<form method="post" action="<?= site_url('admin/cms/section/'.rawurlencode($section->public_id).'/status') ?>">
								<?= csrf_field() ?><input type="hidden" name="enabled" value="<?= $section->is_enabled ? '0' : '1' ?>">
								<button class="btn btn-outline-primary btn-sm" type="submit"><?= $section->is_enabled ? 'Nonaktifkan' : 'Aktifkan' ?></button>
							</form>
							<form method="post" action="<?= site_url('admin/cms/section/'.rawurlencode($section->public_id).'/arsip') ?>"
								data-confirm="Arsipkan section &quot;<?= e($definition['label'] ?? $section->section_type) ?>&quot;? Section keluar dari susunan halaman, riwayat versinya tetap tersimpan.">
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
		</div>
	</div>

	<div class="col-lg-4">
		<?php $this->load->view('admin/cms_workflow_panel', array(
			'page' => $page, 'version' => $version, 'status_labels' => $status_labels,
			'reviews' => $reviews, 'schedules' => $schedules, 'permissions' => $permissions,
		)); ?>

		<div class="card shadow-sm">
			<div class="card-header"><h2 class="h6 mb-0">Riwayat publikasi</h2></div>
			<div class="card-body">
				<?php if (empty($snapshots)): ?>
					<p class="text-muted mb-0">Belum ada publikasi.</p>
				<?php else: ?>
				<ul class="list-unstyled mb-0 small">
					<?php foreach ($snapshots as $snapshot): ?>
					<li class="mb-2">
						Revisi <?= (int) $snapshot->revision_no ?>
						<?php if ($snapshot->superseded_at === NULL): ?><span class="chip-flag is-info">aktif</span><?php endif; ?>
						<span class="d-block text-muted"><?= e(format_wib($snapshot->published_at, 'short')) ?> · <?= e($snapshot->publisher ?: 'sistem') ?></span>
					</li>
					<?php endforeach; ?>
				</ul>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<?php if ($can('cms.page.create')): ?>
<?= ui_modal_open('modal-tambah-section', 'Tambah section') ?>
	<form method="post" action="<?= site_url('admin/cms/section/tambah') ?>">
		<?= csrf_field() ?>
		<input type="hidden" name="page_key" value="<?= e($page->page_key) ?>">
		<div class="form-group mb-2">
			<label for="section_type">Jenis section</label>
			<select class="form-control form-select" id="section_type" name="section_type">
				<?php foreach ($section_types as $code => $definition): ?>
					<option value="<?= e($code) ?>"><?= e($definition['label']) ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<p class="small text-muted mb-0">Jenis section berasal dari daftar yang disediakan pengembang. Section baru tersimpan sebagai draft.</p>
		<?= ui_modal_actions('Tambah section') ?>
	</form>
<?= ui_modal_close() ?>
<?php endif; ?>
