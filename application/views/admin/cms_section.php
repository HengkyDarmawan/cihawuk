<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Form konfigurasi section dibangun dari registry; admin tidak dapat menulis HTML/CSS/JS. */
$config = $section->config;
$links = array();
foreach (array('cta', 'links') as $link_field)
{
	if ( ! empty($config[$link_field]))
	{
		$links = $config[$link_field];
		break;
	}
}
$link_field_name = isset($definition['fields']['links']) ? 'links' : (isset($definition['fields']['cta']) ? 'cta' : NULL);
$link_meta = $link_field_name ? $definition['fields'][$link_field_name] : NULL;
// Section bisa milik beranda atau halaman publik lain; tautan kembali mengikuti halamannya.
$is_home = ($page->page_key === 'home');
$back = site_url($is_home ? 'admin/cms/beranda' : 'admin/cms/halaman/'.rawurlencode($page->public_id));
$back_label = $is_home ? 'Kembali ke pengaturan beranda' : 'Kembali ke halaman';
?>
<div class="page-heading">
	<div>
		<h1><?= e($definition['label']) ?></h1>
		<p><?= e($definition['description']) ?></p>
	</div>
	<div><a class="btn btn-outline-primary btn-sm" href="<?= e($back) ?>"><?= e($back_label) ?></a></div>
</div>

<?php if ( ! $available): ?>
<div class="alert alert-warning" role="status">
	Modul untuk section ini sedang tidak aktif. Konfigurasi tetap dapat disimpan sebagai draft,
	tetapi section tidak akan ikut diterbitkan sampai modulnya diaktifkan.
</div>
<?php endif; ?>

<?= ui_error_summary($this->form_errors) ?>

