<?php
declare(strict_types=1);

/* =============================================================================
   Produk: gambar, teks harga, dan penerbitan landing page.

   Landing page (invishar.com/p/{slug}) membaca data/produk.json. Berkas itu
   ditulis ulang OTOMATIS setiap kali produk disimpan, diarsipkan, atau dihapus
   — tidak ada tombol "Terbitkan" yang bisa terlupa. Checkout dan link
   affiliate membaca basis data langsung, jadi harga selalu yang terbaru.
   ============================================================================= */

require_once __DIR__ . '/affiliate.php';

const LABEL_TOMBOL_BAWAAN = [
    'sekali'    => 'Beli sekarang',
    'langganan' => 'Mulai berlangganan',
    'penawaran' => 'Minta penawaran',
    'eksternal' => 'Kunjungi aplikasi',
];

function folderGambarProduk(): string
{
    return rtrim((string) konfig('situs_data'), '/') . '/produk';
}

/** Folder gambar produk, dibuat bila belum ada; null kalau gagal. */
function siapkanFolderGambarProduk(): ?string
{
    $folder = folderGambarProduk();
    if (!is_dir($folder) && !@mkdir($folder, 0755, true) && !is_dir($folder)) {
        return null;
    }
    // Folder gambar tidak boleh menjalankan skrip, apa pun yang berhasil masuk.
    $jaga = $folder . '/.htaccess';
    if (!is_file($jaga)) {
        file_put_contents($jaga, "<FilesMatch \"\\.(php|phtml|phar|cgi|pl|py)$\">\n  Require all denied\n</FilesMatch>\n");
    }
    return $folder;
}

function urlGambarProduk(?string $berkas): string
{
    return $berkas ? '/data/produk/' . rawurlencode($berkas) : '';
}

/** Harga seperti yang dibaca pengunjung. */
function teksHargaProduk(array $p): string
{
    $harga = $p['harga'] !== null ? (int) $p['harga'] : null;
    switch ($p['jenis']) {
        case 'langganan':
            return $harga !== null ? rupiah($harga) . ' / bulan' : 'Hubungi kami';
        case 'penawaran':
            return $harga !== null ? 'Mulai ' . rupiah($harga) : 'Sesuai kebutuhan';
        case 'eksternal':
            return $harga !== null ? ($harga === 0 ? 'Gratis' : rupiah($harga)) : '';
        default:
            return $harga !== null ? ($harga === 0 ? 'Gratis' : rupiah($harga)) : '';
    }
}

/** Produk ini bisa dibeli lewat checkout invishar.com? */
function bisaCheckout(array $p): bool
{
    return in_array($p['jenis'], ['sekali', 'langganan'], true) && $p['harga'] !== null && (int) $p['harga'] > 0;
}

/** Alamat tombol utama di landing page. Kosong = pakai form penawaran. */
function urlAksiProduk(array $p): string
{
    if (bisaCheckout($p)) {
        return '/toko/checkout.php?p=' . rawurlencode($p['slug']);
    }
    if ($p['jenis'] === 'eksternal' && trim((string) $p['url_eksternal']) !== '') {
        return '/toko/keluar.php?p=' . rawurlencode($p['slug']);
    }
    return '';
}

function tanyaProduk(array $p): array
{
    $isi = json_decode((string) $p['tanya'], true);
    return is_array($isi) ? array_values(array_filter($isi, function ($t) {
        return is_array($t) && trim((string) ($t['q'] ?? '')) !== '' && trim((string) ($t['a'] ?? '')) !== '';
    })) : [];
}

function barisTeks(?string $teks): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $teks) ?: [])));
}

/** Paragraf dipisah baris kosong. */
function paragrafTeks(?string $teks): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R\s*\R/', (string) $teks) ?: [])));
}

