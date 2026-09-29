<?php
declare(strict_types=1);

/* =============================================================================
   Transaksi dan gerbang pembayaran.

   ubahStatusTransaksi() adalah SATU-SATUNYA tempat status transaksi berubah —
   dari gerbang uji, webhook Duitku, maupun admin. Karena itu komisi hanya
   dibuat di satu jalur, dan jalur uji yang dicoba sekarang adalah jalur yang
   sama persis dengan pembayaran sungguhan nanti.

   Gerbang dipilih di panel → Setting → Pembayaran (lihat konfigPembayaran()).
   Pindah ke Duitku produksi (uang sungguhan) butuh kunci yang lolos tes
   koneksi dan penegasan tertulis — tidak bisa sekadar satu klik.
   ============================================================================= */

require_once __DIR__ . '/affiliate.php';

const STATUS_TRANSAKSI = [
    'menunggu'    => 'Menunggu bayar',
    'lunas'       => 'Lunas',
    'gagal'       => 'Gagal',
    'kedaluwarsa' => 'Kedaluwarsa',
    'refund'      => 'Dikembalikan',
];

/* Perpindahan status yang diizinkan. Status yang sama = tidak ada perubahan
   (notifikasi berulang dari gerbang aman). Lunas tidak bisa mundur ke gagal. */
const PERPINDAHAN_TRANSAKSI = [
    'menunggu'    => ['lunas', 'gagal', 'kedaluwarsa'],
    'gagal'       => ['lunas'],
    'kedaluwarsa' => ['lunas'],
    'lunas'       => ['refund'],
    'refund'      => [],
];

const METODE_MANUAL = ['Transfer bank', 'QRIS', 'Tunai', 'Lainnya'];

/* ------------------------------------------------------- Transaksi */

function kodeOrderBaru(): string
{
    do {
        $kode = 'INV-' . date('ymd') . '-' . kodeAcak(6);
    } while (ambilNilai('SELECT 1 FROM transaksi WHERE kode_order = ?', [$kode]) !== null);
    return $kode;
}

/**
 * Mencatat transaksi baru berstatus "menunggu".
 * $d: produk_id, pembeli_nama, surel, whatsapp, jumlah, gerbang, sumber,
 *     dan opsional affiliate_id, beli_sendiri, langganan_id, periode_ke,
 *     order_jasa_id, metode, catatan, ip.
 */
function buatTransaksi(array $d): array
{
    $kode = kodeOrderBaru();
    q(
        'INSERT INTO transaksi (kode_order, produk_id, affiliate_id, beli_sendiri, langganan_id, periode_ke, order_jasa_id,
                                pembeli_nama, surel, whatsapp, jumlah, status, gerbang, metode, catatan, ip,
                                dibuat_pada, diperbarui_pada)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'menunggu\', ?, ?, ?, ?, NOW(), NOW())',
        [
            $kode, $d['produk_id'], $d['affiliate_id'] ?? null, !empty($d['beli_sendiri']) ? 1 : 0,
            $d['langganan_id'] ?? null, max(1, (int) ($d['periode_ke'] ?? 1)), $d['order_jasa_id'] ?? null,
            mb_substr($d['pembeli_nama'], 0, 120),
            ($d['surel'] ?? '') !== '' ? mb_substr($d['surel'], 0, 160) : null,
            ($d['whatsapp'] ?? '') !== '' ? mb_substr($d['whatsapp'], 0, 40) : null,
            (int) $d['jumlah'], $d['gerbang'], $d['metode'] ?? null, $d['catatan'] ?? null, $d['ip'] ?? null,
        ]
    );
    $id = (int) db()->lastInsertId();
    // Pembayaran order jasa dan perpanjangan langganan bukan pesanan baru yang
    // perlu dikirim/dikerjakan — pesanannya sudah diurus di tempat lain.
    if (!empty($d['order_jasa_id']) || (int) ($d['periode_ke'] ?? 1) > 1) {
        q("UPDATE transaksi SET status_proses = 'selesai' WHERE id = ?", [$id]);
    }
    q(
        'INSERT INTO transaksi_riwayat (transaksi_id, status_lama, status_baru, sumber, catatan, dibuat_pada)
         VALUES (?, NULL, \'menunggu\', ?, ?, NOW())',
        [$id, $d['sumber'], $d['catatan_riwayat'] ?? 'Transaksi dibuat']
    );
    return ambilSatu('SELECT * FROM transaksi WHERE id = ?', [$id]);
}

