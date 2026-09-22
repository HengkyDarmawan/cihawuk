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
		<h1>Halaman Publik</h1>
		<p>Halaman disusun dari section yang disediakan; frontend hanya membaca versi yang sudah diterbitkan.</p>
	</div>
	<?php if ($can('cms.page.create')): ?><div class="page-actions"><?= ui_add_button('modal-halaman-baru', 'Halaman baru') ?></div><?php endif; ?>
</div>

<?= ui_error_summary($this->form_errors) ?>

<div class="row">
	<div class="col-12">
		<div class="card shadow-sm">
			<div class="table-responsive">
				<table class="table table-sm mb-0">
					<caption class="sr-only">Daftar halaman publik</caption>
					<thead><tr><th scope="col">Halaman</th><th scope="col">Slug</th><th scope="col">Status</th><th scope="col">Terbit terakhir</th><th scope="col"></th></tr></thead>
					<tbody>
					<?php foreach ($pages as $row): ?>
						<tr>
							<td>
								<span class="font-weight-bold"><?= e($row->title ?: $row->page_key) ?></span>
								<span class="d-block small text-muted"><code><?= e($row->page_key) ?></code><?= $row->is_system ? ' · halaman sistem' : '' ?></span>
							</td>
							<td class="small"><code>/<?= e($row->slug) ?></code></td>
							<td><span class="chip-flag <?= $row->status === 'published' ? 'is-info' : '' ?>"><?= e($status_labels[$row->status] ?? $row->status) ?></span></td>
							<td class="small text-muted"><?= $row->published_at ? e(format_wib($row->published_at, 'short')) : '—' ?></td>
							<td class="text-right">
								<a class="btn btn-outline-primary btn-sm" href="<?= site_url($row->page_key === 'home' ? 'admin/cms/beranda' : 'admin/cms/halaman/'.rawurlencode($row->public_id)) ?>">Kelola</a>
							</td>
						</tr>
					<?php endforeach; ?>
					<?php if (empty($pages)): ?>
						<tr><td colspan="5" class="dt-empty text-center">Belum ada halaman.</td></tr>
					<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

</div>

<?php if ($can('cms.page.create')): ?>
<?= ui_modal_open('modal-halaman-baru', 'Halaman baru') ?>
	<form method="post" action="<?= site_url('admin/cms/halaman/buat') ?>" data-once>
		<?= csrf_field() ?>
		<?= ui_input(array('name' => 'page_key', 'label' => 'Kunci halaman', 'required' => TRUE, 'maxlength' => 60,
			'help' => 'Huruf kecil, angka, dan garis bawah. Tidak berubah walaupun slug diganti.')) ?>
		<?= ui_input(array('name' => 'title', 'label' => 'Judul halaman', 'required' => TRUE, 'maxlength' => 180)) ?>
		<?= ui_input(array('name' => 'slug', 'label' => 'Slug URL', 'maxlength' => 180, 'help' => 'Dibuat dari judul bila dikosongkan.')) ?>
		<?= ui_select(array('name' => 'template_code', 'label' => 'Template', 'options' => $templates, 'value' => 'page.standard')) ?>
		<?= ui_modal_actions('Buat draft') ?>
	</form>
<?= ui_modal_close() ?>
<?php endif; ?>
