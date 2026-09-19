<?php
declare(strict_types=1);

/* =============================================================================
   Logika affiliate: kode, cookie link, atribusi, komisi, saldo, penarikan.

   SATU-SATUNYA tempat aturan uang affiliate ditulis. Panel, portal mitra, dan
   toko semuanya memanggil fungsi di sini — jangan menyalin rumusnya ke tempat
   lain, supaya angka yang dilihat admin dan affiliator selalu sama.
   ============================================================================= */

require_once __DIR__ . '/inti.php';

/* ------------------------------------------------------------- Label */

const STATUS_AFFILIATE = [
    'menunggu'  => 'Menunggu persetujuan',
    'aktif'     => 'Aktif',
    'dibekukan' => 'Dibekukan',
    'ditolak'   => 'Ditolak',
];

const JENIS_PRODUK = [
    'sekali'    => ['label' => 'Sekali bayar', 'sub' => 'Kelas atau produk digital. Pembeli bayar sekali lewat checkout.'],
    'langganan' => ['label' => 'Langganan bulanan', 'sub' => 'Bulan pertama dibayar lewat checkout; bulan berikutnya dicatat di Transaksi.'],
    'penawaran' => ['label' => 'Lewat penawaran', 'sub' => 'Harga dibicarakan dulu. Pengunjung mengisi form, masuk ke Order jasa.'],
    'eksternal' => ['label' => 'Aplikasi lain', 'sub' => 'Dijual di aplikasi terpisah (mis. amanafinance). Tombol mengarah ke sana.'],
];

const STATUS_PRODUK = [
    'draf'  => 'Draf',
    'aktif' => 'Tayang',
    'arsip' => 'Diarsipkan',
];

/* Keadaan komisi seperti yang dilihat affiliator — diturunkan, tidak disimpan. */
const KEADAAN_KOMISI = [
    'tertahan'  => 'Tertahan',
    'siap'      => 'Siap ditarik',
    'diproses'  => 'Sedang diproses',
    'dicairkan' => 'Dicairkan',
    'batal'     => 'Dibatalkan',
];

const STATUS_PENARIKAN = [
    'diajukan' => 'Perlu dibayar',
    'dibayar'  => 'Dibayar',
    'ditolak'  => 'Ditolak',
];

/* Huruf untuk kode affiliate: tanpa yang mudah tertukar (0/O, 1/I/L). */
const HURUF_KODE = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
const NAMA_COOKIE_REF = 'inv_ref';

/* ------------------------------------------------------------- Kode */

function kodeAcak(int $panjang): string
{
    $hasil = '';
    $batas = strlen(HURUF_KODE) - 1;
    for ($i = 0; $i < $panjang; $i++) {
        $hasil .= HURUF_KODE[random_int(0, $batas)];
    }
    return $hasil;
}

/** Usulan kode affiliate yang belum dipakai siapa pun. */
function kodeAffiliateBaru(): string
{
    do {
        $kode = kodeAcak(6);
    } while (ambilNilai('SELECT 1 FROM affiliate WHERE kode = ?', [$kode]) !== null);
    return $kode;
}

/** Kode yang boleh dipakai: 4–16 huruf besar atau angka. */
function kodeSah(string $kode): bool
{
    return (bool) preg_match('/^[A-Z0-9]{4,16}$/', $kode);
}

function affiliateAktifDariKode(string $kode): ?array
{
    if (!kodeSah($kode)) {
        return null;
    }
    return ambilSatu("SELECT * FROM affiliate WHERE kode = ? AND status = 'aktif'", [$kode]);
}

function linkAffiliate(string $kode, ?string $slug = null): string
{
    return urlSitus() . '/r/' . $kode . ($slug ? '/' . $slug : '');
}

/* ------------------------------------------------------ Cookie link */

/* Isi cookie:  KODE.KEDALUWARSA.TANDATANGAN
   Tanggal kedaluwarsa ikut ditandatangani, jadi:
     - isinya tidak bisa dipalsukan atau diperpanjang;
     - mengubah setelan "lama cookie" hanya berlaku untuk klik berikutnya,
       cookie yang sudah tertanam tetap memakai umurnya sendiri. */

function tandaTanganRef(string $kode, int $kedaluwarsa): string
{
    return substr(hash_hmac('sha256', 'ref|' . $kode . '|' . $kedaluwarsa, rahasiaSistem()), 0, 32);
}

/** Domain cookie: invishar.com (berlaku juga untuk www dan subdomain). */
function domainCookie(): string
{
    $atur = konfig('domain_cookie');
    if (is_string($atur)) {
        return $atur;
    }
    $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    if ($host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
        return '';   // hanya untuk host ini
    }
    return (string) preg_replace('/^www\./', '', $host);
}

