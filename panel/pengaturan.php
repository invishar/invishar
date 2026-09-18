<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
$pengguna = wajibMasuk();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();

    if (masukan('aksi') === 'akun') {
        $nama  = masukan('nama');
        $surel = masukan('surel');

        if ($nama === '' || !filter_var($surel, FILTER_VALIDATE_EMAIL)) {
            pesan('Nama wajib diisi dan surel harus sah.', 'buruk');
        } else {
            q('UPDATE pengguna SET nama = ?, surel = ? WHERE id = ?', [$nama, $surel, $pengguna['id']]);
            catatLog('ubah akun', $surel);
            pesan('Akun diperbarui.');
        }
    }

    if (masukan('aksi') === 'sandi') {
        $lama  = $_POST['sandi_lama'] ?? '';
        $baru  = $_POST['sandi_baru'] ?? '';
        $ulang = $_POST['sandi_ulang'] ?? '';

        if (!password_verify($lama, $pengguna['kata_sandi_hash'])) {
            pesan('Kata sandi lama salah.', 'buruk');
        } elseif (mb_strlen($baru) < 10) {
            pesan('Kata sandi baru minimal 10 karakter.', 'buruk');
        } elseif ($baru !== $ulang) {
            pesan('Ulangan kata sandi tidak sama.', 'buruk');
        } else {
            q('UPDATE pengguna SET kata_sandi_hash = ? WHERE id = ?',
                [password_hash($baru, PASSWORD_DEFAULT), $pengguna['id']]);
            catatLog('ganti kata sandi');
            pesan('Kata sandi diganti.');
        }
    }

    pergi('pengaturan.php');
}

$jejak = ambilSemua('SELECT * FROM log_aktivitas ORDER BY dibuat_pada DESC LIMIT 40');

$judul = 'Pengaturan';
$menu  = 'pengaturan';
require __DIR__ . '/inc/kepala.php';
?>

<div class="dua-kolom">

  <section class="kotak kotak-form">
    <div class="kotak-kepala"><h2>Akun</h2></div>

    <form method="post" class="form-panel">
      <?= csrfInput() ?>
      <input type="hidden" name="aksi" value="akun">

      <div class="bidang">
        <label for="nama">Nama</label>
        <input id="nama" name="nama" type="text" value="<?= e($pengguna['nama']) ?>" required>
      </div>
      <div class="bidang">
        <label for="surel">Surel</label>
        <input id="surel" name="surel" type="email" value="<?= e($pengguna['surel']) ?>" required>
      </div>
      <p class="petunjuk">Masuk terakhir: <?= e(waktuIndo($pengguna['terakhir_masuk'])) ?></p>

      <div class="form-aksi">
        <button class="tbl tbl-utama" type="submit">Simpan akun</button>
      </div>
    </form>

    <div class="kotak-kepala kotak-kepala-jarak"><h2>Kata sandi</h2></div>

    <form method="post" class="form-panel" autocomplete="off">
      <?= csrfInput() ?>
      <input type="hidden" name="aksi" value="sandi">

      <div class="bidang">
        <label for="sandi_lama">Kata sandi sekarang</label>
        <input id="sandi_lama" name="sandi_lama" type="password" autocomplete="current-password" required>
      </div>
      <div class="baris-form">
        <div class="bidang">
          <label for="sandi_baru">Kata sandi baru</label>
          <input id="sandi_baru" name="sandi_baru" type="password" minlength="10" autocomplete="new-password" required>
        </div>
        <div class="bidang">
          <label for="sandi_ulang">Ulangi</label>
          <input id="sandi_ulang" name="sandi_ulang" type="password" minlength="10" autocomplete="new-password" required>
        </div>
      </div>

      <div class="form-aksi">
        <button class="tbl tbl-utama" type="submit">Ganti kata sandi</button>
      </div>
    </form>
  </section>

  <section class="kotak">
    <div class="kotak-kepala"><h2>Jejak perubahan</h2></div>
    <?php if (!$jejak): ?>
      <p class="kosong">Belum ada yang tercatat.</p>
    <?php else: ?>
      <ul class="jejak">
        <?php foreach ($jejak as $j): ?>
          <li>
            <span class="jejak-aksi"><?= e($j['aksi']) ?></span>
            <?php if ($j['objek']): ?><span class="jejak-objek"><?= e($j['objek']) ?></span><?php endif; ?>
            <span class="jejak-waktu"><?= e(waktuIndo($j['dibuat_pada'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

</div>

<?php require __DIR__ . '/inc/kaki.php'; ?>
