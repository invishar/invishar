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

    $folder = folderGambarProduk();
    if (!is_dir($folder) && !@mkdir($folder, 0755, true) && !is_dir($folder)) {
        $galat = 'Folder gambar tidak bisa dibuat.';
        return null;
    }
    // Folder gambar tidak boleh menjalankan skrip, apa pun yang berhasil masuk.
    $jaga = $folder . '/.htaccess';
    if (!is_file($jaga)) {
        file_put_contents($jaga, "<FilesMatch \"\\.(php|phtml|phar|cgi|pl|py)$\">\n  Require all denied\n</FilesMatch>\n");
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
