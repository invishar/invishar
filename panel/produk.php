<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
require __DIR__ . '/inc/produk.php';
wajibMasuk();
wajibPenjualanSiap();

/* =============================================================================
   Produk — semua yang dijual: Produk, Jasa, dan Kelas.
   Tambah, sunting, dan putuskan tayang/tidaknya di sini. Komisi affiliate
   diatur terpisah di Afiliasi → Produk afiliasi.
   ============================================================================= */

/* ---- tombol cepat: tayangkan / turunkan ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $kembali = tautan('produk') . (($q = (string) ($_POST['kembali'] ?? '')) !== '' && $q[0] === '?' ? $q : '');
    $p = ambilSatu('SELECT * FROM produk WHERE id = ?', [(int) masukan('id')]);
    if (!$p) {
        pesan('Produk tidak ditemukan.', 'buruk');
        pergi($kembali);
    }
    if (masukan('aksi') === 'tayang') {
        $alasan = alasanBelumTayang($p);
        if ($alasan) {
            pesan('"' . $p['nama'] . '" belum bisa ditayangkan: ' . $alasan . '. Lengkapi dulu di sini.', 'buruk');
            pergi(tautan('produk/' . (int) $p['id']));
        }
        q("UPDATE produk SET status = 'aktif', diperbarui_pada = NOW() WHERE id = ?", [$p['id']]);
        catatLog('tayangkan produk', $p['nama']);
        $g = terbitkanProduk();
        pesan($g ? 'Tayang, tapi landing page gagal diperbarui: ' . $g
            : '"' . $p['nama'] . '" tayang di ' . preg_replace('#^https?://#', '', urlSitus()) . '/p/' . $p['slug'] . '.', $g ? 'peringatan' : 'baik');
    } elseif (masukan('aksi') === 'turunkan') {
        q("UPDATE produk SET status = 'draf', diperbarui_pada = NOW() WHERE id = ?", [$p['id']]);
        catatLog('turunkan produk', $p['nama']);
        terbitkanProduk();
        pesan('"' . $p['nama'] . '" diturunkan menjadi Draf. Landing page-nya tidak bisa dibuka dan tidak bisa dibeli.');
    }
    pergi($kembali);
}

// Kelas baru (atau dari data lama) otomatis punya baris produk berstatus Draf.
sinkronKelasProduk();

/* ---- tampilan: daftar atau grid, diingat per peramban ---- */
$tampil = $_GET['tampil'] ?? ($_COOKIE['panel_tampil_produk'] ?? 'daftar');
$tampil = $tampil === 'grid' ? 'grid' : 'daftar';
if (isset($_GET['tampil'])) {
    setcookie('panel_tampil_produk', $tampil, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true]);
}

/* ---- saringan ---- */
$f = [
    'kategori' => (string) ($_GET['kategori'] ?? ''),
    'status'   => (string) ($_GET['status'] ?? ''),
    'q'        => trim((string) ($_GET['q'] ?? '')),
];
if (!isset(KATEGORI_PRODUK[$f['kategori']])) {
    $f['kategori'] = '';
}
if (!isset(STATUS_PRODUK[$f['status']])) {
    $f['status'] = '';
}

$syarat = [];
$isi = [];
if ($f['kategori'] !== '') {
    $syarat[] = 'p.kategori = ?';
    $isi[] = $f['kategori'];
}
if ($f['status'] !== '') {
    $syarat[] = 'p.status = ?';
    $isi[] = $f['status'];
} else {
    $syarat[] = "p.status <> 'arsip'";   // arsip hanya tampil kalau dipilih
}
if ($f['q'] !== '') {
    $syarat[] = '(p.nama LIKE ? OR p.slug LIKE ? OR p.tagline LIKE ?)';
    $kata = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $f['q']) . '%';
    array_push($isi, $kata, $kata, $kata);
}

