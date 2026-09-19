<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
require __DIR__ . '/inc/produk.php';
wajibMasuk();
wajibPenjualanSiap();

/* =============================================================================
   Produk affiliate — satu tempat untuk memutuskan apa saja yang boleh
   dipromosikan mitra dan berapa komisinya.

   1. Semua produk: nyalakan/matikan affiliate dan ubah komisinya per baris.
   2. Kelas yang belum punya produk: sekali klik menjadi produk "sekali bayar"
      (landing page + checkout) sekaligus dibuka untuk affiliate.

   Mitra hanya melihat produk yang affiliate-nya dibuka DAN berstatus Tayang;
   halaman ini menyebutkan dengan jelas kalau salah satunya belum terpenuhi.
   ============================================================================= */

/** Isian yang gagal disimpan, supaya ketikan admin tidak hilang: [kunci => nilai]. */
$isianGagal = [];
/** Pesan galat per baris: ['p12' => [...], 'k3' => [...]]. */
$galatBaris = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $aksi = masukan('aksi');

    /* ---- 1. simpan setelan komisi satu produk ---- */
    if ($aksi === 'simpan') {
        $pid = (int) masukan('produk_id');
        $p = ambilSatu('SELECT * FROM produk WHERE id = ?', [$pid]);
        if (!$p) {
            pesan('Produk tidak ditemukan.', 'buruk');
            pergi(tautan('produk-affiliate'));
        }
        $galat = [];
        $harga = $p['harga'] !== null ? (int) $p['harga'] : null;
        $s = bacaSetelanAffiliate($p, $p['jenis'], $harga, $galat);
        if ($galat) {
            $galatBaris['p' . $pid] = $galat;
            $isianGagal['p' . $pid] = $_POST;
        } else {
            q(
                'UPDATE produk SET affiliate_aktif = ?, fee_jenis = ?, fee_nilai = ?, fee_bulan_berulang = ?, diperbarui_pada = NOW() WHERE id = ?',
                [$s['affiliate_aktif'], $s['fee_jenis'], $s['fee_nilai'], $s['fee_bulan_berulang'], $pid]
            );
            $baru = array_merge($p, $s);
            catatLog('atur affiliate produk', $p['nama'] . ': ' . ($s['affiliate_aktif'] ? teksFee($s['fee_jenis'], $s['fee_nilai']) : 'ditutup'));
            if (!$s['affiliate_aktif']) {
                pesan('"' . $p['nama'] . '" ditutup untuk affiliate. Link yang sudah dibagikan tetap membuka halamannya, tapi tidak menghasilkan komisi baru.');
            } elseif ($p['status'] !== 'aktif') {
                pesan('Komisi "' . $p['nama'] . '" tersimpan (' . teksKomisiProduk($baru) . '). Produk ini belum Tayang, jadi mitra belum melihatnya.', 'peringatan');
            } else {
                pesan('"' . $p['nama'] . '" terbuka untuk affiliate: ' . teksKomisiProduk($baru) . '. Sudah tampil di dashboard mitra.');
            }
            pergi(tautan('produk-affiliate') . '#p' . $pid);
        }
    }

    /* ---- 2. tayangkan produk yang masih draf/arsip ---- */
    if ($aksi === 'tayangkan') {
        $pid = (int) masukan('produk_id');
        $p = ambilSatu('SELECT * FROM produk WHERE id = ?', [$pid]);
        if (!$p) {
            pesan('Produk tidak ditemukan.', 'buruk');
            pergi(tautan('produk-affiliate'));
        }
        // Syarat tayang sama dengan halaman sunting produk.
        $kurang = null;
        if (in_array($p['jenis'], ['sekali', 'langganan'], true) && (int) $p['harga'] <= 0) {
            $kurang = 'harganya belum diisi';
        } elseif ($p['jenis'] === 'eksternal' && !preg_match('#^https?://[^\s]+\.[^\s]+#i', (string) $p['url_eksternal'])) {
            $kurang = 'alamat aplikasinya belum diisi';
        }
        if ($kurang) {
            pesan('"' . $p['nama'] . '" belum bisa ditayangkan karena ' . $kurang . '. Lengkapi dulu di sini.', 'buruk');
            pergi(tautan('produk/' . $pid));
        }
        q("UPDATE produk SET status = 'aktif', diperbarui_pada = NOW() WHERE id = ?", [$pid]);
        catatLog('tayangkan produk', $p['nama']);
        $g = terbitkanProduk();
        pesan($g
            ? 'Status berubah menjadi Tayang, tapi landing page gagal diperbarui: ' . $g
            : '"' . $p['nama'] . '" sekarang Tayang di ' . preg_replace('#^https?://#', '', urlSitus()) . '/p/' . $p['slug'] . '.',
            $g ? 'peringatan' : 'baik');
        pergi(tautan('produk-affiliate') . '#p' . $pid);
    }

    /* ---- 3. kelas → produk ---- */
    if ($aksi === 'dari_kelas') {
        $kid = (int) masukan('kelas_id');
        $kelas = ambilSatu('SELECT * FROM kelas WHERE id = ?', [$kid]);
        if (!$kelas) {
            pesan('Kelas tidak ditemukan.', 'buruk');
            pergi(tautan('produk-affiliate'));
        }
        $ada = produkUntukKelas($kid);
        if ($ada) {
            // Mis. dua tab terbuka, atau tombol ditekan dua kali.
            pesan('Kelas "' . $kelas['judul'] . '" sudah punya produk. Atur komisinya di baris ini.', 'peringatan');
            pergi(tautan('produk-affiliate') . '#p' . (int) $ada['id']);
        }

        $galat = [];
        $harga = angkaRupiah(masukan('harga'));
        if ($harga === null || $harga <= 0) {
            $galat[] = 'Isi harga jual kelas ini — pembeli membayarnya lewat checkout.';
        }
        $s = bacaSetelanAffiliate([], 'sekali', $harga, $galat);
        if ($galat) {
            $galatBaris['k' . $kid] = $galat;
            $isianGagal['k' . $kid] = $_POST;
        } else {
            $status = isset($_POST['tayang']) ? 'aktif' : 'draf';
            $pid = buatProdukDariKelas($kelas, $harga, $status, $s);
            catatLog('jual kelas', $kelas['judul'] . ' · ' . rupiah($harga) . ($s['affiliate_aktif'] ? ' · komisi ' . teksFee($s['fee_jenis'], $s['fee_nilai']) : ''));
            $g = terbitkanProduk();
            if ($g) {
                pesan('Produk dibuat, tapi landing page gagal diperbarui: ' . $g, 'peringatan');
            } elseif ($status === 'aktif') {
                pesan('"' . $kelas['judul'] . '" sekarang bisa dibeli di ' . preg_replace('#^https?://#', '', urlSitus()) . '/p/' . ambilNilai('SELECT slug FROM produk WHERE id = ?', [$pid])
                    . ($s['affiliate_aktif'] ? ' dan sudah muncul di dashboard mitra.' : '.'));
            } else {
                pesan('Produk "' . $kelas['judul'] . '" dibuat sebagai Draf. Tekan Tayangkan kalau sudah siap dijual.');
            }
            pergi(tautan('produk-affiliate') . '#p' . $pid);
        }
    }
}

