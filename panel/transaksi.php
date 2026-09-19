<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
require_once __DIR__ . '/inc/order.php';
wajibMasuk();
wajibPenjualanSiap();

/* =============================================================================
   Transaksi — semua pesanan dalam satu daftar: checkout dan permintaan jasa.
   Status proses (Baru → Diproses → Selesai / Dibatalkan) diubah di sini;
   status bayar berubah sendiri dari pembayaran.
   ============================================================================= */

/* ---- tombol cepat: pindah ke langkah berikutnya ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $kembali = tautan('transaksi') . (($q = (string) ($_POST['kembali'] ?? '')) !== '' && $q[0] === '?' ? $q : '');
    $tipe = masukan('tipe');
    $id = (int) masukan('id');
    $ke = masukan('ke');

    $galat = $tipe === 'jasa' ? ubahStatusOrderJasa($id, $ke, 'Dari daftar transaksi') : ubahProsesTransaksi($id, $ke);
    if ($galat) {
        pesan($galat, 'buruk');
    } else {
        $label = $tipe === 'jasa' ? (STATUS_ORDER[$ke] ?? $ke) : (STATUS_PROSES[$ke] ?? $ke);
        pesan('Status pesanan diubah menjadi ' . $label . '.');
    }
    pergi($kembali);
}

/* ---------------------------------------------------------------- saringan */
$f = [
    'q'        => trim((string) ($_GET['q'] ?? '')),
    'proses'   => (string) ($_GET['proses'] ?? ''),
    'bayar'    => (string) ($_GET['bayar'] ?? ''),
    'kategori' => (string) ($_GET['kategori'] ?? ''),
    'produk'   => (int) ($_GET['produk'] ?? 0),
    'sumber'   => (string) ($_GET['sumber'] ?? ''),
    'periode'  => (string) ($_GET['periode'] ?? ''),
];
if ($f['proses'] !== 'perlu' && !isset(STATUS_PROSES[$f['proses']])) {
    $f['proses'] = '';
}
if (!isset(STATUS_BAYAR[$f['bayar']])) {
    $f['bayar'] = '';
}
if (!isset(KATEGORI_PRODUK[$f['kategori']])) {
    $f['kategori'] = '';
}
if (!in_array($f['sumber'], ['', 'affiliate', 'langsung'], true) && !preg_match('/^a\d+$/', $f['sumber'])) {
    $f['sumber'] = '';
}
if (!in_array($f['periode'], ['', '7', '30', '90', 'bulan'], true)) {
    $f['periode'] = '';
}

$syarat = [];
$isi = [];
if ($f['q'] !== '') {
    $kata = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $f['q']) . '%';
    $atau = 'o.kode LIKE ? OR o.nama LIKE ? OR o.whatsapp LIKE ? OR o.surel LIKE ? OR o.produk_nama LIKE ? OR o.kebutuhan LIKE ?';
    array_push($isi, $kata, $kata, $kata, $kata, $kata, $kata);
    // Nomor WA ketemu apa pun formatnya (0812-…, +62 812…): bandingkan angkanya
    // saja, tanpa awalan 0 / 62.
    $angka = (string) preg_replace('/\D/', '', $f['q']);
    if (strlen($angka) >= 6 && strlen($angka) === strlen(preg_replace('/[\s+\-.()]/', '', $f['q']))) {
        $inti = (string) preg_replace('/^(62|0)/', '', $angka);
        $atau .= " OR REPLACE(REPLACE(REPLACE(REPLACE(o.whatsapp, '+', ''), '-', ''), ' ', ''), '.', '') LIKE ?";
        $isi[] = '%' . $inti . '%';
    }
    $syarat[] = '(' . $atau . ')';
}
if ($f['proses'] === 'perlu') {
    $syarat[] = SQL_PERLU_DIPROSES;
} elseif ($f['proses'] !== '') {
    $syarat[] = 'o.status_proses = ?';
    $isi[] = $f['proses'];
}
if ($f['bayar'] !== '') {
    $syarat[] = 'o.status_bayar = ?';
    $isi[] = $f['bayar'];
}
if ($f['kategori'] !== '') {
    $syarat[] = 'o.produk_kategori = ?';
    $isi[] = $f['kategori'];
}
if ($f['produk'] > 0) {
    $syarat[] = 'o.produk_id = ?';
    $isi[] = $f['produk'];
}
if ($f['sumber'] === 'affiliate') {
    $syarat[] = 'o.affiliate_id IS NOT NULL';
} elseif ($f['sumber'] === 'langsung') {
    $syarat[] = 'o.affiliate_id IS NULL';
} elseif ($f['sumber'] !== '') {
    $syarat[] = 'o.affiliate_id = ?';
    $isi[] = (int) substr($f['sumber'], 1);
}
if ($f['periode'] === 'bulan') {
    $syarat[] = "o.dibuat_pada >= DATE_FORMAT(NOW(), '%Y-%m-01')";
} elseif ($f['periode'] !== '') {
    $syarat[] = 'o.dibuat_pada >= (NOW() - INTERVAL ' . (int) $f['periode'] . ' DAY)';
}
$where = $syarat ? ' WHERE ' . implode(' AND ', $syarat) : '';

