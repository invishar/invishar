-- =============================================================================
-- 003 — Affiliator, klik link, dan pembatas login portal mitra.
--
-- kode baru diisi saat admin menyetujui pendaftaran; selama menunggu masih
-- kosong (UNIQUE tetap membolehkan banyak NULL).
-- status: menunggu | aktif | dibekukan | ditolak
-- =============================================================================

CREATE TABLE IF NOT EXISTS affiliate (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  kode            VARCHAR(16)  NULL UNIQUE,
  nama            VARCHAR(120) NOT NULL,
  surel           VARCHAR(160) NOT NULL UNIQUE,
  whatsapp        VARCHAR(40)  NOT NULL,
  kata_sandi_hash VARCHAR(255) NOT NULL,
  kanal_promosi   TEXT         NOT NULL,
  status          VARCHAR(12)  NOT NULL DEFAULT 'menunggu',
  bank_nama       VARCHAR(60)  NULL,
  bank_nomor      VARCHAR(40)  NULL,
  bank_atas_nama  VARCHAR(120) NULL,
  catatan_admin   TEXT         NULL,
  ip_daftar       VARCHAR(45)  NULL,
  disetujui_pada  DATETIME     NULL,
  dibuat_pada     DATETIME     NOT NULL,
  terakhir_masuk  DATETIME     NULL,
  INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Klik pada link affiliate. IP disimpan dalam bentuk sidik (hash), bukan
-- alamat aslinya.
CREATE TABLE IF NOT EXISTS affiliate_klik (
  id           BIGINT AUTO_INCREMENT PRIMARY KEY,
  affiliate_id INT          NOT NULL,
  produk_id    INT          NULL,
  ip_hash      CHAR(64)     NOT NULL,
  referer      VARCHAR(255) NULL,
  dibuat_pada  DATETIME     NOT NULL,
  INDEX (affiliate_id, dibuat_pada),
  INDEX (affiliate_id, produk_id, ip_hash, dibuat_pada),
  CONSTRAINT fk_klik_affiliate FOREIGN KEY (affiliate_id) REFERENCES affiliate(id) ON DELETE CASCADE,
  CONSTRAINT fk_klik_produk    FOREIGN KEY (produk_id)    REFERENCES produk(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Terpisah dari login_gagal milik panel, supaya percobaan di portal mitra
-- tidak pernah mengunci admin keluar dari panel.
CREATE TABLE IF NOT EXISTS mitra_login_gagal (
  id    INT AUTO_INCREMENT PRIMARY KEY,
  ip    VARCHAR(45) NOT NULL,
  waktu DATETIME    NOT NULL,
  INDEX (ip, waktu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
