<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
wajibMasuk();

$id = (int) ($_GET['id'] ?? $_POST['kelas_id'] ?? 0);
$kelas = ambilSatu('SELECT * FROM kelas WHERE id = ?', [$id]);

if ($kelas === null) {
    pesan('Kelas tidak ditemukan.', 'buruk');
    pergi(tautan('kelas'));
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

const IKON = [
    'grafik'  => ['M3 3v18h18', 'M7 14l3-4 3 3 5-7'],
    'pesan'   => ['M21 11.5a7.5 7.5 0 0 1-7.5 7.5H8l-4 3v-4.9A7.5 7.5 0 0 1 8.5 4h5A7.5 7.5 0 0 1 21 11.5z', 'M9 11h6'],
    'tata'    => ['M4 4h6v6H4z', 'M14 4h6v3h-6z', 'M14 11h6v9h-6z', 'M4 14h6v6H4z'],
    'kilau'   => ['M12 3l2.2 5.3L20 10.5l-5.8 2.2L12 18l-2.2-5.3L4 10.5l5.8-2.2z', 'M18.5 16.5l.9 2.1 2.1.9-2.1.9-.9 2.1-.9-2.1-2.1-.9 2.1-.9z'],
    'perisai' => ['M12 3l7.5 3v6c0 4.2-3.2 7.6-7.5 8.7C7.7 19.6 4.5 16.2 4.5 12V6z', 'M9 12l2.2 2.2L15.5 10'],
    'video'   => ['M3.5 6.5h11v11h-11z', 'M14.5 10.5l6-3.5v10l-6-3.5z'],
];

function ikonSvg(string $nama): string
{
    $jalur = IKON[$nama] ?? IKON['kilau'];
    $isi = '';
    foreach ($jalur as $d) {
        $isi .= '<path d="' . e($d) . '" stroke="currentColor" stroke-width="1.6" '
              . 'stroke-linecap="round" stroke-linejoin="round"/>';
    }
    return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true">' . $isi . '</svg>';
}

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

/** Menggabungkan beberapa larik POST sejajar menjadi satu daftar baris. */
function dariKolom(array $kunci): array
{
    $kolom = [];
    $jumlah = 0;
    foreach ($kunci as $nama => $medan) {
        $kolom[$nama] = array_map('trim', (array) ($_POST[$medan] ?? []));
        $jumlah = max($jumlah, count($kolom[$nama]));
    }

    $utama = array_key_first($kunci);
    $hasil = [];
    for ($i = 0; $i < $jumlah; $i++) {
        if (($kolom[$utama][$i] ?? '') === '') {
            continue;   // baris tanpa isi utama dianggap kosong
        }
        $baris = [];
        foreach ($kunci as $nama => $medan) {
            $baris[$nama] = $kolom[$nama][$i] ?? '';
        }
        $hasil[] = $baris;
    }
    return $hasil;
}

function folderGambar(): string
{
    return rtrim((string) konfig('situs_data'), '/') . '/kelas';
}

/** Alamat gambar sampul seperti yang dilihat pengunjung situs. */
function urlGambar(?string $berkas): string
{
    return $berkas ? 'https://invishar.com/data/kelas/' . rawurlencode($berkas) : '';
}

/**
 * Menyimpan gambar sampul yang diunggah, mengembalikan nama berkasnya.
 *
 * Jenis berkas ditentukan dari isinya lewat getimagesize, bukan dari nama
 * yang dikirim peramban — nama berkas sepenuhnya dikuasai pengunggah.
 */
function simpanGambar(array $berkas, string $slug): ?string
{
    $kode = $berkas['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($kode === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($kode !== UPLOAD_ERR_OK) {
        pesan('Unggahan gagal. Periksa ukuran berkasnya.', 'buruk');
        return null;
    }
    if (($berkas['size'] ?? 0) > 3 * 1024 * 1024) {
        pesan('Gambar lebih dari 3 MB. Perkecil dulu.', 'buruk');
        return null;
    }

    $ukuran = @getimagesize($berkas['tmp_name']);
    $jenis  = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];

    if (!$ukuran || !isset($jenis[$ukuran[2]])) {
        pesan('Berkas itu bukan gambar JPG, PNG, atau WebP.', 'buruk');
        return null;
    }

    $folder = folderGambar();
    if (!is_dir($folder) && !@mkdir($folder, 0755, true) && !is_dir($folder)) {
        pesan('Folder gambar tidak bisa dibuat.', 'buruk');
        return null;
    }

    // Folder gambar tidak boleh menjalankan skrip, apa pun yang berhasil masuk.
    $jaga = $folder . '/.htaccess';
    if (!is_file($jaga)) {
        file_put_contents($jaga, "<FilesMatch \"\\.(php|phtml|phar|cgi|pl|py)$\">\n  Require all denied\n</FilesMatch>\n");
    }

    $nama = $slug . '-' . bin2hex(random_bytes(3)) . '.' . $jenis[$ukuran[2]];
    if (!move_uploaded_file($berkas['tmp_name'], $folder . '/' . $nama)) {
        pesan('Gambar tidak bisa disimpan ke folder tujuan.', 'buruk');
        return null;
    }
    @chmod($folder . '/' . $nama, 0644);

    return $nama;
}

