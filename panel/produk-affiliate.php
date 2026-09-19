<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
require __DIR__ . '/inc/produk.php';
wajibMasuk();
wajibPenjualanSiap();

/* =============================================================================
   Afiliasi → Produk afiliasi: produk mana yang boleh dipromosikan mitra, dan
   berapa komisinya.

   Satu baris per produk:
     belum Tayang            abu-abu, "Belum siap jual" — siapkan dulu di Produk
     Tayang, belum diatur    abu-abu + tombol hijau "Atur afiliasi"
     Tayang, afiliasi aktif  berwarna, komisinya tampil — muncul di dashboard mitra
   ============================================================================= */

$galatBaris = [];   // [id produk => pesan-pesan]
$isianGagal = [];   // [id produk => $_POST] supaya ketikan tidak hilang

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $pid = (int) masukan('produk_id');
    $p = ambilSatu('SELECT * FROM produk WHERE id = ?', [$pid]);
    if (!$p) {
        pesan('Produk tidak ditemukan.', 'buruk');
        pergi(tautan('produk-affiliate'));
    }

    if (masukan('aksi') === 'tutup') {
        q('UPDATE produk SET affiliate_aktif = 0, diperbarui_pada = NOW() WHERE id = ?', [$pid]);
        catatLog('tutup afiliasi', $p['nama']);
        pesan('"' . $p['nama'] . '" ditutup untuk mitra. Link yang sudah dibagikan tetap membuka halamannya, tapi tidak menghasilkan komisi baru.');
        pergi(tautan('produk-affiliate') . '#p' . $pid);
    }

    if (masukan('aksi') === 'simpan') {
        if ($p['status'] !== 'aktif') {
            pesan('"' . $p['nama'] . '" belum tayang. Tayangkan dulu di menu Produk, baru atur afiliasinya.', 'buruk');
            pergi(tautan('produk-affiliate') . '#p' . $pid);
        }
        $galat = [];
        $s = bacaSetelanAffiliate($p, $p['jenis'], $p['harga'] !== null ? (int) $p['harga'] : null, $galat);
        if ($galat) {
            $galatBaris[$pid] = $galat;
            $isianGagal[$pid] = $_POST;
        } else {
            $baru = !(int) $p['affiliate_aktif'];
            q(
                'UPDATE produk SET affiliate_aktif = 1, fee_jenis = ?, fee_nilai = ?, fee_bulan_berulang = ?, diperbarui_pada = NOW() WHERE id = ?',
                [$s['fee_jenis'], $s['fee_nilai'], $s['fee_bulan_berulang'], $pid]
            );
            catatLog($baru ? 'buka afiliasi' : 'ubah komisi', $p['nama'] . ': ' . teksFee($s['fee_jenis'], $s['fee_nilai']));
            pesan('"' . $p['nama'] . '" ' . ($baru ? 'dibuka untuk mitra' : 'diperbarui') . ': komisi ' . teksKomisiProduk(array_merge($p, $s))
                . ($baru ? '. Sudah tampil di dashboard mitra.' : '. Berlaku untuk penjualan berikutnya.'));
            pergi(tautan('produk-affiliate') . '#p' . $pid);
        }
    }
}

sinkronKelasProduk();

$produk = ambilSemua(
    "SELECT p.*,
            (SELECT COUNT(*) FROM transaksi t WHERE t.produk_id = p.id AND t.affiliate_id IS NOT NULL AND t.beli_sendiri = 0 AND t.status = 'lunas') AS terjual_mitra
       FROM produk p
      WHERE p.status <> 'arsip'
      ORDER BY (p.status = 'aktif' AND p.affiliate_aktif = 1) DESC, (p.status = 'aktif') DESC,
               FIELD(p.kategori, 'produk', 'jasa', 'kelas'), p.urutan, p.nama"
);
$aktif = $siapAtur = $belumSiap = 0;
foreach ($produk as $p) {
    if ($p['status'] !== 'aktif') {
        $belumSiap++;
    } elseif ((int) $p['affiliate_aktif']) {
        $aktif++;
    } else {
        $siapAtur++;
    }
}
$mitraAktif = (int) ambilNilai("SELECT COUNT(*) FROM affiliate WHERE status = 'aktif'");
$bulanUmum = setelanAngka('affiliate.bulan_berulang');

/** Nilai isian: ketikan yang gagal disimpan menang atas nilai tersimpan. */
function isianPA(array $isian, string $kunci, string $bawaan): string
{
    return array_key_exists($kunci, $isian) ? trim((string) $isian[$kunci]) : $bawaan;
}

