<?php
declare(strict_types=1);

/* =============================================================================
   Landing page produk: invishar.com/p/{slug}

   Produk yang memakai landing page custom → HTML unggahannya (bersandbox,
   lihat panel/inc/halaman.php). Selain itu → templat bawaan produk.html yang
   mengisi dirinya dari data/produk.json.

   ?ref=KODE dialihkan ke /r/KODE/{slug} supaya kliknya tercatat dan cookie
   affiliate tertanam lewat jalur yang sama dengan link affiliate biasa.
   ?pratinjau=TOKEN menampilkan landing page custom produk yang belum Tayang
   (tombol Pratinjau di panel).
   ============================================================================= */

require __DIR__ . '/inc/awal.php';
require_once dirname(__DIR__) . '/panel/inc/halaman.php';

// Landing page boleh diindeks mesin pencari (toko lain tidak).
header_remove('X-Robots-Tag');

$slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
$slugSah = preg_match('/^[a-z0-9-]{1,80}$/', $slug) === 1;

$ref = strtoupper(trim((string) ($_GET['ref'] ?? '')));
if ($slugSah && $ref !== '' && kodeSah($ref)) {
    header('Location: /r/' . rawurlencode($ref) . '/' . $slug, true, 302);
    exit;
}

try {
    $produk = $slugSah && penjualanSiap() ? ambilSatu('SELECT * FROM produk WHERE slug = ?', [$slug]) : null;
} catch (Throwable $e) {
    error_log('[invishar halaman.php] ' . $e->getMessage());
    $produk = null;
}

if ($produk && ($produk['lp_mode'] ?? 'bawaan') === 'custom' && !empty($produk['lp_berkas'])) {
    $tayang = $produk['status'] === 'aktif';
    $pratinjau = hash_equals(tokenPratinjauLp($produk), (string) ($_GET['pratinjau'] ?? ''));
    if ($tayang || $pratinjau) {
        $html = htmlLandingPage($produk);
        if ($html !== null) {
            kirimKepalaSandboxLp();
            if ($pratinjau && !$tayang) {
                header('X-Robots-Tag: noindex, nofollow');
            }
            echo $html;
            exit;
        }
        error_log('[invishar halaman.php] berkas landing page ' . $produk['lp_berkas'] . ' hilang');
    }
}

// Templat bawaan: di server produk.html ada di public_html, di repo di site/.
$templat = is_file(dirname(__DIR__) . '/produk.html') ? dirname(__DIR__) . '/produk.html' : dirname(__DIR__) . '/site/produk.html';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, max-age=0');
readfile($templat);
