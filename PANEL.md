# Rencana Panel Admin Invishar

Dokumen rencana dan rujukan untuk **panel.invishar.com**.

Susunan menu, sistem afiliasi, produk, transaksi, dan pembayaran: lihat **AFFILIATE.md**.

Status: **tahap 0–3 sudah ditulis**, menunggu subdomain dan basis data dibuat
di cPanel. Cara memasangnya ada di bagian 9.

Disusun 18 September 2026

---

## 1. Apa yang panel ini kerjakan

Panel adalah **satu pintu masuk** ke seluruh urusan Invishar. Ia tidak
menggantikan aplikasi yang sudah jalan, hanya menyatukannya — dan mengurus
sendiri bagian yang memang belum punya admin.

**Yang dikerjakan panel sendiri:**

| Bagian | Kenapa di panel |
| --- | --- |
| Order jasa pembuatan aplikasi | Belum ada tempatnya sama sekali |
| Kelas (course) | Belum ada admin; sekarang isinya diketik langsung di berkas JS |
| Konten website utama | Sekarang teks, harga, dan nomor kontak ditulis di dalam HTML |
| Inventaris aset kantor | Belum ada tempatnya; berbeda dari stok dagang di catatorder |
| Daftar & tautan ke aplikasi lain | Supaya tidak perlu mengingat alamat admin satu per satu |

**Yang TIDAK dikerjakan panel:**

- Mengurus isi amanafinance dan catatorder. Keduanya **produk**, bukan bagian
  administrasi Invishar: angka order dan pengeluaran di dalamnya milik pengguna
  masing-masing aplikasi, bukan pembukuan Invishar. Panel hanya menautkannya.
- Menggabungkan angka dari kedua produk itu ke ringkasan. **Menyusul.** Kalau
  nanti dikerjakan, yang ditarik adalah angka yang memang milik Invishar —
  jumlah pengguna dan langganan aktif — bukan isi buku pengguna.

Perbedaan ini penting karena mudah tergelincir: menaruh omzet pengguna
catatorder di ringkasan Invishar akan menghasilkan angka yang kelihatan resmi
padahal salah arti.

---

## 2. Keputusan teknis

### 2.1 Tempat dan tumpukan — **diputuskan**

**PHP + MySQL di hosting Domainesia yang sama**, panel sebagai folder `panel/`
di repo ini, dan `panel.invishar.com` diarahkan ke folder itu.

Alasannya:

- Tugas pertama panel adalah mengubah isi invishar.com. Kalau keduanya satu
  server, panel tinggal menulis berkas; kalau beda tempat, perlu jembatan API
  tersendiri hanya untuk itu.
- amanafinance sudah di Domainesia dan catatorder menyusul ke sana. Panel di
  tempat yang sama membuat integrasi nanti jadi urusan satu server, bukan tiga.
- Deploy-nya sudah jalan: `git push cpanel main` tinggal ditambah satu tugas di
  `.cpanel.yml`. Tidak ada rantai deploy baru yang harus dirawat.
- Tidak ada build step, sejalan dengan repo ini sekarang.

Konsekuensi yang harus diterima: PHP tidak sama dengan React/Firebase yang
dipakai catatorder sekarang, jadi komponennya tidak bisa dipakai ulang; dan
panel ikut mati kalau hosting Domainesia mati.

### 2.2 Login

Satu akun, milik Anda sendiri. Tanpa peran, tanpa hak akses bertingkat.
Peran bisa ditambahkan belakangan tanpa membongkar apa pun, asalkan sejak awal
ada tabel `pengguna` — bukan kata sandi yang ditanam di dalam kode.

Syarat minimum yang tidak boleh ditawar:

- Kata sandi disimpan sebagai hash (`password_hash`), tidak pernah apa adanya.
- Pengaturan basis data disimpan **di luar** folder yang bisa dibuka publik, dan
  tidak pernah ikut masuk Git.
- Seluruh panel wajib HTTPS, dan hanya bisa dibuka setelah login.
- Percobaan login dibatasi supaya tidak bisa ditebak berulang-ulang.
- Setiap form yang mengubah data memakai token CSRF.

---

## 3. Susunan menu

```
panel.invishar.com
├── Ringkasan          angka hari ini + pekerjaan yang menunggu
├── Order jasa         daftar permintaan pembuatan aplikasi + statusnya
├── Kelas              kelas → modul → materi (menggantikan berkas data JS)
├── Konten website     teks, katalog, harga, kontak, tanya jawab invishar.com
├── Inventaris         aset kantor: laptop, kamera, perangkat kerja
├── Aplikasi           kartu tautan ke admin amanafinance, catatorder, dll
└── Pengaturan         akun, kata sandi, data kontak, jejak perubahan
```