/* ---------------------------------------------------------------- data */
$produk = ambilSemua(
    "SELECT p.*, k.judul AS kelas_judul,
            (SELECT COUNT(*) FROM transaksi t WHERE t.produk_id = p.id AND t.affiliate_id IS NOT NULL AND t.beli_sendiri = 0 AND t.status = 'lunas') AS terjual_mitra,
            (SELECT COALESCE(SUM(m.jumlah), 0) FROM komisi m WHERE m.produk_id = p.id AND m.status = 'berlaku') AS komisi_total
       FROM produk p
       LEFT JOIN kelas k ON k.id = p.kelas_id
      WHERE p.status <> 'arsip'
      ORDER BY p.affiliate_aktif DESC, FIELD(p.status, 'aktif', 'draf'), p.urutan, p.nama"
);
$kelasBelum = ambilSemua(
    'SELECT k.* FROM kelas k
      WHERE NOT EXISTS (SELECT 1 FROM produk p WHERE p.kelas_id = k.id)
      ORDER BY k.urutan, k.judul'
);
$jmlArsip = (int) ambilNilai("SELECT COUNT(*) FROM produk WHERE status = 'arsip'");

$dilihatMitra = 0;
$dibukaBelumTayang = 0;
$pertamaBelumTayang = null;   // tujuan kartu angka "Dibuka, belum tayang"
foreach ($produk as $p) {
    if ((int) $p['affiliate_aktif'] && $p['status'] === 'aktif') {
        $dilihatMitra++;
    } elseif ((int) $p['affiliate_aktif']) {
        $dibukaBelumTayang++;
        $pertamaBelumTayang = $pertamaBelumTayang ?? (int) $p['id'];
    }
}
$mitraAktif = (int) ambilNilai("SELECT COUNT(*) FROM affiliate WHERE status = 'aktif'");
$bulanUmum = setelanAngka('affiliate.bulan_berulang');

