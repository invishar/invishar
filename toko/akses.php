<?php
declare(strict_types=1);

/* =============================================================================
   Halaman akses pembeli: /toko/akses.php?t={token}

   Tautan permanen yang terbit saat transaksi lunas. Menampilkan apa yang
   dibeli: tautan unduhan + instruksi untuk produk digital, atau daftar materi
   untuk produk kelas (dibuka lewat /toko/materi.php yang menggembok token).

   Token 48 heksadesimal acak — tidak bisa ditebak. Halaman ini noindex.
   Parameter ?o={kode_order} diterima sebagai jalan pemulihan: bila transaksinya
   lunas, pengunjung diarahkan ke tautan tokennya.
   ============================================================================= */

require __DIR__ . '/inc/awal.php';

header('Cache-Control: no-store, max-age=0');

$token = (string) ($_GET['t'] ?? '');

/* Tanpa token: form pemulihan lewat kode pesanan. */
if ($token === '' && !isset($_GET['o'])) {
    $judulHalaman = 'Buka akses pembelian';
    require __DIR__ . '/inc/kepala.php';
    ?>
    <section class="toko-wrap toko-sempit">
      <h1>Buka akses pembelian</h1>
      <p class="toko-sub">Masukkan kode pesanan dari bukti pembayaran Anda (contoh: INV-260101-AB12CD).</p>
      <form class="form toko-form" method="get" action="/toko/akses.php">
        <div class="field">
          <label for="kode">Kode pesanan</label>
          <input id="kode" name="o" type="text" inputmode="text" autocomplete="off"
                 placeholder="INV-000000-XXXXXX" pattern="INV-[0-9]{6}-[A-Z0-9]{6}" required
                 style="text-transform:uppercase">
        </div>
        <button class="btn btn-solid btn-block" type="submit">Buka akses</button>
      </form>
    </section>
    <?php
    require __DIR__ . '/inc/kaki.php';
    exit;
}

/* Jalan pemulihan: kode order → token. */
if ($token === '' && isset($_GET['o'])) {
    $kode = strtoupper(trim((string) $_GET['o']));
    $trx = preg_match('/^INV-\d{6}-[A-Z0-9]{6}$/', $kode)
        ? ambilSatu("SELECT * FROM transaksi WHERE kode_order = ? AND status = 'lunas'", [$kode])
        : null;
    try {
        if ($trx) {
            $trx = pastikanTokenAkses($trx);
        }
    } catch (Throwable $e) {
        error_log('[invishar akses] ' . $e->getMessage());
        $trx = null;
    }
    if ($trx && !empty($trx['akses_token'])) {
        header('Location: /toko/akses.php?t=' . $trx['akses_token'], true, 302);
        exit;
    }
    halamanBuntu(404, 'Akses tidak ditemukan', 'Periksa lagi kode pesanan Anda, atau hubungi kami lewat WhatsApp dengan menyebutkan kode tersebut.', '/#kontak', 'Hubungi kami');
}

$trx = aksesDariToken($token);
if (!$trx) {
    halamanBuntu(404, 'Tautan akses tidak valid', 'Tautan ini salah, kedaluwarsa, atau pesanannya sudah dikembalikan. Minta tautan baru lewat WhatsApp dengan menyebutkan kode pesanan Anda.', '/#kontak', 'Hubungi kami');
}

$produk = $trx['produk'];
$namaDepan = explode(' ', trim($trx['pembeli_nama']))[0] ?? '';

$kelasSaya = [];
if (($produk['kategori'] ?? '') === 'kelas') {
    $kelasSaya = kelasUntukToken((int) $trx['id']);
}

$daftarMateri = [];
foreach ($kelasSaya as $k) {
    $modul = ambilSemua('SELECT * FROM modul WHERE kelas_id = ? ORDER BY urutan, id', [$k['id']]);
    $isi = [];
    foreach ($modul as $m) {
        $isi[] = [
            'modul'  => $m,
            'materi' => ambilSemua('SELECT id, kode, judul, durasi FROM materi WHERE modul_id = ? ORDER BY urutan, id', [$m['id']]),
        ];
    }
    $daftarMateri[] = ['kelas' => $k, 'isi' => $isi];
}

$punyaUnduhan = !empty($produk['akses_tautan']);
$punyaInstruksi = trim((string) ($produk['akses_catatan'] ?? '')) !== '';
$punyaKelas = count($daftarMateri) > 0;

$judulHalaman = 'Akses: ' . $produk['nama'];
require __DIR__ . '/inc/kepala.php';
?>

<section class="toko-wrap">
  <p class="ringkasan-label">Akses pembelian</p>
  <h1><?= e($produk['nama']) ?></h1>
  <p class="toko-sub">Halo, <?= e($namaDepan) ?>! Ini halaman akses permanen Anda — simpan tautannya, bisa dibuka kapan saja tanpa login.</p>

  <dl class="rincian">
    <div><dt>Kode pesanan</dt><dd class="kode"><?= e($trx['kode_order']) ?></dd></div>
    <div><dt>Dibayar</dt><dd><?= e(waktuIndo($trx['dibayar_pada'])) ?></dd></div>
  </dl>

  <?php if ($punyaUnduhan): ?>
    <p style="margin:22px 0 0">
      <a class="btn btn-solid btn-block" href="<?= e($produk['akses_tautan']) ?>" target="_blank" rel="noopener">Unduh / buka produk</a>
    </p>
  <?php endif; ?>

  <?php if ($punyaInstruksi): ?>
    <div class="kartu-bayar" style="margin-top:18px;text-align:left">
      <p class="ringkasan-label">Cara memakai</p>
      <p style="white-space:pre-line;margin:0"><?= e($produk['akses_catatan']) ?></p>
    </div>
  <?php endif; ?>

  <?php foreach ($daftarMateri as $dm): ?>
    <div class="kartu-bayar" style="margin-top:18px;text-align:left">
      <p class="ringkasan-label">Materi kelas</p>
      <p class="ringkasan-nama"><?= e($dm['kelas']['judul']) ?></p>
      <?php foreach ($dm['isi'] as $blok): ?>
        <p style="font-weight:600;margin:16px 0 8px"><?= e($blok['modul']['judul']) ?></p>
        <ul style="list-style:none;margin:0;padding:0;display:grid;gap:8px">
          <?php foreach ($blok['materi'] as $mt): ?>
            <li>
              <a class="btn btn-outline btn-block" style="text-align:left"
                 href="/toko/materi.php?t=<?= e($token) ?>&amp;k=<?= (int) $dm['kelas']['id'] ?>&amp;m=<?= (int) $mt['id'] ?>">
                <?= e($mt['judul']) ?>
                <?php if ($mt['durasi']): ?><span style="opacity:.65"> · <?= e($mt['durasi']) ?></span><?php endif; ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <?php if (!$punyaUnduhan && !$punyaInstruksi && !$punyaKelas): ?>
    <div class="kartu-bayar" style="margin-top:18px">
      <p class="toko-sub" style="margin:0">Akses produk ini sedang disiapkan. Kami menghubungi Anda lewat WhatsApp maksimal 1×24 jam. Simpan kode pesanan di atas untuk berjaga-jaga.</p>
    </div>
  <?php endif; ?>

  <p class="toko-sub" style="margin-top:22px;font-size:13px">Tautan ini pribadi — jangan dibagikan. Butuh bantuan? Hubungi kami lewat WhatsApp dengan menyebutkan kode pesanan.</p>
</section>

<?php require __DIR__ . '/inc/kaki.php'; ?>