/** Tulis lewat berkas sementara supaya pembaca tidak pernah melihat isi separuh. */
function tulisJsonAtomik(string $berkas, array $isi): bool
{
    $sementara = $berkas . '.tmp';
    $teks = json_encode($isi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($teks === false || file_put_contents($sementara, $teks) === false) {
        return false;
    }
    return rename($sementara, $berkas);
}

/**
 * Menulis ulang data/produk.json dari basis data — hanya produk berstatus
 * Tayang. Mengembalikan pesan galat, atau null kalau berhasil.
 */
function terbitkanProduk(): ?string
{
    $tujuan = rtrim((string) konfig('situs_data'), '/');
    if (!is_dir($tujuan) && !@mkdir($tujuan, 0755, true) && !is_dir($tujuan)) {
        return 'Folder terbitan tidak bisa dibuat: ' . $tujuan;
    }

    $daftar = [];
    $semua = ambilSemua(
        "SELECT p.*, k.slug AS kelas_slug FROM produk p LEFT JOIN kelas k ON k.id = p.kelas_id
          WHERE p.status = 'aktif' ORDER BY p.urutan, p.nama"
    );
    foreach ($semua as $p) {
        $daftar[] = [
            'slug'         => $p['slug'],
            'nama'         => $p['nama'],
            'jenis'        => $p['jenis'],
            'jenis_label'  => JENIS_PRODUK[$p['jenis']]['label'] ?? '',
            'kategori'     => $p['kategori'] ?? 'produk',
            'tagline'      => (string) $p['tagline'],
            'ringkas'      => (string) $p['ringkas'],
            'isi'          => paragrafTeks($p['isi']),
            'manfaat'      => barisTeks($p['manfaat']),
            'tanya'        => tanyaProduk($p),
            'gambar'       => urlGambarProduk($p['gambar']),
            'harga'        => $p['harga'] !== null ? (int) $p['harga'] : null,
            'harga_teks'   => teksHargaProduk($p),
            'label_tombol' => trim((string) $p['label_tombol']) !== '' ? $p['label_tombol'] : LABEL_TOMBOL_BAWAAN[$p['jenis']],
            'url_aksi'     => urlAksiProduk($p),
            'kelas'        => $p['kelas_slug'] ? '/course.html?k=' . rawurlencode($p['kelas_slug']) : '',
        ];
    }

    $isi = ['diperbarui' => date('c'), 'produk' => $daftar];
    return tulisJsonAtomik($tujuan . '/produk.json', $isi) ? null : 'Gagal menulis data/produk.json.';
}

/**
 * Menyimpan gambar yang diunggah ke data/produk, mengembalikan nama berkasnya.
 * Jenis berkas ditentukan dari isinya, bukan dari nama kiriman peramban.
 */
function simpanGambarProduk(array $berkas, string $slug, ?string &$galat): ?string
{
    $galat = null;
    $kode = $berkas['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($kode === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($kode !== UPLOAD_ERR_OK) {
        $galat = 'Unggahan gambar gagal. Periksa ukuran berkasnya.';
        return null;
    }
    if (($berkas['size'] ?? 0) > 3 * 1024 * 1024) {
        $galat = 'Gambar lebih dari 3 MB. Perkecil dulu.';
        return null;
    }
    $ukuran = @getimagesize($berkas['tmp_name']);
    $jenis  = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$ukuran || !isset($jenis[$ukuran[2]])) {
        $galat = 'Berkas itu bukan gambar JPG, PNG, atau WebP.';
        return null;
    }

    $folder = siapkanFolderGambarProduk();
    if ($folder === null) {
        $galat = 'Folder gambar tidak bisa dibuat.';
        return null;
    }

    $nama = $slug . '-' . bin2hex(random_bytes(3)) . '.' . $jenis[$ukuran[2]];
    $tujuan = $folder . '/' . $nama;
    $pindah = is_uploaded_file($berkas['tmp_name'])
        ? move_uploaded_file($berkas['tmp_name'], $tujuan)
        : false;
    if (!$pindah) {
        $galat = 'Gambar tidak bisa disimpan ke folder tujuan.';
        return null;
    }
    @chmod($tujuan, 0644);
    return $nama;
}

function buangGambarProduk(?string $berkas): void
{
    if ($berkas) {
        @unlink(folderGambarProduk() . '/' . basename($berkas));
    }
}

/* ---------------------------------------------------------------------------
   Setelan affiliate sebuah produk.

   Dipakai halaman sunting produk dan halaman Produk affiliate, supaya aturan
   komisinya satu. $lama berisi setelan yang sedang tersimpan: kalau affiliate
   dimatikan, isian fee tidak terkirim dan nilai lama dipertahankan (siap
   dipakai lagi saat dinyalakan kembali).
   --------------------------------------------------------------------------- */
