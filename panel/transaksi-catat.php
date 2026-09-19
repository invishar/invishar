<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
require_once __DIR__ . '/inc/gerbang.php';
wajibMasuk();
wajibPenjualanSiap();

/* =============================================================================
   Catat pembayaran yang masuk di luar checkout (transfer, WhatsApp, tunai).
   Tiga titik masuk:
     ?order=ID       — dari order jasa: pembeli & affiliate terisi dari order
     ?langganan=ID   — perpanjangan: bulan ke-n berikutnya, affiliate terkunci
     (tanpa apa-apa) — pembelian baru
   Transaksi dibuat lalu langsung dilunasi lewat ubahStatusTransaksi(), jalur
   yang sama dengan checkout — komisi affiliate ikut dihitung.
   ============================================================================= */

$orderId = (int) ($_GET['order'] ?? $_POST['order_id'] ?? 0);
$langgananId = (int) ($_GET['langganan'] ?? $_POST['langganan_id'] ?? 0);
$order = $orderId ? ambilSatu('SELECT * FROM order_jasa WHERE id = ?', [$orderId]) : null;
$langganan = $langgananId ? ambilSatu('SELECT * FROM langganan WHERE id = ?', [$langgananId]) : null;
$periodeBerikut = $langganan
    ? (int) ambilNilai("SELECT COALESCE(MAX(periode_ke), 0) FROM transaksi WHERE langganan_id = ? AND status = 'lunas'", [$langganan['id']]) + 1
    : 1;

$daftarProduk = ambilSemua("SELECT * FROM produk WHERE status <> 'arsip' ORDER BY FIELD(status, 'aktif', 'draf'), urutan, nama");
$daftarAffiliate = ambilSemua("SELECT id, nama, kode, surel, whatsapp FROM affiliate WHERE status = 'aktif' ORDER BY nama");

$isian = [
    'produk_id'    => $langganan['produk_id'] ?? ($order['produk_id'] ?? ''),
    'pembeli_nama' => $langganan['pembeli_nama'] ?? ($order ? trim($order['nama'] . ($order['lembaga'] ? ' · ' . $order['lembaga'] : '')) : ''),
    'whatsapp'     => $langganan['whatsapp'] ?? ($order['whatsapp'] ?? ''),
    'surel'        => $langganan['surel'] ?? ($order['surel'] ?? ''),
    'jumlah'       => '',
    'tanggal'      => date('Y-m-d'),
    'metode'       => 'Transfer bank',
    'affiliate_id' => $langganan['affiliate_id'] ?? ($order['affiliate_id'] ?? ''),
    'catatan'      => '',
];
if ($isian['produk_id'] !== '' && $isian['produk_id'] !== null) {
    $hargaAwal = ambilNilai('SELECT harga FROM produk WHERE id = ?', [$isian['produk_id']]);
    if ($hargaAwal !== null) {
        $isian['jumlah'] = number_format((int) $hargaAwal, 0, ',', '.');
    }
} elseif ($order && $order['nilai'] !== null) {
    $isian['jumlah'] = number_format((int) $order['nilai'], 0, ',', '.');
}
$galat = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $isian = array_merge($isian, [
        'produk_id'    => $langganan ? (int) $langganan['produk_id'] : (int) masukan('produk_id'),
        'pembeli_nama' => $langganan ? $langganan['pembeli_nama'] : mb_substr(masukan('pembeli_nama'), 0, 120),
        'whatsapp'     => $langganan ? (string) $langganan['whatsapp'] : mb_substr(masukan('whatsapp'), 0, 40),
        'surel'        => $langganan ? (string) $langganan['surel'] : mb_substr(masukan('surel'), 0, 160),
        'jumlah'       => masukan('jumlah'),
        'tanggal'      => masukan('tanggal'),
        'metode'       => in_array(masukan('metode'), METODE_MANUAL, true) ? masukan('metode') : 'Lainnya',
        'affiliate_id' => $langganan ? $langganan['affiliate_id'] : (int) masukan('affiliate_id'),
        'catatan'      => masukan('catatan'),
    ]);

    $produk = ambilSatu('SELECT * FROM produk WHERE id = ?', [$isian['produk_id']]);
    $jumlah = angkaRupiah((string) $isian['jumlah']);
    $tanggal = DateTime::createFromFormat('!Y-m-d', (string) $isian['tanggal']);
    $affiliate = $isian['affiliate_id'] ? ambilSatu('SELECT * FROM affiliate WHERE id = ?', [$isian['affiliate_id']]) : null;

    if (!$produk) {
        $galat[] = 'Pilih produknya.';
    }
    if ($isian['pembeli_nama'] === '') {
        $galat[] = 'Isi nama pembeli.';
    }
    if ($jumlah === null || $jumlah <= 0) {
        $galat[] = 'Isi jumlah yang dibayar.';
    }
    if (!$tanggal || $tanggal > new DateTime('tomorrow')) {
        $galat[] = 'Tanggal bayar tidak sah atau di masa depan.';
    }
    if ($isian['surel'] !== '' && !filter_var($isian['surel'], FILTER_VALIDATE_EMAIL)) {
        $galat[] = 'Alamat surel tidak sah.';
    }
    if ($isian['affiliate_id'] && !$affiliate) {
        $galat[] = 'Affiliate yang dipilih tidak ditemukan.';
    }

    if (!$galat) {
        $beliSendiri = false;
        if ($affiliate) {
            $beliSendiri = (normalSurel($isian['surel']) !== '' && normalSurel($isian['surel']) === normalSurel($affiliate['surel']))
                || (normalWa($isian['whatsapp']) !== '' && normalWa($isian['whatsapp']) === normalWa($affiliate['whatsapp']));
        }

        $trx = buatTransaksi([
            'produk_id'     => $produk['id'],
            'pembeli_nama'  => $isian['pembeli_nama'],
            'surel'         => $isian['surel'],
            'whatsapp'      => $isian['whatsapp'],
            'jumlah'        => $jumlah,
            'gerbang'       => 'manual',
            'sumber'        => 'admin',
            'affiliate_id'  => $affiliate['id'] ?? null,
            'beli_sendiri'  => $beliSendiri,
            'langganan_id'  => $langganan['id'] ?? null,
            'periode_ke'    => $langganan ? $periodeBerikut : 1,
            'order_jasa_id' => $order['id'] ?? null,
            'metode'        => $isian['metode'],
            'catatan'       => $isian['catatan'] !== '' ? $isian['catatan'] : null,
            'catatan_riwayat' => 'Dicatat manual oleh admin',
        ]);
        ubahStatusTransaksi((int) $trx['id'], 'lunas', 'admin', 'Pembayaran ' . strtolower($isian['metode']) . ' dicatat manual', null, [
            'dibayar_pada' => $tanggal->format('Y-m-d') . ' ' . date('H:i:s'),
            'metode'       => $isian['metode'],
        ]);
        catatLog('catat pembayaran manual', $trx['kode_order'] . ' · ' . rupiah($jumlah));

        $kms = (int) ambilNilai("SELECT COALESCE(SUM(jumlah), 0) FROM komisi WHERE transaksi_id = ? AND jenis = 'komisi'", [$trx['id']]);
        pesan('Pembayaran ' . rupiah($jumlah) . ' tercatat lunas' . ($kms > 0 ? ', komisi ' . rupiah($kms) . ' untuk ' . $affiliate['nama'] . '.' : '.'));
        pergi(tautan('transaksi/' . (int) $trx['id']));
    }
}

