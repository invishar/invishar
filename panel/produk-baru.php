<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
require __DIR__ . '/inc/produk.php';
wajibMasuk();
wajibPenjualanSiap();

/* =============================================================================
   Tambah produk — langkah pertama: pilih kategorinya.
     Produk / Jasa → formulir produk (produk-edit.php)
     Kelas         → judul kelas di sini → kelas + produknya dibuat sekaligus,
                     lalu lanjut ke formulir kelas (modul & materi).
   ============================================================================= */

$kategori = (string) ($_GET['kategori'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $judulKelas = mb_substr(masukan('judul'), 0, 160);
    if ($judulKelas === '') {
        pesan('Judul kelas wajib diisi.', 'buruk');
        pergi(tautan('produk-baru') . '?kategori=kelas');
    }

    $kelasId = dalamTransaksi(function () use ($judulKelas): int {
        $slug = slugkan($judulKelas);
        if (ambilNilai('SELECT id FROM kelas WHERE slug = ?', [$slug]) !== null) {
            $slug .= '-' . substr((string) time(), -4);
        }
        $urutan = (int) ambilNilai('SELECT COALESCE(MAX(urutan), -1) + 1 FROM kelas');
        q(
            'INSERT INTO kelas (slug, judul, ringkas, urutan, detail, diperbarui_pada) VALUES (?, ?, \'\', ?, ?, NOW())',
            [$slug, $judulKelas, $urutan, json_encode([
                'kicker'   => 'Kelas',
                'bahasa'   => 'Bahasa Indonesia',
                'akses'    => 'Akses selamanya',
                'pengajar' => ['nama' => 'Tim Invishar', 'peran' => 'Studio produk digital'],
                'ikhtisar' => ['hasil' => [], 'untukSiapa' => [], 'syarat' => []],
                'sumber'   => [],
                'tanya'    => [],
            ], JSON_UNESCAPED_UNICODE)]
        );
        $id = (int) db()->lastInsertId();
        buatProdukDariKelas(ambilSatu('SELECT * FROM kelas WHERE id = ?', [$id]), null, 'draf');
        return $id;
    });
    catatLog('tambah kelas', $judulKelas);
    pesan('Kelas "' . $judulKelas . '" dibuat sebagai Draf. Lengkapi isi kelasnya, lalu atur harga & tayang di tab Penjualan.');
    pergi(tautan('kelas/' . $kelasId));
}

if ($kategori === 'produk' || $kategori === 'jasa') {
    pergi(tautan('produk-edit') . '?kategori=' . $kategori);
}

$judul = 'Tambah produk';
$menu  = 'produk';
require __DIR__ . '/inc/kepala.php';
?>

<p class="remah"><a href="<?= tautan('produk') ?>">&larr; Semua produk</a></p>

<?php if ($kategori !== 'kelas'): ?>
  <p class="pengantar">Pilih kategorinya dulu — formulir berikutnya menyesuaikan.</p>
  <div class="kategori-pilih">
    <?php
      $ikon = ['produk' => '▣', 'jasa' => '✎', 'kelas' => '▶'];
      $contoh = ['produk' => 'Ponpes Manager, amanafinance Pro', 'jasa' => 'Pembuatan aplikasi, desain sistem', 'kelas' => 'Kelas Dashboard Sosial Media'];
    ?>
    <?php foreach (KATEGORI_PRODUK as $kunci => $label): ?>
      <a class="kategori-kartu" href="<?= tautan('produk-baru') ?>?kategori=<?= e($kunci) ?>">
        <span class="kategori-ikon kategori-ikon-<?= e($kunci) ?>" aria-hidden="true"><?= $ikon[$kunci] ?></span>
        <span class="kategori-judul"><?= e($label) ?></span>
        <span class="kategori-sub"><?= e(KATEGORI_KETERANGAN[$kunci]) ?></span>
        <span class="kategori-contoh">Mis. <?= e($contoh[$kunci]) ?></span>
        <span class="kategori-lanjut">Pilih &rarr;</span>
      </a>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <section class="kotak kotak-sempit">
    <div class="kotak-kepala"><h2>Kelas baru</h2></div>
    <p class="bagian-sub">Mulai dari judulnya. Setelah itu Anda masuk ke formulir kelas: identitas, tampilan galeri,
      modul &amp; materi. Harga dan tayangnya diatur di tab <strong>Penjualan</strong>.</p>
    <form method="post" class="form-panel">
      <?= csrfInput() ?>
      <div class="bidang">
        <label for="judul">Judul kelas</label>
        <input class="isian-besar" id="judul" name="judul" type="text" maxlength="160" required autofocus
               placeholder="mis. Membuat Dashboard Sosial Media">
      </div>
      <div class="form-aksi">
        <button class="tbl tbl-utama" type="submit">Buat kelas</button>
        <a class="tbl" href="<?= tautan('produk-baru') ?>">Kembali</a>
      </div>
    </form>
  </section>
<?php endif; ?>

<?php require __DIR__ . '/inc/kaki.php'; ?>
