<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';

/* =============================================================================
   Pendaftaran affiliate. Hasilnya berstatus "menunggu" — baru bisa masuk
   setelah disetujui admin di panel (menu Affiliator).
   ============================================================================= */

if (!penjualanSiap()) {
    http_response_code(503);
    exit('Pendaftaran mitra sedang disiapkan. Coba lagi sebentar lagi.');
}
if (affiliateKini() !== null) {
    pergi(tautan());
}

$isian = ['nama' => '', 'surel' => '', 'whatsapp' => '', 'kanal' => ''];
$galat = [];
$berhasil = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();

    $isian = [
        'nama'     => mb_substr(masukan('nama'), 0, 120),
        'surel'    => mb_substr(normalSurel(masukan('surel')), 0, 160),
        'whatsapp' => mb_substr(masukan('whatsapp'), 0, 40),
        'kanal'    => mb_substr(masukan('kanal'), 0, 2000),
    ];
    $sandi = (string) ($_POST['sandi'] ?? '');
    $ulang = (string) ($_POST['ulang'] ?? '');

    if (trim((string) ($_POST['alamat'] ?? '')) !== '') {
        $galat[] = 'Permintaan ditolak. Muat ulang halaman lalu coba lagi.';
    }
    $ip = ipPengunjung();
    if ((int) ambilNilai('SELECT COUNT(*) FROM affiliate WHERE ip_daftar = ? AND dibuat_pada > (NOW() - INTERVAL 1 HOUR)', [$ip]) >= 5) {
        $galat[] = 'Terlalu banyak pendaftaran dari jaringan ini. Coba lagi satu jam lagi.';
    }
    if ($isian['nama'] === '') {
        $galat[] = 'Isi nama lengkap Anda.';
    }
    if (!filter_var($isian['surel'], FILTER_VALIDATE_EMAIL)) {
        $galat[] = 'Isi alamat surel yang benar.';
    } elseif (ambilNilai('SELECT 1 FROM affiliate WHERE surel = ?', [$isian['surel']]) !== null) {
        $galat[] = 'Surel ini sudah terdaftar. Silakan masuk, atau hubungi admin kalau lupa kata sandi.';
    }
    if (strlen(normalWa($isian['whatsapp'])) < 9) {
        $galat[] = 'Isi nomor WhatsApp yang aktif — kami menghubungi Anda lewat sana.';
    }
    if (mb_strlen($sandi) < 8) {
        $galat[] = 'Kata sandi minimal 8 karakter.';
    } elseif ($sandi !== $ulang) {
        $galat[] = 'Ulangan kata sandi tidak sama.';
    }
    if (mb_strlen($isian['kanal']) < 10) {
        $galat[] = 'Ceritakan singkat di mana Anda akan membagikan link (minimal satu kalimat).';
    }
    if (empty($_POST['setuju'])) {
        $galat[] = 'Centang persetujuan syarat & ketentuan.';
    }

    if (!$galat) {
        q(
            'INSERT INTO affiliate (nama, surel, whatsapp, kata_sandi_hash, kanal_promosi, status, ip_daftar, dibuat_pada)
             VALUES (?, ?, ?, ?, ?, \'menunggu\', ?, NOW())',
            [$isian['nama'], $isian['surel'], $isian['whatsapp'], password_hash($sandi, PASSWORD_DEFAULT), $isian['kanal'], $ip]
        );
        catatLog('pendaftaran affiliate', $isian['nama'] . ' · ' . $isian['surel']);
        $berhasil = true;
    }
}

// Contoh komisi yang sungguhan dari produk yang sedang dibuka untuk affiliate.
$contohProduk = ambilSemua(
    "SELECT * FROM produk WHERE status = 'aktif' AND affiliate_aktif = 1 ORDER BY urutan, nama LIMIT 4"
);

$judul = $berhasil ? 'Pendaftaran terkirim' : 'Daftar jadi mitra';
$halaman = 'daftar';
require __DIR__ . '/inc/luar-kepala.php';
?>

<main class="m-luar" id="isi">
<?php if ($berhasil): ?>
  <div class="m-luar-kotak">
    <section class="kotak" style="text-align:center">
      <div class="berhasil-ikon" aria-hidden="true">✓</div>
      <h1 style="margin:0 0 8px;font-size:26px;letter-spacing:-0.035em">Pendaftaran terkirim</h1>
      <p class="pengantar" style="margin-inline:auto">Terima kasih, <?= e(namaDepan($isian['nama'])) ?>. Admin Invishar akan meninjau
        pendaftaran Anda dan mengabari lewat WhatsApp <strong><?= e($isian['whatsapp']) ?></strong>.</p>
      <ul class="garis-waktu" style="text-align:left;margin:18px 0 22px">
        <li><span class="gw-judul">Ditinjau admin</span><span class="gw-sub">Kami melihat rencana promosi Anda.</span></li>
        <li><span class="gw-judul">Disetujui</span><span class="gw-sub">Anda mendapat kode pribadi dan bisa masuk.</span></li>
        <li><span class="gw-judul">Bagikan link</span><span class="gw-sub">Setiap penjualan lewat link Anda menghasilkan komisi.</span></li>
      </ul>
      <a class="tbl tbl-utama tbl-penuh" href="<?= tautan('masuk') ?>">Ke halaman masuk</a>
    </section>
  </div>
