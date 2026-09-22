<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Pengelolaan satu periode struktur: unit, jabatan, penugasan, dan penerbitan. */
$base = site_url('admin/struktur/periode/'.rawurlencode($period->public_id));
$unit_options = array('' => '— tanpa unit —');
foreach ($units as $unit) { $unit_options[(string) $unit->id] = $unit->name; }
$position_options = array('' => '— tanpa atasan —');
foreach ($positions as $position) { $position_options[(string) $position->id] = $position->title; }
$person_options = array('' => '— kosong —');
foreach ($people as $person) { $person_options[(string) $person->id] = $person->full_name; }
$active_positions = array();
foreach ($positions as $position) { if ((int) $position->active === 1) { $active_positions[(string) $position->id] = $position->title; } }
?>
<div class="page-heading">
	<div>
		<h1><?= e($period->name) ?></h1>
		<p><?= (int) $period->year_start ?><?= $period->year_end ? '&ndash;'.(int) $period->year_end : '' ?> &middot; status <?= e($period->status) ?></p>
	</div>
	<div><a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/struktur') ?>">Kembali</a></div>
</div>

<?= ui_error_summary($this->form_errors) ?>

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
	<?php if ($can_publish): ?>
	<div class="card-footer">
		<form class="form-inline mb-2" method="post" action="<?= $base ?>/alur/terbitkan" data-once>
			<?= csrf_field() ?>
			<input class="form-control mr-2" type="text" name="reason" maxlength="500" placeholder="Alasan penerbitan" aria-label="Alasan penerbitan">
			<div class="form-check mr-2">
				<input class="form-check-input" type="checkbox" id="make_default" name="make_default" value="1" checked>
				<label class="form-check-label" for="make_default">Jadikan tampilan publik bawaan</label>
			</div>
			<button class="btn btn-primary" type="submit" <?= $report['errors'] ? 'disabled' : '' ?>>Terbitkan</button>
		</form>
		<?php if ($period->status === 'published'): ?>
		<form class="form-inline" method="post" action="<?= $base ?>/alur/tarik" data-once>
			<?= csrf_field() ?>
			<input class="form-control mr-2" type="text" name="reason" maxlength="500" placeholder="Alasan penarikan" aria-label="Alasan penarikan">
			<button class="btn btn-outline-danger" type="submit">Tarik dari publik</button>
		</form>
		<?php endif; ?>
	</div>
	<?php endif; ?>
</div>

<div class="card shadow-sm mb-4">
	<div class="card-header card-header-actions"><h2 class="h6 mb-0">Unit dan lembaga</h2><?php if ($can_edit): ?><?= ui_add_button('modal-tambah-unit', 'Tambah unit') ?><?php endif; ?></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Nama</th><th scope="col">Jenis</th><th scope="col">Induk</th><th scope="col">Aktif</th></tr></thead>
			<tbody>
			<?php foreach ($units as $unit): ?>
				<tr>
					<th scope="row"><?= e($unit->name) ?></th>
					<td><?= e($unit_types[$unit->unit_type] ?? $unit->unit_type) ?></td>
					<td><?= e($unit->parent_id ? ($unit_options[(string) $unit->parent_id] ?? '—') : '—') ?></td>
					<td><?= (int) $unit->active === 1 ? 'ya' : 'tidak' ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($units)): ?><tr><td colspan="4" class="text-muted">Belum ada unit.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<div class="card shadow-sm mb-4">
	<div class="card-header card-header-actions"><h2 class="h6 mb-0">Jabatan</h2><?php if ($can_edit): ?><?= ui_add_button('modal-tambah-jabatan', 'Tambah jabatan') ?><?php endif; ?></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Jabatan</th><th scope="col">Unit</th><th scope="col">Atasan</th><th scope="col">Tingkat</th><th scope="col">Aktif</th><th scope="col">Aksi</th></tr></thead>
			<tbody>
			<?php foreach ($positions as $position): ?>
				<tr>
					<th scope="row"><?= e($position->title) ?></th>
					<td><?= e($position->unit_name ?: '—') ?></td>
					<td><?= e($position->parent_id ? ($position_options[(string) $position->parent_id] ?? '—') : '—') ?></td>
					<td><?= (int) $position->level ?></td>
					<td><?= (int) $position->active === 1 ? 'ya' : 'tidak' ?></td>
					<td>
						<?php if ($can_edit && (int) $position->active === 1): ?>
						<form method="post" action="<?= $base ?>/jabatan-nonaktif" data-once data-confirm="Jabatan <?= e($position->title) ?> akan dinonaktifkan dan tidak bisa dipakai untuk penugasan baru." data-confirm-title="Nonaktifkan jabatan?" data-confirm-ok="Ya, nonaktifkan" data-confirm-danger>
							<?= csrf_field() ?>
							<input type="hidden" name="position_public_id" value="<?= e($position->public_id) ?>">
							<button class="btn btn-sm btn-outline-secondary" type="submit">Nonaktifkan</button>
						</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($positions)): ?><tr><td colspan="6" class="text-muted">Belum ada jabatan.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<div class="card shadow-sm mb-4">
	<div class="card-header card-header-actions"><h2 class="h6 mb-0">Penugasan</h2><?php if ($can_edit): ?><?= ui_add_button('modal-tambah-penugasan', 'Tambah penugasan') ?><?php endif; ?></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Jabatan</th><th scope="col">Orang</th><th scope="col">Jenis</th><th scope="col">Mulai</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead>
			<tbody>
			<?php foreach ($assignments as $assignment): ?>
				<tr>
					<th scope="row"><?= e($assignment->position_title) ?></th>
					<td><?= e($assignment->full_name ?: '— kosong —') ?></td>
					<td><?= e($assignment_types[$assignment->assignment_type] ?? $assignment->assignment_type) ?></td>
					<td><?= e($assignment->start_date ?: '—') ?></td>
					<td><?= e($assignment->status) ?><?= $assignment->end_reason ? '<br><span class="small text-muted">'.e($assignment->end_reason).'</span>' : '' ?></td>
					<td>
						<?php if ($can_edit && $assignment->status === 'active'): ?>
						<form class="form-inline" method="post" action="<?= $base ?>/penugasan-akhiri" data-once data-confirm="Penugasan <?= e($assignment->full_name ?: '(kosong)') ?> sebagai <?= e($assignment->position_title) ?> akan diakhiri." data-confirm-title="Akhiri penugasan?" data-confirm-ok="Ya, akhiri" data-confirm-danger>
							<?= csrf_field() ?>
							<input type="hidden" name="assignment_public_id" value="<?= e($assignment->public_id) ?>">
							<input class="form-control form-control-sm mr-1" type="date" name="end_date" aria-label="Tanggal berakhir">
							<input class="form-control form-control-sm mr-1" type="text" name="reason" maxlength="255" placeholder="Alasan" aria-label="Alasan berakhir">
							<button class="btn btn-sm btn-outline-secondary" type="submit">Akhiri</button>
						</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($assignments)): ?><tr><td colspan="6" class="text-muted">Belum ada penugasan.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<?php if ($can_edit && count($periods) > 1): ?>
