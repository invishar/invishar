<?php
declare(strict_types=1);

/* =============================================================================
   Perantara ke 9router untuk tombol bantuan AI di panel.

   Semua panggilan AI lewat sini, tidak pernah langsung dari peramban. Dua
   alasannya: kunci API tidak boleh sampai ke sisi pengunjung, dan endpoint
   9router yang berbasis http:// akan diblokir peramban sebagai mixed content
   karena panel berjalan di https://.

   Keluaran selalu JSON: {"baik":true,"hasil":…} atau {"galat":"…"}.
   ============================================================================= */
require __DIR__ . '/inc/awal.php';
wajibMasuk();

header('Content-Type: application/json; charset=utf-8');

function jawab(int $kode, array $isi): void
{
    http_response_code($kode);
    echo json_encode($isi, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jawab(405, ['galat' => 'Metode tidak didukung.']);
}

$minta = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($minta)) {
    jawab(400, ['galat' => 'Permintaan tidak terbaca.']);
}

if (!hash_equals(csrfToken(), (string) ($minta['csrf'] ?? ''))) {
    jawab(400, ['galat' => 'Permintaan tidak sah. Muat ulang halaman.']);
}

$ai = konfig('ai') ?: [];
if (empty($ai['kunci'])) {
    jawab(503, ['galat' => 'Bantuan AI belum diaktifkan di konfig.php.']);
}

/* Pembatas pemakaian per sesi — menjaga dari klik beruntun dan dari skrip
   yang macet memanggil berulang-ulang. */
$jam = (int) date('YmdH');
if (($_SESSION['ai_jam'] ?? 0) !== $jam) {
    $_SESSION['ai_jam'] = $jam;
    $_SESSION['ai_hitung'] = 0;
}
if (++$_SESSION['ai_hitung'] > 60) {
    jawab(429, ['galat' => 'Sudah 60 permintaan AI dalam satu jam. Tunggu sebentar.']);
}

/* ------------------------------------------------------------- Pemanggilan */

function panggilAI(array $pesan, int $maxToken): string
{
    $ai = konfig('ai');

    $ch = curl_init(rtrim($ai['endpoint'], '/') . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => (int) ($ai['batas_detik'] ?? 90),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $ai['kunci'],
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model'       => $ai['model'] ?? 'amana',
            'messages'    => $pesan,
            'temperature' => 0.7,
            // Model di balik combo ini mengeluarkan token penalaran lebih dulu.
            // Jatah yang terlalu ketat habis di sana dan jawabannya jadi kosong.
            'max_tokens'  => $maxToken,
            'stream'      => false,
        ], JSON_UNESCAPED_UNICODE),
    ]);

    $hasil = curl_exec($ch);
    $kode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $galat = curl_error($ch);
    curl_close($ch);

    if ($hasil === false) {
        jawab(502, ['galat' => 'Tidak bisa menghubungi layanan AI: ' . $galat]);
    }
    if ($kode >= 400) {
        jawab(502, ['galat' => 'Layanan AI menolak permintaan (HTTP ' . $kode . ').']);
    }

    $urai = json_decode((string) $hasil, true);
    $isi  = $urai['choices'][0]['message']['content'] ?? '';

    if (trim((string) $isi) === '') {
        jawab(502, ['galat' => 'Layanan AI menjawab kosong. Coba sekali lagi.']);
    }

    return (string) $isi;
}

/**
 * Mengurai jawaban model jadi larik.
 *
 * Model tidak selalu patuh pada bentuk yang diminta: kadang dibungkus pagar
 * kode, kadang diberi kalimat pengantar, dan cukup sering dibungkus larik satu
 * unsur — `[{"ringkas":"…"}]` alih-alih `{"ringkas":"…"}`. Ketiganya ditangani
 * di sini supaya penanganan tiap tugas tidak perlu mengulang-ulang.
 *
 * $wajib = false mengembalikan larik kosong, bukan menghentikan permintaan,
 * untuk tugas yang jawabannya masih berguna walau berupa teks biasa.
 */
