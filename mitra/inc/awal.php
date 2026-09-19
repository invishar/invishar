<?php
declare(strict_types=1);

/* =============================================================================
   Pintu masuk portal mitra (affiliator): invishar.com/mitra/

   Sesi dan akun TERPISAH dari panel admin — nama kuki berbeda dan berlaku
   hanya di jalur /mitra, jadi masuk sebagai affiliator tidak pernah memberi
   akses apa pun ke panel.
   ============================================================================= */

require_once dirname(__DIR__, 2) . '/panel/inc/inti.php';
require_once dirname(__DIR__, 2) . '/panel/inc/affiliate.php';
require_once dirname(__DIR__, 2) . '/panel/inc/produk.php';

header('X-Robots-Tag: noindex, nofollow');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
        'path'     => '/mitra',
    ]);
    session_name('mitrainvishar');
    session_start();
}

/** Alamat aset dengan penanda versi dari waktu ubahnya. */
function asetMitra(string $berkas): string
{
    $penuh = dirname(__DIR__) . '/aset/' . $berkas;
    return tautan('aset/' . $berkas) . '?v=' . (is_file($penuh) ? filemtime($penuh) : 0);
}

/** CSS panel dipakai ulang supaya komponennya (kotak, tabel, tombol) sama persis. */
function asetPanelCss(): string
{
    $penuh = dirname(__DIR__, 2) . '/panel/aset/panel.css';
    return '/panel/aset/panel.css?v=' . (is_file($penuh) ? filemtime($penuh) : 0);
}

function affiliateKini(): ?array
{
    static $a = null;
    if ($a === null && !empty($_SESSION['affiliate_id'])) {
        $a = ambilSatu('SELECT * FROM affiliate WHERE id = ?', [$_SESSION['affiliate_id']]);
        // Hanya akun aktif atau dibekukan yang boleh berada di dalam.
        if ($a === null || !in_array($a['status'], ['aktif', 'dibekukan'], true)) {
            $a = null;
            unset($_SESSION['affiliate_id']);
        }
    }
    return $a;
}

function wajibMitra(): array
{
    if (!penjualanSiap()) {
        http_response_code(503);
        exit('Portal mitra sedang disiapkan. Coba lagi sebentar lagi.');
    }
    $a = affiliateKini();
    if ($a === null) {
        $_SESSION['tujuan'] = $_SERVER['REQUEST_URI'] ?? tautan();
        pergi(tautan('masuk'));
    }
    return $a;
}

/** Akun dibekukan hanya boleh melihat, tidak boleh mengubah apa pun. */
function wajibAktif(array $a): void
{
    if ($a['status'] !== 'aktif') {
        pesan('Akun Anda sedang dibekukan, jadi perubahan belum bisa dilakukan. Hubungi admin Invishar.', 'buruk');
        pergi(tautan());
    }
}

function namaDepan(string $nama): string
{
    return explode(' ', trim($nama))[0] ?: $nama;
}
