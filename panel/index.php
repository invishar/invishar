<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
wajibMasuk();   // sebelum kueri apa pun: tamu dialihkan, bukan disuguhi galat

$judul = 'Ringkasan';
$menu  = 'ringkasan';

$orderBaru    = (int) ambilNilai("SELECT COUNT(*) FROM order_jasa WHERE status = 'baru'");
$orderJalan   = (int) ambilNilai("SELECT COUNT(*) FROM order_jasa WHERE status IN ('penawaran','dikerjakan')");
$kelasTerbit  = (int) ambilNilai("SELECT COUNT(*) FROM kelas WHERE status <> 'Segera'");
$kelasTotal   = (int) ambilNilai('SELECT COUNT(*) FROM kelas');
$materiTotal  = (int) ambilNilai('SELECT COUNT(*) FROM materi');
$asetTotal    = (int) ambilNilai('SELECT COUNT(*) FROM aset');
$nilaiJalan   = (int) ambilNilai("SELECT COALESCE(SUM(nilai),0) FROM order_jasa WHERE status IN ('penawaran','dikerjakan')");

$berkasTerbit = rtrim((string) konfig('situs_data'), '/') . '/kelas.json';
$terbitPada   = is_file($berkasTerbit) ? date('Y-m-d H:i:s', (int) filemtime($berkasTerbit)) : null;

$jual = null;
$perluDiproses = null;
if (penjualanSiap()) {
    require_once __DIR__ . '/inc/order.php';
    $perluDiproses = jumlahPerluDiproses();
    $jual = [
        'rp'        => (int) ambilNilai("SELECT COALESCE(SUM(jumlah), 0) FROM transaksi WHERE status = 'lunas' AND dibayar_pada >= DATE_FORMAT(NOW(), '%Y-%m-01')"),
        'n'         => (int) ambilNilai("SELECT COUNT(*) FROM transaksi WHERE status = 'lunas' AND dibayar_pada >= DATE_FORMAT(NOW(), '%Y-%m-01')"),
        'menunggu'  => (int) ambilNilai("SELECT COUNT(*) FROM affiliate WHERE status = 'menunggu'"),
        'tarik_n'   => (int) ambilNilai("SELECT COUNT(*) FROM penarikan WHERE status = 'diajukan'"),
        'tarik_rp'  => (int) ambilNilai("SELECT COALESCE(SUM(jumlah), 0) FROM penarikan WHERE status = 'diajukan'"),
        'komisi'    => (int) ambilNilai("SELECT COALESCE(SUM(jumlah), 0) FROM komisi WHERE jenis = 'komisi' AND status = 'berlaku' AND dibuat_pada >= DATE_FORMAT(NOW(), '%Y-%m-01')"),
    ];
}

$orderTerakhir = ambilSemua('SELECT * FROM order_jasa ORDER BY dibuat_pada DESC LIMIT 6');
$jejak         = ambilSemua('SELECT * FROM log_aktivitas ORDER BY dibuat_pada DESC LIMIT 8');

require __DIR__ . '/inc/kepala.php';
?>

<div class="angka-kisi">
  <?php if ($perluDiproses !== null): ?>
    <a class="angka<?= $perluDiproses > 0 ? ' angka-sorot' : '' ?>" href="<?= tautan('transaksi') ?>?proses=perlu">
      <span class="angka-num"><?= $perluDiproses ?></span>
      <span class="angka-lbl">Pesanan perlu diproses</span>
    </a>
  <?php else: ?>
    <a class="angka" href="<?= tautan('transaksi') ?>">
      <span class="angka-num"><?= $orderBaru ?></span>
      <span class="angka-lbl">Permintaan baru</span>
    </a>
  <?php endif; ?>
  <a class="angka" href="<?= tautan('transaksi') ?>?kategori=jasa&amp;proses=diproses">
    <span class="angka-num"><?= $orderJalan ?></span>
    <span class="angka-lbl">Permintaan jasa berjalan</span>
  </a>
  <a class="angka" href="<?= tautan('produk') ?>?kategori=kelas">
    <span class="angka-num"><?= $kelasTerbit ?>/<?= $kelasTotal ?></span>
    <span class="angka-lbl">Kelas dibuka</span>
  </a>
  <a class="angka" href="<?= tautan('gadget') ?>">
    <span class="angka-num"><?= $asetTotal ?></span>
    <span class="angka-lbl">Gadget tercatat</span>
  </a>
