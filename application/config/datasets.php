<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| Registry dataset dan aturan visualisasi (modul-frontend §8, modul-backend §11).
|
| Tema tertutup: pengelola memilih dari daftar ini, tidak dapat menambah tema dari dashboard.
| Jenis grafik juga tertutup, dan pie/donut hanya boleh dipakai bila seri benar-benar sebuah
| komposisi yang totalnya cocok — pemeriksaannya ada di DatasetService::validate_version().
*/

$config['dataset_themes'] = array(
	'population' => 'Kependudukan',
	'family_welfare' => 'Keluarga dan kesejahteraan agregat',
	'education' => 'Pendidikan',
	'employment' => 'Pekerjaan dan ekonomi',
	'health' => 'Kesehatan agregat',
	'environment' => 'Lingkungan dan penggunaan lahan',
	'institution' => 'Lembaga dan partisipasi',
	'infrastructure' => 'Prasarana dan fasilitas',
	'government' => 'Pemerintahan dan anggaran',
);

/* Tema yang isinya wajib agregat dan tidak boleh menampilkan angka kecil apa adanya. */
$config['dataset_sensitive_themes'] = array('health', 'family_welfare');

$config['dataset_sensitivity'] = array(
	'public' => 'Publik',
	'aggregate_only' => 'Hanya agregat',
	'restricted' => 'Terbatas (tidak tampil publik)',
);

/* Ambang minimal angka yang boleh ditampilkan pada tema sensitif. */
$config['dataset_small_count_threshold'] = 10;

// Perbandingan antartahun belum tersedia: satu versi dataset mewakili satu periode.
$config['dataset_chart_types'] = array(
	'bar' => 'Bar',
	'bar_horizontal' => 'Bar horizontal',
	'donut' => 'Donut (hanya komposisi)',
	'number' => 'Kartu angka',
);

/* Jenis grafik yang hanya sah untuk komposisi dengan total yang cocok. */
$config['dataset_composition_charts'] = array('donut');

/*
| Indikator total untuk setiap kelompok komposisi. Donut hanya boleh dipakai bila seluruh
| komponen kelompok ikut disertakan dan jumlahnya sama persis dengan indikator total ini.
| Kategori yang dapat tumpang tindih (mis. pendidikan) sengaja tidak punya total.
*/
$config['dataset_composition_totals'] = array(
	'population_sex' => 'population_total',
);

/* Pemetaan kelompok indikator lama ke tema dataset. */
$config['dataset_group_theme_map'] = array(
	'population' => 'population',
	'government' => 'government',
	'geography' => 'environment',
	'agriculture' => 'environment',
	'education' => 'education',
	'employment' => 'employment',
	'health' => 'health',
	'infrastructure' => 'infrastructure',
);
