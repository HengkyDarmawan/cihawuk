<?php defined('BASEPATH') OR exit('No direct script access allowed');
$media_select = array();
foreach ($media_options as $media) { $media_select[(string) $media->id] = $media->original_name.' — '.$media->alt_text; }
$mission_lines = ! empty($mission) ? $mission : array('');
?>
<div class="page-heading">
	<div>
		<h1>Profil desa</h1>
		<p>Identitas, sejarah, visi misi, kontak, dan jam pelayanan yang tampil pada situs publik.</p>
	</div>
	<a class="btn btn-outline-primary" href="<?= site_url('admin/konten') ?>">Kembali</a>
</div>

<?php if ( ! $profile): ?>
	<div class="alert alert-warning">Data profil desa belum tersedia. Jalankan <code>php public/index.php tools seed</code> terlebih dahulu.</div>
<?php else: ?>
<form method="post" action="<?= site_url('admin/konten/profil') ?>" novalidate data-once>
	<?= csrf_field() ?>
	<div class="row">
		<div class="col-lg-8">
			<div class="card shadow-sm mb-4">
				<div class="card-header"><h2>Identitas &amp; narasi</h2></div>
				<div class="card-body">
					<dl class="dl-grid mb-3">
						<dt>Desa</dt><dd><?= e($profile->village_name) ?></dd>
						<dt>Kecamatan</dt><dd><?= e($profile->district) ?></dd>
						<dt>Kabupaten</dt><dd><?= e($profile->regency) ?></dd>
						<dt>Kode PUM</dt><dd><?= e($profile->pum_code ?: '—') ?></dd>
					</dl>
					<?= ui_textarea(array('name' => 'summary', 'label' => 'Ringkasan profil', 'rows' => 3, 'maxlength' => 2000, 'value' => $profile->summary)) ?>
					<?= ui_textarea(array('name' => 'history_html', 'label' => 'Sejarah (HTML sederhana)', 'rows' => 8, 'value' => $profile->history_html,
						'help' => 'Disanitasi di server. Sebutkan sumber dan tahun bila mengutip dokumen.')) ?>
					<?= ui_textarea(array('name' => 'vision_official', 'label' => 'Visi (teks resmi)', 'rows' => 3, 'maxlength' => 2000, 'value' => $profile->vision_official,
						'help' => 'Salin persis dari dokumen resmi. Kosongkan bila belum tersedia.')) ?>
					<?= ui_textarea(array('name' => 'vision_summary', 'label' => 'Visi (ringkasan editorial)', 'rows' => 3, 'maxlength' => 2000, 'value' => $profile->vision_summary)) ?>

					<fieldset class="form-group">
						<legend class="col-form-label p-0">Misi</legend>
						<div id="mission-list">
							<?php foreach ($mission_lines as $i => $line): ?>
							<div class="input-group mb-2">
								<label class="sr-only" for="mission-<?= (int) $i ?>">Misi <?= (int) $i + 1 ?></label>
								<input class="form-control" id="mission-<?= (int) $i ?>" type="text" name="mission[]" maxlength="300" value="<?= e($line) ?>">
							</div>
							<?php endforeach; ?>
							<?php for ($i = count($mission_lines); $i < count($mission_lines) + 2; $i++): ?>
							<div class="input-group mb-2">
								<label class="sr-only" for="mission-<?= (int) $i ?>">Misi baru</label>
								<input class="form-control" id="mission-<?= (int) $i ?>" type="text" name="mission[]" maxlength="300" value="" placeholder="Tambah butir misi">
							</div>
							<?php endfor; ?>
						</div>
						<small class="form-text">Butir kosong diabaikan saat menyimpan.</small>
					</fieldset>

					<?= ui_textarea(array('name' => 'greeting_html', 'label' => 'Sambutan (HTML sederhana)', 'rows' => 5, 'value' => $profile->greeting_html)) ?>
				</div>
			</div>

			<div class="card shadow-sm mb-4">
				<div class="card-header"><h2>Kontak &amp; pelayanan</h2></div>
				<div class="card-body">
					<?= ui_input(array('name' => 'office_address', 'label' => 'Alamat kantor desa', 'maxlength' => 255, 'value' => $profile->office_address)) ?>
					<div class="form-row">
						<div class="col-md-6"><?= ui_input(array('name' => 'contact_phone', 'label' => 'Telepon', 'maxlength' => 30, 'value' => $contacts['phone'] ?? '')) ?></div>
						<div class="col-md-6"><?= ui_input(array('name' => 'contact_email', 'label' => 'Email', 'type' => 'email', 'maxlength' => 191, 'value' => $contacts['email'] ?? '')) ?></div>
					</div>
					<div class="custom-control custom-checkbox mb-3">
						<input class="custom-control-input" type="checkbox" id="contact_confirmed" name="contact_confirmed" value="1" <?= ! empty($contacts['confirmed']) ? 'checked' : '' ?>>
						<label class="custom-control-label" for="contact_confirmed">Kontak sudah dikonfirmasi kantor desa (baru ditampilkan publik bila dicentang)</label>
					</div>
					<?= ui_input(array('name' => 'service_hours', 'label' => 'Jam pelayanan', 'maxlength' => 150, 'value' => $hours['label'] ?? '')) ?>
					<div class="custom-control custom-checkbox mb-3">
						<input class="custom-control-input" type="checkbox" id="service_hours_example" name="service_hours_example" value="1" <?= ! empty($hours['is_example']) ? 'checked' : '' ?>>
						<label class="custom-control-label" for="service_hours_example">Tandai sebagai jadwal contoh (belum dikonfirmasi)</label>
					</div>
					<?= ui_textarea(array('name' => 'seo_description', 'label' => 'Deskripsi SEO', 'rows' => 2, 'maxlength' => 300, 'value' => $profile->seo_description)) ?>
				</div>
			</div>
		</div>

		<div class="col-lg-4">
			<div class="card shadow-sm mb-4">
				<div class="card-header"><h2>Publikasi</h2></div>
				<div class="card-body">
					<?php $statuses = $can_publish ? $publication_statuses : array_intersect_key($publication_statuses, array('draft' => 1, 'in_review' => 1)); ?>
					<?= ui_select(array('name' => 'publication_status', 'label' => 'Status', 'options' => $statuses, 'value' => $profile->publication_status,
						'help' => $can_publish ? NULL : 'Menerbitkan memerlukan izin content.publish.')) ?>
					<button class="btn btn-primary btn-block" type="submit"><i class="fas fa-save mr-1" aria-hidden="true"></i> Simpan profil</button>
				</div>
			</div>

			<div class="card shadow-sm mb-4">
				<div class="card-header"><h2>Media</h2></div>
				<div class="card-body">
					<?= ui_select(array('name' => 'logo_media_id', 'label' => 'Logo desa', 'options' => $media_select, 'placeholder_option' => 'Tanpa logo', 'value' => (string) $profile->logo_media_id)) ?>
					<?= ui_select(array('name' => 'profile_media_id', 'label' => 'Foto profil/lanskap', 'options' => $media_select, 'placeholder_option' => 'Tanpa foto', 'value' => (string) $profile->profile_media_id)) ?>
				</div>
			</div>

			<div class="card shadow-sm">
				<div class="card-header"><h2>Sumber &amp; catatan review</h2></div>
				<div class="card-body">
					<?= ui_input(array('name' => 'source_note', 'label' => 'Catatan sumber', 'maxlength' => 500, 'value' => $profile->source_note)) ?>
					<?= ui_textarea(array('name' => 'review_note', 'label' => 'Catatan review (internal)', 'rows' => 6, 'maxlength' => 2000, 'value' => $profile->review_note,
						'help' => 'Terlihat pada mode pratinjau, tidak tampil pada halaman publik terbit.')) ?>
				</div>
			</div>
		</div>
	</div>
</form>
<?php endif; ?>