</div>

<?php if ($jual !== null): ?>
  <h2 class="sub-judul" style="margin-top:0">Penjualan &amp; affiliate</h2>
  <div class="angka-kisi">
    <a class="angka" href="<?= tautan('transaksi') ?>?bayar=lunas&amp;periode=bulan">
      <span class="angka-num angka-rp"><?= e(rupiah($jual['rp'])) ?></span>
      <span class="angka-lbl">Lunas bulan ini · <?= $jual['n'] ?> transaksi</span>
    </a>
    <a class="angka" href="<?= tautan('transaksi') ?>">
      <span class="angka-num angka-rp"><?= e(rupiah($jual['komisi'])) ?></span>
      <span class="angka-lbl">Komisi affiliate bulan ini</span>
    </a>
    <a class="angka<?= $jual['menunggu'] > 0 ? ' angka-sorot' : '' ?>" href="<?= tautan('affiliate') ?>?status=menunggu">
      <span class="angka-num"><?= $jual['menunggu'] ?></span>
      <span class="angka-lbl">Pendaftar menunggu persetujuan</span>
    </a>
    <a class="angka<?= $jual['tarik_n'] > 0 ? ' angka-sorot' : '' ?>" href="<?= tautan('penarikan') ?>">
      <span class="angka-num"><?= $jual['tarik_n'] ?></span>
      <span class="angka-lbl">Withdraw perlu ditransfer<?= $jual['tarik_n'] > 0 ? ' · ' . e(rupiah($jual['tarik_rp'])) : '' ?></span>
    </a>
  </div>
<?php endif; ?>

<div class="dua-kolom">

  <section class="kotak">
    <div class="kotak-kepala">
      <h2>Permintaan jasa terakhir</h2>
      <a class="tautan-lain" href="<?= tautan('transaksi') ?>?kategori=jasa">Semua &rarr;</a>
    </div>

    <?php if (!$orderTerakhir): ?>
      <p class="kosong">Belum ada permintaan masuk. Pesan dari form kontak dan formulir order jasa muncul di sini.</p>
    <?php else: ?>
      <ul class="daftar-ringkas">
        <?php foreach ($orderTerakhir as $o): ?>
          <li>
            <a href="<?= tautan('order/' . (int) $o['id']) ?>">
              <span class="dr-judul"><?= e($o['nama']) ?><?= $o['lembaga'] ? ' · ' . e($o['lembaga']) : '' ?></span>
              <span class="dr-sub"><?= e(mb_strimwidth($o['kebutuhan'], 0, 90, '…')) ?></span>
            </a>
            <span class="tanda tanda-<?= e($o['status']) ?>"><?= e(STATUS_ORDER[$o['status']] ?? $o['status']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="kotak">
    <div class="kotak-kepala">
      <h2>Keadaan</h2>
    </div>

    <dl class="keadaan">
      <dt>Materi kelas</dt>
      <dd><?= $materiTotal ?> materi di <?= $kelasTotal ?> kelas</dd>

      <dt>Nilai jasa berjalan</dt>
      <dd><?= rupiah($nilaiJalan) ?></dd>

      <dt>Terbit terakhir ke situs</dt>
      <dd>
        <?= $terbitPada ? e(waktuIndo($terbitPada)) : 'Belum pernah' ?>
        <?php if (!$terbitPada): ?>
          <br><span class="petunjuk">Tekan Terbitkan galeri kelas di menu Produk supaya situs memakai data panel.</span>
        <?php endif; ?>
      </dd>
    </dl>

    <?php if ($jejak): ?>
      <h3 class="sub-judul">Perubahan terakhir</h3>
      <ul class="jejak">
        <?php foreach ($jejak as $j): ?>
          <li>
            <span class="jejak-aksi"><?= e($j['aksi']) ?></span>
            <?php if ($j['objek']): ?><span class="jejak-objek"><?= e($j['objek']) ?></span><?php endif; ?>
            <span class="jejak-waktu"><?= e(waktuIndo($j['dibuat_pada'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

</div>

<?php require __DIR__ . '/inc/kaki.php'; ?>
