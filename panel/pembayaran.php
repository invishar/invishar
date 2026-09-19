<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
require_once __DIR__ . '/inc/gerbang.php';
wajibMasuk();
wajibPenjualanSiap();

/* =============================================================================
   Setting → Pembayaran: gerbang yang dipakai checkout.

     Uji               simulasi, tidak ada uang berpindah
     Midtrans sandbox  Midtrans sungguhan dengan uang pura-pura
     Midtrans produksi uang sungguhan

   Pengaman: kunci Midtrans selalu dites ke Midtrans sebelum disimpan, dan
   pindah ke produksi butuh centang penegasan. Server key tidak pernah
   ditampilkan utuh lagi setelah disimpan.
   ============================================================================= */

const MODE_BAYAR = [
    'uji'      => ['Mode uji (simulasi)', 'Tidak ada uang berpindah. Pembeli diarahkan ke halaman simulasi — untuk mencoba alur checkout dan komisi.'],
    'sandbox'  => ['Midtrans sandbox', 'Midtrans sungguhan dengan uang pura-pura. Butuh kunci sandbox (diawali "SB-Mid-").'],
    'produksi' => ['Midtrans produksi', 'Uang sungguhan masuk ke akun Midtrans Anda. Pakai setelah sandbox berhasil.'],
];

/** Server key disamarkan: "SB-Mid-server-…a1b2". */
function samarkanKunci(string $k): string
{
    return $k === '' ? '' : (strlen($k) > 12 ? substr($k, 0, 14) . '…' . substr($k, -4) : '••••');
}

