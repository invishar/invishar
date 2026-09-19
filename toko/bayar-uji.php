<?php
declare(strict_types=1);

/* =============================================================================
   Simulasi pembayaran — HANYA hidup saat konfig 'gerbang' => 'uji'.

   Tombol di sini memanggil ubahStatusTransaksi(), jalur yang sama persis
   dengan webhook Midtrans. Jadi yang dicoba sekarang (komisi, langganan,
   saldo affiliate) adalah perilaku yang akan terjadi dengan uang sungguhan.
   ============================================================================= */

require __DIR__ . '/inc/awal.php';

header('Cache-Control: no-store, max-age=0');

if (!modeUji() || !penjualanSiap()) {
    halamanBuntu(404, 'Halaman tidak tersedia', 'Pembayaran simulasi hanya ada selama mode uji.');
}

$kode = (string) ($_GET['o'] ?? $_POST['o'] ?? '');
$trx = preg_match('/^INV-\d{6}-[A-Z0-9]{6}$/', $kode)
    ? ambilSatu("SELECT * FROM transaksi WHERE kode_order = ? AND gerbang = 'uji'", [$kode])
    : null;
if (!$trx) {
    halamanBuntu(404, 'Transaksi tidak ditemukan', 'Periksa lagi alamatnya, atau mulai pembelian dari halaman produk.');
}

const HASIL_UJI = [
    'lunas'       => 'Simulasikan pembayaran berhasil',
    'gagal'       => 'Simulasikan pembayaran gagal',
    'kedaluwarsa' => 'Simulasikan kedaluwarsa',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = (string) ($_POST['hasil'] ?? '');
    if (!hash_equals(GerbangUji::token($trx['kode_order']), (string) ($_POST['token'] ?? '')) || !isset(HASIL_UJI[$aksi])) {
        halamanBuntu(400, 'Permintaan ditolak', 'Muat ulang halaman lalu coba lagi.');
    }
    ubahStatusTransaksi((int) $trx['id'], $aksi, 'uji', 'Simulasi dari halaman bayar uji');
    header('Location: /toko/selesai.php?o=' . rawurlencode($trx['kode_order']), true, 303);
    exit;
}

$produk = ambilSatu('SELECT * FROM produk WHERE id = ?', [$trx['produk_id']]);
$judulHalaman = 'Bayar (uji)';
require __DIR__ . '/inc/kepala.php';
?>

<section class="toko-wrap toko-sempit">
  <nav class="toko-langkah" aria-label="Langkah pembelian">
    <span class="is-lewat">Produk</span>
    <span class="is-lewat">Data pembeli</span>
    <span class="is-kini" aria-current="step">Pembayaran</span>
  </nav>

  <div class="kartu-bayar">
    <p class="ringkasan-label">Halaman pembayaran simulasi</p>
    <h1><?= e(rupiah((int) $trx['jumlah'])) ?></h1>
    <dl class="rincian">
      <div><dt>Produk</dt><dd><?= e($produk['nama'] ?? '—') ?><?= (int) $trx['periode_ke'] > 1 ? ' · bulan ke-' . (int) $trx['periode_ke'] : '' ?></dd></div>
      <div><dt>Kode pesanan</dt><dd class="kode"><?= e($trx['kode_order']) ?></dd></div>
      <div><dt>Status sekarang</dt><dd><?= e(STATUS_TRANSAKSI[$trx['status']] ?? $trx['status']) ?></dd></div>
    </dl>

    <?php if ($trx['status'] === 'menunggu'): ?>
      <p class="toko-sub">Pilih hasil pembayaran yang ingin dicoba. Di mode sungguhan, bagian ini diganti halaman Midtrans.</p>
      <form method="post" class="aksi-uji">
        <input type="hidden" name="o" value="<?= e($trx['kode_order']) ?>">
        <input type="hidden" name="token" value="<?= e(GerbangUji::token($trx['kode_order'])) ?>">
        <button class="btn btn-solid btn-block" type="submit" name="hasil" value="lunas"><?= e(HASIL_UJI['lunas']) ?></button>
        <button class="btn btn-outline btn-block" type="submit" name="hasil" value="gagal"><?= e(HASIL_UJI['gagal']) ?></button>
        <button class="btn btn-outline btn-block" type="submit" name="hasil" value="kedaluwarsa"><?= e(HASIL_UJI['kedaluwarsa']) ?></button>
      </form>
    <?php else: ?>
      <p class="toko-sub">Transaksi ini sudah tidak menunggu pembayaran.</p>
      <a class="btn btn-solid btn-block" href="/toko/selesai.php?o=<?= e(rawurlencode($trx['kode_order'])) ?>">Lihat status pembayaran</a>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/inc/kaki.php'; ?>
