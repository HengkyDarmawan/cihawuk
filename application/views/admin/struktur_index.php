<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Daftar periode struktur dan data orang. Akun login adalah entitas terpisah. */
?>
<div class="page-heading">
	<div>
		<h1>Struktur Organisasi</h1>
		<p>Periode, unit, jabatan, orang, dan penugasan disimpan terpisah supaya pejabat dapat berganti tanpa menyusun ulang struktur.</p>
	</div>
</div>

<?= ui_error_summary($this->form_errors) ?>

<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Periode</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Nama</th><th scope="col">Tahun</th><th scope="col">Status</th><th scope="col">Tampilan publik</th></tr></thead>
			<tbody>
			<?php foreach ($periods as $period): ?>
				<tr>
					<th scope="row"><a href="<?= site_url('admin/struktur/periode/'.rawurlencode($period->public_id)) ?>"><?= e($period->name) ?></a></th>
					<td><?= (int) $period->year_start ?><?= $period->year_end ? '&ndash;'.(int) $period->year_end : '' ?></td>
					<td><?= e($period->status) ?></td>
					<td><?= (int) $period->is_public_default === 1 ? '<span class="badge badge-success">bawaan</span>' : '&mdash;' ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($periods)): ?><tr><td colspan="4" class="text-muted">Belum ada periode.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php if ($can_edit): ?>
	<div class="card-body border-top">
		<h3 class="h6">Tambah periode</h3>
		<form method="post" action="<?= site_url('admin/struktur/periode/simpan') ?>" data-once>
			<?= csrf_field() ?>
			<div class="form-row">
				<div class="col-md-5"><?= ui_input(array('name' => 'name', 'label' => 'Nama periode', 'maxlength' => 160, 'required' => TRUE)) ?></div>
				<div class="col-md-2"><?= ui_input(array('name' => 'year_start', 'label' => 'Tahun mulai', 'required' => TRUE)) ?></div>
				<div class="col-md-2"><?= ui_input(array('name' => 'year_end', 'label' => 'Tahun selesai')) ?></div>
				<div class="col-md-3"><?= ui_input(array('name' => 'note', 'label' => 'Catatan', 'maxlength' => 500)) ?></div>
			</div>
			<button class="btn btn-primary" type="submit">Buat periode</button>
		</form>
	</div>
	<?php endif; ?>
</div>

<div class="card shadow-sm">
	<div class="card-header"><h2 class="h6 mb-0">Orang</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Nama</th><th scope="col">Status data</th><th scope="col">Foto</th></tr></thead>
			<tbody>
			<?php foreach ($people as $person): ?>
				<tr>
					<th scope="row"><?= e(trim(($person->title_prefix ? $person->title_prefix.' ' : '').$person->full_name.($person->title_suffix ? ', '.$person->title_suffix : ''))) ?></th>
					<td><?= e($person->data_status) ?></td>
					<td><?= $person->photo_media_id ? ((int) $person->photo_consent === 1 ? 'ada, berizin' : 'ada, tanpa izin') : 'belum ada' ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($people)): ?><tr><td colspan="3" class="text-muted">Belum ada data orang.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php if ($can_edit): ?>
	<div class="card-body border-top">
		<h3 class="h6">Tambah orang</h3>
		<p class="small text-muted">Entitas orang terpisah dari akun login. Menutup akun tidak menghapus profil pejabat.</p>
		<form method="post" action="<?= site_url('admin/struktur/orang/simpan') ?>" data-once>
			<?= csrf_field() ?>
			<div class="form-row">
				<div class="col-md-2"><?= ui_input(array('name' => 'title_prefix', 'label' => 'Gelar depan', 'maxlength' => 40)) ?></div>
				<div class="col-md-5"><?= ui_input(array('name' => 'full_name', 'label' => 'Nama lengkap', 'maxlength' => 180, 'required' => TRUE)) ?></div>
				<div class="col-md-3"><?= ui_input(array('name' => 'title_suffix', 'label' => 'Gelar belakang', 'maxlength' => 60)) ?></div>
				<div class="col-md-2 d-flex align-items-center">
					<div class="form-check mt-3">
						<input class="form-check-input" type="checkbox" id="photo_consent" name="photo_consent" value="1">
						<label class="form-check-label" for="photo_consent">Izin foto dicatat</label>
					</div>
				</div>
			</div>
			<?= ui_textarea(array('name' => 'bio_public', 'label' => 'Bio publik yang sudah direview', 'maxlength' => 1000, 'rows' => 3)) ?>
			<button class="btn btn-outline-primary" type="submit">Simpan orang</button>
		</form>
	</div>
	<?php endif; ?>
</div>
