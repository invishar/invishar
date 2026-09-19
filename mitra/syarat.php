<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';

$judul = 'Syarat & ketentuan';
$halaman = 'syarat';
require __DIR__ . '/inc/luar-kepala.php';
?>

<main class="m-luar" id="isi">
  <div class="m-luar-kotak" style="max-width:720px">
    <section class="kotak">
      <p class="masuk-kicker">Mitra Invishar</p>
      <h1 style="margin:0 0 16px;font-size:28px;letter-spacing:-0.035em">Syarat &amp; ketentuan</h1>
      <div class="kotak-syarat" style="max-height:none;font-size:14.5px"><?= e(setelan('affiliate.syarat')) ?></div>

      <h2 class="sub-judul">Ketentuan komisi yang berlaku saat ini</h2>
      <dl class="keadaan">
        <dt>Lama link berlaku</dt>
        <dd>Pembeli yang membuka link Anda ditandai selama <?= setelanAngka('affiliate.cookie_hari') ?> hari. Kalau ia membuka link mitra lain sesudahnya, link terakhir yang berlaku.</dd>
        <dt>Masa tahan komisi</dt>
        <dd><?= setelanAngka('affiliate.masa_tahan_hari') === 0 ? 'Komisi langsung bisa ditarik setelah pembayaran lunas.' : 'Komisi bisa ditarik ' . setelanAngka('affiliate.masa_tahan_hari') . ' hari setelah pembayaran lunas.' ?></dd>
        <dt>Minimal penarikan</dt>
        <dd><?= e(rupiah(setelanAngka('affiliate.min_tarik'))) ?></dd>
        <dt>Produk berlangganan</dt>
        <dd>Komisi dari setiap pembayaran bulanan, hingga <?= setelanAngka('affiliate.bulan_berulang') ?> bulan (kecuali tertulis lain pada produknya).</dd>
      </dl>
    </section>
  </div>
</main>

<?php require __DIR__ . '/inc/luar-kaki.php'; ?>
