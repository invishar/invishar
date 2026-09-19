# Rencana Sistem Affiliate Invishar

Dokumen rencana dan rujukan untuk sistem affiliate invishar.com.

Status: **rencana disetujui, belum dikerjakan.** Urutan pengerjaan ada di bagian *Tahapan*.

Disusun 19 September 2026


## Latar belakang

Invishar ingin orang luar (affiliator) ikut menjual produk-produknya dan mendapat komisi. Yang diminta: form pendaftaran affiliate, kode affiliate, link yang "menanam" jejak (cookie) di peramban calon pembeli, setelan fee per produk, landing page sederhana per produk, serta dashboard affiliator untuk melihat link, penghasilan, dan mengajukan penarikan.

Kondisi ekosistem sekarang yang membentuk rencana ini:

- **invishar.com** statis (`site/`), dan **panel admin PHP + MySQL** di `panel/` (sudah hidup di `invishar.com/panel/`). Deploy lewat `git push` → `.cpanel.yml` menyalin `site/` dan `panel/` ke `public_html`.
- **Belum ada pembayaran sama sekali.** Kelas punya harga tapi tanpa checkout (`kelas.harga` bahkan berupa teks `"Rp 249rb"`), Portal Sekolah/Ponpes lewat "Minta demo", jasa lewat form kontak. Jadi sistem affiliate harus membawa **checkout + transaksi** sendiri — tanpa itu tidak ada momen "terjual" untuk memicu komisi.
- Pondasi yang dipakai ulang: `panel/inc/awal.php` (PDO `q()/ambilSatu()/ambilSemua()`, CSRF, `catatLog()`, `rupiah()`), pola endpoint publik aman di `panel/api-pesan.php` (allowlist asal, honeypot, batas per IP), pola terbit-ke-JSON di `panel/terbitkan.php` (`tulisJson()` atomik), pembatas login `login_gagal`.
- Tidak ada PHP di komputer lokal; server punya PHP dan kita punya akses SSH.

**Keputusan yang sudah diambil:** gerbang pembayaran **Midtrans**, tapi sekarang pakai **gerbang uji (dummy)** dengan jalur kode yang sama; **semua produk** diatur admin di menu Produk baru, lengkap dengan setelan affiliate per produk; komisi langganan **berulang maksimal 12 bulan**; pendaftaran affiliate **terbuka tapi harus disetujui admin**.

---

## Gambaran arsitektur

Tiga area PHP berbagi satu basis data dan satu pustaka logika uang:

```
invishar.com/
├── p/{slug}            landing page produk (statis, baca data/produk.json)
├── r/{KODE}/{slug}     link affiliate → catat klik, tanam cookie, alihkan ke /p/{slug}
├── toko/               publik tanpa login: r.php, checkout, bayar-uji, webhook Midtrans
├── mitra/              portal affiliator (login sendiri)
└── panel/              admin (sudah ada) + menu baru
```

**Satu sumber kebenaran untuk urusan uang:** semua perhitungan atribusi, transaksi, dan komisi ada di `panel/inc/affiliate.php` dan `panel/inc/gerbang.php`, dipanggil oleh panel, mitra, dan toko. Tidak ada salinan logika di tempat lain.

### Alur utama

```
Affiliator bagikan  invishar.com/r/ANDI7K/ponpes-manager
        │
        ▼  toko/r.php: catat klik, set cookie inv_ref (10 hari), 302 →
   /p/ponpes-manager  (landing page statis)
        │
        ├─ produk berharga  → "Beli" → toko/checkout.php → transaksi (menunggu, affiliate terkunci)
        │                          → gerbang (uji / Midtrans) → lunas → komisi dibuat
        └─ produk penawaran → form "Minta demo" → order_jasa (affiliate terkunci)
                                   → admin catat pembayaran manual → lunas → komisi dibuat
```

---

## Keputusan desain

