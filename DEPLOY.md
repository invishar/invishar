# Panduan Deploy ke Domainesia — untuk pemula

Tujuannya: setelah semua ini beres, **cukup `git push`** dan dalam satu menit
situs di domainmu ikut terbarui. Tidak perlu buka cPanel lagi.

## Cara kerjanya (baca sekali biar paham gambarannya)

```
  komputermu              GitHub                    Domainesia
  ──────────              ──────                    ──────────
  git push     ───────▶   repo kamu
                             │
                             │ GitHub Actions jalan otomatis
                             │ (resepnya: .github/workflows/deploy.yml)
                             ▼
                          unggah isi folder site/  ───▶  public_html/
                          lewat FTP                       situs live ✅
```

Yang diunggah **hanya isi folder `site/`**. Folder `design-source/` dan berkas
`.md` tetap di GitHub saja, tidak ikut ke server.

Total ada 4 tahap. Sekali saja; sesudahnya tinggal push.

---

## Tahap 1 — Naikkan proyek ke GitHub

Repo lokal sudah saya siapkan (sudah `git init` dan sudah ada commit pertama).
Yang tersisa: bikin wadahnya di GitHub, lalu dorong ke sana.

### 1.1 Buat repo kosong di GitHub

1. Buka <https://github.com/new>
2. **Repository name**: `invisharhome` (atau nama lain, bebas)
3. Pilih **Private** kalau tidak mau dilihat orang. Publik juga tidak apa-apa —
   tidak ada password di dalam repo ini.
4. **PENTING**: jangan centang *Add a README*, *Add .gitignore*, maupun
   *Choose a license*. Biarkan benar-benar kosong, supaya tidak bentrok.
5. Klik **Create repository**.

### 1.2 Dorong kode dari komputermu

GitHub akan menampilkan alamat repo, bentuknya seperti
`https://github.com/namakamu/invisharhome.git`. Salin itu.

Buka terminal di folder proyek (`D:\BISNIS\APLIKASI\INVISHAR\invisharhome`),
lalu jalankan dua baris ini — ganti alamatnya dengan milikmu:

```bash
git remote add origin https://github.com/namakamu/invisharhome.git
git push -u origin main
```

Kalau diminta login, gunakan **Personal Access Token**, bukan password akun
(GitHub sudah tidak menerima password biasa sejak 2021):

1. Buka <https://github.com/settings/tokens> → **Generate new token (classic)**
2. **Note**: `deploy invishar`, **Expiration**: pilih sesukamu
3. Centang scope **`repo`** saja
4. **Generate token**, lalu **salin token itu sekarang** — hanya tampil sekali
5. Saat terminal menanyakan password, tempel token tersebut