function buangGambar(?string $berkas): void
{
    if ($berkas) {
        @unlink(folderGambar() . '/' . $berkas);
    }
}

function jumlahMateriKelas(int $id): int
{
    return (int) ambilNilai(
        'SELECT COUNT(*) FROM materi x JOIN modul m ON m.id = x.modul_id WHERE m.kelas_id = ?',
        [$id]
    );
}

/* ------------------------------------------------------------- Penanganan */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $aksi = masukan('aksi');

    if ($aksi === 'hapus_kelas') {
        // Kelas yang sudah berisi materi menuntut judulnya diketik ulang.
        // Menghapusnya membuang seluruh modul dan materi sekaligus.
        if (jumlahMateriKelas($id) > 0 && masukan('konfirmasi') !== $kelas['judul']) {
            pesan('Judul kelas tidak cocok — penghapusan dibatalkan.', 'buruk');
            pergi(tautan('kelas/' . $id));
        }
        buangGambar($kelas['gambar']);
        q('DELETE FROM kelas WHERE id = ?', [$id]);
        catatLog('hapus kelas', $kelas['judul']);
        pesan('Kelas "' . $kelas['judul'] . '" dihapus.');
        pergi(tautan('kelas'));
    }

    if ($aksi === 'hapus_gambar') {
        buangGambar($kelas['gambar']);
        q('UPDATE kelas SET gambar = NULL, diperbarui_pada = NOW() WHERE id = ?', [$id]);
        catatLog('hapus gambar kelas', $kelas['judul']);
        pesan('Gambar sampul dihapus. Kartu kembali memakai ikon.');
        pergi(tautan('kelas/' . $id));
    }

    if ($aksi === 'simpan_kelas') {
        // Slug hanya berubah kalau kuncinya dibuka secara sadar di antarmuka.
        // Mengubahnya memutus tautan lama dan menghapus catatan progres
        // pengunjung, karena kunci penyimpanannya mengandung slug.
        $slug = $kelas['slug'];
        if (masukan('slug_ubah') === '1') {
            $slug = slugkan(masukan('slug') !== '' ? masukan('slug') : masukan('judul'));
            if (ambilNilai('SELECT id FROM kelas WHERE slug = ? AND id <> ?', [$slug, $id]) !== null) {
                pesan('Slug "' . $slug . '" sudah dipakai kelas lain.', 'buruk');
                pergi(tautan('kelas/' . $id));
            }
            if ($slug !== $kelas['slug']) {
                catatLog('ubah slug kelas', $kelas['slug'] . ' → ' . $slug);
            }
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
            'sumber' => dariKolom([
                'judul' => 'sumber_judul', 'desc' => 'sumber_desc',
                'aksi'  => 'sumber_aksi',  'url'  => 'sumber_url',
            ]),
            'tanya' => dariKolom(['q' => 'tanya_q', 'a' => 'tanya_a']),
        ];

        $gambar = $kelas['gambar'];
        $gambarBaru = simpanGambar($_FILES['gambar'] ?? [], $slug);
        if ($gambarBaru !== null) {
            buangGambar($gambar);
            $gambar = $gambarBaru;
        }

        q(
            'UPDATE kelas SET slug = ?, judul = ?, kategori = ?, ringkas = ?, level = ?, harga = ?,
                    status = ?, ikon = ?, gambar = ?, urutan = ?, detail = ?, diperbarui_pada = NOW() WHERE id = ?',
            [
                $slug, masukan('judul'), masukan('kategori'), masukan('ringkas'), masukan('level'),
                masukan('harga'), masukan('status'), masukan('ikon'), $gambar, (int) ($_POST['urutan'] ?? 0),
                json_encode($detailBaru, JSON_UNESCAPED_UNICODE), $id,
            ]
        );
        catatLog('ubah kelas', masukan('judul'));
        pesan('Kelas "' . masukan('judul') . '" tersimpan'
            . ($gambarBaru !== null ? ' berikut gambar sampulnya' : '')
            . '. Tekan Terbitkan supaya situs ikut berubah.');
        pergi(tautan('kelas/' . $id));
    }

    if ($aksi === 'simpan_modul') {
        $modulId    = (int) ($_POST['modul_id'] ?? 0);
        $judulModul = masukan('modul_judul');

        if ($judulModul === '') {
            pesan('Judul modul wajib diisi.', 'buruk');
        } elseif ($modulId > 0) {
            q('UPDATE modul SET judul = ?, urutan = ? WHERE id = ? AND kelas_id = ?',
                [$judulModul, (int) ($_POST['modul_urutan'] ?? 0), $modulId, $id]);
            pesan('Modul diperbarui.');
        } else {
            $urutan = (int) ambilNilai('SELECT COALESCE(MAX(urutan), -1) + 1 FROM modul WHERE kelas_id = ?', [$id]);
            q('INSERT INTO modul (kelas_id, judul, urutan) VALUES (?, ?, ?)', [$id, $judulModul, $urutan]);
            pesan('Modul ditambahkan.');
        }
        q('UPDATE kelas SET diperbarui_pada = NOW() WHERE id = ?', [$id]);
        pergi(tautan('kelas/' . $id));
    }

    if ($aksi === 'hapus_modul') {
        q('DELETE FROM modul WHERE id = ? AND kelas_id = ?', [(int) ($_POST['modul_id'] ?? 0), $id]);
        q('UPDATE kelas SET diperbarui_pada = NOW() WHERE id = ?', [$id]);
        catatLog('hapus modul', $kelas['judul']);
        pesan('Modul dihapus beserta materinya.');
        pergi(tautan('kelas/' . $id));
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
                masukan('kode'), masukan('materi_judul'), masukan('durasi') ?: '10 mnt', $yt,
                masukan('materi_ringkas'), masukan('poin'), (int) ($_POST['materi_urutan'] ?? 0),
            ];

            if ($materiId > 0) {
                q('UPDATE materi SET kode = ?, judul = ?, durasi = ?, youtube_id = ?, ringkas = ?, poin = ?, urutan = ?
                   WHERE id = ? AND modul_id = ?', [...$isi, $materiId, $modulId]);
                pesan('Materi diperbarui.');
            } else {
                if ($isi[0] === '') {
                    $isi[0] = 'm' . str_pad((string) (jumlahMateriKelas($id) + 1), 2, '0', STR_PAD_LEFT);
                }
                $isi[6] = (int) ambilNilai('SELECT COALESCE(MAX(urutan), -1) + 1 FROM materi WHERE modul_id = ?', [$modulId]);
                q('INSERT INTO materi (kode, judul, durasi, youtube_id, ringkas, poin, urutan, modul_id)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [...$isi, $modulId]);
                pesan('Materi ditambahkan.');
            }
            q('UPDATE kelas SET diperbarui_pada = NOW() WHERE id = ?', [$id]);
        }
        pergi(tautan('kelas/' . $id));
    }

    if ($aksi === 'hapus_materi') {
        q('DELETE x FROM materi x JOIN modul m ON m.id = x.modul_id WHERE x.id = ? AND m.kelas_id = ?',
            [(int) ($_POST['materi_id'] ?? 0), $id]);
        q('UPDATE kelas SET diperbarui_pada = NOW() WHERE id = ?', [$id]);
        pesan('Materi dihapus.');
        pergi(tautan('kelas/' . $id));
    }

    /* Menerima kerangka usulan AI: modul beserta materinya sekaligus.
       Hanya ditambahkan, tidak pernah menimpa yang sudah ada. */
    if ($aksi === 'terapkan_kerangka') {
        $kerangka = json_decode(masukan('kerangka', ''), true);
        $jmlModul = 0;
        $jmlMateri = 0;

        foreach ((array) ($kerangka['modul'] ?? []) as $m) {
            if (empty($m['judul'])) {
                continue;
            }
            $urutan = (int) ambilNilai('SELECT COALESCE(MAX(urutan), -1) + 1 FROM modul WHERE kelas_id = ?', [$id]);
            q('INSERT INTO modul (kelas_id, judul, urutan) VALUES (?, ?, ?)',
                [$id, mb_substr((string) $m['judul'], 0, 160), $urutan]);
            $modulId = (int) db()->lastInsertId();
            $jmlModul++;

            foreach ((array) ($m['materi'] ?? []) as $i => $x) {
                if (empty($x['judul'])) {
                    continue;
                }
                $kode = 'm' . str_pad((string) (jumlahMateriKelas($id) + 1), 2, '0', STR_PAD_LEFT);
                q('INSERT INTO materi (modul_id, kode, judul, durasi, youtube_id, ringkas, poin, urutan)
                   VALUES (?, ?, ?, ?, \'\', ?, \'\', ?)',
                    [
                        $modulId, $kode, mb_substr((string) $x['judul'], 0, 160),
                        mb_substr((string) ($x['durasi'] ?? '12 mnt'), 0, 20),
                        mb_substr((string) ($x['ringkas'] ?? ''), 0, 1000), $i,
                    ]);
                $jmlMateri++;
            }
        }

        q('UPDATE kelas SET diperbarui_pada = NOW() WHERE id = ?', [$id]);
        catatLog('terapkan kerangka AI', $kelas['judul']);
        pesan($jmlModul . ' modul dan ' . $jmlMateri . ' materi ditambahkan. ID video masih kosong — isi satu per satu.');
        pergi(tautan('kelas/' . $id));
    }

    pergi(tautan('kelas/' . $id));
}