**Ringkasan** sengaja dibuat sederhana dulu: jumlah order baru, order yang belum
dibalas, jumlah kelas terbit, dan kapan website terakhir diperbarui. Angka
keuangan lintas aplikasi menyusul di tahap integrasi.

---

## 4. Modul tahap pertama

### 4.1 Order jasa

Form kontak invishar.com sudah mengirim ke `panel/api-pesan.php` (jalur relatif,
satu origin, tanpa CORS) dan langsung muncul di menu Transaksi sebagai order
jasa. Bidang wajibnya nama, WhatsApp, dan pesan; surel opsional.

Alurnya: form kontak → tersimpan di basis data → muncul di panel dengan status.

Status yang cukup: `baru` → `dibalas` → `penawaran` → `dikerjakan` → `selesai`,
plus `batal`. Tiap order punya catatan bebas dan riwayat perubahan status.

Order juga bisa ditambahkan manual, karena kenyataannya banyak yang masuk lewat
WhatsApp, bukan lewat form.

**Uang dan dokumen.** `order_jasa.nilai` adalah nilai proyek; pembayarannya bisa
beberapa kali (DP, termin, pelunasan) sebagai baris `transaksi` dengan
`order_jasa_id`. `uangOrderJasa()` di `panel/inc/order.php` satu-satunya tempat
sisa dihitung, jadi angka di daftar, halaman order, formulir pencatatan, dan
dokumen cetak selalu sama. Order yang baru dibayar sebagian ditandai
**Bayar sebagian** — keadaan turunan di SQL, tidak pernah ditulis ke
`transaksi.status`. Isian "Label pembayaran" (`transaksi.catatan`) yang tercetak
sebagai keterangan termin di kuitansi.

Dua dokumen dicetak dari `panel/cetak.php`, dibuka di tab baru dan disimpan jadi
PDF lewat dialog cetak peramban — tanpa pustaka PDF:

| Dokumen | Alamat | Nomor | Syarat |
| --- | --- | --- | --- |
| Tagihan | `/panel/cetak?order=12` | `JASA-0012` | Nilai proyek sudah diisi |
| Kuitansi | `/panel/cetak?bayar=34` | `transaksi.kode_order` | Pembayaran sudah lunas |

Kop suratnya dari setelan `usaha.*` di `/panel/pengaturan#usaha` (nama, alamat,
telepon, surel, logo, rekening, jatuh tempo, penanda tangan, catatan). Disimpan
sebagai setelan, bukan kolom basis data, supaya menambahnya tidak menuntut
migrasi — satu berkas migrasi baru akan mengunci seluruh menu Penjualan sampai
admin menekan tombol di `/panel/pembaruan`.

Dokumennya dirakit ulang dari data hidup setiap kali dibuka, tidak ada salinan
yang dibekukan. Mengubah identitas usaha atau label pembayaran akan mengubah
dokumen yang **sudah dikirim**, jadi simpan tiap PDF yang diserahkan ke klien.

### 4.2 Kelas

Menggantikan `site/js/course-data.js` dan `site/js/kelas-data.js` yang sekarang
disunting dengan tangan.

- Daftar kelas: judul, kategori, ringkasan, level, harga, status, urutan.
- Di dalam kelas: modul, dan di dalam modul: materi (judul, durasi, ID video
  YouTube, ringkasan, poin penting).
- Menyusun ulang urutan dengan geser.
- Tombol **Terbitkan** yang menulis ulang berkas data yang dibaca situs.

### 4.3 Konten website

Bagian invishar.com yang sering berubah, supaya tidak perlu menyunting HTML:
teks hero, kartu katalog, harga, langkah kerja jasa, tanya jawab, nomor
WhatsApp, dan surel.

### 4.4 Inventaris aset kantor

Barang milik Invishar sendiri — laptop, kamera, perangkat kerja — bukan stok
barang dagang (itu sudah ada di catatorder, halaman `Products`).

Yang dicatat: nama barang, kategori, nomor seri, tanggal dan harga beli,
kondisi, siapa yang sedang memegang, dan catatan bebas. Cukup itu; daftar aset
yang terlalu banyak kolom justru berhenti diisi.