### Link & cookie ("cache link")
- Format link: `https://invishar.com/r/{KODE}/{slug}` (per produk) dan `https://invishar.com/r/{KODE}` (ke beranda). Pendek, mudah ditempel di WhatsApp.
- `toko/r.php` men-set cookie **dari server**: `inv_ref = KODE.kedaluwarsa.hmac` (tanggal kedaluwarsa ikut ditandatangani, jadi mengubah setelan tidak mengubah umur cookie yang sudah tertanam), `Domain=invishar.com; Path=/; Secure; HttpOnly; SameSite=Lax`, umur = setelan `cookie_hari` (bawaan 10). Dari server, bukan JavaScript, supaya tidak kena batas 7 hari Safari untuk cookie buatan JS. Ditandatangani HMAC (`rahasia_ref` di `konfig.php`) supaya tidak bisa dipalsukan isinya.
- **Last-click:** klik baru menimpa cookie lama.
- `/p/{slug}?ref=KODE` juga diterima: `produk.js` mengalihkan sekali ke `/r/KODE/slug`, jadi semua pencatatan tetap lewat server.
- Klik dicatat dengan IP yang di-hash (sha256 + garam), bukan IP mentah. Klik berulang dari IP+produk yang sama dalam 24 jam dihitung satu untuk statistik.

### Atribusi (siapa dapat komisi)
Fungsi tunggal `atribusi(produk, pembeli)` di `panel/inc/affiliate.php`:
1. Ambil kode dari cookie `inv_ref` yang tanda tangannya sah dan belum melewati tanggal kedaluwarsa di dalamnya; kalau tidak ada, dari parameter `ref`.
2. Sah hanya jika affiliate berstatus `aktif` **dan** produk `affiliate_aktif = 1`.
3. **Tolak beli-sendiri:** surel atau WhatsApp pembeli (dinormalkan) sama dengan milik affiliate → tanpa komisi, ditandai di transaksi.
4. Hasilnya **dikunci di transaksi/order saat dibuat** — perubahan cookie setelahnya tidak berpengaruh.

### Komisi
- Dibuat oleh satu fungsi `buatKomisi(transaksi_id)` saat transaksi berubah jadi `lunas` — dari gerbang uji, webhook Midtrans, atau pencatatan manual admin. **Idempoten**: kolom `komisi.transaksi_id` UNIQUE, jadi notifikasi ganda tidak menggandakan komisi.
- Setelan fee **disalin (snapshot)** ke baris komisi: `dasar`, `fee_jenis`, `fee_nilai`. Mengubah fee produk nanti tidak mengubah komisi lama.
- `persen` → `floor(jumlah × nilai / 100)`; `tetap` → `nilai` rupiah.
- **Langganan:** komisi hanya untuk `periode_ke ≤ bulan_berulang` (setelan produk, bawaan global 12). Affiliate terkunci di `langganan` sejak pembayaran pertama; perpanjangan mewarisi affiliate-nya.
- **Masa tahan** (bawaan 3 hari) sebelum bisa ditarik — ruang untuk refund. Status "siap" dihitung dari tanggal (`cair_pada <= NOW()`), **tanpa cron**.
- **Pencairan lebih awal oleh admin:** admin boleh mencairkan komisi yang masih tertahan kapan saja — per baris komisi, atau sekaligus semua komisi tertahan milik satu affiliate — lewat tombol *Cairkan sekarang* di `affiliate-detail.php`. Caranya cukup mengisi `cair_pada = NOW()`, lalu dicatat di `log_aktivitas` beserta siapa dan kapan. Komisi itu langsung masuk saldo siap dan bisa ditarik seperti biasa.
- **Refund:** komisi yang belum ditarik → `dibatalkan`; yang sudah ditarik → baris penyesuaian **negatif** yang memotong saldo berikutnya.

### Penarikan
- Affiliator menarik **seluruh saldo siap** sekaligus, minimal `min_tarik` (bawaan Rp 100.000), satu pengajuan terbuka pada satu waktu.
- Saat diajukan, baris komisi yang ikut **dikunci** (`penarikan_id` diisi) supaya tidak terhitung dua kali. Ditolak → kuncinya dilepas, saldo kembali.
- Admin mentransfer manual, lalu menandai `dibayar` + nomor referensi / bukti. Data rekening **disalin** ke penarikan saat diajukan.

