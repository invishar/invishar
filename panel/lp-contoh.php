<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
wajibMasuk();

/* Contoh landing page custom untuk diunduh — titik awal bagi desainer atau
   alat desain. Semua penanda yang dikenali panel sudah dipakai di sini. */

header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="contoh-landing-page.html"');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{NAMA}}</title>
<!--
  CONTOH LANDING PAGE CUSTOM — Invishar
  ------------------------------------------------------------------
  Penanda yang diganti otomatis saat halaman tampil:
    {{ORDER}}  alamat formulir order produk ini  → pakai di tombol pesan
    {{HARGA}}  harga produk, mis. "Rp 249.000"
    {{NAMA}}   nama produk

  Gambar: taruh di samping berkas ini lalu tulis namanya saja,
  mis. <img src="foto.jpg">. Unggah HTML + gambarnya sekaligus,
  atau kemas semuanya dalam satu .zip.

  Halaman ini dijalankan terpisah dari panel demi keamanan: skrip
  boleh dipakai, tapi localStorage dan cookie tidak tersedia.
-->
<style>
  :root { --hijau: #23441c; --latar: #eef4ea; --tinta: #1a1917; }
  * { box-sizing: border-box; }
  body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--tinta); background: var(--latar); line-height: 1.6; }
  .wadah { max-width: 1040px; margin: 0 auto; padding: 0 20px; }
  header { padding: 20px 0; font-weight: 700; }
  .hero { padding: 56px 0 72px; display: grid; gap: 32px; grid-template-columns: repeat(auto-fit, minmax(min(300px, 100%), 1fr)); align-items: center; }
  h1 { font-size: clamp(32px, 5vw, 52px); line-height: 1.08; letter-spacing: -0.03em; margin: 0 0 16px; }
  .lead { font-size: 18px; opacity: .8; margin: 0 0 28px; }
  .harga { font-size: 28px; font-weight: 700; margin: 0 0 16px; }
  .tombol { display: inline-block; background: var(--hijau); color: #fff; padding: 16px 28px; border-radius: 999px; text-decoration: none; font-weight: 600; }
  .kartu { background: #fff; border-radius: 20px; padding: 28px; box-shadow: 0 10px 40px rgba(0,0,0,.06); }
  .manfaat { padding: 24px 0 72px; display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(min(240px, 100%), 1fr)); }
  footer { padding: 32px 0; font-size: 14px; opacity: .6; }
</style>
</head>
<body>
  <header class="wadah">{{NAMA}}</header>

  <main class="wadah">
    <section class="hero">
      <div>
        <h1>Tulis janji utama produk Anda di sini</h1>
        <p class="lead">Satu–dua kalimat: untuk siapa produk ini, dan apa yang berubah setelah memakainya.</p>
        <p class="harga">{{HARGA}}</p>
        <a class="tombol" href="{{ORDER}}">Pesan sekarang</a>
      </div>
      <div class="kartu">
        <!-- Ganti dengan gambar Anda: <img src="foto.jpg" alt="" style="width:100%;border-radius:12px"> -->
        <p><strong>Tempat gambar produk</strong></p>
        <p>Unggah gambar bersama berkas HTML ini, lalu panggil dengan namanya.</p>
      </div>
    </section>

    <section class="manfaat">
      <div class="kartu"><h3>Manfaat pertama</h3><p>Jelaskan dengan bahasa pembeli, bukan istilah teknis.</p></div>
      <div class="kartu"><h3>Manfaat kedua</h3><p>Sebutkan hasil yang bisa dilihat atau diukur.</p></div>
      <div class="kartu"><h3>Manfaat ketiga</h3><p>Tutup keraguan yang paling sering ditanyakan.</p></div>
    </section>

    <p style="text-align:center;padding-bottom:64px"><a class="tombol" href="{{ORDER}}">Pesan {{NAMA}}</a></p>
  </main>

  <footer class="wadah">&copy; Invishar</footer>
</body>
</html>
