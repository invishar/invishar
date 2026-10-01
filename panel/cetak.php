<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
require_once __DIR__ . '/inc/order.php';
wajibMasuk();

/* =============================================================================
   Tagihan & kuitansi — satu berkas, dua dokumen.

     /panel/cetak?order=12   tagihan sebuah order jasa (butuh Nilai proyek)
     /panel/cetak?bayar=34   kuitansi sebuah pembayaran yang sudah lunas

   Berdiri sendiri: tanpa kepala.php/kaki.php dan tanpa panel.css. Dengan begitu
   ukuran halaman bisa dikunci ke A4 tanpa menyentuh gaya panel sama sekali, dan
   PDF-nya dibuat lewat dialog cetak peramban — tanpa pustaka PDF.

   Dua hal yang perlu diingat saat memakainya:
     * Dokumen dirakit ulang dari data hidup setiap kali dibuka. Mengubah
       identitas usaha atau label pembayaran akan mengubah dokumen yang sudah
       dikirim, jadi simpan setiap PDF yang sudah diserahkan ke klien.
     * Matikan "Headers and footers" di dialog cetak, kalau tidak alamat
       /panel/cetak?order=12 ikut tercetak di kertas klien.

   Penolakan tidak memakai pesan() + pergi(): halaman ini dibuka di tab baru,
   pesan kilatnya akan muncul di tempat yang tidak dilihat siapa pun. Jadi
   alasannya ditampilkan di halaman ini sendiri, lengkap dengan tombol kembali.
   ============================================================================= */

header('Cache-Control: no-store');

$mode    = 'tolak';                 // tolak | tagihan | kuitansi
$galat   = '';
$kembali = ['url' => tautan('transaksi'), 'label' => 'Daftar transaksi'];

$order = $bayar = $uang = $t = null;
$nomor = $produkNama = '';

$idOrder = (int) ($_GET['order'] ?? 0);
$idBayar = (int) ($_GET['bayar'] ?? 0);

/* Identitas usaha untuk kop surat. Nama dan penanda tangan selalu punya isi
   supaya dokumen tidak pernah tercetak tanpa pengirim. */
$usaha = [
    'nama'    => setelan('usaha.nama') !== '' ? setelan('usaha.nama') : 'Invishar',
    'alamat'  => setelan('usaha.alamat'),
    'telepon' => setelan('usaha.telepon'),
    'surel'   => setelan('usaha.surel'),
    'logo'    => setelan('usaha.logo_url'),
    'bank'    => setelan('usaha.bank'),
    'catatan' => setelan('usaha.catatan_invoice'),
];
$usaha['ttd'] = setelan('usaha.penanda_tangan') !== '' ? setelan('usaha.penanda_tangan') : $usaha['nama'];

$hariIni = date('Y-m-d');
$tempo   = setelanAngka('usaha.tempo_hari');