### Setelan yang diatur dari panel
Semua angka di bawah **tidak ditulis di kode**. Nilainya disimpan di tabel `setelan` dan diubah lewat **Panel → Pengaturan → Affiliate**. Angka di kolom *Bawaan* hanya isi awal saat migrasi dijalankan.

| Kolom di halaman Pengaturan | Kunci | Bawaan | Batas yang diterima |
|---|---|---|---|
| Lama cookie link affiliate | `affiliate.cookie_hari` | 10 hari | 1–90 hari |
| Masa tahan komisi | `affiliate.masa_tahan_hari` | 3 hari | 0–60 hari (0 = langsung siap ditarik) |
| Minimal penarikan | `affiliate.min_tarik` | Rp 100.000 | Rp 0 ke atas |
| Bulan komisi berulang (langganan) | `affiliate.bulan_berulang` | 12 bulan | 1–60 bulan |
| Syarat & ketentuan affiliate | `affiliate.syarat` | teks contoh | teks bebas, tampil di form daftar |

Aturan kapan perubahan berlaku, supaya tidak mengejutkan affiliator:

- **Lama cookie** berlaku untuk **klik berikutnya**. Cookie yang sudah tertanam di peramban pembeli tetap memakai umur saat ditanam: tanggal kedaluwarsa ada di dalam cookie itu sendiri dan dilindungi tanda tangan.
- **Masa tahan** berlaku untuk **komisi baru**. `cair_pada` komisi lama tidak dihitung ulang. Kalau ingin mempercepat komisi lama, pakai tombol *Cairkan sekarang*.
- **Minimal penarikan** langsung berlaku untuk pengajuan berikutnya. Pengajuan yang sudah masuk tidak terpengaruh.
- **Bulan berulang** di sini hanya bawaan. Produk yang punya nilai sendiri (`fee_bulan_berulang`) tetap memakai nilainya. Langganan yang sudah berjalan mengikuti nilai yang berlaku saat komisi periodenya dibuat.

Setiap penyimpanan dicatat di `log_aktivitas` beserta nilai lama → baru, misalnya "Masa tahan: 3 → 7 hari".

### Gerbang pembayaran
Antarmuka `Gerbang` di `panel/inc/gerbang.php` dengan dua pengemudi, dipilih oleh `konfig('gerbang')` (bukan setelan di panel — pindah ke uang sungguhan tidak boleh sekadar satu klik):

- **`GerbangUji`** (sekarang): mengarahkan ke `toko/bayar-uji.php?o={kode_order}` berisi tombol *Simulasikan lunas / gagal / kedaluwarsa*, yang memanggil `ubahStatusTransaksi()` — jalur yang **sama persis** dengan webhook Midtrans. Halaman ini 404 bila gerbang bukan `uji`, dan menampilkan pita "MODE UJI".
- **`GerbangMidtrans`** (tahap 6): Snap API — `POST {app}/snap/v1/transactions` (Basic auth server key) → `redirect_url`. Webhook `toko/midtrans.php`: verifikasi `signature_key = sha512(order_id + status_code + gross_amount + server_key)`, lalu **konfirmasi ulang** lewat `GET {api}/v2/{order_id}/status`, cocokkan `gross_amount` dengan jumlah di basis data. Pemetaan: `settlement`, `capture`+`accept` → lunas; `deny`/`cancel` → gagal; `expire` → kedaluwarsa; `refund` → refund.
- Jumlah bayar **selalu diambil dari basis data**, tidak pernah dari peramban.