function bacaSetelanAffiliate(array $lama, string $jenis, ?int $harga, array &$galat): array
{
    $d = [
        'affiliate_aktif'    => isset($_POST['affiliate_aktif']) ? 1 : 0,
        'fee_jenis'          => $lama['fee_jenis'] ?? 'persen',
        'fee_nilai'          => (string) ($lama['fee_nilai'] ?? '0'),
        'fee_bulan_berulang' => $lama['fee_bulan_berulang'] ?? null,
    ];
    if (!$d['affiliate_aktif']) {
        return $d;
    }

    $d['fee_jenis'] = masukan('fee_jenis') === 'tetap' ? 'tetap' : 'persen';
    if ($d['fee_jenis'] === 'persen') {
        $teksPersen = str_replace(',', '.', str_replace('.', '', masukan('fee_nilai')));
        $d['fee_nilai'] = is_numeric($teksPersen) ? (string) round((float) $teksPersen, 2) : '';
    } else {
        $n = angkaRupiah(masukan('fee_nilai'));
        $d['fee_nilai'] = $n === null ? '' : (string) $n;
    }
    $bulan = trim(masukan('fee_bulan_berulang'));
    $d['fee_bulan_berulang'] = $jenis === 'langganan' && $bulan !== '' ? (int) $bulan : null;

    if ($d['fee_nilai'] === '') {
        $galat[] = 'Isi besar komisi, atau matikan saklar "Buka untuk affiliate".';
    } elseif ($d['fee_jenis'] === 'persen') {
        if ($d['fee_nilai'] === '' || (float) $d['fee_nilai'] <= 0 || (float) $d['fee_nilai'] > 100) {
            $galat[] = 'Komisi persen harus di antara 0 dan 100.';
        }
    } else {
        if ($d['fee_nilai'] === '' || (int) $d['fee_nilai'] <= 0) {
            $galat[] = 'Isi besar komisi tetap dalam rupiah.';
        } elseif ((int) $d['fee_nilai'] < 1000) {
            // "15" dengan pilihan Rupiah tetap hampir pasti maksudnya 15%.
            $galat[] = 'Komisi tetap Rp ' . (int) $d['fee_nilai'] . ' terlalu kecil. Kalau maksudnya persen, pilih "Persen"; kalau rupiah, minimal Rp 1.000.';
        } elseif ($harga !== null && (int) $d['fee_nilai'] > $harga) {
            $galat[] = 'Komisi tetap (' . rupiah((int) $d['fee_nilai']) . ') tidak boleh melebihi harga (' . rupiah($harga) . ').';
        }
    }
    if ($d['fee_bulan_berulang'] !== null && ($d['fee_bulan_berulang'] < 1 || $d['fee_bulan_berulang'] > 60)) {
        $galat[] = 'Bulan komisi berulang harus 1–60, atau kosongkan untuk mengikuti setelan umum.';
    }
    if ($d['fee_nilai'] === '') {
        $d['fee_nilai'] = '0';
    }
    return $d;
}

/** Nilai isian "Besar komisi" seperti yang diketik admin (20 / 12,5 / 100.000). */
function teksIsianFee(array $p): string
{
    if (($p['fee_jenis'] ?? 'persen') === 'tetap') {
        return (int) $p['fee_nilai'] > 0 ? number_format((int) $p['fee_nilai'], 0, ',', '.') : '';
    }
    return (float) $p['fee_nilai'] > 0 ? rtrim(rtrim(number_format((float) $p['fee_nilai'], 2, ',', ''), '0'), ',') : '';
}

/* ---------------------------------------------------------------------------
   Kelas → produk.

   Kelas di menu Kelas hanya berisi materi dan tampilan galeri; harganya teks
   bebas ("Rp 249rb"). Supaya bisa dibeli dan dipromosikan affiliate, kelas
   perlu satu baris produk yang ditautkan lewat produk.kelas_id.
   --------------------------------------------------------------------------- */

/** "Rp 249rb" → 249000, "Rp 1,5jt" → 1500000, "Gratis" → 0; tak terbaca → null. */
function hargaDariTeksKelas(string $teks): ?int
{
    $t = strtolower(trim($teks));
    if ($t === '') {
        return null;
    }
    if (preg_match('/gratis|free/', $t)) {
        return 0;
    }
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*(rb|ribu|k|jt|juta)\b/', $t, $m)) {
        $angka = (float) str_replace(',', '.', $m[1]);
        return (int) round($angka * (in_array($m[2], ['jt', 'juta'], true) ? 1000000 : 1000));
    }
    return angkaRupiah($t);
}

/** Produk yang sudah ditautkan ke kelas ini (yang tayang didahulukan), atau null. */
function produkUntukKelas(int $kelasId): ?array
{
    return ambilSatu(
        "SELECT * FROM produk WHERE kelas_id = ? ORDER BY FIELD(status, 'aktif', 'draf', 'arsip'), id LIMIT 1",
        [$kelasId]
    );
}

/**
 * Membuat produk "sekali bayar" dari sebuah kelas: nama, ringkasan, daftar
 * hasil belajar, tanya jawab, dan gambar sampulnya ikut tersalin sebagai isi
 * awal landing page. Mengembalikan id produk baru.
 */
