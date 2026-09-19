<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
wajibMasuk();
wajibPenjualanSiap();

/* =============================================================================
   Inventaris → Akun: layanan yang dipakai Invishar (hosting, domain, email,
   langganan aplikasi) beserta tanggal perpanjangannya.

   KATA SANDI SENGAJA TIDAK DISIMPAN. Panel ini bisa bocor seperti aplikasi
   web mana pun; kata sandi tempatnya di password manager. Isian "Login"
   hanya untuk email/username, supaya tahu akun mana yang dipakai.
   ============================================================================= */

const SIKLUS_AKUN = ['bulanan' => 'per bulan', 'tahunan' => 'per tahun', 'sekali' => 'sekali bayar', 'gratis' => 'gratis'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $id = (int) masukan('id');

    if (masukan('aksi') === 'hapus' && $id) {
        $nama = (string) ambilNilai('SELECT layanan FROM akun WHERE id = ?', [$id]);
        q('DELETE FROM akun WHERE id = ?', [$id]);
        catatLog('hapus akun', $nama);
        pesan('Akun "' . $nama . '" dihapus dari daftar.');
        pergi(tautan('akun'));
    }

    if (masukan('aksi') === 'perpanjang' && $id) {
        // Satu klik setelah membayar: tanggal maju satu siklus.
        $a = ambilSatu('SELECT * FROM akun WHERE id = ?', [$id]);
        if ($a && $a['perpanjang_pada'] && in_array($a['siklus'], ['bulanan', 'tahunan'], true)) {
            $baru = date('Y-m-d', strtotime($a['perpanjang_pada'] . ($a['siklus'] === 'bulanan' ? ' +1 month' : ' +1 year')));
            q('UPDATE akun SET perpanjang_pada = ?, diperbarui_pada = NOW() WHERE id = ?', [$baru, $id]);
            catatLog('perpanjang akun', $a['layanan'] . ' → ' . $baru);
            pesan('"' . $a['layanan'] . '" diperpanjang sampai ' . tanggalIndo($baru) . '.');
        }
        pergi(tautan('akun'));
    }

    $layanan = mb_substr(masukan('layanan'), 0, 120);
    $url = mb_substr(masukan('url'), 0, 255);
    $tanggal = masukan('perpanjang_pada');
    $siklus = isset(SIKLUS_AKUN[masukan('siklus')]) ? masukan('siklus') : 'bulanan';
    $salah = [];
    if ($layanan === '') {
        $salah[] = 'Nama layanan wajib diisi.';
    }
    if ($url !== '' && !preg_match('#^https?://[^\s]+\.[^\s]+#i', $url)) {
        $salah[] = 'Alamat harus lengkap dengan https://.';
    }
    if ($tanggal !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
        $salah[] = 'Tanggal perpanjang tidak sah.';
    }
    if ($salah) {
        pesan(implode(' ', $salah), 'buruk');
        pergi($id ? tautan('akun/' . $id) : tautan('akun'));
    }

    $isi = [
        $layanan, mb_substr(masukan('login'), 0, 160) ?: null, $url ?: null, mb_substr(masukan('pemilik'), 0, 80) ?: null,
        $siklus === 'gratis' ? null : angkaRupiah(masukan('biaya')), $siklus, $tanggal ?: null, masukan('catatan') ?: null,
    ];
    if ($id) {
        q('UPDATE akun SET layanan = ?, login = ?, url = ?, pemilik = ?, biaya = ?, siklus = ?, perpanjang_pada = ?, catatan = ?, diperbarui_pada = NOW() WHERE id = ?',
            array_merge($isi, [$id]));
        catatLog('ubah akun', $layanan);
        pesan('Akun "' . $layanan . '" diperbarui.');
    } else {
        q('INSERT INTO akun (layanan, login, url, pemilik, biaya, siklus, perpanjang_pada, catatan, dibuat_pada, diperbarui_pada)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())', $isi);
        catatLog('tambah akun', $layanan);
        pesan('Akun "' . $layanan . '" dicatat.');
    }
    pergi(tautan('akun'));
}