/* ---------------------------------------------------------------- Tampilan */
$modulList = ambilSemua('SELECT * FROM modul WHERE kelas_id = ? ORDER BY urutan, id', [$id]);
$materiPer = [];
foreach ($modulList as $m) {
    $materiPer[$m['id']] = ambilSemua('SELECT * FROM materi WHERE modul_id = ? ORDER BY urutan, id', [$m['id']]);
}

$jmlMateri = jumlahMateriKelas($id);
$aiHidup   = !empty((konfig('ai') ?: [])['kunci']);

$berkasTerbit = rtrim((string) konfig('situs_data'), '/') . '/course-' . $kelas['slug'] . '.json';
$terbitPada   = is_file($berkasTerbit) ? (int) filemtime($berkasTerbit) : 0;
$perluTerbit  = strtotime($kelas['diperbarui_pada']) > $terbitPada;

$judul = 'Kelas · ' . $kelas['judul'];
$menu  = 'kelas';
require __DIR__ . '/inc/kepala.php';
?>

<p class="remah">
  <a href="<?= tautan('kelas') ?>">&larr; Semua kelas</a>
  <a class="tautan-lain" href="https://invishar.com/course.html?k=<?= e($kelas['slug']) ?>" target="_blank" rel="noopener">Lihat di situs &nearr;</a>
