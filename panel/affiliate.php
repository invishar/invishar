<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
require_once __DIR__ . '/inc/affiliate.php';
wajibMasuk();
wajibPenjualanSiap();

$saring = $_GET['status'] ?? '';
$saring = isset(STATUS_AFFILIATE[$saring]) ? $saring : '';

$hitung = [];
foreach (ambilSemua('SELECT status, COUNT(*) AS jml FROM affiliate GROUP BY status') as $b) {
    $hitung[$b['status']] = (int) $b['jml'];
}

$sql = "SELECT a.*,
          (SELECT COUNT(*) FROM affiliate_klik k WHERE k.affiliate_id = a.id AND k.dibuat_pada > (NOW() - INTERVAL 30 DAY)) AS klik30,
          (SELECT COUNT(*) FROM transaksi t WHERE t.affiliate_id = a.id AND t.status = 'lunas' AND t.beli_sendiri = 0) AS terjual,
          (SELECT COALESCE(SUM(jumlah), 0) FROM komisi k WHERE k.affiliate_id = a.id AND k.status = 'berlaku' AND k.penarikan_id IS NULL AND k.cair_pada <= NOW()) AS siap,
          (SELECT COALESCE(SUM(jumlah), 0) FROM komisi k WHERE k.affiliate_id = a.id AND k.status = 'berlaku' AND k.penarikan_id IS NULL AND k.cair_pada > NOW()) AS tertahan
        FROM affiliate a"
     . ($saring !== '' ? ' WHERE a.status = ?' : '')
     . " ORDER BY FIELD(a.status, 'menunggu', 'aktif', 'dibekukan', 'ditolak'), a.dibuat_pada DESC";
$daftar = ambilSemua($sql, $saring !== '' ? [$saring] : []);

$judul = 'Affiliator';
$menu  = 'affiliate';
$aksiKepala = '<a class="tbl" href="' . e(urlSitus() . '/mitra/daftar') . '" target="_blank" rel="noopener">Halaman daftar mitra ↗</a>';
require __DIR__ . '/inc/kepala.php';
?>

<?php if (($hitung['menunggu'] ?? 0) > 0 && $saring !== 'menunggu'): ?>
  <div class="pita pita-peringatan">
    <span><strong><?= (int) $hitung['menunggu'] ?> pendaftaran</strong> menunggu persetujuan Anda.</span>
    <a class="tbl tbl-kecil tbl-utama" href="<?= tautan('affiliate') ?>?status=menunggu">Tinjau sekarang</a>
  </div>
<?php endif; ?>

<p class="pengantar">
  Orang yang mendaftar di <a href="<?= e(urlSitus() . '/mitra/daftar') ?>" target="_blank" rel="noopener"><?= e(preg_replace('#^https?://#', '', urlSitus())) ?>/mitra/daftar</a>
  baru bisa masuk dan mendapat kode setelah Anda setujui. Buka salah satu untuk menyetujui, membekukan, atau mencairkan komisinya.
</p>

<?php if (array_sum($hitung) > 0): ?>
  <div class="saring">
    <a class="cip<?= $saring === '' ? ' is-on' : '' ?>" href="<?= tautan('affiliate') ?>">Semua <span><?= array_sum($hitung) ?></span></a>
    <?php foreach (STATUS_AFFILIATE as $kunci => $label): ?>
      <a class="cip<?= $saring === $kunci ? ' is-on' : '' ?>" href="<?= tautan('affiliate') ?>?status=<?= e($kunci) ?>"><?= e($kunci === 'menunggu' ? 'Menunggu' : $label) ?> <span><?= $hitung[$kunci] ?? 0 ?></span></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (!$daftar && array_sum($hitung) === 0): ?>
  <div class="kosong kosong-besar">
    <h3>Belum ada yang mendaftar</h3>
    <p>Bagikan halaman pendaftaran mitra ke orang-orang yang ingin ikut memasarkan produk Invishar.
       Pastikan juga ada produk yang sudah dibuka untuk affiliate di menu Produk.</p>
    <div class="salin-baris" style="max-width:460px;margin:0 auto">
      <code><?= e(urlSitus() . '/mitra/daftar') ?></code>
      <button class="tbl-salin" type="button" data-salin="<?= e(urlSitus() . '/mitra/daftar') ?>">Salin</button>
    </div>
  </div>
<?php elseif (!$daftar): ?>
  <p class="kosong">Tidak ada affiliator berstatus ini.</p>
<?php else: ?>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr>
          <th>Affiliator</th>
          <th>Kode</th>
          <th class="kanan">Klik 30 hr</th>
          <th class="kanan">Terjual</th>
          <th class="kanan">Saldo siap</th>
          <th class="kanan">Tertahan</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($daftar as $a): ?>
          <tr onclick="location='<?= tautan('affiliate/' . (int) $a['id']) ?>'">
            <td>
              <a class="tabel-utama" href="<?= tautan('affiliate/' . (int) $a['id']) ?>"><?= e($a['nama']) ?></a>
              <span class="tabel-sub"><?= e($a['surel']) ?> · daftar <?= e(tanggalIndo($a['dibuat_pada'])) ?></span>
            </td>
            <td><?= $a['kode'] ? '<span class="tabel-kode">' . e($a['kode']) . '</span>' : '<span class="teks-kecil">—</span>' ?></td>
            <td class="kanan"><?= (int) $a['klik30'] ?></td>
            <td class="kanan"><?= (int) $a['terjual'] ?></td>
            <td class="kanan tebal<?= (int) $a['siap'] < 0 ? ' minus' : '' ?>"><?= e(rupiah((int) $a['siap'])) ?></td>
            <td class="kanan"><?= e(rupiah((int) $a['tertahan'])) ?></td>
            <td><span class="tanda tanda-<?= e($a['status']) ?>"><?= e($a['status'] === 'menunggu' ? 'Menunggu' : STATUS_AFFILIATE[$a['status']]) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/inc/kaki.php'; ?>