$sunting = isset($_GET['sunting']) ? ambilSatu('SELECT * FROM akun WHERE id = ?', [(int) $_GET['sunting']]) : null;
$daftar = ambilSemua('SELECT * FROM akun ORDER BY perpanjang_pada IS NULL, perpanjang_pada, layanan');
$biayaBulan = 0;
foreach ($daftar as $a) {
    if ($a['biaya'] !== null) {
        $biayaBulan += $a['siklus'] === 'bulanan' ? (int) $a['biaya'] : ($a['siklus'] === 'tahunan' ? intdiv((int) $a['biaya'], 12) : 0);
    }
}
$hariIni = strtotime(date('Y-m-d'));

$judul = 'Akun';
$menu  = 'akun';
require __DIR__ . '/inc/kepala.php';
?>

<p class="pengantar">
  Layanan yang dipakai Invishar — hosting, domain, email, langganan aplikasi — dan kapan harus diperpanjang.
  Akun yang jatuh tempo dalam 14 hari muncul sebagai angka di menu.
  <strong>Kata sandi tidak disimpan di sini</strong>; simpan di password manager.
</p>

<?php if ($daftar): ?>
  <div class="angka-kisi">
    <div class="angka angka-diam"><span class="angka-num"><?= count($daftar) ?></span><span class="angka-lbl">Akun tercatat</span></div>
    <div class="angka angka-diam"><span class="angka-num angka-rp"><?= e(rupiah($biayaBulan)) ?></span><span class="angka-lbl">Perkiraan biaya per bulan</span><span class="angka-catatan">Tahunan dibagi 12</span></div>
  </div>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>Layanan</th><th>Login</th><th>Pemilik</th><th class="kanan">Biaya</th><th>Perpanjang</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($daftar as $a): ?>
          <?php
            $sisa = $a['perpanjang_pada'] ? (int) floor((strtotime($a['perpanjang_pada']) - $hariIni) / 86400) : null;
            $kelasSisa = $sisa === null ? '' : ($sisa < 0 ? 'tanda-gagal' : ($sisa <= 14 ? 'tanda-menunggu' : 'tanda-lembut'));
          ?>
          <tr onclick="location='<?= tautan('akun/' . (int) $a['id']) ?>'">
            <td>
              <a class="tabel-utama" href="<?= tautan('akun/' . (int) $a['id']) ?>"><?= e($a['layanan']) ?></a>
              <?php if ($a['url']): ?><span class="tabel-sub"><a href="<?= e($a['url']) ?>" target="_blank" rel="noopener"><?= e(preg_replace('#^https?://#', '', $a['url'])) ?> ↗</a></span><?php endif; ?>
            </td>
            <td><?= $a['login'] ? '<code class="teks-kecil">' . e($a['login']) . '</code>' : '<span class="teks-kecil">—</span>' ?></td>
            <td><?= e((string) ($a['pemilik'] ?? '')) ?: '<span class="teks-kecil">—</span>' ?></td>
            <td class="kanan"><?= $a['biaya'] !== null ? e(rupiah((int) $a['biaya'])) . '<span class="tabel-sub">' . e(SIKLUS_AKUN[$a['siklus']]) . '</span>' : '<span class="teks-kecil">' . e(SIKLUS_AKUN[$a['siklus']] ?? '—') . '</span>' ?></td>
            <td>
              <?php if ($a['perpanjang_pada']): ?>
                <span class="tanda <?= $kelasSisa ?>"><?= e(tanggalIndo($a['perpanjang_pada'])) ?></span>
                <span class="tabel-sub"><?= $sisa < 0 ? 'lewat ' . abs($sisa) . ' hari' : ($sisa === 0 ? 'hari ini' : $sisa . ' hari lagi') ?></span>
              <?php else: ?>
                <span class="teks-kecil">—</span>
              <?php endif; ?>
            </td>
            <td class="kanan">
              <?php if ($a['perpanjang_pada'] && in_array($a['siklus'], ['bulanan', 'tahunan'], true) && $sisa !== null && $sisa <= 30): ?>
                <form method="post" class="sebaris">
                  <?= csrfInput() ?>
                  <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                  <button class="tbl tbl-kecil" type="submit" name="aksi" value="perpanjang"
                          data-pastikan="Sudah dibayar? Tanggal perpanjang <?= e($a['layanan']) ?> maju <?= $a['siklus'] === 'bulanan' ? 'satu bulan' : 'satu tahun' ?>.">Sudah dibayar</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <p class="kosong">Belum ada akun tercatat. Mulai dari yang paling penting: hosting, domain, dan email.</p>
