<?php
declare(strict_types=1);

/* =============================================================================
   Landing page custom — HTML unggahan admin untuk satu produk.

   Alamatnya tetap invishar.com/p/{slug} (link affiliate /r/KODE/{slug} ikut
   berlaku). Berkas disimpan di data/lp/{folder}/:
     halaman.html   HTML-nya (namanya selalu ini)
     …              gambar, CSS, JS, font — jalur relatifnya dipertahankan

   KEAMANAN. HTML unggahan bisa berisi skrip. Supaya skrip itu tidak bisa
   menyentuh panel atau sesi login (satu domain), halaman disajikan dengan
   Content-Security-Policy "sandbox" tanpa allow-same-origin: peramban
   memperlakukannya sebagai asal terpisah — tidak bisa membaca cookie,
   tidak bisa memanggil /panel atas nama admin. HTML-nya tidak bisa dibuka
   langsung lewat /data/lp/… (.htaccess menolak).

   Penanda yang diganti saat ditampilkan:
     {{ORDER}}  alamat formulir order produk ini (/order/{slug})
     {{HARGA}}  harga seperti di halaman bawaan, mis. "Rp 249.000"
     {{NAMA}}   nama produk
   ============================================================================= */

require_once __DIR__ . '/produk.php';

const LP_ASET_BOLEH = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'svg', 'ico', 'css', 'js', 'woff', 'woff2', 'ttf', 'otf', 'mp4', 'webm'];
const LP_MAKS_HTML = 2 * 1024 * 1024;
const LP_MAKS_BERKAS = 10 * 1024 * 1024;
const LP_MAKS_TOTAL = 40 * 1024 * 1024;
const LP_MAKS_JUMLAH = 120;

function folderLp(): string
{
    return rtrim((string) konfig('situs_data'), '/') . '/lp';
}

/** Folder induk landing page, lengkap dengan penjaganya. */
function siapkanFolderLp(): ?string
{
    $folder = folderLp();
    if (!is_dir($folder) && !@mkdir($folder, 0755, true) && !is_dir($folder)) {
        return null;
    }
    $jaga = $folder . '/.htaccess';
    $isi = <<<'HT'
# Berkas landing page unggahan (dibuat otomatis oleh panel).
# HTML hanya boleh tampil lewat /p/nama-produk yang memakai sandbox.
# Dibuka langsung dari sini = ditolak, begitu juga skrip server.
<FilesMatch "\.(?i:html?|xhtml|shtml|php\d?|phtml|phar|cgi|pl|py|xml|json|htaccess)$">
  Require all denied
</FilesMatch>
<IfModule mod_headers.c>
  # SVG yang dibuka langsung tidak boleh menjalankan skrip.
  <FilesMatch "\.(?i:svg)$">
    Header set Content-Security-Policy "sandbox; default-src 'none'; style-src 'unsafe-inline'; img-src data:"
  </FilesMatch>
  # Halaman bersandbox dianggap asal lain; font & modul JS butuh izin CORS.
  <FilesMatch "\.(?i:woff2?|ttf|otf|js|css)$">
    Header set Access-Control-Allow-Origin "*"
  </FilesMatch>
  Header set X-Content-Type-Options "nosniff"
</IfModule>
Options -Indexes -ExecCGI
HT;
    if (!is_file($jaga) || file_get_contents($jaga) !== $isi) {
        file_put_contents($jaga, $isi);
    }
    return $folder;
}

/** Jalur relatif yang aman di dalam folder landing page, atau null. */
function jalurLpAman(string $nama): ?string
{
    $nama = trim(str_replace('\\', '/', $nama), '/');
    if ($nama === '' || strlen($nama) > 180 || str_contains($nama, "\0")) {
        return null;
    }
    foreach (explode('/', $nama) as $bagian) {
        if ($bagian === '' || $bagian === '.' || $bagian === '..' || $bagian[0] === '.'
            || !preg_match('/^[\p{L}\p{N} ._()\-]+$/u', $bagian)) {
            return null;
        }
    }
    return $nama;
}

function ekstensiLp(string $nama): string
{
    return strtolower(pathinfo($nama, PATHINFO_EXTENSION));
}

/** SVG berisi skrip atau pemicu kejadian ditolak — gambar tidak butuh itu. */
function svgAman(string $isi): bool
{
    return !preg_match('/<\s*script|\son[a-z]+\s*=|javascript:|<\s*foreignObject|<\s*iframe|<\s*embed|<\s*object/i', $isi);
}

