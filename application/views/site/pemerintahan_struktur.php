<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Struktur organisasi publik.
 *
 * Dirender sebagai daftar hierarkis semantik supaya tetap terbaca pembaca layar, dapat
 * dicetak, dan tetap berfungsi ketika JavaScript gagal. JavaScript hanya menambahkan
 * lipat/buka, pencarian, dan perbesaran di atas markup yang sudah lengkap.
 *
 * Tidak ada NIK, kontak pribadi, alamat rumah, atau nomor SK pada atribut DOM mana pun.
 */
$badge = function ($type) {
	if ($type === 'acting') { return '<span class="badge badge-warning">Plt</span>'; }
	if ($type === 'vacant') { return '<span class="badge badge-secondary">Kosong</span>'; }
	return '';
};
$render = function (array $nodes, $depth) use (&$render, $badge) {
	if (empty($nodes)) { return; }
	echo '<ul class="org-tree'.($depth === 0 ? ' org-tree-root' : '').'">';
	foreach ($nodes as $node)
	{
		echo '<li class="org-node">';
		echo '<div class="org-card">';
		echo '<p class="org-title">'.e($node['title']).' '.$badge($node['assignment_type']).'</p>';
		if ( ! empty($node['person']))
		{
			echo '<p class="org-person">'.e($node['person']['name']).'</p>';
		}
		else
		{
			echo '<p class="org-person text-muted">Belum ada penugasan</p>';
		}
		if ( ! empty($node['unit_name']))
		{
			echo '<p class="org-unit small text-muted">'.e($node['unit_name']).'</p>';
		}
		if ( ! empty($node['duties_public']))
		{
			echo '<p class="org-duties small">'.e($node['duties_public']).'</p>';
		}
		echo '</div>';
		$render($node['children'], $depth + 1);
		echo '</li>';
	}
	echo '</ul>';
};
?>
<section class="page-hero">
	<div class="container-site">
		<nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-2"><li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li><li class="breadcrumb-item"><a href="<?= site_url('pemerintahan') ?>">Pemerintahan</a></li><li class="breadcrumb-item active" aria-current="page">Struktur Organisasi</li></ol></nav>
		<h1>Struktur organisasi</h1>
		<p>Susunan jabatan dan penugasan yang berlaku pada periode terpilih. Jabatan tanpa pejabat ditandai kosong, bukan diisi nama lama.</p>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<?php if ( ! $snapshot): ?>
			<div class="empty-state">
				<?= icon('users') ?>
				<h2 class="h5">Struktur organisasi belum diterbitkan</h2>
				<p class="mb-0">Susunan jabatan sedang disiapkan pengelola desa. Data pejabat lama tidak ditampilkan sebagai kondisi saat ini.</p>
			</div>
		<?php else: ?>
			<?php if (count($periods) > 1): ?>
			<form class="filter-bar mb-4" method="get" action="<?= site_url('pemerintahan/struktur') ?>">
				<div>
					<label class="form-label" for="periode">Periode</label>
					<select class="form-select" id="periode" name="periode">
						<?php foreach ($periods as $period): ?>
							<option value="<?= e($period['public_id']) ?>" <?= $selected === $period['public_id'] ? 'selected' : '' ?>>
								<?= e($period['name']) ?> (<?= (int) $period['year_start'] ?><?= $period['year_end'] ? '–'.(int) $period['year_end'] : '' ?>)
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div><button class="btn btn-primary" type="submit">Tampilkan</button></div>
			</form>
			<?php endif; ?>

			<div class="org-toolbar mb-3" data-org-toolbar hidden>
				<label class="form-label" for="org-search">Cari nama atau jabatan</label>
				<input class="form-control" type="search" id="org-search" data-org-search maxlength="60">
				<button class="btn btn-outline-primary btn-sm" type="button" data-org-expand>Buka semua</button>
				<button class="btn btn-outline-primary btn-sm" type="button" data-org-collapse>Tutup semua</button>
			</div>

			<div class="org-chart" data-org-chart>
				<?php $render($tree, 0); ?>
			</div>

			<p class="small text-muted mt-4">
				Periode <?= e($snapshot['period']['name']) ?>
				(<?= (int) $snapshot['period']['year_start'] ?><?= $snapshot['period']['year_end'] ? '–'.(int) $snapshot['period']['year_end'] : '' ?>),
				revisi <?= (int) $snapshot['revision_no'] ?>, diterbitkan <?= e(format_wib($snapshot['published_at'], 'short')) ?>.
				Halaman ini tetap dapat dibaca tanpa JavaScript.
			</p>
		<?php endif; ?>
	</div>
</section>
