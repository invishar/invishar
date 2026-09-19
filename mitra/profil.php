<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
$a = wajibMitra();
$aid = (int) $a['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    wajibAktif($a);
    $aksi = masukan('aksi');

    if ($aksi === 'diri') {
        $nama = mb_substr(masukan('nama'), 0, 120);
        $wa   = mb_substr(masukan('whatsapp'), 0, 40);
        if ($nama === '' || strlen(normalWa($wa)) < 9) {
            pesan('Nama dan nomor WhatsApp wajib diisi dengan benar.', 'buruk');
        } else {
            q('UPDATE affiliate SET nama = ?, whatsapp = ? WHERE id = ?', [$nama, $wa, $aid]);
            pesan('Data diri tersimpan.');
        }
    }

    if ($aksi === 'rekening') {
        $bank  = mb_substr(masukan('bank_nama'), 0, 60);
        $nomor = mb_substr(preg_replace('/[^0-9A-Za-z\-]/', '', masukan('bank_nomor')) ?? '', 0, 40);
        $an    = mb_substr(masukan('bank_atas_nama'), 0, 120);
        if ($bank === '' || strlen($nomor) < 5 || $an === '') {
            pesan('Lengkapi nama bank, nomor rekening, dan nama pemilik rekening.', 'buruk');
        } else {
            q('UPDATE affiliate SET bank_nama = ?, bank_nomor = ?, bank_atas_nama = ? WHERE id = ?', [$bank, $nomor, $an, $aid]);
            catatLog('mitra ubah rekening', $a['nama'] . ' · ' . $bank);
            pesan('Rekening tersimpan. Dipakai untuk penarikan berikutnya.');
        }
    }

    if ($aksi === 'sandi') {
        $lama  = (string) ($_POST['sandi_lama'] ?? '');
        $baru  = (string) ($_POST['sandi_baru'] ?? '');
        $ulang = (string) ($_POST['sandi_ulang'] ?? '');
        if (!password_verify($lama, $a['kata_sandi_hash'])) {
            pesan('Kata sandi sekarang salah.', 'buruk');
        } elseif (mb_strlen($baru) < 8) {
            pesan('Kata sandi baru minimal 8 karakter.', 'buruk');
        } elseif ($baru !== $ulang) {
            pesan('Ulangan kata sandi tidak sama.', 'buruk');
        } else {
            q('UPDATE affiliate SET kata_sandi_hash = ? WHERE id = ?', [password_hash($baru, PASSWORD_DEFAULT), $aid]);
            session_regenerate_id(true);
            pesan('Kata sandi diganti.');
        }
    }

    pergi(tautan('profil'));
}

$judul = 'Profil';
$sub = 'Data diri dan rekening tujuan penarikan.';
$menu = 'profil';
require __DIR__ . '/inc/kepala.php';
?>

<div class="dua-kolom">
  <section class="kotak">
    <div class="kotak-kepala"><h2>Data diri</h2></div>
    <form method="post" class="form-panel">
      <?= csrfInput() ?>
      <input type="hidden" name="aksi" value="diri">
      <div class="bidang">
        <label for="nama">Nama lengkap</label>
        <input id="nama" name="nama" type="text" value="<?= e($a['nama']) ?>" required>
      </div>
      <div class="bidang">
        <label for="whatsapp">WhatsApp</label>
        <input id="whatsapp" name="whatsapp" type="tel" value="<?= e($a['whatsapp']) ?>" required>
      </div>
      <div class="baris-form">
        <div class="bidang">
          <label>Surel</label>
          <input type="email" value="<?= e($a['surel']) ?>" readonly>
          <p class="petunjuk">Untuk mengganti surel, hubungi admin.</p>
        </div>
        <div class="bidang">
          <label>Kode mitra</label>
          <input type="text" value="<?= e((string) $a['kode']) ?>" readonly>
          <p class="petunjuk">Tetap, supaya link yang tersebar tidak mati.</p>
        </div>
      </div>
      <div class="form-aksi"><button class="tbl tbl-utama" type="submit">Simpan data diri</button></div>
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
          <input id="sandi_baru" name="sandi_baru" type="password" minlength="8" autocomplete="new-password" required>
        </div>
        <div class="bidang">
          <label for="sandi_ulang">Ulangi</label>
          <input id="sandi_ulang" name="sandi_ulang" type="password" minlength="8" autocomplete="new-password" required>
        </div>
      </div>
      <div class="form-aksi"><button class="tbl tbl-utama" type="submit">Ganti kata sandi</button></div>
    </form>
  </section>

  <section class="kotak" id="rekening">
    <div class="kotak-kepala"><h2>Rekening penarikan</h2></div>
    <p class="teks-kecil" style="margin:-6px 0 16px">Pastikan nama pemilik sama persis dengan di buku tabungan, supaya transfer tidak tertolak.
      Penarikan yang sedang diproses tetap memakai rekening saat diajukan.</p>
    <form method="post" class="form-panel">
      <?= csrfInput() ?>
      <input type="hidden" name="aksi" value="rekening">
      <div class="bidang">
        <label for="bank_nama">Bank atau dompet digital</label>
        <input id="bank_nama" name="bank_nama" type="text" list="daftar-bank" value="<?= e((string) $a['bank_nama']) ?>" placeholder="Mis. BCA" required>
        <datalist id="daftar-bank">
          <?php foreach (DAFTAR_BANK as $b): ?><option value="<?= e($b) ?>"><?php endforeach; ?>
        </datalist>
      </div>
      <div class="bidang">
        <label for="bank_nomor">Nomor rekening</label>
        <input id="bank_nomor" name="bank_nomor" type="text" inputmode="numeric" value="<?= e((string) $a['bank_nomor']) ?>" required>
      </div>
      <div class="bidang">
        <label for="bank_atas_nama">Nama pemilik rekening</label>
        <input id="bank_atas_nama" name="bank_atas_nama" type="text" value="<?= e((string) $a['bank_atas_nama']) ?>" required>
      </div>
      <div class="form-aksi"><button class="tbl tbl-utama" type="submit">Simpan rekening</button></div>
    </form>
  </section>
</div>

<?php require __DIR__ . '/inc/kaki.php'; ?>
