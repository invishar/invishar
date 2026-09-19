<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
require_once __DIR__ . '/inc/gerbang.php';
wajibMasuk();
wajibPenjualanSiap();

$saring = $_GET['status'] ?? '';
$saring = isset(STATUS_TRANSAKSI[$saring]) ? $saring : '';
$produkId = (int) ($_GET['produk'] ?? 0);

$syarat = [];
$isi = [];
if ($saring !== '') {
    $syarat[] = 't.status = ?';
    $isi[] = $saring;
}
if ($produkId > 0) {
    $syarat[] = 't.produk_id = ?';
    $isi[] = $produkId;
}
$daftar = ambilSemua(
    'SELECT t.*, p.nama AS produk_nama, p.jenis AS produk_jenis, a.kode AS affiliate_kode, a.nama AS affiliate_nama
       FROM transaksi t
       JOIN produk p ON p.id = t.produk_id
       LEFT JOIN affiliate a ON a.id = t.affiliate_id'
    . ($syarat ? ' WHERE ' . implode(' AND ', $syarat) : '')
    . ' ORDER BY t.dibuat_pada DESC, t.id DESC LIMIT 500',
    $isi
);

$hitung = [];
foreach (ambilSemua('SELECT status, COUNT(*) AS jml FROM transaksi GROUP BY status') as $b) {
    $hitung[$b['status']] = (int) $b['jml'];
}
$bulanIni = ambilSatu(
    "SELECT COUNT(*) AS n, COALESCE(SUM(jumlah), 0) AS rp FROM transaksi
      WHERE status = 'lunas' AND dibayar_pada >= DATE_FORMAT(NOW(), '%Y-%m-01')"
);
$komisiBulanIni = (int) ambilNilai(
    "SELECT COALESCE(SUM(jumlah), 0) FROM komisi WHERE jenis = 'komisi' AND status = 'berlaku' AND dibuat_pada >= DATE_FORMAT(NOW(), '%Y-%m-01')"
);
$filterProduk = $produkId > 0 ? ambilSatu('SELECT id, nama FROM produk WHERE id = ?', [$produkId]) : null;

$judul = 'Transaksi';
$menu  = 'transaksi';
$aksiKepala = '<a class="tbl tbl-utama" href="' . e(tautan('transaksi-catat')) . '">+ Catat pembayaran manual</a>';
require __DIR__ . '/inc/kepala.php';
?>

<?php if (modeUji()): ?>
  <div class="pita pita-peringatan">
    <span><strong>Mode uji aktif.</strong> Checkout di situs memakai pembayaran simulasi — belum ada uang sungguhan.
      Pindah ke Midtrans lewat <code>konfig.php</code> (lihat AFFILIATE.md).</span>
  </div>
<?php endif; ?>

<div class="angka-kisi">
  <div class="angka angka-diam"><span class="angka-num angka-rp"><?= e(rupiah((int) $bulanIni['rp'])) ?></span><span class="angka-lbl">Lunas bulan ini · <?= (int) $bulanIni['n'] ?> transaksi</span></div>
  <a class="angka" href="<?= tautan('transaksi') ?>?status=menunggu"><span class="angka-num"><?= $hitung['menunggu'] ?? 0 ?></span><span class="angka-lbl">Menunggu bayar</span></a>
  <div class="angka angka-diam"><span class="angka-num angka-rp"><?= e(rupiah($komisiBulanIni)) ?></span><span class="angka-lbl">Komisi affiliate bulan ini</span></div>
</div>

<?php if ($filterProduk): ?>
  <div class="pita pita-info">
    <span>Menampilkan transaksi <strong><?= e($filterProduk['nama']) ?></strong> saja.</span>
    <a class="tbl tbl-kecil" href="<?= tautan('transaksi') ?>">Tampilkan semua</a>
  </div>
<?php endif; ?>

<?php if (array_sum($hitung) > 0): ?>
  <div class="saring">
    <a class="cip<?= $saring === '' ? ' is-on' : '' ?>" href="<?= tautan('transaksi') ?><?= $produkId ? '?produk=' . $produkId : '' ?>">Semua <span><?= array_sum($hitung) ?></span></a>
    <?php foreach (STATUS_TRANSAKSI as $kunci => $label): ?>
      <a class="cip<?= $saring === $kunci ? ' is-on' : '' ?>" href="<?= tautan('transaksi') ?>?status=<?= e($kunci) ?><?= $produkId ? '&amp;produk=' . $produkId : '' ?>"><?= e($label) ?> <span><?= $hitung[$kunci] ?? 0 ?></span></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (!$daftar && array_sum($hitung) === 0): ?>
  <div class="kosong kosong-besar">
    <h3>Belum ada transaksi</h3>
    <p>Transaksi tercatat otomatis saat pembeli checkout di landing page produk. Pembayaran yang masuk lewat
       transfer atau WhatsApp bisa dicatat manual — komisi affiliate-nya ikut dihitung.</p>
    <a class="tbl tbl-utama" href="<?= tautan('transaksi-catat') ?>">+ Catat pembayaran manual</a>
  </div>
<?php elseif (!$daftar): ?>
  <p class="kosong">Tidak ada transaksi dengan saringan ini.</p>
<?php else: ?>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr>
          <th>Pesanan</th>
          <th>Pembeli</th>
          <th>Produk</th>
          <th class="kanan">Jumlah</th>
          <th>Affiliate</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($daftar as $t): ?>
          <tr onclick="location='<?= tautan('transaksi/' . (int) $t['id']) ?>'">
            <td>
              <a class="tabel-utama tabel-kode" href="<?= tautan('transaksi/' . (int) $t['id']) ?>"><?= e($t['kode_order']) ?></a>
              <span class="tabel-sub"><?= e(waktuIndo($t['dibuat_pada'])) ?> · <?= e($t['gerbang'] === 'manual' ? 'manual' : ($t['gerbang'] === 'uji' ? 'uji' : 'Midtrans')) ?></span>
            </td>
            <td><?= e($t['pembeli_nama']) ?><?php if ($t['whatsapp']): ?><span class="tabel-sub"><?= e($t['whatsapp']) ?></span><?php endif; ?></td>
            <td><?= e($t['produk_nama']) ?><?php if ($t['produk_jenis'] === 'langganan'): ?><span class="tabel-sub">bulan ke-<?= (int) $t['periode_ke'] ?></span><?php endif; ?></td>
            <td class="kanan tebal"><?= e(rupiah((int) $t['jumlah'])) ?></td>
            <td>
              <?php if ($t['affiliate_kode']): ?>
                <span class="tabel-kode"><?= e($t['affiliate_kode']) ?></span>
                <?php if ((int) $t['beli_sendiri']): ?><span class="tabel-sub">beli sendiri · tanpa komisi</span><?php endif; ?>
              <?php else: ?>
                <span class="teks-kecil">—</span>
              <?php endif; ?>
            </td>
            <td><span class="tanda tanda-<?= e($t['status']) ?>"><?= e(STATUS_TRANSAKSI[$t['status']]) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/inc/kaki.php'; ?>
