<?php
declare(strict_types=1);

/* =============================================================================
   Tombol produk "Aplikasi lain" (mis. amanafinance).
   Mengarahkan ke alamat aplikasinya dan membawa kode affiliate dari cookie
   sebagai ?ref=KODE, supaya aplikasi tujuan bisa mencatatnya. Cookie
   bersifat HttpOnly, jadi hanya server yang bisa membacanya — karena itu
   lewat sini, bukan langsung dari halaman.
   ============================================================================= */

require __DIR__ . '/inc/awal.php';

header('Cache-Control: no-store, max-age=0');

$slug = strtolower(trim((string) ($_GET['p'] ?? '')));
$tujuan = '/';

try {
    $produk = preg_match('/^[a-z0-9-]{1,80}$/', $slug)
        ? ambilSatu("SELECT * FROM produk WHERE slug = ? AND status = 'aktif' AND jenis = 'eksternal'", [$slug])
        : null;

    if ($produk && preg_match('#^https?://#i', (string) $produk['url_eksternal'])) {
        $tujuan = (string) $produk['url_eksternal'];
        $affiliate = (int) $produk['affiliate_aktif'] ? affiliateDariCookie() : null;
        if ($affiliate) {
            $tujuan .= (strpos($tujuan, '?') === false ? '?' : '&') . 'ref=' . rawurlencode($affiliate['kode']);
        }
    }
} catch (Throwable $e) {
    error_log('[invishar keluar.php] ' . $e->getMessage());
}

header('Location: ' . $tujuan, true, 302);
exit;
