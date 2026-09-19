<?php
declare(strict_types=1);

/* =============================================================================
   Checkout: /toko/checkout.php?p={slug}

   Harga SELALU diambil dari basis data, tidak pernah dari kiriman peramban.
   Affiliate ditentukan di sini (atribusi) lalu DIKUNCI di transaksi —
   cookie yang berubah sesudahnya tidak berpengaruh.
   ============================================================================= */

require __DIR__ . '/inc/awal.php';

header('Cache-Control: no-store, max-age=0');

if (!penjualanSiap()) {
    halamanBuntu(503, 'Pembayaran belum dibuka', 'Halaman pembayaran sedang disiapkan. Hubungi kami lewat WhatsApp untuk memesan.', '/#kontak', 'Hubungi kami');
}

$slug = strtolower(trim((string) ($_GET['p'] ?? '')));
$produk = preg_match('/^[a-z0-9-]{1,80}$/', $slug)
    ? ambilSatu("SELECT * FROM produk WHERE slug = ? AND status = 'aktif'", [$slug])
    : null;

if (!$produk) {
    halamanBuntu(404, 'Produk tidak ditemukan', 'Mungkin alamatnya berubah atau produknya sudah tidak dijual.', '/#katalog', 'Lihat katalog');
}
if (!bisaCheckout($produk)) {
    halamanBuntu(400, 'Produk ini tidak dibeli lewat checkout', 'Silakan hubungi kami lewat halaman produknya.', '/p/' . $produk['slug'], 'Kembali ke produk');
}

$ref = (string) ($_GET['ref'] ?? '');
$isian = ['nama' => '', 'whatsapp' => '', 'surel' => ''];
$galat = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isian = [
        'nama'     => mb_substr(trim((string) ($_POST['nama'] ?? '')), 0, 120),
        'whatsapp' => mb_substr(trim((string) ($_POST['whatsapp'] ?? '')), 0, 40),
        'surel'    => mb_substr(trim((string) ($_POST['surel'] ?? '')), 0, 160),
    ];

    // Perangkap robot: manusia tidak melihat bidang ini.
    if (trim((string) ($_POST['alamat'] ?? '')) !== '') {
        halamanBuntu(400, 'Permintaan ditolak', 'Muat ulang halaman lalu coba lagi.');
    }

    $ip = ipPengunjung();
    $baruSaja = (int) ambilNilai(
        'SELECT COUNT(*) FROM transaksi WHERE ip = ? AND dibuat_pada > (NOW() - INTERVAL 1 HOUR)',
        [$ip]
    );
    if ($baruSaja >= 10) {
        $galat[] = 'Terlalu banyak percobaan dalam satu jam. Coba lagi nanti, atau hubungi kami lewat WhatsApp.';
    }
    if ($isian['nama'] === '') {
        $galat[] = 'Isi nama Anda.';
    }
    if (strlen(normalWa($isian['whatsapp'])) < 9) {
        $galat[] = 'Isi nomor WhatsApp yang aktif — akses dikirim ke sana.';
    }
    if (!filter_var($isian['surel'], FILTER_VALIDATE_EMAIL)) {
        $galat[] = 'Isi alamat surel yang benar.';
    }

    if (!$galat) {
        $atribusi = atribusi($produk, $isian['surel'], $isian['whatsapp'], (string) ($_POST['ref'] ?? $ref));
        $gerbang = gerbangAktif();

        $trx = buatTransaksi([
            'produk_id'    => $produk['id'],
            'pembeli_nama' => $isian['nama'],
            'surel'        => $isian['surel'],
            'whatsapp'     => $isian['whatsapp'],
            'jumlah'       => (int) $produk['harga'],
            'gerbang'      => $gerbang->nama(),
            'sumber'       => 'checkout',
            'affiliate_id' => $atribusi['affiliate']['id'] ?? null,
            'beli_sendiri' => $atribusi['beli_sendiri'],
            'periode_ke'   => 1,
            'ip'           => $ip,
            'catatan_riwayat' => 'Checkout' . ($atribusi['affiliate'] ? ' lewat affiliate ' . $atribusi['affiliate']['kode'] . ($atribusi['beli_sendiri'] ? ' (beli sendiri — tanpa komisi)' : '') : ''),
        ]);

        try {
            $tujuan = $gerbang->mulai($trx, $produk);
        } catch (Throwable $e) {
            error_log('[invishar checkout] ' . $e->getMessage());
            ubahStatusTransaksi((int) $trx['id'], 'gagal', 'checkout', mb_substr('Gerbang gagal: ' . $e->getMessage(), 0, 255));
            halamanBuntu(502, 'Pembayaran belum bisa dimulai', 'Layanan pembayaran sedang bermasalah. Coba beberapa menit lagi, atau hubungi kami lewat WhatsApp.', '/toko/checkout.php?p=' . rawurlencode($produk['slug']), 'Coba lagi');
        }

        header('Location: ' . $tujuan, true, 303);
        exit;
    }
}

