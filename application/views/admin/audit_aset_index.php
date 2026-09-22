<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Daftar sesi audit fisik aset. */
$category_options = array('' => '— semua kategori —');
foreach ($categories as $category) { $category_options[(string) $category->id] = $category->name; }
$location_options = array('' => '— semua lokasi —');
foreach ($locations as $location) { $location_options[(string) $location->id] = $location->name; }
?>
<div class="page-heading">
	<div>
		<h1>Audit Aset</h1>
		<p>Daftar target dibekukan saat sesi diterbitkan. Temuan tidak pernah langsung menimpa data master.</p>
	</div>
	<?php if ($can_create): ?><div class="page-actions"><?= ui_add_button('modal-buat-sesi', 'Buat sesi audit') ?></div><?php endif; ?>
</div>

<?= ui_error_summary($this->form_errors) ?>

<div class="card shadow-sm">
	<div class="card-header"><h2 class="h6 mb-0">Sesi audit</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Nama</th><th scope="col">Status</th><th scope="col">Mulai</th><th scope="col">Ditutup</th></tr></thead>
			<tbody>
			<?php foreach ($sessions as $session): ?>
				<tr>
					<th scope="row"><a href="<?= site_url('admin/audit-aset/'.rawurlencode($session->public_id)) ?>"><?= e($session->name) ?></a></th>
					<td><?= e($statuses[$session->status] ?? $session->status) ?></td>
					<td><?= e(format_wib($session->starts_at, 'short')) ?></td>
					<td><?= $session->closed_at ? e(format_wib($session->closed_at, 'short')) : '&mdash;' ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($sessions)): ?><tr><td colspan="4" class="text-muted">Belum ada sesi audit.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<?php if ($can_create): ?>
<?= ui_modal_open('modal-buat-sesi', 'Buat sesi audit') ?>
	<form method="post" action="<?= site_url('admin/audit-aset/buat') ?>" data-once>
		<?= csrf_field() ?>
		<?= ui_input(array('name' => 'name', 'label' => 'Nama sesi', 'maxlength' => 220, 'required' => TRUE)) ?>
		<?= ui_select(array('name' => 'category_id', 'label' => 'Scope kategori', 'options' => $category_options)) ?>
		<?= ui_select(array('name' => 'location_id', 'label' => 'Scope lokasi', 'options' => $location_options)) ?>
		<p class="small text-muted mb-0">Sesi dibuat sebagai draft; daftar target dibekukan saat sesi diterbitkan.</p>
		<?= ui_modal_actions('Buat draft sesi') ?>
	</form>
<?= ui_modal_close() ?>
<?php endif; ?>
