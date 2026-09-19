<?php defined('BASEPATH') OR exit('No direct script access allowed');
$query = function ($params) use ($filters, $page) {
	return site_url('admin/audit').'?'.http_build_query(array_filter(array_merge($filters, array('hal' => $page), $params)));
};
?>
<div class="page-heading">
	<div>
		<h1>Log audit</h1>
		<p><?= (int) $total ?> entri. Log bersifat append-only: tidak ada tombol ubah atau hapus pada aplikasi.</p>
	</div>
</div>

<div class="card shadow-sm mb-4">
	<div class="card-body">
		<form method="get" action="<?= site_url('admin/audit') ?>" class="form-row align-items-end">
			<div class="col-md-3 mb-2">
				<label for="aksi">Aksi mengandung</label>
				<input class="form-control" type="search" id="aksi" name="aksi" maxlength="80" value="<?= e($filters['aksi']) ?>" placeholder="mis. ticket. atau auth.">
			</div>
			<div class="col-md-3 mb-2">
				<label for="entitas">Entitas</label>
				<select class="form-control form-select" id="entitas" name="entitas">
					<?php foreach ($entities as $value => $label): ?>
						<option value="<?= e($value) ?>" <?= $filters['entitas'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-2 mb-2">
				<label for="modul">Modul</label>
				<select class="form-control form-select" id="modul" name="modul">
					<?php foreach ($modules as $value => $label): ?>
						<option value="<?= e($value) ?>" <?= $filters['modul'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-2 mb-2">
				<label for="request">Request ID</label>
				<input class="form-control" type="search" id="request" name="request" maxlength="32" value="<?= e($filters['request']) ?>" placeholder="32 karakter">
			</div>
			<div class="col-md-2 mb-2"><button class="btn btn-primary" type="submit">Terapkan</button> <a class="btn btn-outline-primary" href="<?= site_url('admin/audit') ?>">Reset</a></div>
		</form>
	</div>
</div>

<?php if ( ! empty($events)): ?>
<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Peristiwa operasional terakhir</h2></div>
	<div class="card-body">
		<ul class="list-unstyled mb-0">
		<?php foreach ($events as $event): ?>
			<li class="mb-2">
				<span class="chip-flag <?= $event->severity === 'error' ? 'is-danger' : ($event->severity === 'warning' ? 'is-warning' : 'is-info') ?>"><?= e($event->severity) ?></span>
				<?= e($event->message) ?>
				<span class="d-block small text-muted"><?= e(format_wib($event->occurred_at, 'short')) ?> · <?= e($event->actor_name ?: 'sistem') ?><?= $event->module_code ? ' · '.e($event->module_code) : '' ?></span>
			</li>
		<?php endforeach; ?>
		</ul>
	</div>
</div>
<?php endif; ?>

<div class="card shadow-sm">
	<div class="card-body">
		<div class="table-responsive">
			<table class="table table-sm table-hover mb-0">
				<caption class="sr-only">Entri log audit</caption>
				<thead><tr><th scope="col">Waktu (WIB)</th><th scope="col">Aksi</th><th scope="col">Modul</th><th scope="col">Objek</th><th scope="col">Aktor</th><th scope="col">Metadata aman</th><th scope="col">Request</th></tr></thead>
				<tbody>
				<?php foreach ($rows as $row): ?>
					<tr>
						<td class="small text-nowrap"><?= e(format_wib($row->created_at, 'short')) ?></td>
						<td class="small"><code><?= e($row->action) ?></code></td>
						<td class="small text-muted"><?= e((string) $row->module_code) ?></td>
						<td class="small"><?= e($row->entity_type) ?><?= $row->entity_id ? ' <span class="text-muted">'.e($row->entity_id).'</span>' : '' ?></td>
						<td class="small"><?= e($row->actor_name ?: ($row->actor_label ?: 'sistem')) ?></td>
						<td class="small"><?= e(str_limit_id((string) $row->safe_metadata_json, 100)) ?></td>
						<td class="small text-muted"><a href="<?= e($query(array('request' => $row->request_id, 'hal' => 1))) ?>" title="Lihat semua entri pada request ini"><?= e(substr((string) $row->request_id, 0, 8)) ?></a></td>
					</tr>
				<?php endforeach; ?>
				<?php if (empty($rows)): ?>
					<tr><td colspan="7" class="dt-empty text-center">Tidak ada entri audit yang cocok.</td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php if ($pages > 1): ?>
	<div class="card-footer bg-white">
		<nav aria-label="Halaman audit">
			<ul class="pagination mb-0 justify-content-center">
				<li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $query(array('hal' => $page - 1)) ?>">Sebelumnya</a></li>
				<li class="page-item disabled"><span class="page-link">Halaman <?= (int) $page ?> dari <?= (int) $pages ?></span></li>
				<li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $query(array('hal' => $page + 1)) ?>">Berikutnya</a></li>
			</ul>
		</nav>
	</div>
	<?php endif; ?>
</div>