if (!penjualanSiap()) {
    $galat   = 'Tagihan dan kuitansi memakai tabel pembayaran yang belum terpasang di basis data. Jalankan dulu pembaruannya.';
    $kembali = ['url' => tautan('pembaruan'), 'label' => 'Jalankan pembaruan'];

} elseif ($idBayar > 0) {
    $t = ambilSatu('SELECT * FROM transaksi WHERE id = ?', [$idBayar]);
    if ($t === null) {
        $galat = 'Pembayaran #' . $idBayar . ' tidak ditemukan.';
    } elseif ($t['status'] !== 'lunas') {
        $galat   = 'Kuitansi hanya untuk pembayaran yang sudah lunas. Pembayaran ini masih "'
            . (STATUS_TRANSAKSI[$t['status']] ?? $t['status']) . '". Tandai lunas dulu, baru cetak kuitansinya.';
        $kembali = ['url' => tautan('transaksi/' . $idBayar), 'label' => 'Buka pembayaran'];
    } else {
        $mode       = 'kuitansi';
        $nomor      = (string) $t['kode_order'];
        $produkNama = (string) (ambilNilai('SELECT nama FROM produk WHERE id = ?', [(int) $t['produk_id']]) ?: 'Jasa');
        $order      = !empty($t['order_jasa_id'])
            ? ambilSatu('SELECT * FROM order_jasa WHERE id = ?', [(int) $t['order_jasa_id']])
            : null;
        // Nilai/dibayar/sisa hanya masuk akal kalau kuitansinya milik order jasa.
        $uang    = $order ? uangOrderJasa((int) $order['id'], $order['nilai'] === null ? null : (int) $order['nilai']) : null;
        $kembali = ['url' => tautan('transaksi/' . $idBayar), 'label' => 'Buka pembayaran'];
    }

} elseif ($idOrder > 0) {
    $order = ambilSatu('SELECT * FROM order_jasa WHERE id = ?', [$idOrder]);
    if ($order === null) {
        $galat = 'Order #' . $idOrder . ' tidak ditemukan. Mungkin sudah dihapus.';
    } elseif ($order['nilai'] === null || (int) $order['nilai'] <= 0) {
        $galat   = 'Order ini belum punya Nilai proyek, jadi belum ada angka yang bisa ditagih. Isi Nilai proyek di halaman order, lalu cetak tagihannya.';
        $kembali = ['url' => tautan('order/' . $idOrder), 'label' => 'Buka order'];
    } else {
        $mode  = 'tagihan';
        $nomor = sprintf('JASA-%04d', (int) $order['id']);
        $uang  = uangOrderJasa((int) $order['id'], (int) $order['nilai']);
        $bayar = ambilSemua(
            "SELECT kode_order, jumlah, metode, catatan, gerbang, dibayar_pada, dibuat_pada
               FROM transaksi
              WHERE order_jasa_id = ? AND status = 'lunas'
              ORDER BY COALESCE(dibayar_pada, dibuat_pada), id",
            [$idOrder]
        );
        $produkNama = 'Jasa pembuatan aplikasi';
        if (!empty($order['produk_id'])) {
            $produkNama = (string) (ambilNilai('SELECT nama FROM produk WHERE id = ?', [(int) $order['produk_id']]) ?: $produkNama);
        }
        $kembali = ['url' => tautan('order/' . $idOrder), 'label' => 'Buka order'];
    }

} else {
    $galat = 'Alamatnya kurang lengkap. Buka dokumen ini dari tombol "Cetak tagihan" di halaman order, atau "Cetak kuitansi" di halaman pembayaran.';
}

/** Cara bayar dalam bahasa manusia: gerbang yang dipakai + metode yang dicatat admin. */
function caraBayar(?string $gerbang, ?string $metode): string
{
    $nama = ['uji' => 'Simulasi (mode uji)', 'duitku' => 'Duitku', 'manual' => 'Transfer / tunai'][$gerbang ?? ''] ?? (string) $gerbang;
    return $metode !== null && $metode !== '' ? $nama . ' · ' . $metode : $nama;
}

$judulDok = ['tagihan' => 'Tagihan', 'kuitansi' => 'Kuitansi', 'tolak' => 'Tidak bisa dicetak'][$mode];

/* Cap LUNAS hanya pada tagihan yang memang sudah tertutup — bukan pada order
   yang nilainya belum dibayar sepeser pun. */
$lunasKah = $mode === 'tagihan' && $uang['sisa'] !== null && $uang['sisa'] <= 0 && $uang['dibayar'] > 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judulDok . ($nomor !== '' ? ' ' . $nomor : '')) ?></title>
<style>
/* Ukuran kertas dikunci, dan lebar di layar dibuat sama dengan A4 supaya apa
   yang terlihat di layar persis sama dengan yang keluar dari printer. */
@page { size: A4; margin: 14mm 16mm; }

