<?php
declare(strict_types=1);

/* =============================================================================
   Inti bersama: konfigurasi, basis data, alat bantu, CSRF, migrasi, setelan.

   Dipakai oleh tiga pintu yang berbeda:
     panel/  — admin          → lewat inc/awal.php (menambah sesi & login admin)
     mitra/  — affiliator     → lewat mitra/inc/awal.php (sesi & login sendiri)
     toko/   — publik         → lewat toko/inc/awal.php (tanpa sesi)

   Berkas ini TIDAK memulai sesi. Fungsi yang memakai $_SESSION (CSRF, pesan)
   baru boleh dipanggil setelah pintu masing-masing memulai sesinya.
   ============================================================================= */

$berkasKonfig = __DIR__ . '/konfig.php';
if (!is_file($berkasKonfig)) {
    http_response_code(500);
    exit('Konfigurasi belum ada. Salin inc/konfig.contoh.php menjadi inc/konfig.php lalu isi datanya.');
}

/** @var array $KONFIG */
$KONFIG = require $berkasKonfig;

date_default_timezone_set($KONFIG['zona_waktu'] ?? 'Asia/Jakarta');
mb_internal_encoding('UTF-8');

/* ----------------------------------------------------------- Basis data */

function konfig(?string $kunci = null)
{
    global $KONFIG;
    return $kunci === null ? $KONFIG : ($KONFIG[$kunci] ?? null);
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $d = konfig('db');
        $pdo = new PDO(
            "mysql:host={$d['host']};dbname={$d['nama']};charset={$d['charset']}",
            $d['pengguna'],
            $d['sandi'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        // Jam basis data disamakan dengan jam PHP, supaya NOW() dan date()
        // tidak berselisih — penting untuk masa tahan komisi.
        $pdo->exec("SET time_zone = '" . date('P') . "'");
        // Kolasi koneksi disamakan dengan tabel (utf8mb4_unicode_ci). Tanpa ini,
        // teks literal di kueri memakai kolasi bawaan server — di MariaDB 11
        // berbeda — dan UNION/perbandingan dengan kolom tabel bisa ditolak.
        if (($d['charset'] ?? 'utf8mb4') === 'utf8mb4') {
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
    }
    return $pdo;
}

/** Jalankan kueri dengan parameter terikat, kembalikan statement-nya. */
function q(string $sql, array $isi = []): PDOStatement
{
    $s = db()->prepare($sql);
    $s->execute($isi);
    return $s;
}

function ambilSatu(string $sql, array $isi = []): ?array
{
    $baris = q($sql, $isi)->fetch();
    return $baris === false ? null : $baris;
}

function ambilSemua(string $sql, array $isi = []): array
{
    return q($sql, $isi)->fetchAll();
}

function ambilNilai(string $sql, array $isi = [])
{
    $nilai = q($sql, $isi)->fetchColumn();
    return $nilai === false ? null : $nilai;
}

/**
 * Jalankan $kerja di dalam transaksi basis data. Kalau terjadi galat, semua
 * perubahan dibatalkan dan galatnya dilempar lagi.
 */
function dalamTransaksi(callable $kerja)
{
    $pdo = db();
    if ($pdo->inTransaction()) {
        return $kerja();
    }
    $pdo->beginTransaction();
    try {
        $hasil = $kerja();
        $pdo->commit();
        return $hasil;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Menjalankan teks SQL berisi banyak perintah.
 * Baris komentar dibuang dulu — kalau tidak, potongan yang diawali komentar
 * ikut terbuang beserta perintah di bawahnya.
 */
function jalankanSql(string $sql): void
{
    foreach (preg_split('/;\s*[\r\n]+/', $sql) ?: [] as $perintah) {
        $perintah = trim((string) preg_replace('/^\s*--.*$/m', '', $perintah));
        if ($perintah !== '') {
            db()->exec($perintah);
        }
    }
}

/* -------------------------------------------------------------- Migrasi */

/* Tabel baru ditambahkan lewat berkas di panel/migrasi/NNN_nama.sql.
   pasang.php menolak jalan setelah ada akun, jadi inilah jalan satu-satunya
   untuk memperbarui basis data yang sudah terisi. */

function folderMigrasi(): string
{
    return dirname(__DIR__) . '/migrasi';
}

/** Nama semua berkas migrasi, berurutan. */
function daftarMigrasi(): array
{
    $nama = array_map('basename', glob(folderMigrasi() . '/*.sql') ?: []);
    sort($nama, SORT_STRING);
    return $nama;
}

function migrasiTerpasang(): array
{
    db()->exec(
        'CREATE TABLE IF NOT EXISTS migrasi (
           nama            VARCHAR(120) NOT NULL PRIMARY KEY,
           dijalankan_pada DATETIME     NOT NULL
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    return array_column(ambilSemua('SELECT nama FROM migrasi'), 'nama');
}

/** Migrasi yang belum dijalankan. Dihitung sekali per permintaan. */
function migrasiTertunda(bool $segarkan = false): array
{
    static $tertunda = null;
    if ($tertunda === null || $segarkan) {
        $tertunda = array_values(array_diff(daftarMigrasi(), migrasiTerpasang()));
    }
    return $tertunda;
}

/** Jalankan semua migrasi yang tertunda. Mengembalikan nama yang dijalankan. */
function jalankanMigrasi(): array
{
    $dijalankan = [];
    foreach (migrasiTertunda(true) as $nama) {
        jalankanSql((string) file_get_contents(folderMigrasi() . '/' . $nama));
        q('INSERT INTO migrasi (nama, dijalankan_pada) VALUES (?, NOW())', [$nama]);
        $dijalankan[] = $nama;
    }
    migrasiTertunda(true);
    return $dijalankan;
}

/** Tabel sistem penjualan & affiliate sudah siap dipakai? */
function penjualanSiap(): bool
{
    try {
        return migrasiTertunda() === [];
    } catch (PDOException $e) {
        return false;
    }
}

/* -------------------------------------------------------------- Setelan */

/* Nilai bawaan setiap setelan. Tabel `setelan` hanya menyimpan yang pernah
   diubah admin — kalau barisnya tidak ada, nilai di sini yang berlaku. */
const SETELAN_BAWAAN = [
    'affiliate.cookie_hari'     => '10',
    'affiliate.masa_tahan_hari' => '3',
    'affiliate.min_tarik'       => '100000',
    'affiliate.bulan_berulang'  => '12',
    'affiliate.syarat'          => "1. Komisi hanya dihitung dari penjualan yang lunas melalui link Anda.\n2. Membeli lewat link sendiri tidak menghasilkan komisi.\n3. Dilarang memasang iklan berbayar memakai nama Invishar atau nama produknya.\n4. Dilarang menjanjikan hal yang tidak tertulis di halaman produk.\n5. Komisi dari transaksi yang dibatalkan atau dikembalikan dananya ikut dibatalkan.\n6. Invishar berhak membekukan akun yang melanggar ketentuan ini.",
];

/** Tembolok setelan untuk satu permintaan; dibaca sekali dari basis data. */
function &tembolokSetelan(): array
{
    static $simpanan = null;
    if ($simpanan === null) {
        $simpanan = [];
        try {
            foreach (ambilSemua('SELECT kunci, nilai FROM setelan') as $b) {
                $simpanan[$b['kunci']] = (string) $b['nilai'];
            }
        } catch (PDOException $e) {
            // Tabel belum ada — nilai bawaan yang berlaku.
        }
    }
    return $simpanan;
}

function setelan(string $kunci): string
{
    $simpanan = &tembolokSetelan();
    return $simpanan[$kunci] ?? (SETELAN_BAWAAN[$kunci] ?? '');
}

function setelanAngka(string $kunci): int
{
    return (int) setelan($kunci);
}

function simpanSetelan(string $kunci, string $nilai): void
{
    q(
        'INSERT INTO setelan (kunci, nilai, diperbarui_pada) VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE nilai = VALUES(nilai), diperbarui_pada = NOW()',
        [$kunci, $nilai]
    );
    $simpanan = &tembolokSetelan();
    $simpanan[$kunci] = $nilai;   // langsung berlaku di permintaan yang sama
}

/**
 * Kunci rahasia untuk menandatangani cookie affiliate dan token lain.
 * Dibuat acak sekali lalu disimpan di basis data — tidak perlu diisi
 * manual di konfig.php, dan tidak pernah terlihat oleh peramban.
 */
function rahasiaSistem(): string
{
    static $rahasia = null;
    if ($rahasia === null) {
        $rahasia = (string) ambilNilai("SELECT nilai FROM setelan WHERE kunci = 'sistem.rahasia'");
        if (strlen($rahasia) < 32) {
            q(
                "INSERT IGNORE INTO setelan (kunci, nilai, diperbarui_pada) VALUES ('sistem.rahasia', ?, NOW())",
                [bin2hex(random_bytes(32))]
            );
            $rahasia = (string) ambilNilai("SELECT nilai FROM setelan WHERE kunci = 'sistem.rahasia'");
        }
    }
    return $rahasia;
}

/* ------------------------------------------------------------------ CSRF */

function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrfInput(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrfToken()) . '">';
}

/** Dipanggil di awal setiap penanganan POST. Menghentikan permintaan palsu. */
function periksaCsrf(): void
{
    $dikirim = $_POST['csrf'] ?? '';
    if (!is_string($dikirim) || !hash_equals(csrfToken(), $dikirim)) {
        http_response_code(400);
        exit('Permintaan tidak sah. Muat ulang halaman lalu coba lagi.');
    }
}

/* ------------------------------------------------------------- Alamat URL */

/* Akar area yang sedang berjalan: '/panel', '/mitra', '/toko', atau kosong
   kalau dipasang di akar subdomain. Dihitung dari jalur skrip supaya bisa
   berpindah tempat tanpa satu pun tautan perlu disunting. */
define('AKAR', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/'));

function tautan(string $jalur = ''): string
{
    return AKAR . '/' . ltrim($jalur, '/');
}

/**
 * Alamat berkas aset panel berikut penanda versi dari waktu ubahnya.
 *
 * Server menyajikan CSS dan JS dengan `Cache-Control: immutable` selama 30
 * hari — peramban tidak memeriksa ulang bahkan saat di-refresh. Tanpa penanda
 * ini, tampilan lama bisa bertahan berminggu-minggu setelah kodenya berubah.
 */
function aset(string $berkas): string
{
    $penuh = dirname(__DIR__) . '/aset/' . $berkas;
    return tautan('aset/' . $berkas) . '?v=' . (is_file($penuh) ? filemtime($penuh) : 0);
}

/** Alamat invishar.com (tanpa garis miring di akhir). */
function urlSitus(): string
{
    return rtrim((string) (konfig('situs_url') ?: 'https://invishar.com'), '/');
}

/* ------------------------------------------------------------ Alat bantu */

function e(?string $teks): string
{
    return htmlspecialchars($teks ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pergi(string $tujuan): void
{
    header('Location: ' . $tujuan);
    exit;
}

/** Pesan singkat yang tampil sekali di halaman berikutnya. */
function pesan(string $teks, string $jenis = 'baik'): void
{
    $_SESSION['pesan'][] = ['teks' => $teks, 'jenis' => $jenis];
}

function ambilPesan(): array
{
    $daftar = $_SESSION['pesan'] ?? [];
    unset($_SESSION['pesan']);
    return $daftar;
}

function masukan(string $nama, string $bawaan = ''): string
{
    $nilai = $_POST[$nama] ?? $bawaan;
    return is_string($nilai) ? trim($nilai) : $bawaan;
}

/** "Rp 1.250.000", "1250000", "1.250.000,00" → 1250000. Kosong → null. */
function angkaRupiah(string $teks): ?int
{
    $teks = trim($teks);
    if ($teks === '') {
        return null;
    }
    $teks = (string) preg_replace('/,\d{1,2}$/', '', $teks);   // buang sen
    $angka = preg_replace('/\D/', '', $teks);
    return $angka === '' ? null : (int) $angka;
}

function slugkan(string $teks): string
{
    $slug = strtolower(trim($teks));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    return trim($slug, '-');
}

const BULAN_PENDEK = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

function waktuIndo(?string $waktu): string
{
    if (!$waktu) {
        return '—';
    }
    $t = strtotime($waktu);
    return date('j', $t) . ' ' . BULAN_PENDEK[(int) date('n', $t)] . ' ' . date('Y · H:i', $t);
}

function tanggalIndo(?string $waktu): string
{
    if (!$waktu) {
        return '—';
    }
    $t = strtotime($waktu);
    return date('j', $t) . ' ' . BULAN_PENDEK[(int) date('n', $t)] . ' ' . date('Y', $t);
}

function rupiah(?int $angka): string
{
    if ($angka === null) {
        return '—';
    }
    return ($angka < 0 ? '−Rp ' : 'Rp ') . number_format(abs($angka), 0, ',', '.');
}

/** Nomor WhatsApp dalam bentuk seragam: 0812… / +62812… / 62812… → 62812… */
function normalWa(?string $nomor): string
{
    $angka = (string) preg_replace('/\D/', '', (string) $nomor);
    if ($angka === '') {
        return '';
    }
    if ($angka[0] === '0') {
        $angka = '62' . substr($angka, 1);
    } elseif (strpos($angka, '8') === 0) {
        $angka = '62' . $angka;
    }
    return $angka;
}

function normalSurel(?string $surel): string
{
    return mb_strtolower(trim((string) $surel));
}

/** Balas dalam bentuk JSON lalu berhenti. */
function jawabJson(int $kode, array $isi): void
{
    http_response_code($kode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($isi, JSON_UNESCAPED_UNICODE);
    exit;
}

function ipPengunjung(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/** Jejak perubahan, supaya suatu hari bisa ditelusuri "kenapa ini berubah". */
function catatLog(string $aksi, string $objek = ''): void
{
    $pengguna = function_exists('penggunaKini') ? penggunaKini() : null;
    q(
        'INSERT INTO log_aktivitas (pengguna_id, aksi, objek, dibuat_pada) VALUES (?, ?, ?, NOW())',
        [$pengguna['id'] ?? null, $aksi, mb_substr($objek, 0, 160)]
    );
}
