<?php
/**
 * Unduh foto demonstrasi dari Wikimedia Commons.
 *
 * Commons dipilih karena setiap berkas membawa penulis dan lisensinya secara mesin-terbaca,
 * dan karena isinya relevan dengan wilayah setempat (Pangalengan, Rancabali dan Ciwidey
 * berada di dataran tinggi yang sama dengan Kertasari). Foto tetap BUKAN foto Desa Cihawuk;
 * itu ditulis pada caption setiap berkas dan ditegaskan lagi oleh bilah mode demo.
 *
 * Hasil: reference/media/demo/*.jpg + manifest.csv, siap untuk `tools import_media demo`.
 *
 * Pemakaian: php scripts/fetch-demo-media.php
 */

$root = dirname(__DIR__);
$out  = $root.'/reference/media/demo/';
@mkdir($out, 0775, TRUE);

$agent = 'CihawukVillageDemo/1.0 (offline demo seeding)';

$candidates = array('C:/xampp/apache/bin/curl-ca-bundle.crt', 'C:/Program Files/Git/mingw64/etc/ssl/certs/ca-bundle.crt', ini_get('curl.cainfo'));
$GLOBALS['chw_ca'] = NULL;
foreach ($candidates as $c) { if ($c && is_file($c)) { $GLOBALS['chw_ca'] = $c; break; } }
if ($GLOBALS['chw_ca'] === NULL) { fwrite(STDERR, "Tidak ada bundel CA; unduhan HTTPS tidak dapat diverifikasi.
"); exit(1); }
echo 'Bundel CA: '.$GLOBALS['chw_ca'].PHP_EOL;


/* kunci => array(kata kunci pencarian, alt_text, caption) */
$wanted = array(
	'lanskap-pegunungan' => array('Tea plantation Indonesia', 'Lanskap perbukitan berkabut di dataran tinggi Bandung selatan', 'Foto contoh: lanskap dataran tinggi'),
	'kebun-teh' => array('Kebun teh Rancabali', 'Hamparan kebun teh di lereng bukit', 'Foto contoh: kebun teh'),
	'kebun-teh-2' => array('Kebun teh di Pangalengan', 'Barisan tanaman teh dengan latar pegunungan', 'Foto contoh: kebun teh'),
	'sawah' => array('Rice field Indonesia', 'Petak sawah bertingkat di lereng bukit', 'Foto contoh: persawahan'),
	'sayuran' => array('Vegetable market Indonesia', 'Lahan sayuran dataran tinggi', 'Foto contoh: pertanian sayuran'),
	'kentang' => array('Potato harvest', 'Umbi kentang hasil panen', 'Foto contoh: komoditas kentang'),
	'stroberi' => array('Strawberry fruit', 'Buah stroberi segar', 'Foto contoh: komoditas stroberi'),
	'kopi' => array('Roasted coffee beans', 'Biji kopi yang sudah disangrai', 'Foto contoh: komoditas kopi'),
	'pasar' => array('Pasar tradisional Indonesia', 'Suasana pasar tradisional dengan lapak sayur', 'Foto contoh: pasar tradisional'),
	'kantor-desa' => array('Kantor desa', 'Bangunan kantor pemerintahan desa', 'Foto contoh: kantor desa'),
	'balai-desa' => array('Balai desa', 'Bangunan balai pertemuan desa', 'Foto contoh: balai desa'),
	'masjid' => array('Mosque Indonesia village', 'Bangunan masjid di permukiman desa', 'Foto contoh: masjid'),
	'sekolah' => array('Elementary school Indonesia building', 'Gedung sekolah dasar', 'Foto contoh: sekolah dasar'),
	'puskesmas' => array('Puskesmas', 'Bangunan pusat kesehatan masyarakat', 'Foto contoh: layanan kesehatan'),
	'posyandu' => array('Posyandu', 'Kegiatan pelayanan kesehatan ibu dan anak', 'Foto contoh: posyandu'),
	'ternak-sapi' => array('Sapi perah', 'Sapi perah di kandang peternakan', 'Foto contoh: peternakan sapi'),
	'ternak-domba' => array('Sheep farm Indonesia', 'Domba di kandang peternakan rakyat', 'Foto contoh: peternakan domba'),
	'air-panas' => array('Kawah Putih', 'Kolam pemandian air panas alami', 'Foto contoh: pemandian air panas'),
	'danau' => array('Situ Cileunca', 'Danau dengan latar perbukitan', 'Foto contoh: danau pegunungan'),
	'kerajinan-bambu' => array('Bambu anyaman', 'Anyaman bambu hasil kerajinan tangan', 'Foto contoh: kerajinan bambu'),
	'warung' => array('Warung Indonesia', 'Warung makan sederhana di tepi jalan', 'Foto contoh: warung'),
	'jalan-desa' => array('Jalan pedesaan Indonesia', 'Jalan permukiman desa', 'Foto contoh: jalan desa'),
	'permukiman' => array('Rural village Indonesia houses', 'Rumah-rumah warga di permukiman desa', 'Foto contoh: permukiman'),
	'musyawarah' => array('Musyawarah desa', 'Pertemuan warga di balai desa', 'Foto contoh: musyawarah desa'),
	'lapangan' => array('Lapangan sepak bola Indonesia', 'Lapangan olahraga terbuka di desa', 'Foto contoh: lapangan olahraga'),
	'air-bersih' => array('Water tank village', 'Sumber mata air dan bak penampungan', 'Foto contoh: sumber air bersih'),
	'teh-produk' => array('Dried tea leaves', 'Daun teh kering siap seduh', 'Foto contoh: produk olahan teh'),
	'susu' => array('Milk glass bottle', 'Susu sapi segar dalam wadah', 'Foto contoh: produk susu'),
);

function api_get($url, $agent)
{
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER => TRUE, CURLOPT_TIMEOUT => 40,
		CURLOPT_USERAGENT => $agent, CURLOPT_FOLLOWLOCATION => TRUE,
		// PHP CLI di XAMPP tidak punya curl.cainfo; pakai bundel CA yang sudah ada
		// alih-alih mematikan verifikasi sertifikat.
		CURLOPT_CAINFO => $GLOBALS['chw_ca'],
	));
	$body = curl_exec($ch);
	$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	if ($code !== 200)
	{
		// Commons membatasi laju; satu kali coba ulang setelah jeda sudah cukup.
		sleep(3);
		$ch = curl_init($url);
		curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => TRUE, CURLOPT_TIMEOUT => 40,
			CURLOPT_USERAGENT => $agent, CURLOPT_FOLLOWLOCATION => TRUE, CURLOPT_CAINFO => $GLOBALS['chw_ca']));
		$body = curl_exec($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
	}
	return ($code === 200) ? $body : NULL;
}

