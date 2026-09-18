<?php
declare(strict_types=1);

/* =============================================================================
   Pemasangan panel — dijalankan SEKALI.

   Membuat seluruh tabel, akun pertama, dan mengisi kelas dengan data yang
   sekarang ada di invishar.com (isi-awal.json) supaya panel tidak mulai kosong.

   Setelah ada satu akun, berkas ini menolak berjalan dengan sendirinya —
   jadi tidak perlu dihapus, dan memang tidak bisa: tiap deploy mengembalikannya.
   ============================================================================= */
require __DIR__ . '/inc/awal.php';

$sudahAda = false;
try {
    $sudahAda = (int) ambilNilai('SELECT COUNT(*) FROM pengguna') > 0;
} catch (PDOException $e) {
    // Tabel belum ada — memang itu yang mau kita kerjakan.
}

$galat = '';

if ($sudahAda) {
    $galat = "Panel sudah terpasang. Berkas ini menolak berjalan lagi — biarkan saja.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();

    $nama  = masukan('nama');
    $surel = masukan('surel');
    $sandi = $_POST['sandi'] ?? '';
    $ulang = $_POST['ulang'] ?? '';

    if ($nama === '' || $surel === '') {
        $galat = 'Nama dan surel wajib diisi.';
    } elseif (!filter_var($surel, FILTER_VALIDATE_EMAIL)) {
        $galat = 'Alamat surel tidak sah.';
    } elseif (mb_strlen($sandi) < 10) {
        $galat = 'Kata sandi minimal 10 karakter.';
    } elseif ($sandi !== $ulang) {
        $galat = 'Ulangan kata sandi tidak sama.';
    } else {
        // --- tabel ---
        $sql = file_get_contents(__DIR__ . '/skema.sql');
        foreach (preg_split('/;\s*[\r\n]+/', (string) $sql) as $perintah) {
            // Baris komentar dibuang dulu — kalau tidak, potongan yang diawali
            // komentar ikut terbuang beserta perintah CREATE TABLE di bawahnya.
            $perintah = trim((string) preg_replace('/^\s*--.*$/m', '', $perintah));
            if ($perintah !== '') {
                db()->exec($perintah);
            }
        }

        // --- akun pertama ---
        q(
            'INSERT INTO pengguna (nama, surel, kata_sandi_hash, dibuat_pada) VALUES (?, ?, ?, NOW())',
            [$nama, $surel, password_hash($sandi, PASSWORD_DEFAULT)]
        );

        // --- daftar aplikasi ---
        $aplikasi = [
            ['amanafinance', 'Pencatatan keuangan keluarga berbasis obrolan', '', 'https://amanafinance.id'],
            ['catatorder', 'Pencatatan order untuk usaha kecil', '', 'https://catatorderonline.web.app'],
            ['Ponpes Manager', 'Administrasi pesantren', '', ''],
            ['Portal Anak Sekolah', 'Nilai, presensi, dan tagihan untuk wali murid', '', ''],
        ];
        foreach ($aplikasi as $i => $a) {
            q(
                'INSERT INTO aplikasi (nama, keterangan, url_admin, url_situs, urutan) VALUES (?, ?, ?, ?, ?)',
                [$a[0], $a[1], $a[2], $a[3], $i]
            );
        }

        // --- kelas yang sekarang sudah tayang ---
        $awal = json_decode((string) file_get_contents(__DIR__ . '/isi-awal.json'), true);
        foreach ($awal['kelas'] ?? [] as $k) {
            q(
                'INSERT INTO kelas (slug, judul, kategori, ringkas, level, harga, status, ikon, urutan, detail, diperbarui_pada)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $k['slug'], $k['judul'], $k['kategori'], $k['ringkas'], $k['level'],
                    $k['harga'], $k['status'], $k['ikon'], $k['urutan'],
                    json_encode($k['detail'], JSON_UNESCAPED_UNICODE),
                ]
            );
            $kelasId = (int) db()->lastInsertId();

            foreach ($k['modul'] ?? [] as $urutanModul => $m) {
                q('INSERT INTO modul (kelas_id, judul, urutan) VALUES (?, ?, ?)',
                    [$kelasId, $m['judul'], $urutanModul]);
                $modulId = (int) db()->lastInsertId();

                foreach ($m['materi'] ?? [] as $urutanMateri => $x) {
                    q(
                        'INSERT INTO materi (modul_id, kode, judul, durasi, youtube_id, ringkas, poin, urutan)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                        [
                            $modulId, $x['kode'], $x['judul'], $x['durasi'],
                            $x['youtube_id'], $x['ringkas'], $x['poin'], $urutanMateri,
                        ]
                    );
                }
            }
        }

        $_SESSION['pengguna_id'] = (int) ambilNilai('SELECT id FROM pengguna WHERE surel = ?', [$surel]);
        catatLog('pasang panel', $surel);
        pesan('Panel terpasang dan Anda sudah masuk.');
        pergi(tautan());
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Pasang Panel Invishar</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:opsz,wght@9..40,400..600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(aset('panel.css')) ?>">
</head>
<body class="body-masuk">

<main class="kartu-masuk">
  <p class="masuk-kicker">Panel Invishar</p>
  <h1>Pemasangan</h1>
  <p class="masuk-sub">Membuat tabel, akun pertama, dan mengisi kelas dengan data yang sekarang tayang di invishar.com.</p>

  <?php if ($galat !== ''): ?>
    <p class="kabar kabar-buruk"><?= e($galat) ?></p>
  <?php endif; ?>

  <?php if (!$sudahAda): ?>
    <form method="post" autocomplete="off">
      <?= csrfInput() ?>
      <label for="nama">Nama</label>
      <input id="nama" name="nama" type="text" value="<?= e(masukan('nama')) ?>" required>

      <label for="surel">Surel</label>
      <input id="surel" name="surel" type="email" value="<?= e(masukan('surel')) ?>" required>

      <label for="sandi">Kata sandi</label>
      <input id="sandi" name="sandi" type="password" minlength="10" required>
      <p class="petunjuk">Minimal 10 karakter. Simpan di pengelola kata sandi, bukan di catatan.</p>

      <label for="ulang">Ulangi kata sandi</label>
      <input id="ulang" name="ulang" type="password" minlength="10" required>

      <button class="tbl tbl-utama tbl-penuh" type="submit">Pasang panel</button>
    </form>
  <?php else: ?>
    <p><a class="tbl tbl-utama tbl-penuh" href="<?= tautan('masuk') ?>">Ke halaman masuk</a></p>
  <?php endif; ?>
</main>

</body>
</html>
