# Panduan Deploy ke Domainesia

Domain: **invishar.com** · Repo: **github.com/invishar/invishar**

> ## ✅ SETUP SUDAH SELESAI — situs sudah live
>
> Seluruh Tahap 1–5 di bawah **sudah dikerjakan** pada 12 September 2026.
> Kamu **tidak perlu mengulanginya**. Untuk mengubah isi situs, langsung
> lompat ke bagian [Cara mengubah isi situs](#sesudah-ini-cara-mengubah-isi-situs).
>
> Tahap 1–5 disimpan sebagai catatan: berguna kalau nanti ganti komputer,
> ganti hosting, atau perlu menelusuri kenapa sesuatu disetel begitu.

Tujuannya: **cukup `git push`** — satu perintah mengirim kode ke GitHub
*sekaligus* memperbarui situs di invishar.com.

## Cara kerjanya

Kita pakai **Git Deploy Manager** bawaan Domainesia dalam mode **CI/CD**.

```
  komputermu                          Domainesia
  ──────────                          ──────────
                    ┌──▶ GitHub          (arsip kode)
  git push  ────────┤
                    └──▶ cPanel  ──▶ baca .cpanel.yml
                                        │
                                        │ salin isi site/
                                        ▼
                                     public_html/   → invishar.com ✅
```

Satu perintah `git push` mengirim ke **dua** tujuan sekaligus, karena nanti
kita daftarkan dua alamat pada remote `origin`.

Yang naik ke server **hanya isi folder `site/`** — itu diatur oleh berkas
`.cpanel.yml` di root repo. Folder `design-source/` dan `DEPLOY.md` tetap
tinggal di Git saja, tidak pernah tersentuh publik.

Ada 5 tahap. Sekali saja; sesudahnya tinggal push.

---

## Tahap 1 — Pasang SSH key ke cPanel

Ini kunci supaya komputermu boleh mendorong kode ke server. Key-nya **sudah
saya buatkan** di `C:\Users\user\.ssh\invishar_deploy` (mengikuti pola
`amanafinance_deploy` yang sudah kamu punya).

### 1.1 Nyalakan SSH Access

1. Login cPanel Domainesia.
2. Cari bagian **Security** → **SSH Access**.
3. Klik **Manage SSH Keys**.

> Kalau menu SSH Access tidak muncul, hubungi support Domainesia lewat live
> chat dan minta *"tolong aktifkan SSH access (jailshell) untuk akun saya"*.
> Biasanya beres dalam hitungan menit.

### 1.2 Tempel kunci publik

1. Klik **Import Key**.
2. Kosongkan kolom *Private Key*. Yang diisi hanya **Public Key**.
3. Tempel baris ini **persis**, satu baris utuh tanpa enter di tengah:

```
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIEHj1/DB8VvF5vuwgnKceDBhsuTy78F+J1qFqOCs5aM8 deploy invishar dari laptop
```

4. Beri nama key: `invishar_deploy`. Klik **Import**.
5. Kembali ke daftar key. Di baris `invishar_deploy` klik **Manage** →
   **Authorize**.

Statusnya harus berubah jadi **authorized**. Kalau masih *not authorized*,
push nanti akan ditolak.

### 1.3 Beri tahu komputermu key mana yang dipakai — SUDAH BERES

Komputermu punya lebih dari satu kunci SSH (`amanafinance_deploy` dan
`invishar_deploy`). Tanpa petunjuk, `ssh` akan mencoba menebak dan bisa salah
pakai. Jadi repo ini dikunci ke key yang benar lewat satu baris:

```bash
git config core.sshCommand "ssh -i ~/.ssh/invishar_deploy -o IdentitiesOnly=yes"
```

**Perintah ini sudah dijalankan** — tidak perlu kamu ketik ulang.

Artinya sederhana: *"khusus di repo ini, kalau menyambung lewat SSH, pakai
kunci `invishar_deploy` dan jangan coba kunci lain."* Pengaturannya tersimpan
di `.git/config` milik repo ini saja, tidak mengganggu proyek amanafinance.

Untuk memastikan sudah tersimpan:

```bash
git config --get core.sshCommand
```

---

## Tahap 2 — Naikkan kode ke GitHub

Repo lokal sudah siap (`git init` dan commit pertama sudah ada). Yang kurang
hanya alamat tujuannya.

```bash
git remote add origin https://github.com/invishar/invishar.git
git push -u origin main
```

Kalau diminta password, gunakan **Personal Access Token**, bukan password akun
GitHub — sejak 2021 password biasa sudah ditolak:

1. <https://github.com/settings/tokens> → **Generate new token (classic)**
2. **Note**: `push invishar` · centang scope **`repo`** saja
3. **Generate token** → **salin sekarang**, hanya tampil sekali
4. Tempel token itu saat terminal menanyakan password

Cek halaman repo GitHub-mu — folder `site/`, `design-source/`, dan berkas
`.cpanel.yml` harus sudah kelihatan.

---

## Tahap 3 — Daftarkan deployment di Domainesia

Kembali ke layar **Git Deploy Manager** (Apps → Git Deploy Manager).

1. Klik **+ Add Deployment**.
2. Isi:

   | Kolom | Isi |
   | --- | --- |
   | **Domain** | `invishar.com` |
   | **Repository URL** | `https://github.com/invishar/invishar.git` |
   | **Branch** | `main` |

   Repo-mu publik, jadi tidak perlu menyisipkan token di URL.

3. Simpan. Kartu deployment akan muncul di bawah.
4. Di kartu itu, klik **Enable CI/CD**.
5. Setelah CI/CD menyala, kartu menampilkan sebuah **push URL** — bentuknya
   kira-kira `ssh://user@srv123.domainesia.com/home/user/repositories/invishar`.
   **Salin URL itu.**

---

## Tahap 4 — Sambungkan supaya satu push mengirim ke dua tempat

Jalankan tiga baris ini. Ganti `<PUSH_URL>` dengan yang baru kamu salin:

```bash
git remote set-url --add --push origin https://github.com/invishar/invishar.git
git remote set-url --add --push origin <PUSH_URL>
git remote -v
```

Baris terakhir harus menampilkan **satu** `fetch` dan **dua** `push`:

```
origin  https://github.com/invishar/invishar.git (fetch)
origin  https://github.com/invishar/invishar.git (push)
origin  ssh://user@srv123.domainesia.com/home/user/repositories/invishar (push)
```

> Urutannya penting. Begitu kamu memakai `set-url --add --push` yang pertama,
> alamat push bawaan tidak lagi dipakai — karena itu GitHub harus ikut
> didaftarkan ulang secara eksplisit di baris pertama.

---

## Tahap 5 — Deploy pertama

```bash
git commit --allow-empty -m "Uji deploy pertama"
git push
```

Saat pertama kali menyambung lewat SSH akan muncul pertanyaan
*"Are you sure you want to continue connecting?"* — ketik **`yes`**, enter.

Cara memastikan berhasil:

1. Buka **https://invishar.com** — landing page-nya tampil.
2. Buka **https://invishar.com/deploy-terakhir.txt** — muncul tanggal dan jam
   deploy barusan. Ini cara cepat memastikan deploy benar jalan, bukan sekadar
   halaman lama yang di-cache.
3. Di panel Git Deploy Manager, tombol **Log** pada kartu deployment
   menampilkan catatan proses kalau ada yang gagal.

---

## Sesudah ini: cara mengubah isi situs

```bash
# 1. Edit apa pun di dalam folder site/
#    (teks di site/index.html, warna di site/css/style.css, dst.)

# 2. Simpan ke git
git add .
git commit -m "Ganti nomor WhatsApp"

# 3. Kirim — GitHub terbarui DAN situs ikut ter-deploy
git push
```

> **Perubahan belum kelihatan di browser?** Tekan `Ctrl + F5` untuk muat ulang
> paksa. Browser menyimpan CSS lama sampai satu bulan (diatur di
> `site/.htaccess`), sedangkan HTML hanya 10 menit. Cek
> `invishar.com/deploy-terakhir.txt` untuk memastikan servernya memang sudah
> menerima versi baru.

---

## Kalau macet

| Gejala | Penyebab & solusi |
| --- | --- |
| `Permission denied (publickey)` | Key belum di-**Authorize** di cPanel (Tahap 1.2 langkah 5). Cek juga `git config --get core.sshCommand` — harus menampilkan `ssh -i ~/.ssh/invishar_deploy -o IdentitiesOnly=yes`. |
| `Could not resolve hostname` | Push URL salah salin. Ambil ulang dari kartu deployment di panel Git Deploy Manager, lalu perbaiki dengan `git remote set-url --delete --push origin <url lama>` dan tambahkan yang benar. |
| Push ke GitHub jalan, tapi situs tidak berubah | Push URL cPanel belum terdaftar. Cek `git remote -v` — harus ada **dua** baris `(push)`. |
| Deploy jalan tapi `invishar.com` menampilkan daftar folder | `.cpanel.yml` tidak terbaca, jadi seluruh repo tersalin. Pastikan berkas itu ada di **root repo** (bukan di dalam `site/`) dan CI/CD sudah **Enable**. |
| Situs tampil tapi tanpa warna/gaya | Folder `css/` atau `js/` tidak ikut tersalin. Cek lewat File Manager: `public_html` harus berisi `index.html`, `css/`, `js/`, `assets/`, `.htaccess`. |
| `deploy-terakhir.txt` menampilkan jam lama | Deploy tidak jalan. Buka tombol **Log** di kartu deployment untuk melihat pesan errornya. |
| Menu SSH Access tidak ada di cPanel | Belum diaktifkan Domainesia. Minta lewat live chat: *"tolong aktifkan SSH access (jailshell)"*. |

---

## Sentuhan akhir setelah situs hidup

1. ~~**Aktifkan HTTPS.**~~ **Sudah beres.** Sertifikat sudah valid untuk
   `invishar.com` maupun `www.invishar.com`, dan blok *Paksa HTTPS* di
   `site/.htaccess` sudah diaktifkan supaya pengalihan itu tidak hilang saat
   deploy menimpa berkas `.htaccess` di server.
2. **Ganti alamat surel.** Masih `hello@invishar.id` di bagian kontak
   (`site/index.html` baris 315) — sesuaikan kalau domainmu `.com`.
3. **Ganti nomor WhatsApp.** Masih `+62 812 0000 0000` / `wa.me/628120000000`.
4. **Pasang screenshot produk.** Tiga kotak di bagian *Sorotan* masih
   placeholder. Taruh gambarnya di `site/assets/`, lalu ganti isi
   `.shot-frame` dengan tag `<img>` — contohnya sudah ada sebagai komentar di
   `site/index.html`.
5. **Hubungkan form kontak.** Sekarang masih mode demo. Tiga pilihan caranya
   ada di komentar `site/js/main.js`.

Daftar lengkap nilai bawaan yang perlu diganti ada di
[site/README.md](site/README.md).

---

## Catatan

- **Deploy tidak menghapus berkas lama di server.** Kalau suatu saat kamu
  menghapus berkas dari `site/`, hapus juga manual lewat File Manager cPanel.
- Folder `design-source/` berisi artboard Claude Design asli beserta
  runtime-nya. Ia tersimpan di GitHub sebagai arsip, tapi **tidak pernah**
  diunggah ke hosting — `.cpanel.yml` hanya menyalin `site/`.
- Key `invishar_deploy` dibuat tanpa passphrase supaya push tidak selalu
  menanyakan sandi. Wajar untuk deploy key. Jangan pernah membagikan berkas
  `invishar_deploy` (yang tanpa `.pub`) ke siapa pun.
