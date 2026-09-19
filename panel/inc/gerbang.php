<?php
declare(strict_types=1);

/* =============================================================================
   Transaksi dan gerbang pembayaran.

   ubahStatusTransaksi() adalah SATU-SATUNYA tempat status transaksi berubah —
   dari gerbang uji, webhook Midtrans, maupun admin. Karena itu komisi hanya
   dibuat di satu jalur, dan jalur uji yang dicoba sekarang adalah jalur yang
   sama persis dengan pembayaran sungguhan nanti.

   Gerbang dipilih di konfig.php ('gerbang' => 'uji' | 'midtrans'), bukan dari
   panel: pindah ke uang sungguhan tidak boleh sekadar satu klik.
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

    /** Menyiapkan pembayaran; mengembalikan alamat tujuan pembeli. */
    public function mulai(array $trx, array $produk): string;
}

function gerbangAktif(): Gerbang
{
    return (konfig('gerbang') ?: 'uji') === 'midtrans' ? new GerbangMidtrans() : new GerbangUji();
}

function modeUji(): bool
{
    return (konfig('gerbang') ?: 'uji') !== 'midtrans';
}

/**
 * Gerbang uji: tidak ada uang yang berpindah. Pembeli diarahkan ke halaman
 * simulasi yang memanggil ubahStatusTransaksi() — jalur yang sama dengan
 * webhook Midtrans.
 */