/**
 * Mengubah status transaksi, lalu menjalankan akibatnya:
 *   → lunas  : catat waktu bayar, buka langganan (bulan pertama), buat komisi
 *   → refund : batalkan / potong komisi
 * Seluruhnya di dalam satu transaksi basis data dengan baris terkunci, jadi
 * dua notifikasi yang datang bersamaan tidak bisa membuat komisi dua kali.
 *
 * $tambahan: gerbang_ref, metode, dibayar_pada (Y-m-d H:i:s)
 * @return array{berubah: bool, trx: array, alasan?: string}
 */
function ubahStatusTransaksi(int $id, string $baru, string $sumber, string $catatan = '', ?string $payload = null, array $tambahan = []): array
{
    return dalamTransaksi(function () use ($id, $baru, $sumber, $catatan, $payload, $tambahan): array {
        $trx = ambilSatu('SELECT * FROM transaksi WHERE id = ? FOR UPDATE', [$id]);
        if ($trx === null) {
            throw new RuntimeException('Transaksi tidak ditemukan.');
        }

        // Keterangan gerbang boleh diperbarui kapan saja.
        if (!empty($tambahan['gerbang_ref']) || !empty($tambahan['metode'])) {
            q(
                'UPDATE transaksi SET gerbang_ref = COALESCE(?, gerbang_ref), metode = COALESCE(?, metode) WHERE id = ?',
                [$tambahan['gerbang_ref'] ?? null, $tambahan['metode'] ?? null, $id]
            );
        }

        $lama = $trx['status'];
        if ($lama === $baru) {
            return ['berubah' => false, 'trx' => $trx, 'alasan' => 'sama'];
        }
        if (!in_array($baru, PERPINDAHAN_TRANSAKSI[$lama] ?? [], true)) {
            q(
                'INSERT INTO transaksi_riwayat (transaksi_id, status_lama, status_baru, sumber, catatan, payload, dibuat_pada)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())',
                [$id, $lama, $lama, $sumber, 'Diabaikan: tidak boleh dari "' . $lama . '" ke "' . $baru . '"', $payload]
            );
            return ['berubah' => false, 'trx' => $trx, 'alasan' => 'tidak boleh'];
        }

        if ($baru === 'lunas') {
            $dibayar = $tambahan['dibayar_pada'] ?? null;
            q(
                'UPDATE transaksi SET status = ?, dibayar_pada = COALESCE(?, NOW()), diperbarui_pada = NOW() WHERE id = ?',
                [$baru, $dibayar, $id]
            );
        } else {
            q('UPDATE transaksi SET status = ?, diperbarui_pada = NOW() WHERE id = ?', [$baru, $id]);
        }

        // Status proses mengikuti: pesanan yang tidak jadi dibayar otomatis batal,
        // dan pesanan batal yang ternyata dibayar belakangan kembali perlu diproses.
        if (in_array($baru, ['gagal', 'kedaluwarsa', 'refund'], true)) {
            q("UPDATE transaksi SET status_proses = 'batal', proses_pada = NOW() WHERE id = ?", [$id]);
        } elseif ($baru === 'lunas' && $trx['status_proses'] === 'batal') {
            q("UPDATE transaksi SET status_proses = 'baru', proses_pada = NULL WHERE id = ?", [$id]);
        }

        q(
            'INSERT INTO transaksi_riwayat (transaksi_id, status_lama, status_baru, sumber, catatan, payload, dibuat_pada)
             VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [$id, $lama, $baru, $sumber, $catatan !== '' ? mb_substr($catatan, 0, 255) : null, $payload]
        );

        $trx = ambilSatu('SELECT * FROM transaksi WHERE id = ?', [$id]);

        if ($baru === 'lunas') {
            $produk = ambilSatu('SELECT * FROM produk WHERE id = ?', [$trx['produk_id']]);
            if ($produk && $produk['jenis'] === 'langganan' && $trx['langganan_id'] === null) {
                q(
                    'INSERT INTO langganan (produk_id, affiliate_id, pembeli_nama, surel, whatsapp, status, mulai_pada, dibuat_pada)
                     VALUES (?, ?, ?, ?, ?, \'aktif\', NOW(), NOW())',
                    [
                        $trx['produk_id'],
                        (int) $trx['beli_sendiri'] === 1 ? null : $trx['affiliate_id'],
                        $trx['pembeli_nama'], $trx['surel'], $trx['whatsapp'],
                    ]
                );
                q('UPDATE transaksi SET langganan_id = ? WHERE id = ?', [(int) db()->lastInsertId(), $id]);
                $trx = ambilSatu('SELECT * FROM transaksi WHERE id = ?', [$id]);
            }
            buatKomisi($trx);
        }

        if ($baru === 'refund') {
            batalkanKomisi($trx, 'Dana transaksi ' . $trx['kode_order'] . ' dikembalikan');
        }

        return ['berubah' => true, 'trx' => $trx];
    });
}

/* ---------------------------------------------------------- Gerbang */

interface Gerbang
{
    public function nama(): string;

    /** Siap dipakai sekarang — mis. kuncinya sudah diisi. */
    public function siap(): bool;

    /** Menyiapkan pembayaran; mengembalikan alamat tujuan pembeli. */
    public function mulai(array $trx, array $produk): string;
}

/**
 * Gerbang yang statusnya bisa ditanyakan balik ke penyedia.
 *
 * Sengaja terpisah dari Gerbang: gerbang uji tidak punya layanan untuk ditanyai,
 * dan memaksanya punya status() yang selalu null adalah method bohong. Dengan
 * antarmuka sendiri, pemanggil cukup menulis `instanceof GerbangDicek` dan tidak
 * perlu tahu nama penyedianya.
 */
interface GerbangDicek
{
    /** Status terkini dari penyedia, atau null kalau ordernya tidak dikenal. */
    public function status(string $kodeOrder): ?array;

    /**
     * Menerapkan jawaban penyedia ke transaksi, setelah mencocokkan jumlahnya.
     * @return array{kode: int, pesan: string}
     */
    public function terapkan(array $trx, array $jawaban, string $sumber, ?string $payload): array;
}

/* Gerbang yang boleh dipilih untuk checkout. 'manual' tidak di sini — itu hanya
   nilai tersimpan pada transaksi yang dicatat admin, bukan pilihan pembayaran. */
const GERBANG_TERSEDIA = ['uji', 'duitku'];

/* Batas waktu bayar yang dikirim ke Duitku. Duitku hanya menerima 5, 10, atau
   60 menit; dipakai yang terpanjang supaya pembeli yang transfer lewat VA masih
   sempat. Dipakai juga untuk menghitung kapan halaman bayar Duitku sudah mati,
   karena tidak ada kolom kedaluwarsa di tabel transaksi. */
const DUITKU_EXPIRY_MENIT = 60;

/**
 * Setelan pembayaran yang berlaku.
 *
 * Diatur di panel → Setting → Pembayaran (tabel setelan). Selama halaman itu
 * belum pernah disimpan, konfig.php yang berlaku — server yang sudah memakai
 * konfig.php tidak berubah perilakunya. url_app/url_api (untuk uji lokal)
 * hanya dibaca dari konfig.php.
 *
 * @return array{gerbang: string, dikenal: bool, duitku: array, sumber: string}
 */
function konfigPembayaran(): array
{
    $k = (array) konfig('duitku');
    $dariPanel = setelan('pembayaran.gerbang') !== '';
    if ($dariPanel) {
        $gerbang = setelan('pembayaran.gerbang');
        $duitku = [
            'merchant_code' => setelan('pembayaran.duitku_merchant_code'),
            'api_key'       => setelan('pembayaran.duitku_api_key'),
            'produksi'      => setelan('pembayaran.duitku_produksi') === '1',
        ];
    } else {
        $gerbang = (string) (konfig('gerbang') ?: 'uji');
        $duitku = [
            'merchant_code' => (string) ($k['merchant_code'] ?? ''),
            'api_key'       => (string) ($k['api_key'] ?? ''),
            'produksi'      => !empty($k['produksi']),
        ];
    }
    foreach (['url_pop', 'url_api'] as $x) {
        if (!empty($k[$x])) {
            $duitku[$x] = $k[$x];
        }
    }
    return [
        // Apa adanya, TIDAK dinormalkan. Dulu nilai tak dikenal dipaksa jadi
        // 'uji', yang berarti setelan basi diam-diam memindahkan checkout ke
        // mode simulasi — pembeli menekan "Simulasikan lunas" dan mendapat
        // produk gratis lewat jalur sah, lengkap dengan komisi. Sekarang
        // keputusannya diserahkan ke pemanggil supaya bisa gagal-tertutup.
        'gerbang' => $gerbang,
        'dikenal' => in_array($gerbang, GERBANG_TERSEDIA, true),
        'duitku'  => $duitku,
        'sumber'  => $dariPanel ? 'panel' : 'konfig',
    ];
}

/** Nama gerbang tersimpan → objeknya. null = tidak dikenal atau bukan gerbang otomatis. */
function gerbangUntuk(string $nama): ?Gerbang
{
    return match ($nama) {
        'uji'    => new GerbangUji(),
        'duitku' => new GerbangDuitku(),
        default  => null,      // 'manual', salah tulis, atau gerbang yang sudah dibuang
    };
}

function gerbangAktif(): ?Gerbang
{
    return gerbangUntuk(konfigPembayaran()['gerbang']);
}

/* Whitelist positif, bukan "bukan gerbang X". Definisi negatif yang lama membuat
   setiap gerbang baru otomatis dianggap mode uji — artinya toko/bayar-uji.php
   tetap hidup di atas transaksi uang sungguhan. Di berkas ini, mode uji selalu
   ditentukan oleh apa dirinya, bukan oleh apa yang bukan dirinya. */
function modeUji(): bool
{
    return konfigPembayaran()['gerbang'] === 'uji';
}

/**
 * Gerbang uji: tidak ada uang yang berpindah. Pembeli diarahkan ke halaman
 * simulasi yang memanggil ubahStatusTransaksi() — jalur yang sama dengan
 * webhook Duitku.
 */
final class GerbangUji implements Gerbang
{
    public function nama(): string
    {
        return 'uji';
    }

    /** Tidak menghubungi layanan apa pun, jadi selalu siap. */
    public function siap(): bool
    {
        return true;
    }

    public function mulai(array $trx, array $produk): string
    {
        return '/toko/bayar-uji.php?o=' . rawurlencode($trx['kode_order']);
    }

    /** Token pengaman tombol simulasi, supaya tidak bisa dipicu dari luar. */
    public static function token(string $kodeOrder): string
    {
        return substr(hash_hmac('sha256', 'uji|' . $kodeOrder, rahasiaSistem()), 0, 24);
    }
}

/**
 * Duitku POP — satu halaman pembayaran milik Duitku tempat pembeli memilih
 * sendiri VA, QRIS, atau e-wallet.
 *   mulai()   → POST {pop}/api/merchant/createInvoice      → paymentUrl
 *   status()  → POST {pop}/api/merchant/transactionStatus  (webhook & halaman selesai)
 *
 * Kunci API hanya dipakai di sisi server, tidak pernah dikirim ke peramban.
 *
 * Catatan soal tanda tangan: tulisan dokumentasi Duitku menyebut HMAC_SHA256 di
 * semua bagian, sementara pustaka PHP resmi mereka memakai sha256 biasa atas
 * gabungan untuk header dan md5 untuk callback serta cek status. Dua penerapan
 * resmi sepakat melawan tulisannya, jadi yang diikuti penerapannya. Ketiga rumus
 * sengaja dipisah jadi method sebaris supaya bisa ditukar tanpa menyentuh yang
 * lain kalau tes koneksi membuktikan sebaliknya.
 */
final class GerbangDuitku implements Gerbang, GerbangDicek
{
    /** @param array|null $konfTetap setelan pengganti — dipakai tes kunci sebelum disimpan */
    public function __construct(private ?array $konfTetap = null)
    {
    }

    public function nama(): string
    {
        return 'duitku';
    }

    private function konf(): array
    {
        return $this->konfTetap ?? konfigPembayaran()['duitku'];
    }

    public function siap(): bool
    {
        $k = $this->konf();
        return trim((string) ($k['merchant_code'] ?? '')) !== ''
            && trim((string) ($k['api_key'] ?? '')) !== '';
    }

    private function produksi(): bool
    {
        return !empty($this->konf()['produksi']);
    }

    private function lingkungan(): string
    {
        return $this->produksi() ? 'produksi' : 'sandbox';
    }

    /** Host POP: createInvoice dan transactionStatus. */
    private function urlPop(): string
    {
        $k = $this->konf();
        return rtrim((string) ($k['url_pop'] ?? ($this->produksi() ? 'https://api-prod.duitku.com' : 'https://api-sandbox.duitku.com')), '/');
    }

    /** Host lama: hanya dipakai getPaymentMethod untuk tes kunci. */
    private function urlApi(): string
    {
        $k = $this->konf();
        return rtrim((string) ($k['url_api'] ?? ($this->produksi() ? 'https://passport.duitku.com' : 'https://sandbox.duitku.com')), '/');
    }

    private function kodeMerchant(): string
    {
        $kode = trim((string) ($this->konf()['merchant_code'] ?? ''));
        if ($kode === '') {
            throw new RuntimeException('Merchant Code Duitku belum diisi (panel → Setting → Pembayaran).');
        }
        return $kode;
    }

    private function kunciApi(): string
    {
        $kunci = trim((string) ($this->konf()['api_key'] ?? ''));
        if ($kunci === '') {
            throw new RuntimeException('API Key Duitku belum diisi (panel → Setting → Pembayaran).');
        }
        return $kunci;
    }

    /* ------------------------------------------------------ Tanda tangan */

    private function ttdHeader(string $stempel): string
    {
        return hash('sha256', $this->kodeMerchant() . $stempel . $this->kunciApi());
    }

    private function ttdStatus(string $kodeOrder): string
    {
        return md5($this->kodeMerchant() . $kodeOrder . $this->kunciApi());
    }

    private function ttdTesKunci(int $jumlah, string $waktu): string
    {
        return hash('sha256', $this->kodeMerchant() . $jumlah . $waktu . $this->kunciApi());
    }

    /**
     * Callback hanya menandatangani merchantCode + amount + merchantOrderId.
     * resultCode TIDAK ikut ditandatangani — karena itu tanda tangan yang sah
     * pun belum cukup untuk memutuskan status; lihat toko/duitku.php.
     */
    public function tandaTanganSah(array $n): bool
    {
        foreach (['merchantCode', 'amount', 'merchantOrderId', 'signature'] as $f) {
            if (!isset($n[$f]) || !is_string($n[$f])) {
                return false;
            }
        }
        if (!hash_equals($this->kodeMerchant(), $n['merchantCode'])) {
            return false;
        }
        $harus = md5($n['merchantCode'] . $n['amount'] . $n['merchantOrderId'] . $this->kunciApi());
        return hash_equals($harus, $n['signature']);
    }

    /* ---------------------------------------------------------- Jaringan */

    /** @return array{0:int,1:?array} kode HTTP dan isi JSON */
    private function panggil(string $url, array $isi, array $kepalaTambahan = []): array
    {
        $c = curl_init($url);
        curl_setopt_array($c, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($isi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json', 'Content-Type: application/json'], $kepalaTambahan),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 25,
            // Contoh resmi Duitku mematikan verifikasi sertifikat. Jangan ditiru:
            // itu membuka seluruh alur pembayaran ke penyadapan di tengah jalan.
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $jawab = curl_exec($c);
        $kode  = (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE);
        $galat = curl_error($c);
        curl_close($c);

        if ($jawab === false) {
            throw new RuntimeException('Tidak bisa menghubungi Duitku: ' . $galat);
        }
        $json = json_decode((string) $jawab, true);
        return [$kode, is_array($json) ? $json : null];
    }

    /**
     * Memeriksa kredensial dengan meminta daftar metode pembayaran. Tidak
     * membuat apa pun, jadi aman dipanggil berulang sebelum setelan disimpan.
     * @return array{ok: bool, pesan: string}
     */
    public function tesKunci(): array
    {
        $waktu = date('Y-m-d H:i:s');
        $jumlah = 10000;
        try {
            [$kode, $json] = $this->panggil($this->urlApi() . '/webapi/api/merchant/paymentmethod/getpaymentmethod', [
                'merchantcode' => $this->kodeMerchant(),
                'amount'       => $jumlah,
                'datetime'     => $waktu,
                'signature'    => $this->ttdTesKunci($jumlah, $waktu),
            ]);
        } catch (Throwable $e) {
            return ['ok' => false, 'pesan' => $e->getMessage()];
        }

        $respon = (string) ($json['responseCode'] ?? '');
        if ($kode === 200 && $respon === '00') {
            $jml = count((array) ($json['paymentFee'] ?? []));
            return ['ok' => true, 'pesan' => 'Kredensial diterima Duitku ' . $this->lingkungan() . '. '
                . ($jml > 0
                    ? $jml . ' metode pembayaran aktif di project ini.'
                    : 'Tapi BELUM ADA metode pembayaran yang aktif — aktifkan dulu di dashboard Duitku, kalau tidak halaman bayarnya kosong.')];
        }
        if ($kode === 200) {
            return ['ok' => false, 'pesan' => 'Duitku ' . $this->lingkungan() . ' menolak: '
                . (string) ($json['Message'] ?? $json['responseMessage'] ?? 'kode ' . $respon)
                . '. Pastikan Merchant Code dan API Key berasal dari dashboard ' . $this->lingkungan() . '.'];
        }
        return ['ok' => false, 'pesan' => 'Jawaban Duitku tidak terduga (HTTP ' . $kode . ').'];
    }

    public function mulai(array $trx, array $produk): string
    {
        $namaBarang = $produk['nama'] . ($produk['jenis'] === 'langganan' ? ' — bulan ke-' . (int) $trx['periode_ke'] : '');
        $stempel = (string) round(microtime(true) * 1000);

        $isi = array_filter([
            'merchantCode'    => $this->kodeMerchant(),
            'paymentAmount'   => (int) $trx['jumlah'],
            'merchantOrderId' => $trx['kode_order'],
            'productDetails'  => mb_substr($namaBarang, 0, 255),
            'email'           => $trx['surel'],
            // Duitku memotong nama VA di 20 karakter dan menolak yang lebih
            // panjang — tanpa ini, checkout gagal hanya untuk pembeli bernama
            // panjang, gejala yang sangat mudah salah didiagnosis.
            'customerVaName'  => mb_substr((string) $trx['pembeli_nama'], 0, 20),
            'phoneNumber'     => $trx['whatsapp'] ? mb_substr(normalWa((string) $trx['whatsapp']), 0, 50) : null,
            'callbackUrl'     => urlSitus() . '/toko/duitku.php',
            'returnUrl'       => urlSitus() . '/toko/selesai.php?o=' . rawurlencode($trx['kode_order']),
            'expiryPeriod'    => DUITKU_EXPIRY_MENIT,
            // paymentMethod sengaja TIDAK diisi: mengisinya melewati halaman POP
            // dan memaksa kita memilihkan metode untuk pembeli.
        ], static fn ($v) => $v !== null && $v !== '');

        [$kode, $json] = $this->panggil($this->urlPop() . '/api/merchant/createInvoice', $isi, [
            'x-duitku-merchantcode: ' . $this->kodeMerchant(),
            'x-duitku-timestamp: ' . $stempel,
            'x-duitku-signature: ' . $this->ttdHeader($stempel),
        ]);

        if ($kode !== 200 || (string) ($json['statusCode'] ?? '') !== '00' || empty($json['paymentUrl'])) {
            $alasan = (string) ($json['statusMessage'] ?? $json['Message'] ?? ('HTTP ' . $kode));
            throw new RuntimeException('Duitku menolak membuat pembayaran: ' . $alasan);
        }

        q(
            'UPDATE transaksi SET gerbang_ref = ?, gerbang_url = ? WHERE id = ?',
            [
                isset($json['reference']) ? mb_substr((string) $json['reference'], 0, 80) : null,
                mb_substr((string) $json['paymentUrl'], 0, 255),
                $trx['id'],
            ]
        );
        return (string) $json['paymentUrl'];
    }

    /** Status terkini langsung dari Duitku, atau null kalau ordernya tidak dikenal. */
    public function status(string $kodeOrder): ?array
    {
        [$kode, $json] = $this->panggil($this->urlPop() . '/api/merchant/transactionStatus', [
            'merchantCode'    => $this->kodeMerchant(),
            'merchantOrderId' => $kodeOrder,
            'signature'       => $this->ttdStatus($kodeOrder),
        ]);
        if ($kode !== 200 || !$json || !isset($json['statusCode'])) {
            return null;
        }
        return $json;
    }

    /**
     * statusCode Duitku → status transaksi kita. null = belum ada perubahan.
     *
     * Duitku menyatukan gagal dan kedaluwarsa dalam satu kode 02, jadi keduanya
     * dibedakan di terapkan() memakai umur transaksi.
     */
    public static function petaStatus(array $s): ?string
    {
        return match ((string) ($s['statusCode'] ?? '')) {
            '00'    => 'lunas',
            '02'    => 'gagal',
            default => null,      // 01 = sedang diproses
        };
    }

    public function terapkan(array $trx, array $jawaban, string $sumber, ?string $payload): array
    {
        $jumlahPenyedia = (int) round((float) ($jawaban['amount'] ?? 0));
        if ($jumlahPenyedia !== (int) $trx['jumlah']) {
            q(
                'INSERT INTO transaksi_riwayat (transaksi_id, status_lama, status_baru, sumber, catatan, payload, dibuat_pada)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())',
                [$trx['id'], $trx['status'], $trx['status'], $sumber,
                 'DITOLAK: jumlah dari Duitku ' . $jumlahPenyedia . ' tidak sama dengan ' . $trx['jumlah'], $payload]
            );
            return ['kode' => 409, 'pesan' => 'Jumlah tidak cocok'];
        }

        $baru = self::petaStatus($jawaban);
        if ($baru === 'gagal' && strtotime((string) $trx['dibuat_pada']) + DUITKU_EXPIRY_MENIT * 60 <= time()) {
            $baru = 'kedaluwarsa';
        }

        $tambahan = [
            'gerbang_ref' => isset($jawaban['reference']) ? mb_substr((string) $jawaban['reference'], 0, 80) : null,
            // paymentCode datang dari callback yang tidak ditandatangani, jadi
            // hanya boleh jadi label tampilan — tidak pernah ikut memutuskan uang.
            'metode'      => isset($jawaban['paymentCode']) ? mb_substr((string) $jawaban['paymentCode'], 0, 40) : null,
        ];

        if ($baru === null) {
            if ($tambahan['gerbang_ref'] || $tambahan['metode']) {
                q(
                    'UPDATE transaksi SET gerbang_ref = COALESCE(?, gerbang_ref), metode = COALESCE(?, metode) WHERE id = ?',
                    [$tambahan['gerbang_ref'], $tambahan['metode'], $trx['id']]
                );
            }
            return ['kode' => 200, 'pesan' => 'Dicatat, belum ada perubahan status'];
        }

        $catatan = 'Duitku: ' . (string) ($jawaban['statusMessage'] ?? $jawaban['statusCode'] ?? '?')
            . (isset($jawaban['paymentCode']) ? ' · ' . $jawaban['paymentCode'] : '');
        ubahStatusTransaksi((int) $trx['id'], $baru, $sumber, $catatan, $payload, $tambahan);
        return ['kode' => 200, 'pesan' => 'Diterapkan'];
    }
}
