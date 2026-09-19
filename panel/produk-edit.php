<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
require __DIR__ . '/inc/halaman.php';
wajibMasuk();
wajibPenjualanSiap();

/* =============================================================================
   Tambah / sunting produk. id kosong = produk baru (?kategori=produk|jasa).
   Kelas dibuat lewat produk-baru.php; di sini kelas hanya diatur penjualan &
   landing page-nya (isi kelas di tab "Isi kelas" → kelas-edit.php).
   Komisi affiliate TIDAK diatur di sini, melainkan di Produk afiliasi.
   Setiap penyimpanan langsung menulis ulang data/produk.json.
   ============================================================================= */

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$lama = $id ? ambilSatu('SELECT * FROM produk WHERE id = ?', [$id]) : null;
if ($id && $lama === null) {
    pesan('Produk tidak ditemukan.', 'buruk');
    pergi(tautan('produk'));
}

$kategori = $lama ? (string) $lama['kategori'] : (string) ($_GET['kategori'] ?? $_POST['kategori'] ?? 'produk');
if (!$lama && $kategori === 'kelas') {
    pergi(tautan('produk-baru') . '?kategori=kelas');
}
if (!isset(KATEGORI_PRODUK[$kategori])) {
    $kategori = 'produk';
}
$kelasTaut = $lama && $lama['kelas_id'] ? ambilSatu('SELECT id, judul, slug, gambar, status FROM kelas WHERE id = ?', [$lama['kelas_id']]) : null;

$kosong = [
    'id' => 0, 'slug' => '', 'nama' => '', 'jenis' => JENIS_PER_KATEGORI[$kategori][0], 'kategori' => $kategori, 'kelas_id' => null,
    'tagline' => '', 'ringkas' => '', 'isi' => '', 'manfaat' => '', 'tanya' => '[]', 'gambar' => null, 'harga' => null,
    'url_eksternal' => '', 'url_admin' => '', 'label_tombol' => '', 'lp_mode' => 'bawaan', 'lp_berkas' => null,
    'lp_diunggah_pada' => null, 'status' => 'draf', 'urutan' => 0, 'affiliate_aktif' => 0,
];
$p = $lama ?? $kosong;
$galat = [];

$jumlahTransaksi = $id ? (int) ambilNilai('SELECT COUNT(*) FROM transaksi WHERE produk_id = ?', [$id]) : 0;

