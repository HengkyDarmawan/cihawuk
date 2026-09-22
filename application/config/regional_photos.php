<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * Foto kawasan Kecamatan Kertasari dari Wikimedia Commons (lisensi bebas, kredit wajib).
 *
 * Ini BUKAN foto Desa Cihawuk: lokasinya Situ Cisanti (hulu Citarum, Desa Tarumajaya) dan jalan
 * di wilayah Kec. Kertasari, diverifikasi dari deskripsi/kategori berkas di Commons. Dipakai
 * sebagai ilustrasi kawasan sampai desa mengunggah foto sendiri lewat CMS (field hero
 * "Foto sisi kanan" / media profil). File disimpan di assets agar ikut terbawa git.
 */
$config['regional_photos'] = array(
	array(
		'file' => 'site/img/kertasari/gunung-wayang-situ-cisanti.webp',
		'width' => 1400, 'height' => 933,
		'place' => 'Gunung Wayang dilihat dari Situ Cisanti',
		'alt' => 'Danau Situ Cisanti yang tenang dengan Gunung Wayang di latar belakang dan langit biru',
		'author' => 'Satria Budiana Tresna',
		'license' => 'CC BY-SA 4.0',
		'source' => 'https://commons.wikimedia.org/wiki/File:Gunung_Wayang_di_Situ_Cisanti.jpg',
	),
	array(
		'file' => 'site/img/kertasari/jalan-kebun-sayur-kertasari.webp',
		'width' => 1400, 'height' => 1050,
		'place' => 'Jalan di antara kebun sayur menuju Situ Cisanti',
		'alt' => 'Jalan desa berkelok di antara kebun sayur bertingkat di perbukitan hijau',
		'author' => 'I Dewa Agung Panji Dwipayana',
		'license' => 'CC BY-SA 4.0',
		'source' => 'https://commons.wikimedia.org/wiki/File:Country_road_in_Bandung_Regency,_West_Java,_Indonesia.jpg',
	),
	array(
		'file' => 'site/img/kertasari/situ-cisanti-pagi.webp',
		'width' => 1400, 'height' => 933,
		'place' => 'Situ Cisanti, titik nol Sungai Citarum',
		'alt' => 'Permukaan Situ Cisanti memantulkan pepohonan dan perbukitan hutan',
		'author' => 'Pendam3slw',
		'license' => 'CC BY-SA 4.0',
		'source' => 'https://commons.wikimedia.org/wiki/File:Cisanti.jpg',
	),
	array(
		'file' => 'site/img/kertasari/situ-cisanti-tepian.webp',
		'width' => 1400, 'height' => 1400,
		'place' => 'Tepian Situ Cisanti',
		'alt' => 'Tepian Situ Cisanti dengan pohon-pohon tinggi dan perbukitan berkabut',
		'author' => 'Awsd24',
		'license' => 'CC BY-SA 4.0',
		'source' => 'https://commons.wikimedia.org/wiki/File:AT_SITU_CISANTI.jpg',
	),
);

/* Label kawasan yang selalu menyertai foto di atas. */
$config['regional_photos_area'] = 'Kawasan Kec. Kertasari';