### Jenis produk
| Jenis | Contoh | Tombol di landing page | Transaksi |
|---|---|---|---|
| `sekali` | Kelas, produk digital | Beli sekarang → checkout | otomatis lewat gerbang |
| `langganan` | Portal Sekolah, Ponpes Manager | Berlangganan → checkout bulan pertama | bulan berikutnya dicatat admin |
| `penawaran` | Jasa aplikasi, paket B2B | Minta demo / penawaran → form | admin catat pembayaran manual |
| `eksternal` | amanafinance Pro | Kunjungi → URL luar + `?ref=KODE` | lewat API konversi (tahap opsional) |

---

## Basis data (tabel & kolom baru)

Uang disimpan sebagai `BIGINT` rupiah bulat. Semua lewat berkas migrasi.

```
setelan          kunci PK · nilai · diperbarui_pada
                 (affiliate.cookie_hari=10, .min_tarik=100000, .masa_tahan_hari=3,
                  .bulan_berulang=12, .syarat=teks S&K)

produk           id · slug UNIQUE · nama · jenis · kelas_id NULL · tagline · ringkas
                 isi · manfaat (satu per baris) · tanya (JSON) · gambar · harga BIGINT NULL
                 periode ('bulan'|NULL) · url_eksternal · label_tombol · status (draf|aktif|arsip)
                 urutan · affiliate_aktif · fee_jenis (persen|tetap) · fee_nilai
                 fee_bulan_berulang NULL · dibuat_pada · diperbarui_pada

affiliate        id · kode UNIQUE · nama · surel UNIQUE · whatsapp · kata_sandi_hash
                 kanal_promosi · status (menunggu|aktif|ditolak|dibekukan)
                 bank_nama · bank_nomor · bank_atas_nama · catatan_admin
                 disetujui_pada · dibuat_pada · terakhir_masuk

affiliate_klik   id · affiliate_id · produk_id NULL · ip_hash · referer · dibuat_pada

langganan        id · produk_id · affiliate_id NULL · pembeli_nama · surel · whatsapp
                 status (aktif|berhenti) · mulai_pada · dibuat_pada

transaksi        id · kode_order UNIQUE (INV-YYMMDD-XXXXXX, dipakai sbg order_id Midtrans)
                 produk_id · affiliate_id NULL · langganan_id NULL · periode_ke
                 order_jasa_id NULL · pembeli_nama · surel · whatsapp · jumlah
                 status (menunggu|lunas|gagal|kedaluwarsa|refund) · gerbang (uji|midtrans|manual)
                 gerbang_ref · metode · beli_sendiri · catatan · dibayar_pada · dibuat_pada

transaksi_riwayat id · transaksi_id · status_lama · status_baru · sumber · payload · dibuat_pada

komisi           id · affiliate_id · transaksi_id UNIQUE NULL · produk_id · jenis (komisi|penyesuaian)
                 dasar · fee_jenis · fee_nilai · jumlah (boleh negatif) · status (tertahan|dibatalkan)
                 cair_pada · penarikan_id NULL · catatan · dibuat_pada

penarikan        id · affiliate_id · jumlah · status (diajukan|dibayar|ditolak)
                 bank_nama · bank_nomor · bank_atas_nama · referensi · catatan
                 diajukan_pada · diproses_pada

migrasi          nama PK · dijalankan_pada

-- ubahan tabel lama
order_jasa       + produk_id NULL · affiliate_id NULL
login_gagal      + ruang VARCHAR(10) DEFAULT 'panel'   (dipakai juga oleh mitra)
```

Kode affiliate: 6 karakter dari huruf/angka tanpa yang mirip (`ABCDEFGHJKMNPQRSTUVWXYZ23456789`). Admin boleh menggantinya dengan kode pilihan (4–16 karakter) **saat menyetujui**; setelah aktif dikunci, supaya link yang sudah tersebar tidak mati.

---

## Berkas