</p>

<form method="post" class="form-panel" id="form-kelas" data-jaga enctype="multipart/form-data">
  <?= csrfInput() ?>
  <input type="hidden" name="kelas_id" value="<?= (int) $id ?>">
  <input type="hidden" name="aksi" value="simpan_kelas">
  <input type="hidden" name="slug_ubah" id="slug-ubah" value="0">

  <!-- ============ 1. Identitas ============ -->
  <section class="kotak">
    <div class="kotak-kepala"><h2>Identitas kelas</h2></div>

    <div class="bidang">
      <label for="f-judul">Judul</label>
      <input id="f-judul" name="judul" type="text" class="isian-besar" value="<?= e($kelas['judul']) ?>" required>
    </div>

    <div class="baris-form">
      <div class="bidang">
        <label for="f-slug">Alamat kelas (slug)</label>
        <div class="slug-baris">
          <span class="slug-awalan">course.html?k=</span>
          <input id="f-slug" name="slug" type="text" value="<?= e($kelas['slug']) ?>" readonly>
          <button class="tbl tbl-kecil" type="button" id="slug-buka">Ubah</button>
        </div>
        <p class="petunjuk" id="slug-catatan">Terkunci. Mengubahnya memutus tautan yang sudah beredar.</p>
      </div>
      <div class="bidang">
        <label for="f-status">Status</label>
        <select id="f-status" name="status">
          <?php foreach (['Dibuka', 'Baru', 'Segera'] as $s): ?>
            <option value="<?= e($s) ?>"<?= $kelas['status'] === $s ? ' selected' : '' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="bidang">
        <label for="f-kategori">Kategori</label>
        <input id="f-kategori" name="kategori" type="text" value="<?= e($kelas['kategori']) ?>"
               placeholder="Ketik kategori baru" autocomplete="off">
        <?php $kategoriAda = ambilSemua('SELECT kategori, COUNT(*) AS jml FROM kelas GROUP BY kategori ORDER BY jml DESC, kategori'); ?>
        <?php if ($kategoriAda): ?>
          <div class="cip-pilih">
            <?php foreach ($kategoriAda as $k): ?>
              <button class="cip-kecil<?= $kelas['kategori'] === $k['kategori'] ? ' is-on' : '' ?>"
                      type="button" data-isi="#f-kategori" data-nilai="<?= e($k['kategori']) ?>">
                <?= e($k['kategori']) ?> <span><?= (int) $k['jml'] ?></span>
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <p class="petunjuk">Ketik apa saja untuk membuat kategori baru, atau tekan salah satu di atas.</p>
      </div>
    </div>

    <div class="bidang">
      <div class="label-baris">
        <label for="f-ringkas">Ringkasan</label>
        <?php if ($aiHidup): ?>
          <button class="tbl-ai" type="button" data-ai="ringkasan" data-tujuan="#f-ringkas">Bantu tulis</button>
        <?php endif; ?>
      </div>
      <textarea id="f-ringkas" name="ringkas" rows="3" maxlength="400"
                data-hitung="#hitung-ringkas"><?= e($kelas['ringkas']) ?></textarea>
      <p class="petunjuk">
        Tampil di kartu galeri dan di bawah judul kelas.
        <span id="hitung-ringkas" class="hitung"></span>
      </p>
    </div>
  </section>

  <!-- ============ 2. Tampilan di galeri ============ -->
  <section class="kotak">
    <div class="kotak-kepala"><h2>Tampilan di galeri</h2></div>

    <div class="galeri-atur">
      <div>
        <div class="bidang">
          <label for="f-gambar">Gambar sampul</label>
          <div class="unggah">
            <input id="f-gambar" name="gambar" type="file" accept="image/jpeg,image/png,image/webp" data-gambar>
            <label class="unggah-tombol" for="f-gambar">Pilih gambar&hellip;</label>
            <span class="unggah-nama" id="unggah-nama">
              <?= $kelas['gambar'] ? 'Terpasang: ' . e($kelas['gambar']) : 'Belum ada gambar' ?>
            </span>
            <?php if ($kelas['gambar']): ?>
              <button class="tbl tbl-kecil tbl-bahaya" type="submit" name="aksi" value="hapus_gambar"
                      formnovalidate data-pastikan="Hapus gambar sampul kelas ini?">Hapus gambar</button>
            <?php endif; ?>
          </div>
          <p class="petunjuk">JPG, PNG, atau WebP. Maksimal 3 MB. Bentuk yang paling pas 16:10 &mdash; misalnya 1280&times;800.</p>
        </div>

        <div class="bidang">
          <label>Ikon cadangan</label>
          <p class="petunjuk petunjuk-atas">Dipakai hanya selama kelas ini belum punya gambar sampul.</p>
          <div class="ikon-pilih">
            <?php foreach (IKON as $nama => $_): ?>
              <label class="ikon-satu<?= $kelas['ikon'] === $nama ? ' is-on' : '' ?>">
                <input type="radio" name="ikon" value="<?= e($nama) ?>"<?= $kelas['ikon'] === $nama ? ' checked' : '' ?>>
                <?= ikonSvg($nama) ?>
                <span><?= e($nama) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="baris-form">
          <div class="bidang">
            <label for="f-harga">Harga</label>
            <input id="f-harga" name="harga" type="text" value="<?= e($kelas['harga']) ?>"
                   placeholder="Gratis, Rp 249rb, atau apa pun" autocomplete="off">
            <p class="petunjuk">Ditulis apa adanya di kartu galeri.</p>
          </div>
          <div class="bidang">
            <label for="f-level">Level</label>
            <input id="f-level" name="level" type="text" value="<?= e($kelas['level']) ?>" autocomplete="off">
          </div>
          <div class="bidang bidang-kecil">
            <label for="f-urutan">Urutan di galeri</label>
            <input id="f-urutan" name="urutan" type="number" value="<?= (int) $kelas['urutan'] ?>">
          </div>
        </div>
      </div>

      <!-- Pratinjau kartu: satu-satunya cara melihat akibat pilihan di atas -->
      <div class="pratinjau" aria-hidden="true">
        <p class="pratinjau-label">Pratinjau kartu</p>
        <div class="kartu-mini" id="pratinjau-kartu">
          <div class="mini-sampul">
            <img class="mini-gambar" id="mini-gambar" alt=""
                 src="<?= e(urlGambar($kelas['gambar'])) ?>"<?= $kelas['gambar'] ? '' : ' hidden' ?>>
            <span class="mini-status"><?= e($kelas['status']) ?></span>
            <span class="mini-ikon" id="mini-ikon"<?= $kelas['gambar'] ? ' hidden' : '' ?>><?= ikonSvg($kelas['ikon']) ?></span>
            <span class="mini-kategori"><?= e($kelas['kategori']) ?></span>
          </div>
          <p class="mini-judul"><?= e($kelas['judul']) ?></p>
          <p class="mini-ringkas"><?= e($kelas['ringkas']) ?></p>
          <p class="mini-kaki">
            <span class="mini-harga"><?= e($kelas['harga']) ?></span>
            <span class="mini-meta"><?= $jmlMateri ?> materi</span>
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ 3. Detail halaman kelas ============ -->
  <section class="kotak">
    <details class="lipat"<?= ($detail['ikhtisar']['hasil'] || $detail['tanya'] || $detail['sumber']) ? ' open' : '' ?>>
      <summary>
        <span class="lipat-judul">Detail halaman kelas</span>
        <span class="lipat-sub">Ikhtisar, sumber belajar, tanya jawab, keterangan pengajar</span>
      </summary>

      <div class="lipat-isi">
        <div class="baris-form">
          <div class="bidang">
            <label for="f-kicker">Label di atas judul</label>
            <input id="f-kicker" name="kicker" type="text" value="<?= e($detail['kicker']) ?>">
          </div>
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

        <div class="label-baris label-bagian">
          <h3>Ikhtisar</h3>
          <?php if ($aiHidup): ?>
            <button class="tbl-ai" type="button" data-ai="ikhtisar">Isi otomatis ketiganya</button>
          <?php endif; ?>
        </div>

        <div class="bidang">
          <label for="f-hasil">Yang akan dikuasai</label>
          <textarea id="f-hasil" name="hasil" rows="5"><?= e(keBaris($detail['ikhtisar']['hasil'] ?? [])) ?></textarea>
          <p class="petunjuk">Satu butir per baris.</p>
        </div>
        <div class="baris-form">
          <div class="bidang">
            <label for="f-untuk">Cocok untuk</label>
            <textarea id="f-untuk" name="untuk" rows="4"><?= e(keBaris($detail['ikhtisar']['untukSiapa'] ?? [])) ?></textarea>
            <p class="petunjuk">Satu butir per baris.</p>
          </div>
          <div class="bidang">
            <label for="f-syarat">Perlu disiapkan</label>
            <textarea id="f-syarat" name="syarat" rows="4"><?= e(keBaris($detail['ikhtisar']['syarat'] ?? [])) ?></textarea>
            <p class="petunjuk">Satu butir per baris.</p>
          </div>
        </div>

        <!-- Sumber belajar: baris berulang, bukan lagi teks berpemisah -->
        <div class="label-baris label-bagian">
          <h3>Sumber belajar</h3>
        </div>
        <div class="ulang" id="ulang-sumber" data-templat="#templat-sumber">
          <?php foreach ($detail['sumber'] ?: [[]] as $s): ?>
            <div class="ulang-baris">
              <div class="bidang"><input name="sumber_judul[]" type="text" placeholder="Judul berkas" value="<?= e($s['judul'] ?? '') ?>"></div>
              <div class="bidang"><input name="sumber_desc[]" type="text" placeholder="Keterangan singkat" value="<?= e($s['desc'] ?? '') ?>"></div>
              <div class="bidang bidang-kecil"><input name="sumber_aksi[]" type="text" placeholder="Unduh" value="<?= e($s['aksi'] ?? '') ?>"></div>
              <div class="bidang"><input name="sumber_url[]" type="text" placeholder="https://…" value="<?= e($s['url'] ?? '') ?>"></div>
              <button class="ulang-buang" type="button" aria-label="Hapus baris">&times;</button>
            </div>
          <?php endforeach; ?>
        </div>
        <button class="tbl tbl-kecil" type="button" data-tambah="#ulang-sumber">Tambah sumber</button>

        <!-- Tanya jawab -->
        <div class="label-baris label-bagian">
          <h3>Tanya jawab</h3>
          <?php if ($aiHidup): ?>
            <button class="tbl-ai" type="button" data-ai="tanya">Usulkan tanya jawab</button>
          <?php endif; ?>
        </div>
        <div class="ulang" id="ulang-tanya" data-templat="#templat-tanya">
          <?php foreach ($detail['tanya'] ?: [[]] as $t): ?>
            <div class="ulang-baris ulang-tegak">
              <div class="bidang"><input name="tanya_q[]" type="text" placeholder="Pertanyaan" value="<?= e($t['q'] ?? '') ?>"></div>
              <div class="bidang"><textarea name="tanya_a[]" rows="2" placeholder="Jawaban"><?= e($t['a'] ?? '') ?></textarea></div>
              <button class="ulang-buang" type="button" aria-label="Hapus baris">&times;</button>
            </div>
          <?php endforeach; ?>
        </div>
        <button class="tbl tbl-kecil" type="button" data-tambah="#ulang-tanya">Tambah tanya jawab</button>
      </div>
    </details>
  </section>