<form class="card shadow-sm" method="post" action="<?= site_url('admin/cms/section/'.rawurlencode($section->public_id).'/simpan') ?>" data-once>
	<div class="card-body">
		<?= csrf_field() ?>
		<?= ui_input(array('name' => 'title', 'label' => 'Judul section', 'maxlength' => 180, 'value' => $section->title)) ?>
		<?= ui_input(array('name' => 'subtitle', 'label' => 'Label kecil di atas judul', 'maxlength' => 300, 'value' => $section->subtitle)) ?>
		<?= ui_select(array('name' => 'layout_variant', 'label' => 'Varian layout', 'required' => TRUE,
			'options' => $definition['layouts'], 'value' => $section->layout_variant)) ?>

		<?php foreach ($definition['fields'] as $field => $meta): ?>
			<?php if ($meta['type'] === 'links') { continue; } ?>
			<?php $value = $config[$field] ?? ($meta['default'] ?? ''); ?>

			<?php if ($meta['type'] === 'text'): ?>
				<?= ui_input(array('name' => $field, 'label' => $meta['label'], 'maxlength' => $meta['max'] ?? 255, 'value' => $value, 'required' => ! empty($meta['required']), 'help' => $meta['help'] ?? NULL)) ?>

			<?php elseif ($meta['type'] === 'textarea'): ?>
				<?= ui_textarea(array('name' => $field, 'label' => $meta['label'], 'maxlength' => $meta['max'] ?? 1000, 'rows' => 4, 'value' => $value, 'required' => ! empty($meta['required']), 'help' => $meta['help'] ?? NULL)) ?>

			<?php elseif ($meta['type'] === 'select'): ?>
				<?= ui_select(array('name' => $field, 'label' => $meta['label'], 'options' => $meta['options'], 'value' => $value, 'required' => ! empty($meta['required']), 'help' => $meta['help'] ?? NULL)) ?>

			<?php elseif ($meta['type'] === 'number'): ?>
				<?= ui_input(array('name' => $field, 'label' => $meta['label'], 'type' => 'number', 'value' => $value, 'required' => ! empty($meta['required']),
					'min' => $meta['min'] ?? NULL, 'max' => $meta['max'] ?? NULL, 'help' => $meta['help'] ?? NULL)) ?>

			<?php elseif ($meta['type'] === 'media'): ?>
				<?= ui_select(array('name' => $field, 'label' => $meta['label'], 'options' => $media, 'value' => (string) $value, 'help' => $meta['help'] ?? NULL)) ?>

			<?php elseif ($meta['type'] === 'indicators'): ?>
				<div class="form-group">
					<label for="<?= e($field) ?>"><?= e($meta['label']) ?></label>
					<select class="form-control form-select" id="<?= e($field) ?>" name="<?= e($field) ?>[]" multiple size="6">
						<?php foreach ($indicators as $code => $label): ?>
							<option value="<?= e($code) ?>" <?= in_array($code, (array) $value, TRUE) ? 'selected' : '' ?>><?= e($label) ?></option>
						<?php endforeach; ?>
					</select>
					<small class="form-text">Maksimal <?= (int) ($meta['max_items'] ?? 4) ?> indikator, semuanya harus punya nilai terbit pada tahun yang dipilih.</small>
				</div>

			<?php elseif ($meta['type'] === 'dataset'): ?>
				<?= ui_select(array('name' => $field, 'label' => $meta['label'], 'options' => $datasets, 'value' => (string) $value,
					'required' => ! empty($meta['required']),
					'help' => 'Hanya dataset yang sudah terbit yang muncul di sini. Setelah mengganti dataset, simpan dulu agar daftar indikatornya ikut berubah.')) ?>

			<?php elseif ($meta['type'] === 'dataset_series'): ?>
				<div class="form-group">
					<label for="<?= e($field) ?>"><?= e($meta['label']) ?></label>
					<select class="form-control form-select" id="<?= e($field) ?>" name="<?= e($field) ?>[]" multiple size="6">
						<?php foreach ($dataset_series as $code => $label): ?>
							<option value="<?= e($code) ?>" <?= in_array($code, (array) $value, TRUE) ? 'selected' : '' ?>><?= e($label) ?></option>
						<?php endforeach; ?>
					</select>
					<?php if (empty($dataset_series)): ?>
						<small class="form-text">Pilih dataset lalu simpan untuk memuat daftar indikatornya.</small>
					<?php else: ?>
						<small class="form-text">Maksimal <?= (int) ($meta['max_items'] ?? 4) ?> indikator dari dataset di atas. Tahun, satuan, sumber dan tanggal terbit mengikuti dataset.</small>
					<?php endif; ?>
				</div>

			<?php elseif ($meta['type'] === 'ids'): ?>
				<?php $options = ($meta['entity'] ?? '') === 'potential' ? $potentials : $galleries; ?>
				<div class="form-group">
					<label for="<?= e($field) ?>"><?= e($meta['label']) ?></label>
					<select class="form-control form-select" id="<?= e($field) ?>" name="<?= e($field) ?>[]" <?= ((int) ($meta['max_items'] ?? 12) > 1) ? 'multiple size="6"' : '' ?>>
						<?php if ((int) ($meta['max_items'] ?? 12) === 1): ?><option value="">— pilih —</option><?php endif; ?>
						<?php foreach ($options as $id => $label): ?>
							<option value="<?= e($id) ?>" <?= in_array((int) $id, array_map('intval', (array) $value), TRUE) ? 'selected' : '' ?>><?= e($label) ?></option>
						<?php endforeach; ?>
					</select>
					<small class="form-text">Hanya entitas yang sudah terbit dan terverifikasi yang muncul di sini.</small>
				</div>
			<?php endif; ?>
		<?php endforeach; ?>

		<?php if ($link_field_name): ?>
		<fieldset class="form-group">
			<legend class="form-label"><?= e($link_meta['label']) ?></legend>
			<p class="small text-muted">
				Maksimal <?= (int) ($link_meta['max_items'] ?? 8) ?> tautan. Gunakan path internal seperti <code>/lapor</code>
				atau URL <code>https://</code>. Alamat lain ditolak server.
			</p>
			<?php for ($i = 0; $i < (int) ($link_meta['max_items'] ?? 8); $i++):
				$link = $links[$i] ?? array('label' => '', 'url' => '', 'description' => ''); ?>
			<div class="form-row">
				<div class="col-md-3 mb-2">
					<label class="sr-only" for="link_label_<?= $i ?>">Label tautan <?= $i + 1 ?></label>
					<input class="form-control form-control-sm" id="link_label_<?= $i ?>" name="link_label[]" maxlength="80" value="<?= e($link['label'] ?? '') ?>" placeholder="Label">
				</div>
				<div class="col-md-4 mb-2">
					<label class="sr-only" for="link_url_<?= $i ?>">Alamat tautan <?= $i + 1 ?></label>
					<input class="form-control form-control-sm" id="link_url_<?= $i ?>" name="link_url[]" maxlength="300" value="<?= e($link['url'] ?? '') ?>" placeholder="/lapor">
				</div>
				<div class="col-md-5 mb-2">
					<label class="sr-only" for="link_desc_<?= $i ?>">Keterangan tautan <?= $i + 1 ?></label>
					<input class="form-control form-control-sm" id="link_desc_<?= $i ?>" name="link_description[]" maxlength="160" value="<?= e($link['description'] ?? '') ?>" placeholder="Keterangan singkat (opsional)">
				</div>
			</div>
			<?php endfor; ?>
		</fieldset>
		<?php endif; ?>
	</div>
	<div class="card-footer bg-white d-flex justify-content-between">
		<a class="btn btn-outline-primary" href="<?= e($back) ?>">Batal</a>
		<button class="btn btn-primary" type="submit">Simpan draft</button>
	</div>
</form>
