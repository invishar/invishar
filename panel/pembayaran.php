<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
require_once __DIR__ . '/inc/gerbang.php';
wajibMasuk();
wajibPenjualanSiap();

/* =============================================================================
   Setting → Pembayaran: gerbang yang dipakai checkout.

     Uji             simulasi, tidak ada uang berpindah
     Duitku sandbox  Duitku sungguhan dengan uang pura-pura
     Duitku produksi uang sungguhan

   Pengaman: kredensial selalu dites ke Duitku sebelum disimpan, dan pindah ke
   produksi butuh centang penegasan. API Key tidak pernah ditampilkan utuh lagi
   setelah disimpan.

   Berbeda dari Midtrans, kredensial Duitku tidak punya penanda lingkungan
   seperti awalan "SB-". Kunci produksi bisa tertempel di mode sandbox atau
   sebaliknya tanpa bisa dideteksi dari bentuknya — jadi tes koneksi wajib di
   bawah ini adalah satu-satunya pengaman salah tempel. Jangan dilonggarkan.
   ============================================================================= */

const MODE_BAYAR = [
    'uji'      => ['Mode uji (simulasi)', 'Tidak ada uang berpindah. Pembeli diarahkan ke halaman simulasi — untuk mencoba alur checkout dan komisi.'],
    'sandbox'  => ['Duitku sandbox', 'Duitku sungguhan dengan uang pura-pura. Butuh Merchant Code dan API Key dari dashboard sandbox.'],
    'produksi' => ['Duitku produksi', 'Uang sungguhan masuk ke akun Duitku Anda. Pakai setelah sandbox berhasil.'],
];

/** API Key disamarkan supaya tidak pernah dikirim utuh ke peramban. */
function samarkanKunci(string $k): string
{
    return $k === '' ? '' : (strlen($k) > 12 ? substr($k, 0, 6) . '…' . substr($k, -4) : '••••');
}

