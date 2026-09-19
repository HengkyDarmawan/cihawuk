<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="page-heading">
	<div>
		<h1>Menu dan Navigasi</h1>
		<p>Setiap lokasi menu punya draft sendiri. Situs publik baru berubah setelah menu diterbitkan.</p>
	</div>
</div>

<div class="card shadow-sm">
	<div class="table-responsive">
		<table class="table table-sm mb-0">
			<caption class="sr-only">Daftar lokasi menu</caption>
			<thead><tr><th scope="col">Lokasi</th><th scope="col">Jumlah item draft</th><th scope="col">Status</th><th scope="col">Terbit terakhir</th><th scope="col"></th></tr></thead>
			<tbody>
			<?php foreach ($menus as $location => $menu): ?>
				<tr>
					<td>
						<span class="font-weight-bold"><?= e($locations[$location]) ?></span>
						<span class="d-block small text-muted"><code><?= e($location) ?></code></span>
					</td>
					<td><?= (int) $menu->item_count ?></td>
					<td><span class="chip-flag <?= $menu->status === 'published' ? 'is-info' : '' ?>"><?= $menu->status === 'published' ? 'Terbit' : 'Belum pernah terbit' ?></span></td>
					<td class="small text-muted"><?= $menu->published_at ? e(format_wib($menu->published_at, 'short')) : '—' ?></td>
					<td class="text-right"><a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/cms/menu/'.rawurlencode($location)) ?>">Kelola</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<p class="small text-muted mt-3">
	Menu yang belum pernah diterbitkan tidak mengubah situs: header dan footer tetap memakai navigasi bawaan
	sampai ada snapshot menu pertama.
</p>
