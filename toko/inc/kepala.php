<?php
/* Kepala halaman toko. Tetapkan $judulHalaman sebelum memanggil. */
$judulHalaman = $judulHalaman ?? 'Invishar';
$asetToko = '/toko/aset/toko.css?v=' . (int) @filemtime(dirname(__DIR__) . '/aset/toko.css');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($judulHalaman) ?> &mdash; Invishar</title>
<link rel="icon" href="/assets/invishar-logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:ital,opsz,wght@0,9..40,400..600;1,9..40,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/style.css?v=20260919b">
<link rel="stylesheet" href="<?= e($asetToko) ?>">
</head>
<body class="toko">

<?php if (modeUji()): ?>
  <div class="pita-uji" role="note">
    <strong>MODE UJI</strong> — belum ada uang sungguhan yang berpindah. Pembayaran di sini hanya simulasi.
  </div>
<?php endif; ?>

<header class="nav toko-nav">
  <div class="nav-inner wrap">
    <a class="brand" href="/">
      <img src="/assets/invishar-logo.png" alt="" width="30" height="30">
      <span>invishar</span>
    </a>
    <span class="toko-aman"><span aria-hidden="true">🔒</span> Pembayaran aman</span>
  </div>
</header>

<main id="isi">
