<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
wajibMasuk();

/* =============================================================================
   Pembaruan basis data: menjalankan berkas panel/migrasi/*.sql yang belum
   pernah dijalankan. Setiap berkas hanya MENAMBAH tabel atau kolom — tidak
   pernah menghapus data — dan tercatat supaya tidak dijalankan dua kali.
   ============================================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    try {
        $dijalankan = jalankanMigrasi();
        if ($dijalankan) {
            catatLog('pembaruan basis data', implode(', ', $dijalankan));
            pesan('Pembaruan selesai: ' . count($dijalankan) . ' langkah dijalankan. Menu Penjualan dan Affiliate sudah bisa dipakai.');
        } else {
            pesan('Tidak ada yang perlu diperbarui.');
        }
    } catch (PDOException $e) {
        pesan('Pembaruan berhenti karena galat basis data: ' . $e->getMessage(), 'buruk');
    }
    pergi(tautan('pembaruan'));
}

$semua = daftarMigrasi();
$tertunda = migrasiTertunda();
$terpasang = [];
foreach (ambilSemua('SELECT nama, dijalankan_pada FROM migrasi') as $b) {
    $terpasang[$b['nama']] = $b['dijalankan_pada'];
}

/* Nama berkas → keterangan yang bisa dibaca manusia. */
function keteranganMigrasi(string $nama): string
{
    $peta = [
        '001_setelan.sql'   => 'Tempat menyimpan setelan affiliate (lama cookie, masa tahan, dan lainnya)',
        '002_produk.sql'    => 'Daftar produk beserta landing page dan setelan komisinya',
        '003_affiliate.sql' => 'Akun affiliator dan catatan klik link',
        '004_transaksi.sql' => 'Transaksi, langganan, komisi, dan penarikan',
    ];
    return $peta[$nama] ?? $nama;
}

$judul = 'Pembaruan basis data';
$menu  = 'pengaturan';
require __DIR__ . '/inc/kepala.php';
?>

<section class="kotak kotak-sempit">
  <div class="kotak-kepala">
    <h2><?= $tertunda ? 'Ada ' . count($tertunda) . ' pembaruan yang belum dijalankan' : 'Basis data sudah terbaru' ?></h2>
    <?php if (!$tertunda): ?><span class="tanda tanda-baik">Terbaru</span><?php endif; ?>
  </div>

  <p class="pengantar">
    Pembaruan hanya <strong>menambah</strong> tabel atau kolom baru. Data yang sudah ada &mdash; kelas, order,
    inventaris &mdash; tidak disentuh. Setiap langkah tercatat, jadi tidak akan dijalankan dua kali.
  </p>

  <ol class="langkah-daftar">
    <?php foreach ($semua as $nama): ?>
      <?php $sudah = isset($terpasang[$nama]); ?>
      <li class="<?= $sudah ? 'is-sudah' : 'is-tunda' ?>">
        <span class="langkah-tanda" aria-hidden="true"><?= $sudah ? '✓' : '•' ?></span>
        <span class="langkah-isi">
          <span class="langkah-judul"><?= e(keteranganMigrasi($nama)) ?></span>
          <span class="langkah-sub"><?= $sudah ? 'Dijalankan ' . e(waktuIndo($terpasang[$nama])) : 'Belum dijalankan' ?></span>
        </span>
      </li>
    <?php endforeach; ?>
  </ol>

  <?php if ($tertunda): ?>
    <form method="post" class="form-aksi">
      <?= csrfInput() ?>
      <button class="tbl tbl-utama" type="submit">Jalankan pembaruan</button>
    </form>
  <?php else: ?>
    <div class="form-aksi">
      <a class="tbl tbl-utama" href="<?= tautan('produk') ?>">Ke halaman Produk</a>
      <a class="tbl" href="<?= tautan() ?>">Ke Ringkasan</a>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/inc/kaki.php'; ?>