$daftar = ambilSemua(
    "SELECT p.*, k.gambar AS kelas_gambar, k.status AS kelas_status,
            (SELECT COUNT(*) FROM transaksi t WHERE t.produk_id = p.id AND t.status = 'lunas') AS terjual,
            (SELECT COALESCE(SUM(t.jumlah), 0) FROM transaksi t WHERE t.produk_id = p.id AND t.status = 'lunas') AS omzet,
            (SELECT COUNT(*) FROM modul m JOIN materi x ON x.modul_id = m.id WHERE m.kelas_id = p.kelas_id) AS jml_materi
       FROM produk p
       LEFT JOIN kelas k ON k.id = p.kelas_id
      WHERE " . implode(' AND ', $syarat) . "
      ORDER BY FIELD(p.status, 'aktif', 'draf', 'arsip'), FIELD(p.kategori, 'produk', 'jasa', 'kelas'), p.urutan, p.nama",
    $isi
);

$hitungKategori = [];
foreach (ambilSemua("SELECT kategori, COUNT(*) AS n FROM produk WHERE status <> 'arsip' GROUP BY kategori") as $b) {
    $hitungKategori[$b['kategori']] = (int) $b['n'];
}
$hitungStatus = [];
foreach (ambilSemua('SELECT status, COUNT(*) AS n FROM produk' . ($f['kategori'] !== '' ? ' WHERE kategori = ?' : '') . ' GROUP BY status', $f['kategori'] !== '' ? [$f['kategori']] : []) as $b) {
    $hitungStatus[$b['status']] = (int) $b['n'];
}

// Galeri kelas (kelas.html) diterbitkan terpisah dari landing page produk.
$berkasKelas = rtrim((string) konfig('situs_data'), '/') . '/kelas.json';
$kelasTerbit = is_file($berkasKelas) ? (int) filemtime($berkasKelas) : 0;
$kelasUbah = (string) ambilNilai('SELECT MAX(diperbarui_pada) FROM kelas');
$galeriKelasUsang = $kelasUbah !== '' && strtotime($kelasUbah) > $kelasTerbit;

