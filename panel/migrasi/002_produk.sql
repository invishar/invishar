-- =============================================================================
-- 002 — Produk: apa pun yang bisa dijual atau dipromosikan affiliate.
--
-- jenis:
--   sekali     dibayar sekali lewat checkout (kelas, produk digital)
--   langganan  dibayar per bulan; bulan pertama lewat checkout
--   penawaran  harga lewat percakapan; masuk sebagai order jasa
--   eksternal  dijual di aplikasi lain (mis. amanafinance)
--
-- Uang selalu rupiah bulat (BIGINT). fee_nilai DECIMAL supaya persen boleh
-- berkoma (mis. 12,5%).
-- =============================================================================

CREATE TABLE IF NOT EXISTS produk (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  slug               VARCHAR(80)   NOT NULL UNIQUE,
  nama               VARCHAR(160)  NOT NULL,
  jenis              VARCHAR(20)   NOT NULL DEFAULT 'sekali',
  kelas_id           INT           NULL,
  tagline            VARCHAR(200)  NULL,
  ringkas            TEXT          NULL,
  isi                MEDIUMTEXT    NULL,
  manfaat            TEXT          NULL,          -- satu butir per baris
  tanya              MEDIUMTEXT    NULL,          -- JSON [{q, a}]
  gambar             VARCHAR(160)  NULL,          -- berkas di data/produk
  harga              BIGINT        NULL,
  url_eksternal      VARCHAR(255)  NULL,
  label_tombol       VARCHAR(40)   NULL,
  status             VARCHAR(10)   NOT NULL DEFAULT 'draf',   -- draf | aktif | arsip
  urutan             INT           NOT NULL DEFAULT 0,
  affiliate_aktif    TINYINT(1)    NOT NULL DEFAULT 0,
  fee_jenis          VARCHAR(10)   NOT NULL DEFAULT 'persen', -- persen | tetap
  fee_nilai          DECIMAL(14,2) NOT NULL DEFAULT 0,
  fee_bulan_berulang INT           NULL,          -- kosong = ikut setelan umum
  dibuat_pada        DATETIME      NOT NULL,
  diperbarui_pada    DATETIME      NOT NULL,
  INDEX (status, urutan),
  CONSTRAINT fk_produk_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
