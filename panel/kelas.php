<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
wajibMasuk();

/* Daftar kelas sekarang ada di menu Produk (kategori Kelas); kelas baru
   dibuat lewat Produk → Tambah produk → Kelas. Alamat lama tetap berlaku. */
pergi(tautan('produk') . '?kategori=kelas');
