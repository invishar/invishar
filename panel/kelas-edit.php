<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
wajibMasuk();

$id = (int) ($_GET['id'] ?? $_POST['kelas_id'] ?? 0);
$kelas = ambilSatu('SELECT * FROM kelas WHERE id = ?', [$id]);

if ($kelas === null) {
    pesan('Kelas tidak ditemukan.', 'buruk');
    pergi('kelas.php');
}

/* Detail tambahan disimpan sebagai JSON supaya menambah bidang baru tidak
   perlu mengubah tabel. Di form ia dipecah jadi beberapa isian. */
$detail = json_decode((string) $kelas['detail'], true) ?: [];
$detail += [
    'kicker'   => 'Kelas',
    'bahasa'   => 'Bahasa Indonesia',
    'akses'    => 'Akses selamanya',
    'pengajar' => ['nama' => '', 'peran' => ''],
    'ikhtisar' => ['hasil' => [], 'untukSiapa' => [], 'syarat' => []],
    'sumber'   => [],
    'tanya'    => [],
];

/** Satu baris teks per butir. */
function keBaris(array $daftar): string
{
    return implode("\n", $daftar);
}

function dariBaris(string $teks): array
{
    $hasil = [];
    foreach (preg_split('/\R/', $teks) ?: [] as $baris) {
        $baris = trim($baris);
        if ($baris !== '') {
            $hasil[] = $baris;
        }
    }
    return $hasil;
}

/** Baris berbentuk "bagian | bagian | …" untuk sumber dan tanya jawab. */
function keBarisPisah(array $daftar, array $kunci): string
{
    $baris = [];
    foreach ($daftar as $butir) {
        $bagian = [];
        foreach ($kunci as $k) {
            $bagian[] = (string) ($butir[$k] ?? '');
        }
        $baris[] = implode(' | ', $bagian);
    }
    return implode("\n", $baris);
}

function dariBarisPisah(string $teks, array $kunci): array
{
    $hasil = [];
    foreach (dariBaris($teks) as $baris) {
        $bagian = array_map('trim', explode('|', $baris));
        $butir = [];
        foreach ($kunci as $i => $k) {
            $butir[$k] = $bagian[$i] ?? '';
        }
        $hasil[] = $butir;
    }
    return $hasil;
}

