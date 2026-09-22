<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Daftar tahun anggaran. */
?>
<div class="page-heading">
	<div>
		<h1>Anggaran dan Realisasi</h1>
		<p>Anggaran murni, anggaran perubahan, dan realisasi diisi sebagai revisi terpisah pada satu tahun anggaran.</p>
	</div>
	<?php if ($can_manage): ?><div class="page-actions"><?= ui_add_button('modal-tambah-tahun', 'Tambah tahun anggaran') ?></div><?php endif; ?>
</div>

<?= ui_error_summary($this->form_errors) ?>

<div class="card shadow-sm">
	<div class="card-header"><h2 class="h6 mb-0">Tahun anggaran</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Tahun</th><th scope="col">Status</th><th scope="col">Sifat data</th><th scope="col">Terbit</th></tr></thead>
			<tbody>
			<?php foreach ($years as $year): ?>
				<tr>
					<th scope="row"><a href="<?= site_url('admin/keuangan/'.rawurlencode($year->public_id)) ?>"><?= (int) $year->fiscal_year ?></a></th>
					<td><?= e($statuses[$year->status] ?? $year->status) ?></td>
					<td><?= (int) $year->is_provisional === 1 ? 'sementara' : 'final' ?></td>
					<td><?= $year->published_at ? e(format_wib($year->published_at, 'short')) : '&mdash;' ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($years)): ?><tr><td colspan="4" class="text-muted">Belum ada tahun anggaran.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<?php if ($can_manage): ?>
<?= ui_modal_open('modal-tambah-tahun', 'Tambah tahun anggaran') ?>
	<form method="post" action="<?= site_url('admin/keuangan/tahun') ?>" data-once>
		<?= csrf_field() ?>
		<?= ui_input(array('name' => 'fiscal_year', 'label' => 'Tahun anggaran', 'required' => TRUE)) ?>
		<?= ui_input(array('name' => 'note', 'label' => 'Catatan', 'maxlength' => 1000)) ?>
		<?= ui_modal_actions('Buat') ?>
	</form>
<?= ui_modal_close() ?>
<?php endif; ?>
