<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
wajibMasuk();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $judulBaru = masukan('judul');

    if ($judulBaru === '') {
        pesan('Judul kelas wajib diisi.', 'buruk');
    } else {
        $slug = slugkan(masukan('slug') !== '' ? masukan('slug') : $judulBaru);
        if (ambilNilai('SELECT id FROM kelas WHERE slug = ?', [$slug]) !== null) {
            $slug .= '-' . substr((string) time(), -4);
        }
        $urutan = (int) ambilNilai('SELECT COALESCE(MAX(urutan), -1) + 1 FROM kelas');

        q(
            'INSERT INTO kelas (slug, judul, ringkas, urutan, detail, diperbarui_pada) VALUES (?, ?, \'\', ?, ?, NOW())',
            [$slug, $judulBaru, $urutan, json_encode([
                'kicker'   => 'Kelas',
                'bahasa'   => 'Bahasa Indonesia',
                'akses'    => 'Akses selamanya',
                'pengajar' => ['nama' => 'Tim Invishar', 'peran' => 'Studio produk digital'],
                'ikhtisar' => ['hasil' => [], 'untukSiapa' => [], 'syarat' => []],
                'sumber'   => [],
                'tanya'    => [],
            ], JSON_UNESCAPED_UNICODE)]
        );
        $id = (int) db()->lastInsertId();
        catatLog('tambah kelas', $judulBaru);
        pesan('Kelas "' . $judulBaru . '" dibuat. Lengkapi isinya di bawah.');
        pergi('kelas-edit.php?id=' . $id);
    }
    pergi('kelas.php');
}

$daftar = ambilSemua(
    'SELECT k.*,
            (SELECT COUNT(*) FROM modul m WHERE m.kelas_id = k.id) AS jml_modul,
            (SELECT COUNT(*) FROM materi x JOIN modul m ON m.id = x.modul_id WHERE m.kelas_id = k.id) AS jml_materi
     FROM kelas k ORDER BY k.urutan, k.judul'
);

$berkasTerbit = rtrim((string) konfig('situs_data'), '/') . '/kelas.json';
$terbitPada   = is_file($berkasTerbit) ? date('Y-m-d H:i:s', (int) filemtime($berkasTerbit)) : null;
$perluTerbit  = false;
foreach ($daftar as $k) {
    if ($terbitPada === null || strtotime($k['diperbarui_pada']) > strtotime($terbitPada)) {
        $perluTerbit = true;
        break;
    }
}

$judul = 'Kelas';
$menu  = 'kelas';

ob_start(); ?>
  <form method="post" action="terbitkan.php" class="sebaris">
    <?= csrfInput() ?>
    <button class="tbl <?= $perluTerbit ? 'tbl-utama' : '' ?>" type="submit">Terbitkan ke situs</button>
  </form>
<?php
$aksiKepala = ob_get_clean();

require __DIR__ . '/inc/kepala.php';
?>

<p class="pengantar">
  Kelas di sini adalah sumbernya. Situs baru berubah setelah ditekan <strong>Terbitkan</strong>.
  <?php if ($terbitPada): ?>
    Terakhir terbit <?= e(waktuIndo($terbitPada)) ?>.
  <?php else: ?>
    Belum pernah diterbitkan &mdash; situs masih memakai data bawaan.
  <?php endif; ?>
</p>

<?php if ($perluTerbit && $terbitPada): ?>
  <p class="kabar kabar-peringatan">Ada perubahan yang belum diterbitkan.</p>
<?php endif; ?>

<div class="tabel-bungkus">
  <table class="tabel">
    <thead>
      <tr><th>Kelas</th><th>Kategori</th><th>Isi</th><th>Harga</th><th>Status</th></tr>
    </thead>
    <tbody>
      <?php foreach ($daftar as $k): ?>
        <tr onclick="location='kelas-edit.php?id=<?= (int) $k['id'] ?>'">
          <td>
            <a class="tabel-utama" href="kelas-edit.php?id=<?= (int) $k['id'] ?>"><?= e($k['judul']) ?></a>
            <span class="tabel-sub"><?= e($k['slug']) ?></span>
          </td>
          <td><?= e($k['kategori']) ?></td>
          <td class="tabel-tipis"><?= (int) $k['jml_modul'] ?> modul · <?= (int) $k['jml_materi'] ?> materi</td>
          <td><?= e($k['harga']) ?></td>
          <td><span class="tanda tanda-<?= e(strtolower($k['status'])) ?>"><?= e($k['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<section class="kotak kotak-form">
  <div class="kotak-kepala"><h2>Kelas baru</h2></div>
  <form method="post" class="form-panel">
    <?= csrfInput() ?>
    <div class="baris-form">
      <div class="bidang">
        <label for="judul">Judul</label>
        <input id="judul" name="judul" type="text" required>
      </div>
      <div class="bidang">
        <label for="slug">Slug</label>
        <input id="slug" name="slug" type="text" placeholder="dibuat otomatis dari judul">
      </div>
    </div>
    <div class="form-aksi">
      <button class="tbl tbl-utama" type="submit">Buat kelas</button>
    </div>
  </form>
</section>

<?php require __DIR__ . '/inc/kaki.php'; ?>