Nilai buku dan penyusutan sengaja tidak dimasukkan dulu — baru berguna kalau
asetnya sudah banyak dan ada urusan pembukuan yang menuntutnya.

### 4.5 Aplikasi

Sekadar daftar kartu berisi nama, keterangan singkat, dan tautan ke admin
masing-masing aplikasi. Tanpa integrasi apa pun dulu — persis seperti yang Anda
minta.

---

## 5. Bagaimana panel mengubah isi invishar.com

Ini bagian yang paling menentukan, karena invishar.com adalah situs statis tanpa
PHP dan tanpa build.

**Rancangan: basis data sebagai sumber kebenaran, JSON sebagai hasil terbitan.**

```
Panel (MySQL)  ──[tombol Terbitkan]──▶  public_html/data/*.json  ◀──[fetch]── invishar.com
```

- Panel menyimpan data di MySQL.
- Saat ditekan **Terbitkan**, panel menulis berkas JSON ke `public_html/data/`.
- Halaman `kelas.html`, `course.html`, dan `materi.html` membaca JSON itu.

Kenapa JSON, bukan halaman publik yang langsung membaca MySQL: pengunjung tidak
menyentuh basis data sama sekali, tidak ada PHP di jalur publik, berkasnya bisa
di-cache, dan kalau panel mati situs tetap hidup.

**Dua hal yang harus diperhatikan:**

1. Deploy menyalin isi `site/` ke `public_html` dan **tidak menghapus** berkas
   yang tidak dilacak Git. Jadi `data/*.json` hasil terbitan panel aman dari
   `git push` — asalkan folder itu tidak pernah ikut dimasukkan ke repo.
2. Berkas `course-data.js` dan `kelas-data.js` yang sekarang tetap dipertahankan
   sebagai **cadangan**: kalau JSON belum ada atau gagal dimuat, halaman jatuh ke
   data bawaan. Peralihannya jadi tidak berisiko, dan situs tetap bisa dibuka
   tanpa panel.

---

## 6. Rancangan tabel

Nama tabel dan kolom memakai bahasa Indonesia, mengikuti kebiasaan di repo ini.

```
pengguna        id · nama · surel · kata_sandi_hash · dibuat_pada · terakhir_masuk

order_jasa      id · nama · lembaga · surel · whatsapp · kebutuhan · sumber
                status · nilai · catatan · dibuat_pada · diperbarui_pada

order_riwayat   id · order_id · status_lama · status_baru · catatan · dibuat_pada

kelas           id · slug · judul · kategori · ringkas · level · harga
                status · ikon · urutan · diperbarui_pada

modul           id · kelas_id · judul · urutan

materi          id · modul_id · judul · durasi · youtube_id · ringkas
                poin (JSON) · urutan

konten          kunci · nilai (JSON) · diperbarui_pada

aset            id · nama · kategori · nomor_seri · tanggal_beli · harga_beli
                kondisi · pemegang · catatan · dibuat_pada

aplikasi        id · nama · keterangan · url_admin · urutan

log_aktivitas   id · pengguna_id · aksi · objek · dibuat_pada
```

`log_aktivitas` terlihat berlebihan untuk satu pengguna, tapi murah dibuat dan
sangat menolong saat suatu hari muncul pertanyaan "kenapa harganya berubah".

---

## 7. Urutan pengerjaan

| Tahap | Isi | Keadaan |
| --- | --- | --- |
| 0 | Kerangka panel, login, pemasang | **Sudah ditulis** |
| 1 | Menu Aplikasi + Ringkasan | **Sudah ditulis** |
| 2 | Admin kelas + tombol Terbitkan | **Sudah ditulis** |
| 3 | Order jasa + penerima form kontak | **Sudah ditulis** |
| 5 | Inventaris aset kantor | **Sudah ditulis** (dimajukan, kecil) |
| 4 | Editor konten website | Belum |
| 6 | Integrasi angka produk | Belum, menunggu keduanya mapan di Domainesia |

Semuanya masih **belum pernah dijalankan** — di komputer ini tidak ada PHP,
jadi pengujian sungguhan baru bisa dilakukan setelah dipasang di server.

---

## 8. Berkas panel

