<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
$a = wajibMitra();
$aid = (int) $a['id'];

$saldo = saldoAffiliate($aid);

$klik30 = (int) ambilNilai('SELECT COUNT(*) FROM affiliate_klik WHERE affiliate_id = ? AND dibuat_pada > (NOW() - INTERVAL 30 DAY)', [$aid]);
$jual30 = (int) ambilNilai(
    "SELECT COUNT(*) FROM transaksi WHERE affiliate_id = ? AND beli_sendiri = 0 AND status = 'lunas' AND dibayar_pada > (NOW() - INTERVAL 30 DAY)",
    [$aid]
);
$komisi30 = (int) ambilNilai(
    "SELECT COALESCE(SUM(jumlah), 0) FROM komisi WHERE affiliate_id = ? AND jenis = 'komisi' AND status = 'berlaku' AND dibuat_pada > (NOW() - INTERVAL 30 DAY)",
    [$aid]
);
$cairBerikut = ambilNilai(
    "SELECT MIN(cair_pada) FROM komisi WHERE affiliate_id = ? AND status = 'berlaku' AND penarikan_id IS NULL AND cair_pada > NOW()",
    [$aid]
);
$pernahKlik = $klik30 > 0 || ambilNilai('SELECT 1 FROM affiliate_klik WHERE affiliate_id = ? LIMIT 1', [$aid]) !== null;
$pernahJual = ambilNilai("SELECT 1 FROM komisi WHERE affiliate_id = ? AND jenis = 'komisi' LIMIT 1", [$aid]) !== null;
$rekeningLengkap = trim((string) $a['bank_nama']) !== '' && trim((string) $a['bank_nomor']) !== '' && trim((string) $a['bank_atas_nama']) !== '';

$terbaru = ambilSemua(
    'SELECT k.*, p.nama AS produk_nama, ' . SQL_KEADAAN_KOMISI . '
       FROM komisi k
       LEFT JOIN produk p ON p.id = k.produk_id
       LEFT JOIN penarikan tp ON tp.id = k.penarikan_id
      WHERE k.affiliate_id = ?
      ORDER BY k.dibuat_pada DESC, k.id DESC LIMIT 6',
    [$aid]
);
$linkUtama = linkAffiliate((string) $a['kode']);
$min = setelanAngka('affiliate.min_tarik');

$judul = 'Halo, ' . namaDepan($a['nama']);
$sub = 'Kode mitra Anda <strong>' . e((string) $a['kode']) . '</strong>. Link apa pun yang memakai kode ini tercatat atas nama Anda.';
$menu = 'ringkasan';
require __DIR__ . '/inc/kepala.php';
?>

<?php if (!($rekeningLengkap && $pernahKlik && $pernahJual)): ?>
  <section class="kotak">
    <div class="kotak-kepala"><h2>Langkah awal</h2></div>
    <div class="langkah-awal">
      <div class="is-beres"><span class="la-no">✓</span><span><span class="la-judul">Akun disetujui</span>Kode Anda sudah aktif.</span></div>
      <a href="<?= tautan('profil') ?>#rekening"<?= $rekeningLengkap ? ' class="is-beres"' : '' ?>>
        <span class="la-no"><?= $rekeningLengkap ? '✓' : '2' ?></span>
        <span><span class="la-judul">Isi rekening</span>Tujuan transfer saat Anda menarik komisi.</span>
      </a>
      <a href="<?= tautan('tautan') ?>"<?= $pernahKlik ? ' class="is-beres"' : '' ?>>
        <span class="la-no"><?= $pernahKlik ? '✓' : '3' ?></span>
        <span><span class="la-judul">Bagikan link pertama</span>Salin link produk dan bagikan.</span>
      </a>
      <div<?= $pernahJual ? ' class="is-beres"' : '' ?>>
        <span class="la-no"><?= $pernahJual ? '✓' : '4' ?></span>
        <span><span class="la-judul">Penjualan pertama</span>Komisi muncul begitu pembayaran lunas.</span>
      </div>
    </div>
  </section>
<?php endif; ?>