function uraiJson(string $teks, bool $wajib = true): array
{
    $teks = trim((string) preg_replace('/^```(?:json)?|```$/m', '', trim($teks)));

    $hasil = json_decode($teks, true);

    if (!is_array($hasil)) {
        foreach ([['{', '}'], ['[', ']']] as [$buka, $tutup]) {
            $awal  = strpos($teks, $buka);
            $akhir = strrpos($teks, $tutup);
            if ($awal !== false && $akhir !== false && $akhir > $awal) {
                $hasil = json_decode(substr($teks, $awal, $akhir - $awal + 1), true);
                if (is_array($hasil)) {
                    break;
                }
            }
        }
    }

    if (is_array($hasil) && array_is_list($hasil) && count($hasil) === 1 && is_array($hasil[0])) {
        $hasil = $hasil[0];
    }

    if (!is_array($hasil)) {
        if ($wajib) {
            jawab(502, ['galat' => 'Jawaban AI tidak berbentuk JSON yang bisa dipakai. Coba sekali lagi.']);
        }
        return [];
    }
    return $hasil;
}

/** Mengambil daftar dari kunci yang diminta, atau dari larik telanjang. */
function ambilDaftar(array $isi, string $kunci): array
{
    if (isset($isi[$kunci]) && is_array($isi[$kunci])) {
        return $isi[$kunci];
    }
    return array_is_list($isi) ? $isi : [];
}

function teks(array $sumber, string $kunci, int $batas = 400): string
{
    return mb_substr(trim((string) ($sumber[$kunci] ?? '')), 0, $batas);
}

/* ------------------------------------------------------------------- Tugas */

$tugas   = (string) ($minta['tugas'] ?? '');
$konteks = is_array($minta['konteks'] ?? null) ? $minta['konteks'] : [];

$judul    = teks($konteks, 'judul', 160);
$kategori = teks($konteks, 'kategori', 60);
$level    = teks($konteks, 'level', 60);
$ringkas  = teks($konteks, 'ringkas', 600);
$arahan   = teks($konteks, 'arahan', 600);

if ($judul === '') {
    jawab(422, ['galat' => 'Isi judul kelas dulu — itu bahan utamanya.']);
}

$sistem = [
    'role' => 'system',
    'content' =>
        "Kamu penyusun materi kelas daring untuk Invishar, studio produk digital Indonesia. " .
        "Bahasa Indonesia yang lugas dan membumi, tanpa jargon pemasaran, tanpa kata berlebihan " .
        "seperti 'revolusioner' atau 'mudah banget'. Kalimat pendek. Sapa pembaca dengan 'Anda'. " .
        "Jawab HANYA dengan JSON valid tanpa penjelasan dan tanpa pagar kode.",
];

$dasar = "Judul kelas: \"$judul\"."
    . ($kategori !== '' ? " Kategori: $kategori." : '')
    . ($level !== '' ? " Level: $level." : '')
    . ($ringkas !== '' ? " Ringkasan yang sudah ada: \"$ringkas\"." : '')
    . ($arahan !== '' ? " Arahan tambahan dari pengajar: \"$arahan\"." : '');

