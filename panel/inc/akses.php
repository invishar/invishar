<?php
declare(strict_types=1);

/* =============================================================================
   Akses otomatis produk digital & kelas.

   Setiap transaksi lunas mendapat token akses unik. Pembeli membuka
     /toko/akses.php?t={token}
   kapan saja untuk mengambil produk digitalnya (tautan unduhan + instruksi
   dari data produk) atau — untuk produk kelas — daftar materi yang hanya
   bisa dibuka lewat token tersebut (/toko/materi.php).

   Token dibuat tepat saat status menjadi lunas di ubahStatusTransaksi(), jadi
   satu-satunya jalur (webhook, jaring pengaman selesai.php, catat manual)
   otomatis ikut. Transaksi lunas yang lebih tua dari fitur ini mendapat
   tokennya saat halaman selesai/akses pertama kali dibuka (pastikanTokenAkses).

   Refund mencabut akses: baris peserta dihapus dan token dibuang.
   ============================================================================= */

require_once __DIR__ . '/inti.php';

function tokenAksesBaru(): string
{
    do {
        $token = bin2hex(random_bytes(24));
    } while (ambilNilai('SELECT 1 FROM transaksi WHERE akses_token = ?', [$token]) !== null);
    return $token;
}

/**
 * Membuka akses untuk transaksi lunas: pastikan token ada, daftarkan ke kelas
 * bila produknya kelas. Dipanggil di dalam transaksi basis data milik
 * ubahStatusTransaksi(), jadi aman dari notifikasi ganda.
 */
function bukaAksesTransaksi(array $trx): array
{
    if (empty($trx['akses_token'])) {
        $trx['akses_token'] = tokenAksesBaru();
        q('UPDATE transaksi SET akses_token = ? WHERE id = ?', [$trx['akses_token'], $trx['id']]);
    }
    $produk = ambilSatu('SELECT id, kategori, kelas_id FROM produk WHERE id = ?', [$trx['produk_id']]);
    if ($produk && $produk['kategori'] === 'kelas' && !empty($produk['kelas_id'])) {
        q(
            'INSERT IGNORE INTO peserta_kelas (transaksi_id, kelas_id, dibuat_pada) VALUES (?, ?, NOW())',
            [$trx['id'], $produk['kelas_id']]
        );
    }
    return $trx;
}

/** Mencabut akses saat refund: peserta keluar, token dibuang. */
function cabutAksesTransaksi(array $trx): void
{
    q('DELETE FROM peserta_kelas WHERE transaksi_id = ?', [$trx['id']]);
    q('UPDATE transaksi SET akses_token = NULL WHERE id = ?', [$trx['id']]);
}

/**
 * Untuk transaksi lunas lama yang belum punya token (dibuat sebelum fitur
 * ini ada): buatkan sekarang. Aman dipanggil berulang.
 */
function pastikanTokenAkses(array $trx): array
{
    if ($trx['status'] !== 'lunas') {
        return $trx;
    }
    if (!empty($trx['akses_token'])) {
        return $trx;
    }
    return dalamTransaksi(function () use ($trx): array {
        $segar = ambilSatu('SELECT * FROM transaksi WHERE id = ? FOR UPDATE', [$trx['id']]);
        if ($segar && empty($segar['akses_token'])) {
            return bukaAksesTransaksi($segar);
        }
        return $segar ?: $trx;
    });
}

/**
 * Validasi token akses pembeli. Mengembalikan transaksi lunas + produknya,
 * atau null bila token tidak dikenal / transaksinya tidak lunas lagi.
 */
function aksesDariToken(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
        return null;
    }
    $trx = ambilSatu("SELECT * FROM transaksi WHERE akses_token = ? AND status = 'lunas'", [$token]);
    if (!$trx) {
        return null;
    }
    $trx['produk'] = ambilSatu('SELECT * FROM produk WHERE id = ?', [$trx['produk_id']]);
    return $trx;
}

/** Kelas-kelas yang boleh dibuka token ini (terdaftar sebagai peserta). */
function kelasUntukToken(int $transaksiId): array
{
    return ambilSemua(
        'SELECT k.* FROM peserta_kelas p JOIN kelas k ON k.id = p.kelas_id WHERE p.transaksi_id = ?',
        [$transaksiId]
    );
}

/** Apakah token ini boleh membuka materi dari kelas tersebut. */
function bolehBukaKelas(string $token, int $kelasId): bool
{
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
        return false;
    }
    return ambilNilai(
        "SELECT 1 FROM transaksi t JOIN peserta_kelas p ON p.transaksi_id = t.id
          WHERE t.akses_token = ? AND t.status = 'lunas' AND p.kelas_id = ?",
        [$token, $kelasId]
    ) !== null;
}

function urlAksesTransaksi(array $trx): string
{
    return '/toko/akses.php?t=' . $trx['akses_token'];
}