### Pondasi bersama (dipecah dari yang sudah ada)
- `panel/inc/inti.php` **baru** — dipindah dari `awal.php`: konfig, `db()`, `q()`, `ambil*()`, CSRF, `e()`, `masukan()`, `rupiah()`, `waktuIndo()`, `slugkan()`, `catatLog()`, plus `jawabJson()` dan `batasLaju()` yang sekarang tertanam di `api-pesan.php`.
- `panel/inc/awal.php` — tinggal `require inti.php` + sesi panel + `wajibMasuk()`. Perilaku panel tidak berubah.
- `panel/inc/affiliate.php` **baru** — `setelan()`, `kodeBaru()`, `tanamCookie()/bacaCookie()`, `atribusi()`, `buatKomisi()`, `batalkanKomisi()`, `saldoAffiliate()`, `ajukanPenarikan()`.
- `panel/inc/gerbang.php` **baru** — antarmuka `Gerbang`, `GerbangUji`, `GerbangMidtrans`, `buatTransaksi()`, `ubahStatusTransaksi()` (satu-satunya tempat status transaksi berubah).
- `panel/migrasi.php` + `panel/migrasi/NNN_*.sql` **baru** — `pasang.php` menolak jalan setelah ada akun, jadi tabel baru butuh pelari migrasi (halaman admin, tombol "Jalankan", tercatat di tabel `migrasi`). `pasang.php` ikut menjalankannya untuk pemasangan baru. `.sql` sudah terlarang diunduh oleh `panel/.htaccess`.
- `panel/inc/konfig.contoh.php` — tambah `rahasia_ref`, `gerbang => 'uji'`, `midtrans => [server_key, client_key, produksi => false]`.

### Panel admin (menu baru di `panel/inc/kepala.php`)
- **Produk** — `produk.php`, `produk-edit.php`: isi landing page, harga, jenis, tautan ke kelas, dan kotak *Setelan affiliate* (aktif, persen/tetap, nilai, bulan berulang). `terbitkan.php` ditambah menulis `data/produk.json`.
- **Affiliate** — `affiliate.php` (saring per status, jumlah menunggu di menu), `affiliate-detail.php` (setujui + tetapkan kode, tolak, bekukan, reset sandi, statistik, daftar komisi & penarikan, tombol *Cairkan sekarang* per komisi atau untuk semua komisi tertahan).
- **Transaksi** — `transaksi.php`, `transaksi-detail.php`: daftar, riwayat status, **catat pembayaran manual** (termasuk perpanjangan langganan bulan ke-n), tandai refund. `order-detail.php` dapat tombol *Catat pembayaran* yang mengisi produk & affiliate dari order.
- **Penarikan** — `penarikan.php`: antrean diajukan → tandai dibayar (referensi transfer) / tolak (alasan).
- `pengaturan.php` — bagian *Affiliate*: kolom-kolom di tabel *Setelan yang diatur dari panel*, dengan validasi batas dan jejak nilai lama → baru.
- `index.php` (Ringkasan) — kartu: affiliate menunggu, penarikan diajukan, penjualan & komisi bulan ini.
- `api-pesan.php` — baca cookie `inv_ref` + `produk`, isi `order_jasa.produk_id/affiliate_id`.

### Portal affiliator — `mitra/` (baru, `invishar.com/mitra/`)
Sesi terpisah (`session_name('mitrainvishar')`), tabel pengguna terpisah (`affiliate`); tampilan memakai token warna dan font situs.
- `daftar.php` — form + setuju S&K; honeypot dan batas per IP seperti `api-pesan.php`. Hasil: status `menunggu`.
- `masuk.php`, `keluar.php` — dibatasi lewat `login_gagal` (`ruang='mitra'`). Akun belum disetujui mendapat pesan "menunggu persetujuan", bukan dashboard.
- `index.php` — klik 30 hari, pembeli, konversi, komisi tertahan, **saldo siap**, total dicairkan.
- `tautan.php` — tiap produk yang affiliate-aktif: link pribadi + tombol salin, besaran komisi, statistik per produk.
- `penghasilan.php` — tabel komisi: tanggal, produk, nilai transaksi, komisi, status, tanggal cair. Nama pembeli disamarkan (`A*** S***`).
- `penarikan.php` — ajukan (bila saldo ≥ minimum dan tak ada yang terbuka) + riwayat.
- `profil.php` — rekening bank, WhatsApp, ganti sandi.

