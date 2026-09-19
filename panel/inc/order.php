<?php
declare(strict_types=1);

/* =============================================================================
   Order gabungan — menu Transaksi.

   Dua sumber pesanan ditampilkan sebagai satu daftar:
     trx   transaksi dari checkout (dan pembayaran yang dicatat manual)
     jasa  permintaan jasa dari form (tabel order_jasa)

   Setiap pesanan punya dua status yang terpisah:
     proses  Baru → Diproses → Selesai / Dibatalkan — diubah admin
     bayar   Menunggu / Lunas / … — berubah sendiri dari pembayaran

   Pembayaran untuk order jasa (transaksi.order_jasa_id) tidak tampil sebagai
   baris sendiri; ia menjadi status bayar order jasanya.
   ============================================================================= */

require_once __DIR__ . '/gerbang.php';

const STATUS_PROSES = [
    'baru'     => 'Baru',
    'diproses' => 'Diproses',
    'selesai'  => 'Selesai',
    'batal'    => 'Dibatalkan',
];

/* Status bayar di daftar gabungan: status transaksi + "belum ditagih" untuk
   order jasa yang belum punya pembayaran. */
const STATUS_BAYAR = STATUS_TRANSAKSI + ['belum' => 'Belum ditagih'];

/* Order jasa punya tahapan lebih rinci; di daftar gabungan dikelompokkan. */
const PROSES_ORDER_JASA = [
    'baru'       => 'baru',
    'dibalas'    => 'diproses',
    'penawaran'  => 'diproses',
    'dikerjakan' => 'diproses',
    'selesai'    => 'selesai',
    'batal'      => 'batal',
];

/* Langkah berikut order jasa untuk tombol cepat di daftar. */
const LANGKAH_ORDER_JASA = [
    'baru'       => 'dibalas',
    'dibalas'    => 'penawaran',
    'penawaran'  => 'dikerjakan',
    'dikerjakan' => 'selesai',
];

/**
 * Tabel turunan berisi semua pesanan dengan kolom seragam. Dipakai sebagai
 * "FROM (…) o" supaya saringan dan urutan berlaku sama untuk keduanya.
 */
function sqlOrderGabungan(): string
{
    return "(
        SELECT 'trx' AS tipe, t.id, t.kode_order AS kode, t.dibuat_pada, t.pembeli_nama AS nama, t.whatsapp, t.surel,
               t.produk_id, p.nama AS produk_nama, p.kategori AS produk_kategori, p.jenis AS produk_jenis, t.periode_ke,
               t.affiliate_id, a.kode AS affiliate_kode, a.nama AS affiliate_nama, t.beli_sendiri,
               t.jumlah, t.status AS status_bayar, t.status_proses, CAST(NULL AS CHAR) AS status_jasa,
               CAST(NULL AS CHAR) AS kebutuhan, t.gerbang
          FROM transaksi t
          JOIN produk p ON p.id = t.produk_id
          LEFT JOIN affiliate a ON a.id = t.affiliate_id
         WHERE t.order_jasa_id IS NULL
        UNION ALL
        SELECT 'jasa', o.id, CONCAT('JASA-', LPAD(o.id, 4, '0')), o.dibuat_pada, o.nama, o.whatsapp, o.surel,
               o.produk_id, COALESCE(p.nama, 'Permintaan jasa'), COALESCE(p.kategori, 'jasa'), COALESCE(p.jenis, 'penawaran'), 1,
               o.affiliate_id, a.kode, a.nama, 0,
               COALESCE((SELECT SUM(x.jumlah) FROM transaksi x WHERE x.order_jasa_id = o.id AND x.status = 'lunas'), o.nilai),
               CASE
                 WHEN EXISTS (SELECT 1 FROM transaksi x WHERE x.order_jasa_id = o.id AND x.status = 'lunas') THEN 'lunas'
                 WHEN EXISTS (SELECT 1 FROM transaksi x WHERE x.order_jasa_id = o.id AND x.status = 'menunggu') THEN 'menunggu'
                 ELSE 'belum'
               END,
               CASE o.status WHEN 'baru' THEN 'baru' WHEN 'selesai' THEN 'selesai' WHEN 'batal' THEN 'batal' ELSE 'diproses' END,
               o.status, o.kebutuhan, 'form'
          FROM order_jasa o
          LEFT JOIN produk p ON p.id = o.produk_id
          LEFT JOIN affiliate a ON a.id = o.affiliate_id
    ) o";
}

