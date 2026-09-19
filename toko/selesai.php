<?php
declare(strict_types=1);

/* =============================================================================
   Status pembayaran: /toko/selesai.php?o={kode_order}
   Juga tujuan "finish" dari Midtrans (yang menambahkan ?order_id=…).

   Untuk transaksi Midtrans yang masih menunggu, status diperiksa langsung ke
   Midtrans di sini — jaring pengaman kalau notifikasi webhook terlambat
   atau alamat notifikasi belum disetel.
   ============================================================================= */

require __DIR__ . '/inc/awal.php';

header('Cache-Control: no-store, max-age=0');

if (!penjualanSiap()) {
    halamanBuntu(404, 'Transaksi tidak ditemukan', 'Periksa lagi alamatnya.');
}

$kode = (string) ($_GET['o'] ?? $_GET['order_id'] ?? '');
$trx = preg_match('/^INV-\d{6}-[A-Z0-9]{6}$/', $kode)
    ? ambilSatu('SELECT * FROM transaksi WHERE kode_order = ?', [$kode])
    : null;
if (!$trx) {
    halamanBuntu(404, 'Transaksi tidak ditemukan', 'Periksa lagi alamatnya, atau hubungi kami dengan menyebutkan kode pesanan Anda.', '/#kontak', 'Hubungi kami');
}

if ($trx['gerbang'] === 'midtrans' && $trx['status'] === 'menunggu') {
    try {
        $midtrans = new GerbangMidtrans();
        $s = $midtrans->siap() ? $midtrans->status($trx['kode_order']) : null;
        if ($s) {
            terapkanStatusMidtrans($trx, $s, 'midtrans', json_encode($s, JSON_UNESCAPED_UNICODE));
            $trx = ambilSatu('SELECT * FROM transaksi WHERE id = ?', [$trx['id']]);
        }
    } catch (Throwable $e) {
        error_log('[invishar selesai] ' . $e->getMessage());
    }
}

$produk = ambilSatu('SELECT * FROM produk WHERE id = ?', [$trx['produk_id']]);
$namaDepan = explode(' ', trim($trx['pembeli_nama']))[0] ?? '';
$waSamaran = $trx['whatsapp'] ? substr(normalWa($trx['whatsapp']), 0, 5) . '•••' . substr(normalWa($trx['whatsapp']), -3) : '';

$tampilan = [
    'lunas'       => ['ikon' => '✓', 'kelas' => 'status-baik',   'judul' => 'Pembayaran berhasil',
                      'teks' => 'Terima kasih, ' . $namaDepan . '! Pembayaran Anda sudah kami terima.'],
    'menunggu'    => ['ikon' => '…', 'kelas' => 'status-tunggu', 'judul' => 'Menunggu pembayaran',
                      'teks' => 'Selesaikan pembayaran Anda. Halaman ini menampilkan status terbaru setiap kali dimuat ulang.'],
    'gagal'       => ['ikon' => '×', 'kelas' => 'status-buruk',  'judul' => 'Pembayaran tidak berhasil',
                      'teks' => 'Tidak ada dana yang terpotong. Anda bisa mencoba lagi.'],
    'kedaluwarsa' => ['ikon' => '×', 'kelas' => 'status-buruk',  'judul' => 'Waktu pembayaran habis',
                      'teks' => 'Batas waktu pembayaran sudah lewat. Silakan buat pesanan baru.'],
    'refund'      => ['ikon' => '↩', 'kelas' => 'status-netral', 'judul' => 'Dana sudah dikembalikan',
                      'teks' => 'Pembayaran untuk pesanan ini sudah dikembalikan.'],
][$trx['status']] ?? ['ikon' => '!', 'kelas' => 'status-netral', 'judul' => 'Status pesanan', 'teks' => ''];

$lanjutkan = '';
if ($trx['status'] === 'menunggu') {
    $lanjutkan = $trx['gerbang'] === 'uji'
        ? '/toko/bayar-uji.php?o=' . rawurlencode($trx['kode_order'])
        : (string) $trx['gerbang_url'];
}

$judulHalaman = $tampilan['judul'];
require __DIR__ . '/inc/kepala.php';
?>

<section class="toko-wrap toko-sempit">
  <div class="kartu-bayar kartu-status">
    <div class="status-ikon <?= e($tampilan['kelas']) ?>" aria-hidden="true"><?= e($tampilan['ikon']) ?></div>
    <h1><?= e($tampilan['judul']) ?></h1>
    <p class="toko-sub"><?= e($tampilan['teks']) ?></p>

    <dl class="rincian">
      <div><dt>Produk</dt><dd><?= e($produk['nama'] ?? '—') ?><?= (int) $trx['periode_ke'] > 1 ? ' · bulan ke-' . (int) $trx['periode_ke'] : '' ?></dd></div>
      <div><dt>Kode pesanan</dt><dd class="kode"><?= e($trx['kode_order']) ?></dd></div>
      <div><dt>Jumlah</dt><dd><?= e(rupiah((int) $trx['jumlah'])) ?></dd></div>
      <?php if ($trx['status'] === 'lunas'): ?>
        <div><dt>Dibayar</dt><dd><?= e(waktuIndo($trx['dibayar_pada'])) ?></dd></div>
      <?php endif; ?>
    </dl>

    <?php if ($trx['status'] === 'lunas'): ?>
      <div class="langkah-lanjut">
        <p class="ringkasan-label">Langkah berikutnya</p>
        <p>Kami segera menghubungi Anda<?= $waSamaran ? ' lewat WhatsApp <strong>' . e($waSamaran) . '</strong>' : '' ?> untuk mengirim akses.
           Simpan kode pesanan di atas untuk berjaga-jaga.</p>
      </div>
      <a class="btn btn-outline btn-block" href="/">Kembali ke beranda</a>
    <?php elseif ($lanjutkan !== ''): ?>
      <a class="btn btn-solid btn-block" href="<?= e($lanjutkan) ?>">Lanjutkan pembayaran</a>
      <a class="btn btn-outline btn-block" href="/toko/selesai.php?o=<?= e(rawurlencode($trx['kode_order'])) ?>">Periksa status lagi</a>
    <?php elseif (in_array($trx['status'], ['gagal', 'kedaluwarsa'], true) && $produk && $produk['status'] === 'aktif'): ?>
      <a class="btn btn-solid btn-block" href="/toko/checkout.php?p=<?= e(rawurlencode($produk['slug'])) ?>">Coba lagi</a>
    <?php else: ?>
      <a class="btn btn-outline btn-block" href="/">Kembali ke beranda</a>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/inc/kaki.php'; ?>