$konf = konfigPembayaran();
$modeKini = $konf['gerbang'] === 'duitku' ? (!empty($konf['duitku']['produksi']) ? 'produksi' : 'sandbox') : 'uji';
$kunciKini = (string) ($konf['duitku']['api_key'] ?? '');
$kodeKini  = (string) ($konf['duitku']['merchant_code'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $mode = isset(MODE_BAYAR[masukan('mode')]) ? masukan('mode') : 'uji';
    $kunciBaru = trim(masukan('api_key'));
    $kodeBaru  = trim(masukan('merchant_code'));
    $kunci = $kunciBaru !== '' ? $kunciBaru : $kunciKini;
    $kode  = $kodeBaru !== '' ? $kodeBaru : $kodeKini;
    $produksi = $mode === 'produksi';
    $aksi = masukan('aksi', 'simpan');

    $salah = null;
    if ($mode !== 'uji' || $aksi === 'tes') {
        if ($kode === '') {
            $salah = 'Isi Merchant Code dulu — ada di dashboard Duitku, halaman Project.';
        } elseif ($kunci === '') {
            $salah = 'Isi API Key dulu — ada di dashboard Duitku, halaman Project.';
        }
    }
    if (!$salah && ($mode !== 'uji' || $aksi === 'tes')) {
        /* Duitku tidak menandai lingkungan pada kredensialnya, jadi kunci salah
           lingkungan hanya ketahuan dari jawaban Duitku sendiri. Karena itu tes
           ini wajib lolos sebelum apa pun disimpan. */
        $tes = (new GerbangDuitku(['merchant_code' => $kode, 'api_key' => $kunci, 'produksi' => $produksi]
            + array_intersect_key($konf['duitku'], ['url_pop' => 1, 'url_api' => 1])))->tesKunci();
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

    simpanSetelan('pembayaran.gerbang', $mode === 'uji' ? 'uji' : 'duitku');
    simpanSetelan('pembayaran.duitku_produksi', $produksi ? '1' : '0');
    if ($kodeBaru !== '') {
        simpanSetelan('pembayaran.duitku_merchant_code', $kodeBaru);
    }
    if ($kunciBaru !== '') {
        simpanSetelan('pembayaran.duitku_api_key', $kunciBaru);
    }
    // Sisa setelan Midtrans tidak dibaca siapa pun lagi; menyimpannya hanya
    // membingungkan orang yang membuka tabel setelan suatu hari nanti.
    q("DELETE FROM setelan WHERE kunci LIKE 'pembayaran.midtrans\\_%'");

    catatLog('ubah pembayaran', MODE_BAYAR[$mode][0] . ($kunciBaru !== '' ? ' · API key diganti' : ''));
    pesan('Pembayaran sekarang memakai ' . MODE_BAYAR[$mode][0] . '.' . ($mode === 'uji' ? ' Checkout berikutnya berupa simulasi.' : ' Checkout berikutnya lewat Duitku.'));
    pergi(tautan('pembayaran'));
}

$urlNotifikasi = urlSitus() . '/toko/duitku.php';
$urlSelesai = urlSitus() . '/toko/selesai.php';

$judul = 'Pembayaran';
$menu  = 'pembayaran';
require __DIR__ . '/inc/kepala.php';
?>

<?php if (!$konf['dikenal']): ?>
  <div class="pita pita-buruk"><span><strong>Gerbang tersimpan tidak dikenal:
    <code><?= e($konf['gerbang']) ?></code>.</strong> Checkout sedang menolak pesanan dengan
    503 — itu memang disengaja, supaya tidak ada yang diam-diam jatuh ke halaman simulasi.
    Pilih salah satu di bawah lalu simpan.</span></div>
<?php elseif ($modeKini === 'uji'): ?>
  <div class="pita pita-peringatan"><span><strong>Mode uji aktif.</strong> Belum ada uang sungguhan — checkout berakhir di halaman simulasi.</span></div>
<?php elseif ($modeKini === 'sandbox'): ?>
  <div class="pita pita-info"><span><strong>Duitku sandbox.</strong> Pembayaran lewat Duitku, tapi uangnya pura-pura. Cocok untuk uji terakhir sebelum produksi.</span></div>
<?php else: ?>
  <div class="pita pita-baik"><span><strong>Duitku produksi.</strong> Pembayaran sungguhan diterima.</span></div>
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
        <label for="merchant_code">Merchant Code</label>
        <input id="merchant_code" name="merchant_code" type="text" spellcheck="false"
               value="<?= e($kodeKini) ?>" placeholder="DXXXX">
        <p class="petunjuk">Dashboard Duitku → Project. Kode sandbox dan produksi berbeda.</p>
      </div>
      <div class="bidang">
        <label for="api_key">API Key</label>
        <input id="api_key" name="api_key" type="password" autocomplete="new-password" spellcheck="false"
               placeholder="<?= $kunciKini !== '' ? 'Tersimpan: ' . e(samarkanKunci($kunciKini)) . ' — kosongkan kalau tidak diganti' : 'Salin dari dashboard Duitku' ?>">
        <p class="petunjuk">Rahasia — hanya dipakai server, tidak pernah dikirim ke peramban.</p>
      </div>

      <?php if ($modeKini !== 'produksi'): ?>
      <label class="pa-centang" data-tampil-jika="mode=produksi">
        <input type="checkbox" name="yakin" value="1">
        <span>Saya sudah mencoba di sandbox, dan siap menerima pembayaran sungguhan.
          <span class="teks-kecil">Wajib dicentang saat pertama kali pindah ke produksi.</span></span>
      </label>
      <?php endif; ?>

      <p class="petunjuk">Kredensial dites langsung ke Duitku saat disimpan. Kalau ditolak, setelan lama tetap berlaku.</p>
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
      <div class="kotak-kepala"><h2>Isi di dashboard Duitku</h2></div>
      <p class="bagian-sub">Project → Setting. Tanpa ini status pembayaran tidak sampai ke panel.</p>
      <dl class="keadaan">
        <dt>Callback URL</dt>
        <dd><div class="salin-baris"><code><?= e($urlNotifikasi) ?></code><button class="tbl-salin" type="button" data-salin="<?= e($urlNotifikasi) ?>">Salin</button></div></dd>
        <dt>Return URL</dt>
        <dd><div class="salin-baris"><code><?= e($urlSelesai) ?></code><button class="tbl-salin" type="button" data-salin="<?= e($urlSelesai) ?>">Salin</button></div></dd>
      </dl>
    </section>
    <section class="kotak">
      <div class="kotak-kepala"><h2>Urutan yang aman</h2></div>
      <ol class="titik-daftar">
        <li>Coba alur lengkap di <strong>mode uji</strong>.</li>
        <li>Pindah ke <strong>sandbox</strong>, bayar lewat simulator Duitku, pastikan transaksi jadi Lunas.</li>
        <li>Baru pindah ke <strong>produksi</strong> dengan kunci produksi.</li>
      </ol>
    </section>
  </aside>
</div>

<?php require __DIR__ . '/inc/kaki.php'; ?>
