<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
	body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #182B24; }
	h1 { font-size: 18px; margin: 0 0 4px; color: #10392D; }
	.muted { color: #596A62; }
	table { width: 100%; border-collapse: collapse; margin-top: 12px; }
	th, td { border: 1px solid #DCE5DE; padding: 6px 8px; text-align: left; }
	th { background: #EEF3EF; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }
	.num { text-align: right; }
	.footnote { margin-top: 16px; font-size: 10px; color: #596A62; }
</style>
</head>
<body>
	<h1><?= e($title) ?></h1>
	<p class="muted">
		Desa Cihawuk, Kecamatan Kertasari, Kabupaten Bandung<br>
		Dibuat <?= e($generated_at) ?> oleh <?= e($requester) ?>
	</p>

	<p><strong>Filter:</strong> <?= e(empty(array_filter($filters)) ? 'tanpa filter (seluruh lingkup akses pemohon)' : json_encode(array_filter($filters), JSON_UNESCAPED_UNICODE)) ?></p>

	<table>
		<caption style="text-align:left;font-weight:bold;padding-bottom:4px">Jumlah laporan per status</caption>
		<thead><tr><th>Status</th><th class="num">Jumlah</th></tr></thead>
		<tbody>
		<?php foreach (app_config('ticket_statuses', array()) as $code => $meta): ?>
			<tr><td><?= e($meta['staff']) ?></td><td class="num"><?= (int) ($counts[$code] ?? 0) ?></td></tr>
		<?php endforeach; ?>
			<tr><th>Total</th><th class="num"><?= (int) $total ?></th></tr>
			<tr><td>Memiliki milestone terlambat</td><td class="num"><?= (int) $overdue ?></td></tr>
		</tbody>
	</table>

	<?php if ( ! empty($categories)): ?>
	<table>
		<caption style="text-align:left;font-weight:bold;padding-bottom:4px">Kategori terbanyak</caption>
		<thead><tr><th>Kategori</th><th class="num">Jumlah</th></tr></thead>
		<tbody>
		<?php foreach ($categories as $cat): ?>
			<tr><td><?= e($cat->name) ?></td><td class="num"><?= (int) $cat->total ?></td></tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>

	<p class="footnote">
		Definisi: laporan masuk dihitung dari waktu penerimaan (submitted_at); keterlambatan dihitung per milestone yang belum tercapai
		pada episode penanganan berjalan. Rekap ini tidak memuat identitas atau kontak pelapor, dan hanya mencakup laporan dalam lingkup
		akses pemohon pada saat berkas dibuat. Waktu ditampilkan dalam WIB.
	</p>
</body>
</html>
