/* =============================================================================
   Invishar — daftar kelas untuk galeri (kelas.html)

   Sementara ini isinya contoh: baru satu kelas yang materinya benar-benar ada
   (js/course-data.js), sisanya dummy supaya tata letak galeri bisa dinilai.
   Karena itu semua kartu untuk sekarang mengarah ke course.html yang sama.

   Nanti saat panel admin dibuat, tiap kelas punya berkas datanya sendiri dan
   `tautan` tinggal diisi "course.html?k=<slug>".

   `ikon` memilih gambar vektor di js/kelas.js — pilihan yang tersedia:
   grafik · pesan · tata · kilau · perisai · video
   ============================================================================= */
window.INVISHAR_KELAS = {
  kategori: ["Semua", "Data & Analitik", "Otomatisasi", "Web & Aplikasi", "Dasar"],

  daftar: [
    {
      slug: "dashboard-sosial-media",
      judul: "Membuat Dashboard Sosial Media",
      kategori: "Data & Analitik",
      ringkas:
        "Dari menarik data mentah sampai grafik yang enak dibaca klien. Satu proyek nyata, dikerjakan bareng sampai jadi.",
      level: "Pemula — Menengah",
      materi: 11,
      durasi: "3 jam 12 mnt",
      harga: "Rp 249rb",
      status: "Dibuka",
      ikon: "grafik",
      tautan: "course.html",
    },
    {
      slug: "otomatisasi-whatsapp",
      judul: "Otomatisasi WhatsApp untuk Usaha Kecil",
      kategori: "Otomatisasi",
      ringkas:
        "Bot pengingat tagihan, konfirmasi pesanan, dan laporan harian yang berjalan sendiri tanpa ditunggui.",
      level: "Menengah",
      materi: 14,
      durasi: "4 jam 05 mnt",
      harga: "Rp 349rb",
      status: "Segera",
      ikon: "pesan",
      tautan: "course.html",
    },
    {
      slug: "portal-sekolah",
      judul: "Merakit Portal Sekolah Sederhana",
      kategori: "Web & Aplikasi",
      ringkas:
        "Nilai, presensi, dan pengumuman dalam satu portal yang bisa dibuka wali murid dari ponsel.",
      level: "Menengah",
      materi: 18,
      durasi: "5 jam 40 mnt",
      harga: "Rp 449rb",
      status: "Segera",
      ikon: "tata",
      tautan: "course.html",
    },
    {
      slug: "spreadsheet-rapi",
      judul: "Spreadsheet yang Tidak Bikin Pusing",
      kategori: "Dasar",
      ringkas:
        "Menyusun data supaya bisa dipakai ulang: satu baris satu kejadian, rumus yang tidak patah, dan laporan yang ikut memperbarui diri.",
      level: "Pemula",
      materi: 9,
      durasi: "2 jam 24 mnt",
      harga: "Rp 149rb",
      status: "Baru",
      ikon: "kilau",
      tautan: "course.html",
    },
    {
      slug: "keuangan-keluarga",
      judul: "Mencatat Keuangan Keluarga dengan Rapi",
      kategori: "Dasar",
      ringkas:
        "Kebiasaan mencatat yang bertahan lebih dari sebulan, lalu membacanya jadi keputusan belanja yang lebih tenang.",
      level: "Pemula",
      materi: 7,
      durasi: "1 jam 48 mnt",
      harga: "Gratis",
      status: "Dibuka",
      ikon: "perisai",
      tautan: "course.html",
    },
    {
      slug: "konten-video-lembaga",
      judul: "Produksi Konten Video untuk Lembaga",
      kategori: "Otomatisasi",
      ringkas:
        "Alur kerja ringkas dari ide, rekam, potong, sampai jadwal tayang — cukup dikerjakan satu orang.",
      level: "Pemula — Menengah",
      materi: 12,
      durasi: "3 jam 36 mnt",
      harga: "Rp 199rb",
      status: "Segera",
      ikon: "video",
      tautan: "course.html",
    },
  ],
};