function buatProdukDariKelas(array $kelas, ?int $harga, string $status = 'draf', array $affiliate = ['affiliate_aktif' => 0, 'fee_jenis' => 'persen', 'fee_nilai' => '0']): int
{
    $detail = json_decode((string) ($kelas['detail'] ?? ''), true) ?: [];
    $hasil = array_values(array_filter(array_map('trim', (array) ($detail['ikhtisar']['hasil'] ?? []))));
    $tanya = [];
    foreach ((array) ($detail['tanya'] ?? []) as $t) {
        if (is_array($t) && trim((string) ($t['q'] ?? '')) !== '' && trim((string) ($t['a'] ?? '')) !== '') {
            $tanya[] = ['q' => trim((string) $t['q']), 'a' => trim((string) $t['a'])];
        }
    }

    // Alamat landing page = slug kelas, ditambah angka kalau sudah terpakai.
    $dasar = slugkan((string) $kelas['slug']) ?: slugkan((string) $kelas['judul']);
    $slug = $dasar;
    for ($i = 2; ambilNilai('SELECT 1 FROM produk WHERE slug = ?', [$slug]) !== null; $i++) {
        $slug = $dasar . '-' . $i;
    }

    q(
        'INSERT INTO produk (slug, nama, jenis, kategori, kelas_id, tagline, ringkas, isi, manfaat, tanya, gambar, harga,
                             url_eksternal, label_tombol, status, urutan, affiliate_aktif, fee_jenis, fee_nilai,
                             fee_bulan_berulang, dibuat_pada, diperbarui_pada)
         VALUES (?, ?, \'sekali\', \'kelas\', ?, NULL, ?, NULL, ?, ?, NULL, ?, NULL, NULL, ?, ?, ?, ?, ?, NULL, NOW(), NOW())',
        [
            $slug, mb_substr((string) $kelas['judul'], 0, 160), (int) $kelas['id'],
            trim((string) $kelas['ringkas']) ?: null,
            $hasil ? implode("\n", $hasil) : null,
            json_encode($tanya, JSON_UNESCAPED_UNICODE),
            $harga, $status, (int) $kelas['urutan'],
            (int) $affiliate['affiliate_aktif'], $affiliate['fee_jenis'], $affiliate['fee_nilai'],
        ]
    );
    $id = (int) db()->lastInsertId();

    // Gambar sampul kelas disalin (bukan dipindah): menghapus salah satunya
    // nanti tidak ikut menghapus yang lain.
    if (!empty($kelas['gambar'])) {
        $asal = rtrim((string) konfig('situs_data'), '/') . '/kelas/' . basename((string) $kelas['gambar']);
        $folder = is_file($asal) ? siapkanFolderGambarProduk() : null;
        if ($folder !== null) {
            $nama = $slug . '-' . bin2hex(random_bytes(3)) . '.' . strtolower(pathinfo($asal, PATHINFO_EXTENSION));
            if (@copy($asal, $folder . '/' . $nama)) {
                q('UPDATE produk SET gambar = ? WHERE id = ?', [$nama, $id]);
            }
        }
    }
    return $id;
}

/**
 * Setiap kelas punya satu baris produk (kategori Kelas), supaya semua yang
 * dijual tampil di satu daftar menu Produk. Kelas yang belum punya dibuatkan
 * sebagai Draf — tidak ada yang tayang otomatis. Mengembalikan jumlah yang dibuat.
 */
function sinkronKelasProduk(): int
{
    $kelasBaru = ambilSemua('SELECT k.* FROM kelas k WHERE NOT EXISTS (SELECT 1 FROM produk p WHERE p.kelas_id = k.id)');
    foreach ($kelasBaru as $k) {
        $harga = hargaDariTeksKelas((string) $k['harga']);
        buatProdukDariKelas($k, $harga, 'draf');
    }
    return count($kelasBaru);
}

/**
 * Kenapa produk ini belum bisa ditayangkan, atau null kalau sudah bisa.
 * Dipakai tombol Tayangkan di daftar produk dan saat menyimpan formulir.
 */
function alasanBelumTayang(array $p): ?string
{
    if (in_array($p['jenis'], ['sekali', 'langganan'], true) && (int) $p['harga'] <= 0) {
        return 'harganya belum diisi';
    }
    if ($p['jenis'] === 'eksternal' && !preg_match('#^https?://[^\s]+\.[^\s]+#i', (string) $p['url_eksternal'])) {
        return 'alamat aplikasinya belum diisi';
    }
    if (($p['lp_mode'] ?? 'bawaan') === 'custom' && empty($p['lp_berkas'])) {
        return 'landing page custom dipilih tapi berkas HTML-nya belum diunggah';
    }
    return null;
}

/** Alamat formulir order produk — tujuan tombol {{ORDER}} di landing page custom. */
function urlOrderProduk(array $p): string
{
    return '/order/' . rawurlencode($p['slug']);
}

/** Gambar untuk kartu di panel: gambar produk, atau sampul kelasnya. */
function gambarKartuProduk(array $p): string
{
    if (!empty($p['gambar'])) {
        return urlGambarProduk($p['gambar']);
    }
    if (!empty($p['kelas_gambar'])) {
        return '/data/kelas/' . rawurlencode((string) $p['kelas_gambar']);
    }
    return '';
}
