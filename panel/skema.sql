-- =============================================================================
-- Skema basis data Panel Invishar
-- Dijalankan otomatis oleh pasang.php; disimpan di sini sebagai rujukan dan
-- untuk dijalankan manual lewat phpMyAdmin bila perlu.
-- =============================================================================

CREATE TABLE IF NOT EXISTS pengguna (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  nama            VARCHAR(80)  NOT NULL,
  surel           VARCHAR(160) NOT NULL UNIQUE,
  kata_sandi_hash VARCHAR(255) NOT NULL,
  dibuat_pada     DATETIME     NOT NULL,
  terakhir_masuk  DATETIME     NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pembatas percobaan masuk, supaya kata sandi tidak bisa ditebak berulang.
CREATE TABLE IF NOT EXISTS login_gagal (
  id      INT AUTO_INCREMENT PRIMARY KEY,
  ip      VARCHAR(45) NOT NULL,
  waktu   DATETIME    NOT NULL,
  INDEX (ip, waktu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------- Order jasa
CREATE TABLE IF NOT EXISTS order_jasa (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  nama            VARCHAR(120) NOT NULL,
  lembaga         VARCHAR(120) NULL,
  surel           VARCHAR(160) NULL,
  whatsapp        VARCHAR(40)  NULL,
  kebutuhan       TEXT         NOT NULL,
  sumber          VARCHAR(40)  NOT NULL DEFAULT 'form',
  ip              VARCHAR(45)  NULL,
  status          VARCHAR(20)  NOT NULL DEFAULT 'baru',
  nilai           BIGINT       NULL,
  catatan         TEXT         NULL,
  dibuat_pada     DATETIME     NOT NULL,
  diperbarui_pada DATETIME     NOT NULL,
  INDEX (status),
  INDEX (dibuat_pada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_riwayat (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  order_id    INT         NOT NULL,
  status_lama VARCHAR(20) NULL,
  status_baru VARCHAR(20) NOT NULL,
  catatan     TEXT        NULL,
  dibuat_pada DATETIME    NOT NULL,
  FOREIGN KEY (order_id) REFERENCES order_jasa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------- Kelas
CREATE TABLE IF NOT EXISTS kelas (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  slug            VARCHAR(80)  NOT NULL UNIQUE,
  judul           VARCHAR(160) NOT NULL,
  kategori        VARCHAR(60)  NOT NULL DEFAULT 'Dasar',
  ringkas         TEXT         NOT NULL,
  level           VARCHAR(60)  NOT NULL DEFAULT 'Pemula',
  harga           VARCHAR(40)  NOT NULL DEFAULT 'Gratis',
  status          VARCHAR(20)  NOT NULL DEFAULT 'Segera',
  ikon            VARCHAR(20)  NOT NULL DEFAULT 'kilau',
  urutan          INT          NOT NULL DEFAULT 0,
  -- Bagian detail yang hanya tampil di halaman kelas, disimpan sebagai JSON:
  -- kicker, bahasa, akses, pengajar, ikhtisar, sumber, tanya.
  detail          MEDIUMTEXT   NULL,
  diperbarui_pada DATETIME     NOT NULL,
  INDEX (urutan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS modul (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  kelas_id INT          NOT NULL,
  judul    VARCHAR(160) NOT NULL,
  urutan   INT          NOT NULL DEFAULT 0,
  FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
  INDEX (kelas_id, urutan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS materi (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  modul_id   INT          NOT NULL,
  kode       VARCHAR(20)  NOT NULL,   -- dipakai di URL: materi.html?m=m01
  judul      VARCHAR(160) NOT NULL,
  durasi     VARCHAR(20)  NOT NULL DEFAULT '10 mnt',
  youtube_id VARCHAR(24)  NOT NULL,
  ringkas    TEXT         NULL,
  poin       TEXT         NULL,       -- satu poin per baris
  urutan     INT          NOT NULL DEFAULT 0,
  FOREIGN KEY (modul_id) REFERENCES modul(id) ON DELETE CASCADE,
  INDEX (modul_id, urutan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------- Inventaris
CREATE TABLE IF NOT EXISTS aset (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  nama         VARCHAR(120) NOT NULL,
  kategori     VARCHAR(60)  NOT NULL DEFAULT 'Lainnya',
  nomor_seri   VARCHAR(80)  NULL,
  tanggal_beli DATE         NULL,
  harga_beli   BIGINT       NULL,
  kondisi      VARCHAR(20)  NOT NULL DEFAULT 'Baik',
  pemegang     VARCHAR(80)  NULL,
  catatan      TEXT         NULL,
  dibuat_pada  DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Aplikasi
CREATE TABLE IF NOT EXISTS aplikasi (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  nama       VARCHAR(80)  NOT NULL,
  keterangan VARCHAR(200) NULL,
  url_admin  VARCHAR(255) NULL,
  url_situs  VARCHAR(255) NULL,
  urutan     INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------- Jejak
CREATE TABLE IF NOT EXISTS log_aktivitas (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  pengguna_id INT          NULL,
  aksi        VARCHAR(80)  NOT NULL,
  objek       VARCHAR(160) NULL,
  dibuat_pada DATETIME     NOT NULL,
  INDEX (dibuat_pada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
