<?php
declare(strict_types=1);

/* =============================================================================
   Penerima form kontak invishar.com.

   Satu-satunya halaman panel yang boleh dibuka tanpa login, jadi pengamanannya
   berdiri sendiri: hanya asal yang terdaftar, ada perangkap robot, dan dibatasi
   jumlahnya per alamat IP.
   ============================================================================= */
require __DIR__ . '/inc/awal.php';

$asal = $_SERVER['HTTP_ORIGIN'] ?? '';
$diizinkan = (array) konfig('asal_diizinkan');

if ($asal !== '' && in_array($asal, $diizinkan, true)) {
    header('Access-Control-Allow-Origin: ' . $asal);
    header('Vary: Origin');
}
header('Content-Type: application/json; charset=utf-8');

function jawab(int $kode, array $isi): void
{
    http_response_code($kode);
    echo json_encode($isi, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 86400');
    jawab(204, []);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jawab(405, ['galat' => 'Metode tidak didukung.']);
}

if ($asal !== '' && !in_array($asal, $diizinkan, true)) {
    jawab(403, ['galat' => 'Asal permintaan tidak dikenal.']);
}

// Perangkap robot: bidang ini tersembunyi di form, manusia tidak pernah mengisinya.
if (trim((string) ($_POST['alamat'] ?? '')) !== '') {
    jawab(200, ['baik' => true]);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$baruSaja = (int) ambilNilai(
    'SELECT COUNT(*) FROM order_jasa WHERE ip = ? AND dibuat_pada > (NOW() - INTERVAL 1 HOUR)',
    [$ip]
);
if ($baruSaja >= 5) {
    jawab(429, ['galat' => 'Terlalu banyak pesan dalam satu jam. Coba lagi nanti.']);
}

$nama      = trim((string) ($_POST['nama'] ?? ''));
$surel     = trim((string) ($_POST['surel'] ?? ''));
$kebutuhan = trim((string) ($_POST['pesan'] ?? ''));
$whatsapp  = trim((string) ($_POST['whatsapp'] ?? ''));

if ($nama === '' || $kebutuhan === '') {
    jawab(422, ['galat' => 'Nama dan pesan wajib diisi.']);
}
if ($surel !== '' && !filter_var($surel, FILTER_VALIDATE_EMAIL)) {
    jawab(422, ['galat' => 'Alamat surel tidak sah.']);
}

// Dipotong sesuai lebar kolom, supaya kiriman panjang tidak ditolak basis data.
$nama      = mb_substr($nama, 0, 120);
$surel     = mb_substr($surel, 0, 160);
$whatsapp  = mb_substr($whatsapp, 0, 40);
$kebutuhan = mb_substr($kebutuhan, 0, 5000);

q(
    'INSERT INTO order_jasa (nama, surel, whatsapp, kebutuhan, sumber, ip, status, dibuat_pada, diperbarui_pada)
     VALUES (?, ?, ?, ?, \'form\', ?, \'baru\', NOW(), NOW())',
    [$nama, $surel, $whatsapp, $kebutuhan, $ip]
);
$id = (int) db()->lastInsertId();

q('INSERT INTO order_riwayat (order_id, status_baru, catatan, dibuat_pada) VALUES (?, \'baru\', ?, NOW())',
    [$id, 'Masuk dari form invishar.com']);

jawab(201, ['baik' => true]);