function tanamCookieRef(string $kode): void
{
    $hari = max(1, setelanAngka('affiliate.cookie_hari'));
    $kedaluwarsa = time() + $hari * 86400;
    $opsi = [
        'expires'  => $kedaluwarsa,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    if (domainCookie() !== '') {
        $opsi['domain'] = domainCookie();
    }
    setcookie(NAMA_COOKIE_REF, $kode . '.' . $kedaluwarsa . '.' . tandaTanganRef($kode, $kedaluwarsa), $opsi);
}

/** Kode dari cookie, hanya kalau tanda tangannya sah dan belum kedaluwarsa. */
function bacaCookieRef(): ?array
{
    $isi = (string) ($_COOKIE[NAMA_COOKIE_REF] ?? '');
    if (!preg_match('/^([A-Z0-9]{4,16})\.(\d{9,11})\.([a-f0-9]{32})$/', $isi, $m)) {
        return null;
    }
    [, $kode, $kedaluwarsa, $tanda] = $m;
    if (!hash_equals(tandaTanganRef($kode, (int) $kedaluwarsa), $tanda) || (int) $kedaluwarsa < time()) {
        return null;
    }
    return ['kode' => $kode, 'kedaluwarsa' => (int) $kedaluwarsa];
}

/** Affiliate aktif yang cookie-nya ada di peramban pengunjung ini. */
function affiliateDariCookie(): ?array
{
    $ref = bacaCookieRef();
    return $ref ? affiliateAktifDariKode($ref['kode']) : null;
}

function catatKlik(array $affiliate, ?array $produk): void
{
    $sidikIp = hash('sha256', ipPengunjung() . '|' . rahasiaSistem());
    $produkId = $produk ? (int) $produk['id'] : null;

    // Klik berulang dari pengunjung yang sama dalam 24 jam dihitung sekali.
    $sudah = ambilNilai(
        'SELECT 1 FROM affiliate_klik
          WHERE affiliate_id = ? AND produk_id <=> ? AND ip_hash = ? AND dibuat_pada > (NOW() - INTERVAL 1 DAY)
          LIMIT 1',
        [$affiliate['id'], $produkId, $sidikIp]
    );
    if ($sudah !== null) {
        return;
    }

    $asal = (string) parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST);
    q(
        'INSERT INTO affiliate_klik (affiliate_id, produk_id, ip_hash, referer, dibuat_pada) VALUES (?, ?, ?, ?, NOW())',
        [$affiliate['id'], $produkId, $sidikIp, $asal !== '' ? mb_substr($asal, 0, 255) : null]
    );
}

/* ------------------------------------------------------ Atribusi */

/**
 * Siapa affiliate yang berhak atas pembelian produk ini.
 *
 * Urutan: cookie (sah & belum kedaluwarsa) → kode cadangan dari alamat.
 * Sah hanya kalau affiliate aktif DAN produk membuka affiliate. Pembeli yang
 * surel atau WhatsApp-nya sama dengan milik affiliate ditandai beli-sendiri:
 * affiliate tetap tercatat untuk jejak, tapi komisi tidak dibuat.
 */
function atribusi(array $produk, string $surel, string $whatsapp, string $refCadangan = ''): array
{
    $hasil = ['affiliate' => null, 'beli_sendiri' => false];
    if (!(int) $produk['affiliate_aktif']) {
        return $hasil;
    }

    $affiliate = affiliateDariCookie();
    if ($affiliate === null && $refCadangan !== '') {
        $affiliate = affiliateAktifDariKode(strtoupper(trim($refCadangan)));
    }
    if ($affiliate === null) {
        return $hasil;
    }

    $hasil['affiliate'] = $affiliate;
    $surelSama = normalSurel($surel) !== '' && normalSurel($surel) === normalSurel($affiliate['surel']);
    $waSama    = normalWa($whatsapp) !== '' && normalWa($whatsapp) === normalWa($affiliate['whatsapp']);
    $hasil['beli_sendiri'] = $surelSama || $waSama;

    return $hasil;
}

/* --------------------------------------------------------- Komisi */

function bulanBerulang(array $produk): int
{
    $khusus = $produk['fee_bulan_berulang'];
    return $khusus !== null && (int) $khusus > 0 ? (int) $khusus : max(1, setelanAngka('affiliate.bulan_berulang'));
}

