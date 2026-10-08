<?php
declare(strict_types=1);

/* =============================================================================
   Email akses otomatis — memakai mail() bawaan PHP, gratis tanpa layanan luar.

   Dikirim tepat saat transaksi lunas (di dalam ubahStatusTransaksi(), setelah
   token akses dibuat). Isinya: logo usaha, sapaan, rincian pembelian, tombol
   menuju halaman akses bertoken, dan instruksi pemakaian dari data produk.

   mail() di shared hosting kadang mendarat di spam — karena itu halaman
   selesai juga menampilkan tombol akses langsung dan menyarankan pembeli
   memeriksa folder spam. Kegagalan kirim tidak pernah menggagalkan status
   lunas: fungsi ini mengembalikan boolean, bukan melempar.
   ============================================================================= */

require_once __DIR__ . '/inti.php';

/**
 * Mengirim email akses ke pembeli. true bila mail() diterima server.
 * Hanya untuk pembelian produk (bukan pembayaran jasa, bukan perpanjangan
 * langganan — pembeli sudah punya aksesnya).
 */
function kirimEmailAkses(array $trx): bool
{
    if (!empty($trx['order_jasa_id']) || (int) ($trx['periode_ke'] ?? 1) > 1) {
        return false;
    }
    $surel = trim((string) ($trx['surel'] ?? ''));
    if (!filter_var($surel, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    if (empty($trx['akses_token'])) {
        return false;
    }
    $produk = $trx['produk'] ?? ambilSatu('SELECT * FROM produk WHERE id = ?', [$trx['produk_id']]);
    if (!$produk) {
        return false;
    }

    $namaUsaha = setelan('usaha.nama') !== '' ? (string) setelan('usaha.nama') : 'Invishar';
    $dari      = setelan('usaha.surel') !== '' ? (string) setelan('usaha.surel') : 'hello@invishar.id';
    $logo      = (string) setelan('usaha.logo_url');
    $urlAkses  = urlSitus() . '/toko/akses.php?t=' . $trx['akses_token'];

    $namaDepan = explode(' ', trim((string) $trx['pembeli_nama']))[0] ?? '';
    $sapa = $namaDepan !== '' ? 'Halo, ' . $namaDepan . '!' : 'Halo!';

    $instruksi = '';
    if (trim((string) ($produk['akses_catatan'] ?? '')) !== '') {
        $instruksi =
            '<tr><td style="padding:0 28px 20px;">' .
            '<div style="background:#f6f8f6;border:1px solid #e2e8e2;border-radius:10px;padding:16px 18px;">' .
            '<p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#1a2b1a;letter-spacing:.04em;">CARA MEMAKAI</p>' .
            '<p style="margin:0;font-size:14px;line-height:1.65;color:#334133;white-space:pre-line;">' . e($produk['akses_catatan']) . '</p>' .
            '</div></td></tr>';
    }

    $logoHtml = $logo !== ''
        ? '<img src="' . e($logo) . '" alt="' . e($namaUsaha) . '" height="44" style="height:44px;width:auto;display:block;margin:0 auto 18px;">'
        : '';

    $judulEmail = 'Pembayaran diterima — akses Anda sudah siap';

    $isi =
        '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"></head>' .
        '<body style="margin:0;padding:0;background:#eef2ee;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">' .
        '<div style="padding:28px 14px;">' .
        '<table role="presentation" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;">' .
        '<tr><td style="padding:30px 28px 6px;text-align:center;">' . $logoHtml .
        '<h1 style="margin:0 0 6px;font-size:22px;color:#142114;">' . e($judulEmail) . '</h1>' .
        '<p style="margin:0;font-size:14px;color:#5a6b5a;">' . e($sapa) . ' Terima kasih, pembayaran Anda sudah kami terima.</p>' .
        '</td></tr>' .
        '<tr><td style="padding:20px 28px;">' .
        '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;font-size:14px;color:#334133;">' .
        '<tr><td style="padding:6px 0;color:#75816f;">Produk</td><td style="padding:6px 0;text-align:right;font-weight:600;color:#142114;">' . e($produk['nama']) . '</td></tr>' .
        '<tr><td style="padding:6px 0;color:#75816f;">Kode pesanan</td><td style="padding:6px 0;text-align:right;font-family:monospace;">' . e($trx['kode_order']) . '</td></tr>' .
        '<tr><td style="padding:6px 0;color:#75816f;">Jumlah dibayar</td><td style="padding:6px 0;text-align:right;font-weight:600;color:#142114;">' . e(rupiah((int) $trx['jumlah'])) . '</td></tr>' .
        '<tr><td style="padding:6px 0;color:#75816f;">Tanggal bayar</td><td style="padding:6px 0;text-align:right;">' . e(waktuIndo($trx['dibayar_pada'])) . '</td></tr>' .
        '</table></td></tr>' .
        '<tr><td style="padding:6px 28px 22px;text-align:center;">' .
        '<a href="' . e($urlAkses) . '" style="display:inline-block;background:#1d7a34;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:14px 34px;border-radius:10px;">Buka akses saya</a>' .
        '<p style="margin:12px 0 0;font-size:12px;color:#75816f;">Tombol tidak berfungsi? Salin tautan ini:<br>' .
        '<span style="word-break:break-all;color:#3f6b4a;">' . e($urlAkses) . '</span></p>' .
        '</td></tr>' .
        $instruksi .
        '<tr><td style="padding:0 28px 26px;">' .
        '<p style="margin:0;font-size:13px;line-height:1.65;color:#75816f;">Tautan di atas <strong>pribadi untuk Anda</strong> — jangan dibagikan. ' .
        'Simpan email ini; tautannya tidak kedaluwarsa dan bisa dibuka kapan saja tanpa login.</p>' .
        '</td></tr>' .
        '<tr><td style="padding:18px 28px;background:#f6f8f6;text-align:center;">' .
        '<p style="margin:0;font-size:12px;color:#8a968a;">Butuh bantuan? Balas email ini dengan menyebutkan kode pesanan Anda.<br>© ' . e($namaUsaha) . '</p>' .
        '</td></tr>' .
        '</table></div></body></html>';

    $subjek = 'Akses ' . $produk['nama'] . ' Anda sudah siap';
    $subjekEnk = function_exists('mb_encode_mimeheader')
        ? mb_encode_mimeheader($subjek, 'UTF-8', 'Q')
        : $subjek;

    $kepala = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $namaUsaha . ' <' . $dari . '>',
        'Reply-To: ' . $dari,
        'X-Mailer: Invishar',
    ]);

    /* Coba dengan envelope-sender yang cocok dulu (lebih ramah SPF); kalau
       server menolak parameternya, kirim tanpa itu. */
    $terkirim = @mail($surel, $subjekEnk, $isi, $kepala, '-f' . $dari);
    if (!$terkirim) {
        $terkirim = @mail($surel, $subjekEnk, $isi, $kepala);
    }
    return $terkirim;
}