/** Nilai isian: ketikan yang gagal disimpan menang atas nilai tersimpan. */
function isianPA(array $isian, string $kunci, string $bawaan): string
{
    return array_key_exists($kunci, $isian) ? trim((string) $isian[$kunci]) : $bawaan;
}

/**
 * Kontrol komisi (saklar, persen/rupiah, besar, bulan berulang, perkiraan)
 * untuk satu formulir. $v = nilai tersimpan; $isian = ketikan yang gagal.
 */
function kontrolKomisi(string $awalan, array $v, array $isian, bool $langganan, int $bulanUmum): void
{
    $aktif = $isian ? isset($isian['affiliate_aktif']) : (bool) $v['affiliate_aktif'];
    $jenisFee = isianPA($isian, 'fee_jenis', (string) $v['fee_jenis']) === 'tetap' ? 'tetap' : 'persen';
    $nilai = isianPA($isian, 'fee_nilai', teksIsianFee($v));
    $bulan = isianPA($isian, 'fee_bulan_berulang', $v['fee_bulan_berulang'] !== null ? (string) (int) $v['fee_bulan_berulang'] : '');
    ?>
    <label class="saklar">
      <input type="checkbox" name="affiliate_aktif" value="1" data-pa-saklar<?= $aktif ? ' checked' : '' ?>>
      <span class="saklar-rel" aria-hidden="true"></span>
      <span class="saklar-teks">Buka untuk affiliate</span>
    </label>

    <div class="pa-fee" data-pa-fee<?= $aktif ? '' : ' hidden' ?>>
      <div class="pa-fee-baris">
        <div class="segmen" role="radiogroup" aria-label="Bentuk komisi">
          <label><input type="radio" name="fee_jenis" value="persen"<?= $jenisFee === 'persen' ? ' checked' : '' ?>><span>Persen</span></label>
          <label><input type="radio" name="fee_jenis" value="tetap"<?= $jenisFee === 'tetap' ? ' checked' : '' ?>><span>Rupiah tetap</span></label>
        </div>
        <div class="isian-imbuh pa-nilai">
          <span class="imbuh imbuh-awal" data-pa-awal<?= $jenisFee === 'tetap' ? '' : ' hidden' ?>>Rp</span>
          <input id="<?= e($awalan) ?>-fee" name="fee_nilai" type="text" inputmode="decimal" value="<?= e($nilai) ?>"
                 placeholder="<?= $jenisFee === 'tetap' ? 'mis. 50.000' : 'mis. 20' ?>" aria-label="Besar komisi" autocomplete="off">
          <span class="imbuh imbuh-akhir" data-pa-akhir<?= $jenisFee === 'tetap' ? ' hidden' : '' ?>>%</span>
        </div>
        <?php if ($langganan): ?>
          <div class="isian-imbuh pa-bulan" title="Komisi dibayar tiap bulan pelanggan membayar, selama sekian bulan">
            <span class="imbuh imbuh-awal">selama</span>
            <input name="fee_bulan_berulang" type="number" min="1" max="60" value="<?= e($bulan) ?>" placeholder="<?= $bulanUmum ?>" aria-label="Komisi berulang selama (bulan)">
            <span class="imbuh imbuh-akhir">bulan</span>
          </div>
        <?php endif; ?>
      </div>
      <p class="pa-perkiraan" data-pa-perkiraan aria-live="polite"></p>
    </div>
    <?php
}

