<?php
/* Kepala halaman panel. Halaman memanggilnya setelah menetapkan:
   $judul  — judul halaman
   $menu   — kunci menu yang sedang aktif  */
$pengguna = wajibMasuk();
$judul = $judul ?? 'Panel';
$menu  = $menu ?? '';

/* Menu dikelompokkan supaya letak tiap urusan mudah ditebak.
   [grup => [kunci => [label, tautan]]]; grup '' tanpa judul. */
$grupMenu = [
    '' => [
        'ringkasan'  => ['Ringkasan',  tautan()],
    ],
    'Penjualan' => [
        'order'      => ['Order jasa', tautan('order')],
        'produk'     => ['Produk',     tautan('produk')],
        'transaksi'  => ['Transaksi',  tautan('transaksi')],
    ],
    'Affiliate' => [
        'produk-affiliate' => ['Produk affiliate', tautan('produk-affiliate')],
        'affiliate'  => ['Affiliator', tautan('affiliate')],
        'penarikan'  => ['Penarikan',  tautan('penarikan')],
    ],
    'Konten' => [
        'kelas'      => ['Kelas',      tautan('kelas')],
    ],
    'Kantor' => [
        'inventaris' => ['Inventaris', tautan('inventaris')],
        'aplikasi'   => ['Aplikasi',   tautan('aplikasi')],
    ],
];

/* Angka yang menunggu tindakan admin, tampil sebagai lencana di menu. */
$lencanaMenu = [
    'order' => (int) ambilNilai("SELECT COUNT(*) FROM order_jasa WHERE status = 'baru'"),
];
$pembaruanTertunda = !penjualanSiap();
if (!$pembaruanTertunda) {
    $lencanaMenu['affiliate'] = (int) ambilNilai("SELECT COUNT(*) FROM affiliate WHERE status = 'menunggu'");
    $lencanaMenu['penarikan'] = (int) ambilNilai("SELECT COUNT(*) FROM penarikan WHERE status = 'diajukan'");
}
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
<link rel="stylesheet" href="<?= e(aset('panel.css')) ?>">
</head>
<body data-akar="<?= e(tautan()) ?>">

<a class="skip" href="#isi">Lompat ke konten</a>

<div class="rangka">

  <aside class="sisi" id="sisi">
    <div class="sisi-atas">
      <a class="merek" href="<?= tautan() ?>">
        <span class="merek-tanda">iv</span>
        <span class="merek-teks">Panel<em>Invishar</em></span>
      </a>
    </div>

    <nav class="sisi-menu" aria-label="Menu panel">
      <?php foreach ($grupMenu as $grup => $isiGrup): ?>
        <?php if ($grup !== ''): ?><p class="sisi-grup"><?= e($grup) ?></p><?php endif; ?>
        <?php foreach ($isiGrup as $kunci => [$label, $tautan]): ?>
          <a href="<?= e($tautan) ?>"<?= $menu === $kunci ? ' class="is-on" aria-current="page"' : '' ?>>
            <?= e($label) ?>
            <?php if (($lencanaMenu[$kunci] ?? 0) > 0): ?>
              <span class="lencana" title="Menunggu tindakan"><?= (int) $lencanaMenu[$kunci] ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>

    <a class="sisi-atur<?= $menu === 'pengaturan' ? ' is-on' : '' ?>" href="<?= tautan('pengaturan') ?>"<?= $menu === 'pengaturan' ? ' aria-current="page"' : '' ?>>Pengaturan</a>

    <div class="sisi-bawah">
      <p class="sisi-nama"><?= e($pengguna['nama']) ?></p>
      <p class="sisi-surel"><?= e($pengguna['surel']) ?></p>
      <a class="sisi-keluar" href="<?= tautan('keluar') ?>">Keluar</a>
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
      <?php if ($pembaruanTertunda && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'pembaruan.php'): ?>
        <div class="pita pita-peringatan">
          <span><strong>Ada pembaruan basis data.</strong> Jalankan sekali supaya menu Produk, Transaksi, dan Affiliate bisa dipakai. Data yang ada tidak berubah.</span>
          <a class="tbl tbl-kecil tbl-utama" href="<?= tautan('pembaruan') ?>">Jalankan pembaruan</a>
        </div>
      <?php endif; ?>
      <?php $kabar = ambilPesan(); ?>
      <?php if ($kabar): ?>
        <?php /* Melayang, bukan menempel di puncak halaman: setelah menyimpan,
                 posisi gulir dikembalikan ke tempat semula dan pesan di atas
                 sana tidak akan pernah terlihat. */ ?>
        <div class="kabar-wadah" role="status" aria-live="polite">
          <?php foreach ($kabar as $p): ?>
            <div class="kabar kabar-<?= e($p['jenis']) ?>" data-kabar>
              <span><?= e($p['teks']) ?></span>
              <button class="kabar-tutup" type="button" aria-label="Tutup pesan">&times;</button>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