$konf = konfigPembayaran();
$modeKini = $konf['gerbang'] === 'uji' ? 'uji' : (!empty($konf['midtrans']['produksi']) ? 'produksi' : 'sandbox');
$kunciKini = (string) ($konf['midtrans']['server_key'] ?? '');
$klienKini = (string) ($konf['midtrans']['client_key'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $mode = isset(MODE_BAYAR[masukan('mode')]) ? masukan('mode') : 'uji';
    $kunciBaru = trim(masukan('server_key'));
    $klienBaru = trim(masukan('client_key'));
    $kunci = $kunciBaru !== '' ? $kunciBaru : $kunciKini;
    $klien = $klienBaru !== '' ? $klienBaru : $klienKini;
    $produksi = $mode === 'produksi';
    $aksi = masukan('aksi', 'simpan');

    $salah = null;
    if ($mode !== 'uji' || $aksi === 'tes') {
        if ($kunci === '') {
            $salah = 'Isi server key Midtrans dulu (Settings → Access Keys di dashboard Midtrans).';
        } elseif ($produksi && str_starts_with($kunci, 'SB-')) {
            $salah = 'Kunci ini kunci sandbox (diawali "SB-"). Untuk produksi, pakai kunci dari dashboard Midtrans produksi.';
        } elseif (!$produksi && !str_starts_with($kunci, 'SB-')) {
            $salah = 'Untuk sandbox, pakai kunci sandbox (diawali "SB-Mid-server-").';
        }
    }
    if (!$salah && ($mode !== 'uji' || $aksi === 'tes')) {
        $tes = (new GerbangMidtrans(['server_key' => $kunci, 'client_key' => $klien, 'produksi' => $produksi]
            + array_intersect_key($konf['midtrans'], ['url_app' => 1, 'url_api' => 1])))->tesKunci();
        if (!$tes['ok']) {
            $salah = 'Tes koneksi gagal: ' . $tes['pesan'];
        } elseif ($aksi === 'tes') {
            pesan('Tes koneksi berhasil. ' . $tes['pesan'] . ' Belum disimpan — tekan Simpan untuk memakainya.');
            pergi(tautan('pembayaran'));
        }
    }
    if (!$salah && $produksi && $modeKini !== 'produksi' && !isset($_POST['yakin'])) {
        $salah = 'Centang penegasan untuk mulai menerima uang sungguhan.';
    }

    if ($salah) {
        pesan($salah, 'buruk');
        pergi(tautan('pembayaran'));
    }

    simpanSetelan('pembayaran.gerbang', $mode === 'uji' ? 'uji' : 'midtrans');
    simpanSetelan('pembayaran.midtrans_produksi', $produksi ? '1' : '0');
    if ($kunciBaru !== '') {
        simpanSetelan('pembayaran.midtrans_server_key', $kunciBaru);
    } elseif ($konf['sumber'] === 'konfig' && $kunciKini !== '') {
        // Pertama kali disimpan dari panel: bawa kunci lama dari konfig.php.
        simpanSetelan('pembayaran.midtrans_server_key', $kunciKini);
    }
    if ($klienBaru !== '' || ($konf['sumber'] === 'konfig' && $klienKini !== '')) {
        simpanSetelan('pembayaran.midtrans_client_key', $klienBaru !== '' ? $klienBaru : $klienKini);
    }
    catatLog('ubah pembayaran', MODE_BAYAR[$mode][0] . ($kunciBaru !== '' ? ' · server key diganti' : ''));
    pesan('Pembayaran sekarang memakai ' . MODE_BAYAR[$mode][0] . '.' . ($mode === 'uji' ? ' Checkout berikutnya berupa simulasi.' : ' Checkout berikutnya lewat Midtrans.'));
    pergi(tautan('pembayaran'));
}

$urlNotifikasi = urlSitus() . '/toko/midtrans.php';
$urlSelesai = urlSitus() . '/toko/selesai.php';

$judul = 'Pembayaran';
$menu  = 'pembayaran';
require __DIR__ . '/inc/kepala.php';
?>

<?php if ($modeKini === 'uji'): ?>
  <div class="pita pita-peringatan"><span><strong>Mode uji aktif.</strong> Belum ada uang sungguhan — checkout berakhir di halaman simulasi.</span></div>
<?php elseif ($modeKini === 'sandbox'): ?>
  <div class="pita pita-info"><span><strong>Midtrans sandbox.</strong> Pembayaran lewat Midtrans, tapi uangnya pura-pura. Cocok untuk uji terakhir sebelum produksi.</span></div>
<?php else: ?>
  <div class="pita pita-baik"><span><strong>Midtrans produksi.</strong> Pembayaran sungguhan diterima.</span></div>
<?php endif; ?>

<div class="dua-kolom-lebar">
  <form method="post" class="kotak form-panel" autocomplete="off">
    <?= csrfInput() ?>
    <div class="kotak-kepala"><h2>Gerbang pembayaran</h2></div>

    <div class="pilih-kisi pilih-kisi-tegak">
      <?php foreach (MODE_BAYAR as $kunci => [$label, $sub]): ?>
        <label class="pilih-kartu">
          <input type="radio" name="mode" value="<?= e($kunci) ?>"<?= $modeKini === $kunci ? ' checked' : '' ?>>
          <span class="pilih-judul"><?= e($label) ?><?= $modeKini === $kunci ? ' <span class="teks-kecil">· dipakai sekarang</span>' : '' ?></span>
          <span class="pilih-sub"><?= e($sub) ?></span>
        </label>
      <?php endforeach; ?>
    </div>

    <div class="form-panel" data-tampil-jika="mode=sandbox,produksi">
      <div class="bidang">
        <label for="server_key">Server key</label>
        <input id="server_key" name="server_key" type="password" autocomplete="new-password" spellcheck="false"
               placeholder="<?= $kunciKini !== '' ? 'Tersimpan: ' . e(samarkanKunci($kunciKini)) . ' — kosongkan kalau tidak diganti' : 'SB-Mid-server-…' ?>">
        <p class="petunjuk">Dashboard Midtrans → Settings → Access Keys. Rahasia — hanya dipakai server, tidak pernah dikirim ke peramban.</p>
      </div>
      <div class="bidang">
        <label for="client_key">Client key</label>
        <input id="client_key" name="client_key" type="text" spellcheck="false" value="<?= e($klienKini) ?>" placeholder="SB-Mid-client-…">
      </div>

      <?php if ($modeKini !== 'produksi'): ?>
      <label class="pa-centang" data-tampil-jika="mode=produksi">
        <input type="checkbox" name="yakin" value="1">
        <span>Saya sudah mencoba di sandbox, dan siap menerima pembayaran sungguhan.
          <span class="teks-kecil">Wajib dicentang saat pertama kali pindah ke produksi.</span></span>
      </label>
      <?php endif; ?>

      <p class="petunjuk">Kunci dites langsung ke Midtrans saat disimpan. Kalau ditolak, setelan lama tetap berlaku.</p>
    </div>

    <div class="form-aksi">
      <button class="tbl tbl-utama" type="submit" name="aksi" value="simpan">Simpan</button>
      <button class="tbl" type="submit" name="aksi" value="tes" data-tampil-jika="mode=sandbox,produksi">Tes koneksi saja</button>
    </div>
    <?php if ($konf['sumber'] === 'konfig'): ?>
      <p class="petunjuk">Saat ini setelan dibaca dari <code>konfig.php</code> di server. Setelah disimpan di sini, setelan panel yang berlaku.</p>
    <?php endif; ?>
  </form>

  <aside>
    <section class="kotak">
      <div class="kotak-kepala"><h2>Isi di dashboard Midtrans</h2></div>
      <p class="bagian-sub">Settings → Configuration. Tanpa ini status pembayaran tidak sampai ke panel.</p>
      <dl class="keadaan">
        <dt>Payment notification URL</dt>
        <dd><div class="salin-baris"><code><?= e($urlNotifikasi) ?></code><button class="tbl-salin" type="button" data-salin="<?= e($urlNotifikasi) ?>">Salin</button></div></dd>
        <dt>Finish redirect URL</dt>
        <dd><div class="salin-baris"><code><?= e($urlSelesai) ?></code><button class="tbl-salin" type="button" data-salin="<?= e($urlSelesai) ?>">Salin</button></div></dd>
      </dl>
    </section>
    <section class="kotak">
      <div class="kotak-kepala"><h2>Urutan yang aman</h2></div>
      <ol class="titik-daftar">
        <li>Coba alur lengkap di <strong>mode uji</strong>.</li>
        <li>Pindah ke <strong>sandbox</strong>, bayar lewat simulator Midtrans, pastikan transaksi jadi Lunas.</li>
        <li>Baru pindah ke <strong>produksi</strong> dengan kunci produksi.</li>
      </ol>
    </section>
  </aside>
</div>

<?php require __DIR__ . '/inc/kaki.php'; ?>