function tautanProdukSaring(array $f, array $ubah = []): string
{
    $q = array_filter(array_merge($f, $ubah), fn ($v) => $v !== '' && $v !== null);
    return tautan('produk') . ($q ? '?' . http_build_query($q) : '');
}
$kembaliIni = ($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';

$judul = 'Produk';
$menu  = 'produk';
$aksiKepala = '<a class="tbl tbl-utama" href="' . e(tautan('produk-baru')) . '">+ Tambah produk</a>';
require __DIR__ . '/inc/kepala.php';
?>

<p class="pengantar">
  Semua yang dijual ada di sini. Produk berstatus <strong>Tayang</strong> punya landing page di
  <code><?= e(preg_replace('#^https?://#', '', urlSitus())) ?>/p/nama-produk</code> dan bisa dipesan.
  Komisi untuk mitra diatur di <a href="<?= tautan('produk-affiliate') ?>">Produk afiliasi</a>.
</p>

<?php if ($galeriKelasUsang && ($f['kategori'] === '' || $f['kategori'] === 'kelas')): ?>
  <div class="pita pita-peringatan">
    <span><strong>Galeri kelas belum diperbarui.</strong> Ada perubahan isi kelas yang belum tampil di invishar.com/kelas.html.</span>
    <form method="post" action="<?= tautan('terbitkan') ?>" class="sebaris">
      <?= csrfInput() ?>
      <button class="tbl tbl-kecil tbl-utama" type="submit">Terbitkan galeri kelas</button>
    </form>
  </div>
<?php endif; ?>

<div class="produk-alat">
  <div class="saring" role="tablist" aria-label="Kategori">
    <a class="cip<?= $f['kategori'] === '' ? ' is-on' : '' ?>" href="<?= e(tautanProdukSaring($f, ['kategori' => ''])) ?>">Semua <span><?= array_sum($hitungKategori) ?></span></a>
    <?php foreach (KATEGORI_PRODUK as $kunci => $label): ?>
      <a class="cip<?= $f['kategori'] === $kunci ? ' is-on' : '' ?>" href="<?= e(tautanProdukSaring($f, ['kategori' => $kunci])) ?>"><?= e($label) ?> <span><?= $hitungKategori[$kunci] ?? 0 ?></span></a>
    <?php endforeach; ?>
  </div>

  <form class="produk-cari" method="get" action="<?= tautan('produk') ?>" role="search">
    <?php if ($f['kategori'] !== ''): ?><input type="hidden" name="kategori" value="<?= e($f['kategori']) ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Cari produk" aria-label="Cari produk">
    <select name="status" aria-label="Status" onchange="this.form.submit()">
      <option value="">Tayang &amp; draf</option>
      <?php foreach (STATUS_PRODUK as $kunci => $label): ?>
        <option value="<?= e($kunci) ?>"<?= $f['status'] === $kunci ? ' selected' : '' ?>><?= e($label) ?> (<?= $hitungStatus[$kunci] ?? 0 ?>)</option>
      <?php endforeach; ?>
    </select>
    <div class="tampil-pilih" role="group" aria-label="Tampilan">
      <a class="<?= $tampil === 'daftar' ? 'is-on' : '' ?>" href="<?= e(tautanProdukSaring($f, ['tampil' => 'daftar'])) ?>" title="Tampilan daftar" aria-label="Tampilan daftar">
        <svg viewBox="0 0 20 20" width="16" height="16" aria-hidden="true"><path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      </a>
      <a class="<?= $tampil === 'grid' ? 'is-on' : '' ?>" href="<?= e(tautanProdukSaring($f, ['tampil' => 'grid'])) ?>" title="Tampilan kartu" aria-label="Tampilan kartu">
        <svg viewBox="0 0 20 20" width="16" height="16" aria-hidden="true"><rect x="3" y="3" width="6" height="6" rx="1.5" fill="currentColor"/><rect x="11" y="3" width="6" height="6" rx="1.5" fill="currentColor"/><rect x="3" y="11" width="6" height="6" rx="1.5" fill="currentColor"/><rect x="11" y="11" width="6" height="6" rx="1.5" fill="currentColor"/></svg>
      </a>
    </div>
  </form>
</div>

<?php if (!$daftar && array_sum($hitungStatus) === 0): ?>
  <div class="kosong kosong-besar">
    <h3>Belum ada <?= $f['kategori'] ? strtolower(KATEGORI_PRODUK[$f['kategori']]) : 'produk' ?></h3>
    <p>Pilih kategorinya — Produk, Jasa, atau Kelas — lalu isi formulirnya. Simpan sebagai Draf dulu, tayangkan kalau sudah siap.</p>
    <a class="tbl tbl-utama" href="<?= tautan('produk-baru') ?><?= $f['kategori'] ? '?kategori=' . e($f['kategori']) : '' ?>">+ Tambah <?= $f['kategori'] ? strtolower(KATEGORI_PRODUK[$f['kategori']]) : 'produk' ?></a>
  </div>
<?php elseif (!$daftar): ?>
  <p class="kosong">Tidak ada produk yang cocok. <a href="<?= tautan('produk') ?>">Tampilkan semua</a></p>
<?php elseif ($tampil === 'grid'): ?>

  <div class="produk-grid">
    <?php foreach ($daftar as $p): ?>
      <?php $gambar = gambarKartuProduk($p); $tayang = $p['status'] === 'aktif'; ?>
      <article class="pg-kartu<?= $tayang ? '' : ' is-redup' ?>">
        <a class="pg-sampul" href="<?= tautan('produk/' . (int) $p['id']) ?>" tabindex="-1" aria-hidden="true">
          <?php if ($gambar): ?><img src="<?= e($gambar) ?>" alt="" loading="lazy"><?php else: ?><span><?= e(mb_strtoupper(mb_substr($p['nama'], 0, 1))) ?></span><?php endif; ?>
          <span class="pg-kategori pg-kategori-<?= e($p['kategori']) ?>"><?= e(KATEGORI_PRODUK[$p['kategori']] ?? '') ?></span>
        </a>
        <div class="pg-isi">
          <a class="pg-nama" href="<?= tautan('produk/' . (int) $p['id']) ?>"><?= e($p['nama']) ?></a>
          <p class="pg-harga"><?= e(teksHargaProduk($p) ?: '—') ?></p>
          <p class="pg-meta">
            <span class="tanda tanda-<?= $tayang ? 'tayang' : e($p['status']) ?>"><?= e(STATUS_PRODUK[$p['status']]) ?></span>
            <?php if (($p['lp_mode'] ?? '') === 'custom'): ?><span class="tanda tanda-lembut">LP custom</span><?php endif; ?>
            <span class="teks-kecil"><?= (int) $p['terjual'] ?> terjual</span>
          </p>
        </div>
        <div class="pg-aksi">
          <a class="tbl tbl-kecil" href="<?= tautan('produk/' . (int) $p['id']) ?>">Sunting</a>
          <?php if ($p['kategori'] === 'kelas' && $p['kelas_id']): ?>
            <a class="tbl tbl-kecil" href="<?= tautan('kelas/' . (int) $p['kelas_id']) ?>">Isi kelas</a>
          <?php endif; ?>
          <?php if ($p['status'] !== 'arsip'): ?>
            <form method="post" class="sebaris">
              <?= csrfInput() ?>
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <input type="hidden" name="kembali" value="<?= e($kembaliIni) ?>">
              <?php if ($tayang): ?>
                <button class="tbl tbl-kecil tbl-tipis" type="submit" name="aksi" value="turunkan"
                        data-pastikan="Turunkan <?= e($p['nama']) ?>? Landing page-nya tidak bisa dibuka dan tidak bisa dibeli sampai ditayangkan lagi.">Turunkan</button>
              <?php else: ?>
                <button class="tbl tbl-kecil tbl-utama" type="submit" name="aksi" value="tayang">Tayangkan</button>
              <?php endif; ?>
            </form>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

<?php else: ?>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr>
          <th>Produk</th>
          <th>Kategori</th>
          <th>Harga</th>
          <th class="kanan">Terjual</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($daftar as $p): ?>
          <?php $tayang = $p['status'] === 'aktif'; ?>
          <tr onclick="location='<?= tautan('produk/' . (int) $p['id']) ?>'">
            <td>
              <a class="tabel-utama" href="<?= tautan('produk/' . (int) $p['id']) ?>"><?= e($p['nama']) ?></a>
              <span class="tabel-sub">
                <?= e(JENIS_PRODUK[$p['jenis']]['label'] ?? $p['jenis']) ?>
                <?php if ($p['kategori'] === 'kelas'): ?> · <?= (int) $p['jml_materi'] ?> materi<?php endif; ?>
                <?php if (($p['lp_mode'] ?? '') === 'custom'): ?> · landing page custom<?php endif; ?>
              </span>
            </td>
            <td><span class="tanda tanda-kat-<?= e($p['kategori']) ?>"><?= e(KATEGORI_PRODUK[$p['kategori']] ?? $p['kategori']) ?></span></td>
            <td class="nowrap"><?= e(teksHargaProduk($p) ?: '—') ?></td>
            <td class="kanan">
              <?= (int) $p['terjual'] ?>
              <?php if ((int) $p['omzet'] > 0): ?><span class="tabel-sub"><?= e(rupiah((int) $p['omzet'])) ?></span><?php endif; ?>
            </td>
            <td><span class="tanda tanda-<?= $tayang ? 'tayang' : e($p['status']) ?>"><?= e(STATUS_PRODUK[$p['status']] ?? $p['status']) ?></span></td>
            <td class="kanan">
              <?php if ($p['status'] !== 'arsip'): ?>
                <form method="post" class="sebaris">
                  <?= csrfInput() ?>
                  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                  <input type="hidden" name="kembali" value="<?= e($kembaliIni) ?>">
                  <?php if ($tayang): ?>
                    <a class="tautan-lain" href="<?= e(urlSitus() . '/p/' . $p['slug']) ?>" target="_blank" rel="noopener">Lihat ↗</a>
                    <button class="tbl tbl-kecil tbl-tipis" type="submit" name="aksi" value="turunkan"
                            data-pastikan="Turunkan <?= e($p['nama']) ?>? Landing page-nya tidak bisa dibuka dan tidak bisa dibeli sampai ditayangkan lagi.">Turunkan</button>
                  <?php else: ?>
                    <button class="tbl tbl-kecil tbl-utama" type="submit" name="aksi" value="tayang">Tayangkan</button>
                  <?php endif; ?>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/inc/kaki.php'; ?>