/**
 * Besar komisi dari satu pembayaran.
 * persen: dibulatkan ke bawah ke rupiah penuh. tetap: tidak pernah melebihi
 * nilai pembayarannya sendiri.
 */
function hitungKomisi(string $feeJenis, $feeNilai, int $dasar): int
{
    if ($dasar <= 0) {
        return 0;
    }
    if ($feeJenis === 'tetap') {
        return max(0, min($dasar, (int) round((float) $feeNilai)));
    }
    $basisPoin = (int) round((float) $feeNilai * 100);   // 12,5% → 1250
    return max(0, intdiv($dasar * $basisPoin, 10000));
}

/** "20%", "12,5%", "Rp 100.000" */
function teksFee(string $feeJenis, $feeNilai): string
{
    if ($feeJenis === 'tetap') {
        return rupiah((int) round((float) $feeNilai));
    }
    $n = (float) $feeNilai;
    return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',') . '%';
}

/** Kalimat utuh untuk affiliator: "20% per penjualan", "Rp 100.000 per bulan, hingga 12 bulan". */
function teksKomisiProduk(array $produk): string
{
    $fee = teksFee($produk['fee_jenis'], $produk['fee_nilai']);
    if ($produk['jenis'] === 'langganan') {
        return $fee . ' per pembayaran bulanan, hingga ' . bulanBerulang($produk) . ' bulan';
    }
    return $fee . ' per penjualan';
}

/**
 * Membuat komisi untuk transaksi yang baru lunas. Aman dipanggil berkali-kali:
 * transaksi yang sudah punya komisi dikembalikan apa adanya.
 * Dipanggil dari dalam ubahStatusTransaksi(), di bawah kunci baris transaksi.
 */
function buatKomisi(array $trx): ?int
{
    if (!$trx['affiliate_id'] || (int) $trx['beli_sendiri'] === 1) {
        return null;
    }

    $ada = ambilNilai("SELECT id FROM komisi WHERE transaksi_id = ? AND jenis = 'komisi'", [$trx['id']]);
    if ($ada !== null) {
        return (int) $ada;
    }

    $affiliate = ambilSatu('SELECT * FROM affiliate WHERE id = ?', [$trx['affiliate_id']]);
    $produk    = ambilSatu('SELECT * FROM produk WHERE id = ?', [$trx['produk_id']]);
    if (!$affiliate || $affiliate['status'] !== 'aktif' || !$produk || !(int) $produk['affiliate_aktif']) {
        return null;
    }
    if ($produk['jenis'] === 'langganan' && (int) $trx['periode_ke'] > bulanBerulang($produk)) {
        return null;
    }

    $jumlah = hitungKomisi($produk['fee_jenis'], $produk['fee_nilai'], (int) $trx['jumlah']);
    if ($jumlah <= 0) {
        return null;
    }

    $masaTahan = max(0, setelanAngka('affiliate.masa_tahan_hari'));
    q(
        'INSERT INTO komisi (affiliate_id, transaksi_id, produk_id, jenis, dasar, fee_jenis, fee_nilai,
                             periode_ke, jumlah, status, cair_pada, dibuat_pada)
         VALUES (?, ?, ?, \'komisi\', ?, ?, ?, ?, ?, \'berlaku\', NOW() + INTERVAL ' . $masaTahan . ' DAY, NOW())',
        [
            $affiliate['id'], $trx['id'], $produk['id'], (int) $trx['jumlah'],
            $produk['fee_jenis'], $produk['fee_nilai'],
            $produk['jenis'] === 'langganan' ? (int) $trx['periode_ke'] : null,
            $jumlah,
        ]
    );
    return (int) db()->lastInsertId();
}

/**
 * Transaksi dikembalikan dananya. Komisi yang belum ditarik dibatalkan;
 * yang sudah masuk pengajuan penarikan dipotong lewat penyesuaian negatif.
 */
