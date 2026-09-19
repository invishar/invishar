<?php
declare(strict_types=1);

/* =============================================================================
   Link affiliate:  /r/{KODE}            → beranda
                    /r/{KODE}/{produk}   → /p/{produk}
   (ditulis ulang oleh .htaccess di akar situs menjadi toko/r.php?kode=&p=)

   Mencatat klik, menanam cookie inv_ref, lalu mengalihkan. Aturan yang
   dijaga: link yang pernah dibagikan TIDAK PERNAH berujung galat — kode
   salah, produk terhapus, atau basis data bermasalah tetap berakhir di
   halaman yang wajar, hanya tanpa cookie.
   ============================================================================= */

require __DIR__ . '/inc/awal.php';

header('Cache-Control: no-store, max-age=0');

$kode = strtoupper(trim((string) ($_GET['kode'] ?? '')));
$slug = strtolower(trim((string) ($_GET['p'] ?? '')));
$tujuan = '/';

try {
    if (penjualanSiap()) {
        $produk = preg_match('/^[a-z0-9-]{1,80}$/', $slug)
            ? ambilSatu("SELECT * FROM produk WHERE slug = ? AND status = 'aktif'", [$slug])
            : null;
        if ($produk) {
            $tujuan = '/p/' . $produk['slug'];
        }

        $affiliate = affiliateAktifDariKode($kode);
        if ($affiliate) {
            tanamCookieRef($affiliate['kode']);   // klik terakhir menang
            catatKlik($affiliate, $produk);
        }
    }
} catch (Throwable $e) {
    error_log('[invishar r.php] ' . $e->getMessage());
}

header('Location: ' . $tujuan, true, 302);
exit;
