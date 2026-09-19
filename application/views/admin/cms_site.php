<?php defined('BASEPATH') OR exit('No direct script access allowed');
$can = function ($p) use ($permissions) { return in_array($p, $permissions, TRUE); };
$identity = $draft['identity'];
$theme = $draft['theme'];
$radius_options = array();
foreach ($radius_presets as $key => $preset) { $radius_options[$key] = $preset['label']; }
$font_options = $fonts;
?>
<div class="page-heading">
	<div>
		<h1>Identitas dan Tema Situs</h1>
		<p>Isian di bawah adalah <strong>draft</strong>. Situs publik memakai revisi terakhir yang diterbitkan.</p>
	</div>
	<div class="text-right">
		<span class="chip-flag <?= $dirty ? 'is-warning' : 'is-info' ?>"><?= $dirty ? 'Ada perubahan belum terbit' : 'Sama dengan yang terbit' ?></span>
		<span class="d-block small text-muted">Revisi publik: <?= (int) $published['revision_no'] ?></span>
	</div>
</div>

<?= ui_error_summary($this->form_errors) ?>

<form method="post" action="<?= site_url('admin/cms/situs/simpan') ?>" data-once>
	<?= csrf_field() ?>
	<div class="row">
		<div class="col-lg-6">
			<div class="card shadow-sm mb-4">
				<div class="card-header"><h2 class="h6 mb-0">Identitas</h2></div>
				<div class="card-body">
					<?= ui_input(array('name' => 'site_name', 'label' => 'Nama situs', 'required' => TRUE, 'maxlength' => 120, 'value' => $identity['site_name'])) ?>
					<div class="row">
						<div class="col-sm-6"><?= ui_input(array('name' => 'village_name', 'label' => 'Nama desa', 'maxlength' => 80, 'value' => $identity['village_name'])) ?></div>
						<div class="col-sm-6"><?= ui_input(array('name' => 'district', 'label' => 'Kecamatan', 'maxlength' => 80, 'value' => $identity['district'])) ?></div>
						<div class="col-sm-6"><?= ui_input(array('name' => 'regency', 'label' => 'Kabupaten', 'maxlength' => 80, 'value' => $identity['regency'])) ?></div>
						<div class="col-sm-6"><?= ui_input(array('name' => 'province', 'label' => 'Provinsi', 'maxlength' => 80, 'value' => $identity['province'])) ?></div>
					</div>
					<?= ui_textarea(array('name' => 'office_address', 'label' => 'Alamat kantor', 'maxlength' => 250, 'rows' => 2, 'value' => $identity['office_address'])) ?>
					<div class="row">
						<div class="col-sm-6"><?= ui_input(array('name' => 'contact_phone', 'label' => 'Telepon publik', 'maxlength' => 40, 'value' => $identity['contact_phone'])) ?></div>
						<div class="col-sm-6"><?= ui_input(array('name' => 'contact_email', 'label' => 'Email publik', 'maxlength' => 120, 'value' => $identity['contact_email'])) ?></div>
					</div>
					<?= ui_input(array('name' => 'service_hours', 'label' => 'Jam pelayanan', 'maxlength' => 160, 'value' => $identity['service_hours'],
						'help' => 'Contoh: Senin–Jumat 08.00–15.00 WIB.')) ?>
					<?= ui_input(array('name' => 'footer_text', 'label' => 'Teks footer', 'maxlength' => 300, 'value' => $identity['footer_text'])) ?>
					<?= ui_input(array('name' => 'privacy_contact', 'label' => 'Kontak kebijakan privasi', 'maxlength' => 160, 'value' => $identity['privacy_contact'])) ?>
				</div>
			</div>

			<div class="card shadow-sm mb-4">
				<div class="card-header"><h2 class="h6 mb-0">Media dan media sosial</h2></div>
				<div class="card-body">
					<?= ui_select(array('name' => 'logo_media_id', 'label' => 'Logo', 'options' => $media, 'value' => (string) $identity['logo_media_id'],
						'placeholder_option' => 'Tidak memakai logo', 'help' => 'Pastikan memakai lambang resmi desa, bukan lambang kabupaten.')) ?>
					<?= ui_select(array('name' => 'favicon_media_id', 'label' => 'Favicon', 'options' => $media, 'value' => (string) $identity['favicon_media_id'], 'placeholder_option' => 'Bawaan aplikasi')) ?>
					<?= ui_select(array('name' => 'social_image_media_id', 'label' => 'Gambar berbagi (social image)', 'options' => $media, 'value' => (string) $identity['social_image_media_id'], 'placeholder_option' => 'Tidak ada')) ?>
					<?php foreach (array('facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'whatsapp' => 'WhatsApp') as $key => $label): ?>
						<?= ui_input(array('name' => 'social_'.$key, 'label' => $label, 'maxlength' => 300, 'value' => $identity['social'][$key])) ?>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

		<div class="col-lg-6">
			<div class="card shadow-sm mb-4">
				<div class="card-header"><h2 class="h6 mb-0">Tema</h2></div>
				<div class="card-body">
					<p class="small text-muted">
						Hanya token ini yang dapat diubah dari dashboard. Warna wajib format heksadesimal dan
						harus lolos kontras minimum; CSS bebas tetap hanya lewat source code.
					</p>
					<div class="row">
						<div class="col-sm-6"><?= ui_input(array('name' => 'color_primary', 'label' => 'Warna utama', 'maxlength' => 7, 'value' => $theme['color_primary'])) ?></div>
						<div class="col-sm-6"><?= ui_input(array('name' => 'color_secondary', 'label' => 'Warna utama gelap', 'maxlength' => 7, 'value' => $theme['color_secondary'])) ?></div>
						<div class="col-sm-6"><?= ui_input(array('name' => 'color_accent', 'label' => 'Warna aksen', 'maxlength' => 7, 'value' => $theme['color_accent'])) ?></div>
						<div class="col-sm-6"><?= ui_input(array('name' => 'color_surface', 'label' => 'Warna latar', 'maxlength' => 7, 'value' => $theme['color_surface'])) ?></div>
					</div>
					<div class="row">
						<div class="col-sm-6"><?= ui_select(array('name' => 'font_body', 'label' => 'Font isi', 'options' => $font_options, 'value' => $theme['font_body'])) ?></div>
						<div class="col-sm-6"><?= ui_select(array('name' => 'font_display', 'label' => 'Font judul', 'options' => $font_options, 'value' => $theme['font_display'])) ?></div>
						<div class="col-sm-6"><?= ui_select(array('name' => 'radius', 'label' => 'Preset sudut', 'options' => $radius_options, 'value' => $theme['radius'])) ?></div>
						<div class="col-sm-6"><?= ui_select(array('name' => 'hero_mode', 'label' => 'Mode hero bawaan', 'options' => $hero_modes, 'value' => $theme['hero_mode'])) ?></div>
					</div>
					<?= ui_select(array('name' => 'placeholder_media_id', 'label' => 'Gambar placeholder', 'options' => $media, 'value' => (string) $theme['placeholder_media_id'], 'placeholder_option' => 'Bawaan aplikasi')) ?>
					<div class="form-check">
						<input class="form-check-input" type="checkbox" id="reduced_motion_default" name="reduced_motion_default" value="1" <?= $theme['reduced_motion_default'] ? 'checked' : '' ?>>
						<label class="form-check-label" for="reduced_motion_default">Kurangi animasi secara bawaan</label>
					</div>
				</div>
				<div class="card-footer bg-white text-right">
					<button class="btn btn-primary btn-sm" type="submit">Simpan draft</button>
				</div>
			</div>
		</div>
	</div>
</form>

<div class="row">
	<div class="col-lg-6">
		<?php if ($can('cms.page.publish')): ?>
		<div class="card shadow-sm mb-4">
			<div class="card-header"><h2 class="h6 mb-0">Terbitkan</h2></div>
			<form class="card-body" method="post" action="<?= site_url('admin/cms/situs/terbitkan') ?>" data-once
				data-confirm="Terbitkan identitas dan tema? Nama situs, kontak, logo, dan warna di situs publik akan mengikuti draft ini.">
				<?= csrf_field() ?>
				<?= ui_input(array('name' => 'reason', 'label' => 'Catatan publikasi', 'maxlength' => 200)) ?>
				<button class="btn btn-primary btn-sm" type="submit">Terbitkan sekarang</button>
			</form>
		</div>
		<?php else: ?>
		<p class="small text-muted">Anda dapat menyunting draft, tetapi penerbitan memerlukan izin penerbit.</p>
		<?php endif; ?>
	</div>

	<div class="col-lg-6">
		<div class="card shadow-sm mb-4">
			<div class="card-header"><h2 class="h6 mb-0">Riwayat publikasi</h2></div>
			<?php if (empty($snapshots)): ?>
				<div class="card-body"><p class="small text-muted mb-0">Belum pernah diterbitkan; situs memakai nilai bawaan aplikasi.</p></div>
			<?php else: ?>
			<div class="table-responsive">
				<table class="table table-sm mb-0">
					<caption class="sr-only">Riwayat publikasi identitas dan tema</caption>
					<thead><tr><th scope="col">Revisi</th><th scope="col">Diterbitkan</th><th scope="col">Oleh</th><th scope="col"></th></tr></thead>
					<tbody>
					<?php foreach ($snapshots as $snapshot): ?>
						<tr>
							<td><?= (int) $snapshot->revision_no ?><?= $snapshot->superseded_at === NULL ? ' <span class="chip-flag is-info">aktif</span>' : '' ?></td>
							<td class="small"><?= e(format_wib($snapshot->published_at, 'short')) ?></td>
							<td class="small text-muted"><?= e($snapshot->publisher ?: '—') ?></td>
							<td class="text-right">
								<?php if ($can('cms.page.rollback') && $snapshot->superseded_at !== NULL): ?>
								<form method="post" action="<?= site_url('admin/cms/situs/rollback') ?>"
									data-confirm="Kembalikan identitas dan tema ke revisi <?= (int) $snapshot->revision_no ?>? Draft ikut dikembalikan.">
									<?= csrf_field() ?>
									<input type="hidden" name="snapshot_id" value="<?= (int) $snapshot->id ?>">
									<input type="hidden" name="reason" value="Rollback identitas situs ke revisi <?= (int) $snapshot->revision_no ?>">
									<button class="btn btn-outline-primary btn-sm" type="submit">Kembalikan</button>
								</form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>
		</div>
	</div>
</div>
