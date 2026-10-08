<?php
declare(strict_types=1);

/* =============================================================================
   Materi kelas untuk pembeli: /toko/materi.php?t={token}&k={kelas_id}&m={materi_id}

   Isi diambil langsung dari basis data (bukan JSON publik), dan setiap
   permintaan memvalidasi token + kepesertaan. Tanpa token yang sah, tidak ada
   isi yang keluar — inilah yang menggembok materi kelas berbayar.
   ============================================================================= */

require __DIR__ . '/inc/awal.php';

header('Cache-Control: no-store, max-age=0');

$token   = (string) ($_GET['t'] ?? '');
$kelasId = (int) ($_GET['k'] ?? 0);
$materiId = (int) ($_GET['m'] ?? 0);

if (!bolehBukaKelas($token, $kelasId)) {
    halamanBuntu(403, 'Materi terkunci', 'Materi ini hanya untuk peserta kelas. Buka lewat halaman akses pembelian Anda, atau beli kelasnya dulu.', '/kelas.html', 'Lihat kelas');
}

$materi = ambilSatu(
    'SELECT m.*, md.judul AS modul_judul, md.urutan AS modul_urutan
       FROM materi m JOIN modul md ON md.id = m.modul_id
      WHERE m.id = ? AND md.kelas_id = ?',
    [$materiId, $kelasId]
);
if (!$materi) {
    halamanBuntu(404, 'Materi tidak ditemukan', 'Mungkin sudah dipindah atau dihapus.', '/toko/akses.php?t=' . rawurlencode($token), 'Kembali ke akses saya');
}
$kelas = ambilSatu('SELECT * FROM kelas WHERE id = ?', [$kelasId]);

/* Urutan materi sekelas untuk tombol sebelum/berikutnya. */
$semua = ambilSemua(
    'SELECT m.id FROM materi m JOIN modul md ON md.id = m.modul_id
      WHERE md.kelas_id = ? ORDER BY md.urutan, md.id, m.urutan, m.id',
    [$kelasId]
);
$ids = array_column($semua, 'id');
$pos = array_search($materiId, $ids, true);
$sebelum = $pos > 0 ? $ids[$pos - 1] : null;
$sesudah = $pos !== false && $pos < count($ids) - 1 ? $ids[$pos + 1] : null;

$poin = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $materi['poin']) ?: [])));

$judulHalaman = $materi['judul'] . ' · ' . ($kelas['judul'] ?? 'Kelas');
require __DIR__ . '/inc/kepala.php';
?>

<section class="toko-wrap">
  <p class="ringkasan-label">
    <a class="ringkasan-kembali" style="margin:0" href="/toko/akses.php?t=<?= e($token) ?>">&larr; Semua materi</a>
  </p>
  <p class="toko-sub" style="margin-bottom:6px"><?= e($kelas['judul'] ?? '') ?> · Modul <?= e($materi['modul_judul']) ?></p>
  <h1><?= e($materi['judul']) ?></h1>
  <?php if ($materi['durasi']): ?><p class="toko-sub"><?= e($materi['durasi']) ?></p><?php endif; ?>

  <?php if ($materi['youtube_id']): ?>
    <div style="position:relative;padding-top:56.25%;margin:18px 0;border-radius:12px;overflow:hidden;background:#000">
      <iframe src="https://www.youtube-nocookie.com/embed/<?= e($materi['youtube_id']) ?>?rel=0&amp;modestbranding=1"
              title="<?= e($materi['judul']) ?>" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
              allowfullscreen referrerpolicy="strict-origin-when-cross-origin"
              style="position:absolute;inset:0;width:100%;height:100%;border:0"></iframe>
    </div>
  <?php endif; ?>

  <?php if (trim((string) $materi['ringkas']) !== ''): ?>
    <div class="kartu-bayar" style="text-align:left">
      <p class="ringkasan-label">Tentang materi ini</p>
      <p style="white-space:pre-line;margin:0"><?= e($materi['ringkas']) ?></p>
    </div>
  <?php endif; ?>

  <?php if ($poin): ?>
    <div class="kartu-bayar" style="text-align:left;margin-top:14px">
      <p class="ringkasan-label">Poin penting</p>
      <ul style="margin:0;padding-left:20px;display:grid;gap:8px">
        <?php foreach ($poin as $p): ?><li><?= e($p) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div style="display:flex;gap:10px;margin-top:22px">
    <?php if ($sebelum): ?>
      <a class="btn btn-outline" style="flex:1" href="/toko/materi.php?t=<?= e($token) ?>&amp;k=<?= $kelasId ?>&amp;m=<?= $sebelum ?>">&larr; Sebelumnya</a>
    <?php endif; ?>
    <?php if ($sesudah): ?>
      <a class="btn btn-solid" style="flex:1" href="/toko/materi.php?t=<?= e($token) ?>&amp;k=<?= $kelasId ?>&amp;m=<?= $sesudah ?>">Berikutnya &rarr;</a>
    <?php else: ?>
      <a class="btn btn-solid" style="flex:1" href="/toko/akses.php?t=<?= e($token) ?>">Selesai &#10003;</a>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/inc/kaki.php'; ?>