/* Unggahan melebihi post_max_size: PHP membuang seluruh isian, termasuk CSRF. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    pesan('Unggahan terlalu besar untuk server (maks. ' . batasUnggahServer() . ' sekali kirim). Kecilkan gambarnya atau unggah sebagian.', 'buruk');
    pergi($lama ? tautan('produk/' . $id) : tautan('produk-edit') . '?kategori=' . $kategori);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $aksi = masukan('aksi', 'simpan');

    /* ---- hapus ---- */
    if ($aksi === 'hapus' && $lama) {
        if ($kategori === 'kelas') {
            pesan('Produk kelas dihapus bersama kelasnya — lewat tab Isi kelas, bagian paling bawah.', 'buruk');
            pergi(tautan('produk/' . $id));
        }
        if ($jumlahTransaksi > 0) {
            pesan('Produk ini sudah punya ' . $jumlahTransaksi . ' transaksi, jadi tidak bisa dihapus. Ubah statusnya menjadi Diarsipkan.', 'buruk');
            pergi(tautan('produk/' . $id));
        }
        q('DELETE FROM produk WHERE id = ?', [$id]);
        buangGambarProduk($lama['gambar']);
        hapusFolderLp($lama['lp_berkas']);
        catatLog('hapus produk', $lama['nama']);
        $g = terbitkanProduk();
        pesan('Produk "' . $lama['nama'] . '" dihapus' . ($g ? ', tapi landing page gagal diperbarui: ' . $g : '.'), $g ? 'peringatan' : 'baik');
        pergi(tautan('produk'));
    }

    /* ---- hapus gambar ---- */
    if ($aksi === 'hapus_gambar' && $lama) {
        buangGambarProduk($lama['gambar']);
        q('UPDATE produk SET gambar = NULL, diperbarui_pada = NOW() WHERE id = ?', [$id]);
        catatLog('hapus gambar produk', $lama['nama']);
        terbitkanProduk();
        pesan('Gambar dihapus.');
        pergi(tautan('produk/' . $id));
    }

    /* ---- kembali ke landing page bawaan (desain custom dibuang) ---- */
    if ($aksi === 'hapus_lp' && $lama) {
        if ($lama['status'] === 'aktif' && $lama['lp_mode'] === 'custom') {
            // Tetap tayang: pengunjung langsung melihat halaman bawaan.
        }
        q("UPDATE produk SET lp_mode = 'bawaan', lp_berkas = NULL, lp_diunggah_pada = NULL, diperbarui_pada = NOW() WHERE id = ?", [$id]);
        hapusFolderLp($lama['lp_berkas']);
        catatLog('hapus landing page custom', $lama['nama']);
        pesan('Desain custom dihapus. Landing page kembali memakai tampilan bawaan.');
        pergi(tautan('produk/' . $id) . '#landing');
    }

    /* ---- simpan ---- */
    // Isian yang tidak terkirim (bagiannya tersembunyi) mempertahankan nilai lama.
    $ambil = fn (string $k, int $maks = 0) => array_key_exists($k, $_POST)
        ? ($maks > 0 ? mb_substr(masukan($k), 0, $maks) : masukan($k))
        : (string) ($p[$k] ?? '');

    $d = $p;
    if ($kategori !== 'kelas') {
        $d['nama'] = mb_substr(masukan('nama'), 0, 160);
        // Produk dan jasa boleh bertukar kategori; kelas tidak.
        $kategoriBaru = masukan('kategori', $kategori);
        if (in_array($kategoriBaru, ['produk', 'jasa'], true)) {
            $d['kategori'] = $kategoriBaru;
        }
    }
    $jenisBoleh = JENIS_PER_KATEGORI[$d['kategori']];
    $d['jenis']         = in_array(masukan('jenis'), $jenisBoleh, true) ? masukan('jenis') : (in_array($p['jenis'], $jenisBoleh, true) ? $p['jenis'] : $jenisBoleh[0]);
    $d['status']        = isset(STATUS_PRODUK[masukan('status')]) ? masukan('status') : $p['status'];
    $d['urutan']        = (int) masukan('urutan', (string) $p['urutan']);
    $d['harga']         = angkaRupiah(masukan('harga'));
    $d['tagline']       = $ambil('tagline', 200);
    $d['ringkas']       = $ambil('ringkas');
    $d['isi']           = $ambil('isi');
    $d['manfaat']       = $ambil('manfaat');
    $d['label_tombol']  = $ambil('label_tombol', 40);
    $d['url_eksternal'] = $ambil('url_eksternal', 255);
    $d['url_admin']     = $d['kategori'] === 'produk' ? $ambil('url_admin', 255) : '';
    $d['lp_mode']       = masukan('lp_mode') === 'custom' ? 'custom' : (array_key_exists('lp_mode', $_POST) ? 'bawaan' : (string) $p['lp_mode']);

    if (array_key_exists('tanya_q', $_POST)) {
        $tanya = [];
        foreach ((array) $_POST['tanya_q'] as $i => $q) {
            $q = trim((string) $q);
            $a = trim((string) ($_POST['tanya_a'][$i] ?? ''));
            if ($q !== '' && $a !== '') {
                $tanya[] = ['q' => $q, 'a' => $a];
            } elseif ($q !== '' || $a !== '') {
                $galat[] = 'Setiap tanya jawab butuh pertanyaan dan jawaban. Lengkapi atau kosongkan barisnya.';
            }
        }
        $d['tanya'] = json_encode($tanya, JSON_UNESCAPED_UNICODE);
    }

    // Alamat halaman: produk baru boleh bebas; produk lama hanya kalau dibuka kuncinya.
    $slugKiriman = strtolower(masukan('slug'));
    if (!$lama) {
        $d['slug'] = $slugKiriman !== '' ? $slugKiriman : slugkan($d['nama']);
    } elseif ($slugKiriman !== '' && $slugKiriman !== $lama['slug']) {
        $d['slug'] = $slugKiriman;
    }

    /* ---- periksa ---- */
    if ($d['nama'] === '') {
        $galat[] = 'Nama produk wajib diisi.';
    }
    if ($d['slug'] === '' && $d['nama'] === '') {
        // Alamat terisi dari nama — cukup satu pesan soal nama.
    } elseif (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $d['slug']) || strlen($d['slug']) > 80) {
        $galat[] = 'Alamat halaman hanya boleh huruf kecil, angka, dan tanda hubung (mis. kelas-dashboard).';
    } elseif (ambilNilai('SELECT 1 FROM produk WHERE slug = ? AND id <> ?', [$d['slug'], $id]) !== null) {
        $galat[] = 'Alamat halaman "' . $d['slug'] . '" sudah dipakai produk lain.';
    }
    if (in_array($d['jenis'], ['sekali', 'langganan'], true) && ($d['harga'] === null || $d['harga'] <= 0) && $d['status'] === 'aktif') {
        $galat[] = $d['jenis'] === 'langganan'
            ? 'Isi harga per bulan sebelum menayangkan — pembeli membayar bulan pertama lewat checkout.'
            : 'Isi harga sebelum menayangkan — pembeli membayarnya lewat checkout.';
    }
    if ($d['jenis'] === 'eksternal' && $d['url_eksternal'] !== '' && !preg_match('#^https?://[^\s]+\.[^\s]+#i', $d['url_eksternal'])) {
        $galat[] = 'Alamat aplikasi harus lengkap dengan https:// (mis. https://amanafinance.id).';
    }
    if ($d['jenis'] === 'eksternal' && $d['url_eksternal'] === '' && $d['status'] === 'aktif') {
        $galat[] = 'Isi alamat aplikasi sebelum menayangkan — tombol di landing page mengarah ke sana.';
    }
    if ($d['url_admin'] !== '' && !preg_match('#^https?://[^\s]+\.[^\s]+#i', $d['url_admin'])) {
        $galat[] = 'Link panel admin harus lengkap dengan https:// (mis. https://app.ponpesmanager.id/admin).';
    }

    /* ---- unggahan ---- */
    $lpBaru = null;
    $adaUnggahLp = $d['lp_mode'] === 'custom' && !empty(array_filter((array) ($_FILES['lp_berkas']['name'] ?? [])));
    if (!$galat && $adaUnggahLp) {
        [$lpBaru, $galatLp] = simpanLandingPage($id, $_FILES['lp_berkas']);
        if ($galatLp) {
            $galat[] = 'Landing page: ' . $galatLp;
        }
    }
    if ($d['lp_mode'] === 'custom' && !$lpBaru && empty($p['lp_berkas']) && !$galat) {
        $galat[] = 'Pilih berkas HTML (atau .zip) untuk landing page custom — atau pilih Tampilan bawaan.';
    }

    $gambarBaru = null;
    if (!$galat) {
        $galatGambar = null;
        $gambarBaru = simpanGambarProduk($_FILES['gambar'] ?? [], $d['slug'], $galatGambar);
        if ($galatGambar) {
            $galat[] = $galatGambar;
        }
    }

    if (!$galat) {
        if ($gambarBaru !== null) {
            buangGambarProduk($p['gambar']);
            $d['gambar'] = $gambarBaru;
        }
        if ($lpBaru !== null) {
            $d['lp_berkas'] = $lpBaru;
            $d['lp_diunggah_pada'] = date('Y-m-d H:i:s');
        }
        $isi = [
            $d['slug'], $d['nama'], $d['jenis'], $d['kategori'], $d['tagline'] ?: null, $d['ringkas'] ?: null,
            $d['isi'] ?: null, $d['manfaat'] ?: null, $d['tanya'], $d['gambar'], $d['harga'],
            $d['url_eksternal'] ?: null, $d['url_admin'] ?: null, $d['label_tombol'] ?: null,
            $d['lp_mode'], $d['lp_berkas'], $d['lp_diunggah_pada'], $d['status'], $d['urutan'],
        ];
        if ($lama) {
            q(
                'UPDATE produk SET slug = ?, nama = ?, jenis = ?, kategori = ?, tagline = ?, ringkas = ?, isi = ?, manfaat = ?,
                        tanya = ?, gambar = ?, harga = ?, url_eksternal = ?, url_admin = ?, label_tombol = ?,
                        lp_mode = ?, lp_berkas = ?, lp_diunggah_pada = ?, status = ?, urutan = ?, diperbarui_pada = NOW()
                  WHERE id = ?',
                array_merge($isi, [$id])
            );
            if ($lpBaru !== null && $lama['lp_berkas'] && $lama['lp_berkas'] !== $lpBaru) {
                hapusFolderLp($lama['lp_berkas']);
            }
            catatLog('ubah produk', $d['nama'] . ($lama['slug'] !== $d['slug'] ? ' (alamat ' . $lama['slug'] . ' → ' . $d['slug'] . ')' : ''));
        } else {
            q(
                'INSERT INTO produk (slug, nama, jenis, kategori, tagline, ringkas, isi, manfaat, tanya, gambar, harga,
                                     url_eksternal, url_admin, label_tombol, lp_mode, lp_berkas, lp_diunggah_pada, status, urutan,
                                     affiliate_aktif, fee_jenis, fee_nilai, dibuat_pada, diperbarui_pada)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, \'persen\', 0, NOW(), NOW())',
                $isi
            );
            $id = (int) db()->lastInsertId();
            catatLog('tambah produk', $d['nama']);
        }

        $g = terbitkanProduk();
        if ($g) {
            pesan('Produk tersimpan, tapi landing page gagal diperbarui: ' . $g, 'peringatan');
        } elseif ($d['status'] === 'aktif') {
            pesan('Tersimpan dan tayang di ' . preg_replace('#^https?://#', '', urlSitus()) . '/p/' . $d['slug'] . '.'
                . ($lpBaru ? ' Desain custom sudah dipasang.' : ''));
        } else {
            pesan('Tersimpan sebagai ' . strtolower(STATUS_PRODUK[$d['status']]) . ' — belum tampil di situs.'
                . ($lpBaru ? ' Desain custom sudah dipasang; lihat lewat Pratinjau.' : ''));
        }
        pergi(tautan('produk/' . $id));
    }

    // Ada yang perlu diperbaiki: tampilkan lagi form dengan isian yang sudah diketik.
    $p = $d;
    $kategori = $d['kategori'];
    if ($gambarBaru !== null) {
        buangGambarProduk($gambarBaru);
    }
    if ($lpBaru !== null) {
        hapusFolderLp($lpBaru);
    }
}