/** Perkiraan komisi per penjualan, untuk kolom Komisi. */
function perkiraanKomisi(array $p): string
{
    if ($p['harga'] === null || (int) $p['harga'] <= 0) {
        return '';
    }
    $n = hitungKomisi((string) $p['fee_jenis'], $p['fee_nilai'], (int) $p['harga']);
    return $p['fee_jenis'] === 'persen' ? '≈ ' . rupiah($n) . ($p['jenis'] === 'langganan' ? '/bln' : '') : '';
}

$judul = 'Produk afiliasi';
$menu  = 'produk-affiliate';
require __DIR__ . '/inc/kepala.php';
?>

<p class="pengantar">
  Pilih produk yang boleh dipromosikan mitra dan tentukan komisinya. Hanya produk yang <strong>Tayang</strong> yang bisa diatur —
  produk lain disiapkan dulu di <a href="<?= tautan('produk') ?>">Produk</a>. Mengubah komisi berlaku untuk penjualan berikutnya.
</p>

<div class="angka-kisi">
  <div class="angka angka-diam<?= $aktif ? ' angka-sorot' : '' ?>">
    <span class="angka-num"><?= $aktif ?></span><span class="angka-lbl">Dipromosikan mitra</span><span class="angka-catatan">Tampil di dashboard mitra</span>
  </div>
  <div class="angka angka-diam">
    <span class="angka-num"><?= $siapAtur ?></span><span class="angka-lbl">Tayang, belum diatur</span><span class="angka-catatan"><?= $siapAtur ? 'Tekan Atur afiliasi' : 'Tidak ada' ?></span>
  </div>
  <div class="angka angka-diam">
    <span class="angka-num"><?= $belumSiap ?></span><span class="angka-lbl">Belum siap jual</span><span class="angka-catatan">Draf di menu Produk</span>
  </div>
  <a class="angka" href="<?= tautan('affiliate') ?>">
    <span class="angka-num"><?= $mitraAktif ?></span><span class="angka-lbl">Mitra aktif</span><span class="angka-catatan">Yang bisa membagikan link</span>
  </a>
</div>

<?php if (!$produk): ?>
  <div class="kosong kosong-besar">
    <h3>Belum ada produk</h3>
    <p>Tambahkan produk, jasa, atau kelas di menu Produk dan tayangkan. Setelah itu komisinya bisa diatur di sini.</p>
    <a class="tbl tbl-utama" href="<?= tautan('produk-baru') ?>">+ Tambah produk</a>
  </div>
