<?php
/* Kepala halaman mitra yang dibuka TANPA login: daftar, masuk, syarat. */
$judul = $judul ?? 'Mitra Invishar';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul) ?> &mdash; Mitra Invishar</title>
<meta name="description" content="Program affiliate Invishar: bagikan link produk, dapatkan komisi dari setiap penjualan.">
<link rel="icon" href="/assets/invishar-logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:ital,opsz,wght@0,9..40,400..600;1,9..40,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asetPanelCss()) ?>">
<link rel="stylesheet" href="<?= e(asetMitra('mitra.css')) ?>">
</head>
<body class="mitra">

<header class="m-bilah">
  <div class="m-bilah-isi">
    <a class="m-merek" href="/">
      <img src="/assets/invishar-logo.png" alt="" width="30" height="30">
      <span>invishar <em>Mitra</em></span>
    </a>
    <div class="m-akun">
      <?php if (($halaman ?? '') !== 'masuk'): ?><a class="tbl tbl-kecil" href="<?= tautan('masuk') ?>">Masuk</a><?php endif; ?>
      <?php if (($halaman ?? '') !== 'daftar'): ?><a class="tbl tbl-kecil tbl-utama" href="<?= tautan('daftar') ?>">Daftar</a><?php endif; ?>
    </div>
  </div>
</header>