$judul = 'Produk affiliate';
$menu  = 'produk-affiliate';
require __DIR__ . '/inc/kepala.php';
?>

<p class="pengantar">
  Tentukan apa saja yang boleh dipromosikan mitra dan berapa komisinya. Produk muncul di dashboard mitra
  (lengkap dengan link pribadinya) kalau <strong>dibuka untuk affiliate</strong> <em>dan</em> berstatus <strong>Tayang</strong>.
  Mengubah komisi hanya berlaku untuk penjualan berikutnya &mdash; komisi yang sudah tercatat tidak berubah.
</p>

<div class="angka-kisi">
  <div class="angka angka-diam angka-sorot">
    <span class="angka-num"><?= $dilihatMitra ?></span>
    <span class="angka-lbl">Produk terlihat mitra</span>
    <span class="angka-catatan">Dibuka &amp; tayang</span>
  </div>
  <?php if ($pertamaBelumTayang): ?><a class="angka" href="#p<?= $pertamaBelumTayang ?>"><?php else: ?><div class="angka angka-diam"><?php endif; ?>
    <span class="angka-num"><?= $dibukaBelumTayang ?></span>
    <span class="angka-lbl">Dibuka, belum tayang</span>
    <span class="angka-catatan"><?= $dibukaBelumTayang ? 'Mitra belum melihatnya' : 'Tidak ada' ?></span>
  <?= $pertamaBelumTayang ? '</a>' : '</div>' ?>
  <a class="angka" href="#kelas">
    <span class="angka-num"><?= count($kelasBelum) ?></span>
    <span class="angka-lbl">Kelas belum dijual</span>
    <span class="angka-catatan"><?= $kelasBelum ? 'Bisa dijadikan produk di bawah' : 'Semua kelas sudah punya produk' ?></span>
  </a>
  <a class="angka" href="<?= tautan('affiliate') ?>">
    <span class="angka-num"><?= $mitraAktif ?></span>
    <span class="angka-lbl">Mitra aktif</span>
    <span class="angka-catatan">Yang bisa membagikan link</span>
  </a>
</div>

