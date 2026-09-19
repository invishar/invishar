-- =============================================================================
-- 004 — Transaksi, langganan, komisi, dan penarikan.
--
-- Aturan uang yang dijaga oleh struktur tabel ini:
--   * satu transaksi paling banyak punya satu komisi dan satu penyesuaian
--     (UNIQUE transaksi_id + jenis) — notifikasi ganda tidak menggandakan komisi.
--   * data yang sudah menyangkut uang tidak bisa terhapus diam-diam
--     (FOREIGN KEY ... RESTRICT).
-- =============================================================================

CREATE TABLE IF NOT EXISTS penarikan (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  affiliate_id   INT          NOT NULL,
  jumlah         BIGINT       NOT NULL DEFAULT 0,
  status         VARCHAR(10)  NOT NULL DEFAULT 'diajukan',  -- diajukan | dibayar | ditolak
  bank_nama      VARCHAR(60)  NOT NULL,
  bank_nomor     VARCHAR(40)  NOT NULL,
  bank_atas_nama VARCHAR(120) NOT NULL,
  referensi      VARCHAR(120) NULL,
  catatan        TEXT         NULL,
  diajukan_pada  DATETIME     NOT NULL,
  diproses_pada  DATETIME     NULL,
  INDEX (status),
  INDEX (affiliate_id),
  CONSTRAINT fk_tarik_affiliate FOREIGN KEY (affiliate_id) REFERENCES affiliate(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS langganan (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  produk_id    INT          NOT NULL,
  affiliate_id INT          NULL,
  pembeli_nama VARCHAR(120) NOT NULL,
  surel        VARCHAR(160) NULL,
  whatsapp     VARCHAR(40)  NULL,
  status       VARCHAR(10)  NOT NULL DEFAULT 'aktif',       -- aktif | berhenti
  mulai_pada   DATETIME     NOT NULL,
  dibuat_pada  DATETIME     NOT NULL,
  INDEX (produk_id),
  CONSTRAINT fk_lgn_produk    FOREIGN KEY (produk_id)    REFERENCES produk(id)    ON DELETE RESTRICT,
  CONSTRAINT fk_lgn_affiliate FOREIGN KEY (affiliate_id) REFERENCES affiliate(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transaksi (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  kode_order      VARCHAR(30)  NOT NULL UNIQUE,   -- dipakai juga sebagai order_id Midtrans
  produk_id       INT          NOT NULL,
  affiliate_id    INT          NULL,
  beli_sendiri    TINYINT(1)   NOT NULL DEFAULT 0,
  langganan_id    INT          NULL,
  periode_ke      INT          NOT NULL DEFAULT 1,
  order_jasa_id   INT          NULL,
  pembeli_nama    VARCHAR(120) NOT NULL,
  surel           VARCHAR(160) NULL,
  whatsapp        VARCHAR(40)  NULL,
  jumlah          BIGINT       NOT NULL,
  status          VARCHAR(12)  NOT NULL DEFAULT 'menunggu', -- menunggu | lunas | gagal | kedaluwarsa | refund
  gerbang         VARCHAR(12)  NOT NULL,                    -- uji | midtrans | manual
  gerbang_ref     VARCHAR(80)  NULL,
  gerbang_url     VARCHAR(255) NULL,
  metode          VARCHAR(40)  NULL,
  catatan         TEXT         NULL,
  ip              VARCHAR(45)  NULL,
  dibayar_pada    DATETIME     NULL,
  dibuat_pada     DATETIME     NOT NULL,
  diperbarui_pada DATETIME     NOT NULL,
  INDEX (status),
  INDEX (affiliate_id),
  INDEX (dibuat_pada),
  INDEX (ip, dibuat_pada),
  CONSTRAINT fk_trx_produk    FOREIGN KEY (produk_id)     REFERENCES produk(id)     ON DELETE RESTRICT,
  CONSTRAINT fk_trx_affiliate FOREIGN KEY (affiliate_id)  REFERENCES affiliate(id)  ON DELETE RESTRICT,
  CONSTRAINT fk_trx_langganan FOREIGN KEY (langganan_id)  REFERENCES langganan(id)  ON DELETE RESTRICT,
  CONSTRAINT fk_trx_order     FOREIGN KEY (order_jasa_id) REFERENCES order_jasa(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transaksi_riwayat (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  transaksi_id INT          NOT NULL,
  status_lama  VARCHAR(12)  NULL,
  status_baru  VARCHAR(12)  NOT NULL,
  sumber       VARCHAR(20)  NOT NULL,     -- checkout | uji | midtrans | admin
  catatan      VARCHAR(255) NULL,
  payload      MEDIUMTEXT   NULL,
  dibuat_pada  DATETIME     NOT NULL,
  INDEX (transaksi_id),
  CONSTRAINT fk_riw_transaksi FOREIGN KEY (transaksi_id) REFERENCES transaksi(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- status: berlaku | batal. Keadaan yang tampil ke affiliator (Tertahan, Siap
-- ditarik, Diproses, Dicairkan) diturunkan dari cair_pada dan penarikan_id,
-- jadi tidak butuh cron untuk berpindah dari tertahan ke siap.
CREATE TABLE IF NOT EXISTS komisi (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  affiliate_id    INT           NOT NULL,
  transaksi_id    INT           NULL,
  produk_id       INT           NULL,
  jenis           VARCHAR(12)   NOT NULL DEFAULT 'komisi',  -- komisi | penyesuaian
  dasar           BIGINT        NOT NULL DEFAULT 0,
  fee_jenis       VARCHAR(10)   NULL,
  fee_nilai       DECIMAL(14,2) NULL,
  periode_ke      INT           NULL,
  jumlah          BIGINT        NOT NULL,                   -- penyesuaian bernilai negatif
  status          VARCHAR(10)   NOT NULL DEFAULT 'berlaku',
  cair_pada       DATETIME      NOT NULL,
  dipercepat_pada DATETIME      NULL,
  penarikan_id    INT           NULL,
  catatan         VARCHAR(255)  NULL,
  dibuat_pada     DATETIME      NOT NULL,
  UNIQUE KEY uniq_komisi_transaksi (transaksi_id, jenis),
  INDEX (affiliate_id, status, cair_pada),
  INDEX (penarikan_id),
  CONSTRAINT fk_kms_affiliate FOREIGN KEY (affiliate_id) REFERENCES affiliate(id) ON DELETE RESTRICT,
  CONSTRAINT fk_kms_transaksi FOREIGN KEY (transaksi_id) REFERENCES transaksi(id) ON DELETE RESTRICT,
  CONSTRAINT fk_kms_produk    FOREIGN KEY (produk_id)    REFERENCES produk(id)    ON DELETE SET NULL,
  CONSTRAINT fk_kms_penarikan FOREIGN KEY (penarikan_id) REFERENCES penarikan(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order jasa ikut mencatat produk dan affiliate yang membawanya.
ALTER TABLE order_jasa ADD COLUMN IF NOT EXISTS produk_id INT NULL AFTER sumber;
ALTER TABLE order_jasa ADD COLUMN IF NOT EXISTS affiliate_id INT NULL AFTER produk_id;
ALTER TABLE order_jasa ADD INDEX IF NOT EXISTS idx_order_affiliate (affiliate_id);