$perHalaman = 50;
$halaman = max(1, (int) ($_GET['hal'] ?? 1));
$total = (int) ambilNilai('SELECT COUNT(*) FROM ' . sqlOrderGabungan() . $where, $isi);
$halaman = min($halaman, max(1, (int) ceil($total / $perHalaman)));
$daftar = ambilSemua(
    'SELECT o.* FROM ' . sqlOrderGabungan() . $where
    . ' ORDER BY o.dibuat_pada DESC, o.id DESC LIMIT ' . $perHalaman . ' OFFSET ' . (($halaman - 1) * $perHalaman),
    $isi
);

/* ---------------------------------------------------------------- ringkasan */
$perlu = jumlahPerluDiproses();
$perluBaru = (int) ambilNilai('SELECT COUNT(*) FROM ' . sqlOrderGabungan() . " WHERE " . SQL_PERLU_DIPROSES . " AND o.status_proses = 'baru'");
$menunggu = (int) ambilNilai("SELECT COUNT(*) FROM transaksi WHERE status = 'menunggu'");
$bulanIni = ambilSatu(
    "SELECT COUNT(*) AS n, COALESCE(SUM(jumlah), 0) AS rp FROM transaksi
      WHERE status = 'lunas' AND dibayar_pada >= DATE_FORMAT(NOW(), '%Y-%m-01')"
);
$komisiBulanIni = (int) ambilNilai(
    "SELECT COALESCE(SUM(jumlah), 0) FROM komisi WHERE jenis = 'komisi' AND status = 'berlaku' AND dibuat_pada >= DATE_FORMAT(NOW(), '%Y-%m-01')"
);
$semuaOrder = (int) ambilNilai('SELECT COUNT(*) FROM ' . sqlOrderGabungan());

$daftarProduk = ambilSemua('SELECT id, nama, kategori FROM produk ORDER BY FIELD(kategori, \'produk\', \'jasa\', \'kelas\'), nama');
$daftarAff = ambilSemua("SELECT id, nama, kode FROM affiliate WHERE kode IS NOT NULL ORDER BY nama");
$adaSaringan = array_filter($f, fn ($v) => $v !== '' && $v !== 0) !== [];

/** Alamat daftar dengan saringan sekarang, diubah sebagian. */
function tautanSaring(array $f, array $ubah = []): string
{
    $q = array_filter(array_merge($f, $ubah, ['hal' => $ubah['hal'] ?? null]), fn ($v) => $v !== '' && $v !== 0 && $v !== null);
    return tautan('transaksi') . ($q ? '?' . http_build_query($q) : '');
}

$judul = 'Transaksi';
$menu  = 'transaksi';
$aksiKepala = '<a class="tbl" href="' . e(tautan('order')) . '?baru=1">+ Permintaan jasa</a> '
    . '<a class="tbl tbl-utama" href="' . e(tautan('transaksi-catat')) . '">+ Catat pembayaran</a>';
