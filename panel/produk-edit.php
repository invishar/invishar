<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
require __DIR__ . '/inc/produk.php';
wajibMasuk();
wajibPenjualanSiap();

/* =============================================================================
   Tambah / sunting produk. id kosong = produk baru.
   Setiap penyimpanan langsung menulis ulang landing page (data/produk.json).
   ============================================================================= */

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$lama = $id ? ambilSatu('SELECT * FROM produk WHERE id = ?', [$id]) : null;
if ($id && $lama === null) {
    pesan('Produk tidak ditemukan.', 'buruk');
    pergi(tautan('produk'));
}

$kosong = [
    'id' => 0, 'slug' => '', 'nama' => '', 'jenis' => 'sekali', 'kelas_id' => null, 'tagline' => '', 'ringkas' => '',
    'isi' => '', 'manfaat' => '', 'tanya' => '[]', 'gambar' => null, 'harga' => null, 'url_eksternal' => '',
    'label_tombol' => '', 'status' => 'draf', 'urutan' => 0, 'affiliate_aktif' => 0, 'fee_jenis' => 'persen',
    'fee_nilai' => '0', 'fee_bulan_berulang' => null,
];
$p = $lama ?? $kosong;
$galat = [];

$jumlahTransaksi = $id ? (int) ambilNilai('SELECT COUNT(*) FROM transaksi WHERE produk_id = ?', [$id]) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $aksi = masukan('aksi', 'simpan');

    /* ---- hapus ---- */
    if ($aksi === 'hapus' && $lama) {
        if ($jumlahTransaksi > 0) {
            pesan('Produk ini sudah punya ' . $jumlahTransaksi . ' transaksi, jadi tidak bisa dihapus. Ubah statusnya menjadi Diarsipkan.', 'buruk');
            pergi(tautan('produk/' . $id));
        }
        q('DELETE FROM produk WHERE id = ?', [$id]);
        buangGambarProduk($lama['gambar']);
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

    /* ---- simpan ---- */
    $d = $p;
    $d['nama']          = mb_substr(masukan('nama'), 0, 160);
    $d['jenis']         = isset(JENIS_PRODUK[masukan('jenis')]) ? masukan('jenis') : $p['jenis'];
    $d['status']        = isset(STATUS_PRODUK[masukan('status')]) ? masukan('status') : $p['status'];
    $d['urutan']        = (int) masukan('urutan', '0');
    $d['tagline']       = mb_substr(masukan('tagline'), 0, 200);
    $d['ringkas']       = masukan('ringkas');
    $d['isi']           = masukan('isi');
    $d['manfaat']       = masukan('manfaat');
    $d['label_tombol']  = mb_substr(masukan('label_tombol'), 0, 40);
    $d['url_eksternal'] = mb_substr(masukan('url_eksternal'), 0, 255);
    $d['harga']         = angkaRupiah(masukan('harga'));
    $kelasId            = (int) masukan('kelas_id', '0');
    $d['kelas_id']      = $kelasId > 0 && ambilNilai('SELECT 1 FROM kelas WHERE id = ?', [$kelasId]) ? $kelasId : null;

    $tanya = [];
    foreach ((array) ($_POST['tanya_q'] ?? []) as $i => $q) {
        $q = trim((string) $q);
        $a = trim((string) ($_POST['tanya_a'][$i] ?? ''));
        if ($q !== '' && $a !== '') {
            $tanya[] = ['q' => $q, 'a' => $a];
        } elseif ($q !== '' || $a !== '') {
            $galat[] = 'Setiap tanya jawab butuh pertanyaan dan jawaban. Lengkapi atau kosongkan barisnya.';
        }
    }
    $d['tanya'] = json_encode($tanya, JSON_UNESCAPED_UNICODE);

    // Alamat halaman: produk baru boleh bebas; produk lama hanya kalau dibuka kuncinya.
    $slugKiriman = strtolower(masukan('slug'));
    if (!$lama) {
        $d['slug'] = $slugKiriman !== '' ? $slugKiriman : slugkan($d['nama']);
    } elseif ($slugKiriman !== '' && $slugKiriman !== $lama['slug']) {
        $d['slug'] = $slugKiriman;
    }

    // Setelan affiliate. Kalau dimatikan, isian fee tidak terkirim — nilai lama dipertahankan.
    $d['affiliate_aktif'] = isset($_POST['affiliate_aktif']) ? 1 : 0;
    if ($d['affiliate_aktif']) {
        $d['fee_jenis'] = masukan('fee_jenis') === 'tetap' ? 'tetap' : 'persen';
        if ($d['fee_jenis'] === 'persen') {
            $teksPersen = str_replace(',', '.', str_replace('.', '', masukan('fee_nilai')));
            $d['fee_nilai'] = is_numeric($teksPersen) ? (string) round((float) $teksPersen, 2) : '';
        } else {
            $n = angkaRupiah(masukan('fee_nilai'));
            $d['fee_nilai'] = $n === null ? '' : (string) $n;
        }
        $bulan = trim(masukan('fee_bulan_berulang'));
        $d['fee_bulan_berulang'] = $d['jenis'] === 'langganan' && $bulan !== '' ? (int) $bulan : null;
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
    if (in_array($d['jenis'], ['sekali', 'langganan'], true) && ($d['harga'] === null || $d['harga'] <= 0)) {
        $galat[] = $d['jenis'] === 'langganan'
            ? 'Isi harga per bulan — pembeli membayar bulan pertama lewat checkout.'
            : 'Isi harga — pembeli membayarnya lewat checkout.';
    }
    if ($d['jenis'] === 'eksternal' && !preg_match('#^https?://[^\s]+\.[^\s]+#i', $d['url_eksternal'])) {
        $galat[] = 'Isi alamat aplikasi lengkap dengan https:// (mis. https://amanafinance.id).';
    }
    if ($d['affiliate_aktif']) {
        if ($d['fee_jenis'] === 'persen') {
            if ($d['fee_nilai'] === '' || (float) $d['fee_nilai'] <= 0 || (float) $d['fee_nilai'] > 100) {
                $galat[] = 'Komisi persen harus di antara 0 dan 100.';
            }
        } else {
            if ($d['fee_nilai'] === '' || (int) $d['fee_nilai'] <= 0) {
                $galat[] = 'Isi besar komisi tetap dalam rupiah.';
            } elseif ($d['harga'] !== null && (int) $d['fee_nilai'] > $d['harga']) {
                $galat[] = 'Komisi tetap (' . rupiah((int) $d['fee_nilai']) . ') tidak boleh melebihi harga (' . rupiah($d['harga']) . ').';
            }
        }
        if ($d['fee_bulan_berulang'] !== null && ($d['fee_bulan_berulang'] < 1 || $d['fee_bulan_berulang'] > 60)) {
            $galat[] = 'Bulan komisi berulang harus 1–60, atau kosongkan untuk mengikuti setelan umum.';
        }
    }

    /* ---- gambar ---- */
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
        $isi = [
            $d['slug'], $d['nama'], $d['jenis'], $d['kelas_id'], $d['tagline'] ?: null, $d['ringkas'] ?: null,
            $d['isi'] ?: null, $d['manfaat'] ?: null, $d['tanya'], $d['gambar'], $d['harga'],
            $d['url_eksternal'] ?: null, $d['label_tombol'] ?: null, $d['status'], $d['urutan'],
            $d['affiliate_aktif'], $d['fee_jenis'], $d['fee_nilai'] === '' ? '0' : $d['fee_nilai'], $d['fee_bulan_berulang'],
        ];
        if ($lama) {
            q(
                'UPDATE produk SET slug = ?, nama = ?, jenis = ?, kelas_id = ?, tagline = ?, ringkas = ?, isi = ?, manfaat = ?,
                        tanya = ?, gambar = ?, harga = ?, url_eksternal = ?, label_tombol = ?, status = ?, urutan = ?,
                        affiliate_aktif = ?, fee_jenis = ?, fee_nilai = ?, fee_bulan_berulang = ?, diperbarui_pada = NOW()
                  WHERE id = ?',
                array_merge($isi, [$id])
            );
            catatLog('ubah produk', $d['nama'] . ($lama['slug'] !== $d['slug'] ? ' (alamat ' . $lama['slug'] . ' → ' . $d['slug'] . ')' : ''));
        } else {
            q(
                'INSERT INTO produk (slug, nama, jenis, kelas_id, tagline, ringkas, isi, manfaat, tanya, gambar, harga,
                                     url_eksternal, label_tombol, status, urutan, affiliate_aktif, fee_jenis, fee_nilai,
                                     fee_bulan_berulang, dibuat_pada, diperbarui_pada)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                $isi
            );
            $id = (int) db()->lastInsertId();
            catatLog('tambah produk', $d['nama']);
        }

        $g = terbitkanProduk();
        if ($g) {
            pesan('Produk tersimpan, tapi landing page gagal diperbarui: ' . $g, 'peringatan');
        } elseif ($d['status'] === 'aktif') {
            pesan('Tersimpan dan langsung tayang di ' . preg_replace('#^https?://#', '', urlSitus()) . '/p/' . $d['slug'] . '.');
        } else {
            pesan('Tersimpan sebagai ' . strtolower(STATUS_PRODUK[$d['status']]) . ' — belum tampil di situs.');
        }
        pergi(tautan('produk/' . $id));
    }

    // Ada yang perlu diperbaiki: tampilkan lagi form dengan isian yang sudah diketik.
    $p = $d;
    if ($gambarBaru !== null) {
        buangGambarProduk($gambarBaru);
    }
}

