<?php defined('BASEPATH') OR exit('No direct script access allowed');
$value_of = function ($name, $field) use ($item) {
	$posted = old($name, NULL);
	if ($posted !== NULL && $posted !== '')
	{
		return $posted;
	}
	if ($item && property_exists($item, $name))
	{
		return $item->{$name};
	}
	return '';
};
?>
<div class="page-heading">
	<div>
		<h1><?= $item ? 'Ubah' : 'Buat' ?> <?= e(strtolower($schema['singular'])) ?></h1>
		<p><?= strip_tags($schema['label']) ?><?= $item && $schema['publishable'] ? ' · status saat ini: '.e(config_label('publication_statuses', $item->publication_status)) : '' ?></p>
	</div>
	<a class="btn btn-outline-primary" href="<?= site_url('admin/konten/'.$type) ?>">Kembali ke daftar</a>
</div>

<?php if ( ! empty($save_error)): ?><div class="alert alert-danger" role="alert"><?= e($save_error) ?></div><?php endif; ?>
<?= ui_error_summary() ?>

<div class="row">
	<div class="col-lg-8">
		<form class="card shadow-sm" method="post" action="<?= site_url('admin/konten/'.$type.'/simpan') ?>" novalidate data-once>
			<div class="card-body">
				<?= csrf_field() ?>
				<?php if ($item): ?><input type="hidden" name="id" value="<?= (int) $item->id ?>"><?php endif; ?>

				<?php foreach ($schema['fields'] as $name => $field):
					$value = $value_of($name, $field);
					switch ($field['type']):
						case 'text':
						case 'slug': ?>
							<?= ui_input(array('name' => $name, 'label' => $field['label'], 'required' => ! empty($field['required']), 'maxlength' => $field['max'] ?? NULL, 'minlength' => $field['min'] ?? NULL, 'value' => $value, 'help' => $field['help'] ?? NULL)) ?>
						<?php break;

						case 'textarea': ?>
							<?= ui_textarea(array('name' => $name, 'label' => $field['label'], 'required' => ! empty($field['required']), 'maxlength' => $field['max'] ?? NULL, 'rows' => $field['rows'] ?? 3, 'value' => $value, 'help' => $field['help'] ?? NULL)) ?>
						<?php break;

						case 'richtext': ?>
							<?= ui_textarea(array('name' => $name, 'label' => $field['label'], 'required' => ! empty($field['required']), 'rows' => 12, 'value' => $value,
								'help' => 'HTML sederhana diizinkan: paragraf, heading, daftar, tautan, kutipan, tabel, dan gambar dari Media desa. Tag lain dibuang server saat disimpan.')) ?>
						<?php break;

						case 'select': ?>
							<?= ui_select(array('name' => $name, 'label' => $field['label'], 'required' => ! empty($field['required']),
								'options' => $field['options'] ?? app_config($field['options_config'], array()),
								'placeholder_option' => empty($field['required']) ? '—' : NULL, 'value' => $value)) ?>
						<?php break;

						case 'category':
							$options = array();
							foreach ($category_options[$name] ?? array() as $cat) { $options[(string) $cat->id] = $cat->name; } ?>
							<?= ui_select(array('name' => $name, 'label' => $field['label'], 'required' => ! empty($field['required']), 'options' => $options, 'placeholder_option' => 'Pilih kategori…', 'value' => (string) $value)) ?>
						<?php break;

						case 'position':
							$options = array();
							foreach ($positions as $pos) { $options[(string) $pos->id] = $pos->title; } ?>
							<?= ui_select(array('name' => $name, 'label' => $field['label'], 'required' => TRUE, 'options' => $options, 'placeholder_option' => 'Pilih jabatan…', 'value' => (string) $value)) ?>
						<?php break;

						case 'menu_parent':
							$options = array();
							foreach ($menu_parents as $parent) { if ( ! $item OR (int) $parent->id !== (int) $item->id) { $options[(string) $parent->id] = $parent->menu_key.' · '.$parent->label; } } ?>
							<?= ui_select(array('name' => $name, 'label' => $field['label'], 'options' => $options, 'placeholder_option' => 'Tanpa induk (menu tingkat 1)', 'value' => (string) $value)) ?>
						<?php break;

						case 'media':
							$options = array();
							foreach ($media_options as $media) { $options[(string) $media->id] = $media->original_name.' — '.$media->alt_text; } ?>
							<?= ui_select(array('name' => $name, 'label' => $field['label'], 'required' => ! empty($field['required']), 'options' => $options,
								'placeholder_option' => 'Tanpa media', 'value' => (string) $value, 'select_class' => 'custom-select',
								'help' => ($field['help'] ?? 'Hanya media dengan status hak publikasi jelas yang tersedia.'))) ?>
						<?php break;

						case 'media_multi': ?>
							<div class="form-group">
								<label for="gallery_items">Foto album</label>
								<select class="form-control" id="gallery_items" name="gallery_items[]" multiple size="8" data-select2>
									<?php foreach ($media_options as $media): ?>
										<option value="<?= (int) $media->id ?>" <?= in_array((int) $media->id, $gallery_items, TRUE) ? 'selected' : '' ?>><?= e($media->original_name) ?> — <?= e($media->alt_text) ?></option>
									<?php endforeach; ?>
								</select>
								<small class="form-text">Tahan Ctrl/Command untuk memilih beberapa foto. Urutan mengikuti urutan daftar.</small>
							</div>
						<?php break;

						case 'checkbox': ?>
							<div class="custom-control custom-checkbox mb-3">
								<input class="custom-control-input" type="checkbox" id="<?= e($name) ?>" name="<?= e($name) ?>" value="1" <?= $value ? 'checked' : '' ?>>
								<label class="custom-control-label" for="<?= e($name) ?>"><?= e($field['label']) ?></label>
							</div>
						<?php break;

						case 'number': ?>
							<?= ui_input(array('name' => $name, 'label' => $field['label'], 'type' => 'number', 'min' => $field['min'] ?? NULL, 'max' => $field['max'] ?? NULL, 'value' => (string) $value, 'required' => ! empty($field['required']))) ?>
						<?php break;

						case 'date': ?>
							<?= ui_input(array('name' => $name, 'label' => $field['label'], 'type' => 'date', 'value' => (string) $value, 'required' => ! empty($field['required']))) ?>
						<?php break;

						case 'datetime': ?>
							<?= ui_input(array('name' => $name, 'label' => $field['label'], 'type' => 'datetime-local', 'value' => $value ? utc_to_local_input($value) : '', 'required' => ! empty($field['required']), 'help' => ($field['help'] ?? 'Waktu dalam WIB.'))) ?>
						<?php break;

						case 'url_internal': ?>
							<?= ui_input(array('name' => $name, 'label' => $field['label'], 'required' => TRUE, 'maxlength' => 500, 'value' => (string) $value, 'help' => $field['help'] ?? NULL)) ?>
						<?php break;

						case 'cta':
							$cta = $value ? json_decode((string) $value, TRUE) : array(); ?>
							<fieldset class="form-group border rounded p-3">
								<legend class="col-form-label p-0 h6"><?= e($field['label']) ?></legend>
								<?= ui_input(array('name' => $name.'_label', 'label' => 'Teks tombol', 'maxlength' => 60, 'value' => $cta['label'] ?? '')) ?>
								<?= ui_input(array('name' => $name.'_url', 'label' => 'Tautan tombol', 'maxlength' => 500, 'value' => $cta['url'] ?? '', 'help' => 'Path internal seperti /layanan, atau URL http(s).')) ?>
							</fieldset>
						<?php break;
					endswitch;
				endforeach; ?>
			</div>
			<div class="card-footer bg-white d-flex justify-content-between">
				<a class="btn btn-outline-secondary" href="<?= site_url('admin/konten/'.$type) ?>">Batal</a>
				<button class="btn btn-primary" type="submit"><i class="fas fa-save mr-1" aria-hidden="true"></i> Simpan</button>
			</div>
		</form>
	</div>

	<div class="col-lg-4">
		<?php if ($item && $schema['publishable']): ?>
		<div class="card shadow-sm mb-4">
			<div class="card-header"><h2>Status publikasi</h2></div>
			<div class="card-body">
				<p>Status saat ini: <span class="chip-flag <?= $item->publication_status === 'published' ? 'is-info' : '' ?>"><?= e(config_label('publication_statuses', $item->publication_status)) ?></span></p>
				<div class="d-grid gap-2">
					<?php
					$transitions = array(
						'draft' => array('label' => 'Kembalikan ke draft', 'class' => 'btn-outline-secondary', 'need_publish' => FALSE),
						'in_review' => array('label' => 'Kirim untuk review', 'class' => 'btn-outline-primary', 'need_publish' => FALSE),
						'published' => array('label' => 'Terbitkan', 'class' => 'btn-primary', 'need_publish' => TRUE),
						'archived' => array('label' => 'Arsipkan', 'class' => 'btn-outline-danger', 'need_publish' => TRUE),
					);
					foreach ($transitions as $status => $meta):
						if ($status === $item->publication_status) { continue; }
						if ($meta['need_publish'] && ! $can_publish) { continue; } ?>
						<form method="post" action="<?= site_url('admin/konten/'.$type.'/'.(int) $item->id.'/status') ?>" class="mb-2"
							<?= $status === 'archived' ? 'data-confirm="Arsipkan konten ini? Konten hilang dari halaman publik namun tidak dihapus permanen." data-confirm-ok="Arsipkan"' : '' ?>>
							<?= csrf_field() ?>
							<input type="hidden" name="status" value="<?= e($status) ?>">
							<button class="btn <?= e($meta['class']) ?> btn-block" type="submit"><?= e($meta['label']) ?></button>
						</form>
					<?php endforeach; ?>
					<?php if ( ! $can_publish): ?>
						<p class="small text-muted mb-0">Menerbitkan dan mengarsipkan memerlukan izin <code>content.publish</code>.</p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<div class="card shadow-sm">
			<div class="card-header"><h2>Catatan</h2></div>
			<div class="card-body small">
				<ul class="pl-3 mb-0">
					<li>Isi rich text disanitasi di server dengan daftar tag yang diizinkan.</li>
					<li>Gambar hanya boleh berasal dari menu Media desa (path <code>/media/…</code>).</li>
					<li>Mengubah slug otomatis membuat pengalihan 301 dari slug lama.</li>
					<li>Konten hanya tampil di situs publik setelah berstatus <strong>Terbit</strong>.</li>
				</ul>
			</div>
		</div>
	</div>
</div>