<?php else: ?>
  <div class="m-luar-kotak m-luar-lebar">
    <div class="daftar-grid">
      <section class="ajakan-mitra">
        <p class="masuk-kicker">Program mitra Invishar</p>
        <h1>Bagikan produk yang Anda percaya, dapat komisi dari setiap penjualan.</h1>
        <p class="lead">Cocok untuk guru, pengurus lembaga, kreator, dan siapa pun yang punya jaringan
          keluarga, sekolah, atau pesantren.</p>

        <ol class="cara-kerja">
          <li><span class="la-no">1</span><span><b>Daftar &amp; disetujui</b>Kami tinjau pendaftaran Anda, lalu memberi kode pribadi.</span></li>
          <li><span class="la-no">2</span><span><b>Bagikan link</b>Setiap produk punya link khusus Anda. Pembeli yang datang lewat link itu ditandai selama <?= setelanAngka('affiliate.cookie_hari') ?> hari.</span></li>
          <li><span class="la-no">3</span><span><b>Terima komisi</b>Komisi tercatat begitu pembayaran lunas, dan bisa ditarik ke rekening Anda.</span></li>
        </ol>

        <?php if ($contohProduk): ?>
          <p class="sub-judul" style="margin-top:0">Contoh komisi saat ini</p>
          <div class="komisi-pamer">
            <?php foreach ($contohProduk as $cp): ?>
              <span><?= e($cp['nama']) ?> · <b><?= e(teksFee($cp['fee_jenis'], $cp['fee_nilai'])) ?></b></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <section class="kotak">
        <div class="kotak-kepala"><h2>Buat akun mitra</h2></div>

        <?php if ($galat): ?>
          <div class="pita pita-bahaya" role="alert">
            <span><?php foreach ($galat as $i => $g): ?><?= $i ? '<br>' : '' ?>• <?= e($g) ?><?php endforeach; ?></span>
          </div>
        <?php endif; ?>

        <form method="post" class="form-panel" novalidate>
          <?= csrfInput() ?>
          <div class="perangkap" aria-hidden="true" style="position:absolute;left:-9999px">
            <label for="alamat">Alamat</label>
            <input id="alamat" name="alamat" type="text" tabindex="-1" autocomplete="off">
          </div>

          <div class="bidang">
            <label for="nama">Nama lengkap</label>
            <input id="nama" name="nama" type="text" autocomplete="name" value="<?= e($isian['nama']) ?>" required>
          </div>
          <div class="baris-form">
            <div class="bidang">
              <label for="surel">Surel</label>
              <input id="surel" name="surel" type="email" autocomplete="email" value="<?= e($isian['surel']) ?>" required>
            </div>
            <div class="bidang">
              <label for="whatsapp">WhatsApp</label>
              <input id="whatsapp" name="whatsapp" type="tel" autocomplete="tel" placeholder="0812…" value="<?= e($isian['whatsapp']) ?>" required>
            </div>
          </div>
          <div class="baris-form">
            <div class="bidang">
              <label for="sandi">Kata sandi</label>
              <input id="sandi" name="sandi" type="password" autocomplete="new-password" minlength="8" required>
            </div>
            <div class="bidang">
              <label for="ulang">Ulangi</label>
              <input id="ulang" name="ulang" type="password" autocomplete="new-password" minlength="8" required>
            </div>
          </div>
          <div class="bidang">
            <label for="kanal">Di mana Anda akan membagikan link?</label>
            <textarea id="kanal" name="kanal" rows="3" required
                      placeholder="Mis. Instagram @akunsaya (5 rb pengikut), grup WhatsApp wali murid SD, kanal YouTube tentang keuangan keluarga"><?= e($isian['kanal']) ?></textarea>
          </div>

          <details class="lipat">
            <summary><span class="lipat-sub">Baca syarat &amp; ketentuan</span></summary>
            <div class="kotak-syarat" style="margin-top:10px"><?= e(setelan('affiliate.syarat')) ?></div>
          </details>
          <label class="centang">
            <input type="checkbox" name="setuju" value="1"<?= !empty($_POST['setuju']) ? ' checked' : '' ?> required>
            <span>Saya sudah membaca dan menyetujui syarat &amp; ketentuan mitra Invishar.</span>
          </label>

          <button class="tbl tbl-utama tbl-penuh" type="submit">Kirim pendaftaran</button>
          <p class="petunjuk" style="text-align:center">Sudah punya akun? <a href="<?= tautan('masuk') ?>">Masuk</a></p>
        </form>
      </section>
    </div>
  </div>
<?php endif; ?>
</main>

<?php require __DIR__ . '/inc/luar-kaki.php'; ?>
