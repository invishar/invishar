# Invishar — landing page

Versi statis dari desain **Invishar v2** (artboard Claude Design di folder induk).
Tanpa build step, tanpa dependensi npm. Cukup tiga berkas + aset.

```
site/
├── index.html          markup + seluruh isi teks
├── course.html         halaman kelas: ikhtisar, daftar materi, sumber, tanya jawab
├── materi.html         halaman materi: video YouTube, ceklis paham, tombol lanjut
├── css/style.css       token warna/tipografi di :root, lalu komponen
├── css/course.css      lanjutan style.css khusus halaman kelas & materi
├── js/main.js          menu mobile, saringan katalog, FAQ, form, reveal
├── js/course-data.js   ISI kelas (judul, modul, materi, ID video, tanya jawab)
├── js/course.js        perilaku halaman kelas & materi
└── assets/
    └── invishar-logo.png
```

## Halaman kelas

`course.html` dan `materi.html` mengambil seluruh isinya dari satu berkas:
**`js/course-data.js`** (`window.INVISHAR_COURSE`). Untuk mengubah kelas, cukup
sunting berkas itu — tidak ada markup yang perlu disentuh.

- **Video** diisi *ID*-nya saja, bukan URL penuh.
  `https://www.youtube.com/watch?v=aircAruvnKk` → `youtube: "aircAruvnKk"`.
  Video dimuat dengan sampul dulu; iframe `youtube-nocookie.com` baru dipasang
  setelah pengunjung menekan tombol putar.
- **Ceklis "sudah paham"** disimpan di `localStorage` peramban pengunjung
  (kunci `invishar.course.<slug>.paham`), jadi belum butuh server maupun akun.
  Saat panel admin dan login dibuat, cukup ganti fungsi `simpanan()` dan
  `catat()` di `js/course.js` bagian 2 dengan panggilan ke API.
- **Urutan materi** menentukan tombol *Sebelumnya* / *Materi berikutnya*; materi
  terakhir mengarah balik ke daftar materi.
- Halaman materi dibuka lewat `materi.html?m=<id materi>`. ID yang tidak dikenal
  jatuh ke materi pertama.

## Menjalankan secara lokal

```bash
cd site
python -m http.server 8899
# buka http://127.0.0.1:8899/
```

Membuka `index.html` langsung lewat `file://` juga jalan, tapi pakai server
lokal supaya path relatif dan font berperilaku sama seperti di produksi.

## Menerbitkan

Isi folder `site/` adalah situsnya. Unggah apa adanya:

- **Netlify / Vercel / Cloudflare Pages** — seret folder `site/`, atau arahkan
  publish directory ke `site`. Tanpa build command.
- **Hosting biasa (cPanel)** — unggah isi `site/` ke `public_html/`.
- **GitHub Pages** — taruh isi `site/` di root branch atau di `/docs`.

## Yang masih perlu diisi

| Hal | Di mana | Catatan |
| --- | --- | --- |
| Screenshot produk | `index.html`, seksi `#sorotan` | Tiga placeholder. Ganti isi `.shot-frame` dengan `<img src="assets/shot-xxx.png" alt="…">` |
| Nomor WhatsApp | `index.html`, seksi `#kontak` | Masih `+62 812 0000 0000` / `wa.me/628120000000` |
| Alamat surel | `index.html`, seksi `#kontak` | Masih `hello@invishar.id` |
| Domain | `index.html`, tag `<link rel="canonical">` dan `og:url`/`og:image` | Masih `https://invishar.id/` |
| Gambar OG | `og:image` | Sekarang menunjuk logo; idealnya gambar 1200×630 |
| Tujuan form | `js/main.js`, bagian 4 | Sedang mode demo — lihat di bawah |
| ID video kelas | `js/course-data.js` | Masih memakai ID contoh; ganti dengan video Anda |
| Berkas proyek kelas | `js/course-data.js`, bagian `sumber` | Semua `url` masih `#` |

### Mengaktifkan form kontak

Form saat ini **tidak mengirim ke mana pun**; ia hanya mengubah teks tombol,
persis seperti perilaku di desain aslinya. Tiga cara mengaktifkannya, semuanya
dijelaskan sebagai komentar di `js/main.js`:

1. **Layanan form** (tercepat, tanpa server) — di `index.html` ubah
   `action="https://formspree.io/f/KODE-ANDA"` lalu hapus atribut `data-demo`.
2. **Endpoint sendiri** — hapus `data-demo`, ganti blok itu dengan `fetch()`.
3. **Lempar ke WhatsApp** — rakit teks dari isian form lalu arahkan ke
   `https://wa.me/…?text=…`.

## Catatan teknis

- **Breakpoint mobile 760px**, mengikuti ambang `mob` di desain asli. Di bawah
  itu: menu jadi dropdown burger, tumpukan kartu hero jadi daftar biasa tanpa
  rotasi, dan kolom kelas menumpuk.
- **Progressive enhancement.** Tanpa JavaScript halaman tetap terbaca penuh:
  semua kartu katalog tampil, jawaban FAQ pertama terbuka, dan form jatuh ke
  pengiriman HTML biasa. Animasi *reveal* hanya aktif setelah `class="js"`
  terpasang di `<html>`.
- **Animasi reveal** memakai properti CSS `translate`, bukan `transform`,
  supaya tidak bentrok dengan rotasi kartu hero dan efek angkat saat hover.
- **`prefers-reduced-motion`** dihormati: marquee, kartu melayang, dan reveal
  semuanya mati kalau pengguna memintanya.
- **Semua warna dan font** ada sebagai custom property di `:root`
  (`css/style.css` bagian 1). Ganti palet cukup dari situ.
- Font dimuat dari Google Fonts (Space Grotesk + DM Sans). Kalau butuh jalan
  tanpa koneksi eksternal, unduh berkasnya dan ganti dengan `@font-face`.

## Hubungannya dengan berkas `.dc.html`

Berkas `*.dc.html` di folder induk adalah artboard kanvas Claude Design dan
**bukan** HTML yang bisa dijalankan langsung — isinya bergantung pada runtime
`support.js` (`<x-dc>`, `<helmet>`, binding `{{ … }}`, atribut `style-hover`,
kelas `DCLogic`). Berkas-berkas itu sengaja dibiarkan utuh sebagai referensi
desain. Folder `site/` inilah hasil konversinya.