<?php endif; ?>

<section class="kotak kotak-form" id="form-akun">
  <div class="kotak-kepala">
    <h2><?= $sunting ? 'Ubah akun' : 'Catat akun baru' ?></h2>
    <?php if ($sunting): ?><a class="tautan-lain" href="<?= tautan('akun') ?>">Batal</a><?php endif; ?>
  </div>
  <form method="post" class="form-panel">
    <?= csrfInput() ?>
    <input type="hidden" name="id" value="<?= (int) ($sunting['id'] ?? 0) ?>">
    <div class="baris-form">
      <div class="bidang">
        <label for="layanan">Layanan</label>
        <input id="layanan" name="layanan" type="text" required value="<?= e($sunting['layanan'] ?? '') ?>" placeholder="mis. Hosting Domainesia">
      </div>
      <div class="bidang">
        <label for="url">Alamat login <span class="teks-kecil">(opsional)</span></label>
        <input id="url" name="url" type="url" value="<?= e($sunting['url'] ?? '') ?>" placeholder="https://my.domainesia.com">
      </div>
    </div>
    <div class="baris-form">
      <div class="bidang">
        <label for="login">Email / username login</label>
        <input id="login" name="login" type="text" autocomplete="off" value="<?= e($sunting['login'] ?? '') ?>" placeholder="admin@invishar.com">
        <p class="petunjuk">Tanpa kata sandi.</p>
      </div>
      <div class="bidang">
        <label for="pemilik">Pemegang</label>
        <input id="pemilik" name="pemilik" type="text" value="<?= e($sunting['pemilik'] ?? '') ?>" placeholder="Siapa yang mengurus">
      </div>
    </div>
    <div class="baris-form">
      <div class="bidang">
        <label for="siklus">Pembayaran</label>
        <select id="siklus" name="siklus">
          <?php foreach (SIKLUS_AKUN as $k => $l): ?>
            <option value="<?= e($k) ?>"<?= ($sunting['siklus'] ?? 'bulanan') === $k ? ' selected' : '' ?>><?= e(ucfirst($l)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="bidang">
        <label for="biaya">Biaya</label>
        <div class="isian-imbuh">
          <span class="imbuh imbuh-awal">Rp</span>
          <input id="biaya" name="biaya" type="text" inputmode="numeric" value="<?= isset($sunting['biaya']) ? e(number_format((int) $sunting['biaya'], 0, ',', '.')) : '' ?>">
        </div>
      </div>
      <div class="bidang">
        <label for="perpanjang_pada">Jatuh tempo berikutnya</label>
        <input id="perpanjang_pada" name="perpanjang_pada" type="date" value="<?= e($sunting['perpanjang_pada'] ?? '') ?>">
      </div>
    </div>
    <div class="bidang">
      <label for="catatan">Catatan</label>
      <textarea id="catatan" name="catatan" rows="2" placeholder="Mis. dibayar pakai kartu kantor, pemulihan akun lewat nomor 0812…"><?= e($sunting['catatan'] ?? '') ?></textarea>
    </div>
    <div class="form-aksi">
      <button class="tbl tbl-utama" type="submit"><?= $sunting ? 'Simpan perubahan' : 'Catat akun' ?></button>
      <?php if ($sunting): ?>
        <button class="tbl tbl-bahaya" type="submit" name="aksi" value="hapus" data-pastikan="Hapus <?= e($sunting['layanan']) ?> dari daftar?">Hapus</button>
      <?php endif; ?>
    </div>
  </form>
</section>

<?php require __DIR__ . '/inc/kaki.php'; ?>