<div class="angka-kisi">
  <a class="angka angka-sorot" href="<?= tautan('penarikan') ?>">
    <span class="angka-num angka-rp<?= $saldo['siap'] < 0 ? ' minus' : '' ?>"><?= e(rupiah($saldo['siap'])) ?></span>
    <span class="angka-lbl">Siap ditarik</span>
    <span class="angka-catatan"><?= $saldo['siap'] >= $min && $saldo['siap'] > 0 ? 'Bisa diajukan sekarang →' : 'Minimal penarikan ' . e(rupiah($min)) ?></span>
  </a>
  <a class="angka" href="<?= tautan('penghasilan') ?>?keadaan=tertahan">
    <span class="angka-num angka-rp"><?= e(rupiah($saldo['tertahan'])) ?></span>
    <span class="angka-lbl">Tertahan</span>
    <span class="angka-catatan"><?= $cairBerikut ? 'Mulai cair ' . e(tanggalIndo((string) $cairBerikut)) : 'Menunggu masa tahan ' . setelanAngka('affiliate.masa_tahan_hari') . ' hari' ?></span>
  </a>
  <a class="angka" href="<?= tautan('penarikan') ?>">
    <span class="angka-num angka-rp"><?= e(rupiah($saldo['diproses'])) ?></span>
    <span class="angka-lbl">Sedang diproses</span>
    <span class="angka-catatan">Penarikan yang menunggu transfer</span>
  </a>
  <a class="angka" href="<?= tautan('penarikan') ?>">
    <span class="angka-num angka-rp"><?= e(rupiah($saldo['dicairkan'])) ?></span>
    <span class="angka-lbl">Sudah dicairkan</span>
    <span class="angka-catatan">Total sepanjang waktu</span>
  </a>
</div>

<div class="dua-kolom-lebar">
  <section class="kotak">
    <div class="kotak-kepala">
      <h2>Komisi terbaru</h2>
      <a class="tautan-lain" href="<?= tautan('penghasilan') ?>">Semua &rarr;</a>
    </div>
    <?php if (!$terbaru): ?>
      <div class="kosong kosong-besar">
        <h3>Belum ada komisi</h3>
        <p>Komisi muncul di sini begitu seseorang membeli lewat link Anda dan pembayarannya lunas.</p>
        <a class="tbl tbl-utama" href="<?= tautan('tautan') ?>">Ambil link produk</a>
      </div>
    <?php else: ?>
      <ul class="daftar-ringkas">
        <?php foreach ($terbaru as $k): ?>
          <?php $keadaan = keadaanKomisi($k); ?>
          <li>
            <a href="<?= tautan('penghasilan') ?>">
              <span class="dr-judul<?= (int) $k['jumlah'] < 0 ? ' minus' : '' ?>"><?= e(rupiah((int) $k['jumlah'])) ?></span>
              <span class="dr-sub"><?= e($k['jenis'] === 'penyesuaian' ? 'Penyesuaian · ' . ($k['catatan'] ?? '') : ($k['produk_nama'] ?? 'Produk')) ?><?= $k['periode_ke'] ? ' · bulan ke-' . (int) $k['periode_ke'] : '' ?> · <?= e(tanggalIndo($k['dibuat_pada'])) ?></span>
            </a>
            <span class="tanda tanda-<?= e($keadaan) ?>"><?= e(KEADAAN_KOMISI[$keadaan]) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <aside>
    <section class="kotak">
      <div class="kotak-kepala"><h2>30 hari terakhir</h2></div>
      <dl class="keadaan">
        <dt>Klik link</dt><dd><?= number_format($klik30, 0, ',', '.') ?> pengunjung</dd>
        <dt>Penjualan</dt><?php /* Klik dihitung sekali per pengunjung per hari, sedangkan satu pengunjung
                  bisa membeli lebih dari sekali — persen di atas 100 menyesatkan. */ ?>
        <dd><?= $jual30 ?> lunas<?= $klik30 > 0 && $jual30 <= $klik30 ? ' · konversi ' . number_format($jual30 / $klik30 * 100, 1, ',', '.') . '%' : '' ?></dd>
        <dt>Komisi diperoleh</dt><dd><?= e(rupiah($komisi30)) ?></dd>
      </dl>
    </section>

    <section class="kotak">
      <div class="kotak-kepala"><h2>Link beranda</h2></div>
      <p class="teks-kecil" style="margin:0 0 10px">Untuk dibagikan umum. Pengunjung dari link ini yang membeli produk mana pun di halaman Link produk tercatat atas nama Anda.</p>
      <div class="salin-baris">
        <code><?= e($linkUtama) ?></code>
        <button class="tbl-salin" type="button" data-salin="<?= e($linkUtama) ?>">Salin</button>
      </div>
    </section>
  </aside>
</div>

<?php require __DIR__ . '/inc/kaki.php'; ?>
