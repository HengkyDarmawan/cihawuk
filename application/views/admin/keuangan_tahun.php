<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Pengelolaan satu tahun anggaran: revisi, kategori, angka, dokumen, dan alur terbit. */
$base = site_url('admin/keuangan/'.rawurlencode($year->public_id));
$rupiah = function ($value) { return number_format((float) $value, 2, ',', '.'); };
$category_options = array();
foreach ($categories as $category)
{
	$category_options[$category->public_id] = str_repeat('— ', max(0, (int) $category->level - 1))
		.$category->name.' ['.($sections[$category->section] ?? $category->section).']';
}
$parent_options = array('' => '— tanpa induk —') + $category_options;
$revision_options = array();
foreach ($revisions as $revision) { $revision_options[$revision->public_id] = $revision->label; }
$locked = ($year->status === 'locked');
?>
<div class="page-heading">
	<div>
		<h1>APBDes <?= (int) $year->fiscal_year ?></h1>
		<p>Status: <?= e($statuses[$year->status] ?? $year->status) ?>
			<?= (int) $year->is_provisional === 1 ? '· data sementara' : '· data final' ?></p>
	</div>
	<div><a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/keuangan') ?>">Kembali</a></div>
</div>

<?= ui_error_summary($this->form_errors) ?>

<?php if ($locked): ?>
<div class="alert alert-info" role="status">
	Periode ini terkunci. Koreksi hanya lewat revisi baru setelah kuncinya dibuka.
</div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Pemeriksaan sebelum terbit</h2></div>
	<div class="card-body">
		<?php if (empty($report['errors']) && empty($report['warnings'])): ?>
			<p class="mb-0 text-success"><?= icon('check-circle') ?> Tidak ada masalah.</p>
		<?php endif; ?>
		<?php if ($report['errors']): ?>
			<h3 class="h6">Harus diperbaiki</h3>
			<ul class="mb-3"><?php foreach ($report['errors'] as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
		<?php endif; ?>
		<?php if ($report['warnings']): ?>
			<h3 class="h6">Perlu diperiksa</h3>
			<ul class="mb-0 text-muted"><?php foreach ($report['warnings'] as $warning): ?><li><?= e($warning) ?></li><?php endforeach; ?></ul>
		<?php endif; ?>
	</div>
	<div class="card-footer">
		<?php foreach (array(
			'rekonsiliasi' => array('Mulai rekonsiliasi', $can_manage),
			'verifikasi' => array('Verifikasi', $can_verify),
			'setujui' => array('Setujui untuk terbit', $can_publish),
			'terbitkan' => array('Terbitkan', $can_publish),
			'tarik' => array('Tarik dari publik', $can_publish),
			'kunci' => array('Kunci periode', $can_publish),
			'buka-kunci' => array('Buka kunci', $can_publish),
		) as $action => $definition): if ( ! $definition[1]) { continue; } ?>
		<form class="form-inline d-inline-block mr-2 mb-2" method="post" action="<?= $base ?>/alur/<?= e($action) ?>" data-once>
			<?= csrf_field() ?>
			<input class="form-control form-control-sm mr-1" type="text" name="reason" maxlength="500" placeholder="Alasan" aria-label="Alasan <?= e($definition[0]) ?>">
			<button class="btn btn-sm btn-outline-secondary" type="submit"><?= e($definition[0]) ?></button>
		</form>
		<?php endforeach; ?>
	</div>
</div>

<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Revisi</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Jenis</th><th scope="col">Label</th><th scope="col">Tahun dokumen</th><th scope="col">Jumlah baris</th></tr></thead>
			<tbody>
			<?php foreach ($revisions as $revision): ?>
				<tr>
					<th scope="row"><?= e($revision_types[$revision->revision_type] ?? $revision->revision_type) ?></th>
					<td><?= e($revision->label) ?></td>
					<td><?= $revision->document_year ? (int) $revision->document_year : '&mdash;' ?></td>
					<td><?= count($lines[$revision->revision_type] ?? array()) ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($revisions)): ?><tr><td colspan="4" class="text-muted">Belum ada revisi.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php if ($can_manage && ! $locked): ?>
	<div class="card-body border-top">
		<form method="post" action="<?= $base ?>/revisi" data-once>
			<?= csrf_field() ?>
			<div class="form-row">
				<div class="col-md-3"><?= ui_select(array('name' => 'revision_type', 'label' => 'Jenis revisi', 'options' => $revision_types, 'required' => TRUE)) ?></div>
				<div class="col-md-4"><?= ui_input(array('name' => 'label', 'label' => 'Label', 'maxlength' => 160)) ?></div>
				<div class="col-md-2"><?= ui_input(array('name' => 'document_year', 'label' => 'Tahun dokumen')) ?></div>
				<div class="col-md-3"><?= ui_input(array('name' => 'document_note', 'label' => 'Catatan dokumen', 'maxlength' => 500)) ?></div>
			</div>
			<button class="btn btn-outline-primary" type="submit">Simpan revisi</button>
		</form>
	</div>
	<?php endif; ?>
</div>

