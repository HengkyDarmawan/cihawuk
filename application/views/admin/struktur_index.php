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
	<div class="card-header card-header-actions"><h2 class="h6 mb-0">Periode</h2><?php if ($can_edit): ?><?= ui_add_button('modal-tambah-periode', 'Tambah periode') ?><?php endif; ?></div>
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
</div>

<?php
$person_label = function ($person) {
	return trim(($person->title_prefix ? $person->title_prefix.' ' : '').$person->full_name.($person->title_suffix ? ', '.$person->title_suffix : ''));
};
$ep = $edit_person;
$media_select = array();
foreach ($media_options as $media) { $media_select[(string) $media->id] = $media->original_name.' — '.$media->alt_text; }
?>
<div class="card shadow-sm" id="orang">
	<div class="card-header card-header-actions"><h2 class="h6 mb-0">Orang</h2>
		<?php if ($can_edit): ?>
			<?php if ($ep): ?>
				<a class="btn btn-primary btn-add" href="<?= site_url('admin/struktur') ?>#form-orang"><i class="fas fa-plus mr-1" aria-hidden="true"></i> Tambah orang</a>
			<?php else: ?>
				<?= ui_add_button('form-orang', 'Tambah orang') ?>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<div class="table-responsive">
		<table class="table mb-0 align-middle">
			<thead><tr><th scope="col" style="width:64px">Foto</th><th scope="col">Nama</th><th scope="col">Status data</th><th scope="col">Izin foto</th><?php if ($can_edit): ?><th scope="col"><span class="sr-only">Aksi</span></th><?php endif; ?></tr></thead>
			<tbody>
			<?php foreach ($people as $person): ?>
				<?php $photo = $person->photo_media_id ? ($photos[(int) $person->photo_media_id] ?? NULL) : NULL; ?>
				<tr>
					<td>
						<?php if ($photo && $photo->storage_key): ?>
							<img class="rounded-circle" src="<?= e(media_url($photo)) ?>" alt="" width="44" height="44" style="object-fit:cover" loading="lazy">
						<?php else: ?>
							<span class="text-muted small">&mdash;</span>
						<?php endif; ?>
					</td>
					<th scope="row"><?= e($person_label($person)) ?></th>
					<td><?= e($person->data_status) ?></td>
					<td><?= $person->photo_media_id ? ((int) $person->photo_consent === 1 ? 'ada, berizin' : 'ada, tanpa izin') : 'belum ada' ?></td>
					<?php if ($can_edit): ?>
					<td class="text-right"><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('admin/struktur?orang='.rawurlencode($person->public_id)) ?>#form-orang">Ubah</a></td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($people)): ?><tr><td colspan="5" class="text-muted">Belum ada data orang.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<?php if ($can_edit): ?>
<?= ui_modal_open('modal-tambah-periode', 'Tambah periode') ?>
	<form method="post" action="<?= site_url('admin/struktur/periode/simpan') ?>" data-once>
		<?= csrf_field() ?>
		<?= ui_input(array('name' => 'name', 'label' => 'Nama periode', 'maxlength' => 160, 'required' => TRUE)) ?>
		<div class="form-row">
			<div class="col-md-6"><?= ui_input(array('name' => 'year_start', 'label' => 'Tahun mulai', 'required' => TRUE)) ?></div>
			<div class="col-md-6"><?= ui_input(array('name' => 'year_end', 'label' => 'Tahun selesai')) ?></div>
		</div>
		<?= ui_input(array('name' => 'note', 'label' => 'Catatan', 'maxlength' => 500)) ?>
		<?= ui_modal_actions('Buat periode') ?>
	</form>
<?= ui_modal_close() ?>

<?= ui_modal_open('form-orang', $ep ? 'Ubah orang: '.$person_label($ep) : 'Tambah orang', 'modal-lg', (bool) $ep) ?>
	<p class="small text-muted">Entitas orang terpisah dari akun login. Menutup akun tidak menghapus profil pejabat. Foto hanya dapat dipasang bila izin publikasinya dicatat, dan tampil publik setelah periode diterbitkan ulang.</p>
	<form method="post" action="<?= site_url('admin/struktur/orang/simpan') ?>" enctype="multipart/form-data" data-once>
		<?= csrf_field() ?>
		<?php if ($ep): ?><input type="hidden" name="public_id" value="<?= e($ep->public_id) ?>"><?php endif; ?>
		<div class="form-row">
			<div class="col-md-3"><?= ui_input(array('name' => 'title_prefix', 'label' => 'Gelar depan', 'maxlength' => 40, 'value' => $ep ? (string) $ep->title_prefix : old('title_prefix'))) ?></div>
			<div class="col-md-6"><?= ui_input(array('name' => 'full_name', 'label' => 'Nama lengkap', 'maxlength' => 180, 'required' => TRUE, 'value' => $ep ? $ep->full_name : old('full_name'))) ?></div>
			<div class="col-md-3"><?= ui_input(array('name' => 'title_suffix', 'label' => 'Gelar belakang', 'maxlength' => 60, 'value' => $ep ? (string) $ep->title_suffix : old('title_suffix'))) ?></div>
		</div>
		<?= ui_select(array('name' => 'data_status', 'label' => 'Status data', 'options' => array('draft' => 'Draft', 'reviewed' => 'Sudah direview'), 'value' => $ep ? $ep->data_status : 'draft')) ?>
		<div class="form-row">
			<div class="col-md-6"><?= ui_input(array('name' => 'foto[]', 'id' => 'foto', 'type' => 'file', 'label' => 'Unggah foto baru', 'accept' => '.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp', 'help' => 'JPG, PNG, atau WebP. Metadata EXIF dibuang otomatis.')) ?></div>
			<div class="col-md-6"><?= ui_input(array('name' => 'photo_alt', 'label' => 'Teks alternatif foto', 'maxlength' => 255, 'help' => 'Kosongkan untuk memakai "Foto {nama}".')) ?></div>
		</div>
		<?= ui_select(array('name' => 'photo_media_id', 'label' => 'Atau pilih dari pustaka media', 'options' => $media_select, 'placeholder_option' => $ep && $ep->photo_media_id ? 'Pertahankan foto sekarang' : 'Tanpa foto', 'value' => '', 'select_class' => 'custom-select')) ?>
		<div class="d-flex flex-wrap mb-3" style="gap:1.5rem">
			<div class="form-check">
				<input class="form-check-input" type="checkbox" id="photo_consent" name="photo_consent" value="1" <?= $ep && (int) $ep->photo_consent === 1 ? 'checked' : '' ?>>
				<label class="form-check-label" for="photo_consent">Izin publikasi foto sudah dicatat</label>
			</div>
			<?php if ($ep && $ep->photo_media_id): ?>
			<div class="form-check">
				<input class="form-check-input" type="checkbox" id="remove_photo" name="remove_photo" value="1">
				<label class="form-check-label" for="remove_photo">Lepas foto dari orang ini</label>
			</div>
			<?php endif; ?>
		</div>
		<?= ui_textarea(array('name' => 'bio_public', 'label' => 'Bio publik yang sudah direview', 'maxlength' => 1000, 'rows' => 3, 'value' => $ep ? (string) $ep->bio_public : old('bio_public'))) ?>
		<?= ui_modal_actions($ep ? 'Simpan perubahan' : 'Simpan orang') ?>
	</form>
<?= ui_modal_close() ?>
<?php endif; ?>
