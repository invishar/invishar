<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
wajibMasuk();

const KONDISI = ['Baik', 'Perlu servis', 'Rusak', 'Dipinjam', 'Dilepas'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $id = (int) ($_POST['id'] ?? 0);

    if (masukan('aksi') === 'hapus' && $id > 0) {
        $nama = (string) ambilNilai('SELECT nama FROM aset WHERE id = ?', [$id]);
        q('DELETE FROM aset WHERE id = ?', [$id]);
        catatLog('hapus aset', $nama);
        pesan('Aset "' . $nama . '" dihapus.');
    } elseif (masukan('nama') === '') {
        pesan('Nama barang wajib diisi.', 'buruk');
    } else {
        $harga = masukan('harga_beli') === '' ? null : (int) preg_replace('/\D/', '', masukan('harga_beli'));
        $isi = [
            masukan('nama'),
            masukan('kategori') ?: 'Lainnya',
            masukan('nomor_seri'),
            masukan('tanggal_beli') !== '' ? masukan('tanggal_beli') : null,
            $harga,
            masukan('kondisi') ?: 'Baik',
            masukan('pemegang'),
            masukan('catatan'),
        ];

        if ($id > 0) {
            q('UPDATE aset SET nama = ?, kategori = ?, nomor_seri = ?, tanggal_beli = ?, harga_beli = ?,
                      kondisi = ?, pemegang = ?, catatan = ? WHERE id = ?', [...$isi, $id]);
            catatLog('ubah aset', $isi[0]);
            pesan('Aset "' . $isi[0] . '" diperbarui.');
        } else {
            q('INSERT INTO aset (nama, kategori, nomor_seri, tanggal_beli, harga_beli, kondisi, pemegang, catatan, dibuat_pada)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())', $isi);
            catatLog('tambah aset', $isi[0]);
            pesan('Aset "' . $isi[0] . '" dicatat.');
        }
    }
    pergi(tautan('gadget'));
}

$sunting = isset($_GET['sunting'])
    ? ambilSatu('SELECT * FROM aset WHERE id = ?', [(int) $_GET['sunting']])
    : null;

$daftar    = ambilSemua('SELECT * FROM aset ORDER BY kategori, nama');
$nilaiAset = (int) ambilNilai('SELECT COALESCE(SUM(harga_beli), 0) FROM aset');

$judul = 'Gadget';
$menu  = 'gadget';
require __DIR__ . '/inc/kepala.php';
?>

<p class="pengantar">
  Perangkat milik Invishar &mdash; laptop, ponsel, kamera, perangkat jaringan. Akun layanan (hosting, domain, langganan) ada di menu <a href="<?= tautan('akun') ?>">Akun</a>.
  Stok barang dagang bukan di sini, itu ada di aplikasi catatorder.
  <?php if ($daftar): ?>
    <strong><?= count($daftar) ?> barang</strong>, nilai beli <strong><?= e(rupiah($nilaiAset)) ?></strong>.
  <?php endif; ?>
</p>

<?php if (!$daftar): ?>
  <p class="kosong">Belum ada gadget tercatat. Catat yang pertama di formulir bawah.</p>
<?php else: ?>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr><th>Barang</th><th>Kategori</th><th>Dibeli</th><th>Harga</th><th>Pemegang</th><th>Kondisi</th></tr>
      </thead>
      <tbody>
        <?php foreach ($daftar as $a): ?>
          <tr onclick="location='<?= tautan('gadget/' . (int) $a['id']) ?>'">
            <td>
              <a class="tabel-utama" href="<?= tautan('gadget/' . (int) $a['id']) ?>"><?= e($a['nama']) ?></a>
              <?php if ($a['nomor_seri']): ?><span class="tabel-sub"><?= e($a['nomor_seri']) ?></span><?php endif; ?>
            </td>
            <td><?= e($a['kategori']) ?></td>
            <td class="tabel-tipis"><?= $a['tanggal_beli'] ? e(date('j M Y', strtotime($a['tanggal_beli']))) : '—' ?></td>
            <td class="tabel-tipis"><?= e(rupiah($a['harga_beli'] !== null ? (int) $a['harga_beli'] : null)) ?></td>
            <td><?= e($a['pemegang'] ?: '—') ?></td>
            <td><span class="tanda tanda-<?= e(slugkan($a['kondisi'])) ?>"><?= e($a['kondisi']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<section class="kotak kotak-form">
  <div class="kotak-kepala">
    <h2><?= $sunting ? 'Ubah gadget' : 'Catat gadget baru' ?></h2>
    <?php if ($sunting): ?><a class="tautan-lain" href="<?= tautan('gadget') ?>">Batal</a><?php endif; ?>
  </div>

  <form method="post" class="form-panel">
    <?= csrfInput() ?>
    <input type="hidden" name="id" value="<?= (int) ($sunting['id'] ?? 0) ?>">

    <div class="baris-form">
      <div class="bidang">
        <label for="nama">Nama barang</label>
        <input id="nama" name="nama" type="text" value="<?= e($sunting['nama'] ?? '') ?>" required>
      </div>
      <div class="bidang">
        <label for="kategori">Kategori</label>
        <input id="kategori" name="kategori" type="text" value="<?= e($sunting['kategori'] ?? '') ?>"
               placeholder="Komputer, Kamera, Jaringan…" list="daftar-kategori">
        <datalist id="daftar-kategori">
          <?php foreach (ambilSemua('SELECT DISTINCT kategori FROM aset ORDER BY kategori') as $k): ?>
            <option value="<?= e($k['kategori']) ?>"></option>
          <?php endforeach; ?>
        </datalist>
      </div>
      <div class="bidang">
        <label for="nomor_seri">Nomor seri</label>
        <input id="nomor_seri" name="nomor_seri" type="text" value="<?= e($sunting['nomor_seri'] ?? '') ?>">
      </div>
    </div>

    <div class="baris-form">
      <div class="bidang">
        <label for="tanggal_beli">Tanggal beli</label>
        <input id="tanggal_beli" name="tanggal_beli" type="date" value="<?= e($sunting['tanggal_beli'] ?? '') ?>">
      </div>
      <div class="bidang">
        <label for="harga_beli">Harga beli (Rp)</label>
        <input id="harga_beli" name="harga_beli" type="text" inputmode="numeric"
               value="<?= $sunting && $sunting['harga_beli'] !== null ? (int) $sunting['harga_beli'] : '' ?>">
      </div>
      <div class="bidang">
        <label for="kondisi">Kondisi</label>
        <select id="kondisi" name="kondisi">
          <?php foreach (KONDISI as $k): ?>
            <option value="<?= e($k) ?>"<?= ($sunting['kondisi'] ?? '') === $k ? ' selected' : '' ?>><?= e($k) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="bidang">
        <label for="pemegang">Pemegang</label>
        <input id="pemegang" name="pemegang" type="text" value="<?= e($sunting['pemegang'] ?? '') ?>">
      </div>
    </div>

    <div class="bidang">
      <label for="catatan">Catatan</label>
      <textarea id="catatan" name="catatan" rows="2"><?= e($sunting['catatan'] ?? '') ?></textarea>
    </div>

    <div class="form-aksi">
      <button class="tbl tbl-utama" type="submit"><?= $sunting ? 'Simpan' : 'Catat aset' ?></button>
      <?php if ($sunting): ?>
        <button class="tbl tbl-bahaya" type="submit" name="aksi" value="hapus"
                data-pastikan="Hapus <?= e($sunting['nama']) ?> dari daftar gadget?">Hapus</button>
      <?php endif; ?>
    </div>
  </form>
</section>

<?php require __DIR__ . '/inc/kaki.php'; ?>