require __DIR__ . '/inc/kepala.php';
?>

<?php if (modeUji()): ?>
  <div class="pita pita-peringatan">
    <span><strong>Mode uji aktif.</strong> Checkout di situs memakai pembayaran simulasi — belum ada uang sungguhan.</span>
    <a class="tbl tbl-kecil" href="<?= tautan('pembayaran') ?>">Atur pembayaran</a>
  </div>
<?php endif; ?>

<div class="angka-kisi">
  <a class="angka<?= $perlu ? ' angka-sorot' : '' ?>" href="<?= e(tautanSaring([], ['proses' => 'perlu'])) ?>">
    <span class="angka-num"><?= $perlu ?></span>
    <span class="angka-lbl">Perlu diproses</span>
    <span class="angka-catatan"><?= $perlu ? ($perluBaru ? $perluBaru . ' baru, belum disentuh' : 'Semua sedang dikerjakan') : 'Tidak ada yang menunggu' ?></span>
  </a>
  <a class="angka" href="<?= e(tautanSaring([], ['bayar' => 'menunggu'])) ?>">
    <span class="angka-num"><?= $menunggu ?></span>
    <span class="angka-lbl">Menunggu bayar</span>
    <span class="angka-catatan">Checkout yang belum dibayar</span>
  </a>
  <a class="angka" href="<?= e(tautanSaring([], ['bayar' => 'lunas', 'periode' => 'bulan'])) ?>">
    <span class="angka-num angka-rp"><?= e(rupiah((int) $bulanIni['rp'])) ?></span>
    <span class="angka-lbl">Lunas bulan ini</span>
    <span class="angka-catatan"><?= (int) $bulanIni['n'] ?> pembayaran</span>
  </a>
  <a class="angka" href="<?= e(tautanSaring([], ['sumber' => 'affiliate', 'periode' => 'bulan'])) ?>">
    <span class="angka-num angka-rp"><?= e(rupiah($komisiBulanIni)) ?></span>
    <span class="angka-lbl">Komisi affiliate bulan ini</span>
    <span class="angka-catatan">Dari penjualan lewat mitra</span>
  </a>
</div>

<?php if ($semuaOrder === 0): ?>
  <div class="kosong kosong-besar">
    <h3>Belum ada pesanan</h3>
    <p>Pesanan tercatat otomatis saat pembeli checkout atau mengisi form permintaan di landing page produk.
       Pesanan yang masuk lewat WhatsApp bisa dicatat manual.</p>
    <a class="tbl tbl-utama" href="<?= tautan('order') ?>?baru=1">+ Catat permintaan jasa</a>
  </div>
