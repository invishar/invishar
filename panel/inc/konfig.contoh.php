<?php
/* =============================================================================
   Contoh konfigurasi panel.

   Salin berkas ini menjadi `konfig.php` di folder yang sama, lalu isi sesuai
   server. `konfig.php` TIDAK ikut masuk Git (lihat .gitignore) karena berisi
   kata sandi basis data.
   ============================================================================= */
return [
    // Basis data MySQL dari cPanel → MySQL Databases.
    'db' => [
        'host'     => 'localhost',
        'nama'     => 'invishar_panel',
        'pengguna' => 'invishar_panel',
        'sandi'    => '',
        'charset'  => 'utf8mb4',
    ],

    // Folder tujuan berkas terbitan, dibaca oleh invishar.com.
    // Harus berada DI LUAR folder yang ditimpa deploy — `data/` di public_html
    // aman karena tidak pernah ikut masuk Git.
    'situs_data' => '/home/invishar/public_html/data',

    // Alamat yang boleh mengirim pesan ke api-pesan.php (form kontak).
    'asal_diizinkan' => [
        'https://invishar.com',
        'https://www.invishar.com',
    ],

    // Zona waktu untuk seluruh pencatatan waktu di panel.
    'zona_waktu' => 'Asia/Jakarta',

    // Bantuan AI untuk mengisi kolom kelas (9router, sesuai OpenAI API).
    // Kunci hanya dipakai di sisi server — peramban tidak pernah melihatnya.
    // Kosongkan 'kunci' untuk mematikan seluruh tombol bantuan AI.
    'ai' => [
        'endpoint'  => 'https://r62dmm3.abc-tunnel.us/v1',
        'kunci'     => '',
        'model'     => 'amana',
        'batas_detik' => 90,
    ],

    // --- Sistem affiliate & pembayaran (lihat AFFILIATE.md) ---------------
    // Semua di bawah ini OPSIONAL. Gerbang & kunci Midtrans sekarang diatur di
    // panel → Setting → Pembayaran; isian di sini hanya dipakai selama halaman
    // itu belum pernah disimpan. Tanpa isian, checkout memakai mode uji.

    // 'uji'      : pembayaran simulasi, belum ada uang sungguhan.
    // 'midtrans' : Midtrans Snap — isi kunci di bawah dulu.
    'gerbang' => 'uji',

    'midtrans' => [
        'server_key' => '',      // Settings → Access Keys di dashboard Midtrans
        'client_key' => '',
        'produksi'   => false,   // true hanya setelah uji sandbox beres
    ],

    // Alamat situs untuk link affiliate dan tujuan balik dari Midtrans.
    // 'situs_url' => 'https://invishar.com',
];
