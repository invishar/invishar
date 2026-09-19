-- =============================================================================
-- 005 — Susunan panel baru.
--
--   produk     + kategori (produk | jasa | kelas), link panel admin aplikasi,
--                dan landing page custom (HTML unggahan)
--   transaksi  + status proses order (baru | diproses | selesai | batal),
--                terpisah dari status bayar
--   akun       tabel baru: akun layanan kantor, TANPA kata sandi
--
-- Hanya menambah kolom/tabel. Data lama tidak dihapus.
-- =============================================================================

ALTER TABLE produk ADD COLUMN IF NOT EXISTS kategori VARCHAR(10) NOT NULL DEFAULT 'produk' AFTER jenis;
ALTER TABLE produk ADD COLUMN IF NOT EXISTS url_admin VARCHAR(255) NULL AFTER url_eksternal;
ALTER TABLE produk ADD COLUMN IF NOT EXISTS lp_mode VARCHAR(10) NOT NULL DEFAULT 'bawaan' AFTER label_tombol;
ALTER TABLE produk ADD COLUMN IF NOT EXISTS lp_berkas VARCHAR(40) NULL AFTER lp_mode;
ALTER TABLE produk ADD COLUMN IF NOT EXISTS lp_diunggah_pada DATETIME NULL AFTER lp_berkas;
ALTER TABLE produk ADD INDEX IF NOT EXISTS idx_produk_kategori (kategori, status);

-- Kategori untuk produk yang sudah ada: tertaut kelas = kelas, lewat penawaran = jasa.
UPDATE produk SET kategori = 'kelas' WHERE kelas_id IS NOT NULL;
UPDATE produk SET kategori = 'jasa' WHERE kelas_id IS NULL AND jenis = 'penawaran';

ALTER TABLE transaksi ADD COLUMN IF NOT EXISTS status_proses VARCHAR(10) NOT NULL DEFAULT 'baru' AFTER status;
ALTER TABLE transaksi ADD COLUMN IF NOT EXISTS proses_pada DATETIME NULL AFTER status_proses;
ALTER TABLE transaksi ADD INDEX IF NOT EXISTS idx_trx_proses (status_proses, status);

-- Transaksi lama yang gagal/kedaluwarsa/refund tidak perlu diproses lagi.
-- Pembayaran order jasa dan perpanjangan langganan bukan pesanan baru.
UPDATE transaksi SET status_proses = 'batal' WHERE status IN ('gagal', 'kedaluwarsa', 'refund');
UPDATE transaksi SET status_proses = 'selesai' WHERE status = 'lunas' AND (order_jasa_id IS NOT NULL OR periode_ke > 1);

CREATE TABLE IF NOT EXISTS akun (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  layanan         VARCHAR(120) NOT NULL,
  login           VARCHAR(160) NULL,
  url             VARCHAR(255) NULL,
  pemilik         VARCHAR(80)  NULL,
  biaya           BIGINT       NULL,
  siklus          VARCHAR(10)  NOT NULL DEFAULT 'bulanan',
  perpanjang_pada DATE         NULL,
  catatan         TEXT         NULL,
  dibuat_pada     DATETIME     NOT NULL,
  diperbarui_pada DATETIME     NOT NULL,
  INDEX (perpanjang_pada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
