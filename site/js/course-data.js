/* =============================================================================
   Invishar — data kelas (course)
   Satu-satunya sumber isi untuk course.html dan materi.html.

   Nanti saat panel admin dibuat, berkas ini tinggal diganti oleh keluaran API
   dengan bentuk yang sama (window.INVISHAR_COURSE), tanpa mengubah tampilan.

   Cara mengisi video: `youtube` diisi ID video saja, bukan URL penuh.
   https://www.youtube.com/watch?v=aircAruvnKk  ->  youtube: "aircAruvnKk"
   ============================================================================= */
window.INVISHAR_COURSE = {
  slug: "dashboard-sosial-media",
  kicker: "Kelas unggulan",
  judul: "Membuat Dashboard Sosial Media",
  ringkas:
    "Dari menarik data mentah sampai grafik yang enak dibaca klien. Satu proyek nyata, dikerjakan bareng sampai bisa Anda pakai sendiri.",
  level: "Pemula — Menengah",
  bahasa: "Bahasa Indonesia",
  akses: "Akses selamanya",
  harga: "Rp 249rb",
  pengajar: {
    nama: "Tim Invishar",
    peran: "Studio produk digital",
  },

  ikhtisar: {
    hasil: [
      "Menarik data dari API sosial media dan menyimpannya dengan rapi.",
      "Membersihkan metrik yang berantakan jadi satu tabel yang bisa dipercaya.",
      "Memilih bentuk grafik yang jujur, bukan yang sekadar terlihat ramai.",
      "Menyusun tata letak dashboard yang terbaca dalam sepuluh detik.",
      "Menerbitkan dashboard dan mengirim laporan otomatis tiap pekan.",
    ],
    untukSiapa: [
      "Pengelola sosial media yang capek menyalin angka ke spreadsheet tiap bulan.",
      "Freelancer yang ingin menaikkan nilai laporan ke klien.",
      "Staf lembaga atau sekolah yang mengurus akun resmi.",
    ],
    syarat: [
      "Bisa memakai spreadsheet pada tingkat dasar.",
      "Punya akun sosial media yang datanya boleh dipakai latihan.",
      "Tidak perlu bisa memrogram — semua langkah dipandu.",
    ],
  },

  modul: [
    {
      judul: "Menyiapkan sumber data",
      materi: [
        {
          id: "m01",
          judul: "Selamat datang & cara memakai kelas ini",
          durasi: "6 mnt",
          youtube: "aircAruvnKk",
          ringkas:
            "Gambaran isi kelas, berkas proyek yang perlu diunduh, dan urutan belajar yang disarankan. Tonton ini dulu supaya materi berikutnya terasa nyambung.",
          poin: [
            "Unduh berkas proyek sebelum lanjut ke materi berikutnya.",
            "Kerjakan sambil menonton — jangan ditonton habis dulu.",
            "Setiap materi punya satu hasil yang bisa dilihat di layar Anda.",
          ],
        },
        {
          id: "m02",
          judul: "Mengenali metrik yang benar-benar penting",
          durasi: "14 mnt",
          youtube: "M7lc1UVf-VE",
          ringkas:
            "Jangkauan, tayangan, interaksi, klik — banyak angka terlihat mirip padahal artinya jauh berbeda. Di sini kita pilih metrik mana yang layak masuk dashboard.",
          poin: [
            "Bedakan metrik jangkauan dan metrik interaksi.",
            "Satu pertanyaan bisnis cukup dijawab dua sampai tiga metrik.",
            "Metrik kesombongan: kelihatan besar, tidak bisa ditindaklanjuti.",
          ],
        },
        {
          id: "m03",
          judul: "Izin API & kunci akses",
          durasi: "18 mnt",
          youtube: "ysz5S6PUM-U",
          ringkas:
            "Mendaftarkan aplikasi, meminta izin yang secukupnya, dan menyimpan kunci akses supaya tidak bocor ke repositori publik.",
          poin: [
            "Minta cakupan izin paling sempit yang masih cukup.",
            "Kunci akses tidak pernah ditulis langsung di dalam kode.",
            "Siapkan cara memperbarui token sebelum kedaluwarsa.",
          ],
        },
      ],
    },
    {
      judul: "Mengolah data",
      materi: [
        {
          id: "m04",
          judul: "Menarik data pertama Anda",
          durasi: "21 mnt",
          youtube: "jNQXAC9IVRw",
          ringkas:
            "Permintaan pertama ke API, membaca jawabannya, dan menyimpannya sebagai berkas mentah yang tidak pernah kita ubah lagi.",
          poin: [
            "Simpan jawaban mentah apa adanya sebagai cadangan.",
            "Batas permintaan (rate limit) diperhitungkan sejak awal.",
            "Beri nama berkas dengan tanggal supaya mudah dilacak.",
          ],
        },
        {
          id: "m05",
          judul: "Membersihkan dan menyatukan metrik",
          durasi: "24 mnt",
          youtube: "aircAruvnKk",
          ringkas:
            "Tanggal beda format, nama kolom beda ejaan, angka yang sebenarnya teks. Kita rapikan semuanya jadi satu tabel siap pakai.",
          poin: [
            "Satu baris = satu unggahan pada satu tanggal.",
            "Selesaikan zona waktu di awal, bukan saat membuat grafik.",
            "Nilai kosong ditandai, bukan diam-diam diisi nol.",
          ],
        },
        {
          id: "m06",
          judul: "Menghitung pertumbuhan tanpa menipu diri",
          durasi: "16 mnt",
          youtube: "M7lc1UVf-VE",
          ringkas:
            "Rata-rata bergerak, perbandingan periode, dan kenapa kenaikan 300% dari angka tiga tidak pantas dijadikan berita utama.",
          poin: [
            "Rata-rata tujuh hari meredam ramai harian.",
            "Bandingkan dengan periode sepadan, bukan periode terbaik.",
            "Angka dasar yang kecil membuat persentase jadi menyesatkan.",
          ],
        },
      ],
    },
    {
      judul: "Menyusun tampilan",
      materi: [
        {
          id: "m07",
          judul: "Memilih grafik yang tidak menipu",
          durasi: "19 mnt",
          youtube: "ysz5S6PUM-U",
          ringkas:
            "Garis, batang, atau angka besar saja? Kita cocokkan bentuk grafik dengan pertanyaan yang ingin dijawab pembacanya.",
          poin: [
            "Perubahan waktu: garis. Perbandingan kategori: batang.",
            "Sumbu batang selalu mulai dari nol.",
            "Diagram lingkaran hampir selalu ada penggantinya yang lebih baik.",
          ],
        },
        {
          id: "m08",
          judul: "Tata letak dashboard",
          durasi: "22 mnt",
          youtube: "jNQXAC9IVRw",
          ringkas:
            "Menyusun kartu ringkasan, grafik utama, dan tabel pendukung supaya pembaca paham keadaan hanya dari layar pertama.",
          poin: [
            "Kesimpulan di atas, rincian di bawah.",
            "Paling banyak lima kartu ringkasan pada satu layar.",
            "Setiap grafik diberi judul yang menyatakan temuan, bukan nama kolom.",
          ],
        },
        {
          id: "m09",
          judul: "Warna, label, dan keterbacaan",
          durasi: "15 mnt",
          youtube: "aircAruvnKk",
          ringkas:
            "Palet yang konsisten, label yang tidak bertumpuk, dan kontras yang tetap terbaca di proyektor ruang rapat.",
          poin: [
            "Satu warna aksen, sisanya netral.",
            "Warna jangan jadi satu-satunya pembeda.",
            "Label langsung di garis lebih mudah dibaca daripada legenda.",
          ],
        },
      ],
    },
    {
      judul: "Menerbitkan",
      materi: [
        {
          id: "m10",
          judul: "Menerbitkan dashboard & mengatur akses",
          durasi: "17 mnt",
          youtube: "M7lc1UVf-VE",
          ringkas:
            "Menaikkan dashboard ke hosting, memasang tautan berbagi, dan mengatur siapa yang boleh melihat apa.",
          poin: [
            "Pisahkan tampilan klien dan tampilan internal.",
            "Tautan rahasia bukan pengganti kata sandi.",
            "Catat tanggal pembaruan terakhir di sudut dashboard.",
          ],
        },
        {
          id: "m11",
          judul: "Laporan otomatis tiap pekan",
          durasi: "20 mnt",
          youtube: "ysz5S6PUM-U",
          ringkas:
            "Penjadwalan, ringkasan otomatis lewat surel atau WhatsApp, dan penutup: apa yang perlu Anda kerjakan setelah kelas ini.",
          poin: [
            "Jadwalkan pada jam sepi supaya tidak bentrok dengan batas API.",
            "Laporan dibuka dengan satu kalimat kesimpulan.",
            "Siapkan pemberitahuan kalau pengambilan data gagal.",
          ],
        },
      ],
    },
  ],

  sumber: [
    {
      judul: "Berkas proyek",
      desc: "Data contoh, templat spreadsheet, dan berkas awal untuk tiap modul.",
      aksi: "Unduh",
      url: "#",
    },
    {
      judul: "Lembar contekan metrik",
      desc: "Satu halaman berisi definisi metrik dan rumus yang dipakai di kelas.",
      aksi: "Buka",
      url: "#",
    },
    {
      judul: "Templat dashboard",
      desc: "Tata letak siap pakai yang tinggal Anda sambungkan ke data sendiri.",
      aksi: "Unduh",
      url: "#",
    },
    {
      judul: "Grup tanya jawab",
      desc: "Tempat bertanya kalau ada langkah yang macet di tengah jalan.",
      aksi: "Gabung",
      url: "#kontak",
    },
  ],

  tanya: [
    {
      q: "Saya sama sekali belum pernah memrogram, bisa ikut?",
      a: "Bisa. Semua langkah dipandu dari awal dan berkas proyek sudah disiapkan. Yang dibutuhkan hanya kemampuan dasar memakai spreadsheet dan kesediaan mengerjakan sambil menonton.",
    },
    {
      q: "Berapa lama akses kelasnya?",
      a: "Selamanya. Rekaman dan berkas proyek tetap bisa dibuka, termasuk materi tambahan yang kami sisipkan setelah kelas dirilis.",
    },
    {
      q: "Apakah materinya bisa dipakai untuk platform selain yang dicontohkan?",
      a: "Ya. Cara menarik data memang berbeda tiap platform, tetapi alur membersihkan data, memilih grafik, dan menyusun dashboard berlaku sama di mana pun.",
    },
    {
      q: "Kalau macet di tengah materi, bagaimana?",
      a: "Kirim pertanyaan lewat grup tanya jawab atau kontak di halaman utama. Pertanyaan yang sering muncul kami jawab dengan menambah materi baru di kelas ini.",
    },
  ],
};
