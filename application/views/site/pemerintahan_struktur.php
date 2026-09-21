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
$type_labels = OrganizationService::UNIT_TYPES;
$photos = isset($photos) ? $photos : array();
/*
 * Kartu hanya memuat foto, nama, dan jabatan. Uraian tugas, bio, unit, dan masa jabatan
 * dirender sebagai blok `.org-detail` yang terbaca tanpa JavaScript; org-tree.js
 * menyembunyikannya dan menampilkannya dalam dialog saat kartu diklik.
 */
$card = function (array $node) use ($badge, $photos) {
	$person = $node['person'] ?? NULL;
	$photo = ($person && ! empty($person['photo_media_id'])) ? ($photos[(int) $person['photo_media_id']] ?? NULL) : NULL;
	$years = '';
	if ( ! empty($node['start_year']))
	{
		$years = (int) $node['start_year'].' – '.( ! empty($node['end_year']) ? (int) $node['end_year'] : 'sekarang');
	}
	$html = '<div class="org-card">';
	$html .= '<button type="button" class="org-open" data-org-open>';
	$html .= '<span class="org-avatar" aria-hidden="true">'
		.($photo ? '<img src="'.e(media_url($photo)).'" alt="" width="72" height="72" loading="lazy" decoding="async">' : icon('user'))
		.'</span>';
	$html .= '<span class="org-title">'.e($node['title']).'</span>';
	$html .= $person
		? '<span class="org-person">'.e($person['name']).'</span>'
		: '<span class="org-person text-muted">Belum ada penugasan</span>';
	$html .= $badge($node['assignment_type']);
	$html .= '</button>';

	$html .= '<div class="org-detail">';
	$html .= '<div class="org-detail-head">';
	if ($photo)
	{
		$html .= '<div class="org-detail-photo">'.media_img($photo, 'Foto pejabat', FALSE, '160px').'</div>';
	}
	$html .= '<div><p class="org-detail-title">'.e($node['title']).' '.$badge($node['assignment_type']).'</p>';
	$html .= '<p class="org-detail-name">'.($person ? e($person['name']) : '<span class="text-muted">Belum ada penugasan</span>').'</p></div>';
	$html .= '</div><dl class="org-detail-list">';
	if ( ! empty($node['unit_name'])) { $html .= '<dt>Unit</dt><dd>'.e($node['unit_name']).'</dd>'; }
	if ($years !== '') { $html .= '<dt>Masa tugas</dt><dd>'.e($years).'</dd>'; }
	if ( ! empty($node['duties_public'])) { $html .= '<dt>Uraian tugas</dt><dd>'.nl2br(e($node['duties_public'])).'</dd>'; }
	if ($person && ! empty($person['bio_public'])) { $html .= '<dt>Profil singkat</dt><dd>'.nl2br(e($person['bio_public'])).'</dd>'; }
	$html .= '</dl>';
	if ($photo && ! empty($photo->caption))
	{
		$html .= '<p class="org-detail-note small text-muted">'.e($photo->caption).'</p>';
	}
	return $html.'</div></div>';
};
/*
 * Pohon ke bawah. Supaya bagan tidak melebar ekstrem, jabatan tanpa bawahan ("daun")
 * yang berjumlah tiga atau lebih di bawah satu atasan digabung dalam satu grid kartu.
 */
$render = function (array $nodes, $depth) use (&$render, $card) {
	if (empty($nodes)) { return; }
	$branches = $nodes;
	$leaves = array();
	if ($depth > 0)
	{
		$branches = array_values(array_filter($nodes, function ($n) { return ! empty($n['children']); }));
		$leaves = array_values(array_filter($nodes, function ($n) { return empty($n['children']); }));
		if (count($leaves) < 3)
		{
			$branches = $nodes;
			$leaves = array();
		}
	}
	echo '<ul class="org-tree'.($depth === 0 ? ' org-tree-root' : '').'">';
	foreach ($branches as $node)
	{
		echo '<li class="org-node">'.$card($node);
		$render($node['children'], $depth + 1);
		echo '</li>';
	}
	if ($leaves)
	{
		$n = count($leaves);
		// Di samping cabang lain grid dibatasi 3 kolom; sendirian boleh sampai 4.
		$cols = $branches ? min(3, $n) : ($n <= 4 ? $n : ($n <= 6 ? 3 : 4));
		echo '<li class="org-node org-leaf-group"><ul class="org-leaves" style="--org-cols:'.(int) $cols.'">';
		foreach ($leaves as $node)
		{
			echo '<li class="org-node">'.$card($node).'</li>';
		}
		echo '</ul></li>';
	}
	echo '</ul>';
};
// Akar dikelompokkan per jenis lembaga (Pemerintah Desa, BPD, lembaga lain).
$groups = array();
foreach ($tree as $root)
{
	$groups[(string) ($root['unit_type'] ?: 'village_government')][] = $root;
}
uksort($groups, function ($a, $b) use ($type_labels) {
	return array_search($a, array_keys($type_labels), TRUE) - array_search($b, array_keys($type_labels), TRUE);
});
?>
<section class="page-hero">
	<div class="container-site">
		<nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent p-0 mb-2"><li class="breadcrumb-item"><a href="<?= site_url('/') ?>">Beranda</a></li><?php if ( ! isset($breadcrumb_parent) OR $breadcrumb_parent): ?><li class="breadcrumb-item"><a href="<?= site_url('pemerintahan') ?>">Pemerintahan</a></li><li class="breadcrumb-item active" aria-current="page">Struktur Organisasi</li><?php else: ?><li class="breadcrumb-item active" aria-current="page">Pemerintahan</li><?php endif; ?></ol></nav>
		<h1><?= ( ! isset($breadcrumb_parent) OR $breadcrumb_parent) ? 'Struktur organisasi' : 'Pemerintahan desa' ?></h1>
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
				<?php foreach ($groups as $type => $roots): ?>
					<section class="org-group">
						<?php if (count($groups) > 1): ?><h2 class="h5 org-group-title"><?= e($type_labels[$type] ?? 'Lembaga') ?></h2><?php endif; ?>
						<div class="org-forest"><?php $render($roots, 0); ?></div>
					</section>
				<?php endforeach; ?>
			</div>

			<dialog class="org-dialog" id="org-dialog" aria-labelledby="org-dialog-heading">
				<div class="org-dialog-body">
					<h2 class="sr-only" id="org-dialog-heading">Detail jabatan</h2>
					<button class="btn btn-sm btn-light org-dialog-close" type="button" data-dialog-close aria-label="Tutup detail"><?= icon('x') ?></button>
					<div data-org-dialog-content></div>
				</div>
			</dialog>

			<p class="small text-muted mt-4">
				Periode <?= e($snapshot['period']['name']) ?>
				(<?= (int) $snapshot['period']['year_start'] ?><?= $snapshot['period']['year_end'] ? '–'.(int) $snapshot['period']['year_end'] : '' ?>),
				revisi <?= (int) $snapshot['revision_no'] ?>, diterbitkan <?= e(format_wib($snapshot['published_at'], 'short')) ?>.
				Halaman ini tetap dapat dibaca tanpa JavaScript.
			</p>
		<?php endif; ?>
	</div>
</section>