function batalkanKomisi(array $trx, string $alasan): void
{
    $k = ambilSatu("SELECT * FROM komisi WHERE transaksi_id = ? AND jenis = 'komisi' FOR UPDATE", [$trx['id']]);
    if (!$k || $k['status'] === 'batal') {
        return;
    }

    if ($k['penarikan_id'] === null) {
        q("UPDATE komisi SET status = 'batal', catatan = ? WHERE id = ?", [mb_substr($alasan, 0, 255), $k['id']]);
        return;
    }

    $sudahDisesuaikan = ambilNilai("SELECT 1 FROM komisi WHERE transaksi_id = ? AND jenis = 'penyesuaian'", [$trx['id']]);
    if ($sudahDisesuaikan === null) {
        q(
            'INSERT INTO komisi (affiliate_id, transaksi_id, produk_id, jenis, dasar, jumlah, status, cair_pada, catatan, dibuat_pada)
             VALUES (?, ?, ?, \'penyesuaian\', ?, ?, \'berlaku\', NOW(), ?, NOW())',
            [$k['affiliate_id'], $trx['id'], $k['produk_id'], (int) $k['dasar'], -1 * (int) $k['jumlah'], mb_substr($alasan, 0, 255)]
        );
    }
}

/**
 * Admin mencairkan komisi tertahan lebih awal — satu baris, atau semuanya
 * milik satu affiliate. Mengembalikan jumlah baris yang dicairkan.
 */
function cairkanSekarang(int $affiliateId, ?int $komisiId = null): int
{
    $sql = "UPDATE komisi SET cair_pada = NOW(), dipercepat_pada = NOW()
             WHERE affiliate_id = ? AND status = 'berlaku' AND penarikan_id IS NULL AND cair_pada > NOW()";
    $isi = [$affiliateId];
    if ($komisiId !== null) {
        $sql .= ' AND id = ?';
        $isi[] = $komisiId;
    }
    return q($sql, $isi)->rowCount();
}

/* ---------------------------------------------------------- Saldo */

function saldoAffiliate(int $affiliateId): array
{
    $k = ambilSatu(
        "SELECT
           COALESCE(SUM(CASE WHEN penarikan_id IS NULL AND cair_pada >  NOW() THEN jumlah END), 0) AS tertahan,
           COALESCE(SUM(CASE WHEN penarikan_id IS NULL AND cair_pada <= NOW() THEN jumlah END), 0) AS siap,
           COALESCE(SUM(CASE WHEN jenis = 'komisi' THEN jumlah END), 0)                           AS diperoleh
         FROM komisi WHERE affiliate_id = ? AND status = 'berlaku'",
        [$affiliateId]
    );
    $t = ambilSatu(
        "SELECT
           COALESCE(SUM(CASE WHEN status = 'diajukan' THEN jumlah END), 0) AS diproses,
           COALESCE(SUM(CASE WHEN status = 'dibayar'  THEN jumlah END), 0) AS dicairkan
         FROM penarikan WHERE affiliate_id = ?",
        [$affiliateId]
    );
    return [
        'tertahan'  => (int) $k['tertahan'],
        'siap'      => (int) $k['siap'],
        'diperoleh' => (int) $k['diperoleh'],
        'diproses'  => (int) $t['diproses'],
        'dicairkan' => (int) $t['dicairkan'],
    ];
}

/**
 * Kolom SQL tambahan untuk menurunkan keadaan komisi. Pakai bersama
 * keadaanKomisi(). Butuh alias k (komisi) dan tp (penarikan, LEFT JOIN).
 */
const SQL_KEADAAN_KOMISI = "(k.cair_pada <= NOW()) AS sudah_cair, tp.status AS tarik_status";

function keadaanKomisi(array $k): string
{
    if ($k['status'] === 'batal') {
        return 'batal';
    }
    if ($k['penarikan_id'] !== null) {
        return ($k['tarik_status'] ?? '') === 'dibayar' ? 'dicairkan' : 'diproses';
    }
    return (int) $k['sudah_cair'] === 1 ? 'siap' : 'tertahan';
}

/* ------------------------------------------------------ Penarikan */

/**
 * Affiliator mengajukan penarikan seluruh saldo siap. Semua pemeriksaan dan
 * penguncian baris komisi terjadi dalam satu transaksi basis data, jadi dua
 * klik bersamaan tidak bisa menarik saldo yang sama dua kali.
 *
 * @return array{baik: bool, pesan: string, id?: int}
 */
function ajukanPenarikan(int $affiliateId): array
{
    return dalamTransaksi(function () use ($affiliateId): array {
        $a = ambilSatu('SELECT * FROM affiliate WHERE id = ? FOR UPDATE', [$affiliateId]);
        if (!$a || $a['status'] !== 'aktif') {
            return ['baik' => false, 'pesan' => 'Akun sedang tidak aktif, jadi penarikan belum bisa diajukan.'];
        }
        if (trim((string) $a['bank_nama']) === '' || trim((string) $a['bank_nomor']) === '' || trim((string) $a['bank_atas_nama']) === '') {
            return ['baik' => false, 'pesan' => 'Lengkapi data rekening di halaman Profil dulu.'];
        }
        if (ambilNilai("SELECT 1 FROM penarikan WHERE affiliate_id = ? AND status = 'diajukan'", [$affiliateId]) !== null) {
            return ['baik' => false, 'pesan' => 'Masih ada penarikan yang sedang diproses. Tunggu sampai selesai dulu.'];
        }

        $kini = (string) ambilNilai('SELECT NOW()');
        $min  = max(0, setelanAngka('affiliate.min_tarik'));
        $siap = (int) ambilNilai(
            "SELECT COALESCE(SUM(jumlah), 0) FROM komisi
              WHERE affiliate_id = ? AND status = 'berlaku' AND penarikan_id IS NULL AND cair_pada <= ?",
            [$affiliateId, $kini]
        );
        if ($siap <= 0) {
            return ['baik' => false, 'pesan' => 'Belum ada saldo yang siap ditarik.'];
        }
        if ($siap < $min) {
            return ['baik' => false, 'pesan' => 'Saldo siap ' . rupiah($siap) . ', minimal penarikan ' . rupiah($min) . '.'];
        }

        q(
            'INSERT INTO penarikan (affiliate_id, jumlah, status, bank_nama, bank_nomor, bank_atas_nama, diajukan_pada)
             VALUES (?, 0, \'diajukan\', ?, ?, ?, NOW())',
            [$affiliateId, $a['bank_nama'], $a['bank_nomor'], $a['bank_atas_nama']]
        );
        $id = (int) db()->lastInsertId();

        q(
            "UPDATE komisi SET penarikan_id = ?
              WHERE affiliate_id = ? AND status = 'berlaku' AND penarikan_id IS NULL AND cair_pada <= ?",
            [$id, $affiliateId, $kini]
        );
        $jumlah = (int) ambilNilai('SELECT COALESCE(SUM(jumlah), 0) FROM komisi WHERE penarikan_id = ?', [$id]);
        if ($jumlah !== $siap) {
            // Tidak seharusnya terjadi di bawah kunci; batalkan daripada salah bayar.
            throw new RuntimeException('Saldo berubah saat diproses. Coba lagi.');
        }
        q('UPDATE penarikan SET jumlah = ? WHERE id = ?', [$jumlah, $id]);

        return ['baik' => true, 'pesan' => 'Penarikan ' . rupiah($jumlah) . ' diajukan.', 'id' => $id];
    });
}

/** Admin menolak: komisi yang terkunci dilepas dan kembali ke saldo. */
function tolakPenarikan(int $id, string $alasan): bool
{
    return dalamTransaksi(function () use ($id, $alasan): bool {
        $p = ambilSatu('SELECT * FROM penarikan WHERE id = ? FOR UPDATE', [$id]);
        if (!$p || $p['status'] !== 'diajukan') {
            return false;
        }
        q('UPDATE komisi SET penarikan_id = NULL WHERE penarikan_id = ?', [$id]);
        q("UPDATE penarikan SET status = 'ditolak', catatan = ?, diproses_pada = NOW() WHERE id = ?", [$alasan, $id]);
        return true;
    });
}

/** Admin sudah mentransfer. */
function bayarPenarikan(int $id, string $referensi, string $catatan = ''): bool
{
    return dalamTransaksi(function () use ($id, $referensi, $catatan): bool {
        $p = ambilSatu('SELECT * FROM penarikan WHERE id = ? FOR UPDATE', [$id]);
        if (!$p || $p['status'] !== 'diajukan') {
            return false;
        }
        q(
            "UPDATE penarikan SET status = 'dibayar', referensi = ?, catatan = ?, diproses_pada = NOW() WHERE id = ?",
            [$referensi, $catatan !== '' ? $catatan : null, $id]
        );
        return true;
    });
}

/* --------------------------------------------------------- Lain-lain */

/** "Budi Santoso" → "B*** S***" — untuk ditampilkan ke affiliator. */
function samarkanNama(string $nama): string
{
    $kata = preg_split('/\s+/u', trim($nama)) ?: [];
    $hasil = [];
    foreach (array_slice($kata, 0, 3) as $k) {
        if ($k !== '') {
            $hasil[] = mb_strtoupper(mb_substr($k, 0, 1)) . '***';
        }
    }
    return $hasil ? implode(' ', $hasil) : 'Pembeli';
}

/** Daftar bank yang sering dipakai — hanya usulan, isian tetap bebas. */
const DAFTAR_BANK = [
    'BCA', 'BRI', 'BNI', 'Mandiri', 'BSI (Bank Syariah Indonesia)', 'CIMB Niaga', 'Permata',
    'Danamon', 'BTN', 'Muamalat', 'Jago', 'SeaBank', 'Blu (BCA Digital)', 'DANA', 'GoPay', 'OVO', 'ShopeePay',
];