<?php else: ?>
  <div class="pa2">
    <div class="pa2-kepala" aria-hidden="true">
      <span>Produk</span><span>Kategori</span><span>Harga</span><span>Komisi</span><span></span>
    </div>
    <?php foreach ($produk as $p): ?>
      <?php
        $pid = (int) $p['id'];
        $tayang = $p['status'] === 'aktif';
        $dibuka = $tayang && (int) $p['affiliate_aktif'] === 1;
        $isian = $isianGagal[$pid] ?? [];
        $buka = isset($galatBaris[$pid]);
        $jenisFee = isianPA($isian, 'fee_jenis', (string) $p['fee_jenis']) === 'tetap' ? 'tetap' : 'persen';
        $nilai = isianPA($isian, 'fee_nilai', (int) $p['affiliate_aktif'] ? teksIsianFee($p) : '');
        $bulan = isianPA($isian, 'fee_bulan_berulang', $p['fee_bulan_berulang'] !== null ? (string) (int) $p['fee_bulan_berulang'] : '');
      ?>
      <article class="pa2-baris<?= $dibuka ? ' is-aktif' : ' is-abu' ?>" id="p<?= $pid ?>">
        <div class="pa2-ringkas">
          <div class="pa2-nama">
            <a href="<?= tautan('produk/' . $pid) ?>"><?= e($p['nama']) ?></a>
            <?php if ((int) $p['terjual_mitra'] > 0): ?><span class="teks-kecil"><?= (int) $p['terjual_mitra'] ?> terjual lewat mitra</span><?php endif; ?>
          </div>
          <span class="pa2-sel" data-label="Kategori"><span class="tanda tanda-kat-<?= e($p['kategori']) ?>"><?= e(KATEGORI_PRODUK[$p['kategori']] ?? '') ?></span></span>
          <span class="pa2-sel" data-label="Harga"><?= e(teksHargaProduk($p) ?: '—') ?></span>
          <span class="pa2-sel pa2-komisi" data-label="Komisi">
            <?php if ($dibuka): ?>
              <strong><?= e(teksFee($p['fee_jenis'], $p['fee_nilai'])) ?></strong>
              <span class="teks-kecil"><?= $p['jenis'] === 'langganan' ? 'per bulan · ' . bulanBerulang($p) . ' bln' : e(perkiraanKomisi($p)) ?></span>
            <?php else: ?>
              <span class="teks-kecil">—</span>
            <?php endif; ?>
          </span>
          <span class="pa2-aksi">
            <?php if (!$tayang): ?>
              <span class="pa2-belum">Belum siap jual</span>
            <?php elseif ($dibuka): ?>
              <button class="tbl tbl-kecil" type="button" data-buka="#atur-<?= $pid ?>" aria-controls="atur-<?= $pid ?>">Ubah</button>
            <?php else: ?>
              <button class="tbl tbl-kecil tbl-hijau" type="button" data-buka="#atur-<?= $pid ?>" aria-controls="atur-<?= $pid ?>">Atur afiliasi</button>
            <?php endif; ?>
          </span>
        </div>

        <?php if ($tayang): ?>
          <form method="post" class="pa2-atur pa-atur" id="atur-<?= $pid ?>" data-pa data-harga="<?= (int) $p['harga'] ?>" data-jenis="<?= e($p['jenis']) ?>" novalidate<?= $buka ? '' : ' hidden' ?>>
            <?= csrfInput() ?>
            <input type="hidden" name="produk_id" value="<?= $pid ?>">
            <input type="hidden" name="affiliate_aktif" value="1">
            <?php if ($buka): ?>
              <div class="pa-galat" role="alert"><?php foreach ($galatBaris[$pid] as $g): ?><span><?= e($g) ?></span><?php endforeach; ?></div>
            <?php endif; ?>
            <div class="pa-fee-baris">
              <div class="segmen" role="radiogroup" aria-label="Bentuk komisi">
                <label><input type="radio" name="fee_jenis" value="persen"<?= $jenisFee === 'persen' ? ' checked' : '' ?>><span>Persen</span></label>
                <label><input type="radio" name="fee_jenis" value="tetap"<?= $jenisFee === 'tetap' ? ' checked' : '' ?>><span>Rupiah tetap</span></label>
              </div>
              <div class="isian-imbuh pa-nilai">
                <span class="imbuh imbuh-awal" data-pa-awal<?= $jenisFee === 'tetap' ? '' : ' hidden' ?>>Rp</span>
                <input name="fee_nilai" type="text" inputmode="decimal" value="<?= e($nilai) ?>" aria-label="Besar komisi" autocomplete="off"
                       placeholder="<?= $jenisFee === 'tetap' ? 'mis. 50.000' : 'mis. 20' ?>">
                <span class="imbuh imbuh-akhir" data-pa-akhir<?= $jenisFee === 'tetap' ? ' hidden' : '' ?>>%</span>
              </div>
              <?php if ($p['jenis'] === 'langganan'): ?>
                <div class="isian-imbuh pa-bulan" title="Komisi dibayar tiap bulan pelanggan membayar, selama sekian bulan">
                  <span class="imbuh imbuh-awal">selama</span>
                  <input name="fee_bulan_berulang" type="number" min="1" max="60" value="<?= e($bulan) ?>" placeholder="<?= $bulanUmum ?>" aria-label="Komisi berulang selama (bulan)">
                  <span class="imbuh imbuh-akhir">bulan</span>
                </div>
              <?php endif; ?>
            </div>
            <p class="pa-perkiraan" data-pa-perkiraan aria-live="polite"></p>
            <?php if (in_array($p['jenis'], ['penawaran', 'eksternal'], true)): ?>
              <p class="teks-kecil" style="margin:0"><?= $p['jenis'] === 'penawaran'
                ? 'Komisi dihitung dari nilai yang Anda catat saat pembayaran diterima.'
                : 'Pembayaran terjadi di aplikasi lain; komisi dihitung saat pembayarannya dicatat di Transaksi.' ?></p>
            <?php endif; ?>
            <div class="pa-simpan">
              <button class="tbl tbl-kecil tbl-utama" type="submit" name="aksi" value="simpan" data-pa-tombol><?= $dibuka ? 'Simpan komisi' : 'Simpan & buka untuk mitra' ?></button>
              <button class="tbl tbl-kecil" type="button" data-buka="#atur-<?= $pid ?>">Batal</button>
              <?php if ($dibuka): ?>
                <button class="tbl tbl-kecil tbl-bahaya pa-tutup" type="submit" name="aksi" value="tutup" formnovalidate
                        data-pastikan="Tutup afiliasi <?= e($p['nama']) ?>? Mitra tidak bisa mempromosikannya lagi; komisi yang sudah tercatat tetap berlaku.">Tutup afiliasi</button>
              <?php endif; ?>
            </div>
          </form>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/inc/kaki.php'; ?>