$judul = $langganan ? 'Pembayaran langganan bulan ke-' . $periodeBerikut : 'Catat pembayaran manual';
$menu  = 'transaksi';
require __DIR__ . '/inc/kepala.php';
?>

<p class="remah"><a href="<?= tautan('transaksi') ?>">&larr; Semua transaksi</a></p>

<?php if ($galat): ?>
  <div class="pita pita-bahaya" role="alert">
    <span><strong>Belum tersimpan.</strong> <?php foreach ($galat as $g): ?><br>• <?= e($g) ?><?php endforeach; ?></span>
  </div>
<?php endif; ?>

<div class="dua-kolom-lebar">
  <section class="kotak">
    <form method="post" class="form-panel">
      <?= csrfInput() ?>
      <input type="hidden" name="order_id" value="<?= (int) ($order['id'] ?? 0) ?>">
      <input type="hidden" name="langganan_id" value="<?= (int) ($langganan['id'] ?? 0) ?>">

      <?php if ($langganan): ?>
        <?php $pl = ambilSatu('SELECT nama, harga FROM produk WHERE id = ?', [$langganan['produk_id']]); ?>
        <div class="pita pita-info" style="margin:0">
          <span><strong><?= e($pl['nama'] ?? '') ?></strong> untuk <?= e($langganan['pembeli_nama']) ?> — pembayaran bulan ke-<?= $periodeBerikut ?>.
            <?php if ($langganan['affiliate_id']): ?>Affiliate mengikuti pembelian pertama.<?php endif; ?></span>
        </div>
      <?php else: ?>
        <div class="bidang">
          <label for="produk_id">Produk</label>
          <select id="produk_id" name="produk_id" required>
            <option value="">— Pilih produk —</option>
            <?php foreach ($daftarProduk as $p): ?>
              <option value="<?= (int) $p['id'] ?>"<?= (int) $isian['produk_id'] === (int) $p['id'] ? ' selected' : '' ?>>
                <?= e($p['nama']) ?> · <?= e(JENIS_PRODUK[$p['jenis']]['label']) ?><?= $p['status'] === 'draf' ? ' (draf)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="petunjuk">Produk langganan otomatis membuka langganan baru (bulan ke-1). Untuk perpanjangan, buka transaksi bulan sebelumnya lalu pilih &ldquo;Catat pembayaran bulan berikutnya&rdquo;.</p>
        </div>

        <div class="bidang">
          <label for="pembeli_nama">Nama pembeli</label>
          <input id="pembeli_nama" name="pembeli_nama" type="text" value="<?= e((string) $isian['pembeli_nama']) ?>" required>
        </div>
        <div class="baris-form">
          <div class="bidang">
            <label for="whatsapp">WhatsApp</label>
            <input id="whatsapp" name="whatsapp" type="text" value="<?= e((string) $isian['whatsapp']) ?>">
          </div>
          <div class="bidang">
            <label for="surel">Surel</label>
            <input id="surel" name="surel" type="email" value="<?= e((string) $isian['surel']) ?>">
          </div>
        </div>
      <?php endif; ?>

      <div class="baris-form">
        <div class="bidang">
          <label for="jumlah">Jumlah dibayar</label>
          <div class="isian-imbuh">
            <span class="imbuh imbuh-awal">Rp</span>
            <input id="jumlah" name="jumlah" type="text" inputmode="numeric" value="<?= e((string) $isian['jumlah']) ?>" required>
          </div>
        </div>
        <div class="bidang">
          <label for="tanggal">Tanggal bayar</label>
          <input id="tanggal" name="tanggal" type="date" value="<?= e((string) $isian['tanggal']) ?>" max="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="bidang">
          <label for="metode">Cara bayar</label>
          <select id="metode" name="metode">
            <?php foreach (METODE_MANUAL as $m): ?><option<?= $isian['metode'] === $m ? ' selected' : '' ?>><?= e($m) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>

      <?php if (!$langganan): ?>
        <div class="bidang">
          <label for="affiliate_id">Affiliate yang membawa pembeli</label>
          <select id="affiliate_id" name="affiliate_id">
            <option value="0">— Tanpa affiliate —</option>
            <?php foreach ($daftarAffiliate as $a): ?>
              <option value="<?= (int) $a['id'] ?>"<?= (int) $isian['affiliate_id'] === (int) $a['id'] ? ' selected' : '' ?>><?= e($a['nama']) ?> (<?= e((string) $a['kode']) ?>)</option>
            <?php endforeach; ?>
          </select>
          <p class="petunjuk"><?= $order && $order['affiliate_id'] ? 'Terisi dari order jasa — pembeli datang lewat link affiliate ini.' : 'Komisi dihitung dari setelan affiliate produk yang dipilih.' ?></p>
        </div>
      <?php endif; ?>

      <div class="bidang">
        <label for="catatan">Catatan <span class="teks-kecil">(opsional)</span></label>
        <input id="catatan" name="catatan" type="text" value="<?= e((string) $isian['catatan']) ?>" placeholder="Mis. transfer BCA a.n. Yayasan Al-Hikmah">
      </div>

      <div class="form-aksi">
        <button class="tbl tbl-utama" type="submit">Simpan sebagai lunas</button>
        <a class="tbl" href="<?= tautan('transaksi') ?>">Batal</a>
      </div>
    </form>
  </section>

  <aside>
    <section class="kotak">
      <div class="kotak-kepala"><h2>Yang terjadi saat disimpan</h2></div>
      <ul class="garis-waktu">
        <li><span class="gw-judul">Transaksi tercatat lunas</span><span class="gw-sub">Muncul di daftar Transaksi dengan cara bayar yang dipilih.</span></li>
        <li><span class="gw-judul">Komisi affiliate dihitung</span><span class="gw-sub">Kalau produknya dibuka untuk affiliate dan affiliatenya aktif.</span></li>
        <li><span class="gw-judul">Masa tahan <?= setelanAngka('affiliate.masa_tahan_hari') ?> hari</span><span class="gw-sub">Setelah itu komisi bisa ditarik affiliator.</span></li>
      </ul>
    </section>
    <?php if ($order): ?>
      <section class="kotak">
        <div class="kotak-kepala"><h2>Dari order jasa</h2></div>
        <p class="teks-kecil" style="margin:0 0 8px"><a href="<?= tautan('order/' . (int) $order['id']) ?>"><?= e($order['nama']) ?></a></p>
        <p class="kutipan"><?= e(mb_strimwidth($order['kebutuhan'], 0, 240, '…')) ?></p>
      </section>
    <?php endif; ?>
  </aside>
</div>

<?php require __DIR__ . '/inc/kaki.php'; ?>
