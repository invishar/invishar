<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
$a = wajibMitra();
$aid = (int) $a['id'];

$semua = ambilSemua(
    'SELECT k.*, p.nama AS produk_nama, t.pembeli_nama, t.kode_order, ' . SQL_KEADAAN_KOMISI . '
       FROM komisi k
       LEFT JOIN produk p     ON p.id = k.produk_id
       LEFT JOIN transaksi t  ON t.id = k.transaksi_id
       LEFT JOIN penarikan tp ON tp.id = k.penarikan_id
      WHERE k.affiliate_id = ?
      ORDER BY k.dibuat_pada DESC, k.id DESC
      LIMIT 1000',
    [$aid]
);

$hitung = array_fill_keys(array_keys(KEADAAN_KOMISI), 0);
foreach ($semua as $i => $k) {
    $semua[$i]['keadaan'] = keadaanKomisi($k);
    $hitung[$semua[$i]['keadaan']]++;
}
$saring = (string) ($_GET['keadaan'] ?? '');
$saring = isset(KEADAAN_KOMISI[$saring]) ? $saring : '';
$daftar = $saring === '' ? $semua : array_values(array_filter($semua, function ($k) use ($saring) {
    return $k['keadaan'] === $saring;
}));
$saldo = saldoAffiliate($aid);

$judul = 'Penghasilan';
$sub = 'Total komisi yang pernah Anda peroleh: <strong>' . e(rupiah($saldo['diperoleh'])) . '</strong>.';
$menu = 'penghasilan';
require __DIR__ . '/inc/kepala.php';
?>

<?php if ($semua): ?>
  <div class="saring">
    <a class="cip<?= $saring === '' ? ' is-on' : '' ?>" href="<?= tautan('penghasilan') ?>">Semua <span><?= count($semua) ?></span></a>
    <?php foreach (KEADAAN_KOMISI as $kunci => $label): ?>
      <?php if ($hitung[$kunci] > 0 || $saring === $kunci): ?>
        <a class="cip<?= $saring === $kunci ? ' is-on' : '' ?>" href="<?= tautan('penghasilan') ?>?keadaan=<?= e($kunci) ?>"><?= e($label) ?> <span><?= $hitung[$kunci] ?></span></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (!$semua): ?>
  <div class="kosong kosong-besar">
    <h3>Belum ada penghasilan</h3>
    <p>Setiap penjualan lunas lewat link Anda akan tercatat di sini, lengkap dengan kapan komisinya bisa ditarik.</p>
    <a class="tbl tbl-utama" href="<?= tautan('tautan') ?>">Ambil link produk</a>
  </div>
<?php elseif (!$daftar): ?>
  <p class="kosong">Tidak ada komisi dengan keadaan ini.</p>
<?php else: ?>
  <div class="tabel-bungkus">
    <table class="tabel tabel-diam">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Produk</th>
          <th>Pembeli</th>
          <th class="kanan">Nilai transaksi</th>
          <th class="kanan">Komisi</th>
          <th>Keadaan</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($daftar as $k): ?>
          <tr>
            <td class="tabel-tipis"><?= e(tanggalIndo($k['dibuat_pada'])) ?></td>
            <td>
              <?php if ($k['jenis'] === 'penyesuaian'): ?>
                <span class="tebal">Penyesuaian</span>
                <span class="tabel-sub"><?= e((string) $k['catatan']) ?></span>
              <?php else: ?>
                <span class="tebal"><?= e($k['produk_nama'] ?? 'Produk') ?></span>
                <?php if ($k['periode_ke']): ?><span class="tabel-sub">Langganan bulan ke-<?= (int) $k['periode_ke'] ?></span><?php endif; ?>
              <?php endif; ?>
            </td>
            <td><?= $k['pembeli_nama'] ? e(samarkanNama($k['pembeli_nama'])) : '—' ?></td>
            <td class="kanan"><?= e(rupiah((int) $k['dasar'])) ?></td>
            <td class="kanan tebal<?= (int) $k['jumlah'] < 0 ? ' minus' : '' ?>">
              <?= e(rupiah((int) $k['jumlah'])) ?>
              <?php if ($k['fee_jenis']): ?><span class="tabel-sub"><?= e(teksFee($k['fee_jenis'], $k['fee_nilai'])) ?></span><?php endif; ?>
            </td>
            <td>
              <span class="tanda tanda-<?= e($k['keadaan']) ?>"><?= e(KEADAAN_KOMISI[$k['keadaan']]) ?></span>
              <?php if ($k['keadaan'] === 'tertahan'): ?>
                <span class="tabel-sub">Cair <?= e(tanggalIndo($k['cair_pada'])) ?></span>
              <?php elseif ($k['keadaan'] === 'siap' && $k['dipercepat_pada']): ?>
                <span class="tabel-sub">Dicairkan lebih awal oleh admin</span>
              <?php elseif ($k['keadaan'] === 'batal' && $k['catatan']): ?>
                <span class="tabel-sub"><?= e($k['catatan']) ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<section class="kotak">
  <div class="kotak-kepala"><h2>Arti setiap keadaan</h2></div>
  <div class="legenda">
    <div><span class="tanda tanda-tertahan">Tertahan</span><span>Pembayaran sudah lunas; komisi menunggu masa tahan <?= setelanAngka('affiliate.masa_tahan_hari') ?> hari.</span></div>
    <div><span class="tanda tanda-siap">Siap ditarik</span><span>Masuk saldo dan bisa diajukan penarikannya.</span></div>
    <div><span class="tanda tanda-diproses">Sedang diproses</span><span>Sudah diajukan, menunggu transfer dari admin.</span></div>
    <div><span class="tanda tanda-dicairkan">Dicairkan</span><span>Sudah ditransfer ke rekening Anda.</span></div>
    <div><span class="tanda tanda-batal">Dibatalkan</span><span>Transaksinya dibatalkan atau dananya dikembalikan ke pembeli.</span></div>
    <div><span class="tanda tanda-lembut">Penyesuaian</span><span>Potongan karena transaksi yang komisinya sudah ditarik lalu dikembalikan.</span></div>
  </div>
</section>

<?php require __DIR__ . '/inc/kaki.php'; ?>
