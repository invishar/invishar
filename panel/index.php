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

$orderTerakhir = ambilSemua('SELECT * FROM order_jasa ORDER BY dibuat_pada DESC LIMIT 6');
$jejak         = ambilSemua('SELECT * FROM log_aktivitas ORDER BY dibuat_pada DESC LIMIT 8');

require __DIR__ . '/inc/kepala.php';
?>

<div class="angka-kisi">
  <a class="angka" href="<?= tautan('order') ?>?status=baru">
    <span class="angka-num"><?= $orderBaru ?></span>
    <span class="angka-lbl">Order baru</span>
  </a>
  <a class="angka" href="<?= tautan('order') ?>">
    <span class="angka-num"><?= $orderJalan ?></span>
    <span class="angka-lbl">Sedang berjalan</span>
  </a>
  <a class="angka" href="<?= tautan('kelas') ?>">
    <span class="angka-num"><?= $kelasTerbit ?>/<?= $kelasTotal ?></span>
    <span class="angka-lbl">Kelas dibuka</span>
  </a>
  <a class="angka" href="<?= tautan('inventaris') ?>">
    <span class="angka-num"><?= $asetTotal ?></span>
    <span class="angka-lbl">Aset tercatat</span>
  </a>
</div>

<div class="dua-kolom">

  <section class="kotak">
    <div class="kotak-kepala">
      <h2>Order terakhir</h2>
      <a class="tautan-lain" href="<?= tautan('order') ?>">Semua &rarr;</a>
    </div>

    <?php if (!$orderTerakhir): ?>
      <p class="kosong">Belum ada order masuk. Begitu form kontak di invishar.com disambungkan, pesan yang masuk muncul di sini.</p>
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

      <dt>Nilai order berjalan</dt>
      <dd><?= rupiah($nilaiJalan) ?></dd>

      <dt>Terbit terakhir ke situs</dt>
      <dd>
        <?= $terbitPada ? e(waktuIndo($terbitPada)) : 'Belum pernah' ?>
        <?php if (!$terbitPada): ?>
          <br><span class="petunjuk">Tekan Terbitkan di halaman Kelas supaya situs memakai data panel.</span>
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
