<?php
declare(strict_types=1);

/* =============================================================================
   Pintu masuk panel admin: inti bersama + sesi dan login admin.
   Setiap halaman panel diawali dengan:  require __DIR__ . '/inc/awal.php';
   ============================================================================= */

require_once __DIR__ . '/inti.php';

/* ------------------------------------------------------------------ Sesi */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
        'path'     => '/',
    ]);
    session_name('panelinvishar');
    session_start();
}

function penggunaKini(): ?array
{
    static $pengguna = null;
    if ($pengguna === null && !empty($_SESSION['pengguna_id'])) {
        $pengguna = ambilSatu('SELECT * FROM pengguna WHERE id = ?', [$_SESSION['pengguna_id']]);
        if ($pengguna === null) {
            // Akun terhapus tapi sesi masih ada.
            session_destroy();
        }
    }
    return $pengguna;
}

function wajibMasuk(): array
{
    $pengguna = penggunaKini();
    if ($pengguna === null) {
        $_SESSION['tujuan'] = $_SERVER['REQUEST_URI'] ?? 'index.php';
        pergi(tautan('masuk'));
    }
    return $pengguna;
}

/**
 * Halaman penjualan & affiliate butuh tabel dari migrasi. Kalau belum
 * dijalankan, arahkan ke halaman migrasi dengan penjelasan — jangan sampai
 * admin disuguhi galat basis data.
 */
function wajibPenjualanSiap(): void
{
    if (!penjualanSiap()) {
        pesan('Fitur ini butuh pembaruan basis data. Jalankan dulu pembaruannya di bawah.', 'peringatan');
        pergi(tautan('pembaruan'));
    }
}

/* Status order jasa, berikut urutan tampilnya. */
const STATUS_ORDER = [
    'baru'      => 'Baru',
    'dibalas'   => 'Dibalas',
    'penawaran' => 'Penawaran',
    'dikerjakan'=> 'Dikerjakan',
    'selesai'   => 'Selesai',
    'batal'     => 'Batal',
];