### Publik tanpa login — `toko/` (baru)
- `r.php` — catat klik, tanam cookie, alihkan. Kode tak dikenal/tidak aktif → tetap dialihkan tanpa cookie (link lama tidak pernah berujung error).
- `checkout.php` — form pembeli (nama, surel, WA) → `buatTransaksi()` → gerbang. Batas per IP.
- `bayar-uji.php` — halaman simulasi pembayaran (hanya saat gerbang `uji`).
- `midtrans.php` — webhook (tahap 6).
- `selesai.php?o=` — status pembayaran & terima kasih (juga tujuan *finish* Midtrans).

### Situs statis — `site/`
- `produk.html` + `js/produk.js` + `css/produk.css` — satu templat untuk semua produk; slug diambil dari `location.pathname`; isi dari `data/produk.json`. Tombol menyesuaikan jenis produk; produk `penawaran` memakai form yang dikirim ke `api-pesan.php`. **Jalur aset ditulis absolut** (`/css/...`) karena halaman ini dibuka di `/p/{slug}/`. Ikuti aturan penanda versi `?v=` dari `site/README.md`.
- `.htaccess` — dua aturan tulis ulang: `^r/([A-Za-z0-9]{4,16})(?:/([a-z0-9-]+))?/?$ → toko/r.php?kode=$1&p=$2` dan `^p/([a-z0-9-]+)/?$ → produk.html`.
- (opsional) tombol katalog di `index.html` diarahkan ke `/p/{slug}`.

### Deploy & dokumen
- `.cpanel.yml` — tambah salin `mitra/` → `~/public_html/mitra/` dan `toko/` → `~/public_html/toko/`, masing-masing dengan `.htaccess` yang melarang `inc/`.
- `AFFILIATE.md` baru — cara kerja, setelan, dan panduan admin menyetujui & membayar.

---

## Tahapan (tiap tahap bisa di-deploy sendiri)

| Tahap | Isi | Hasil yang bisa dicoba |
|---|---|---|
| 0 | Pecah `inti.php`, pelari migrasi, tabel `setelan` | Panel lama tetap jalan normal |
| 1 | Menu **Produk** + landing page `/p/{slug}` | Tiap produk punya halaman sendiri |
| 2 | Pendaftaran + persetujuan + portal mitra (link, profil) | Affiliator daftar, disetujui, melihat link-nya |
| 3 | `/r/…` + cookie + atribusi di form penawaran | Klik tercatat; order dari link membawa affiliate |
| 4 | Transaksi + checkout + **gerbang uji** + komisi + halaman penghasilan | Beli simulasi → komisi muncul di dashboard |
| 5 | Penarikan (mitra + panel) | Ajukan → admin bayar → saldo kembali nol |
| 6 | **Midtrans** Snap + webhook, uji di sandbox, lalu produksi | Pembayaran sungguhan |
| 7 | Opsional: API konversi amanafinance, notifikasi surel, lupa sandi mitra | — |

---

## Di luar cakupan & risiko yang perlu disadari

- **Isi kelas sekarang terbuka untuk siapa saja** — `materi.html` memutar video tanpa login. Checkout akan menerima uang untuk sesuatu yang sebenarnya gratis diakses. Mengunci materi berbayar adalah proyek tersendiri; sampai itu dibuat, pengiriman akses setelah lunas dilakukan manual (admin melihat transaksi lunas di panel).
- **amanafinance** perlu diubah di aplikasinya sendiri untuk melaporkan pendaftar/Pro; tahap 7 hanya menyiapkan endpoint bertanda tangan HMAC di sisi Invishar.
- Perpanjangan langganan lewat Midtrans otomatis (*recurring*) tidak termasuk; perpanjangan dicatat admin.
- Kalau panel pindah ke `panel.invishar.com`, `api-pesan.php` jadi lintas asal: form harus memakai `credentials: 'include'` dan server mengirim `Access-Control-Allow-Credentials` supaya cookie ikut. Cookie sudah ber-`Domain=invishar.com`, jadi tetap terbaca.
- Pajak (PPh 21/23 atas komisi) tidak dihitung otomatis; kolom `catatan` penarikan bisa dipakai. Perlu dicek ke konsultan pajak bila volume membesar.