/* ------------------------------------------------------------- Penanganan */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $aksi = masukan('aksi');

    if ($aksi === 'hapus_kelas') {
        q('DELETE FROM kelas WHERE id = ?', [$id]);          // modul & materi ikut terhapus
        catatLog('hapus kelas', $kelas['judul']);
        pesan('Kelas "' . $kelas['judul'] . '" dihapus.');
        pergi('kelas.php');
    }

    if ($aksi === 'simpan_kelas') {
        $slug = slugkan(masukan('slug') !== '' ? masukan('slug') : masukan('judul'));
        $bentrok = ambilNilai('SELECT id FROM kelas WHERE slug = ? AND id <> ?', [$slug, $id]);
        if ($bentrok !== null) {
            pesan('Slug "' . $slug . '" sudah dipakai kelas lain.', 'buruk');
            pergi('kelas-edit.php?id=' . $id);
        }

        $detailBaru = [
            'kicker'   => masukan('kicker'),
            'bahasa'   => masukan('bahasa'),
            'akses'    => masukan('akses'),
            'pengajar' => ['nama' => masukan('pengajar_nama'), 'peran' => masukan('pengajar_peran')],
            'ikhtisar' => [
                'hasil'      => dariBaris(masukan('hasil')),
                'untukSiapa' => dariBaris(masukan('untuk')),
                'syarat'     => dariBaris(masukan('syarat')),
            ],
            'sumber' => dariBarisPisah(masukan('sumber'), ['judul', 'desc', 'aksi', 'url']),
            'tanya'  => dariBarisPisah(masukan('tanya'), ['q', 'a']),
        ];

        q(
            'UPDATE kelas SET slug = ?, judul = ?, kategori = ?, ringkas = ?, level = ?, harga = ?,
                    status = ?, ikon = ?, urutan = ?, detail = ?, diperbarui_pada = NOW() WHERE id = ?',
            [
                $slug, masukan('judul'), masukan('kategori'), masukan('ringkas'), masukan('level'),
                masukan('harga'), masukan('status'), masukan('ikon'), (int) ($_POST['urutan'] ?? 0),
                json_encode($detailBaru, JSON_UNESCAPED_UNICODE), $id,
            ]
        );
        catatLog('ubah kelas', masukan('judul'));
        pesan('Kelas disimpan. Tekan Terbitkan di halaman Kelas supaya situs ikut berubah.');
        pergi('kelas-edit.php?id=' . $id);
    }

    if ($aksi === 'simpan_modul') {
        $modulId    = (int) ($_POST['modul_id'] ?? 0);
        $judulModul = masukan('modul_judul');
        $urutan     = (int) ($_POST['modul_urutan'] ?? 0);

        if ($judulModul === '') {
            pesan('Judul modul wajib diisi.', 'buruk');
        } elseif ($modulId > 0) {
            q('UPDATE modul SET judul = ?, urutan = ? WHERE id = ? AND kelas_id = ?',
                [$judulModul, $urutan, $modulId, $id]);
            pesan('Modul diperbarui.');
        } else {
            $urutan = (int) ambilNilai('SELECT COALESCE(MAX(urutan), -1) + 1 FROM modul WHERE kelas_id = ?', [$id]);
            q('INSERT INTO modul (kelas_id, judul, urutan) VALUES (?, ?, ?)', [$id, $judulModul, $urutan]);
            pesan('Modul ditambahkan.');
        }
        q('UPDATE kelas SET diperbarui_pada = NOW() WHERE id = ?', [$id]);
        pergi('kelas-edit.php?id=' . $id);
    }

    if ($aksi === 'hapus_modul') {
        q('DELETE FROM modul WHERE id = ? AND kelas_id = ?', [(int) ($_POST['modul_id'] ?? 0), $id]);
        q('UPDATE kelas SET diperbarui_pada = NOW() WHERE id = ?', [$id]);
        catatLog('hapus modul', $kelas['judul']);
        pesan('Modul dihapus beserta materinya.');
        pergi('kelas-edit.php?id=' . $id);
    }

    if ($aksi === 'simpan_materi') {
        $materiId = (int) ($_POST['materi_id'] ?? 0);
        $modulId  = (int) ($_POST['modul_id'] ?? 0);
        $milik = ambilNilai('SELECT id FROM modul WHERE id = ? AND kelas_id = ?', [$modulId, $id]);

        if ($milik === null) {
            pesan('Modul tidak sah.', 'buruk');
        } elseif (masukan('materi_judul') === '') {
            pesan('Judul materi wajib diisi.', 'buruk');
        } else {
            // Terima URL penuh maupun ID video saja.
            $yt = masukan('youtube_id');
            if (preg_match('~(?:youtu\.be/|v=|embed/)([A-Za-z0-9_-]{6,})~', $yt, $cocok)) {
                $yt = $cocok[1];
            }

            $isi = [
                masukan('kode'), masukan('materi_judul'), masukan('durasi'), $yt,
                masukan('materi_ringkas'), masukan('poin'), (int) ($_POST['materi_urutan'] ?? 0),
            ];

            if ($materiId > 0) {
                q('UPDATE materi SET kode = ?, judul = ?, durasi = ?, youtube_id = ?, ringkas = ?, poin = ?, urutan = ?
                   WHERE id = ? AND modul_id = ?', [...$isi, $materiId, $modulId]);
                pesan('Materi diperbarui.');
            } else {
                if ($isi[0] === '') {
                    $isi[0] = 'm' . str_pad((string) ((int) ambilNilai(
                        'SELECT COUNT(*) FROM materi x JOIN modul m ON m.id = x.modul_id WHERE m.kelas_id = ?', [$id]
                    ) + 1), 2, '0', STR_PAD_LEFT);
                }
                $isi[6] = (int) ambilNilai('SELECT COALESCE(MAX(urutan), -1) + 1 FROM materi WHERE modul_id = ?', [$modulId]);
                q('INSERT INTO materi (kode, judul, durasi, youtube_id, ringkas, poin, urutan, modul_id)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [...$isi, $modulId]);
                pesan('Materi ditambahkan.');
            }
            q('UPDATE kelas SET diperbarui_pada = NOW() WHERE id = ?', [$id]);
        }
        pergi('kelas-edit.php?id=' . $id);
    }

    if ($aksi === 'hapus_materi') {
        q('DELETE x FROM materi x JOIN modul m ON m.id = x.modul_id WHERE x.id = ? AND m.kelas_id = ?',
            [(int) ($_POST['materi_id'] ?? 0), $id]);
        q('UPDATE kelas SET diperbarui_pada = NOW() WHERE id = ?', [$id]);
        pesan('Materi dihapus.');
        pergi('kelas-edit.php?id=' . $id);
    }

    pergi('kelas-edit.php?id=' . $id);
}

/* ---------------------------------------------------------------- Tampilan */
$modulList = ambilSemua('SELECT * FROM modul WHERE kelas_id = ? ORDER BY urutan, id', [$id]);
$materiPer = [];
foreach ($modulList as $m) {
    $materiPer[$m['id']] = ambilSemua('SELECT * FROM materi WHERE modul_id = ? ORDER BY urutan, id', [$m['id']]);
}

$suntingModul  = null;
$suntingMateri = null;
if (isset($_GET['modul'])) {
    $suntingModul = ambilSatu('SELECT * FROM modul WHERE id = ? AND kelas_id = ?', [(int) $_GET['modul'], $id]);
}
if (isset($_GET['materi'])) {
    $suntingMateri = ambilSatu(
        'SELECT x.* FROM materi x JOIN modul m ON m.id = x.modul_id WHERE x.id = ? AND m.kelas_id = ?',
        [(int) $_GET['materi'], $id]
    );
}
$modulTerpilih = (int) ($_GET['modul_untuk'] ?? $suntingMateri['modul_id'] ?? ($modulList[0]['id'] ?? 0));

$judul = $kelas['judul'];
$menu  = 'kelas';
require __DIR__ . '/inc/kepala.php';
?>

<p class="remah">
  <a href="kelas.php">&larr; Semua kelas</a>
  <a class="tautan-lain" href="https://invishar.com/course.html?k=<?= e($kelas['slug']) ?>" target="_blank" rel="noopener">Lihat di situs &nearr;</a>
</p>

<!-- ============ Keterangan kelas ============ -->
<section class="kotak kotak-form">
  <div class="kotak-kepala"><h2>Keterangan kelas</h2></div>

  <form method="post" class="form-panel">
    <?= csrfInput() ?>
    <input type="hidden" name="kelas_id" value="<?= (int) $id ?>">
    <input type="hidden" name="aksi" value="simpan_kelas">

    <div class="baris-form">
      <div class="bidang">
        <label for="f-judul">Judul</label>
        <input id="f-judul" name="judul" type="text" value="<?= e($kelas['judul']) ?>" required>
      </div>
      <div class="bidang">
        <label for="f-slug">Slug</label>
        <input id="f-slug" name="slug" type="text" value="<?= e($kelas['slug']) ?>">
      </div>
      <div class="bidang bidang-kecil">
        <label for="f-urutan">Urutan</label>
        <input id="f-urutan" name="urutan" type="number" value="<?= (int) $kelas['urutan'] ?>">
      </div>
    </div>

    <div class="bidang">
      <label for="f-ringkas">Ringkasan</label>
      <textarea id="f-ringkas" name="ringkas" rows="2"><?= e($kelas['ringkas']) ?></textarea>
    </div>

    <div class="baris-form">
      <div class="bidang">
        <label for="f-kategori">Kategori</label>
        <input id="f-kategori" name="kategori" type="text" value="<?= e($kelas['kategori']) ?>" list="daftar-kategori">
        <datalist id="daftar-kategori">
          <?php foreach (ambilSemua('SELECT DISTINCT kategori FROM kelas ORDER BY kategori') as $k): ?>
            <option value="<?= e($k['kategori']) ?>"></option>
          <?php endforeach; ?>
        </datalist>
      </div>
      <div class="bidang">
        <label for="f-level">Level</label>
        <input id="f-level" name="level" type="text" value="<?= e($kelas['level']) ?>">
      </div>
      <div class="bidang">
        <label for="f-harga">Harga</label>
        <input id="f-harga" name="harga" type="text" value="<?= e($kelas['harga']) ?>">
      </div>
    </div>

    <div class="baris-form">
      <div class="bidang">
        <label for="f-status">Status</label>
        <select id="f-status" name="status">
          <?php foreach (['Dibuka', 'Baru', 'Segera'] as $s): ?>
            <option value="<?= e($s) ?>"<?= $kelas['status'] === $s ? ' selected' : '' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="bidang">
        <label for="f-ikon">Ikon sampul</label>
        <select id="f-ikon" name="ikon">
          <?php foreach (['grafik', 'pesan', 'tata', 'kilau', 'perisai', 'video'] as $i): ?>
            <option value="<?= e($i) ?>"<?= $kelas['ikon'] === $i ? ' selected' : '' ?>><?= e($i) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="bidang">
        <label for="f-kicker">Label kecil</label>
        <input id="f-kicker" name="kicker" type="text" value="<?= e($detail['kicker']) ?>">
      </div>
    </div>

    <div class="baris-form">
      <div class="bidang">
        <label for="f-bahasa">Bahasa</label>
        <input id="f-bahasa" name="bahasa" type="text" value="<?= e($detail['bahasa']) ?>">
      </div>
      <div class="bidang">
        <label for="f-akses">Akses</label>
        <input id="f-akses" name="akses" type="text" value="<?= e($detail['akses']) ?>">
      </div>
      <div class="bidang">
        <label for="f-pengajar">Pengajar</label>
        <input id="f-pengajar" name="pengajar_nama" type="text" value="<?= e($detail['pengajar']['nama'] ?? '') ?>">
      </div>
      <div class="bidang">
        <label for="f-peran">Peran pengajar</label>
        <input id="f-peran" name="pengajar_peran" type="text" value="<?= e($detail['pengajar']['peran'] ?? '') ?>">
      </div>
    </div>

    <div class="baris-form">
      <div class="bidang">
        <label for="f-hasil">Yang akan dikuasai</label>
        <textarea id="f-hasil" name="hasil" rows="5"><?= e(keBaris($detail['ikhtisar']['hasil'] ?? [])) ?></textarea>
        <p class="petunjuk">Satu butir per baris.</p>
      </div>
      <div class="bidang">
        <label for="f-untuk">Cocok untuk</label>
        <textarea id="f-untuk" name="untuk" rows="5"><?= e(keBaris($detail['ikhtisar']['untukSiapa'] ?? [])) ?></textarea>
      </div>
      <div class="bidang">
        <label for="f-syarat">Perlu disiapkan</label>
        <textarea id="f-syarat" name="syarat" rows="5"><?= e(keBaris($detail['ikhtisar']['syarat'] ?? [])) ?></textarea>
      </div>
    </div>

    <div class="bidang">
      <label for="f-sumber">Sumber belajar</label>
      <textarea id="f-sumber" name="sumber" rows="4"><?= e(keBarisPisah($detail['sumber'] ?? [], ['judul', 'desc', 'aksi', 'url'])) ?></textarea>
      <p class="petunjuk">Satu baris per berkas: <code>judul | keterangan | teks tombol | alamat</code></p>
    </div>

    <div class="bidang">
      <label for="f-tanya">Tanya jawab</label>
      <textarea id="f-tanya" name="tanya" rows="4"><?= e(keBarisPisah($detail['tanya'] ?? [], ['q', 'a'])) ?></textarea>
      <p class="petunjuk">Satu baris per tanya jawab: <code>pertanyaan | jawaban</code></p>
    </div>

    <div class="form-aksi">
      <button class="tbl tbl-utama" type="submit">Simpan kelas</button>
      <button class="tbl tbl-bahaya" type="submit" name="aksi" value="hapus_kelas"
              data-pastikan="Hapus kelas <?= e($kelas['judul']) ?> beserta seluruh modul dan materinya?">Hapus kelas</button>
    </div>
  </form>
</section>

<!-- ============ Modul & materi ============ -->
<section class="kotak">
  <div class="kotak-kepala"><h2>Modul &amp; materi</h2></div>

  <?php if (!$modulList): ?>
    <p class="kosong">Belum ada modul. Tambahkan modul pertama di bawah.</p>
  <?php endif; ?>

  <?php foreach ($modulList as $m): ?>
    <div class="modul-blok">
      <div class="modul-kepala">
        <h3><?= e($m['judul']) ?></h3>
        <span class="petunjuk"><?= count($materiPer[$m['id']]) ?> materi</span>
        <a class="tautan-lain" href="kelas-edit.php?id=<?= $id ?>&amp;modul=<?= (int) $m['id'] ?>">Ubah</a>
        <a class="tautan-lain" href="kelas-edit.php?id=<?= $id ?>&amp;modul_untuk=<?= (int) $m['id'] ?>#materi">Tambah materi</a>
      </div>

      <?php if ($materiPer[$m['id']]): ?>
        <ol class="materi-daftar">
          <?php foreach ($materiPer[$m['id']] as $x): ?>
            <li>
              <a href="kelas-edit.php?id=<?= $id ?>&amp;materi=<?= (int) $x['id'] ?>#materi">
                <span class="md-kode"><?= e($x['kode']) ?></span>
                <span class="md-judul"><?= e($x['judul']) ?></span>
                <span class="md-durasi"><?= e($x['durasi']) ?></span>
                <span class="md-yt<?= $x['youtube_id'] === '' ? ' md-kosong' : '' ?>">
                  <?= $x['youtube_id'] === '' ? 'video kosong' : e($x['youtube_id']) ?>
                </span>
              </a>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>

<div class="dua-kolom">

  <!-- modul -->
  <section class="kotak kotak-form">
    <div class="kotak-kepala">
      <h2><?= $suntingModul ? 'Ubah modul' : 'Modul baru' ?></h2>
      <?php if ($suntingModul): ?><a class="tautan-lain" href="kelas-edit.php?id=<?= $id ?>">Batal</a><?php endif; ?>
    </div>

    <form method="post" class="form-panel">
      <?= csrfInput() ?>
      <input type="hidden" name="kelas_id" value="<?= (int) $id ?>">
      <input type="hidden" name="aksi" value="simpan_modul">
      <input type="hidden" name="modul_id" value="<?= (int) ($suntingModul['id'] ?? 0) ?>">

      <div class="baris-form">
        <div class="bidang">
          <label for="modul_judul">Judul modul</label>
          <input id="modul_judul" name="modul_judul" type="text" value="<?= e($suntingModul['judul'] ?? '') ?>" required>
        </div>
        <?php if ($suntingModul): ?>
          <div class="bidang bidang-kecil">
            <label for="modul_urutan">Urutan</label>
            <input id="modul_urutan" name="modul_urutan" type="number" value="<?= (int) $suntingModul['urutan'] ?>">
          </div>
        <?php endif; ?>
      </div>

      <div class="form-aksi">
        <button class="tbl tbl-utama" type="submit"><?= $suntingModul ? 'Simpan modul' : 'Tambah modul' ?></button>
        <?php if ($suntingModul): ?>
          <button class="tbl tbl-bahaya" type="submit" name="aksi" value="hapus_modul"
                  data-pastikan="Hapus modul ini beserta seluruh materinya?">Hapus modul</button>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <!-- materi -->
  <section class="kotak kotak-form" id="materi">
    <div class="kotak-kepala">
      <h2><?= $suntingMateri ? 'Ubah materi' : 'Materi baru' ?></h2>
      <?php if ($suntingMateri): ?><a class="tautan-lain" href="kelas-edit.php?id=<?= $id ?>">Batal</a><?php endif; ?>
    </div>

    <?php if (!$modulList): ?>
      <p class="kosong">Buat modul dulu sebelum menambah materi.</p>
    <?php else: ?>
      <form method="post" class="form-panel">
        <?= csrfInput() ?>
        <input type="hidden" name="kelas_id" value="<?= (int) $id ?>">
        <input type="hidden" name="aksi" value="simpan_materi">
        <input type="hidden" name="materi_id" value="<?= (int) ($suntingMateri['id'] ?? 0) ?>">

        <div class="baris-form">
          <div class="bidang">
            <label for="modul_id">Modul</label>
            <select id="modul_id" name="modul_id">
              <?php foreach ($modulList as $m): ?>
                <option value="<?= (int) $m['id'] ?>"<?= $modulTerpilih === (int) $m['id'] ? ' selected' : '' ?>><?= e($m['judul']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="bidang bidang-kecil">
            <label for="kode">Kode</label>
            <input id="kode" name="kode" type="text" value="<?= e($suntingMateri['kode'] ?? '') ?>" placeholder="m01">
          </div>
          <?php if ($suntingMateri): ?>
            <div class="bidang bidang-kecil">
              <label for="materi_urutan">Urutan</label>
              <input id="materi_urutan" name="materi_urutan" type="number" value="<?= (int) $suntingMateri['urutan'] ?>">
            </div>
          <?php endif; ?>
        </div>

        <div class="bidang">
          <label for="materi_judul">Judul materi</label>
          <input id="materi_judul" name="materi_judul" type="text" value="<?= e($suntingMateri['judul'] ?? '') ?>" required>
        </div>

        <div class="baris-form">
          <div class="bidang bidang-kecil">
            <label for="durasi">Durasi</label>
            <input id="durasi" name="durasi" type="text" value="<?= e($suntingMateri['durasi'] ?? '10 mnt') ?>">
          </div>
          <div class="bidang">
            <label for="youtube_id">Video YouTube</label>
            <input id="youtube_id" name="youtube_id" type="text" value="<?= e($suntingMateri['youtube_id'] ?? '') ?>" placeholder="ID atau tempel URL-nya">
          </div>
        </div>

        <div class="bidang">
          <label for="materi_ringkas">Ringkasan</label>
          <textarea id="materi_ringkas" name="materi_ringkas" rows="3"><?= e($suntingMateri['ringkas'] ?? '') ?></textarea>
        </div>

        <div class="bidang">
          <label for="poin">Poin penting</label>
          <textarea id="poin" name="poin" rows="3"><?= e($suntingMateri['poin'] ?? '') ?></textarea>
          <p class="petunjuk">Satu poin per baris.</p>
        </div>

        <div class="form-aksi">
          <button class="tbl tbl-utama" type="submit"><?= $suntingMateri ? 'Simpan materi' : 'Tambah materi' ?></button>
          <?php if ($suntingMateri): ?>
            <button class="tbl tbl-bahaya" type="submit" name="aksi" value="hapus_materi"
                    data-pastikan="Hapus materi <?= e($suntingMateri['judul']) ?>?">Hapus materi</button>
          <?php endif; ?>
        </div>
      </form>
    <?php endif; ?>
  </section>

</div>

<?php require __DIR__ . '/inc/kaki.php'; ?>