switch ($tugas) {
    case 'ringkasan':
        $mentah = panggilAI([
            $sistem,
            ['role' => 'user', 'content' => "$dasar\n\n" .
                "Buat JSON {\"ringkas\": \"…\"} berisi satu kalimat yang menjelaskan kelas ini " .
                "kepada calon peserta. Maksimal 160 karakter. Sebut hasil nyata yang didapat, " .
                "bukan janji kosong."],
        ], 2000);

        $isi = uraiJson($mentah, false);
        $ringkas = trim((string) ($isi['ringkas'] ?? ''));

        // Kalau model menjawab kalimat biasa tanpa JSON, kalimat itu justru
        // persis yang dibutuhkan — tidak perlu digagalkan.
        if ($ringkas === '') {
            $ringkas = trim($mentah, " \t\n\r\"'`");
        }
        if ($ringkas === '' || mb_strlen($ringkas) > 400) {
            jawab(502, ['galat' => 'Jawaban AI tidak bisa dipakai. Coba sekali lagi.']);
        }
        jawab(200, ['baik' => true, 'hasil' => ['ringkas' => $ringkas]]);

    case 'ikhtisar':
        $isi = uraiJson(panggilAI([
            $sistem,
            ['role' => 'user', 'content' => "$dasar\n\n" .
                "Buat JSON dengan kunci: hasil (array 4-5 kalimat, apa yang bisa dikerjakan peserta " .
                "setelah kelas), untukSiapa (array 3 kalimat, gambaran orangnya bukan jabatannya), " .
                "syarat (array 3 kalimat, yang perlu disiapkan sebelum mulai)."],
        ], 2500));
        jawab(200, ['baik' => true, 'hasil' => [
            'hasil'      => array_values(array_filter((array) ($isi['hasil'] ?? []), 'is_string')),
            'untukSiapa' => array_values(array_filter((array) ($isi['untukSiapa'] ?? []), 'is_string')),
            'syarat'     => array_values(array_filter((array) ($isi['syarat'] ?? []), 'is_string')),
        ]]);

    case 'tanya':
        $isi = uraiJson(panggilAI([
            $sistem,
            ['role' => 'user', 'content' => "$dasar\n\n" .
                "Buat JSON {\"tanya\": [{\"q\":\"…\",\"a\":\"…\"}]} berisi 4 tanya jawab yang " .
                "benar-benar ditanyakan calon peserta sebelum membeli — soal kesiapan diri, waktu, " .
                "akses, dan hasil. Jawaban 1-2 kalimat, jujur, boleh menyebut keterbatasan."],
        ], 2500));
        $daftar = [];
        foreach (ambilDaftar($isi, 'tanya') as $t) {
            if (!empty($t['q']) && !empty($t['a'])) {
                $daftar[] = ['q' => (string) $t['q'], 'a' => (string) $t['a']];
            }
        }
        jawab(200, ['baik' => true, 'hasil' => ['tanya' => $daftar]]);

    case 'kerangka':
        $isi = uraiJson(panggilAI([
            $sistem,
            ['role' => 'user', 'content' => "$dasar\n\n" .
                "Susun kerangka kelas sebagai JSON {\"modul\": [{\"judul\":\"…\", \"materi\": " .
                "[{\"judul\":\"…\", \"durasi\":\"14 mnt\", \"ringkas\":\"…\"}]}]}. " .
                "Buat 3-5 modul, tiap modul 2-4 materi. Materi pertama adalah perkenalan cara " .
                "memakai kelas. Durasi realistis antara 6 dan 25 menit. Ringkasan materi 1 kalimat. " .
                "Urutkan dari yang harus dikuasai lebih dulu."],
        ], 4000));
        $modul = [];
        foreach (ambilDaftar($isi, 'modul') as $m) {
            if (empty($m['judul'])) {
                continue;
            }
            $materi = [];
            foreach ((array) ($m['materi'] ?? []) as $x) {
                if (!empty($x['judul'])) {
                    $materi[] = [
                        'judul'   => (string) $x['judul'],
                        'durasi'  => (string) ($x['durasi'] ?? '12 mnt'),
                        'ringkas' => (string) ($x['ringkas'] ?? ''),
                    ];
                }
            }
            $modul[] = ['judul' => (string) $m['judul'], 'materi' => $materi];
        }
        if (!$modul) {
            jawab(502, ['galat' => 'AI tidak menghasilkan kerangka yang bisa dipakai.']);
        }
        jawab(200, ['baik' => true, 'hasil' => ['modul' => $modul]]);

    case 'materi':
        $judulMateri = teks($konteks, 'judul_materi', 200);
        if ($judulMateri === '') {
            jawab(422, ['galat' => 'Isi judul materi dulu.']);
        }
        $isi = uraiJson(panggilAI([
            $sistem,
            ['role' => 'user', 'content' => "$dasar\n\n" .
                "Materi berjudul \"$judulMateri\". Buat JSON dengan kunci: ringkas (2 kalimat " .
                "tentang isi materi ini), poin (array 3 poin penting yang harus diingat peserta, " .
                "masing-masing satu kalimat pendek)."],
        ], 2000));
        jawab(200, ['baik' => true, 'hasil' => [
            'ringkas' => (string) ($isi['ringkas'] ?? ''),
            'poin'    => array_values(array_filter((array) ($isi['poin'] ?? []), 'is_string')),
        ]]);

    default:
        jawab(400, ['galat' => 'Tugas AI tidak dikenal.']);
}