* { box-sizing: border-box; }
html { background: #e9eaec; }
body {
  margin: 0; padding: 22px 12px;
  font: 11pt/1.55 "Helvetica Neue", Arial, "Segoe UI", sans-serif;
  color: #111;
}
.lembar {
  width: 210mm; max-width: 100%; margin: 0 auto; padding: 14mm 16mm;
  background: #fff; box-shadow: 0 2px 14px rgba(0, 0, 0, .18);
}

/* Hanya garis dan huruf tebal — tanpa blok warna. Chrome mematikan "Background
   graphics" secara bawaan, jadi dokumen yang mengandalkan latar berwarna akan
   tercetak putih dan terlihat rusak. */
h1 { margin: 0; font-size: 15pt; font-weight: 700; }
p { margin: 0 0 6px; }
.garis { border: 0; border-top: 1.5px solid #111; margin: 14px 0; }
.garis-halus { border: 0; border-top: 1px solid #bbb; margin: 12px 0; }
.kecil { font-size: 9pt; color: #555; }
.label { font-size: 8.5pt; letter-spacing: .08em; text-transform: uppercase; color: #555; }

.kop { display: flex; justify-content: space-between; align-items: flex-start; gap: 16mm; }
.kop-logo { height: 13mm; width: auto; margin-bottom: 5px; display: block; }
.kop-nama { font-size: 15pt; line-height: 1.2; }
.kop-kanan { text-align: right; flex: 0 0 64mm; }
.kop-judul { font-size: 17pt; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
.kop-meta { margin-top: 6px; font-size: 9.5pt; }
.kop-meta span { color: #555; }

.cap { display: inline-block; margin-top: 8px; padding: 3px 12px; border: 2px solid #111;
       font-size: 12pt; font-weight: 700; letter-spacing: .18em; }

.pihak { display: flex; gap: 10mm; }
.pihak > div { flex: 1; }

table { width: 100%; border-collapse: collapse; }
th { text-align: left; font-size: 8.5pt; letter-spacing: .08em; text-transform: uppercase;
     color: #555; font-weight: 400; padding: 0 0 5px; border-bottom: 1.5px solid #111; }
td { padding: 8px 0; vertical-align: top; border-bottom: 1px solid #ddd; }
.kanan { text-align: right; white-space: nowrap; }
.sunyi td { border: 0; padding: 3px 0; }

.hitung { margin-left: auto; width: 80mm; }
.hitung td { border: 0; padding: 3px 0; }
.hitung .pisah td { border-top: 1.5px solid #111; padding-top: 7px; font-weight: 700; font-size: 12pt; }

.nominal-besar { font-size: 16pt; font-weight: 700; }
.kotak-garis { border: 1px solid #111; padding: 8px 10px; }

.ttd { margin-top: 16mm; display: flex; justify-content: flex-end; }
.ttd-isi { width: 70mm; text-align: center; }
.ttd-ruang { height: 20mm; }
.ttd-nama { border-top: 1px solid #111; padding-top: 5px; font-weight: 700; }

/* Bilah tombol: hanya untuk di layar, disembunyikan saat dicetak. */
.bilah { width: 210mm; max-width: 100%; margin: 0 auto 14px; display: flex; flex-wrap: wrap;
         align-items: center; gap: 10px; }
.bilah .tbl { font: inherit; font-size: 10pt; padding: 7px 14px; border-radius: 7px;
              border: 1px solid #111; background: #111; color: #fff; cursor: pointer; text-decoration: none; }
.bilah .tbl-lain { background: #fff; color: #111; }
.bilah .catatan { font-size: 9pt; color: #555; }

@media print {
  html, body { background: #fff; }
  body { padding: 0; }
  .lembar { width: auto; margin: 0; padding: 0; box-shadow: none; }
  .bilah { display: none; }
}
@media (max-width: 820px) {
  body { padding: 12px 8px; }
  .lembar { padding: 8mm; }
  .kop, .pihak { flex-direction: column; gap: 10px; }
  .kop-kanan { text-align: left; flex: 1 1 auto; }
  .hitung { width: 100%; }
}
</style>
</head>
<body>

<div class="bilah">
  <?php if ($mode !== 'tolak'): ?>
    <button class="tbl" type="button" onclick="window.print()">Cetak / simpan PDF</button>
  <?php endif; ?>
  <a class="tbl tbl-lain" href="<?= e($kembali['url']) ?>"><?= e($kembali['label']) ?></a>
  <?php if ($mode !== 'tolak'): ?>
    <span class="catatan">Di dialog cetak: pilih A4, lalu matikan <b>Headers and footers</b>.</span>
  <?php endif; ?>
</div>

<div class="lembar">

<?php if ($mode === 'tolak'): ?>

  <h1>Dokumen ini belum bisa dicetak</h1>
  <hr class="garis">
  <p><?= e($galat) ?></p>
  <p class="kecil">Beresi dulu dari halaman asalnya, lalu cetak lagi.</p>

<?php else: ?>

  <div class="kop">
    <div>
      <?php if ($usaha['logo'] !== ''): ?>
        <img class="kop-logo" src="<?= e($usaha['logo']) ?>" alt="">
      <?php endif; ?>
      <div class="kop-nama"><b><?= e($usaha['nama']) ?></b></div>
      <?php if ($usaha['alamat'] !== ''): ?>
        <p class="kecil" style="margin-top:4px"><?= nl2br(e($usaha['alamat'])) ?></p>
      <?php endif; ?>
      <?php if ($usaha['telepon'] !== '' || $usaha['surel'] !== ''): ?>
        <p class="kecil"><?= e(implode(' · ', array_filter([$usaha['telepon'], $usaha['surel']]))) ?></p>
      <?php endif; ?>
    </div>
    <div class="kop-kanan">
      <div class="kop-judul"><?= e($judulDok) ?></div>
      <div class="kop-meta">
        <div><span>Nomor</span> <b><?= e($nomor) ?></b></div>
        <?php if ($mode === 'tagihan'): ?>
          <div><span>Tanggal</span> <?= e(tanggalIndo($hariIni)) ?></div>
          <?php if ($tempo > 0): ?>
            <div><span>Jatuh tempo</span> <?= e(tanggalIndo(date('Y-m-d', strtotime('+' . $tempo . ' days')))) ?></div>
          <?php endif; ?>
        <?php else: ?>
          <div><span>Tanggal</span> <?= e(tanggalIndo($t['dibayar_pada'] ?: $t['dibuat_pada'])) ?></div>
        <?php endif; ?>
      </div>
      <?php if ($lunasKah): ?><div class="cap">LUNAS</div><?php endif; ?>
    </div>
  </div>

  <hr class="garis">

<?php endif; ?>

<?php if ($mode === 'tagihan'): ?>

  <div class="pihak">
    <div>
      <div class="label">Ditagihkan kepada</div>
      <p><b><?= e(($order['lembaga'] ?? '') !== '' ? $order['lembaga'] : $order['nama']) ?></b></p>
      <?php if (($order['lembaga'] ?? '') !== ''): ?>
        <p class="kecil">u.p. <?= e($order['nama']) ?></p>
      <?php endif; ?>
      <?php $kontak = implode(' · ', array_filter([(string) $order['whatsapp'], (string) $order['surel']])); ?>
      <?php if ($kontak !== ''): ?><p class="kecil"><?= e($kontak) ?></p><?php endif; ?>
    </div>
    <div>
      <div class="label">Permintaan masuk</div>
      <p><?= e(tanggalIndo($order['dibuat_pada'])) ?></p>
      <div class="label" style="margin-top:8px">Status pekerjaan</div>
      <p><?= e(STATUS_ORDER[$order['status']] ?? $order['status']) ?></p>
    </div>
  </div>

  <hr class="garis-halus">

  <table>
    <thead><tr><th>Keterangan</th><th class="kanan" style="width:40mm">Jumlah</th></tr></thead>
    <tbody>
      <tr>
        <td>
          <b><?= e($produkNama) ?></b>
          <?php $ringkas = trim((string) $order['kebutuhan']); ?>
          <?php if ($ringkas !== ''): ?>
            <div class="kecil" style="margin-top:4px"><?= nl2br(e(mb_strimwidth($ringkas, 0, 600, '…'))) ?></div>
          <?php endif; ?>
        </td>
        <td class="kanan"><?= e(rupiah($uang['nilai'])) ?></td>
      </tr>
    </tbody>
  </table>

  <table class="hitung" style="margin-top:12px">
    <tr><td>Nilai proyek</td><td class="kanan"><?= e(rupiah($uang['nilai'])) ?></td></tr>
    <tr><td>Sudah dibayar</td><td class="kanan"><?= e(rupiah($uang['dibayar'])) ?></td></tr>
    <tr class="pisah">
      <td><?= $uang['sisa'] < 0 ? 'Lebih bayar' : 'Sisa tagihan' ?></td>
      <td class="kanan"><?= e(rupiah(abs((int) $uang['sisa']))) ?></td>
    </tr>
  </table>

  <?php if ($bayar): ?>
    <hr class="garis-halus">
    <div class="label">Pembayaran yang sudah masuk</div>
    <table style="margin-top:6px">
      <tbody>
        <?php foreach ($bayar as $b): ?>
          <?php $labelTermin = trim((string) $b['catatan']); ?>
          <tr>
            <td>
              <?= e(tanggalIndo($b['dibayar_pada'] ?: $b['dibuat_pada'])) ?><?php if ($labelTermin !== ''): ?> · <b><?= e($labelTermin) ?></b><?php endif; ?>
              <div class="kecil"><?= e(caraBayar($b['gerbang'], $b['metode'])) ?> · <?= e($b['kode_order']) ?></div>
            </td>
            <td class="kanan"><?= e(rupiah((int) $b['jumlah'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if (!$lunasKah && $usaha['bank'] !== ''): ?>
    <hr class="garis-halus">
    <div class="label">Pembayaran ditujukan ke</div>
    <div class="kotak-garis" style="margin-top:6px"><?= nl2br(e($usaha['bank'])) ?></div>
  <?php endif; ?>

  <?php if ($usaha['catatan'] !== ''): ?>
    <p class="kecil" style="margin-top:12px"><?= nl2br(e($usaha['catatan'])) ?></p>
  <?php endif; ?>

<?php elseif ($mode === 'kuitansi'): ?>

  <?php
  $dariSiapa = $order !== null
      ? (($order['lembaga'] ?? '') !== '' ? $order['lembaga'] : $order['nama'])
      : $t['pembeli_nama'];
  $labelTermin = trim((string) $t['catatan']);
  ?>
  <table class="sunyi">
    <tr>
      <td class="label" style="width:40mm">Telah terima dari</td>
      <td>
        <b><?= e($dariSiapa) ?></b>
        <?php if ($order !== null && ($order['lembaga'] ?? '') !== ''): ?>
          <span class="kecil">· u.p. <?= e($order['nama']) ?></span>
        <?php endif; ?>
      </td>
    </tr>
    <tr>
      <td class="label">Untuk pembayaran</td>
      <td><?= e($produkNama) ?><?php if ($labelTermin !== ''): ?> — <b><?= e($labelTermin) ?></b><?php endif; ?></td>
    </tr>
    <tr>
      <td class="label">Cara bayar</td>
      <td><?= e(caraBayar($t['gerbang'], $t['metode'])) ?></td>
    </tr>
    <?php if ($order !== null): ?>
      <tr>
        <td class="label">Order</td>
        <td><?= e(sprintf('JASA-%04d', (int) $order['id'])) ?></td>
      </tr>
    <?php endif; ?>
  </table>

  <hr class="garis-halus">

  <div class="label">Uang sejumlah</div>
  <div class="kotak-garis nominal-besar" style="margin-top:6px"><?= e(rupiah((int) $t['jumlah'])) ?></div>

  <?php if ($uang !== null && $uang['nilai'] !== null): ?>
    <table class="hitung" style="margin-top:14px">
      <tr><td>Nilai proyek</td><td class="kanan"><?= e(rupiah($uang['nilai'])) ?></td></tr>
      <tr><td>Total sudah dibayar</td><td class="kanan"><?= e(rupiah($uang['dibayar'])) ?></td></tr>
      <tr class="pisah">
        <td><?= $uang['sisa'] < 0 ? 'Lebih bayar' : 'Sisa' ?></td>
        <td class="kanan"><?= e(rupiah(abs((int) $uang['sisa']))) ?></td>
      </tr>
    </table>
    <?php if ($uang['sisa'] <= 0): ?>
      <p class="kecil" style="margin-top:8px">Pembayaran proyek ini sudah lunas.</p>
    <?php endif; ?>
  <?php endif; ?>

<?php endif; ?>

<?php if ($mode !== 'tolak'): ?>
  <div class="ttd">
    <div class="ttd-isi">
      <p class="kecil"><?= e(tanggalIndo($mode === 'tagihan' ? $hariIni : ($t['dibayar_pada'] ?: $t['dibuat_pada']))) ?></p>
      <div class="ttd-ruang"></div>
      <div class="ttd-nama"><?= e($usaha['ttd']) ?></div>
    </div>
  </div>
<?php endif; ?>

</div>

<?php if ($mode !== 'tolak'): ?>
<script>
  /* Dialog cetak dibuka sendiri. Tombol di bilah tetap ada sebagai cadangan,
     kalau peramban memblokirnya atau dialognya tertutup. */
  window.addEventListener('load', function () { window.print(); });
</script>
<?php endif; ?>

</body>
</html>