$header = array('filename', 'alt_text', 'caption', 'source_credit', 'source_year', 'license_note', 'rights_status', 'people_shown', 'is_placeholder');
$rows = array($header);
$have = array();
// Jalankan ulang hanya untuk yang belum ada; baris lama tidak hilang.
if (is_file($out.'manifest.csv'))
{
	$fh = fopen($out.'manifest.csv', 'r');
	fgetcsv($fh);
	while (($r = fgetcsv($fh)) !== FALSE)
	{
		if (empty($r[0]) OR ! is_file($out.$r[0])) { continue; }
		$rows[] = $r;
		$have[pathinfo($r[0], PATHINFO_FILENAME)] = TRUE;
	}
	fclose($fh);
}
$ok = 0; $fail = 0; $skip = 0;

foreach ($wanted as $key => $spec)
{
	list($query, $alt, $caption) = $spec;
	if (isset($have[$key]))
	{
		$skip++;
		continue;
	}
	$url = 'https://commons.wikimedia.org/w/api.php?action=query&format=json&generator=search'
		.'&gsrsearch='.rawurlencode($query).'&gsrnamespace=6&gsrlimit=8'
		.'&prop=imageinfo&iiprop=url|extmetadata|size&iiurlwidth=1600';
	$body = api_get($url, $agent);
	$json = $body ? json_decode($body, TRUE) : NULL;
	if (empty($json['query']['pages']))
	{
		echo str_pad('KOSONG', 10).$key.'  ('.$query.')'.PHP_EOL;
		$fail++;
		continue;
	}

	// Kandidat pertama yang berupa JPEG/PNG dan cukup besar.
	// Hasil teratas sering berupa poster, logo, peta atau papan nama, bukan foto keadaan.
	// Itu disaring dari judul berkas, dan foto melebar diutamakan.
	$reject = '/poster|logo|peta |map of|diagram|infografi|infographic|banner|plakat|coat of arms|lambang|stamp|cover|screenshot|chart/i';
	$chosen = NULL; $fallback = NULL;
	foreach ($json['query']['pages'] as $page)
	{
		$ii = isset($page['imageinfo'][0]) ? $page['imageinfo'][0] : NULL;
		if ( ! $ii OR empty($ii['thumburl'])) { continue; }
		$ext = strtolower(pathinfo(parse_url($ii['url'], PHP_URL_PATH), PATHINFO_EXTENSION));
		if ( ! in_array($ext, array('jpg', 'jpeg', 'png'), TRUE)) { continue; }
		if ((int) $ii['width'] < 900) { continue; }
		if (preg_match($reject, (string) $page['title'])) { continue; }
		if ($fallback === NULL) { $fallback = $ii; }
		if ((int) $ii['width'] >= (int) $ii['height']) { $chosen = $ii; break; }
	}
	if ($chosen === NULL) { $chosen = $fallback; }
	if ($chosen === NULL)
	{
		echo str_pad('TAKCOCOK', 10).$key.PHP_EOL;
		$fail++;
		continue;
	}

	$binary = api_get($chosen['thumburl'], $agent);
	$im = ($binary !== NULL && strlen($binary) > 8000) ? @imagecreatefromstring($binary) : FALSE;
	if ($im === FALSE)
	{
		echo str_pad('GAGAL', 10).$key.PHP_EOL;
		$fail++;
		continue;
	}
	// Disimpan ulang sebagai JPEG supaya jenis berkas dan ekstensinya pasti cocok.
	$name = $key.'.jpg';
	imagejpeg($im, $out.$name, 86);
	imagedestroy($im);

	$em = $chosen['extmetadata'];
	$artist = trim(strip_tags(isset($em['Artist']['value']) ? $em['Artist']['value'] : 'Wikimedia Commons'));
	$license = trim(strip_tags(isset($em['LicenseShortName']['value']) ? $em['LicenseShortName']['value'] : 'lihat halaman berkas'));
	$year = preg_match('/(\d{4})/', (string) (isset($em['DateTimeOriginal']['value']) ? $em['DateTimeOriginal']['value'] : ''), $m) ? (int) $m[1] : NULL;
	$descurl = isset($chosen['descriptionurl']) ? $chosen['descriptionurl'] : 'https://commons.wikimedia.org/';

	$rows[] = array($name, $alt, $caption.' \u{2014} bukan foto Desa Cihawuk.',
		mb_substr($artist, 0, 200), $year,
		mb_substr($license.' | '.$descurl, 0, 255), 'licensed', '', '1');
	echo str_pad('OK', 10).str_pad($key, 22).str_pad($license, 16).mb_substr($artist, 0, 36).PHP_EOL;
	$ok++;
	usleep(900000);
}

$h = fopen($out.'manifest.csv', 'w');
foreach ($rows as $r) { fputcsv($h, $r); }
fclose($h);
echo PHP_EOL.'Selesai: '.$ok.' berhasil, '.$skip.' sudah ada, '.$fail.' gagal. Manifest: '.$out.'manifest.csv'.PHP_EOL;
