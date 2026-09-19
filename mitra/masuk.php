<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';

if (!penjualanSiap()) {
    http_response_code(503);
    exit('Portal mitra sedang disiapkan. Coba lagi sebentar lagi.');
}
if (affiliateKini() !== null) {
    pergi(tautan());
}

/* Batas percobaan: 8 kegagalan dalam 15 menit dari satu alamat IP.
   Tabelnya terpisah dari login panel, supaya tidak pernah mengunci admin. */
const BATAS_GAGAL_MITRA = 8;
const JEDA_MENIT_MITRA  = 15;

$ip = ipPengunjung();
$galat = '';
$info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();

    $gagal = (int) ambilNilai(
        'SELECT COUNT(*) FROM mitra_login_gagal WHERE ip = ? AND waktu > (NOW() - INTERVAL ' . JEDA_MENIT_MITRA . ' MINUTE)',
        [$ip]
    );
    if ($gagal >= BATAS_GAGAL_MITRA) {
        $galat = 'Terlalu banyak percobaan. Coba lagi ' . JEDA_MENIT_MITRA . ' menit lagi.';
    } else {
        $a = ambilSatu('SELECT * FROM affiliate WHERE surel = ?', [normalSurel(masukan('surel'))]);

        if ($a !== null && password_verify((string) ($_POST['sandi'] ?? ''), $a['kata_sandi_hash'])) {
            // Status baru diberitahukan SETELAH kata sandi benar, supaya tidak
            // bisa dipakai menebak siapa yang terdaftar.
            if ($a['status'] === 'menunggu') {
                $info = 'Pendaftaran Anda masih ditinjau admin. Kami kabari lewat WhatsApp setelah disetujui.';
            } elseif ($a['status'] === 'ditolak') {
                $info = 'Pendaftaran Anda belum bisa disetujui. Hubungi admin Invishar bila ada pertanyaan.';
            } else {
                session_regenerate_id(true);
                $_SESSION['affiliate_id'] = (int) $a['id'];
                q('UPDATE affiliate SET terakhir_masuk = NOW() WHERE id = ?', [$a['id']]);
                q('DELETE FROM mitra_login_gagal WHERE ip = ?', [$ip]);
                $tujuan = $_SESSION['tujuan'] ?? tautan();
                unset($_SESSION['tujuan']);
                pergi($tujuan);
            }
        } else {
            q('INSERT INTO mitra_login_gagal (ip, waktu) VALUES (?, NOW())', [$ip]);
            $galat = 'Surel atau kata sandi salah.';
        }
    }
}

$judul = 'Masuk';
$halaman = 'masuk';
require __DIR__ . '/inc/luar-kepala.php';
?>

<main class="m-luar" id="isi">
  <div class="m-luar-kotak">
    <section class="kartu-masuk" style="max-width:none">
      <p class="masuk-kicker">Mitra Invishar</p>
      <h1>Masuk</h1>
      <p class="masuk-sub">Lihat link produk, penghasilan, dan penarikan Anda.</p>

      <?php foreach (ambilPesan() as $p): ?>
        <p class="kabar kabar-<?= e($p['jenis']) ?>"><?= e($p['teks']) ?></p>
      <?php endforeach; ?>
      <?php if ($galat !== ''): ?><p class="kabar kabar-buruk"><?= e($galat) ?></p><?php endif; ?>
      <?php if ($info !== ''): ?><p class="kabar kabar-peringatan"><?= e($info) ?></p><?php endif; ?>

      <form method="post">
        <?= csrfInput() ?>
        <label for="surel">Surel</label>
        <input id="surel" name="surel" type="email" autocomplete="username" value="<?= e(masukan('surel')) ?>" required autofocus>
        <label for="sandi">Kata sandi</label>
        <input id="sandi" name="sandi" type="password" autocomplete="current-password" required>
        <button class="tbl tbl-utama tbl-penuh" type="submit">Masuk</button>
      </form>
      <p class="petunjuk" style="text-align:center;margin-top:16px">Belum jadi mitra? <a href="<?= tautan('daftar') ?>">Daftar di sini</a>.
        Lupa kata sandi? Hubungi admin Invishar untuk mengaturnya ulang.</p>
    </section>
  </div>
</main>

<?php require __DIR__ . '/inc/luar-kaki.php'; ?>
