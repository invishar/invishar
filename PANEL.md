# Rencana Panel Admin Invishar

Dokumen perencanaan untuk **panel.invishar.com**. Belum ada kode yang ditulis;
berkas ini dulu yang disepakati.

Status: **draf** · Disusun 18 September 2026

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

- Mengelola order, produk, dan keuangan catatorder — sudah ada admin-nya sendiri
  (`Orders`, `Products`, `Customers`, `Expenses`, `BankAccounts`, `Analytics`).
- Mengelola data amanafinance.
- Menarik dan menggabungkan angka dari kedua aplikasi itu. **Menyusul**, setelah
  keduanya mapan di Domainesia. Untuk sekarang panel hanya menautkannya.

Menyatukan data keuangan dari dua aplikasi ke panel terlalu dini: selama
sumbernya masih berpindah hosting, angka di panel berisiko berbeda dengan angka
di aplikasi aslinya — dan angka keuangan yang berbeda lebih buruk daripada tidak
ada angka sama sekali.

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

Ini yang paling cepat terasa gunanya, karena sekarang **form kontak di
invishar.com tidak mengirim ke mana pun** — masih mode demo (lihat
`site/js/main.js` bagian 4). Setiap orang yang mengisi form itu hilang begitu
saja.

Alurnya: form kontak → tersimpan di basis data → muncul di panel dengan status.

Status yang cukup: `baru` → `dibalas` → `penawaran` → `dikerjakan` → `selesai`,
plus `batal`. Tiap order punya catatan bebas dan riwayat perubahan status.

Order juga bisa ditambahkan manual, karena kenyataannya banyak yang masuk lewat
WhatsApp, bukan lewat form.

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

| Tahap | Isi | Hasil yang bisa dilihat |
| --- | --- | --- |
| 0 | Subdomain, basis data, kerangka panel, login | Bisa masuk ke panel kosong |
| 1 | Menu Aplikasi + Ringkasan sederhana | Satu pintu masuk ke semua admin |
| 2 | Admin kelas + tombol Terbitkan | Kelas bisa diubah tanpa menyentuh kode |
| 3 | Order jasa + form kontak disambungkan | Tidak ada lagi calon klien yang hilang |
| 4 | Editor konten website | Teks landing bisa diubah sendiri |
| 5 | Inventaris aset kantor | Barang kantor tercatat, tidak lagi dari ingatan |
| 6 | Integrasi angka amanafinance & catatorder | Ringkasan keuangan lintas aplikasi |

Tahap 2 dan 3 yang paling mendesak: satu menutup pekerjaan manual yang sekarang
Anda lakukan, satu lagi menutup kebocoran calon klien.

---

## 8. Yang masih perlu dipastikan

1. Versi PHP dan jatah MySQL pada paket Domainesia yang sekarang.
2. Subdomain `panel.invishar.com` perlu dibuat di cPanel, lalu `.cpanel.yml`
   ditambah satu tugas salin ke folder tujuannya.
3. Form kontak invishar.com: langsung disambungkan ke panel pada tahap 3, atau
   sementara dilempar ke WhatsApp dulu?
4. Pencadangan basis data — siapa dan seberapa sering.