$langganan = $produk['jenis'] === 'langganan';
$judulHalaman = 'Checkout ' . $produk['nama'];
require __DIR__ . '/inc/kepala.php';
?>

<section class="toko-wrap">
  <nav class="toko-langkah" aria-label="Langkah pembelian">
    <span class="is-lewat">Produk</span>
    <span class="is-kini" aria-current="step">Data pembeli</span>
    <span>Pembayaran</span>
  </nav>

  <div class="checkout-grid">
    <div>
      <h1>Data pembeli</h1>
      <p class="toko-sub">Dipakai untuk mengirim akses dan bukti pembelian. Tidak dibagikan ke pihak lain.</p>

      <?php if ($galat): ?>
        <div class="toko-galat" role="alert">
          <?php foreach ($galat as $g): ?><p><?= e($g) ?></p><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form class="form toko-form" method="post" id="form-checkout" novalidate>
        <input type="hidden" name="ref" value="<?= e($ref) ?>">
        <div class="perangkap" aria-hidden="true">
          <label for="alamat">Alamat</label>
          <input id="alamat" name="alamat" type="text" tabindex="-1" autocomplete="off">
        </div>

        <div class="field">
          <label for="nama">Nama lengkap</label>
          <input id="nama" name="nama" type="text" autocomplete="name" value="<?= e($isian['nama']) ?>" required>
        </div>
        <div class="field">
          <label for="whatsapp">WhatsApp</label>
          <input id="whatsapp" name="whatsapp" type="tel" autocomplete="tel" inputmode="tel" placeholder="0812…" value="<?= e($isian['whatsapp']) ?>" required>
        </div>
        <div class="field">
          <label for="surel">Surel</label>
          <input id="surel" name="surel" type="email" autocomplete="email" placeholder="nama@domain.com" value="<?= e($isian['surel']) ?>" required>
        </div>

        <button class="btn btn-solid btn-block" type="submit" id="tombol-bayar">
          Lanjut ke pembayaran &middot; <?= e(rupiah((int) $produk['harga'])) ?>
        </button>
        <p class="form-note">Anda akan diarahkan ke halaman pembayaran<?= modeUji() ? ' (simulasi)' : ' Midtrans' ?>.</p>
      </form>
    </div>

    <aside class="ringkasan-kartu" aria-label="Ringkasan pesanan">
      <p class="ringkasan-label">Ringkasan pesanan</p>
      <?php if ($produk['gambar']): ?>
        <img class="ringkasan-gambar" src="<?= e(urlGambarProduk($produk['gambar'])) ?>" alt="">
      <?php endif; ?>
      <p class="ringkasan-nama"><?= e($produk['nama']) ?></p>
      <p class="ringkasan-jenis"><?= e(JENIS_PRODUK[$produk['jenis']]['label']) ?></p>

      <dl class="rincian">
        <div><dt><?= $langganan ? 'Langganan bulan pertama' : 'Harga' ?></dt><dd><?= e(rupiah((int) $produk['harga'])) ?></dd></div>
        <div class="rincian-total"><dt>Total dibayar</dt><dd><?= e(rupiah((int) $produk['harga'])) ?></dd></div>
      </dl>
      <?php if ($langganan): ?>
        <p class="ringkasan-catatan">Bulan berikutnya <?= e(rupiah((int) $produk['harga'])) ?> per bulan, tagihannya dikirim lewat WhatsApp.</p>
      <?php endif; ?>
      <a class="ringkasan-kembali" href="/p/<?= e($produk['slug']) ?>">&larr; Kembali ke halaman produk</a>
    </aside>
  </div>
</section>

<script>
  // Cegah tagihan ganda karena tombol ditekan dua kali.
  document.getElementById("form-checkout").addEventListener("submit", function () {
    var t = document.getElementById("tombol-bayar");
    setTimeout(function () { t.disabled = true; t.textContent = "Menyiapkan pembayaran…"; }, 0);
  });
</script>

<?php require __DIR__ . '/inc/kaki.php'; ?>
