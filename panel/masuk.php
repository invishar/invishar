<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';

if (penggunaKini() !== null) {
    pergi(tautan());
}

$galat = '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

/* Batas percobaan: 8 kegagalan dalam 15 menit dari satu alamat IP. */
const BATAS_GAGAL = 8;
const JEDA_MENIT  = 15;

function gagalTerakhir(string $ip): int
{
    // JEDA_MENIT ditulis langsung karena MySQL tidak menerima parameter terikat
    // di dalam INTERVAL; nilainya konstanta angka, bukan masukan pengguna.
    return (int) ambilNilai(
        'SELECT COUNT(*) FROM login_gagal WHERE ip = ? AND waktu > (NOW() - INTERVAL ' . JEDA_MENIT . ' MINUTE)',
        [$ip]
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();

    if (gagalTerakhir($ip) >= BATAS_GAGAL) {
        $galat = 'Terlalu banyak percobaan. Coba lagi ' . JEDA_MENIT . ' menit lagi.';
    } else {
        $surel = masukan('surel');
        $sandi = $_POST['sandi'] ?? '';
        $pengguna = ambilSatu('SELECT * FROM pengguna WHERE surel = ?', [$surel]);

        if ($pengguna !== null && password_verify($sandi, $pengguna['kata_sandi_hash'])) {
            session_regenerate_id(true);
            $_SESSION['pengguna_id'] = (int) $pengguna['id'];
            q('UPDATE pengguna SET terakhir_masuk = NOW() WHERE id = ?', [$pengguna['id']]);
            q('DELETE FROM login_gagal WHERE ip = ?', [$ip]);

            $tujuan = $_SESSION['tujuan'] ?? tautan();
            unset($_SESSION['tujuan']);
            pergi($tujuan);
        }

        // Pesan sengaja sama untuk surel salah maupun sandi salah, supaya tidak
        // bisa dipakai menebak alamat surel mana yang terdaftar.
        q('INSERT INTO login_gagal (ip, waktu) VALUES (?, NOW())', [$ip]);
        $galat = 'Surel atau kata sandi salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Masuk &mdash; Panel Invishar</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:opsz,wght@9..40,400..600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(aset('panel.css')) ?>">
</head>
<body class="body-masuk">

<main class="kartu-masuk">
  <p class="masuk-kicker">Panel Invishar</p>
  <h1>Masuk</h1>
  <p class="masuk-sub">Halaman ini hanya untuk pengelola.</p>

  <?php foreach (ambilPesan() as $p): ?>
    <p class="kabar kabar-<?= e($p['jenis']) ?>"><?= e($p['teks']) ?></p>
  <?php endforeach; ?>

  <?php if ($galat !== ''): ?>
    <p class="kabar kabar-buruk"><?= e($galat) ?></p>
  <?php endif; ?>

  <form method="post">
    <?= csrfInput() ?>
    <label for="surel">Surel</label>
    <input id="surel" name="surel" type="email" autocomplete="username" value="<?= e(masukan('surel')) ?>" required autofocus>

    <label for="sandi">Kata sandi</label>
    <input id="sandi" name="sandi" type="password" autocomplete="current-password" required>

    <button class="tbl tbl-utama tbl-penuh" type="submit">Masuk</button>
  </form>
</main>

</body>
</html>
