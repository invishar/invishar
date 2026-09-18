<?php
declare(strict_types=1);

/* =============================================================================
   Pondasi panel: konfigurasi, basis data, sesi, dan alat bantu.
   Setiap halaman panel diawali dengan:  require __DIR__ . '/inc/awal.php';
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
        pergi('masuk.php');
    }
    return $pengguna;
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

function slugkan(string $teks): string
{
    $slug = strtolower(trim($teks));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    return trim($slug, '-');
}

function waktuIndo(?string $waktu): string
{
    if (!$waktu) {
        return '—';
    }
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t = strtotime($waktu);
    return date('j', $t) . ' ' . $bulan[(int)date('n', $t)] . ' ' . date('Y · H:i', $t);
}

function rupiah(?int $angka): string
{
    return $angka === null ? '—' : 'Rp ' . number_format($angka, 0, ',', '.');
}

/** Jejak perubahan, supaya suatu hari bisa ditelusuri "kenapa ini berubah". */
function catatLog(string $aksi, string $objek = ''): void
{
    $pengguna = penggunaKini();
    q(
        'INSERT INTO log_aktivitas (pengguna_id, aksi, objek, dibuat_pada) VALUES (?, ?, ?, NOW())',
        [$pengguna['id'] ?? null, $aksi, $objek]
    );
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
