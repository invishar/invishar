<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
require_once __DIR__ . '/inc/gerbang.php';
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

    if (masukan('aksi') === 'usaha') {
        /* Identitas yang tercetak sebagai kop tagihan dan kuitansi. Semua divalidasi
           lebih dulu — satu isian salah berarti tidak ada kunci yang tersimpan,
           supaya kop surat tidak pernah separuh jadi. */
        $label = [
            'usaha.nama'            => 'Nama usaha',
            'usaha.alamat'          => 'Alamat',
            'usaha.telepon'         => 'Telepon',
            'usaha.surel'           => 'Surel',
            'usaha.logo_url'        => 'URL logo',
            'usaha.bank'            => 'Rekening',
            'usaha.tempo_hari'      => 'Jatuh tempo',
            'usaha.penanda_tangan'  => 'Penanda tangan',
            'usaha.catatan_invoice' => 'Catatan tagihan',
        ];
        $baru  = [];
        $salah = [];

        $nama = masukan('usaha_nama');
        if ($nama === '' || mb_strlen($nama) > 80) {
            $salah[] = 'Nama usaha wajib diisi, maksimal 80 karakter.';
        } else {
            $baru['usaha.nama'] = $nama;
        }

        $tempo = masukan('usaha_tempo_hari');
        if (!preg_match('/^\d+$/', $tempo) || (int) $tempo > 90) {
            $salah[] = 'Jatuh tempo harus 0–90 hari.';
        } else {
            $baru['usaha.tempo_hari'] = (string) (int) $tempo;
        }

        /* Surel dan logo boleh dikosongkan; hanya diperiksa kalau diisi. */
        $surel = masukan('usaha_surel');
        if ($surel !== '' && !filter_var($surel, FILTER_VALIDATE_EMAIL)) {
            $salah[] = 'Surel tidak sah.';
        } else {
            $baru['usaha.surel'] = $surel;
        }

        $logo = masukan('usaha_logo_url');
        if ($logo !== '' && !filter_var($logo, FILTER_VALIDATE_URL)) {
            $salah[] = 'URL logo harus diawali http:// atau https://.';
        } else {
            $baru['usaha.logo_url'] = $logo;
        }

        /* Sisanya teks bebas: cukup disamakan akhir barisnya lalu dibatasi panjangnya
           agar tetap muat satu halaman A4. */
        foreach (['usaha.telepon' => 40, 'usaha.penanda_tangan' => 80,
                  'usaha.alamat' => 300, 'usaha.bank' => 300, 'usaha.catatan_invoice' => 300] as $kunci => $maks) {
            $isi = trim(str_replace("\r\n", "\n", masukan(str_replace('.', '_', $kunci))));
            if (mb_strlen($isi) > $maks) {
                $salah[] = $label[$kunci] . ' maksimal ' . $maks . ' karakter.';
            } else {
                $baru[$kunci] = $isi;
            }
        }

        if ($salah) {
            pesan(implode(' ', $salah), 'buruk');
        } else {
            /* Isian panjang hanya dicatat namanya — nilainya terlalu besar untuk log. */
            $panjang = ['usaha.alamat', 'usaha.bank', 'usaha.catatan_invoice'];
            $berubah      = [];   // rinci, untuk log
            $namaBerubah  = [];   // ringkas, untuk pesan di layar
            foreach ($baru as $kunci => $nilai) {
                $lama = setelan($kunci);
                if ($lama === $nilai) {
                    continue;
                }
                simpanSetelan($kunci, $nilai);
                $berubah[] = in_array($kunci, $panjang, true)
                    ? $label[$kunci]
                    : $label[$kunci] . ': ' . ($lama === '' ? '(kosong)' : $lama) . ' → ' . ($nilai === '' ? '(kosong)' : $nilai);
                $namaBerubah[] = $label[$kunci];
            }
            if ($berubah) {
                catatLog('ubah identitas usaha', implode('; ', $berubah));
                pesan('Identitas usaha disimpan: ' . implode(', ', $namaBerubah) . '.');
            } else {
                pesan('Tidak ada yang berubah.');
            }
        }
        pergi(tautan('pengaturan') . '#usaha');
    }

    if (masukan('aksi') === 'affiliate' && penjualanSiap()) {
        /* [kunci => [label, min, max, satuan]] — batas yang diterima tiap setelan angka. */
        $aturan = [
            'affiliate.cookie_hari'     => ['Lama cookie', 1, 90, 'hari'],
            'affiliate.masa_tahan_hari' => ['Masa tahan', 0, 60, 'hari'],
            'affiliate.bulan_berulang'  => ['Bulan berulang', 1, 60, 'bulan'],
            'affiliate.min_tarik'       => ['Minimal penarikan', 0, 1000000000, 'rupiah'],
        ];
        $baru = [];
        $salah = [];
        foreach ($aturan as $kunci => [$label, $min, $maks, $satuan]) {
            $mentah = masukan(str_replace('.', '_', $kunci));
            $n = $satuan === 'rupiah' ? angkaRupiah($mentah) : (preg_match('/^\d+$/', $mentah) ? (int) $mentah : null);
            if ($n === null || $n < $min || $n > $maks) {
                $salah[] = $label . ' harus ' . ($satuan === 'rupiah' ? 'berupa angka rupiah' : $min . '–' . $maks . ' ' . $satuan) . '.';
            } else {
                $baru[$kunci] = (string) $n;
            }
        }
        $syarat = trim(str_replace("\r\n", "\n", masukan('affiliate_syarat')));
        if ($syarat === '') {
            $salah[] = 'Syarat & ketentuan tidak boleh kosong.';
        } else {
            $baru['affiliate.syarat'] = $syarat;
        }

        if ($salah) {
            pesan(implode(' ', $salah), 'buruk');
        } else {
            $berubah = [];
            foreach ($baru as $kunci => $nilai) {
                $lama = setelan($kunci);
                if ($lama !== $nilai) {
                    simpanSetelan($kunci, $nilai);
                    $berubah[] = $kunci === 'affiliate.syarat'
                        ? 'syarat & ketentuan'
                        : ($aturan[$kunci][0] . ': ' . $lama . ' → ' . $nilai);
                }
            }
            if ($berubah) {
                catatLog('ubah setelan affiliate', implode('; ', $berubah));
                pesan('Setelan affiliate disimpan: ' . implode('; ', $berubah) . '.');
            } else {
                pesan('Tidak ada yang berubah.');
            }
        }
        pergi(tautan('pengaturan') . '#affiliate');
    }

    pergi(tautan('pengaturan'));
}

