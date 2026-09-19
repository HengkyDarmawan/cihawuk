<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Daftar tahun anggaran. */
?>
<div class="page-heading">
	<div>
		<h1>Anggaran dan Realisasi</h1>
		<p>Anggaran murni, anggaran perubahan, dan realisasi diisi sebagai revisi terpisah pada satu tahun anggaran.</p>
	</div>
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
	<?php if ($can_manage): ?>
	<div class="card-body border-top">
		<h3 class="h6">Tambah tahun anggaran</h3>
		<form method="post" action="<?= site_url('admin/keuangan/tahun') ?>" data-once>
			<?= csrf_field() ?>
			<div class="form-row">
				<div class="col-md-3"><?= ui_input(array('name' => 'fiscal_year', 'label' => 'Tahun anggaran', 'required' => TRUE)) ?></div>
				<div class="col-md-9"><?= ui_input(array('name' => 'note', 'label' => 'Catatan', 'maxlength' => 1000)) ?></div>
			</div>
			<button class="btn btn-primary" type="submit">Buat</button>
		</form>
	</div>
	<?php endif; ?>
</div>
