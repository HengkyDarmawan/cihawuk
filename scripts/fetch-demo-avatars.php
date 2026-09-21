<?php
/**
 * Unduh avatar ilustrasi untuk struktur organisasi demo.
 *
 * Nama perangkat desa pada seed master adalah nama ASLI dari dokumen S3. Memasang potret
 * orang sungguhan dari internet pada nama itu membuat wajah orang asing tampak sebagai
 * pejabat desa, jadi demo memakai avatar ilustrasi yang tidak menggambarkan siapa pun.
 *
 * Sumber: DiceBear, gaya "Personas" oleh Draftbit (CC BY 4.0). Setiap berkas ditandai
 * sebagai placeholder dan captionnya menyatakan bahwa itu ilustrasi.
 *
 * Hasil: reference/media/demo-avatars/*.png + manifest.csv, siap untuk
 * `tools import_media demo-avatars`. Bila unduhan gagal, avatar inisial dibuat lokal
 * dengan GD supaya demo tetap dapat diisi tanpa jaringan.
 *
 * Pemakaian: php scripts/fetch-demo-avatars.php
 */

$root = dirname(__DIR__);
$out  = $root.'/reference/media/demo-avatars/';
@mkdir($out, 0775, TRUE);

$candidates = array('C:/xampp/apache/bin/curl-ca-bundle.crt', 'C:/Program Files/Git/mingw64/etc/ssl/certs/ca-bundle.crt', ini_get('curl.cainfo'));
$ca = NULL;
foreach ($candidates as $c) { if ($c && is_file($c)) { $ca = $c; break; } }

// Dua kelompok supaya ilustrasi tidak terlalu janggal terhadap nama: "p" tanpa batasan
// rambut panjang, "w" berambut panjang/sanggul tanpa kumis. Jumlahnya mengikuti
// kebutuhan DemoSeeder::organization().
$pools = array(
	'p' => array('count' => 22, 'params' => 'hair=shortCombover,buzzcut,fade,balding,cap,curly&facialHairProbability=40'),
	'w' => array('count' => 12, 'params' => 'hair=long,bobCut,bobBangs,straightBun,curlyBun,extraLong&facialHairProbability=0'),
);
$backgrounds = array('d7ecdf', 'f4e3c3', 'dbe7f3', 'efdcd2', 'e2e0f2', 'e8efd6');

$header = array('filename', 'alt_text', 'caption', 'source_credit', 'source_year', 'license_note', 'rights_status', 'people_shown', 'is_placeholder');
$rows = array($header);
$ok = 0; $local = 0;

foreach ($pools as $prefix => $pool)
{
	for ($i = 1; $i <= $pool['count']; $i++)
	{
		$name = sprintf('avatar-%s-%02d.png', $prefix, $i);
		$bg = $backgrounds[($i - 1) % count($backgrounds)];
		$url = 'https://api.dicebear.com/9.x/personas/png?size=512&seed='.rawurlencode('cihawuk-'.$prefix.$i)
			.'&backgroundColor='.$bg.'&'.$pool['params'];

		$binary = is_file($out.$name) ? file_get_contents($out.$name) : fetch($url, $ca);
		$im = ($binary !== NULL && strlen($binary) > 2000) ? @imagecreatefromstring($binary) : FALSE;
		$credit = 'DiceBear "Personas" oleh Draftbit';
		$license = 'CC BY 4.0 | https://www.dicebear.com/styles/personas/';
		if ($im === FALSE)
		{
			$im = initials_avatar($prefix.$i, $bg);
			$credit = 'Avatar inisial buatan sistem';
			$license = 'Dibuat lokal oleh scripts/fetch-demo-avatars.php';
			$local++;
		}
		else
		{
			$ok++;
		}
		imagepng($im, $out.$name);
		imagedestroy($im);

		$rows[] = array($name, 'Ilustrasi avatar', "Ilustrasi \u{2014} bukan foto asli perangkat desa.",
			$credit, 2026, $license, 'licensed', '', '1');
		echo str_pad($name, 22).($credit === 'Avatar inisial buatan sistem' ? 'LOKAL' : 'OK').PHP_EOL;
		usleep(200000);
	}
}

$h = fopen($out.'manifest.csv', 'w');
foreach ($rows as $r) { fputcsv($h, $r); }
fclose($h);
echo PHP_EOL.'Selesai: '.$ok.' dari DiceBear, '.$local.' dibuat lokal. Manifest: '.$out.'manifest.csv'.PHP_EOL;

function fetch($url, $ca)
{
	$ch = curl_init($url);
	$opts = array(
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_FOLLOWLOCATION => TRUE,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_USERAGENT => 'CihawukVillageDemo/1.0 (offline demo seeding)',
	);
	if ($ca !== NULL) { $opts[CURLOPT_CAINFO] = $ca; }
	curl_setopt_array($ch, $opts);
	$body = curl_exec($ch);
	$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	return ($body !== FALSE && $code === 200) ? $body : NULL;
}

/** Avatar cadangan: lingkaran warna dengan siluet sederhana, tanpa jaringan. */
function initials_avatar($seed, $bg)
{
	$size = 512;
	$im = imagecreatetruecolor($size, $size);
	$bgc = imagecolorallocate($im, hexdec(substr($bg, 0, 2)), hexdec(substr($bg, 2, 2)), hexdec(substr($bg, 4, 2)));
	imagefill($im, 0, 0, $bgc);
	$shade = 60 + (crc32($seed) % 60);
	$fg = imagecolorallocate($im, $shade, $shade + 30, $shade + 10);
	imagefilledellipse($im, 256, 200, 190, 190, $fg);
	imagefilledellipse($im, 256, 500, 380, 300, $fg);
	return $im;
}