$daftarKelas = ambilSemua('SELECT id, judul FROM kelas ORDER BY urutan, judul');
$tanya = tanyaProduk($p);
$ringkasan = $lama ? ambilSatu(
    "SELECT COUNT(*) AS terjual, COALESCE(SUM(jumlah), 0) AS omzet FROM transaksi WHERE produk_id = ? AND status = 'lunas'",
    [$id]
) : null;
$komisiDibayar = $lama ? (int) ambilNilai("SELECT COALESCE(SUM(jumlah), 0) FROM komisi WHERE produk_id = ? AND status = 'berlaku'", [$id]) : 0;
$urlHalaman = urlSitus() . '/p/' . ($lama['slug'] ?? '');
$hargaTampil = $p['harga'] !== null && $p['harga'] !== '' ? number_format((int) $p['harga'], 0, ',', '.') : '';
$feeTampil = $p['fee_jenis'] === 'tetap'
    ? ((int) $p['fee_nilai'] > 0 ? number_format((int) $p['fee_nilai'], 0, ',', '.') : '')
    : ((float) $p['fee_nilai'] > 0 ? rtrim(rtrim(number_format((float) $p['fee_nilai'], 2, ',', ''), '0'), ',') : '');

$judul = $lama ? $lama['nama'] : 'Produk baru';
$menu  = 'produk';
require __DIR__ . '/inc/kepala.php';
?>

