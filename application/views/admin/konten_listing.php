<?php defined('BASEPATH') OR exit('No direct script access allowed');
$page_url = function ($n) use ($type, $filters) {
	return site_url('admin/konten/'.$type).'?'.http_build_query(array_filter(array('status' => $filters['status'], 'q' => $filters['keyword'], 'hal' => $n)));
};
?>
<div class="page-heading">
	<div>
		<h1><?= strip_tags($schema['label']) ?></h1>
		<p><?= (int) $total ?> item.</p>
	</div>
	<div class="page-actions">
		<a class="btn btn-outline-primary" href="<?= site_url('admin/konten') ?>">Semua konten</a>
		<a class="btn btn-primary btn-add" href="<?= site_url('admin/konten/'.$type.'/buat') ?>"><i class="fas fa-plus mr-1" aria-hidden="true"></i> Buat <?= e(strtolower($schema['singular'])) ?></a>
	</div>
</div>

<div class="card shadow-sm mb-4">
	<div class="card-body">
		<form method="get" action="<?= site_url('admin/konten/'.$type) ?>" class="form-row align-items-end">
			<div class="col-md-5 mb-2">
				<label for="q">Cari</label>
				<input class="form-control" type="search" id="q" name="q" value="<?= e($filters['keyword']) ?>" maxlength="80">
			</div>
			<?php if ($schema['publishable']): ?>
			<div class="col-md-4 mb-2">
				<label for="status">Status</label>
				<select class="form-control" id="status" name="status">
					<option value="">Semua status</option>
					<?php foreach (app_config('publication_statuses') as $code => $label): ?>
						<option value="<?= e($code) ?>" <?= $filters['status'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php endif; ?>
			<div class="col-md-3 mb-2"><button class="btn btn-primary btn-block" type="submit">Terapkan</button></div>
		</form>
	</div>
</div>

<div class="card shadow-sm">
	<div class="card-body">
		<?php if (empty($items)): ?>
			<div class="empty-box"><i class="fas fa-folder-open" aria-hidden="true"></i><p class="mb-0">Belum ada item.</p></div>
		<?php else: ?>
		<div class="table-responsive">
			<table class="table table-hover mb-0">
				<caption class="sr-only">Daftar <?= e(strip_tags($schema['label'])) ?></caption>
				<thead>
					<tr>
						<?php foreach ($schema['list_columns'] as $column => $label): ?><th scope="col"><?= e($label) ?></th><?php endforeach; ?>
						<th scope="col"><span class="sr-only">Tindakan</span></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($items as $item): ?>
					<tr>
						<?php foreach ($schema['list_columns'] as $column => $label):
							$value = $item->{$column} ?? NULL; ?>
							<td>
								<?php if ($column === 'publication_status'): ?>
									<span class="chip-flag <?= $value === 'published' ? 'is-info' : ($value === 'in_review' ? 'is-warning' : '') ?>"><?= e(config_label('publication_statuses', $value)) ?></span>
								<?php elseif ($column === 'verification_status'): ?>
									<span class="chip-flag"><?= e(config_label('verification_statuses', $value)) ?></span>
								<?php elseif (in_array($column, array('published_at', 'starts_at'), TRUE)): ?>
									<?= e($value ? format_wib($value, 'short') : '—') ?>
								<?php elseif ($column === 'active'): ?>
									<?= ((int) $value === 1) ? 'Ya' : 'Tidak' ?>
								<?php elseif ($column === 'type'): ?>
									<?= e($value === 'announcement' ? 'Pengumuman' : 'Berita') ?>
								<?php else: ?>
									<?= e(str_limit_id((string) $value, 80)) ?>
								<?php endif; ?>
							</td>
						<?php endforeach; ?>
						<td class="text-right">
							<a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/konten/'.$type.'/'.(int) $item->id) ?>">Ubah</a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>
	</div>
	<?php if ($pages > 1): ?>
	<div class="card-footer bg-white">
		<nav aria-label="Halaman konten">
			<ul class="pagination mb-0 justify-content-center">
				<li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $page_url($page - 1) ?>">Sebelumnya</a></li>
				<li class="page-item disabled"><span class="page-link">Halaman <?= (int) $page ?> dari <?= (int) $pages ?></span></li>
				<li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $page_url($page + 1) ?>">Berikutnya</a></li>
			</ul>
		</nav>
	</div>
	<?php endif; ?>
</div>