</form>

<template id="templat-sumber">
  <div class="ulang-baris">
    <div class="bidang"><input name="sumber_judul[]" type="text" placeholder="Judul berkas"></div>
    <div class="bidang"><input name="sumber_desc[]" type="text" placeholder="Keterangan singkat"></div>
    <div class="bidang bidang-kecil"><input name="sumber_aksi[]" type="text" placeholder="Unduh"></div>
    <div class="bidang"><input name="sumber_url[]" type="text" placeholder="https://…"></div>
    <button class="ulang-buang" type="button" aria-label="Hapus baris">&times;</button>
  </div>
</template>

<template id="templat-tanya">
  <div class="ulang-baris ulang-tegak">
    <div class="bidang"><input name="tanya_q[]" type="text" placeholder="Pertanyaan"></div>
    <div class="bidang"><textarea name="tanya_a[]" rows="2" placeholder="Jawaban"></textarea></div>
    <button class="ulang-buang" type="button" aria-label="Hapus baris">&times;</button>
  </div>
</template>

<!-- ============ 4. Modul & materi ============ -->
<section class="kotak" id="materi">
  <div class="kotak-kepala">
    <h2>Modul &amp; materi</h2>
    <span class="petunjuk"><?= count($modulList) ?> modul · <?= $jmlMateri ?> materi</span>
    <?php if ($aiHidup): ?>
      <button class="tbl-ai tbl-ai-besar" type="button" data-ai="kerangka">Susun kerangka dengan AI</button>
    <?php endif; ?>
  </div>

  <?php if (!$modulList): ?>
    <div class="kosong kosong-tuntun">
      <p><strong>Belum ada modul.</strong> Modul adalah pengelompokan materi &mdash; misalnya &ldquo;Persiapan&rdquo;, lalu &ldquo;Praktik&rdquo;.</p>
      <?php if ($aiHidup): ?>
        <p>Bisa mulai dari nol di bawah, atau biarkan AI menyusun kerangkanya dulu lalu Anda rapikan.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php foreach ($modulList as $nomor => $m): ?>
    <div class="modul-blok" id="modul-<?= (int) $m['id'] ?>">
      <div class="modul-kepala">
        <span class="modul-no">Modul <?= str_pad((string) ($nomor + 1), 2, '0', STR_PAD_LEFT) ?></span>
        <h3><?= e($m['judul']) ?></h3>
        <span class="petunjuk"><?= count($materiPer[$m['id']]) ?> materi</span>
        <button class="tautan-lain" type="button" data-buka="#ubah-modul-<?= (int) $m['id'] ?>">Ubah modul</button>
      </div>

      <div class="lipatan" id="ubah-modul-<?= (int) $m['id'] ?>" hidden>
        <form method="post" class="form-panel form-dalam">
          <?= csrfInput() ?>
          <input type="hidden" name="kelas_id" value="<?= (int) $id ?>">
          <input type="hidden" name="aksi" value="simpan_modul">
          <input type="hidden" name="modul_id" value="<?= (int) $m['id'] ?>">
          <div class="baris-form">
            <div class="bidang">
              <label>Judul modul</label>
              <input name="modul_judul" type="text" value="<?= e($m['judul']) ?>" required>
            </div>
            <div class="bidang bidang-kecil">
              <label>Urutan</label>
              <input name="modul_urutan" type="number" value="<?= (int) $m['urutan'] ?>">
            </div>
          </div>
          <div class="form-aksi">
            <button class="tbl tbl-utama tbl-kecil" type="submit">Simpan modul</button>
            <button class="tbl tbl-bahaya tbl-kecil" type="submit" name="aksi" value="hapus_modul"
                    data-pastikan="Hapus modul &quot;<?= e($m['judul']) ?>&quot; beserta <?= count($materiPer[$m['id']]) ?> materinya?">Hapus modul</button>
          </div>
        </form>
      </div>

      <?php foreach ($materiPer[$m['id']] as $x): ?>
        <div class="materi-baris" id="materi-<?= (int) $x['id'] ?>">
          <button class="materi-ringkas" type="button" data-buka="#ubah-materi-<?= (int) $x['id'] ?>">
            <span class="md-kode"><?= e($x['kode']) ?></span>
            <span class="md-judul"><?= e($x['judul']) ?></span>
            <span class="md-durasi"><?= e($x['durasi']) ?></span>
            <span class="md-yt<?= $x['youtube_id'] === '' ? ' md-kosong' : '' ?>">
              <?= $x['youtube_id'] === '' ? 'video belum diisi' : e($x['youtube_id']) ?>
            </span>
          </button>

          <div class="lipatan" id="ubah-materi-<?= (int) $x['id'] ?>" hidden>
            <form method="post" class="form-panel form-dalam" data-materi>
              <?= csrfInput() ?>
              <input type="hidden" name="kelas_id" value="<?= (int) $id ?>">
              <input type="hidden" name="aksi" value="simpan_materi">
              <input type="hidden" name="materi_id" value="<?= (int) $x['id'] ?>">

              <div class="baris-form">
                <div class="bidang">
                  <label>Modul</label>
                  <select name="modul_id">
                    <?php foreach ($modulList as $pilih): ?>
                      <option value="<?= (int) $pilih['id'] ?>"<?= (int) $pilih['id'] === (int) $m['id'] ? ' selected' : '' ?>><?= e($pilih['judul']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="bidang bidang-kecil">
                  <label>Kode</label>
                  <input name="kode" type="text" value="<?= e($x['kode']) ?>">
                </div>
                <div class="bidang bidang-kecil">
                  <label>Durasi</label>
                  <input name="durasi" type="text" value="<?= e($x['durasi']) ?>">
                </div>
                <div class="bidang bidang-kecil">
                  <label>Urutan</label>
                  <input name="materi_urutan" type="number" value="<?= (int) $x['urutan'] ?>">
                </div>
              </div>

              <div class="bidang">
                <label>Judul materi</label>
                <input name="materi_judul" type="text" value="<?= e($x['judul']) ?>" required data-judul-materi>
              </div>

              <div class="bidang">
                <label>Video YouTube</label>
                <input name="youtube_id" type="text" value="<?= e($x['youtube_id']) ?>" placeholder="ID atau tempel URL-nya">
                <p class="petunjuk">Boleh ditempel URL penuh &mdash; ID-nya diambil otomatis.</p>
              </div>

              <div class="label-baris">
                <label>Ringkasan &amp; poin penting</label>
                <?php if ($aiHidup): ?>
                  <button class="tbl-ai" type="button" data-ai="materi">Bantu tulis</button>
                <?php endif; ?>
              </div>
              <div class="baris-form">
                <div class="bidang">
                  <textarea name="materi_ringkas" rows="3" placeholder="Ringkasan materi" data-materi-ringkas><?= e($x['ringkas'] ?? '') ?></textarea>
                </div>
                <div class="bidang">
                  <textarea name="poin" rows="3" placeholder="Poin penting, satu per baris" data-materi-poin><?= e($x['poin'] ?? '') ?></textarea>
                </div>
              </div>

              <div class="form-aksi">
                <button class="tbl tbl-utama tbl-kecil" type="submit">Simpan materi</button>
                <button class="tbl tbl-bahaya tbl-kecil" type="submit" name="aksi" value="hapus_materi"
                        data-pastikan="Hapus materi &quot;<?= e($x['judul']) ?>&quot;?">Hapus materi</button>
              </div>
            </form>
          </div>
        </div>
      <?php endforeach; ?>

      <button class="tambah-materi" type="button" data-buka="#materi-baru-<?= (int) $m['id'] ?>">+ Tambah materi di modul ini</button>

      <div class="lipatan" id="materi-baru-<?= (int) $m['id'] ?>" hidden>
        <form method="post" class="form-panel form-dalam" data-materi>
          <?= csrfInput() ?>
          <input type="hidden" name="kelas_id" value="<?= (int) $id ?>">
          <input type="hidden" name="aksi" value="simpan_materi">
          <input type="hidden" name="modul_id" value="<?= (int) $m['id'] ?>">

          <div class="bidang">
            <label>Judul materi</label>
            <input name="materi_judul" type="text" required data-judul-materi>
          </div>
          <div class="baris-form">
            <div class="bidang bidang-kecil">
              <label>Durasi</label>
              <input name="durasi" type="text" placeholder="12 mnt">
            </div>
            <div class="bidang">
              <label>Video YouTube</label>
              <input name="youtube_id" type="text" placeholder="ID atau URL">
            </div>
          </div>
          <div class="label-baris">
            <label>Ringkasan &amp; poin penting</label>
            <?php if ($aiHidup): ?>
              <button class="tbl-ai" type="button" data-ai="materi">Bantu tulis</button>
            <?php endif; ?>
          </div>
          <div class="baris-form">
            <div class="bidang"><textarea name="materi_ringkas" rows="3" placeholder="Ringkasan materi" data-materi-ringkas></textarea></div>
            <div class="bidang"><textarea name="poin" rows="3" placeholder="Poin penting, satu per baris" data-materi-poin></textarea></div>
          </div>
          <div class="form-aksi">
            <button class="tbl tbl-utama tbl-kecil" type="submit">Tambah materi</button>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>

  <form method="post" class="form-panel modul-baru">
    <?= csrfInput() ?>
    <input type="hidden" name="kelas_id" value="<?= (int) $id ?>">
    <input type="hidden" name="aksi" value="simpan_modul">
    <div class="baris-form">
      <div class="bidang">
        <label for="modul_judul">Modul baru</label>
        <input id="modul_judul" name="modul_judul" type="text" placeholder="Judul modul" required>
      </div>
      <div class="form-aksi">
        <button class="tbl tbl-kecil" type="submit">Tambah modul</button>
      </div>
    </div>
  </form>
</section>

<!-- Formulir tersembunyi untuk menerapkan kerangka usulan AI -->
<form method="post" id="form-kerangka" hidden>
  <?= csrfInput() ?>
  <input type="hidden" name="kelas_id" value="<?= (int) $id ?>">
  <input type="hidden" name="aksi" value="terapkan_kerangka">
  <input type="hidden" name="kerangka" id="kerangka-isi">
</form>

<!-- ============ 5. Zona berbahaya ============ -->
<section class="kotak kotak-bahaya">
  <div class="kotak-kepala"><h2>Hapus kelas</h2></div>
  <p class="petunjuk">
    Menghapus kelas ini juga membuang <?= count($modulList) ?> modul dan <?= $jmlMateri ?> materi di dalamnya.
    Tidak bisa dibatalkan.
  </p>
  <form method="post" class="form-panel">
    <?= csrfInput() ?>
    <input type="hidden" name="kelas_id" value="<?= (int) $id ?>">
    <input type="hidden" name="aksi" value="hapus_kelas">
    <?php if ($jmlMateri > 0): ?>
      <div class="bidang">
        <label for="konfirmasi">Ketik <code><?= e($kelas['judul']) ?></code> untuk menegaskan</label>
        <input id="konfirmasi" name="konfirmasi" type="text" autocomplete="off">
      </div>
    <?php endif; ?>
    <div class="form-aksi">
      <button class="tbl tbl-bahaya" type="submit"
              data-pastikan="Hapus kelas ini beserta seluruh isinya?">Hapus kelas</button>
    </div>
  </form>
</section>

<!-- ============ Bilah aksi menempel ============ -->
<div class="bilah-simpan">
  <div class="bilah-simpan-isi">
    <p class="bilah-kabar" id="bilah-kabar">
      <?php if ($perluTerbit): ?>
        <span class="titik-kuning"></span> Ada perubahan yang belum tayang di situs
      <?php else: ?>
        <span class="titik-hijau"></span> Situs sudah memakai versi terbaru
      <?php endif; ?>
    </p>
    <form method="post" action="<?= tautan('terbitkan') ?>" class="sebaris">
      <?= csrfInput() ?>
      <button class="tbl tbl-kecil" type="submit">Terbitkan</button>
    </form>
    <button class="tbl tbl-utama" type="submit" form="form-kelas">Simpan kelas</button>
  </div>
</div>

<?php require __DIR__ . '/inc/kaki.php'; ?>