<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Salin struktur dari periode lain</h2></div>
	<div class="card-body">
		<p class="small text-muted">Unit dan jabatan disalin; penugasan tidak, karena masa jabatan periode sebelumnya sudah berakhir.</p>
		<form class="form-inline" method="post" action="<?= $base ?>/salin" data-once>
			<?= csrf_field() ?>
			<select class="form-select mr-2" name="source_period_id" aria-label="Periode sumber">
				<?php foreach ($periods as $other): if ((int) $other->id === (int) $period->id) { continue; } ?>
					<option value="<?= e($other->public_id) ?>"><?= e($other->name) ?></option>
				<?php endforeach; ?>
			</select>
			<button class="btn btn-outline-primary" type="submit">Salin</button>
		</form>
	</div>
</div>
<?php endif; ?>

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

<?php if ($can_edit): ?>
<?= ui_modal_open('modal-tambah-unit', 'Tambah unit / lembaga') ?>
	<form method="post" action="<?= $base ?>/unit" data-once>
		<?= csrf_field() ?>
		<?= ui_input(array('name' => 'name', 'label' => 'Nama unit', 'maxlength' => 180, 'required' => TRUE)) ?>
		<?= ui_select(array('name' => 'unit_type', 'label' => 'Jenis', 'options' => $unit_types, 'required' => TRUE)) ?>
		<?= ui_select(array('name' => 'parent_id', 'id' => 'unit_parent_id', 'label' => 'Induk', 'options' => $unit_options)) ?>
		<div class="form-check">
			<input class="form-check-input" type="checkbox" id="unit_active" name="active" value="1" checked>
			<label class="form-check-label" for="unit_active">Aktif</label>
		</div>
		<?= ui_modal_actions('Simpan unit') ?>
	</form>
<?= ui_modal_close() ?>

<?= ui_modal_open('modal-tambah-jabatan', 'Tambah jabatan') ?>
	<form method="post" action="<?= $base ?>/jabatan" data-once>
		<?= csrf_field() ?>
		<?= ui_input(array('name' => 'title', 'label' => 'Nama jabatan', 'maxlength' => 180, 'required' => TRUE)) ?>
		<?= ui_select(array('name' => 'unit_id', 'label' => 'Unit', 'options' => $unit_options)) ?>
		<?= ui_select(array('name' => 'parent_id', 'id' => 'position_parent_id', 'label' => 'Atasan', 'options' => $position_options)) ?>
		<?= ui_textarea(array('name' => 'duties_public', 'label' => 'Tupoksi publik', 'maxlength' => 1000, 'rows' => 2)) ?>
		<div class="form-check">
			<input class="form-check-input" type="checkbox" id="position_active" name="active" value="1" checked>
			<label class="form-check-label" for="position_active">Aktif</label>
		</div>
		<?= ui_modal_actions('Simpan jabatan') ?>
	</form>
<?= ui_modal_close() ?>

<?= ui_modal_open('modal-tambah-penugasan', 'Tambah penugasan', 'modal-lg') ?>
	<form method="post" action="<?= $base ?>/penugasan" data-once>
		<?= csrf_field() ?>
		<div class="form-row">
			<div class="col-md-6"><?= ui_select(array('name' => 'position_id', 'label' => 'Jabatan', 'options' => $active_positions, 'required' => TRUE)) ?></div>
			<div class="col-md-6"><?= ui_select(array('name' => 'person_id', 'label' => 'Orang', 'options' => $person_options)) ?></div>
		</div>
		<div class="form-row">
			<div class="col-md-6"><?= ui_select(array('name' => 'assignment_type', 'label' => 'Jenis', 'options' => $assignment_types, 'required' => TRUE)) ?></div>
			<div class="col-md-6"><?= ui_input(array('name' => 'start_date', 'label' => 'Mulai', 'type' => 'date')) ?></div>
		</div>
		<?= ui_input(array('name' => 'decree_number', 'label' => 'Nomor SK', 'maxlength' => 120,
			'help' => 'Disimpan terenkripsi, tidak tampil publik.')) ?>
		<?= ui_modal_actions('Simpan penugasan') ?>
	</form>
<?= ui_modal_close() ?>
<?php endif; ?>
