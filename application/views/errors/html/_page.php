<?php defined('BASEPATH') OR exit('No direct script access allowed');
/* Halaman error mandiri (tanpa ketergantungan DB/layout). Tidak menampilkan detail teknis ke pengguna. */
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= htmlspecialchars($chw_title, ENT_QUOTES, 'UTF-8') ?> — Desa Cihawuk</title>
<style>
body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#F7F9F6;color:#182B24;line-height:1.6;display:grid;min-height:100vh;place-items:center;padding:24px;box-sizing:border-box}
main{max-width:560px;background:#fff;border:1px solid #DCE5DE;border-radius:20px;padding:32px}
h1{font-family:Georgia,serif;font-size:1.9rem;margin:0 0 8px;color:#10392D}
p{margin:0 0 16px;color:#3E4B45}
a{display:inline-flex;min-height:44px;align-items:center;padding:0 20px;border-radius:999px;background:#174B3A;color:#fff;text-decoration:none;font-weight:700}
a:focus-visible{outline:3px solid #8A6420;outline-offset:2px}
small{color:#596A62}
</style>
</head>
<body>
<main>
<h1><?= htmlspecialchars($chw_title, ENT_QUOTES, 'UTF-8') ?></h1>
<p><?= $chw_message ?></p>
<a href="/">Kembali ke beranda</a>
</main>
</body>
</html>
