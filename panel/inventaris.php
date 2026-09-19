<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
wajibMasuk();

/* Inventaris dipecah menjadi Akun dan Gadget. Alamat lama (termasuk
   /inventaris/ID) diarahkan ke Gadget. */
$id = (int) ($_GET['sunting'] ?? 0);
pergi($id > 0 ? tautan('gadget/' . $id) : tautan('gadget'));