<p class="remah"><a href="<?= tautan('produk') ?>">&larr; Semua produk</a></p>

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

  <!-- ============ 1. Produk ============ -->
  <section class="kotak">
    <div class="bagian">
      <h2 class="bagian-judul">1. Produk</h2>
      <p class="bagian-sub">Nama dan jenisnya menentukan tombol apa yang muncul di landing page.</p>

      <div class="form-panel">
        <div class="bidang">
          <label for="f-nama">Nama produk</label>
          <input class="isian-besar" id="f-nama" name="nama" type="text" maxlength="160" required
                 value="<?= e($p['nama']) ?>" placeholder="mis. Kelas Dashboard Sosial Media">
        </div>

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

        <div class="bidang">
          <label>Jenis produk</label>
          <div class="pilih-kisi">
            <?php foreach (JENIS_PRODUK as $kunci => $j): ?>
              <label class="pilih-kartu">
                <input type="radio" name="jenis" value="<?= e($kunci) ?>"<?= $p['jenis'] === $kunci ? ' checked' : '' ?>>
                <span class="pilih-judul"><?= e($j['label']) ?></span>
                <span class="pilih-sub"><?= e($j['sub']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <?php if ($jumlahTransaksi > 0): ?>
            <p class="petunjuk petunjuk-awas">Produk ini sudah punya transaksi. Mengganti jenisnya tidak mengubah transaksi lama.</p>
          <?php endif; ?>
        </div>

        <div class="baris-form">
          <div class="bidang">
            <label for="f-status">Status</label>
            <select id="f-status" name="status">
              <?php foreach (STATUS_PRODUK as $kunci => $label): ?>
                <option value="<?= e($kunci) ?>"<?= $p['status'] === $kunci ? ' selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="petunjuk">Hanya yang <strong>Tayang</strong> punya landing page dan bisa dibeli.</p>
          </div>
          <div class="bidang bidang-kecil">
            <label for="f-urutan">Urutan</label>
            <input id="f-urutan" name="urutan" type="number" value="<?= (int) $p['urutan'] ?>">
          </div>
        </div>
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
        <div class="baris-form">
          <div class="bidang">
            <label for="f-harga">Harga</label>
            <div class="isian-imbuh">
              <span class="imbuh imbuh-awal">Rp</span>
              <input id="f-harga" name="harga" type="text" inputmode="numeric" value="<?= e($hargaTampil) ?>" placeholder="249.000">
              <span class="imbuh imbuh-akhir" data-tampil-jika="jenis=langganan">/ bulan</span>
            </div>
          </div>
          <div class="bidang" data-tampil-jika="jenis=sekali">
            <label for="f-kelas">Tautkan ke kelas <span class="teks-kecil">(opsional)</span></label>
            <select id="f-kelas" name="kelas_id">
              <option value="0">— Tidak ditautkan —</option>
              <?php foreach ($daftarKelas as $k): ?>
                <option value="<?= (int) $k['id'] ?>"<?= (int) $p['kelas_id'] === (int) $k['id'] ? ' selected' : '' ?>><?= e($k['judul']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="bidang" data-tampil-jika="jenis=eksternal">
          <label for="f-url">Alamat aplikasi</label>
          <input id="f-url" name="url_eksternal" type="url" value="<?= e((string) $p['url_eksternal']) ?>" placeholder="https://amanafinance.id">
          <p class="petunjuk">Tombol di landing page mengarah ke sini. Kode affiliate ikut terbawa sebagai <code>?ref=KODE</code>.</p>
        </div>
      </div>
    </div>

    <!-- ============ 3. Landing page ============ -->
    <div class="bagian">
      <h2 class="bagian-judul">3. Isi landing page</h2>
      <p class="bagian-sub">Yang dibaca calon pembeli di halaman produk. Tulis singkat dan konkret.</p>

      <div class="form-panel">
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

        <div class="baris-form">
          <div class="bidang">
            <label for="f-label">Tulisan tombol <span class="teks-kecil">(opsional)</span></label>
            <input id="f-label" name="label_tombol" type="text" maxlength="40" value="<?= e((string) $p['label_tombol']) ?>"
                   placeholder="<?= e(LABEL_TOMBOL_BAWAAN[$p['jenis']] ?? 'Beli sekarang') ?>">
          </div>
          <div class="bidang">
            <label for="f-gambar">Gambar</label>
            <div class="unggah">
              <input id="f-gambar" name="gambar" type="file" accept="image/jpeg,image/png,image/webp" data-gambar>
              <label class="unggah-tombol" for="f-gambar">Pilih gambar&hellip;</label>
              <span class="unggah-nama" id="unggah-nama"><?= $p['gambar'] ? 'Terpasang: ' . e($p['gambar']) : 'JPG, PNG, atau WebP, maks. 3 MB' ?></span>
            </div>
            <?php if ($lama && $lama['gambar']): ?>
              <button class="tbl tbl-kecil tbl-bahaya" type="submit" name="aksi" value="hapus_gambar" formnovalidate
                      data-pastikan="Hapus gambar produk ini?" style="margin-top:8px">Hapus gambar</button>
            <?php endif; ?>
          </div>
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
    </div>

    <!-- ============ 4. Affiliate ============ -->
    <div class="bagian">
      <h2 class="bagian-judul">4. Affiliate</h2>
      <p class="bagian-sub">Kalau dibuka, produk ini muncul di dashboard affiliator lengkap dengan link pribadinya.</p>

      <div class="form-panel">
        <label class="saklar">
          <input type="checkbox" name="affiliate_aktif" value="1"<?= (int) $p['affiliate_aktif'] ? ' checked' : '' ?>>
          <span class="saklar-rel" aria-hidden="true"></span>
          <span class="saklar-teks">Buka untuk affiliate
            <span class="saklar-sub">Matikan kapan saja; komisi yang sudah tercatat tidak berubah.</span>
          </span>
        </label>

        <div class="form-panel" data-tampil-jika="affiliate_aktif=1">
          <div class="bidang">
            <label>Bentuk komisi</label>
            <div class="pilih-kisi">
              <label class="pilih-kartu">
                <input type="radio" name="fee_jenis" value="persen"<?= $p['fee_jenis'] !== 'tetap' ? ' checked' : '' ?>>
                <span class="pilih-judul">Persen dari harga</span>
                <span class="pilih-sub">Mis. 20% — ikut naik kalau harga naik.</span>
              </label>
              <label class="pilih-kartu">
                <input type="radio" name="fee_jenis" value="tetap"<?= $p['fee_jenis'] === 'tetap' ? ' checked' : '' ?>>
                <span class="pilih-judul">Nominal tetap</span>
                <span class="pilih-sub">Mis. Rp 100.000 per penjualan, berapa pun harganya.</span>
              </label>
            </div>
          </div>

          <div class="baris-form">
            <div class="bidang">
              <label for="f-fee-nilai">Besar komisi</label>
              <div class="isian-imbuh">
                <input id="f-fee-nilai" name="fee_nilai" type="text" inputmode="decimal" value="<?= e($feeTampil) ?>" placeholder="20">
                <span class="imbuh imbuh-akhir" id="f-fee-akhiran"><?= $p['fee_jenis'] === 'tetap' ? 'Rp' : '%' ?></span>
              </div>
            </div>
            <div class="bidang" data-tampil-jika="jenis=langganan">
              <label for="f-bulan">Komisi berulang selama</label>
              <div class="isian-imbuh">
                <input id="f-bulan" name="fee_bulan_berulang" type="number" min="1" max="60"
                       value="<?= $p['fee_bulan_berulang'] !== null ? (int) $p['fee_bulan_berulang'] : '' ?>"
                       placeholder="<?= setelanAngka('affiliate.bulan_berulang') ?>">
                <span class="imbuh imbuh-akhir">bulan</span>
              </div>
              <p class="petunjuk">Kosongkan untuk mengikuti setelan umum (<?= setelanAngka('affiliate.bulan_berulang') ?> bulan).</p>
            </div>
          </div>

          <div class="contoh-komisi" data-contoh-komisi>
            <span aria-hidden="true">💡</span><span data-teks></span>
          </div>
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
            <p class="petunjuk"><a href="<?= e($urlHalaman) ?>" target="_blank" rel="noopener">Buka halaman ↗</a></p>
          <?php else: ?>
            <span class="teks-kecil">Belum tayang. Ubah status menjadi Tayang untuk membukanya.</span>
          <?php endif; ?>
        </dd>
        <dt>Terjual</dt>
        <dd><?= (int) $ringkasan['terjual'] ?> transaksi lunas · <?= e(rupiah((int) $ringkasan['omzet'])) ?></dd>
        <dt>Komisi affiliate</dt>
        <dd><?= $komisiDibayar > 0 ? e(rupiah($komisiDibayar)) . ' tercatat' : 'Belum ada' ?></dd>
        <dt>Terakhir diubah</dt>
        <dd><?= e(waktuIndo($lama['diperbarui_pada'])) ?></dd>
      </dl>
      <?php if ($jumlahTransaksi > 0): ?>
        <p class="petunjuk"><a href="<?= tautan('transaksi') ?>?produk=<?= (int) $id ?>">Lihat transaksi produk ini &rarr;</a></p>
      <?php endif; ?>
    </section>

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
  <?php else: ?>
    <section class="kotak">
      <div class="kotak-kepala"><h2>Setelah disimpan</h2></div>
      <ul class="garis-waktu">
        <li><span class="gw-judul">Status Draf</span><span class="gw-sub">Belum tampil di situs. Aman untuk dicoba-coba.</span></li>
        <li><span class="gw-judul">Status Tayang</span><span class="gw-sub">Landing page langsung aktif dan bisa dibeli.</span></li>
        <li><span class="gw-judul">Affiliate dibuka</span><span class="gw-sub">Produk muncul di dashboard semua affiliator aktif.</span></li>
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
        Produk baru belum tersimpan
      <?php endif; ?>
    </p>
    <a class="tbl" href="<?= tautan('produk') ?>">Batal</a>
    <button class="tbl tbl-utama" type="submit" form="form-produk"><?= $lama ? 'Simpan perubahan' : 'Simpan produk' ?></button>
  </div>
</div>

<?php require __DIR__ . '/inc/kaki.php'; ?>
