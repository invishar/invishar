<?php
declare(strict_types=1);

/* =============================================================================
   Webhook notifikasi Midtrans.
   Setel di dashboard Midtrans → Settings → Payment → Notification URL:
       https://invishar.com/toko/midtrans.php

   Urutan pemeriksaan — tidak ada yang dipercaya begitu saja:
     1. tanda tangan (sha512 order_id + status_code + gross_amount + server key)
     2. transaksinya ada dan memang lewat Midtrans
     3. status DIKONFIRMASI ULANG langsung ke API Midtrans
     4. jumlah dari Midtrans harus sama dengan jumlah di basis data
   Lalu status diterapkan lewat jalur tunggal ubahStatusTransaksi(), yang
   aman menerima notifikasi berulang (Midtrans memang mengirim ulang).
   ============================================================================= */

require __DIR__ . '/inc/awal.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jawabJson(405, ['galat' => 'Hanya POST.']);
}

$mentah = (string) file_get_contents('php://input');
$n = json_decode($mentah, true);
if (!is_array($n) || empty($n['order_id'])) {
    jawabJson(400, ['galat' => 'Isi notifikasi tidak dikenali.']);
}

$midtrans = new GerbangMidtrans();
if (!$midtrans->siap()) {
    jawabJson(503, ['galat' => 'Midtrans belum disetel.']);
}
if (!$midtrans->tandaTanganSah($n)) {
    error_log('[invishar midtrans] tanda tangan tidak sah untuk ' . $n['order_id']);
    jawabJson(403, ['galat' => 'Tanda tangan tidak sah.']);
}

$trx = ambilSatu("SELECT * FROM transaksi WHERE kode_order = ? AND gerbang = 'midtrans'", [(string) $n['order_id']]);
if (!$trx) {
    jawabJson(404, ['galat' => 'Transaksi tidak dikenal.']);
}

try {
    $s = $midtrans->status($trx['kode_order']);
} catch (Throwable $e) {
    error_log('[invishar midtrans] ' . $e->getMessage());
    $s = null;
}
if (!$s) {
    // Midtrans akan mengirim ulang notifikasi; jangan ubah apa pun tanpa konfirmasi.
    jawabJson(502, ['galat' => 'Status tidak bisa dikonfirmasi ke Midtrans.']);
}

$hasil = terapkanStatusMidtrans($trx, $s, 'midtrans', $mentah);
jawabJson($hasil['kode'], ['pesan' => $hasil['pesan']]);