$tanya = tanyaProduk($p);
$ringkasan = $lama ? ambilSatu(
    "SELECT COUNT(*) AS terjual, COALESCE(SUM(jumlah), 0) AS omzet FROM transaksi WHERE produk_id = ? AND status = 'lunas'",
    [$id]
) : null;
$urlHalaman = urlSitus() . '/p/' . ($lama['slug'] ?? '');
$hargaTampil = $p['harga'] !== null && $p['harga'] !== '' ? number_format((int) $p['harga'], 0, ',', '.') : '';
$berkasLpKini = $lama ? berkasLp($lama['lp_berkas']) : [];
$urlPratinjau = $lama && $lama['lp_berkas'] ? $urlHalaman . '?pratinjau=' . tokenPratinjauLp($lama) : '';
$jenisBoleh = JENIS_PER_KATEGORI[$kategori];

$judul = $lama ? $lama['nama'] : KATEGORI_PRODUK[$kategori] . ' baru';
$menu  = 'produk';
require __DIR__ . '/inc/kepala.php';
?>

<p class="remah"><a href="<?= tautan('produk') ?><?= $kategori === 'kelas' ? '?kategori=kelas' : '' ?>">&larr; Semua produk</a></p>

<?php if ($kategori === 'kelas' && $kelasTaut): ?>
  <nav class="tab-produk" aria-label="Bagian kelas">
    <a class="is-on" aria-current="page" href="<?= tautan('produk/' . $id) ?>">Penjualan &amp; landing page</a>
    <a href="<?= tautan('kelas/' . (int) $kelasTaut['id']) ?>">Isi kelas <span class="teks-kecil">(identitas, galeri, modul &amp; materi)</span></a>
  </nav>