<?php else: ?>

  <!-- ============ Saringan ============ -->
  <div class="saring">
    <a class="cip<?= !$adaSaringan ? ' is-on' : '' ?>" href="<?= tautan('transaksi') ?>">Semua <span><?= $semuaOrder ?></span></a>
    <a class="cip<?= $f['proses'] === 'perlu' ? ' is-on' : '' ?>" href="<?= e(tautanSaring($f, ['proses' => 'perlu', 'hal' => null])) ?>">Perlu diproses <span><?= $perlu ?></span></a>
    <?php foreach (STATUS_PROSES as $kunci => $label): ?>
      <a class="cip<?= $f['proses'] === $kunci ? ' is-on' : '' ?>" href="<?= e(tautanSaring($f, ['proses' => $kunci, 'hal' => null])) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>

  <form class="saring-form" method="get" action="<?= tautan('transaksi') ?>" role="search">
    <input type="hidden" name="proses" value="<?= e($f['proses']) ?>">
    <div class="saring-cari">
      <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Cari nama, WhatsApp, surel, atau kode pesanan" aria-label="Cari pesanan">
    </div>
    <select name="bayar" aria-label="Status bayar">
      <option value="">Semua pembayaran</option>
      <?php foreach (STATUS_BAYAR as $kunci => $label): ?>
        <option value="<?= e($kunci) ?>"<?= $f['bayar'] === $kunci ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="produk" aria-label="Produk">
      <option value="">Semua produk</option>
      <?php foreach (KATEGORI_PRODUK as $kat => $katLabel): ?>
        <?php $grupIni = array_filter($daftarProduk, fn ($p) => $p['kategori'] === $kat); ?>
        <?php if ($grupIni): ?>
          <optgroup label="<?= e($katLabel) ?>">
            <?php foreach ($grupIni as $p): ?>
              <option value="<?= (int) $p['id'] ?>"<?= $f['produk'] === (int) $p['id'] ? ' selected' : '' ?>><?= e($p['nama']) ?></option>
            <?php endforeach; ?>
          </optgroup>
        <?php endif; ?>
      <?php endforeach; ?>
    </select>
    <select name="kategori" aria-label="Kategori">
      <option value="">Semua kategori</option>
      <?php foreach (KATEGORI_PRODUK as $kunci => $label): ?>
        <option value="<?= e($kunci) ?>"<?= $f['kategori'] === $kunci ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="sumber" aria-label="Sumber pesanan">
      <option value="">Semua sumber</option>
      <option value="affiliate"<?= $f['sumber'] === 'affiliate' ? ' selected' : '' ?>>Lewat affiliate</option>
      <option value="langsung"<?= $f['sumber'] === 'langsung' ? ' selected' : '' ?>>Langsung (bukan affiliate)</option>
      <?php if ($daftarAff): ?>
        <optgroup label="Affiliator">
          <?php foreach ($daftarAff as $a): ?>
            <option value="a<?= (int) $a['id'] ?>"<?= $f['sumber'] === 'a' . $a['id'] ? ' selected' : '' ?>><?= e($a['nama']) ?> · <?= e((string) $a['kode']) ?></option>
          <?php endforeach; ?>
        </optgroup>
      <?php endif; ?>
    </select>
    <select name="periode" aria-label="Periode">
      <option value="">Semua waktu</option>
      <option value="7"<?= $f['periode'] === '7' ? ' selected' : '' ?>>7 hari terakhir</option>
      <option value="30"<?= $f['periode'] === '30' ? ' selected' : '' ?>>30 hari terakhir</option>
      <option value="bulan"<?= $f['periode'] === 'bulan' ? ' selected' : '' ?>>Bulan ini</option>
      <option value="90"<?= $f['periode'] === '90' ? ' selected' : '' ?>>90 hari terakhir</option>
    </select>
    <button class="tbl tbl-utama" type="submit">Terapkan</button>
    <?php if ($adaSaringan): ?><a class="tautan-lain" href="<?= tautan('transaksi') ?>">Hapus saringan</a><?php endif; ?>
  </form>

  <p class="saring-hasil"><?= $total ?> pesanan<?= $adaSaringan ? ' cocok dengan saringan' : '' ?><?= $total > $perHalaman ? ' · halaman ' . $halaman . ' dari ' . (int) ceil($total / $perHalaman) : '' ?></p>

  <?php if (!$daftar): ?>
    <p class="kosong">Tidak ada pesanan yang cocok. <a href="<?= tautan('transaksi') ?>">Tampilkan semua</a></p>
  <?php else: ?>
    <div class="tabel-bungkus tabel-beku">
      <table class="tabel">
        <thead>
          <tr>
            <th>Pesanan</th>
            <th>Pembeli</th>
            <th>Produk</th>
            <th class="kanan">Jumlah</th>
            <th>Sumber</th>
            <th>Bayar</th>
            <th>Proses</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($daftar as $o): ?>
            <?php
              $url = tautanOrder($o['tipe'], (int) $o['id']);
              $jasa = $o['tipe'] === 'jasa';
              // Tombol langkah berikut, hanya kalau memang ada langkah yang masuk akal.
              $lanjut = null;
              if ($jasa && isset(LANGKAH_ORDER_JASA[$o['status_jasa']])) {
                  $ke = LANGKAH_ORDER_JASA[$o['status_jasa']];
                  $lanjut = [$ke, STATUS_ORDER[$ke]];
              } elseif (!$jasa && $o['status_bayar'] === 'lunas' && $o['status_proses'] === 'baru') {
                  $lanjut = ['diproses', 'Proses'];
              } elseif (!$jasa && $o['status_bayar'] === 'lunas' && $o['status_proses'] === 'diproses') {
                  $lanjut = ['selesai', 'Selesai'];
              }
              $perluIni = $o['status_proses'] !== 'selesai' && $o['status_proses'] !== 'batal' && ($jasa || $o['status_bayar'] === 'lunas');
            ?>
            <tr onclick="location='<?= e($url) ?>'"<?= $perluIni ? ' class="baris-perlu"' : '' ?>>
              <td>
                <a class="tabel-utama tabel-kode" href="<?= e($url) ?>"><?= e($o['kode']) ?></a>
                <span class="tabel-sub"><?= e(waktuIndo($o['dibuat_pada'])) ?> · <?= $jasa ? 'permintaan' : e(['manual' => 'manual', 'uji' => 'checkout uji', 'midtrans' => 'checkout'][$o['gerbang']] ?? $o['gerbang']) ?></span>
              </td>
              <td><?= e($o['nama']) ?><?php if ($o['whatsapp']): ?><span class="tabel-sub"><?= e($o['whatsapp']) ?></span><?php endif; ?></td>
              <td>
                <?= e($o['produk_nama']) ?>
                <span class="tabel-sub"><?= e(KATEGORI_PRODUK[$o['produk_kategori']] ?? '') ?><?= $o['produk_jenis'] === 'langganan' && !$jasa ? ' · bulan ke-' . (int) $o['periode_ke'] : '' ?></span>
              </td>
              <td class="kanan tebal"><?= $o['jumlah'] !== null ? e(rupiah((int) $o['jumlah'])) : '<span class="teks-kecil">—</span>' ?></td>
              <td>
                <?php if ($o['affiliate_kode']): ?>
                  <span class="tabel-kode"><?= e($o['affiliate_kode']) ?></span>
                  <span class="tabel-sub"><?= (int) $o['beli_sendiri'] ? 'beli sendiri · tanpa komisi' : e((string) $o['affiliate_nama']) ?></span>
                <?php else: ?>
                  <span class="teks-kecil">Langsung</span>
                <?php endif; ?>
              </td>
              <td><span class="tanda tanda-<?= e($o['status_bayar']) ?>"><?= e(STATUS_BAYAR[$o['status_bayar']] ?? $o['status_bayar']) ?></span></td>
              <td>
                <span class="tanda tanda-proses-<?= e($o['status_proses']) ?>"><?= e(STATUS_PROSES[$o['status_proses']]) ?></span>
                <?php if ($jasa && $o['status_jasa'] !== $o['status_proses']): ?><span class="tabel-sub"><?= e(STATUS_ORDER[$o['status_jasa']] ?? '') ?></span><?php endif; ?>
              </td>
              <td class="kanan">
                <?php if ($lanjut): ?>
                  <form method="post" class="sebaris">
                    <?= csrfInput() ?>
                    <input type="hidden" name="tipe" value="<?= e($o['tipe']) ?>">
                    <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                    <input type="hidden" name="ke" value="<?= e($lanjut[0]) ?>">
                    <input type="hidden" name="kembali" value="<?= e(($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '') ?>">
                    <button class="tbl tbl-kecil tbl-lanjut" type="submit" title="Ubah status menjadi <?= e($lanjut[1]) ?>">→ <?= e($lanjut[1]) ?></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($total > $perHalaman): ?>
      <nav class="halaman-nav" aria-label="Halaman">
        <?php if ($halaman > 1): ?><a class="tbl tbl-kecil" href="<?= e(tautanSaring($f, ['hal' => $halaman - 1])) ?>">&larr; Sebelumnya</a><?php endif; ?>
        <span class="teks-kecil">Halaman <?= $halaman ?> dari <?= (int) ceil($total / $perHalaman) ?></span>
        <?php if ($halaman * $perHalaman < $total): ?><a class="tbl tbl-kecil" href="<?= e(tautanSaring($f, ['hal' => $halaman + 1])) ?>">Berikutnya &rarr;</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/inc/kaki.php'; ?>
