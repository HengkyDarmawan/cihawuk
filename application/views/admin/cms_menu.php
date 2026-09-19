<?php defined('BASEPATH') OR exit('No direct script access allowed');
$can = function ($p) use ($permissions) { return in_array($p, $permissions, TRUE); };
$roots = array();
$public_by_id = array();
foreach ($items as $row)
{
	$public_by_id[(int) $row->id] = (string) $row->public_id;
	if ($row->parent_id === NULL) { $roots[(string) $row->public_id] = $row->label; }
}
$page_public_by_id = array();
foreach ($pages as $row) { $page_public_by_id[(int) $row->id] = (string) $row->public_id; }
$page_options = array();
foreach ($pages as $row) { $page_options[(string) $row->public_id] = $row->title.' (/'.$row->path.')'; }
$href_of = function ($row) {
	if ($row->link_type === 'external') { return (string) $row->external_url; }
	if ($row->link_type === 'page') { return $row->page_slug === NULL ? NULL : '/'.$row->page_slug; }
	return (string) $row->route_path;
};
?>
<div class="page-heading">
	<div>
		<h1><?= e($locations[$menu->location]) ?></h1>
		<p>Item tersimpan sebagai draft. Situs publik memakai susunan terakhir yang diterbitkan.</p>
	</div>
	<div class="text-right">
		<span class="chip-flag <?= $menu->status === 'published' ? 'is-info' : '' ?>"><?= $menu->status === 'published' ? 'Terbit' : 'Belum pernah terbit' ?></span>
		<?php if ($menu->published_at): ?><span class="d-block small text-muted">Terbit terakhir <?= e(format_wib($menu->published_at, 'short')) ?></span><?php endif; ?>
	</div>
</div>

<?= ui_error_summary($this->form_errors) ?>