<?php endif; ?>

<?php if ($galat): ?>
  <div class="pita pita-bahaya" role="alert">
    <span><strong>Belum tersimpan.</strong> Perbaiki dulu:
      <?php foreach (array_unique($galat) as $g): ?><br>• <?= e($g) ?><?php endforeach; ?>
    </span>
  </div>
<?php endif; ?>

<div class="dua-kolom-lebar">

<form method="post" enctype="multipart/form-data" id="form-produk" data-jaga novalidate>
  <?= csrfInput() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <?php if (!$lama): ?><input type="hidden" name="kategori" value="<?= e($kategori) ?>"><?php endif; ?>

  <!-- ============ 1. Produk ============ -->
  <section class="kotak">
    <div class="bagian">
      <h2 class="bagian-judul">1. <?= e(KATEGORI_PRODUK[$kategori]) ?></h2>
      <p class="bagian-sub"><?= e(KATEGORI_KETERANGAN[$kategori]) ?></p>

      <div class="form-panel">
        <?php if ($kategori === 'kelas'): ?>
          <div class="bidang">
            <label>Nama</label>
            <p class="isian-tetap"><?= e($p['nama']) ?></p>
            <p class="petunjuk">Sama dengan judul kelas. Ubah di tab <a href="<?= tautan('kelas/' . (int) $p['kelas_id']) ?>">Isi kelas</a>.</p>
          </div>
        <?php else: ?>
          <div class="bidang">
            <label for="f-nama">Nama <?= e(strtolower(KATEGORI_PRODUK[$kategori])) ?></label>
            <input class="isian-besar" id="f-nama" name="nama" type="text" maxlength="160" required
                   value="<?= e($p['nama']) ?>" placeholder="<?= $kategori === 'jasa' ? 'mis. Jasa Pembuatan Aplikasi Sekolah' : 'mis. Ponpes Manager' ?>">
          </div>
          <?php if ($lama): ?>
            <div class="bidang">
              <label for="f-kategori">Kategori</label>
              <select id="f-kategori" name="kategori">
                <option value="produk"<?= $p['kategori'] === 'produk' ? ' selected' : '' ?>>Produk</option>
                <option value="jasa"<?= $p['kategori'] === 'jasa' ? ' selected' : '' ?>>Jasa</option>
              </select>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <div class="bidang">
          <label for="f-slug">Alamat landing page</label>
          <div class="slug-baris">
            <span class="slug-awalan"><?= e(preg_replace('#^https?://#', '', urlSitus())) ?>/p/</span>
            <?php if ($lama): ?>
              <input id="f-slug" name="slug" type="text" value="<?= e($p['slug']) ?>" readonly>
              <button class="tbl tbl-kecil" type="button" data-buka-kunci="#f-slug"
                      data-pesan="Mengubah alamat akan mematikan link lama, termasuk link affiliate yang sudah dibagikan untuk produk ini. Pengunjung dari link lama akan mendarat di beranda. Lanjutkan?">Ubah</button>
            <?php else: ?>
              <input id="f-slug" name="slug" type="text" value="<?= e($p['slug']) ?>" maxlength="80" data-slug-dari="#f-nama" placeholder="terisi otomatis dari nama">
            <?php endif; ?>
          </div>
          <p class="petunjuk"><?= $lama ? 'Terkunci supaya link yang sudah tersebar tidak mati.' : 'Huruf kecil, angka, dan tanda hubung. Bisa diubah sebelum disimpan pertama kali.' ?></p>
        </div>

        <?php if (count($jenisBoleh) > 1): ?>
          <div class="bidang">
            <label>Cara pembeli membayar</label>
            <div class="pilih-kisi">
              <?php foreach ($jenisBoleh as $kunci): ?>
                <label class="pilih-kartu">
                  <input type="radio" name="jenis" value="<?= e($kunci) ?>"<?= $p['jenis'] === $kunci ? ' checked' : '' ?>>
                  <span class="pilih-judul"><?= e(JENIS_PRODUK[$kunci]['label']) ?></span>
                  <span class="pilih-sub"><?= e(JENIS_PRODUK[$kunci]['sub']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
            <?php if ($jumlahTransaksi > 0): ?>
              <p class="petunjuk petunjuk-awas">Produk ini sudah punya transaksi. Mengganti cara bayar tidak mengubah transaksi lama.</p>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <input type="hidden" name="jenis" value="<?= e($jenisBoleh[0]) ?>">
        <?php endif; ?>

        <div class="baris-form">
          <div class="bidang">
            <label for="f-status">Status</label>
            <select id="f-status" name="status">
              <?php foreach (STATUS_PRODUK as $kunci => $label): ?>
                <option value="<?= e($kunci) ?>"<?= $p['status'] === $kunci ? ' selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="petunjuk">Hanya yang <strong>Tayang</strong> punya landing page dan bisa dipesan.</p>
          </div>
          <div class="bidang bidang-kecil">
            <label for="f-urutan">Urutan</label>
            <input id="f-urutan" name="urutan" type="number" value="<?= (int) $p['urutan'] ?>">
          </div>
        </div>

        <?php if ($kategori === 'produk'): ?>
          <div class="bidang">
            <label for="f-admin">Link panel admin aplikasi <span class="teks-kecil">(opsional)</span></label>
            <input id="f-admin" name="url_admin" type="url" value="<?= e((string) $p['url_admin']) ?>" placeholder="https://app.contoh.id/admin">
            <p class="petunjuk">Kalau diisi, muncul sebagai pintasan di Setting → Panel aplikasi.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ============ 2. Harga ============ -->
    <div class="bagian">
      <h2 class="bagian-judul">2. Harga</h2>
      <p class="bagian-sub" data-tampil-jika="jenis=sekali">Dibayar sekali lewat checkout.</p>
      <p class="bagian-sub" data-tampil-jika="jenis=langganan">Harga per bulan. Bulan pertama dibayar lewat checkout, bulan berikutnya dicatat di menu Transaksi.</p>
      <p class="bagian-sub" data-tampil-jika="jenis=penawaran">Opsional. Kalau diisi, tampil sebagai &ldquo;Mulai Rp …&rdquo;. Nilai sebenarnya dicatat saat pembayaran diterima.</p>
      <p class="bagian-sub" data-tampil-jika="jenis=eksternal">Opsional, hanya untuk ditampilkan. Pembayarannya terjadi di aplikasi tujuan.</p>

      <div class="form-panel">
        <div class="bidang">
          <label for="f-harga">Harga</label>
          <div class="isian-imbuh" style="max-width:320px">
            <span class="imbuh imbuh-awal">Rp</span>
            <input id="f-harga" name="harga" type="text" inputmode="numeric" value="<?= e($hargaTampil) ?>" placeholder="249.000">
            <span class="imbuh imbuh-akhir" data-tampil-jika="jenis=langganan">/ bulan</span>
          </div>
          <?php if ($kategori === 'kelas' && $kelasTaut && ($hg = hargaDariTeksKelas((string) ambilNilai('SELECT harga FROM kelas WHERE id = ?', [$kelasTaut['id']]))) !== null && $hg !== (int) $p['harga']): ?>
            <p class="petunjuk petunjuk-awas">Harga di galeri kelas tertulis berbeda. Samakan di tab Isi kelas supaya pengunjung tidak bingung.</p>
          <?php endif; ?>
        </div>

        <div class="bidang" data-tampil-jika="jenis=eksternal">
          <label for="f-url">Alamat aplikasi</label>
          <input id="f-url" name="url_eksternal" type="url" value="<?= e((string) $p['url_eksternal']) ?>" placeholder="https://amanafinance.id">
          <p class="petunjuk">Tombol order mengarah ke sini. Kode affiliate ikut terbawa sebagai <code>?ref=KODE</code>.</p>
        </div>
      </div>
    </div>

    <!-- ============ 3. Landing page ============ -->
    <div class="bagian" id="landing">
      <h2 class="bagian-judul">3. Landing page</h2>
      <p class="bagian-sub">Halaman penjualan di <code>/p/<?= e($p['slug'] ?: 'nama-produk') ?></code>. Link affiliate mitra mengarah ke halaman ini.</p>

      <div class="form-panel">
        <div class="pilih-kisi">
          <label class="pilih-kartu">
            <input type="radio" name="lp_mode" value="bawaan"<?= $p['lp_mode'] !== 'custom' ? ' checked' : '' ?>>
            <span class="pilih-judul">Tampilan bawaan</span>
            <span class="pilih-sub">Isi teks di bawah — tampilannya mengikuti gaya invishar.com. Paling cepat.</span>
          </label>
          <label class="pilih-kartu">
            <input type="radio" name="lp_mode" value="custom"<?= $p['lp_mode'] === 'custom' ? ' checked' : '' ?>>
            <span class="pilih-judul">Desain sendiri (unggah HTML)</span>
            <span class="pilih-sub">Pakai halaman penjualan buatan sendiri — dari Claude Design, Canva, atau desainer.</span>
          </label>
        </div>

        <!-- desain custom -->
        <div class="lp-custom" data-tampil-jika="lp_mode=custom">
          <?php if ($berkasLpKini): ?>
            <div class="lp-terpasang">
              <div>
                <strong>Desain terpasang</strong>
                <span class="teks-kecil">diunggah <?= e(waktuIndo((string) $lama['lp_diunggah_pada'])) ?> · <?= count($berkasLpKini) ?> berkas</span>
                <?php if (!lpPakaiOrder($lama['lp_berkas'])): ?>
                  <p class="petunjuk petunjuk-awas">HTML ini belum memakai <code>{{ORDER}}</code> — pastikan tombol pesannya mengarah ke
                    <code>/order/<?= e($lama['slug']) ?></code>, atau ganti link tombolnya dengan <code>{{ORDER}}</code> lalu unggah ulang.</p>
                <?php endif; ?>
              </div>
              <div class="aksi-kisi">
                <a class="tbl tbl-kecil" href="<?= e($urlPratinjau) ?>" target="_blank" rel="noopener">Pratinjau ↗</a>
                <button class="tbl tbl-kecil tbl-bahaya" type="submit" name="aksi" value="hapus_lp" formnovalidate
                        data-pastikan="Hapus desain custom dan kembali ke tampilan bawaan?">Hapus desain</button>
              </div>
            </div>
            <details class="lp-daftar">
              <summary>Lihat berkas</summary>
              <ul>
                <?php foreach ($berkasLpKini as $jalur => $ukuran): ?>
                  <li><code><?= e($jalur) ?></code> <span class="teks-kecil"><?= $ukuran >= 1048576 ? round($ukuran / 1048576, 1) . ' MB' : max(1, (int) round($ukuran / 1024)) . ' KB' ?></span></li>
                <?php endforeach; ?>
              </ul>
            </details>
          <?php endif; ?>

          <div class="bidang">
            <label for="f-lp"><?= $berkasLpKini ? 'Ganti dengan desain baru' : 'Berkas desain' ?></label>
            <div class="unggah">
              <input id="f-lp" name="lp_berkas[]" type="file" multiple
                     accept=".html,.htm,.zip,.css,.js,.svg,.woff,.woff2,.ttf,.otf,.mp4,.webm,image/*">
              <label class="unggah-tombol" for="f-lp">Pilih berkas&hellip;</label>
              <span class="unggah-nama">Satu .zip, atau satu .html + gambar/CSS-nya (pilih sekaligus). Maks. <?= e(batasUnggahServer()) ?> sekali kirim.</span>
            </div>
          </div>

          <div class="lp-panduan">
            <p><strong>Supaya tombol order bekerja</strong>, arahkan tombol pesan/beli di HTML ke <code>{{ORDER}}</code>:</p>
            <pre><code>&lt;a href="{{ORDER}}"&gt;Pesan sekarang&lt;/a&gt;</code></pre>
            <p>Penanda lain: <code>{{HARGA}}</code> (mis. <?= e(teksHargaProduk($p) ?: 'Rp 249.000') ?>) dan <code>{{NAMA}}</code>.
              Gambar cukup ditulis dengan nama berkasnya (<code>src="foto.jpg"</code>).</p>
            <p class="teks-kecil">Demi keamanan panel, halaman dijalankan terpisah: skrip boleh, tapi tidak bisa memakai
              <code>localStorage</code>/cookie. <a href="<?= tautan('lp-contoh') ?>">Unduh contoh HTML</a> untuk memulai.</p>
          </div>
        </div>

        <!-- isi tampilan bawaan -->
        <div class="form-panel" data-tampil-jika="lp_mode=bawaan">
          <div class="bidang">
            <label for="f-tagline">Kalimat utama</label>
            <input id="f-tagline" name="tagline" type="text" maxlength="200" value="<?= e((string) $p['tagline']) ?>"
                   placeholder="mis. Administrasi pesantren dalam satu tempat">
          </div>
          <div class="bidang">
            <label for="f-ringkas">Ringkasan</label>
            <textarea id="f-ringkas" name="ringkas" rows="2" placeholder="Satu–dua kalimat: untuk siapa dan apa hasilnya."><?= e((string) $p['ringkas']) ?></textarea>
          </div>
          <div class="bidang">
            <label for="f-manfaat">Yang didapat</label>
            <textarea id="f-manfaat" name="manfaat" rows="4" placeholder="Satu butir per baris"><?= e((string) $p['manfaat']) ?></textarea>
            <p class="petunjuk">Tampil sebagai daftar centang. Satu butir per baris.</p>
          </div>
          <div class="bidang">
            <label for="f-isi">Penjelasan lengkap <span class="teks-kecil">(opsional)</span></label>
            <textarea id="f-isi" name="isi" rows="5" placeholder="Pisahkan paragraf dengan satu baris kosong."><?= e((string) $p['isi']) ?></textarea>
          </div>
          <div class="bidang">
            <label for="f-label">Tulisan tombol <span class="teks-kecil">(opsional)</span></label>
            <input id="f-label" name="label_tombol" type="text" maxlength="40" value="<?= e((string) $p['label_tombol']) ?>"
                   placeholder="<?= e(LABEL_TOMBOL_BAWAAN[$p['jenis']] ?? 'Beli sekarang') ?>">
          </div>
          <div>
            <div class="label-baris label-bagian"><h3>Tanya jawab</h3></div>
            <div class="ulang" id="ulang-tanya" data-templat="#templat-tanya">
              <?php foreach ($tanya ?: [[]] as $t): ?>
                <div class="ulang-baris ulang-tegak">
                  <div class="bidang"><input name="tanya_q[]" type="text" placeholder="Pertanyaan" value="<?= e($t['q'] ?? '') ?>"></div>
                  <div class="bidang"><textarea name="tanya_a[]" rows="2" placeholder="Jawaban"><?= e($t['a'] ?? '') ?></textarea></div>
                  <button class="ulang-buang" type="button" aria-label="Hapus baris">&times;</button>
                </div>
              <?php endforeach; ?>
            </div>
            <button class="tbl tbl-kecil" type="button" data-tambah="#ulang-tanya">+ Tambah tanya jawab</button>
          </div>
        </div>

        <div class="bidang">
          <label for="f-gambar">Gambar produk</label>
          <div class="unggah">
            <input id="f-gambar" name="gambar" type="file" accept="image/jpeg,image/png,image/webp" data-gambar>
            <label class="unggah-tombol" for="f-gambar">Pilih gambar&hellip;</label>
            <span class="unggah-nama"><?= $p['gambar'] ? 'Terpasang: ' . e($p['gambar']) : 'JPG, PNG, atau WebP, maks. 3 MB' ?></span>
          </div>
          <p class="petunjuk">Tampil di kartu produk, checkout, dan landing page bawaan.</p>
          <?php if ($lama && $lama['gambar']): ?>
            <button class="tbl tbl-kecil tbl-bahaya" type="submit" name="aksi" value="hapus_gambar" formnovalidate
                    data-pastikan="Hapus gambar produk ini?" style="margin-top:8px">Hapus gambar</button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <template id="templat-tanya">
    <div class="ulang-baris ulang-tegak">
      <div class="bidang"><input name="tanya_q[]" type="text" placeholder="Pertanyaan"></div>
      <div class="bidang"><textarea name="tanya_a[]" rows="2" placeholder="Jawaban"></textarea></div>
      <button class="ulang-buang" type="button" aria-label="Hapus baris">&times;</button>
    </div>
  </template>
</form>

<aside>
  <?php if ($lama): ?>
    <section class="kotak">
      <div class="kotak-kepala">
        <h2>Ringkasan</h2>
        <span class="tanda tanda-<?= $lama['status'] === 'aktif' ? 'tayang' : e($lama['status']) ?>"><?= e(STATUS_PRODUK[$lama['status']]) ?></span>
      </div>
      <dl class="keadaan">
        <dt>Landing page</dt>
        <dd>
          <?php if ($lama['status'] === 'aktif'): ?>
            <div class="salin-baris">
              <code><?= e($urlHalaman) ?></code>
              <button class="tbl-salin" type="button" data-salin="<?= e($urlHalaman) ?>">Salin</button>
            </div>
            <p class="petunjuk"><a href="<?= e($urlHalaman) ?>" target="_blank" rel="noopener">Buka halaman ↗</a>
              · <?= $lama['lp_mode'] === 'custom' ? 'desain custom' : 'tampilan bawaan' ?></p>
          <?php else: ?>
            <span class="teks-kecil">Belum tayang.<?= $urlPratinjau ? '' : ' Ubah status menjadi Tayang untuk membukanya.' ?></span>
            <?php if ($urlPratinjau): ?><p class="petunjuk"><a href="<?= e($urlPratinjau) ?>" target="_blank" rel="noopener">Pratinjau desain custom ↗</a></p><?php endif; ?>
          <?php endif; ?>
        </dd>
        <dt>Formulir order</dt>
        <dd><code class="teks-kecil"><?= e(preg_replace('#^https?://#', '', urlSitus()) . urlOrderProduk($lama)) ?></code></dd>
        <dt>Terjual</dt>
        <dd><?= (int) $ringkasan['terjual'] ?> transaksi lunas · <?= e(rupiah((int) $ringkasan['omzet'])) ?></dd>
        <dt>Afiliasi</dt>
        <dd>
          <?= (int) $lama['affiliate_aktif'] ? 'Komisi ' . e(teksKomisiProduk($lama)) : 'Belum dibuka untuk mitra' ?>
          <br><a class="teks-kecil" href="<?= tautan('produk-affiliate') ?>#p<?= (int) $id ?>">Atur di Produk afiliasi &rarr;</a>
        </dd>
        <dt>Terakhir diubah</dt>
        <dd><?= e(waktuIndo($lama['diperbarui_pada'])) ?></dd>
      </dl>
      <?php if ($jumlahTransaksi > 0): ?>
        <p class="petunjuk"><a href="<?= tautan('transaksi') ?>?produk=<?= (int) $id ?>">Lihat transaksi produk ini &rarr;</a></p>
      <?php endif; ?>
    </section>

    <?php if ($kategori !== 'kelas'): ?>
      <section class="kotak kotak-bahaya">
        <div class="kotak-kepala"><h2>Hapus produk</h2></div>
        <?php if ($jumlahTransaksi > 0): ?>
          <p class="teks-kecil">Sudah punya transaksi, jadi tidak bisa dihapus supaya catatan uang tetap utuh.
             Ubah status menjadi <strong>Diarsipkan</strong> untuk menurunkannya dari situs.</p>
        <?php else: ?>
          <p class="teks-kecil">Landing page ikut hilang, dan link affiliate untuk produk ini akan mengarah ke beranda.</p>
          <form method="post">
            <?= csrfInput() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <button class="tbl tbl-kecil tbl-bahaya" type="submit" name="aksi" value="hapus"
                    data-pastikan="Hapus produk <?= e($lama['nama']) ?>? Tidak bisa dibatalkan.">Hapus produk</button>
          </form>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  <?php else: ?>
    <section class="kotak">
      <div class="kotak-kepala"><h2>Setelah disimpan</h2></div>
      <ul class="garis-waktu">
        <li><span class="gw-judul">Status Draf</span><span class="gw-sub">Belum tampil di situs. Aman untuk dicoba-coba.</span></li>
        <li><span class="gw-judul">Status Tayang</span><span class="gw-sub">Landing page aktif dan bisa dipesan.</span></li>
        <li><span class="gw-judul">Produk afiliasi</span><span class="gw-sub">Atur komisi kalau ingin dipromosikan mitra.</span></li>
      </ul>
    </section>
  <?php endif; ?>
</aside>

</div>

<div class="bilah-simpan">
  <div class="bilah-simpan-isi">
    <p class="bilah-kabar" id="bilah-kabar">
      <?php if ($lama): ?>
        <span class="titik-hijau"></span> Tersimpan — perubahan langsung tayang saat disimpan
      <?php else: ?>
        <?= e(KATEGORI_PRODUK[$kategori]) ?> baru belum tersimpan
      <?php endif; ?>
    </p>
    <a class="tbl" href="<?= tautan('produk') ?>">Batal</a>
    <button class="tbl tbl-utama" type="submit" form="form-produk"><?= $lama ? 'Simpan perubahan' : 'Simpan' ?></button>
  </div>
</div>

<?php require __DIR__ . '/inc/kaki.php'; ?>