> Lebih gampang lagi: install [GitHub Desktop](https://desktop.github.com/) dan
> push lewat tombol. Loginnya lewat browser, tidak perlu urusan token.

Cek di halaman repo GitHub-mu — folder `site/`, `design-source/`, dan
`.github/` harus sudah kelihatan di sana.

---

## Tahap 2 — Buat akun FTP di cPanel Domainesia

GitHub butuh "kunci" untuk menaruh berkas di hostingmu. Kita buatkan akun FTP
khusus, supaya password cPanel utamamu tidak perlu disimpan di GitHub.

1. Login ke <https://my.domainesia.com> → menu **Hosting** → tombol **Kelola**
   pada paket hostingmu → **Login ke cPanel**.
2. Di cPanel, cari bagian **Files** → klik **FTP Accounts**.
3. Isi formulir **Add FTP Account**:

   | Kolom | Isi |
   | --- | --- |
   | **Log In** | `deploy` |
   | **Domain** | pilih domainmu dari dropdown |
   | **Password** | klik **Password Generator**, lalu **salin dan simpan** — nanti dipakai di Tahap 3 |
   | **Directory** | hapus isi bawaannya, ketik persis: `public_html` |
   | **Quota** | pilih **Unlimited** |

   > Kolom **Directory** ini yang paling sering salah. Bawaannya biasanya
   > terisi `deploy` atau `public_html/deploy` — **hapus dan ganti jadi
   > `public_html`** saja. Kalau salah, situsmu akan mendarat di subfolder dan
   > domain tetap menampilkan halaman kosong.

4. Klik **Create FTP Account**.
5. Setelah jadi, akun itu muncul di daftar bawah. Catat **username lengkapnya**
   — bentuknya `deploy@namadomainmu.com`, **termasuk bagian `@domain`-nya**.

### Cari alamat server FTP

Masih di halaman FTP Accounts, klik **Configure FTP Client** pada akun yang
baru dibuat. Akan muncul baris **FTP Server** — biasanya
`ftp.namadomainmu.com`. Catat itu.

Kalau domainmu belum diarahkan ke Domainesia, pakai **nama server** atau **IP**
yang tertera di sidebar kanan cPanel (bagian *General Information* →
*Shared IP Address*).

Jadi sekarang kamu pegang tiga hal:

| Nama | Contoh |
| --- | --- |
| Alamat server | `ftp.invishar.id` |
| Username | `deploy@invishar.id` |
| Password | yang tadi kamu salin dari Password Generator |

---

## Tahap 3 — Simpan ketiga data itu di GitHub

Jangan pernah menulis password langsung di dalam kode. GitHub punya tempat
khusus yang terenkripsi, namanya *Secrets*.

1. Buka repo GitHub-mu → tab **Settings** (paling kanan atas).
2. Sidebar kiri → **Secrets and variables** → **Actions**.
3. Klik **New repository secret**, lalu buat **tiga** secret ini satu per satu.
   Nama harus **persis** seperti di bawah (huruf besar semua):

   | Name | Secret |
   | --- | --- |
   | `FTP_SERVER` | `ftp.invishar.id` |
   | `FTP_USERNAME` | `deploy@invishar.id` |
   | `FTP_PASSWORD` | password FTP tadi |

Setelah selesai, di daftar secret harus ada tepat tiga baris itu. Isinya tidak
bisa dilihat lagi — itu memang disengaja. Kalau lupa, tinggal timpa dengan
**Update**.

---

## Tahap 4 — Deploy pertama

Deploy jalan otomatis setiap push. Tapi untuk yang pertama, jalankan manual
supaya bisa langsung kamu lihat prosesnya:

1. Buka repo GitHub-mu → tab **Actions**.
2. Sidebar kiri → klik **Deploy ke Domainesia**.
3. Klik tombol **Run workflow** → **Run workflow** (yang hijau).
4. Tunggu sekitar 30–90 detik. Klik baris pekerjaan yang muncul untuk melihat
   log-nya berjalan.

**Titik hijau ✅** = berhasil. Buka domainmu di browser — landing page-nya
sudah tampil.

**Silang merah ❌** = ada yang salah. Klik untuk baca pesan errornya, lalu
cocokkan dengan tabel di bagian *Kalau macet* di bawah.

---

## Sesudah ini: cara mengubah isi situs

Inilah yang kamu mau — tinggal push:

```bash
# 1. Edit apa pun di dalam folder site/
#    (ganti teks di site/index.html, warna di site/css/style.css, dst.)

# 2. Simpan ke git
git add .
git commit -m "Ganti nomor WhatsApp"

# 3. Kirim — deploy jalan sendiri
git push
```

Lihat progresnya di tab **Actions**. Sekitar satu menit kemudian domainmu sudah
terbarui.

> **Kalau perubahan belum kelihatan di browser:** tekan `Ctrl + F5` untuk muat
> ulang paksa. Browser menyimpan CSS lama hingga satu bulan (diatur di
> `site/.htaccess`), sedangkan HTML hanya 10 menit.

---

## Kalau macet

| Gejala | Penyebab & solusi |
| --- | --- |
| `530 Login authentication failed` | Username kurang lengkap. Harus `deploy@namadomain.com`, bukan `deploy` saja. Perbaiki secret `FTP_USERNAME`. |
| `Timeout` / `ECONNREFUSED` | FTPS diblokir. Buka `.github/workflows/deploy.yml`, ubah `protocol: ftps` jadi `protocol: ftp`, lalu commit & push. |
| `ENOTFOUND ftp.namadomain.com` | Alamat server salah, atau domain belum mengarah ke Domainesia. Pakai IP server dari sidebar cPanel. |
| Deploy hijau tapi domain masih menampilkan halaman bawaan Domainesia | Kolom **Directory** akun FTP bukan `public_html`. Hapus akun FTP itu, buat ulang dengan directory yang benar. |
| Deploy hijau tapi muncul *Index of /* | Berkas `index.html` tidak ada di root `public_html`. Cek lewat File Manager cPanel — kalau ia masuk ke subfolder, berarti masalah Directory yang sama seperti di atas. |
| Situs tampil tapi tanpa warna/gaya | Folder `css/` atau `js/` tidak ikut terunggah. Cek di File Manager cPanel, `public_html` harus berisi `index.html`, `css/`, `js/`, `assets/`. |
| Actions tidak jalan sama sekali saat push | Branch-nya bukan `main`. Cek dengan `git branch`. Kalau namanya `master`, jalankan `git branch -M main` lalu push lagi. |

---

## Sentuhan akhir setelah situs hidup

1. **Aktifkan HTTPS.** Di cPanel → **SSL/TLS Status** → centang domainmu →
   **Run AutoSSL**. Tunggu sampai statusnya hijau. Sesudah itu buka
   `site/.htaccess`, hapus tanda `#` di blok *Paksa HTTPS*, lalu push.
2. **Ganti data bawaan.** Nomor WhatsApp, alamat surel, dan domain di tag
   `canonical`/`og:url` masih nilai contoh. Daftar lengkapnya ada di
   [site/README.md](site/README.md).
3. **Pasang screenshot produk.** Tiga kotak di bagian *Sorotan* masih
   placeholder. Taruh gambarnya di `site/assets/`, lalu ganti isi
   `.shot-frame` dengan tag `<img>` — contohnya sudah ada sebagai komentar di
   `site/index.html`.
4. **Hubungkan form kontak.** Sekarang masih mode demo. Tiga pilihan caranya
   ada di komentar `site/js/main.js`.

---

## Catatan

- Berkas `.ftp-deploy-sync-state.json` yang muncul di `public_html` itu normal
  — catatan milik robot deploy supaya unggahan berikutnya hanya mengirim
  berkas yang berubah. Jangan dihapus; ia sudah diblokir dari akses publik
  lewat `.htaccess`.
- Folder `design-source/` berisi artboard Claude Design asli beserta
  runtime-nya. Ia ikut tersimpan di GitHub sebagai arsip, tapi **tidak pernah
  diunggah ke hosting**.
- Deploy tidak menghapus berkas lama di server. Kalau suatu saat kamu menghapus
  berkas dari `site/`, hapus juga manual lewat File Manager cPanel.