final class GerbangUji implements Gerbang
{
    public function nama(): string
    {
        return 'uji';
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
 * Midtrans Snap.
 *   mulai()   → POST {app}/snap/v1/transactions   → redirect_url
 *   status()  → GET  {api}/v2/{order_id}/status   (dipakai webhook & halaman selesai)
 * Kunci server hanya dipakai di sisi server, tidak pernah dikirim ke peramban.
 */
final class GerbangMidtrans implements Gerbang
{
    public function nama(): string
    {
        return 'midtrans';
    }

    private function konf(): array
    {
        return (array) konfig('midtrans');
    }

    public function siap(): bool
    {
        return trim((string) ($this->konf()['server_key'] ?? '')) !== '';
    }

    private function produksi(): bool
    {
        return !empty($this->konf()['produksi']);
    }

    private function urlApp(): string
    {
        $k = $this->konf();
        return rtrim((string) ($k['url_app'] ?? ($this->produksi() ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com')), '/');
    }

    private function urlApi(): string
    {
        $k = $this->konf();
        return rtrim((string) ($k['url_api'] ?? ($this->produksi() ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com')), '/');
    }

    private function kunciServer(): string
    {
        $kunci = trim((string) ($this->konf()['server_key'] ?? ''));
        if ($kunci === '') {
            throw new RuntimeException('Server key Midtrans belum diisi di konfig.php.');
        }
        return $kunci;
    }

    /** @return array{0:int,1:?array} kode HTTP dan isi JSON */
    private function panggil(string $metode, string $url, ?array $isi = null): array
    {
        $c = curl_init($url);
        $kepala = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($this->kunciServer() . ':'),
        ];
        curl_setopt_array($c, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $metode,
            CURLOPT_HTTPHEADER     => $kepala,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 25,
        ]);
        if ($isi !== null) {
            curl_setopt($c, CURLOPT_POSTFIELDS, json_encode($isi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        $jawab = curl_exec($c);
        $kode  = (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE);
        $galat = curl_error($c);
        curl_close($c);

        if ($jawab === false) {
            throw new RuntimeException('Tidak bisa menghubungi Midtrans: ' . $galat);
        }
        $json = json_decode((string) $jawab, true);
        return [$kode, is_array($json) ? $json : null];
    }

    public function mulai(array $trx, array $produk): string
    {
        $namaBarang = $produk['nama'] . ($produk['jenis'] === 'langganan' ? ' — bulan ke-' . (int) $trx['periode_ke'] : '');
        $isi = [
            'transaction_details' => [
                'order_id'     => $trx['kode_order'],
                'gross_amount' => (int) $trx['jumlah'],
            ],
            'item_details' => [[
                'id'       => mb_substr($produk['slug'], 0, 50),
                'price'    => (int) $trx['jumlah'],
                'quantity' => 1,
                'name'     => mb_substr($namaBarang, 0, 50),
            ]],
            'customer_details' => array_filter([
                'first_name' => mb_substr($trx['pembeli_nama'], 0, 50),
                'email'      => $trx['surel'],
                'phone'      => $trx['whatsapp'],
            ]),
            'callbacks' => [
                'finish' => urlSitus() . '/toko/selesai.php?o=' . rawurlencode($trx['kode_order']),
            ],
        ];

        [$kode, $json] = $this->panggil('POST', $this->urlApp() . '/snap/v1/transactions', $isi);
        if ($kode !== 201 || empty($json['redirect_url'])) {
            $alasan = $json['error_messages'][0] ?? ('HTTP ' . $kode);
            throw new RuntimeException('Midtrans menolak membuat pembayaran: ' . $alasan);
        }

        q(
            'UPDATE transaksi SET gerbang_ref = ?, gerbang_url = ? WHERE id = ?',
            [$json['token'] ?? null, mb_substr((string) $json['redirect_url'], 0, 255), $trx['id']]
        );
        return (string) $json['redirect_url'];
    }

    /** Status terkini langsung dari Midtrans, atau null kalau tidak dikenal. */
    public function status(string $kodeOrder): ?array
    {
        [$kode, $json] = $this->panggil('GET', $this->urlApi() . '/v2/' . rawurlencode($kodeOrder) . '/status');
        if ($kode !== 200 || !$json || ($json['status_code'] ?? '') === '404' || empty($json['transaction_status'])) {
            return null;
        }
        return $json;
    }

    public function tandaTanganSah(array $n): bool
    {
        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $f) {
            if (!isset($n[$f]) || !is_string($n[$f])) {
                return false;
            }
        }
        $harus = hash('sha512', $n['order_id'] . $n['status_code'] . $n['gross_amount'] . $this->kunciServer());
        return hash_equals($harus, $n['signature_key']);
    }

    /** Status Midtrans → status transaksi kita. null = belum ada perubahan. */
    public static function petaStatus(array $s): ?string
    {
        $status = (string) ($s['transaction_status'] ?? '');
        $fraud  = (string) ($s['fraud_status'] ?? 'accept');
        switch ($status) {
            case 'capture':
                return $fraud === 'accept' ? 'lunas' : null;   // challenge: tunggu tinjauan
            case 'settlement':
                return 'lunas';
            case 'deny':
            case 'cancel':
            case 'failure':
                return 'gagal';
            case 'expire':
                return 'kedaluwarsa';
            case 'refund':
                return 'refund';
            default:
                return null;   // pending, authorize, partial_refund, dll.
        }
    }
}

/**
 * Menerapkan status Midtrans (yang sudah dikonfirmasi lewat API status) ke
 * transaksi. Dipakai bersama oleh webhook dan halaman selesai.
 *
 * @return array{kode: int, pesan: string}
 */
function terapkanStatusMidtrans(array $trx, array $s, string $sumber, ?string $payload): array
{
    $jumlahMidtrans = (int) round((float) ($s['gross_amount'] ?? 0));
    if ($jumlahMidtrans !== (int) $trx['jumlah']) {
        q(
            'INSERT INTO transaksi_riwayat (transaksi_id, status_lama, status_baru, sumber, catatan, payload, dibuat_pada)
             VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [$trx['id'], $trx['status'], $trx['status'], $sumber,
             'DITOLAK: jumlah dari Midtrans ' . $jumlahMidtrans . ' tidak sama dengan ' . $trx['jumlah'], $payload]
        );
        return ['kode' => 409, 'pesan' => 'Jumlah tidak cocok'];
    }

    $baru = GerbangMidtrans::petaStatus($s);
    $tambahan = [
        'gerbang_ref' => isset($s['transaction_id']) ? mb_substr((string) $s['transaction_id'], 0, 80) : null,
        'metode'      => isset($s['payment_type']) ? mb_substr((string) $s['payment_type'], 0, 40) : null,
    ];
    if ($baru === null) {
        // Tidak ada perubahan status, tapi catat keterangan pembayarannya.
        if ($tambahan['gerbang_ref'] || $tambahan['metode']) {
            q(
                'UPDATE transaksi SET gerbang_ref = COALESCE(?, gerbang_ref), metode = COALESCE(?, metode) WHERE id = ?',
                [$tambahan['gerbang_ref'], $tambahan['metode'], $trx['id']]
            );
        }
        return ['kode' => 200, 'pesan' => 'Dicatat, belum ada perubahan status'];
    }

    $catatan = 'Midtrans: ' . ($s['transaction_status'] ?? '?') . (isset($s['payment_type']) ? ' · ' . $s['payment_type'] : '');
    ubahStatusTransaksi((int) $trx['id'], $baru, $sumber, $catatan, $payload, $tambahan);
    return ['kode' => 200, 'pesan' => 'Diterapkan'];
}
