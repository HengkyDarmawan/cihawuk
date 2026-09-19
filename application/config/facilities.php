<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| Registry fasilitas desa (modul-backend 12.2, modul-frontend 9).
|
| Kategori tertutup. Angka agregat pada dokumen sumber (misalnya "4 SD") TIDAK boleh
| diubah menjadi entitas fasilitas; angka itu tetap berada pada Data Desa. Direktori hanya
| memuat objek yang identitasnya cukup untuk dikunjungi warga.
*/

$config['facility_categories'] = array(
	'education' => 'Pendidikan',
	'religious_education' => 'Pendidikan keagamaan',
	'health' => 'Kesehatan',
	'water' => 'Air bersih',
	'sanitation' => 'Sanitasi',
	'worship' => 'Peribadatan',
	'sport' => 'Olahraga',
	'government' => 'Pemerintahan',
	'communication' => 'Komunikasi',
	'transport' => 'Transportasi',
	'other' => 'Lainnya',
);

$config['place_types'] = array(
	'office' => 'Kantor',
	'facility' => 'Fasilitas',
	'potential' => 'Potensi',
	'other' => 'Lokasi lain',
);

/* Status akses potensi (modul-frontend 7.2). */
$config['potential_access_statuses'] = array(
	'unknown' => 'Belum diketahui',
	'open' => 'Terbuka untuk umum',
	'limited' => 'Terbatas / perlu izin',
	'closed' => 'Tidak terbuka untuk umum',
);
