<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
require_once __DIR__ . '/inc/order.php';
wajibMasuk();

/* Tambah order manual — banyak permintaan masuk lewat WhatsApp, bukan form. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();

    $nama      = masukan('nama');
    $kebutuhan = masukan('kebutuhan');

    if ($nama === '' || $kebutuhan === '') {
        pesan('Nama dan kebutuhan wajib diisi.', 'buruk');
    } else {
        // Produk dan affiliator opsional — hanya yang benar-benar ada yang disimpan.
        $produkId = (int) masukan('produk_id');
        $produkId = $produkId > 0 && ambilNilai('SELECT 1 FROM produk WHERE id = ?', [$produkId]) ? $produkId : null;
        $affId = (int) masukan('affiliate_id');
        $affId = $affId > 0 && ambilNilai("SELECT 1 FROM affiliate WHERE id = ? AND status = 'aktif'", [$affId]) ? $affId : null;
        q(
            'INSERT INTO order_jasa (nama, lembaga, surel, whatsapp, kebutuhan, sumber, produk_id, affiliate_id, status, dibuat_pada, diperbarui_pada)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'baru\', NOW(), NOW())',
            [$nama, masukan('lembaga'), masukan('surel'), masukan('whatsapp'), $kebutuhan, masukan('sumber') ?: 'manual', $produkId, $affId]
        );
        $id = (int) db()->lastInsertId();
        q('INSERT INTO order_riwayat (order_id, status_baru, catatan, dibuat_pada) VALUES (?, \'baru\', ?, NOW())',
            [$id, 'Dicatat manual']);
        catatLog('tambah order', $nama);
        pesan('Permintaan dari "' . $nama . '" dicatat.');
        pergi(tautan('order/' . $id));
    }
    pergi(tautan('order') . '?baru=1');
}

/* Daftar order jasa sekarang menyatu dengan Transaksi. Halaman ini tinggal
   formulir pencatatan manual (?baru=1). */
if (!isset($_GET['baru'])) {
    pergi(tautan('transaksi') . '?kategori=jasa');
}

wajibPenjualanSiap();
$produkJasa = ambilSemua("SELECT id, nama, kategori FROM produk WHERE status <> 'arsip' ORDER BY FIELD(kategori, 'jasa', 'produk', 'kelas'), nama");
$mitraAktif = ambilSemua("SELECT id, nama, kode FROM affiliate WHERE status = 'aktif' ORDER BY nama");

$judul = 'Catat permintaan jasa';
$menu  = 'transaksi';
require __DIR__ . '/inc/kepala.php';
?>

<p class="remah"><a href="<?= tautan('transaksi') ?>">&larr; Semua transaksi</a></p>

<section class="kotak kotak-form kotak-sempit">
  <div class="kotak-kepala">
    <h2>Permintaan yang masuk lewat WhatsApp atau telepon</h2>
  </div>

  <form method="post" class="form-panel">
    <?= csrfInput() ?>
    <div class="baris-form">
      <div class="bidang">
        <label for="nama">Nama</label>
        <input id="nama" name="nama" type="text" required>
      </div>
      <div class="bidang">
        <label for="lembaga">Lembaga</label>
        <input id="lembaga" name="lembaga" type="text">
      </div>
    </div>

    <div class="baris-form">
      <div class="bidang">
        <label for="surel">Surel</label>
        <input id="surel" name="surel" type="email">
      </div>
      <div class="bidang">
        <label for="whatsapp">WhatsApp</label>
        <input id="whatsapp" name="whatsapp" type="text" placeholder="+62…">
      </div>
      <div class="bidang bidang-kecil">
        <label for="sumber">Sumber</label>
        <input id="sumber" name="sumber" type="text" value="manual">
      </div>
    </div>

    <div class="baris-form">
      <div class="bidang">
        <label for="produk_id">Produk yang ditanyakan <span class="teks-kecil">(opsional)</span></label>
        <select id="produk_id" name="produk_id">
          <option value="0">— Belum jelas / umum —</option>
          <?php foreach ($produkJasa as $p): ?>
            <option value="<?= (int) $p['id'] ?>"><?= e($p['nama']) ?> · <?= e(KATEGORI_PRODUK[$p['kategori']] ?? '') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="bidang">
        <label for="affiliate_id">Dibawa affiliator <span class="teks-kecil">(opsional)</span></label>
        <select id="affiliate_id" name="affiliate_id">
          <option value="0">— Tidak ada —</option>
          <?php foreach ($mitraAktif as $a): ?>
            <option value="<?= (int) $a['id'] ?>"><?= e($a['nama']) ?> · <?= e((string) $a['kode']) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="petunjuk">Kalau diisi, komisi dihitung saat pembayarannya dicatat.</p>
      </div>
    </div>

    <div class="bidang">
      <label for="kebutuhan">Yang ingin dibereskan</label>
      <textarea id="kebutuhan" name="kebutuhan" rows="3" required></textarea>
    </div>

    <div class="form-aksi">
      <button class="tbl tbl-utama" type="submit">Catat order</button>
    </div>
  </form>
</section>

<?php require __DIR__ . '/inc/kaki.php'; ?>
