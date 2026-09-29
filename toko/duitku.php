<?php
declare(strict_types=1);

/* =============================================================================
   Callback Duitku: POST form-encoded ke /toko/duitku.php

   Tanda tangan Duitku hanya menutup merchantCode + amount + merchantOrderId.
   resultCode TIDAK ikut ditandatangani — siapa pun yang pernah melihat satu
   callback sah bisa mengulangnya dengan resultCode diganti "00" dan tanda
   tangannya tetap lolos. Dokumentasi Duitku sendiri menulis "never use
   resultCode to update payment status".

   Karena itu status selalu dikonfirmasi ulang ke Duitku lewat transactionStatus
   sebelum apa pun diubah. Langkah itu bukan kemewahan dan tidak boleh dilewati
   sebagai penghematan. Field callback yang tidak ditandatangani hanya boleh
   jadi label tampilan.

   Duitku mengirim ulang callback sampai lima kali kalau tidak menerima HTTP 200,
   jadi membalas 502 saat ragu justru yang benar.
   ============================================================================= */

require __DIR__ . '/inc/awal.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jawabJson(405, ['galat' => 'Hanya POST.']);
}

$mentah = (string) file_get_contents('php://input');
$n = $_POST;
if (empty($n['merchantOrderId'])) {
    jawabJson(400, ['galat' => 'Isi notifikasi tidak dikenali.']);
}

$duitku = new GerbangDuitku();
if (!$duitku->siap()) {
    jawabJson(503, ['galat' => 'Duitku belum disetel.']);
}
if (!$duitku->tandaTanganSah($n)) {
    error_log('[invishar duitku] tanda tangan tidak sah untuk ' . $n['merchantOrderId']);
    jawabJson(403, ['galat' => 'Tanda tangan tidak sah.']);
}

$trx = ambilSatu("SELECT * FROM transaksi WHERE kode_order = ? AND gerbang = 'duitku'", [(string) $n['merchantOrderId']]);
if (!$trx) {
    jawabJson(404, ['galat' => 'Transaksi tidak dikenal.']);
}

try {
    $s = $duitku->status($trx['kode_order']);
} catch (Throwable $e) {
    error_log('[invishar duitku] ' . $e->getMessage());
    $s = null;
}
if (!$s) {
    // Duitku akan mengirim ulang; jangan ubah apa pun tanpa konfirmasi.
    jawabJson(502, ['galat' => 'Status tidak bisa dikonfirmasi ke Duitku.']);
}

// Metode pembayaran tidak ada di jawaban transactionStatus. Diambil dari callback
// meski tidak ditandatangani, karena pemakaiannya hanya sebagai label di panel.
if (!empty($n['paymentCode'])) {
    $s['paymentCode'] = (string) $n['paymentCode'];
}

$hasil = $duitku->terapkan($trx, $s, 'duitku', $mentah !== '' ? $mentah : http_build_query($n));
jawabJson($hasil['kode'], ['pesan' => $hasil['pesan']]);
