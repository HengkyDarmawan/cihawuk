<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="page-heading">
	<div>
		<h1>Media</h1>
		<p><?= (int) $total ?> berkas. Media hanya dapat dipakai konten publik bila status hak publikasinya jelas.</p>
	</div>
	<div class="page-actions"><?= ui_add_button('modal-unggah-media', 'Unggah media') ?></div>
</div>

<div class="row">
	<div class="col-12">
		<?php if (empty($items)): ?>
			<div class="card shadow-sm"><div class="card-body empty-box"><i class="fas fa-images" aria-hidden="true"></i><p class="mb-0">Belum ada media. Klik “Unggah media” untuk menambahkan.</p></div></div>
		<?php else: ?>
			<?php foreach ($items as $item): ?>
			<div class="card shadow-sm mb-3">
				<div class="card-body">
					<div class="d-flex flex-wrap gap-3">
						<div style="width:120px;flex:none">
							<?php if (strpos($item->mime_type, 'image/') === 0): ?>
								<img src="<?= e(base_url('media/'.$item->storage_key)) ?>" alt="<?= e($item->alt_text) ?>" style="width:120px;height:90px;object-fit:cover;border-radius:8px">
							<?php else: ?>
								<div class="d-flex align-items-center justify-content-center" style="width:120px;height:90px;border-radius:8px;background:#F1F5F2"><i class="fas fa-file-pdf fa-2x text-muted" aria-hidden="true"></i></div>
							<?php endif; ?>
						</div>
						<div class="flex-grow-1">
							<h2 class="h6 mb-1"><?= e($item->original_name) ?></h2>
							<p class="small text-muted mb-2">
								<?= e($item->mime_type) ?> · <?= e(format_bytes_id($item->byte_size)) ?>
								<?= $item->width ? ' · '.(int) $item->width.'×'.(int) $item->height.' px' : '' ?>
								· diunggah <?= e(format_wib($item->created_at, 'short')) ?>
							</p>
							<?php if ( ! empty($item->usage)): ?>
								<p class="small mb-2"><span class="chip-flag is-info">dipakai: <?= e(implode(', ', $item->usage)) ?></span></p>
							<?php endif; ?>
							<?php if ( ! empty($item->derivatives)): ?>
								<p class="small text-muted mb-2">
									Asli: privat ·
									<?php foreach ($item->derivatives as $derivative): ?>
										<?= e($derivative->variant) ?> (<?= e(format_bytes_id($derivative->byte_size)) ?><?= $derivative->width ? ', '.(int) $derivative->width.' px' : '' ?>)<?= $derivative !== end($item->derivatives) ? ' · ' : '' ?>
									<?php endforeach; ?>
								</p>
							<?php endif; ?>
							<?php if ($item->is_placeholder): ?><span class="chip-flag is-warning">placeholder</span><?php endif; ?>
							<?php if ($item->rights_status === 'unknown'): ?><span class="chip-flag is-danger">hak belum jelas — tidak bisa dipublikasikan</span><?php endif; ?>

							<form method="post" action="<?= site_url('admin/media/'.(int) $item->id) ?>" class="form-row mt-2 align-items-end">
								<?= csrf_field() ?>
								<div class="col-md-5"><?= ui_input(array('name' => 'alt_text', 'id' => 'alt-'.$item->id, 'label' => 'Alt text', 'maxlength' => 255, 'value' => $item->alt_text, 'wrap_class' => 'mb-2')) ?></div>
								<div class="col-md-3"><?= ui_input(array('name' => 'source_credit', 'id' => 'src-'.$item->id, 'label' => 'Kredit', 'maxlength' => 255, 'value' => $item->source_credit, 'wrap_class' => 'mb-2')) ?></div>
								<div class="col-md-3"><?= ui_select(array('name' => 'rights_status', 'id' => 'rights-'.$item->id, 'label' => 'Hak', 'options' => $rights_options, 'value' => $item->rights_status, 'wrap_class' => 'mb-2')) ?></div>
								<div class="col-md-1 mb-2"><button class="btn btn-outline-primary btn-sm btn-block" type="submit">Simpan</button></div>
								<input type="hidden" name="license_note" value="<?= e($item->license_note) ?>">
								<input type="hidden" name="caption" value="<?= e($item->caption) ?>">
								<input type="hidden" name="people_shown" value="<?= e($item->people_shown) ?>">
								<?php if ($item->is_placeholder): ?><input type="hidden" name="is_placeholder" value="1"><?php endif; ?>
							</form>
						</div>
						<?php if (in_array('content.publish', $permissions, TRUE)): ?>
						<div>
							<form method="post" action="<?= site_url('admin/media/'.(int) $item->id.'/hapus') ?>"
								data-confirm="Arsipkan media ini? Salinan publik dihapus, berkas asli tetap tersimpan privat." data-confirm-ok="Arsipkan media">
								<?= csrf_field() ?>
								<button class="btn btn-outline-danger btn-sm" type="submit" <?= ! empty($item->usage) ? 'disabled title="Masih dipakai konten"' : '' ?>>Arsipkan</button>
							</form>
						</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<?php endforeach; ?>

			<?php if ($pages > 1): ?>
			<nav aria-label="Halaman media">
				<ul class="pagination justify-content-center">
					<li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= site_url('admin/media?hal='.($page - 1)) ?>">Sebelumnya</a></li>
					<li class="page-item disabled"><span class="page-link">Halaman <?= (int) $page ?> dari <?= (int) $pages ?></span></li>
					<li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= site_url('admin/media?hal='.($page + 1)) ?>">Berikutnya</a></li>
				</ul>
			</nav>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>

