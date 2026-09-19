<?php
declare(strict_types=1);

/* =============================================================================
   Formulir order sebuah produk: invishar.com/order/{slug}

   Tujuan tombol {{ORDER}} di landing page custom — satu alamat untuk semua
   jenis produk:
     sekali / langganan  → checkout (bayar)
     aplikasi lain       → aplikasi tujuan (membawa kode affiliate)
     penawaran           → formulir permintaan di halaman ini → Transaksi
   ============================================================================= */

require __DIR__ . '/inc/awal.php';

header('Cache-Control: no-store, max-age=0');

if (!penjualanSiap()) {
    halamanBuntu(503, 'Pemesanan belum dibuka', 'Halaman pemesanan sedang disiapkan. Hubungi kami lewat WhatsApp untuk memesan.', '/#kontak', 'Hubungi kami');
}

$slug = strtolower(trim((string) ($_GET['p'] ?? '')));
$produk = preg_match('/^[a-z0-9-]{1,80}$/', $slug)
    ? ambilSatu("SELECT * FROM produk WHERE slug = ? AND status = 'aktif'", [$slug])
    : null;
if (!$produk) {
    halamanBuntu(404, 'Produk tidak ditemukan', 'Mungkin alamatnya berubah atau produknya sudah tidak dijual.', '/#katalog', 'Lihat katalog');
}

if (bisaCheckout($produk)) {
    header('Location: /toko/checkout.php?p=' . rawurlencode($produk['slug']), true, 302);
    exit;
}
if ($produk['jenis'] === 'eksternal') {
    header('Location: /toko/keluar.php?p=' . rawurlencode($produk['slug']), true, 302);
    exit;
}

