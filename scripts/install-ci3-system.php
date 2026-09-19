<?php
/**
 * Pasang ulang folder system/ dari paket Composer codeigniter/framework 3.1.13
 * lalu terapkan patch kompatibilitas PHP 8.3 dari folder patches/.
 *
 *   php scripts/install-ci3-system.php
 *
 * Skrip memverifikasi checksum baseline sebelum menerapkan patch.
 */
$root = dirname(__DIR__);
$vendor = $root.'/vendor/codeigniter/framework/system';
$target = $root.'/system';
$expectedBaseline = '5f0d5870a7993384b78704fb193517ec79e67ca44b9373cbb5f9c9c192498a88';
$patch = $root.'/patches/ci3-3.1.13-php83.patch';

if (!is_dir($vendor)) {
    fwrite(STDERR, "Jalankan composer install terlebih dahulu.\n");
    exit(1);
}

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vendor, FilesystemIterator::SKIP_DOTS));
$list = [];
foreach ($files as $file) {
    $rel = 'system/'.str_replace(DIRECTORY_SEPARATOR, '/',substr($file->getPathname(), strlen($vendor) + 1));
    $list[$rel] = hash_file('sha256', $file->getPathname());
}
ksort($list, SORT_STRING);
$manifest = '';
foreach ($list as $rel => $hash) {
    $manifest .= $hash.' *'.$rel."\n";
}
$baseline = hash('sha256', $manifest);
if ($baseline !== $expectedBaseline) {
    fwrite(STDERR, "Checksum baseline CI3 tidak cocok ($baseline). Periksa sumber paket.\n");
    exit(1);
}

$rrmdir = function ($dir) use (&$rrmdir) {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir.'/'.$item;
        is_dir($path) ? $rrmdir($path) : unlink($path);
    }
    rmdir($dir);
};
$rrmdir($target);

$copy = function ($src, $dst) use (&$copy) {
    mkdir($dst, 0755, true);
    foreach (scandir($src) as $item) {
        if ($item === '.' || $item === '..') continue;
        is_dir("$src/$item") ? $copy("$src/$item", "$dst/$item") : copy("$src/$item", "$dst/$item");
    }
};
$copy($vendor, $target);

passthru('git -c core.autocrlf=false -C '.escapeshellarg($root).' apply --whitespace=nowarn '.escapeshellarg($patch), $code);
if ($code !== 0) {
    fwrite(STDERR, "Patch gagal diterapkan.\n");
    exit(1);
}
echo "system/ dipasang dari baseline $baseline dan patch diterapkan.\n";