<!-- ============ 1. Produk ============ -->
<section class="pa-bagian">
  <div class="pa-kepala">
    <div>
      <h2>Produk</h2>
      <p class="teks-kecil">Nyalakan saklarnya, isi besar komisi, lalu <strong>Simpan</strong>. Tiap baris disimpan sendiri-sendiri.</p>
    </div>
    <a class="tbl tbl-kecil" href="<?= tautan('produk-edit') ?>">+ Produk baru</a>
  </div>

  <?php if (!$produk): ?>
    <div class="kosong kosong-besar">
      <h3>Belum ada produk</h3>
      <p>Jadikan kelas sebagai produk di bagian bawah halaman ini, atau buat produk lain (langganan, jasa, aplikasi) lewat menu Produk.</p>
      <a class="tbl tbl-utama" href="<?= tautan('produk-edit') ?>">+ Tambah produk</a>
    </div>
  <?php else: ?>
    <div class="pa-daftar">
      <?php foreach ($produk as $p): ?>
        <?php
          $kunci = 'p' . (int) $p['id'];
          $isian = $isianGagal[$kunci] ?? [];
          $tayang = $p['status'] === 'aktif';
          $dibuka = (int) $p['affiliate_aktif'] === 1;
          $harga = $p['harga'] !== null ? (int) $p['harga'] : 0;
        ?>
        <article class="pa-baris<?= isset($galatBaris[$kunci]) ? ' is-galat' : '' ?>" id="<?= e($kunci) ?>">
          <div class="pa-info">
            <div class="pa-nama">
              <a class="tabel-utama" href="<?= tautan('produk/' . (int) $p['id']) ?>"><?= e($p['nama']) ?></a>
              <span class="tanda tanda-<?= $tayang ? 'tayang' : e($p['status']) ?>"><?= e(STATUS_PRODUK[$p['status']]) ?></span>
            </div>
            <p class="pa-meta">
              <?= e(JENIS_PRODUK[$p['jenis']]['label'] ?? $p['jenis']) ?>
              <?php if ($p['kelas_judul']): ?> · dari kelas<?php endif; ?>
              <?php $th = teksHargaProduk($p); if ($th !== ''): ?> · <strong><?= e($th) ?></strong><?php endif; ?>
            </p>
            <p class="pa-keadaan">
              <?php if ($dibuka && $tayang): ?>
                <span class="titik-hijau" aria-hidden="true"></span> Tampil di dashboard mitra
              <?php elseif ($dibuka): ?>
                <span class="titik-kuning" aria-hidden="true"></span> Belum tayang &mdash; mitra belum melihatnya
              <?php else: ?>
                <span class="titik-abu" aria-hidden="true"></span> Tidak dipromosikan mitra
              <?php endif; ?>
            </p>
            <?php if ((int) $p['terjual_mitra'] > 0): ?>
              <p class="teks-kecil"><?= (int) $p['terjual_mitra'] ?> terjual lewat mitra · komisi <?= e(rupiah((int) $p['komisi_total'])) ?></p>
            <?php endif; ?>
            <?php if (in_array($p['jenis'], ['penawaran', 'eksternal'], true)): ?>
              <p class="teks-kecil"><?= $p['jenis'] === 'penawaran'
                  ? 'Komisi dihitung dari nilai yang Anda catat saat pembayaran diterima.'
                  : 'Pembayaran terjadi di aplikasi lain; komisi dihitung saat Anda mencatat pembayarannya di Transaksi.' ?></p>
            <?php endif; ?>
            <?php if (!$tayang): ?>
              <form method="post" class="pa-tayang">
                <?= csrfInput() ?>
                <input type="hidden" name="aksi" value="tayangkan">
                <input type="hidden" name="produk_id" value="<?= (int) $p['id'] ?>">
                <button class="tbl tbl-kecil" type="submit"
                        data-pastikan="Tayangkan <?= e($p['nama']) ?>? Landing page-nya langsung bisa dibuka dan dibeli pengunjung.">Tayangkan</button>
              </form>
            <?php endif; ?>
          </div>

          <form method="post" class="pa-atur" data-pa data-harga="<?= $harga ?>" data-jenis="<?= e($p['jenis']) ?>" novalidate>
            <?= csrfInput() ?>
            <input type="hidden" name="aksi" value="simpan">
            <input type="hidden" name="produk_id" value="<?= (int) $p['id'] ?>">
            <?php if (isset($galatBaris[$kunci])): ?>
              <div class="pa-galat" role="alert"><?php foreach ($galatBaris[$kunci] as $g): ?><span><?= e($g) ?></span><?php endforeach; ?></div>
            <?php endif; ?>
            <?php kontrolKomisi($kunci, $p, $isian, $p['jenis'] === 'langganan', $bulanUmum); ?>
            <div class="pa-simpan">
              <button class="tbl tbl-kecil" type="submit" data-pa-tombol>Simpan</button>
            </div>
          </form>
        </article>
      <?php endforeach; ?>
    </div>
    <?php if ($jmlArsip > 0): ?>
      <p class="teks-kecil"><?= $jmlArsip ?> produk diarsipkan tidak ditampilkan. <a href="<?= tautan('produk') ?>?status=arsip">Lihat arsip</a></p>
    <?php endif; ?>
  <?php endif; ?>
</section>