```
panel/
├── pasang.php        pemasang sekali pakai: bikin tabel + akun + isi awal
├── masuk.php         login (dibatasi 8 percobaan per 15 menit)
├── index.php         ringkasan
├── order.php         daftar order jasa + catat manual
├── order-detail.php  ubah status, nilai, catatan, riwayat
├── kelas.php         daftar kelas + tombol Terbitkan
├── kelas-edit.php    keterangan kelas, modul, dan materi
├── terbitkan.php     menulis JSON ke folder data/ di public_html
├── inventaris.php    aset kantor
├── aplikasi.php      tautan ke admin tiap produk
├── pengaturan.php    identitas usaha, akun, kata sandi, jejak perubahan
├── cetak.php         tagihan & kuitansi A4, siap disimpan jadi PDF
├── api-pesan.php     penerima form kontak invishar.com
├── skema.sql         seluruh tabel
├── isi-awal.json     kelas yang sekarang tayang, untuk mengisi panel
└── inc/              konfigurasi, basis data, sesi, tata letak
```

---

## 9. Cara memasang

Panel ikut ter-deploy ke `public_html/panel`, jadi alamatnya
**https://invishar.com/panel/** sejak `git push cpanel main` yang pertama.
Sisanya harus dikerjakan di cPanel:

1. **MySQL Databases**: buat basis data + pengguna, beri hak penuh.
2. **File Manager** → `public_html/panel/inc`: salin `konfig.contoh.php` menjadi
   `konfig.php`, isi data basis data. Periksa juga `situs_data`, seharusnya
   `/home/invishar/public_html/data`. Berkas ini tidak ikut Git, jadi tidak
   pernah tertimpa deploy.
3. Buka `https://invishar.com/panel/pasang.php`, isi nama, surel, kata sandi.
   Setelah ada satu akun, berkas itu menolak berjalan lagi dengan sendirinya.
5. Masuk ke panel → **Kelas** → tekan **Terbitkan** sekali, supaya situs mulai
   memakai data panel.
6. Di `site/index.html`, ganti `data-demo` pada form kontak menjadi
   `data-kirim="https://invishar.com/panel/api-pesan.php"`, lalu deploy.
   Sejak saat itu pesan yang masuk muncul di menu Order jasa.

### Nanti, saat subdomain dibuat

**Subdomains** → `panel.invishar.com`, arahkan *Document Root*-nya ke
`public_html/panel`. Tidak ada berkas yang perlu diubah; yang perlu disesuaikan
hanya `asal_diizinkan` di `konfig.php` dan alamat `data-kirim` di `index.html`
bila ingin memakai alamat subdomain.

---

## 10. Yang masih perlu dipastikan

1. Versi PHP dan jatah MySQL pada paket Domainesia yang sekarang.
2. Document Root subdomain `panel.invishar.com`, untuk mengisi `.cpanel.yml`.
3. Apakah folder `public_html/data` bisa ditulis oleh proses PHP panel.
4. Pencadangan basis data — siapa dan seberapa sering.

## 11. Akses otomatis produk digital & kelas (migrasi 006)

Begitu transaksi lunas — lewat webhook Duitku, jaring pengaman `selesai.php`,
tombol simulasi, maupun catat manual — `ubahStatusTransaksi()` membuatkan token
akses unik (`panel/inc/akses.php`). Pembeli membukanya di
`/toko/akses.php?t={token}`: tombol unduhan + instruksi dari kolom
`produk.akses_tautan` / `produk.akses_catatan` (diisi di Produk → Akses
otomatis), atau daftar materi untuk produk kelas.

Produk kelas mendaftarkan pembeli ke `peserta_kelas` otomatis, dan materinya
hanya bisa dibuka lewat `/toko/materi.php?t={token}` yang memvalidasi token +
kepesertaan di setiap permintaan. Kelas yang dijual (`produk` aktif
kategori kelas) diterbitkan `terbitkan.php` sebagai pratinjau saja — JSON
publik tidak memuat ID video/isi materi; halaman `materi.html` menampilkan
panel terkunci dengan tombol beli. Refund mencabut akses (peserta dihapus,
token dibuang).

Yang belum otomatis: notifikasi WhatsApp ke pembeli — tautan akses tampil
di halaman selesai, dan admin bisa menyalinnya dari detail transaksi.

Email akses dikirim otomatis via `mail()` bawaan PHP ke surel pembeli begitu
lunas (`panel/inc/email.php`): logo usaha, rincian pembelian, tombol menuju
halaman akses, dan instruksi produk. Pengirim memakai setelan `usaha.nama` /
`usaha.surel` (bawaan: Invishar <hello@invishar.id>). Karena mail() shared
hosting kadang mendarat di spam, halaman selesai menyarankan pembeli
memeriksa folder spam.
