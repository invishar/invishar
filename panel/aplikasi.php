<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
wajibMasuk();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $aksi = masukan('aksi');
    $id   = (int) ($_POST['id'] ?? 0);

    if ($aksi === 'hapus' && $id > 0) {
        $nama = (string) ambilNilai('SELECT nama FROM aplikasi WHERE id = ?', [$id]);
        q('DELETE FROM aplikasi WHERE id = ?', [$id]);
        catatLog('hapus aplikasi', $nama);
        pesan('Aplikasi "' . $nama . '" dihapus dari daftar.');
    } else {
        $isi = [masukan('nama'), masukan('keterangan'), masukan('url_admin'), masukan('url_situs'), (int) ($_POST['urutan'] ?? 0)];

        if ($isi[0] === '') {
            pesan('Nama aplikasi wajib diisi.', 'buruk');
        } elseif ($id > 0) {
            q('UPDATE aplikasi SET nama = ?, keterangan = ?, url_admin = ?, url_situs = ?, urutan = ? WHERE id = ?',
                [...$isi, $id]);
            catatLog('ubah aplikasi', $isi[0]);
            pesan('Aplikasi "' . $isi[0] . '" diperbarui.');
        } else {
            q('INSERT INTO aplikasi (nama, keterangan, url_admin, url_situs, urutan) VALUES (?, ?, ?, ?, ?)', $isi);
            catatLog('tambah aplikasi', $isi[0]);
            pesan('Aplikasi "' . $isi[0] . '" ditambahkan.');
        }
    }
    pergi('aplikasi.php');
}

$sunting = null;
if (isset($_GET['sunting'])) {
    $sunting = ambilSatu('SELECT * FROM aplikasi WHERE id = ?', [(int) $_GET['sunting']]);
}

$daftar = ambilSemua('SELECT * FROM aplikasi ORDER BY urutan, nama');

$judul = 'Aplikasi';
$menu  = 'aplikasi';
require __DIR__ . '/inc/kepala.php';
?>

<p class="pengantar">Pintu masuk ke admin tiap produk Invishar. Panel ini tidak mengelola datanya &mdash; hanya menyimpan alamatnya supaya tidak perlu diingat satu per satu.</p>

<div class="kartu-kisi">
  <?php foreach ($daftar as $a): ?>
    <article class="kartu-app">
      <h2><?= e($a['nama']) ?></h2>
      <p><?= e($a['keterangan']) ?></p>
      <div class="kartu-app-kaki">
        <?php if ($a['url_admin']): ?>
          <a class="tbl tbl-kecil tbl-utama" href="<?= e($a['url_admin']) ?>" target="_blank" rel="noopener">Buka admin</a>
        <?php else: ?>
          <span class="petunjuk">Alamat admin belum diisi</span>
        <?php endif; ?>
        <?php if ($a['url_situs']): ?>
          <a class="tbl tbl-kecil" href="<?= e($a['url_situs']) ?>" target="_blank" rel="noopener">Situs</a>
        <?php endif; ?>
        <a class="tautan-lain" href="aplikasi.php?sunting=<?= (int) $a['id'] ?>">Ubah</a>
      </div>
    </article>
  <?php endforeach; ?>

  <article class="kartu-app kartu-app-kelas">
    <h2>Kelas (course)</h2>
    <p>Belum punya admin sendiri &mdash; dikelola langsung di panel ini.</p>
    <div class="kartu-app-kaki">
      <a class="tbl tbl-kecil tbl-utama" href="kelas.php">Kelola kelas</a>
      <a class="tbl tbl-kecil" href="https://invishar.com/kelas.html" target="_blank" rel="noopener">Situs</a>
    </div>
  </article>
</div>

<section class="kotak kotak-form">
  <div class="kotak-kepala">
    <h2><?= $sunting ? 'Ubah aplikasi' : 'Tambah aplikasi' ?></h2>
    <?php if ($sunting): ?><a class="tautan-lain" href="aplikasi.php">Batal</a><?php endif; ?>
  </div>

  <form method="post" class="form-panel">
    <?= csrfInput() ?>
    <input type="hidden" name="id" value="<?= (int) ($sunting['id'] ?? 0) ?>">

    <div class="baris-form">
      <div class="bidang">
        <label for="nama">Nama</label>
        <input id="nama" name="nama" type="text" value="<?= e($sunting['nama'] ?? '') ?>" required>
      </div>
      <div class="bidang bidang-kecil">
        <label for="urutan">Urutan</label>
        <input id="urutan" name="urutan" type="number" value="<?= (int) ($sunting['urutan'] ?? 0) ?>">
      </div>
    </div>

    <div class="bidang">
      <label for="keterangan">Keterangan singkat</label>
      <input id="keterangan" name="keterangan" type="text" value="<?= e($sunting['keterangan'] ?? '') ?>">
    </div>

    <div class="baris-form">
      <div class="bidang">
        <label for="url_admin">Alamat admin</label>
        <input id="url_admin" name="url_admin" type="url" placeholder="https://…" value="<?= e($sunting['url_admin'] ?? '') ?>">
      </div>
      <div class="bidang">
        <label for="url_situs">Alamat situs</label>
        <input id="url_situs" name="url_situs" type="url" placeholder="https://…" value="<?= e($sunting['url_situs'] ?? '') ?>">
      </div>
    </div>

    <div class="form-aksi">
      <button class="tbl tbl-utama" type="submit"><?= $sunting ? 'Simpan perubahan' : 'Tambah' ?></button>
      <?php if ($sunting): ?>
        <button class="tbl tbl-bahaya" type="submit" name="aksi" value="hapus"
                data-pastikan="Hapus <?= e($sunting['nama']) ?> dari daftar aplikasi?">Hapus</button>
      <?php endif; ?>
    </div>
  </form>
</section>

<?php require __DIR__ . '/inc/kaki.php'; ?>