<!-- ============ 2. Kelas yang belum dijual ============ -->
<section class="pa-bagian" id="kelas">
  <div class="pa-kepala">
    <div>
      <h2>Kelas yang belum dijual</h2>
      <p class="teks-kecil">Kelas baru bisa dibeli dan dipromosikan mitra setelah dijadikan produk. Nama, ringkasan, daftar hasil
        belajar, tanya jawab, dan gambar sampulnya ikut tersalin ke landing page &mdash; bisa disunting lagi kapan saja.</p>
    </div>
  </div>

  <?php if (!$kelasBelum): ?>
    <p class="kosong">Semua kelas sudah punya produk. Kelas baru yang Anda buat di menu Kelas akan muncul di sini.</p>
  <?php else: ?>
    <div class="pa-kelas-kisi">
      <?php foreach ($kelasBelum as $k): ?>
        <?php
          $kunci = 'k' . (int) $k['id'];
          $isian = $isianGagal[$kunci] ?? [];
          $hargaKelas = hargaDariTeksKelas((string) $k['harga']);
          $hargaIsi = isianPA($isian, 'harga', $hargaKelas ? number_format($hargaKelas, 0, ',', '.') : '');
          $segera = strtolower((string) $k['status']) === 'segera';
          $tayangCentang = $isian ? isset($isian['tayang']) : !$segera;
          $bawaan = ['affiliate_aktif' => 1, 'fee_jenis' => 'persen', 'fee_nilai' => '0', 'fee_bulan_berulang' => null];
        ?>
        <article class="pa-kelas<?= isset($galatBaris[$kunci]) ? ' is-galat' : '' ?>" id="<?= e($kunci) ?>">
          <div class="pa-nama">
            <a class="tabel-utama" href="<?= tautan('kelas/' . (int) $k['id']) ?>"><?= e($k['judul']) ?></a>
            <span class="tanda tanda-<?= e(strtolower((string) $k['status'])) ?>"><?= e($k['status']) ?></span>
          </div>
          <p class="pa-meta">Harga di galeri kelas: <strong><?= e(trim((string) $k['harga']) !== '' ? $k['harga'] : '—') ?></strong></p>
          <?php if ($hargaKelas === 0): ?>
            <p class="teks-kecil">Kelas ini bertanda gratis. Isi harga hanya kalau ingin menjualnya.</p>
          <?php endif; ?>

          <form method="post" class="pa-atur" data-pa data-jenis="sekali" novalidate>
            <?= csrfInput() ?>
            <input type="hidden" name="aksi" value="dari_kelas">
            <input type="hidden" name="kelas_id" value="<?= (int) $k['id'] ?>">
            <?php if (isset($galatBaris[$kunci])): ?>
              <div class="pa-galat" role="alert"><?php foreach ($galatBaris[$kunci] as $g): ?><span><?= e($g) ?></span><?php endforeach; ?></div>
            <?php endif; ?>

            <div class="bidang">
              <label for="<?= e($kunci) ?>-harga">Harga jual</label>
              <div class="isian-imbuh">
                <span class="imbuh imbuh-awal">Rp</span>
                <input id="<?= e($kunci) ?>-harga" name="harga" type="text" inputmode="numeric" value="<?= e($hargaIsi) ?>" placeholder="mis. 249.000" data-pa-harga autocomplete="off">
              </div>
            </div>

            <?php kontrolKomisi($kunci, $bawaan, $isian, false, $bulanUmum); ?>

            <label class="pa-centang">
              <input type="checkbox" name="tayang" value="1"<?= $tayangCentang ? ' checked' : '' ?>>
              <span>Langsung tayang &amp; bisa dibeli
                <span class="teks-kecil"><?= $segera ? 'Kelas ini berstatus Segera — biarkan kosong kalau materinya belum siap.' : 'Kosongkan untuk menyimpannya sebagai Draf dulu.' ?></span>
              </span>
            </label>

            <button class="tbl tbl-utama tbl-penuh" type="submit">Jual kelas ini</button>
          </form>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/inc/kaki.php'; ?>