/** Batas unggah server, untuk ditampilkan di formulir. */
function batasUnggahServer(): string
{
    $ke = function (string $v): int {
        $v = trim($v);
        $n = (int) $v;
        return match (strtolower(substr($v, -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n };
    };
    $b = min($ke((string) ini_get('upload_max_filesize')), $ke((string) ini_get('post_max_size')));
    return $b > 0 ? round($b / 1048576) . ' MB' : 'tidak diketahui';
}

/**
 * Menyimpan unggahan landing page ke folder baru.
 * $unggahan: $_FILES['lp_berkas'] (input multiple). Boleh berisi:
 *   - satu .zip berisi HTML + asetnya, ATAU
 *   - satu berkas .html plus gambar/CSS/JS pendukungnya.
 * Mengembalikan [nama folder, null] atau [null, pesan galat].
 * @return array{0: ?string, 1: ?string}
 */
function simpanLandingPage(int $produkId, array $unggahan): array
{
    $berkas = [];
    foreach ((array) ($unggahan['name'] ?? []) as $i => $nama) {
        $kode = (int) ($unggahan['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($kode === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($kode === UPLOAD_ERR_INI_SIZE || $kode === UPLOAD_ERR_FORM_SIZE) {
            return [null, '"' . $nama . '" melebihi batas unggah server (' . batasUnggahServer() . ').'];
        }
        if ($kode !== UPLOAD_ERR_OK || !is_uploaded_file((string) $unggahan['tmp_name'][$i])) {
            return [null, 'Unggahan "' . $nama . '" gagal. Coba lagi.'];
        }
        $berkas[] = ['nama' => basename(str_replace('\\', '/', (string) $nama)), 'tmp' => (string) $unggahan['tmp_name'][$i], 'ukuran' => (int) $unggahan['size'][$i]];
    }
    if (!$berkas) {
        return [null, 'Pilih berkas HTML (atau .zip) landing page-nya dulu.'];
    }

    /* ---- kumpulkan: [jalur relatif => isi] ---- */
    $isi = [];
    $zip = array_values(array_filter($berkas, fn ($b) => ekstensiLp($b['nama']) === 'zip'));
    if ($zip) {
        if (count($berkas) > 1) {
            return [null, 'Unggah .zip saja, atau HTML + gambar tanpa .zip — jangan dicampur.'];
        }
        if (!class_exists('ZipArchive')) {
            return [null, 'Server belum mendukung .zip. Unggah berkas HTML dan gambarnya langsung (pilih beberapa berkas sekaligus).'];
        }
        $za = new ZipArchive();
        if ($za->open($zip[0]['tmp']) !== true) {
            return [null, 'Berkas .zip tidak bisa dibuka. Pastikan tidak rusak dan tidak berkata sandi.'];
        }
        $total = 0;
        for ($i = 0; $i < $za->numFiles; $i++) {
            $st = $za->statIndex($i);
            $nama = (string) $st['name'];
            if (str_ends_with($nama, '/') || str_starts_with($nama, '__MACOSX/') || basename($nama) === '.DS_Store' || basename($nama) === 'Thumbs.db') {
                continue;
            }
            $jalur = jalurLpAman($nama);
            if ($jalur === null) {
                $za->close();
                return [null, 'Nama berkas di dalam .zip tidak aman atau aneh: "' . $nama . '".'];
            }
            $total += (int) $st['size'];
            if ((int) $st['size'] > LP_MAKS_BERKAS || $total > LP_MAKS_TOTAL || count($isi) >= LP_MAKS_JUMLAH) {
                $za->close();
                return [null, 'Isi .zip terlalu besar (maks. ' . (LP_MAKS_TOTAL >> 20) . ' MB, ' . LP_MAKS_JUMLAH . ' berkas, ' . (LP_MAKS_BERKAS >> 20) . ' MB per berkas).'];
            }
            $isi[$jalur] = (string) $za->getFromIndex($i);
        }
        $za->close();
        // Banyak .zip membungkus semuanya dalam satu folder — buang folder itu.
        $awalan = null;
        foreach (array_keys($isi) as $j) {
            $kepala = str_contains($j, '/') ? substr($j, 0, strpos($j, '/') + 1) : '';
            $awalan = $awalan === null ? $kepala : ($awalan === $kepala ? $awalan : '');
        }
        if ($awalan) {
            $isi = array_combine(array_map(fn ($j) => substr($j, strlen($awalan)), array_keys($isi)), array_values($isi));
        }
    } else {
        $total = 0;
        foreach ($berkas as $b) {
            $jalur = jalurLpAman($b['nama']);
            if ($jalur === null) {
                return [null, 'Nama berkas tidak aman atau aneh: "' . $b['nama'] . '". Pakai huruf, angka, titik, dan tanda hubung.'];
            }
            $total += $b['ukuran'];
            if ($total > LP_MAKS_TOTAL || count($isi) >= LP_MAKS_JUMLAH) {
                return [null, 'Unggahan terlalu besar (maks. ' . (LP_MAKS_TOTAL >> 20) . ' MB).'];
            }
            $isi[$jalur] = (string) file_get_contents($b['tmp']);
        }
    }

    /* ---- periksa ---- */
    $html = array_values(array_filter(array_keys($isi), fn ($j) => in_array(ekstensiLp($j), ['html', 'htm'], true)));
    if (!$html) {
        return [null, 'Tidak ada berkas .html di unggahan.'];
    }
    if (count($html) > 1) {
        $utama = array_values(array_filter($html, fn ($j) => strtolower($j) === 'index.html' || strtolower($j) === 'index.htm'));
        if (!$utama) {
            return [null, 'Ada ' . count($html) . ' berkas HTML. Unggah satu saja, atau beri nama index.html untuk halaman utamanya.'];
        }
        $htmlUtama = $utama[0];
    } else {
        $htmlUtama = $html[0];
    }
    if (strlen($isi[$htmlUtama]) > LP_MAKS_HTML) {
        return [null, 'Berkas HTML lebih dari ' . (LP_MAKS_HTML >> 20) . ' MB. Pisahkan gambar dari HTML-nya (jangan ditanam base64).'];
    }
    if (!preg_match('//u', $isi[$htmlUtama])) {
        return [null, 'HTML harus berkodean UTF-8. Simpan ulang berkasnya sebagai UTF-8.'];
    }
    foreach ($isi as $jalur => $data) {
        if ($jalur === $htmlUtama || in_array(ekstensiLp($jalur), ['html', 'htm'], true)) {
            continue;
        }
        if (!in_array(ekstensiLp($jalur), LP_ASET_BOLEH, true)) {
            return [null, 'Jenis berkas "' . $jalur . '" tidak diterima. Yang boleh: HTML, gambar, CSS, JS, font, dan video.'];
        }
        if (ekstensiLp($jalur) === 'svg' && !svgAman($data)) {
            return [null, 'SVG "' . $jalur . '" berisi skrip. Simpan ulang sebagai gambar biasa (tanpa skrip/animasi interaktif).'];
        }
    }

    /* ---- tulis ke folder baru, baru diganti setelah semuanya berhasil ---- */
    $induk = siapkanFolderLp();
    if ($induk === null) {
        return [null, 'Folder landing page tidak bisa dibuat di server.'];
    }
    $namaFolder = $produkId . '-' . date('ymdHis') . '-' . bin2hex(random_bytes(3));
    $tujuan = $induk . '/' . $namaFolder;
    if (!@mkdir($tujuan, 0755)) {
        return [null, 'Folder landing page tidak bisa dibuat.'];
    }
    foreach ($isi as $jalur => $data) {
        if (in_array(ekstensiLp($jalur), ['html', 'htm'], true) && $jalur !== $htmlUtama) {
            continue;   // HTML lain tidak dipakai
        }
        $ke = $jalur === $htmlUtama ? 'halaman.html' : $jalur;
        $dir = dirname($tujuan . '/' . $ke);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            hapusFolderLp($namaFolder);
            return [null, 'Gagal menyimpan "' . $jalur . '".'];
        }
        if (file_put_contents($tujuan . '/' . $ke, $data) === false) {
            hapusFolderLp($namaFolder);
            return [null, 'Gagal menyimpan "' . $jalur . '".'];
        }
    }
    // Catat jalur aset asli (relatif terhadap HTML utama) untuk penulisan ulang alamat.
    $dasarHtml = str_contains($htmlUtama, '/') ? dirname($htmlUtama) . '/' : '';
    $aset = [];
    foreach (array_keys($isi) as $jalur) {
        if (!in_array(ekstensiLp($jalur), ['html', 'htm'], true)) {
            $aset[] = $jalur;
        }
    }
    file_put_contents($tujuan . '/aset.json', json_encode(['dasar' => $dasarHtml, 'aset' => $aset], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    return [$namaFolder, null];
}

/** Menghapus satu folder landing page beserta isinya. */
function hapusFolderLp(?string $namaFolder): void
{
    if (!$namaFolder || !preg_match('/^[0-9]+-[0-9]{12}-[a-f0-9]{6}$/', $namaFolder)) {
        return;
    }
    $akar = folderLp() . '/' . $namaFolder;
    if (!is_dir($akar)) {
        return;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($akar, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($akar);
}

/** Daftar berkas landing page yang terpasang: [jalur => ukuran byte]. */
function berkasLp(?string $namaFolder): array
{
    $akar = $namaFolder ? folderLp() . '/' . $namaFolder : '';
    if ($akar === '' || !is_dir($akar)) {
        return [];
    }
    $hasil = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($akar, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $j = str_replace('\\', '/', substr($f->getPathname(), strlen($akar) + 1));
        if ($j !== 'aset.json') {
            $hasil[$j] = $f->getSize();
        }
    }
    ksort($hasil);
    return $hasil;
}

/** Token pratinjau: melihat landing page produk yang belum Tayang. */
function tokenPratinjauLp(array $p): string
{
    return substr(hash_hmac('sha256', 'lp|' . $p['id'] . '|' . $p['lp_berkas'], rahasiaSistem()), 0, 20);
}

/**
 * HTML landing page custom yang siap dikirim, atau null kalau tidak ada.
 * Alamat aset relatif diarahkan ke /data/lp/{folder}/…, penanda diganti.
 */
function htmlLandingPage(array $p): ?string
{
    $folder = (string) ($p['lp_berkas'] ?? '');
    if ($folder === '' || !preg_match('/^[0-9]+-[0-9]{12}-[a-f0-9]{6}$/', $folder)) {
        return null;
    }
    $akar = folderLp() . '/' . $folder;
    $html = @file_get_contents($akar . '/halaman.html');
    if ($html === false) {
        return null;
    }
    $info = json_decode((string) @file_get_contents($akar . '/aset.json'), true) ?: ['dasar' => '', 'aset' => []];
    $dasar = (string) ($info['dasar'] ?? '');
    $awalanUrl = '/data/lp/' . $folder . '/';

    // Jalur aset seperti yang ditulis di HTML (relatif terhadap letak HTML-nya).
    $peta = [];
    foreach ((array) ($info['aset'] ?? []) as $jalur) {
        $relatif = $dasar !== '' && str_starts_with($jalur, $dasar) ? substr($jalur, strlen($dasar)) : $jalur;
        $url = $awalanUrl . implode('/', array_map('rawurlencode', explode('/', $jalur)));
        $peta[$relatif] = $url;
        $peta[rawurlencode($relatif)] = $url;
        $peta[str_replace(' ', '%20', $relatif)] = $url;
    }
    if ($peta) {
        uksort($peta, fn ($a, $b) => strlen($b) <=> strlen($a));
        $pola = implode('|', array_map(fn ($j) => preg_quote($j, '/'), array_keys($peta)));
        // Hanya di dalam atribut/url(): diawali tanda kutip, kurung, atau koma srcset.
        $html = (string) preg_replace_callback(
            '/(["\'(]|,\s+)(?:\.\/)?(' . $pola . ')(?=["\')\s,?#])/u',
            fn ($m) => $m[1] . $peta[$m[2]],
            $html
        );
    }

    $html = strtr($html, [
        '{{ORDER}}' => urlOrderProduk($p),
        '{{HARGA}}' => htmlspecialchars(teksHargaProduk($p), ENT_QUOTES, 'UTF-8'),
        '{{NAMA}}'  => htmlspecialchars((string) $p['nama'], ENT_QUOTES, 'UTF-8'),
    ]);
    return $html;
}

/** Header keamanan untuk menyajikan landing page custom (lihat keterangan atas). */
function kirimKepalaSandboxLp(): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Security-Policy: sandbox allow-scripts allow-forms allow-popups allow-popups-to-escape-sandbox allow-modals allow-downloads allow-top-navigation-by-user-activation');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Cache-Control: no-cache, max-age=0');
}

/** Apakah HTML memakai penanda {{ORDER}} — kalau tidak, tombol order-nya perlu dicek. */
function lpPakaiOrder(?string $namaFolder): bool
{
    $isi = $namaFolder ? @file_get_contents(folderLp() . '/' . $namaFolder . '/halaman.html') : false;
    return $isi !== false && (str_contains($isi, '{{ORDER}}') || str_contains($isi, '/order/'));
}