<?= ui_modal_open('modal-unggah-media', 'Unggah media', 'modal-lg') ?>
	<form method="post" action="<?= site_url('admin/media') ?>" enctype="multipart/form-data" data-once>
		<?= csrf_field() ?>
		<div class="form-group">
			<label for="berkas">Berkas <span class="required-mark" aria-hidden="true">*</span></label>
			<input class="form-control-file" type="file" id="berkas" name="berkas[]" required accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" aria-describedby="berkas-help">
			<small class="form-text" id="berkas-help">JPG, PNG, WebP, atau PDF. Maksimal 8 MB. Berkas asli disimpan di penyimpanan privat; yang tampil publik adalah salinan hasil olahan tanpa metadata EXIF.</small>
		</div>
		<div class="form-row">
			<div class="col-md-6"><?= ui_input(array('name' => 'alt_text', 'label' => 'Teks alternatif (alt)', 'maxlength' => 255, 'value' => '',
				'help' => 'Deskripsi singkat isi gambar untuk pembaca layar.')) ?></div>
			<div class="col-md-6"><?= ui_input(array('name' => 'caption', 'label' => 'Keterangan (caption)', 'maxlength' => 500, 'value' => '')) ?></div>
			<div class="col-md-6"><?= ui_input(array('name' => 'source_credit', 'label' => 'Kredit/sumber', 'maxlength' => 255, 'value' => '')) ?></div>
			<div class="col-md-6"><?= ui_input(array('name' => 'source_year', 'label' => 'Tahun sumber', 'type' => 'number', 'value' => '', 'help' => 'Tahun foto/dokumen dibuat bila diketahui.')) ?></div>
			<div class="col-md-6"><?= ui_input(array('name' => 'people_shown', 'label' => 'Orang yang tampak', 'maxlength' => 255, 'value' => '',
				'help' => 'Isi bila ada orang yang dapat dikenali; publikasi memerlukan izin mereka.')) ?></div>
			<div class="col-md-6"><?= ui_select(array('name' => 'rights_status', 'label' => 'Status hak publikasi', 'required' => TRUE, 'options' => $rights_options, 'value' => 'owned')) ?></div>
		</div>
		<?= ui_input(array('name' => 'license_note', 'label' => 'Catatan lisensi', 'maxlength' => 255, 'value' => '')) ?>
		<div class="custom-control custom-checkbox">
			<input class="custom-control-input" type="checkbox" id="is_placeholder" name="is_placeholder" value="1">
			<label class="custom-control-label" for="is_placeholder">Tandai sebagai placeholder (bukan foto asli desa)</label>
		</div>
		<?= ui_modal_actions('Unggah') ?>
	</form>
<?= ui_modal_close() ?>
