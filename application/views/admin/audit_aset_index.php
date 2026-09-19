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
	<?php if ($can_create): ?>
	<div class="card-body border-top">
		<h3 class="h6">Buat sesi audit</h3>
		<form method="post" action="<?= site_url('admin/audit-aset/buat') ?>" data-once>
			<?= csrf_field() ?>
			<div class="form-row">
				<div class="col-md-5"><?= ui_input(array('name' => 'name', 'label' => 'Nama sesi', 'maxlength' => 220, 'required' => TRUE)) ?></div>
				<div class="col-md-3"><?= ui_select(array('name' => 'category_id', 'label' => 'Scope kategori', 'options' => $category_options)) ?></div>
				<div class="col-md-4"><?= ui_select(array('name' => 'location_id', 'label' => 'Scope lokasi', 'options' => $location_options)) ?></div>
			</div>
			<button class="btn btn-primary" type="submit">Buat draft sesi</button>
		</form>
	</div>
	<?php endif; ?>
</div>