/* ---- produk lewat penawaran: formulir permintaan ---- */
$isian = ['nama' => '', 'lembaga' => '', 'whatsapp' => '', 'surel' => '', 'kebutuhan' => ''];
$galat = [];
$terkirim = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($isian as $k => $_) {
        $isian[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $isian['nama'] = mb_substr($isian['nama'], 0, 120);
    $isian['lembaga'] = mb_substr($isian['lembaga'], 0, 120);
    $isian['whatsapp'] = mb_substr($isian['whatsapp'], 0, 40);
    $isian['surel'] = mb_substr($isian['surel'], 0, 160);
    $isian['kebutuhan'] = mb_substr($isian['kebutuhan'], 0, 4000);

    if (trim((string) ($_POST['alamat'] ?? '')) !== '') {
        halamanBuntu(400, 'Permintaan ditolak', 'Muat ulang halaman lalu coba lagi.');
    }
    $ip = ipPengunjung();
    if ((int) ambilNilai('SELECT COUNT(*) FROM order_jasa WHERE ip = ? AND dibuat_pada > (NOW() - INTERVAL 1 HOUR)', [$ip]) >= 5) {
        $galat[] = 'Terlalu banyak permintaan dalam satu jam. Coba lagi nanti, atau hubungi kami lewat WhatsApp.';
    }
    if ($isian['nama'] === '') {
        $galat[] = 'Isi nama Anda atau nama lembaga.';
    }
    if (strlen(normalWa($isian['whatsapp'])) < 9) {
        $galat[] = 'Isi nomor WhatsApp yang aktif — kami membalas lewat sana.';
    }
    if ($isian['surel'] !== '' && !filter_var($isian['surel'], FILTER_VALIDATE_EMAIL)) {
        $galat[] = 'Alamat surel belum benar (atau kosongkan saja).';
    }
    if (mb_strlen($isian['kebutuhan']) < 10) {
        $galat[] = 'Ceritakan sedikit kebutuhan Anda (minimal satu kalimat).';
    }

    if (!$galat) {
        $aff = affiliateDariCookie();
        q(
            'INSERT INTO order_jasa (nama, lembaga, surel, whatsapp, kebutuhan, sumber, produk_id, affiliate_id, ip, status, dibuat_pada, diperbarui_pada)
             VALUES (?, ?, ?, ?, ?, \'landing\', ?, ?, ?, \'baru\', NOW(), NOW())',
            [
                $isian['nama'], $isian['lembaga'] ?: null, $isian['surel'] ?: null, $isian['whatsapp'], $isian['kebutuhan'],
                (int) $produk['id'], $aff['id'] ?? null, $ip,
            ]
        );
        $id = (int) db()->lastInsertId();
        q(
            'INSERT INTO order_riwayat (order_id, status_baru, catatan, dibuat_pada) VALUES (?, \'baru\', ?, NOW())',
            [$id, 'Masuk dari formulir order ' . $produk['nama'] . ($aff ? ' · lewat link affiliate ' . $aff['kode'] : '')]
        );
        $terkirim = true;
    }
}

$judulHalaman = ($terkirim ? 'Permintaan terkirim · ' : 'Pesan ') . $produk['nama'];
require __DIR__ . '/inc/kepala.php';
?>

<?php if ($terkirim): ?>
  <section class="toko-wrap toko-buntu">
    <div class="status-ikon status-baik" aria-hidden="true">✓</div>
    <h1>Permintaan terkirim</h1>
    <p class="lead">Terima kasih, <?= e($isian['nama']) ?>. Kami membalas lewat WhatsApp <strong><?= e($isian['whatsapp']) ?></strong>
      dalam 1–2 hari kerja dengan perkiraan lingkup, waktu, dan biaya.</p>
    <a class="btn btn-solid" href="/p/<?= e($produk['slug']) ?>">Kembali ke halaman produk</a>
  </section>
<?php else: ?>
  <section class="toko-wrap">
    <div class="checkout-grid">
      <div>
        <h1>Ceritakan kebutuhan Anda</h1>
        <p class="toko-sub">Gratis dan tanpa kewajiban. Kami balas dalam 1–2 hari kerja lewat WhatsApp.</p>

        <?php if ($galat): ?>
          <div class="toko-galat" role="alert">
            <?php foreach ($galat as $g): ?><p><?= e($g) ?></p><?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form class="form toko-form" method="post" id="form-order" novalidate>
          <div class="perangkap" aria-hidden="true">
            <label for="alamat">Alamat</label>
            <input id="alamat" name="alamat" type="text" tabindex="-1" autocomplete="off">
          </div>
          <div class="field">
            <label for="nama">Nama</label>
            <input id="nama" name="nama" type="text" autocomplete="name" value="<?= e($isian['nama']) ?>" required>
          </div>
          <div class="field">
            <label for="lembaga">Lembaga / usaha <span class="opsional">(opsional)</span></label>
            <input id="lembaga" name="lembaga" type="text" autocomplete="organization" value="<?= e($isian['lembaga']) ?>">
          </div>
          <div class="field">
            <label for="whatsapp">WhatsApp</label>
            <input id="whatsapp" name="whatsapp" type="tel" autocomplete="tel" inputmode="tel" placeholder="0812…" value="<?= e($isian['whatsapp']) ?>" required>
          </div>
          <div class="field">
            <label for="surel">Surel <span class="opsional">(opsional)</span></label>
            <input id="surel" name="surel" type="email" autocomplete="email" placeholder="nama@domain.com" value="<?= e($isian['surel']) ?>">
          </div>
          <div class="field">
            <label for="kebutuhan">Yang ingin dibereskan</label>
            <textarea id="kebutuhan" name="kebutuhan" rows="5" required placeholder="Mis. data santri dan uang saku masih di buku tulis…"><?= e($isian['kebutuhan']) ?></textarea>
          </div>
          <button class="btn btn-solid btn-block" type="submit" id="tombol-kirim">Kirim permintaan</button>
        </form>
      </div>

      <aside class="ringkasan-kartu" aria-label="Produk yang dipesan">
        <p class="ringkasan-label">Yang Anda tanyakan</p>
        <?php if ($produk['gambar']): ?>
          <img class="ringkasan-gambar" src="<?= e(urlGambarProduk($produk['gambar'])) ?>" alt="">
        <?php endif; ?>
        <p class="ringkasan-nama"><?= e($produk['nama']) ?></p>
        <p class="ringkasan-jenis"><?= e(teksHargaProduk($produk)) ?></p>
        <a class="ringkasan-kembali" href="/p/<?= e($produk['slug']) ?>">&larr; Kembali ke halaman produk</a>
      </aside>
    </div>
  </section>
  <script>
    document.getElementById("form-order").addEventListener("submit", function () {
      var t = document.getElementById("tombol-kirim");
      setTimeout(function () { t.disabled = true; t.textContent = "Mengirim…"; }, 0);
    });
  </script>
<?php endif; ?>

<?php require __DIR__ . '/inc/kaki.php'; ?>
