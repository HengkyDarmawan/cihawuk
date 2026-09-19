<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Form blok profil dibangun dari registry; pengelola tidak dapat menulis HTML. */
$body = $block->body;
?>
<div class="page-heading">
	<div>
		<h1><?= e($definition['label']) ?></h1>
		<p>Halaman publik: <code>/<?= e($definition['page']) ?></code>. Menyimpan draft tidak mengubah halaman publik.</p>
	</div>
	<div><a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/profil') ?>">Kembali ke profil</a></div>
</div>

<?= ui_error_summary($this->form_errors) ?>

<?php if ($block->verification_status !== 'verified'): ?>
<div class="alert alert-warning" role="status">
	Blok ini belum diverifikasi terhadap dokumen sumber, jadi belum dapat ikut diterbitkan.
</div>
<?php endif; ?>

<form class="card shadow-sm" method="post" action="<?= site_url('admin/profil/blok/'.rawurlencode($block->public_id).'/simpan') ?>" data-once>
	<div class="card-body">
		<?= csrf_field() ?>
		<?= ui_input(array('name' => 'title', 'label' => 'Judul blok', 'maxlength' => 160, 'value' => $block->title, 'required' => TRUE)) ?>

		<?php foreach ($definition['fields'] as $field => $meta): $value = $body[$field] ?? NULL; ?>

			<?php if (in_array($meta['type'], array('text', 'code'), TRUE)): ?>
				<?= ui_input(array('name' => $field, 'label' => $meta['label'], 'maxlength' => $meta['max'] ?? 255,
					'value' => (string) $value, 'required' => ! empty($meta['required']), 'help' => $meta['help'] ?? NULL)) ?>

			<?php elseif ($meta['type'] === 'textarea'): ?>
				<?= ui_textarea(array('name' => $field, 'label' => $meta['label'], 'maxlength' => $meta['max'] ?? 2000,
					'rows' => 6, 'value' => (string) $value, 'required' => ! empty($meta['required']), 'help' => $meta['help'] ?? NULL)) ?>

			<?php elseif (in_array($meta['type'], array('number', 'decimal'), TRUE)): ?>
				<?= ui_input(array('name' => $field, 'label' => $meta['label'], 'value' => $value === NULL ? '' : (string) $value,
					'required' => ! empty($meta['required']), 'help' => $meta['help'] ?? NULL)) ?>

			<?php elseif ($meta['type'] === 'string_list'): ?>
				<fieldset class="form-group">
					<legend class="form-label"><?= e($meta['label']) ?><?= empty($meta['required']) ? '' : ' (wajib)' ?></legend>
					<p class="small text-muted">Maksimal <?= (int) ($meta['max_items'] ?? 20) ?> butir. Baris kosong diabaikan.</p>
					<?php $items = (array) $value; for ($i = 0; $i < (int) ($meta['max_items'] ?? 20); $i++): ?>
						<input class="form-control mb-2" type="text" name="<?= e($field) ?>[]" maxlength="<?= (int) ($meta['max'] ?? 400) ?>"
							value="<?= e($items[$i] ?? '') ?>" aria-label="<?= e($meta['label']) ?> baris <?= $i + 1 ?>">
					<?php endfor; ?>
				</fieldset>

			<?php elseif ($meta['type'] === 'pair_list'): ?>
				<fieldset class="form-group">
					<legend class="form-label"><?= e($meta['label']) ?></legend>
					<p class="small text-muted">Isi label dan nilainya. Baris yang keduanya kosong diabaikan.</p>
					<?php $pairs = (array) $value; for ($i = 0; $i < (int) ($meta['max_items'] ?? 20); $i++):
						$pair = $pairs[$i] ?? array('label' => '', 'value' => ''); ?>
						<div class="form-row mb-2">
							<div class="col-5"><input class="form-control" type="text" name="<?= e($field) ?>[<?= $i ?>][label]"
								maxlength="120" value="<?= e($pair['label']) ?>" aria-label="Label baris <?= $i + 1 ?>" placeholder="Label"></div>
							<div class="col-7"><input class="form-control" type="text" name="<?= e($field) ?>[<?= $i ?>][value]"
								maxlength="200" value="<?= e($pair['value']) ?>" aria-label="Nilai baris <?= $i + 1 ?>" placeholder="Nilai"></div>
						</div>
					<?php endfor; ?>
				</fieldset>
			<?php endif; ?>

		<?php endforeach; ?>

		<hr>
		<div class="form-row">
			<div class="col-md-3"><?= ui_input(array('name' => 'period_start', 'label' => 'Tahun mulai berlaku', 'value' => (string) $block->period_start)) ?></div>
			<div class="col-md-3"><?= ui_input(array('name' => 'period_end', 'label' => 'Tahun selesai', 'value' => (string) $block->period_end)) ?></div>
		</div>
		<?php $source_options = array('' => '— tidak dari dokumen sumber —');
		foreach ($sources as $source) { $source_options[(string) $source->id] = $source->source_code.' — '.$source->title; } ?>
		<?= ui_select(array('name' => 'source_id', 'label' => 'Dokumen sumber', 'options' => $source_options, 'value' => (string) $block->source_id)) ?>
		<?= ui_input(array('name' => 'source_note', 'label' => 'Catatan sumber', 'maxlength' => 255, 'value' => (string) $block->source_note)) ?>
		<?= ui_input(array('name' => 'change_note', 'label' => 'Catatan perubahan versi ini', 'maxlength' => 255, 'value' => '')) ?>
	</div>
	<div class="card-footer">
		<?php if ($can_edit): ?>
			<button class="btn btn-primary" type="submit">Simpan sebagai versi baru</button>
			<span class="small text-muted ml-2">Menyimpan membatalkan verifikasi sebelumnya karena isinya berubah.</span>
		<?php else: ?>
			<p class="mb-0 small text-muted">Anda tidak punya izin menyunting blok profil.</p>
		<?php endif; ?>
	</div>
</form>

<div class="card shadow-sm mt-4">
	<div class="card-header"><h2 class="h6 mb-0">Riwayat versi</h2></div>
	<div class="table-responsive">
		<table class="table mb-0">
			<thead><tr><th scope="col">Versi</th><th scope="col">Catatan</th><th scope="col">Dibuat</th></tr></thead>
			<tbody>
			<?php foreach ($versions as $version): ?>
				<tr>
					<th scope="row"><?= (int) $version->version_no ?><?= (int) $version->id === (int) $block->current_version_id ? ' <span class="badge badge-primary">draft aktif</span>' : '' ?><?= (int) $version->id === (int) $block->published_version_id ? ' <span class="badge badge-success">terbit</span>' : '' ?></th>
					<td><?= e($version->change_note ?: '—') ?></td>
					<td><?= e(format_wib($version->created_at, 'short')) ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($versions)): ?><tr><td colspan="3" class="text-muted">Belum ada versi.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
