<?php
declare(strict_types=1);

/* =============================================================================
   Pintu masuk area toko (publik, tanpa login): link affiliate, checkout,
   halaman bayar uji, halaman selesai, dan webhook Midtrans.

   Tidak memakai sesi. Keamanan tiap halaman berdiri sendiri: batas per IP,
   perangkap robot, token bertanda tangan, dan tanda tangan Midtrans.
   ============================================================================= */

require_once dirname(__DIR__, 2) . '/panel/inc/inti.php';
require_once dirname(__DIR__, 2) . '/panel/inc/gerbang.php';
require_once dirname(__DIR__, 2) . '/panel/inc/produk.php';

header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: strict-origin-when-cross-origin');

/** Halaman toko sederhana untuk keadaan yang tidak bisa dilanjutkan. */
function halamanBuntu(int $kode, string $judul, string $teks, string $tautan = '/', string $labelTautan = 'Ke beranda'): void
{
    http_response_code($kode);
    $judulHalaman = $judul;
    require __DIR__ . '/kepala.php';
    ?>
    <section class="toko-wrap toko-buntu">
      <div class="status-ikon status-netral" aria-hidden="true">!</div>
      <h1><?= e($judul) ?></h1>
      <p class="lead"><?= e($teks) ?></p>
      <a class="btn btn-solid" href="<?= e($tautan) ?>"><?= e($labelTautan) ?></a>
    </section>
    <?php
    require __DIR__ . '/kaki.php';
    exit;
}
