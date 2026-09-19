<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';

$_SESSION = [];
session_destroy();

session_start();
session_regenerate_id(true);
pesan('Anda sudah keluar.');
pergi(tautan('masuk'));