<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Kategori anggaran</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Kelompok</th><th scope="col">Kode</th><th scope="col">Uraian</th><th scope="col">Tingkat</th></tr></thead>
			<tbody>
			<?php foreach ($categories as $category): ?>
				<tr>
					<td><?= e($sections[$category->section] ?? $category->section) ?></td>
					<td><?= e($category->code ?: '—') ?></td>
					<th scope="row" style="padding-left: <?= (int) $category->level * 12 ?>px"><?= e($category->name) ?></th>
					<td><?= (int) $category->level ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($categories)): ?><tr><td colspan="4" class="text-muted">Belum ada kategori.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php if ($can_manage && ! $locked): ?>
	<div class="card-body border-top">
		<form method="post" action="<?= $base ?>/kategori" data-once>
			<?= csrf_field() ?>
			<div class="form-row">
				<div class="col-md-3"><?= ui_select(array('name' => 'section', 'label' => 'Kelompok', 'options' => $sections, 'required' => TRUE)) ?></div>
				<div class="col-md-2"><?= ui_input(array('name' => 'code', 'label' => 'Kode', 'maxlength' => 40)) ?></div>
				<div class="col-md-4"><?= ui_input(array('name' => 'name', 'label' => 'Uraian', 'maxlength' => 220, 'required' => TRUE)) ?></div>
				<div class="col-md-3"><?= ui_select(array('name' => 'parent_id', 'label' => 'Induk', 'options' => $parent_options)) ?></div>
			</div>
			<button class="btn btn-outline-primary" type="submit">Simpan kategori</button>
		</form>
		<p class="small text-muted mb-0">Induk dipilih dengan public id kategori; kedalaman maksimal bidang, subbidang, lalu kegiatan.</p>
	</div>
	<?php endif; ?>
</div>

<?php foreach ($revisions as $revision): ?>
<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Angka: <?= e($revision->label) ?></h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Kelompok</th><th scope="col">Uraian</th><th scope="col" class="num">Jumlah</th><th scope="col">Catatan selisih</th></tr></thead>
			<tbody>
			<?php foreach (($lines[$revision->revision_type] ?? array()) as $line): ?>
				<tr>
					<td><?= e($sections[$line->section] ?? $line->section) ?></td>
					<th scope="row" style="padding-left: <?= (int) $line->level * 12 ?>px"><?= e($line->name) ?></th>
					<td class="num"><?= e($rupiah($line->amount)) ?></td>
					<td class="small text-muted"><?= e($line->variance_note ?: '—') ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($lines[$revision->revision_type])): ?><tr><td colspan="4" class="text-muted">Belum ada angka.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php if ($can_manage && ! $locked): ?>
	<div class="card-body border-top">
		<form method="post" action="<?= $base ?>/angka" data-once>
			<?= csrf_field() ?>
			<input type="hidden" name="revision_public_id" value="<?= e($revision->public_id) ?>">
			<div class="form-row">
				<div class="col-md-5"><?= ui_select(array('name' => 'category_public_id', 'label' => 'Kategori', 'options' => $category_options, 'required' => TRUE)) ?></div>
				<div class="col-md-3"><?= ui_input(array('name' => 'amount', 'label' => 'Jumlah (rupiah)', 'required' => TRUE)) ?></div>
				<div class="col-md-4"><?= ui_input(array('name' => 'variance_note', 'label' => 'Catatan selisih', 'maxlength' => 500,
					'help' => 'Wajib bila jumlah komponen berbeda dari induknya atau realisasi melebihi anggaran.')) ?></div>
			</div>
			<button class="btn btn-outline-primary" type="submit">Simpan angka</button>
		</form>
	</div>
	<?php endif; ?>
</div>
<?php endforeach; ?>

<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Dokumen pendukung</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Judul</th><th scope="col">Tahun</th><th scope="col">Salinan publik</th><th scope="col">Sudah disamarkan</th></tr></thead>
			<tbody>
			<?php foreach ($documents as $document): ?>
				<tr>
					<th scope="row"><?= e($document->title) ?></th>
					<td><?= $document->document_year ? (int) $document->document_year : '&mdash;' ?></td>
					<td><?= $document->public_document_id ? 'ada' : 'belum' ?></td>
					<td><?= (int) $document->is_redacted === 1 ? 'ya' : '<strong>belum</strong>' ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($documents)): ?><tr><td colspan="4" class="text-muted">Belum ada dokumen.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php if ($can_manage && ! $locked): ?>
	<div class="card-body border-top">
		<form method="post" action="<?= $base ?>/dokumen" data-once>
			<?= csrf_field() ?>
			<div class="form-row">
				<div class="col-md-5"><?= ui_input(array('name' => 'title', 'label' => 'Judul dokumen', 'maxlength' => 220, 'required' => TRUE)) ?></div>
				<div class="col-md-2"><?= ui_input(array('name' => 'document_year', 'label' => 'Tahun dokumen')) ?></div>
				<div class="col-md-2"><?= ui_input(array('name' => 'public_document_id', 'label' => 'ID dokumen publik')) ?></div>
				<div class="col-md-3 d-flex align-items-center">
					<div class="form-check mt-3">
						<input class="form-check-input" type="checkbox" id="is_redacted" name="is_redacted" value="1">
						<label class="form-check-label" for="is_redacted">Sudah disamarkan</label>
					</div>
				</div>
			</div>
			<button class="btn btn-outline-primary" type="submit">Catat dokumen</button>
		</form>
	</div>
	<?php endif; ?>
</div>

<?php if ($snapshots): ?>
<div class="card shadow-sm">
	<div class="card-header"><h2 class="h6 mb-0">Riwayat publikasi</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Revisi</th><th scope="col">Alasan</th><th scope="col">Penerbit</th><th scope="col">Waktu</th></tr></thead>
			<tbody>
			<?php foreach ($snapshots as $snapshot): ?>
				<tr>
					<th scope="row"><?= (int) $snapshot->revision_no ?><?= $snapshot->superseded_at ? '' : ' <span class="badge badge-success">aktif</span>' ?></th>
					<td><?= e($snapshot->reason) ?></td>
					<td><?= e($snapshot->publisher ?: '—') ?></td>
					<td><?= e(format_wib($snapshot->published_at, 'short')) ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
<?php endif; ?>