/**
 * Syarat "perlu diproses": pesanan checkout yang sudah lunas tapi belum
 * selesai, dan permintaan jasa yang masih berjalan.
 */
const SQL_PERLU_DIPROSES = "(o.status_proses IN ('baru', 'diproses') AND (o.tipe = 'jasa' OR o.status_bayar = 'lunas'))";

function jumlahPerluDiproses(): int
{
    return (int) ambilNilai('SELECT COUNT(*) FROM ' . sqlOrderGabungan() . ' WHERE ' . SQL_PERLU_DIPROSES);
}

/** Alamat detail satu pesanan. */
function tautanOrder(string $tipe, int $id): string
{
    return tautan(($tipe === 'jasa' ? 'order/' : 'transaksi/') . $id);
}

/**
 * Mengubah status proses sebuah transaksi (bukan status bayarnya).
 * Diproses/Selesai hanya untuk yang sudah lunas. Membatalkan pesanan yang
 * masih menunggu bayar = transaksi dibatalkan (gagal). Membatalkan pesanan
 * lunas tanpa refund hanya menandai; refund diurus lewat tombol refund.
 * @return string|null pesan galat, atau null kalau berhasil
 */
function ubahProsesTransaksi(int $id, string $ke, string $catatan = ''): ?string
{
    if (!isset(STATUS_PROSES[$ke])) {
        return 'Status proses tidak dikenal.';
    }
    $t = ambilSatu('SELECT * FROM transaksi WHERE id = ?', [$id]);
    if (!$t) {
        return 'Transaksi tidak ditemukan.';
    }
    if ($t['status_proses'] === $ke) {
        return null;
    }
    if (in_array($ke, ['diproses', 'selesai'], true) && $t['status'] !== 'lunas') {
        return 'Pesanan ini belum lunas. Tandai lunas dulu, baru diproses.';
    }
    if ($ke === 'batal' && $t['status'] === 'menunggu') {
        // Tagihannya ikut ditutup; status proses menjadi batal lewat jalur yang sama.
        ubahStatusTransaksi($id, 'gagal', 'admin', 'Pesanan dibatalkan admin' . ($catatan !== '' ? ': ' . $catatan : ''));
        return null;
    }
    if ($ke === 'baru' && !in_array($t['status'], ['lunas', 'menunggu'], true)) {
        return 'Pesanan yang pembayarannya gagal atau dikembalikan tidak bisa dibuka lagi.';
    }

    q('UPDATE transaksi SET status_proses = ?, proses_pada = NOW(), diperbarui_pada = NOW() WHERE id = ?', [$ke, $id]);
    q(
        'INSERT INTO transaksi_riwayat (transaksi_id, status_lama, status_baru, sumber, catatan, dibuat_pada)
         VALUES (?, ?, ?, \'admin\', ?, NOW())',
        [$id, $t['status'], $t['status'], mb_substr('Proses: ' . STATUS_PROSES[$t['status_proses']] . ' → ' . STATUS_PROSES[$ke]
            . ($catatan !== '' ? ' · ' . $catatan : ''), 0, 255)]
    );
    catatLog('proses pesanan', $t['kode_order'] . ' → ' . STATUS_PROSES[$ke]);
    return null;
}

/** Mengubah status order jasa, lengkap dengan riwayatnya. */
function ubahStatusOrderJasa(int $id, string $ke, string $catatan = ''): ?string
{
    if (!isset(STATUS_ORDER[$ke])) {
        return 'Status tidak dikenal.';
    }
    $o = ambilSatu('SELECT id, nama, status FROM order_jasa WHERE id = ?', [$id]);
    if (!$o) {
        return 'Order tidak ditemukan.';
    }
    if ($o['status'] === $ke) {
        return null;
    }
    q('UPDATE order_jasa SET status = ?, diperbarui_pada = NOW() WHERE id = ?', [$ke, $id]);
    q(
        'INSERT INTO order_riwayat (order_id, status_lama, status_baru, catatan, dibuat_pada) VALUES (?, ?, ?, ?, NOW())',
        [$id, $o['status'], $ke, $catatan !== '' ? $catatan : null]
    );
    catatLog('ubah status order', $o['nama'] . ' → ' . STATUS_ORDER[$ke]);
    return null;
}
