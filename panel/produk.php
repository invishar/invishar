<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
require __DIR__ . '/inc/produk.php';
wajibMasuk();
wajibPenjualanSiap();

$saring = $_GET['status'] ?? '';
$saring = isset(STATUS_PRODUK[$saring]) ? $saring : '';

$sql = "SELECT p.*,
               (SELECT COUNT(*) FROM transaksi t WHERE t.produk_id = p.id AND t.status = 'lunas') AS terjual,
               (SELECT COALESCE(SUM(t.jumlah), 0) FROM transaksi t WHERE t.produk_id = p.id AND t.status = 'lunas') AS omzet
          FROM produk p"
     . ($saring !== '' ? ' WHERE p.status = ?' : '')
     . ' ORDER BY FIELD(p.status, \'aktif\', \'draf\', \'arsip\'), p.urutan, p.nama';
$daftar = ambilSemua($sql, $saring !== '' ? [$saring] : []);

$hitung = [];
foreach (ambilSemua('SELECT status, COUNT(*) AS jml FROM produk GROUP BY status') as $b) {
    $hitung[$b['status']] = (int) $b['jml'];
}

$judul = 'Produk';
$menu  = 'produk';
$aksiKepala = '<a class="tbl tbl-utama" href="' . e(tautan('produk-edit')) . '">+ Tambah produk</a>';
require __DIR__ . '/inc/kepala.php';
?>

<p class="pengantar">
  Semua yang dijual atau dipromosikan affiliate ada di sini. Setiap produk berstatus <strong>Tayang</strong> otomatis punya
  landing page di <code><?= e(preg_replace('#^https?://#', '', urlSitus())) ?>/p/nama-produk</code>. Perubahan langsung tayang
  begitu disimpan, tanpa langkah terbitkan terpisah.
  Untuk mengatur komisi semua produk sekaligus, atau menjual kelas, buka
  <a href="<?= tautan('produk-affiliate') ?>">Produk affiliate</a>.
</p>

<?php if (array_sum($hitung) > 0): ?>
  <div class="saring">
    <a class="cip<?= $saring === '' ? ' is-on' : '' ?>" href="<?= tautan('produk') ?>">Semua <span><?= array_sum($hitung) ?></span></a>
    <?php foreach (STATUS_PRODUK as $kunci => $label): ?>
      <a class="cip<?= $saring === $kunci ? ' is-on' : '' ?>" href="<?= tautan('produk') ?>?status=<?= e($kunci) ?>">
        <?= e($label) ?> <span><?= $hitung[$kunci] ?? 0 ?></span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (!$daftar && array_sum($hitung) === 0): ?>
  <div class="kosong kosong-besar">
    <h3>Belum ada produk</h3>
    <p>Mulai dengan produk yang paling sering ditanyakan orang, misalnya kelas atau Ponpes Manager. Isi harganya,
       nyalakan affiliate kalau ingin dipromosikan, lalu simpan. Landing page-nya langsung jadi.</p>
    <a class="tbl tbl-utama" href="<?= tautan('produk-edit') ?>">+ Tambah produk pertama</a>
  </div>
<?php elseif (!$daftar): ?>
  <p class="kosong">Tidak ada produk berstatus ini.</p>
<?php else: ?>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr>
          <th>Produk</th>
          <th>Harga</th>
          <th>Komisi affiliate</th>
          <th class="kanan">Terjual</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($daftar as $p): ?>
          <tr onclick="location='<?= tautan('produk/' . (int) $p['id']) ?>'">
            <td>
              <a class="tabel-utama" href="<?= tautan('produk/' . (int) $p['id']) ?>"><?= e($p['nama']) ?></a>
              <span class="tabel-sub"><?= e(JENIS_PRODUK[$p['jenis']]['label'] ?? $p['jenis']) ?></span>
            </td>
            <td class="nowrap"><?= e(teksHargaProduk($p) ?: '—') ?></td>
            <td>
              <?php if ((int) $p['affiliate_aktif']): ?>
                <span class="tebal"><?= e(teksFee($p['fee_jenis'], $p['fee_nilai'])) ?></span>
                <span class="tabel-sub"><?= $p['jenis'] === 'langganan' ? 'per bulan, hingga ' . bulanBerulang($p) . ' bulan' : 'per penjualan' ?></span>
              <?php else: ?>
                <span class="teks-kecil">Tidak dibuka</span>
              <?php endif; ?>
            </td>
            <td class="kanan">
              <?= (int) $p['terjual'] ?>
              <?php if ((int) $p['omzet'] > 0): ?><span class="tabel-sub"><?= e(rupiah((int) $p['omzet'])) ?></span><?php endif; ?>
            </td>
            <td><span class="tanda tanda-<?= $p['status'] === 'aktif' ? 'tayang' : e($p['status']) ?>"><?= e(STATUS_PRODUK[$p['status']] ?? $p['status']) ?></span></td>
            <td class="kanan">
              <?php if ($p['status'] === 'aktif'): ?>
                <a class="tautan-lain" href="<?= e(urlSitus() . '/p/' . $p['slug']) ?>" target="_blank" rel="noopener">Lihat halaman ↗</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/inc/kaki.php'; ?>