<div class="row">
	<div class="col-lg-7">
		<div class="card shadow-sm mb-4">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h2 class="h6 mb-0">Item menu</h2>
				<span class="small text-muted"><?= count($items) ?> item</span>
			</div>
			<?php if (empty($items)): ?>
				<div class="card-body empty-box"><i class="fas fa-list" aria-hidden="true"></i><p class="mb-0">Belum ada item. Tambahkan dari panel kanan.</p></div>
			<?php else: ?>
			<ul class="list-group list-group-flush">
				<?php foreach ($items as $index => $row): $href = $href_of($row); ?>
				<li class="list-group-item<?= $row->parent_id !== NULL ? ' pl-5' : '' ?>">
					<div class="d-flex flex-wrap justify-content-between align-items-start">
						<div class="mr-3 mb-2">
							<span class="font-weight-bold"><?= $row->parent_id !== NULL ? '↳ ' : '' ?><?= e($row->label) ?></span>
							<?php if ( ! $row->is_enabled): ?><span class="chip-flag">nonaktif</span><?php endif; ?>
							<?php if ($row->link_type === 'page' && $row->page_status !== 'published'): ?>
								<span class="chip-flag is-warning">halaman belum terbit — tidak ikut terbit</span>
							<?php endif; ?>
							<span class="d-block small text-muted">
								<?= e($link_types[$row->link_type] ?? $row->link_type) ?> · <code><?= e($href === NULL ? '—' : $href) ?></code>
							</span>
						</div>
						<div class="d-flex flex-wrap align-items-center" style="gap:.35rem">
							<?php if ($can('cms.menu.manage')): ?>
							<form method="post" action="<?= site_url('admin/cms/menu-item/'.rawurlencode($row->public_id).'/naik') ?>">
								<?= csrf_field() ?><button class="btn btn-outline-primary btn-sm" type="submit" aria-label="Naikkan <?= e($row->label) ?>">↑</button>
							</form>
							<form method="post" action="<?= site_url('admin/cms/menu-item/'.rawurlencode($row->public_id).'/turun') ?>">
								<?= csrf_field() ?><button class="btn btn-outline-primary btn-sm" type="submit" aria-label="Turunkan <?= e($row->label) ?>">↓</button>
							</form>
							<button class="btn btn-outline-primary btn-sm" type="button" data-menu-edit='<?= e(json_encode(array(
								"item_id" => $row->public_id, "label" => $row->label, "link_type" => $row->link_type,
								"route_path" => $row->route_path, "external_url" => $row->external_url,
								"parent_id" => $row->parent_id === NULL ? "" : (string) ($public_by_id[(int) $row->parent_id] ?? ""),
								"cms_page_id" => $row->cms_page_id === NULL ? "" : (string) ($page_public_by_id[(int) $row->cms_page_id] ?? ""),
								"is_enabled" => (int) $row->is_enabled,
							))) ?>'>Ubah</button>
							<form method="post" action="<?= site_url('admin/cms/menu-item/'.rawurlencode($row->public_id).'/hapus') ?>"
								data-confirm="Hapus item menu &quot;<?= e($row->label) ?>&quot; dari draft? Submenu di bawahnya ikut terhapus.">
								<?= csrf_field() ?><button class="btn btn-outline-danger btn-sm" type="submit">Hapus</button>
							</form>
							<?php endif; ?>
						</div>
					</div>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
		</div>

		<?php if ($can('cms.page.publish') OR $can('cms.page.rollback')): ?>
		<div class="card shadow-sm mb-4">
			<div class="card-header"><h2 class="h6 mb-0">Publikasi menu</h2></div>
			<div class="card-body">
				<?php if ($can('cms.page.publish')): ?>
				<form method="post" action="<?= site_url('admin/cms/menu/'.rawurlencode($menu->location).'/terbitkan') ?>" data-once
					data-confirm="Terbitkan menu ini? Susunan draft akan langsung menggantikan menu di situs publik.">
					<?= csrf_field() ?>
					<?= ui_input(array('name' => 'reason', 'label' => 'Catatan publikasi', 'maxlength' => 200)) ?>
					<button class="btn btn-primary btn-sm" type="submit">Terbitkan menu</button>
				</form>
				<?php endif; ?>
			</div>
			<?php if ( ! empty($snapshots)): ?>
			<div class="table-responsive">
				<table class="table table-sm mb-0">
					<caption class="sr-only">Riwayat publikasi menu</caption>
					<thead><tr><th scope="col">Revisi</th><th scope="col">Diterbitkan</th><th scope="col">Oleh</th><th scope="col"></th></tr></thead>
					<tbody>
					<?php foreach ($snapshots as $snapshot): ?>
						<tr>
							<td><?= (int) $snapshot->revision_no ?><?= $snapshot->superseded_at === NULL ? ' <span class="chip-flag is-info">aktif</span>' : '' ?></td>
							<td class="small"><?= e(format_wib($snapshot->published_at, 'short')) ?></td>
							<td class="small text-muted"><?= e($snapshot->publisher ?: '—') ?></td>
							<td class="text-right">
								<?php if ($can('cms.page.rollback') && $snapshot->superseded_at !== NULL): ?>
								<form method="post" action="<?= site_url('admin/cms/menu/'.rawurlencode($menu->location).'/rollback') ?>"
									data-confirm="Kembalikan menu ke revisi <?= (int) $snapshot->revision_no ?>? Tindakan ini membuat revisi baru, riwayat tidak dihapus.">
									<?= csrf_field() ?>
									<input type="hidden" name="snapshot_id" value="<?= (int) $snapshot->id ?>">
									<input type="hidden" name="reason" value="Rollback menu ke revisi <?= (int) $snapshot->revision_no ?>">
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
		<?php endif; ?>
	</div>

	<?php if ($can('cms.menu.manage')): ?>
	<div class="col-lg-5">
		<form class="card shadow-sm" method="post" action="<?= site_url('admin/cms/menu/'.rawurlencode($menu->location).'/item') ?>" data-once id="form-menu-item">
			<div class="card-header"><h2 class="h6 mb-0">Tambah / ubah item</h2></div>
			<div class="card-body">
				<?= csrf_field() ?>
				<input type="hidden" name="item_id" value="">
				<?= ui_input(array('name' => 'label', 'label' => 'Label', 'required' => TRUE, 'maxlength' => 80)) ?>
				<?= ui_select(array('name' => 'link_type', 'label' => 'Jenis tautan', 'options' => $link_types, 'value' => 'route')) ?>
				<?= ui_input(array('name' => 'route_path', 'label' => 'Path aplikasi', 'maxlength' => 191,
					'help' => 'Contoh: /profil. Path dashboard dan berkas privat ditolak.')) ?>
				<?= ui_select(array('name' => 'cms_page_id', 'label' => 'Halaman CMS', 'options' => $page_options,
					'placeholder_option' => $page_options ? 'Pilih halaman terbit' : 'Belum ada halaman terbit')) ?>
				<?= ui_input(array('name' => 'external_url', 'label' => 'URL luar', 'maxlength' => 500, 'help' => 'Hanya http atau https.')) ?>
				<?= ui_select(array('name' => 'parent_id', 'label' => 'Induk', 'options' => $roots,
					'placeholder_option' => 'Tanpa induk (tingkat pertama)', 'help' => 'Menu maksimal dua tingkat.')) ?>
				<div class="form-check mb-0">
					<input class="form-check-input" type="checkbox" id="is_enabled" name="is_enabled" value="1" checked>
					<label class="form-check-label" for="is_enabled">Aktif (ikut diterbitkan)</label>
				</div>
			</div>
			<div class="card-footer bg-white text-right">
				<button class="btn btn-outline-secondary btn-sm" type="reset">Kosongkan</button>
				<button class="btn btn-primary btn-sm" type="submit">Simpan item</button>
			</div>
		</form>

		<div class="card shadow-sm mt-4">
			<div class="card-header"><h2 class="h6 mb-0">Susunan yang akan terbit</h2></div>
			<div class="card-body">
				<?php if (empty($tree)): ?>
					<p class="small text-muted mb-0">Belum ada item aktif.</p>
				<?php else: ?>
				<ol class="small mb-0">
					<?php foreach ($tree as $node): ?>
					<li>
						<?= e($node['label']) ?> <code><?= e($node['href'] ?: '—') ?></code>
						<?php if ( ! empty($node['children'])): ?>
						<ul>
							<?php foreach ($node['children'] as $child): ?>
							<li><?= e($child['label']) ?> <code><?= e($child['href'] ?: '—') ?></code></li>
							<?php endforeach; ?>
						</ul>
						<?php endif; ?>
					</li>
					<?php endforeach; ?>
				</ol>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php endif; ?>
</div>

<script>
// Tombol "Ubah" mengisi form yang sama; server tetap memvalidasi ulang seluruh isian.
document.querySelectorAll('[data-menu-edit]').forEach(function (button) {
	button.addEventListener('click', function () {
		var data = JSON.parse(button.getAttribute('data-menu-edit'));
		var form = document.getElementById('form-menu-item');
		form.querySelector('[name="item_id"]').value = data.item_id || '';
		form.querySelector('[name="label"]').value = data.label || '';
		form.querySelector('[name="link_type"]').value = data.link_type || 'route';
		form.querySelector('[name="route_path"]').value = data.route_path || '';
		form.querySelector('[name="external_url"]').value = data.external_url || '';
		form.querySelector('[name="cms_page_id"]').value = data.cms_page_id || '';
		form.querySelector('[name="parent_id"]').value = data.parent_id || '';
		form.querySelector('[name="is_enabled"]').checked = data.is_enabled === 1;
		form.scrollIntoView({behavior: 'smooth', block: 'center'});
		form.querySelector('[name="label"]').focus();
	});
});
</script>
