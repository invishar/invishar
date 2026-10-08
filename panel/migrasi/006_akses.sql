-- 006_akses.sql — Pengiriman akses otomatis untuk produk digital & kelas.
--
-- Masalah yang dipecahkan: setelah pembayaran lunas, pembeli hanya menerima
-- pesan "kami segera menghubungi via WhatsApp" — produk digital dikirim manual
-- dan halaman materi kelas bisa dibuka siapa saja tanpa membeli.
--
-- Yang ditambahkan (hanya menambah, tidak menghapus/mengubah data lama):
--   produk.akses_tautan   tautan unduhan/halaman akses produk digital
--   produk.akses_catatan  instruksi yang tampil ke pembeli setelah lunas
--   transaksi.akses_token token unik per transaksi lunas → /toko/akses.php?t=...
--   peserta_kelas         pendaftaran otomatis pembeli ke kelas saat lunas

ALTER TABLE produk
  ADD COLUMN IF NOT EXISTS akses_tautan VARCHAR(255) NULL AFTER url_eksternal,
  ADD COLUMN IF NOT EXISTS akses_catatan TEXT NULL AFTER akses_tautan;

ALTER TABLE transaksi
  ADD COLUMN IF NOT EXISTS akses_token VARCHAR(64) NULL AFTER gerbang_ref;

CREATE UNIQUE INDEX IF NOT EXISTS uniq_trx_akses_token ON transaksi (akses_token);

CREATE TABLE IF NOT EXISTS peserta_kelas (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  transaksi_id INT      NOT NULL,
  kelas_id     INT      NOT NULL,
  dibuat_pada  DATETIME NOT NULL,
  UNIQUE KEY uniq_peserta_transaksi (transaksi_id),
  INDEX (kelas_id),
  CONSTRAINT fk_peserta_transaksi FOREIGN KEY (transaksi_id) REFERENCES transaksi(id) ON DELETE CASCADE,
  CONSTRAINT fk_peserta_kelas     FOREIGN KEY (kelas_id)     REFERENCES kelas(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
