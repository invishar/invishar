<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
wajibMasuk();

/* =============================================================================
   Menerbitkan isi panel menjadi berkas JSON yang dibaca invishar.com.

   Basis data adalah sumber kebenaran; JSON hanya hasil cetaknya. Dengan begitu
   pengunjung situs tidak pernah menyentuh basis data, tidak ada PHP di jalur
   publik, dan kalau panel mati situs tetap hidup.
   ============================================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pergi(tautan('kelas'));
}
periksaCsrf();

$tujuan = rtrim((string) konfig('situs_data'), '/');

if (!is_dir($tujuan) && !@mkdir($tujuan, 0755, true) && !is_dir($tujuan)) {
    pesan('Folder terbitan tidak bisa dibuat: ' . $tujuan, 'buruk');
    pergi(tautan('kelas'));
}
if (!is_writable($tujuan)) {
    pesan('Folder terbitan tidak bisa ditulis: ' . $tujuan, 'buruk');
    pergi(tautan('kelas'));
}

/** Tulis lewat berkas sementara supaya pembaca tidak pernah melihat isi separuh. */
function tulisJson(string $berkas, array $isi): bool
{
    $sementara = $berkas . '.tmp';
    $teks = json_encode($isi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($teks === false || file_put_contents($sementara, $teks) === false) {
        return false;
    }
    return rename($sementara, $berkas);
}

/** "14 mnt" × n → "3 jam 12 mnt" */
function ringkasDurasi(int $menit): string
{
    if ($menit <= 0) {
        return '—';
    }
    $jam = intdiv($menit, 60);
    $sisa = $menit % 60;
    return ($jam ? $jam . ' jam ' : '') . $sisa . ' mnt';
}

$daftarKelas = ambilSemua('SELECT * FROM kelas ORDER BY urutan, judul');
$kartu = [];
$kategori = ['Semua'];
$jumlahBerkas = 0;

foreach ($daftarKelas as $k) {
    $detail = json_decode((string) $k['detail'], true) ?: [];

    $modulKeluar = [];
    $jumlahMateri = 0;
    $totalMenit = 0;

    foreach (ambilSemua('SELECT * FROM modul WHERE kelas_id = ? ORDER BY urutan, id', [$k['id']]) as $m) {
        $materiKeluar = [];

        foreach (ambilSemua('SELECT * FROM materi WHERE modul_id = ? ORDER BY urutan, id', [$m['id']]) as $x) {
            $poin = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $x['poin']) ?: [])));
            $materiKeluar[] = [
                'id'      => $x['kode'],
                'judul'   => $x['judul'],
                'durasi'  => $x['durasi'],
                'youtube' => $x['youtube_id'],
                'ringkas' => $x['ringkas'] ?? '',
                'poin'    => $poin,
            ];
            $jumlahMateri++;
            $totalMenit += (int) preg_replace('/\D/', '', $x['durasi']);
        }

        $modulKeluar[] = ['judul' => $m['judul'], 'materi' => $materiKeluar];
    }

    // Berkas detail satu kelas — dibaca course.html dan materi.html.
    $isiKelas = [
        'slug'     => $k['slug'],
        'kicker'   => $detail['kicker'] ?? 'Kelas',
        'judul'    => $k['judul'],
        'ringkas'  => $k['ringkas'],
        'level'    => $k['level'],
        'bahasa'   => $detail['bahasa'] ?? 'Bahasa Indonesia',
        'akses'    => $detail['akses'] ?? 'Akses selamanya',
        'harga'    => $k['harga'],
        'pengajar' => $detail['pengajar'] ?? ['nama' => '', 'peran' => ''],
        'ikhtisar' => $detail['ikhtisar'] ?? ['hasil' => [], 'untukSiapa' => [], 'syarat' => []],
        'modul'    => $modulKeluar,
        'sumber'   => $detail['sumber'] ?? [],
        'tanya'    => $detail['tanya'] ?? [],
    ];

    if (!tulisJson($tujuan . '/course-' . $k['slug'] . '.json', $isiKelas)) {
        pesan('Gagal menulis berkas kelas "' . $k['judul'] . '".', 'buruk');
        pergi(tautan('kelas'));
    }
    $jumlahBerkas++;

    // Baris untuk galeri.
    $kartu[] = [
        'slug'     => $k['slug'],
        'judul'    => $k['judul'],
        'kategori' => $k['kategori'],
        'ringkas'  => $k['ringkas'],
        'level'    => $k['level'],
        'materi'   => $jumlahMateri,
        'durasi'   => ringkasDurasi($totalMenit),
        'harga'    => $k['harga'],
        'status'   => $k['status'],
        'ikon'     => $k['ikon'],
        'tautan'   => 'course.html?k=' . $k['slug'],
    ];

    if (!in_array($k['kategori'], $kategori, true)) {
        $kategori[] = $k['kategori'];
    }
}

if (!tulisJson($tujuan . '/kelas.json', ['kategori' => $kategori, 'daftar' => $kartu])) {
    pesan('Gagal menulis daftar kelas.', 'buruk');
    pergi(tautan('kelas'));
}

/* Buang berkas kelas yang kelasnya sudah dihapus, supaya tidak ada halaman
   yatim yang masih bisa dibuka lewat tautan lama. */
$slugHidup = array_column($daftarKelas, 'slug');
foreach (glob($tujuan . '/course-*.json') ?: [] as $berkas) {
    $slug = substr(basename($berkas), 7, -5);
    if (!in_array($slug, $slugHidup, true)) {
        @unlink($berkas);
    }
}

catatLog('terbitkan kelas', $jumlahBerkas . ' kelas');
pesan('Diterbitkan: ' . $jumlahBerkas . ' kelas. Situs sudah memakai data terbaru.');
pergi(tautan('kelas'));