$jejak = ambilSemua('SELECT * FROM log_aktivitas ORDER BY dibuat_pada DESC LIMIT 40');
$siapJual = penjualanSiap();

$judul = 'General';
$menu  = 'pengaturan';
require __DIR__ . '/inc/kepala.php';
?>

<section class="kotak" id="usaha">
  <div class="kotak-kepala"><h2>Identitas usaha</h2></div>
  <p class="bagian-sub">Dipakai sebagai kop surat pada tagihan dan kuitansi yang dicetak dari order jasa.
    Isi sekali, lalu semua dokumen memakainya.</p>

  <form method="post" class="form-panel">
    <?= csrfInput() ?>
    <input type="hidden" name="aksi" value="usaha">

    <div class="baris-form">
      <div class="bidang">
        <label for="u-nama">Nama usaha</label>
        <input id="u-nama" name="usaha_nama" type="text" maxlength="80" value="<?= e(setelan('usaha.nama')) ?>" required>
        <p class="petunjuk">Tercetak paling atas pada dokumen.</p>
      </div>
      <div class="bidang">
        <label for="u-tempo">Jatuh tempo tagihan</label>
        <div class="isian-imbuh">
          <input id="u-tempo" name="usaha_tempo_hari" type="number" min="0" max="90" value="<?= setelanAngka('usaha.tempo_hari') ?>" required>
          <span class="imbuh imbuh-akhir">hari</span>
        </div>
        <p class="petunjuk">Dihitung dari tanggal tagihan dicetak. 0 = tanpa jatuh tempo.</p>
      </div>
    </div>

    <div class="bidang">
      <label for="u-alamat">Alamat</label>
      <textarea id="u-alamat" name="usaha_alamat" rows="3" maxlength="300"><?= e(setelan('usaha.alamat')) ?></textarea>
    </div>

    <div class="baris-form">
      <div class="bidang">
        <label for="u-telepon">Telepon / WhatsApp</label>
        <input id="u-telepon" name="usaha_telepon" type="text" maxlength="40" value="<?= e(setelan('usaha.telepon')) ?>">
      </div>
      <div class="bidang">
        <label for="u-surel">Surel</label>
        <input id="u-surel" name="usaha_surel" type="email" value="<?= e(setelan('usaha.surel')) ?>">
      </div>
    </div>

    <div class="bidang">
      <label for="u-logo">URL logo</label>
      <input id="u-logo" name="usaha_logo_url" type="url" value="<?= e(setelan('usaha.logo_url')) ?>">
      <p class="petunjuk">Harus URL lengkap (https://&hellip;) supaya tetap tampil saat dokumen dibuka dari mana pun. Kosongkan bila tidak ingin memakai logo.</p>
    </div>

    <div class="baris-form">
      <div class="bidang">
        <label for="u-bank">Rekening pembayaran</label>
        <textarea id="u-bank" name="usaha_bank" rows="3" maxlength="300"><?= e(setelan('usaha.bank')) ?></textarea>
        <p class="petunjuk">Mis. <em>BCA 1234567890 a/n Invishar</em>. Satu rekening per baris. Hanya tampil di tagihan yang belum lunas.</p>
      </div>
      <div class="bidang">
        <label for="u-ttd">Penanda tangan</label>
        <input id="u-ttd" name="usaha_penanda_tangan" type="text" maxlength="80" value="<?= e(setelan('usaha.penanda_tangan')) ?>">
        <p class="petunjuk">Nama di bawah tanda tangan. Kosong = memakai nama usaha.</p>
      </div>
    </div>

    <div class="bidang">
      <label for="u-catatan">Catatan tagihan</label>
      <textarea id="u-catatan" name="usaha_catatan_invoice" rows="2" maxlength="300"><?= e(setelan('usaha.catatan_invoice')) ?></textarea>
      <p class="petunjuk">Tercetak kecil di bagian bawah tagihan.</p>
    </div>

    <div class="form-aksi"><button class="tbl tbl-utama" type="submit">Simpan identitas usaha</button></div>
  </form>
</section>


<?php if ($siapJual): ?>
<section class="kotak" id="affiliate">
  <div class="kotak-kepala"><h2>Afiliasi</h2><a class="tautan-lain" href="<?= tautan('pembayaran') ?>">Setelan pembayaran &rarr;</a></div>
  <p class="bagian-sub">Berlaku untuk semua produk, kecuali produk yang punya setelan sendiri. Perubahan hanya berlaku ke depan —
    cookie yang sudah tertanam dan komisi yang sudah tercatat tidak berubah.</p>

  <form method="post" class="form-panel">
    <?= csrfInput() ?>
    <input type="hidden" name="aksi" value="affiliate">

    <div class="baris-form">
      <div class="bidang">
        <label for="s-cookie">Lama link berlaku</label>
        <div class="isian-imbuh">
          <input id="s-cookie" name="affiliate_cookie_hari" type="number" min="1" max="90" value="<?= setelanAngka('affiliate.cookie_hari') ?>" required>
          <span class="imbuh imbuh-akhir">hari</span>
        </div>
        <p class="petunjuk">Berapa lama pengunjung dari link affiliate tetap ditandai. 1–90 hari.</p>
      </div>
      <div class="bidang">
        <label for="s-tahan">Masa tahan komisi</label>
        <div class="isian-imbuh">
          <input id="s-tahan" name="affiliate_masa_tahan_hari" type="number" min="0" max="60" value="<?= setelanAngka('affiliate.masa_tahan_hari') ?>" required>
          <span class="imbuh imbuh-akhir">hari</span>
        </div>
        <p class="petunjuk">Jeda sebelum komisi bisa ditarik; ruang untuk refund. 0 = langsung. Anda tetap bisa mencairkan lebih awal per affiliator.</p>
      </div>
    </div>

    <div class="baris-form">
      <div class="bidang">
        <label for="s-min">Minimal penarikan</label>
        <div class="isian-imbuh">
          <span class="imbuh imbuh-awal">Rp</span>
          <input id="s-min" name="affiliate_min_tarik" type="text" inputmode="numeric" value="<?= e(number_format(setelanAngka('affiliate.min_tarik'), 0, ',', '.')) ?>" required>
        </div>
      </div>
      <div class="bidang">
        <label for="s-bulan">Komisi langganan berulang</label>
        <div class="isian-imbuh">
          <input id="s-bulan" name="affiliate_bulan_berulang" type="number" min="1" max="60" value="<?= setelanAngka('affiliate.bulan_berulang') ?>" required>
          <span class="imbuh imbuh-akhir">bulan</span>
        </div>
        <p class="petunjuk">Bawaan untuk produk langganan yang tidak punya setelan sendiri.</p>
      </div>
    </div>

    <div class="bidang">
      <label for="s-syarat">Syarat &amp; ketentuan mitra</label>
      <textarea id="s-syarat" name="affiliate_syarat" rows="7" required><?= e(setelan('affiliate.syarat')) ?></textarea>
      <p class="petunjuk">Tampil di halaman daftar dan halaman <a href="<?= e(urlSitus() . '/mitra/syarat') ?>" target="_blank" rel="noopener">/mitra/syarat</a>.</p>
    </div>

    <div class="form-aksi"><button class="tbl tbl-utama" type="submit">Simpan setelan affiliate</button></div>
  </form>

</section>
<?php endif; ?>

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
