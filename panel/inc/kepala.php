<?php
/* Kepala halaman panel. Halaman memanggilnya setelah menetapkan:
   $judul  — judul halaman
   $menu   — kunci menu yang sedang aktif  */
$pengguna = wajibMasuk();
$judul = $judul ?? 'Panel';
$menu  = $menu ?? '';

$daftarMenu = [
    'ringkasan'  => ['Ringkasan',       'index.php'],
    'order'      => ['Order jasa',      'order.php'],
    'kelas'      => ['Kelas',           'kelas.php'],
    'inventaris' => ['Inventaris',      'inventaris.php'],
    'aplikasi'   => ['Aplikasi',        'aplikasi.php'],
    'pengaturan' => ['Pengaturan',      'pengaturan.php'],
];

$orderBaru = (int) ambilNilai("SELECT COUNT(*) FROM order_jasa WHERE status = 'baru'");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($judul) ?> &mdash; Panel Invishar</title>
<link rel="icon" href="https://invishar.com/assets/invishar-logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:ital,opsz,wght@0,9..40,400..600;1,9..40,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="aset/panel.css">
</head>
<body>

<a class="skip" href="#isi">Lompat ke konten</a>

<div class="rangka">

  <aside class="sisi" id="sisi">
    <div class="sisi-atas">
      <a class="merek" href="index.php">
        <span class="merek-tanda">iv</span>
        <span class="merek-teks">Panel<em>Invishar</em></span>
      </a>
    </div>

    <nav class="sisi-menu" aria-label="Menu panel">
      <?php foreach ($daftarMenu as $kunci => [$label, $tautan]): ?>
        <a href="<?= e($tautan) ?>"<?= $menu === $kunci ? ' class="is-on" aria-current="page"' : '' ?>>
          <?= e($label) ?>
          <?php if ($kunci === 'order' && $orderBaru > 0): ?>
            <span class="lencana"><?= $orderBaru ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="sisi-bawah">
      <p class="sisi-nama"><?= e($pengguna['nama']) ?></p>
      <p class="sisi-surel"><?= e($pengguna['surel']) ?></p>
      <a class="sisi-keluar" href="keluar.php">Keluar</a>
    </div>
  </aside>

  <div class="utama">
    <header class="bilah">
      <button class="burger" type="button" id="burger" aria-label="Buka menu" aria-expanded="false" aria-controls="sisi">
        <span></span><span></span><span></span>
      </button>
      <h1><?= e($judul) ?></h1>
      <?php if (!empty($aksiKepala)): ?>
        <div class="bilah-aksi"><?= $aksiKepala ?></div>
      <?php endif; ?>
    </header>

    <main class="isi" id="isi">
      <?php foreach (ambilPesan() as $p): ?>
        <p class="kabar kabar-<?= e($p['jenis']) ?>"><?= e($p['teks']) ?></p>
      <?php endforeach; ?>