---

## Verifikasi

Tidak ada PHP lokal, jadi pemeriksaan sintaks dilakukan di server setelah deploy:
```bash
ssh -i ~/.ssh/invishar_deploy -p 64000 invishar@girona.id.rapidplex.com \
  'for f in $(find ~/public_html/panel ~/public_html/mitra ~/public_html/toko -name "*.php"); do php -l "$f" | grep -v "No syntax errors"; done'
```

**Skenario ujung-ke-ujung (gerbang `uji`, di produksi):**
1. Panel → Migrasi → jalankan; semua tabel baru ada.
2. Buat produk `kelas-dashboard` (sekali, Rp 249.000, affiliate 20%) dan `ponpes-manager` (langganan, Rp 500.000/bln, tetap Rp 100.000, 12 bulan). Terbitkan → `/p/kelas-dashboard` tampil.
3. Daftar di `/mitra/daftar` → login ditolak "menunggu" → setujui di panel dengan kode `UJI01` → login berhasil, `tautan.php` menampilkan 2 link.
4. Jendela penyamaran: buka `/r/UJI01/kelas-dashboard` → dialihkan ke `/p/kelas-dashboard`, cookie `inv_ref` ada (DevTools, HttpOnly, 10 hari), `affiliate_klik` +1.
5. Beli → `bayar-uji` → *Simulasikan lunas* → transaksi `lunas` dengan affiliate `UJI01`; komisi Rp 49.800 berstatus tertahan, tampil di `penghasilan.php`.
6. Muat ulang `bayar-uji` dan kirim *lunas* lagi → **tetap satu** komisi.
7. Komisi masih tertahan (3 hari) → di panel tekan *Cairkan sekarang* → saldo siap Rp 49.800 saat itu juga, tercatat di jejak perubahan; setel min tarik Rp 10.000 → ajukan → panel tandai dibayar → saldo 0, riwayat benar.
8. **Beli-sendiri:** beli dengan surel milik affiliate → transaksi lunas, **tanpa** komisi, `beli_sendiri=1`.
9. **Langganan:** checkout Ponpes → catat manual periode 2…13 di panel → komisi hanya 12 baris (periode 13 tidak).
10. **Refund** transaksi yang komisinya sudah ditarik → muncul penyesuaian −Rp 49.800, saldo negatif terbawa ke berikutnya.
11. **Form penawaran** dari `/p/…` setelah klik link → `order_jasa.affiliate_id` terisi → *Catat pembayaran* → komisi.
12. Kode tak dikenal `/r/ZZZZ/kelas-dashboard` → tetap sampai ke landing page, tanpa cookie, tanpa error.

**Midtrans (tahap 6, sandbox):**
- Isi server/client key sandbox, `gerbang => 'midtrans'`, set Notification URL `https://invishar.com/toko/midtrans.php` di dashboard Midtrans.
- Bayar lewat simulator sandbox Midtrans → webhook masuk → transaksi lunas → komisi.
- `curl` webhook palsu dengan `signature_key` salah → 403, status tidak berubah.
- Kirim ulang notifikasi yang sama dari dashboard → tidak ada komisi ganda.
- `gross_amount` diubah di payload palsu bertanda tangan benar → ditolak karena tidak cocok dengan hasil `GET /v2/{order_id}/status`.

**Pemeriksaan pembukuan** (phpMyAdmin), harus selalu benar:
`SUM(komisi.jumlah WHERE penarikan_id IS NOT NULL)` = `SUM(penarikan.jumlah WHERE status IN ('diajukan','dibayar'))`.
