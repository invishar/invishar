-- =============================================================================
-- 001 — Setelan yang bisa diubah dari halaman Pengaturan.
-- Hanya menyimpan nilai yang pernah diubah; nilai bawaan ada di
-- SETELAN_BAWAAN (panel/inc/inti.php).
-- =============================================================================

CREATE TABLE IF NOT EXISTS setelan (
  kunci           VARCHAR(60) NOT NULL PRIMARY KEY,
  nilai           TEXT        NOT NULL,
  diperbarui_pada DATETIME    NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
