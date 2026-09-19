<?php
/* Kepala halaman portal mitra. Tetapkan $judul, $sub (opsional), $menu. */
$mitra = wajibMitra();
$judul = $judul ?? 'Mitra';
$menu  = $menu ?? '';

$daftarMenu = [
    'ringkasan'   => ['Ringkasan',   tautan()],
    'tautan'      => ['Link produk', tautan('tautan')],
    'penghasilan' => ['Penghasilan', tautan('penghasilan')],
    'penarikan'   => ['Penarikan',   tautan('penarikan')],
    'profil'      => ['Profil',      tautan('profil')],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($judul) ?> &mdash; Mitra Invishar</title>
<link rel="icon" href="/assets/invishar-logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:ital,opsz,wght@0,9..40,400..600;1,9..40,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asetPanelCss()) ?>">
<link rel="stylesheet" href="<?= e(asetMitra('mitra.css')) ?>">
</head>
<body class="mitra">

<a class="skip" href="#isi">Lompat ke konten</a>

<header class="m-bilah">
  <div class="m-bilah-isi">
    <a class="m-merek" href="<?= tautan() ?>">
      <img src="/assets/invishar-logo.png" alt="" width="30" height="30">
      <span>invishar <em>Mitra</em></span>
    </a>

    <nav class="m-menu" aria-label="Menu mitra">
      <?php foreach ($daftarMenu as $kunci => [$label, $alamat]): ?>
        <a href="<?= e($alamat) ?>"<?= $menu === $kunci ? ' class="is-on" aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="m-akun">
      <span class="m-akun-nama"><?= e(namaDepan($mitra['nama'])) ?></span>
      <a class="m-keluar" href="<?= tautan('keluar') ?>">Keluar</a>
    </div>
  </div>
</header>

<main class="m-isi" id="isi">
  <?php if ($mitra['status'] === 'dibekukan'): ?>
    <div class="pita pita-bahaya" role="alert">
      <span><strong>Akun Anda sedang dibekukan.</strong> Link Anda tidak mencatat penjualan baru dan penarikan
      belum bisa diajukan. Riwayat tetap bisa dilihat. Hubungi admin Invishar untuk penjelasan.</span>
    </div>
  <?php endif; ?>

  <?php $kabar = ambilPesan(); ?>
  <?php if ($kabar): ?>
    <div class="kabar-wadah" role="status" aria-live="polite">
      <?php foreach ($kabar as $p): ?>
        <div class="kabar kabar-<?= e($p['jenis']) ?>" data-kabar>
          <span><?= e($p['teks']) ?></span>
          <button class="kabar-tutup" type="button" aria-label="Tutup pesan">&times;</button>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="m-kepala">
    <h1><?= e($judul) ?></h1>
    <?php if (!empty($sub)): ?><p><?= $sub ?></p><?php endif; ?>
  </div>
