/* =============================================================================
   Invishar — galeri kelas (kelas.html)

   Sumber isinya dua lapis:
     1. data/kelas.json   — terbitan panel admin, kalau ada
     2. js/kelas-data.js  — data bawaan, cadangan kalau (1) belum ada

   Selain menampilkan dan menyaring kartu, halaman ini membaca catatan progres
   di peramban lalu menawarkan sambungan ke kelas yang sedang dijalani.
   ============================================================================= */
(function () {
  "use strict";

  var MOBILE = 760;

  function $(sel) { return document.querySelector(sel); }
  function buat(tag, kelasNama, teks) {
    var el = document.createElement(tag);
    if (kelasNama) el.className = kelasNama;
    if (teks != null) el.textContent = teks;
    return el;
  }

  /* --------------------------------------------------------- 1. Menu mobile */
  (function () {
    var burger = $("#burger");
    var navLinks = $("#nav-links");
    if (!burger || !navLinks) return;

    function setMenu(buka) {
      navLinks.classList.toggle("is-open", buka);
      burger.setAttribute("aria-expanded", buka ? "true" : "false");
      burger.setAttribute("aria-label", buka ? "Tutup menu" : "Buka menu");
    }
    burger.addEventListener("click", function () {
      setMenu(!navLinks.classList.contains("is-open"));
    });
    navLinks.addEventListener("click", function (e) {
      if (e.target.closest("a")) setMenu(false);
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && navLinks.classList.contains("is-open")) {
        setMenu(false);
        burger.focus();
      }
    });
    document.addEventListener("click", function (e) {
      if (!navLinks.classList.contains("is-open")) return;
      if (e.target.closest(".nav-inner")) return;
      setMenu(false);
    });
    window.addEventListener("resize", function () {
      if (window.innerWidth > MOBILE) setMenu(false);
    });
  })();

  /* ------------------------------------------------------------- 2. Gambar */
  /* Gambar vektor sederhana untuk sampul kartu — tanpa berkas gambar supaya
     halaman tetap ringan dan warnanya ikut token tema. */
  var GAMBAR = {
    grafik: ["M3 3v18h18", "M7 14l3-4 3 3 5-7"],
    pesan: ["M21 11.5a7.5 7.5 0 0 1-7.5 7.5H8l-4 3v-4.9A7.5 7.5 0 0 1 8.5 4h5A7.5 7.5 0 0 1 21 11.5z", "M9 11h6"],
    tata: ["M4 4h6v6H4z", "M14 4h6v3h-6z", "M14 11h6v9h-6z", "M4 14h6v6H4z"],
    kilau: ["M12 3l2.2 5.3L20 10.5l-5.8 2.2L12 18l-2.2-5.3L4 10.5l5.8-2.2z", "M18.5 16.5l.9 2.1 2.1.9-2.1.9-.9 2.1-.9-2.1-2.1-.9 2.1-.9z"],
    perisai: ["M12 3l7.5 3v6c0 4.2-3.2 7.6-7.5 8.7C7.7 19.6 4.5 16.2 4.5 12V6z", "M9 12l2.2 2.2L15.5 10"],
    video: ["M3.5 6.5h11v11h-11z", "M14.5 10.5l6-3.5v10l-6-3.5z"],
  };

  function gambar(nama) {
    var svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
    svg.setAttribute("viewBox", "0 0 24 24");
    svg.setAttribute("fill", "none");
    svg.setAttribute("aria-hidden", "true");
    (GAMBAR[nama] || GAMBAR.kilau).forEach(function (d) {
      var path = document.createElementNS("http://www.w3.org/2000/svg", "path");
      path.setAttribute("d", d);
      path.setAttribute("stroke", "currentColor");
      path.setAttribute("stroke-width", "1.6");
      path.setAttribute("stroke-linecap", "round");
      path.setAttribute("stroke-linejoin", "round");
      svg.appendChild(path);
    });
    return svg;
  }

  /* --------------------------------------------------------- 3. Memuat data */
  fetch("data/kelas.json", { cache: "no-cache" })
    .then(function (jawab) {
      if (!jawab.ok) throw new Error(String(jawab.status));
      return jawab.json();
    })
    .catch(function () { return window.INVISHAR_KELAS; })
    .then(function (data) {
      if (data && data.daftar && data.daftar.length) jalankan(data);
    });

  /* ========================================================================= */
  function jalankan(data) {
    var semua = data.daftar;

    /* --- angka ringkasan --- */
    var totalMenit = semua.reduce(function (jml, k) {
      var cocok = /(?:(\d+)\s*jam)?\s*(?:(\d+)\s*mnt)?/.exec(k.durasi) || [];
      return jml + (parseInt(cocok[1], 10) || 0) * 60 + (parseInt(cocok[2], 10) || 0);
    }, 0);

    var ringkasan = [
      [semua.length, "kelas"],
      [semua.filter(function (k) { return k.status !== "Segera"; }).length, "sudah dibuka"],
      [Math.round(totalMenit / 60) + " jam", "rekaman"],
      [semua.reduce(function (j, k) { return j + k.materi; }, 0), "materi"],
    ];

    var wadahAngka = $("#g-angka");
    ringkasan.forEach(function (baris) {
      var li = document.createElement("li");
      li.appendChild(buat("span", "angka-num", String(baris[0])));
      li.appendChild(buat("span", "angka-lbl", baris[1]));
      wadahAngka.appendChild(li);
    });

    /* --- kartu --- */
    var grid = $("#g-grid");

    semua.forEach(function (k, i) {
      var kartu = document.createElement("a");
      kartu.className = "kartu";
      kartu.href = k.tautan || ("course.html?k=" + encodeURIComponent(k.slug));
      kartu.dataset.kategori = k.kategori;
      kartu.dataset.cari = (k.judul + " " + k.ringkas + " " + k.kategori + " " + k.level).toLowerCase();
      kartu.dataset.nada = String(i % 4); // memilih perpaduan warna sampul di CSS

      var sampul = buat("span", "sampul");

      // Kelas yang sudah punya gambar memakai gambarnya; sisanya jatuh ke
      // ikon vektor, jadi kartu tidak pernah kosong.
      if (k.gambar) {
        sampul.classList.add("sampul-foto");
        var foto = document.createElement("img");
        foto.className = "sampul-gambar";
        foto.src = k.gambar;
        foto.alt = "";
        foto.loading = "lazy";
        sampul.appendChild(foto);
      } else {
        var lingkar = buat("span", "sampul-ikon");
        lingkar.appendChild(gambar(k.ikon));
        sampul.appendChild(lingkar);
      }

      sampul.appendChild(buat("span", "sampul-status " + (k.status === "Segera" ? "is-nanti" : ""), k.status));
      sampul.appendChild(buat("span", "sampul-kategori", k.kategori));
      kartu.appendChild(sampul);

      var isi = buat("span", "kartu-isi");
      isi.appendChild(buat("span", "kartu-judul", k.judul));
      isi.appendChild(buat("span", "kartu-ringkas", k.ringkas));

      var meta = buat("span", "kartu-meta");
      [k.materi + " materi", k.durasi, k.level].forEach(function (teks) {
        meta.appendChild(buat("span", null, teks));
      });
      isi.appendChild(meta);

      var kaki = buat("span", "kartu-kaki");
      kaki.appendChild(buat("span", "kartu-harga", k.harga));
      kaki.appendChild(buat("span", "kartu-aksi", "Lihat kelas →"));
      isi.appendChild(kaki);

      kartu.appendChild(isi);
      grid.appendChild(kartu);
    });

    /* --- saringan & pencarian --- */
    var tabs = $("#g-tabs");
    var cari = $("#g-cari");
    var hitung = $("#g-hitung");
    var kosong = $("#g-kosong");
    var kategoriKini = "Semua";

    (data.kategori || ["Semua"]).forEach(function (nama) {
      var tombol = document.createElement("button");
      tombol.className = "tab" + (nama === "Semua" ? " is-on" : "");
      tombol.type = "button";
      tombol.textContent = nama;
      tombol.setAttribute("aria-pressed", nama === "Semua" ? "true" : "false");
      tombol.addEventListener("click", function () {
        kategoriKini = nama;
        tabs.querySelectorAll(".tab").forEach(function (t) {
          var aktif = t === tombol;
          t.classList.toggle("is-on", aktif);
          t.setAttribute("aria-pressed", aktif ? "true" : "false");
        });
        saring();
      });
      tabs.appendChild(tombol);
    });

    function saring() {
      var kata = cari.value.trim().toLowerCase();
      var tampil = 0;

      grid.querySelectorAll(".kartu").forEach(function (kartu) {
        var cocokKategori = kategoriKini === "Semua" || kartu.dataset.kategori === kategoriKini;
        var cocokKata = !kata || kartu.dataset.cari.indexOf(kata) !== -1;
        var lolos = cocokKategori && cocokKata;
        kartu.hidden = !lolos;
        if (lolos) tampil++;
      });

      hitung.textContent = tampil === semua.length
        ? semua.length + " kelas tersedia"
        : tampil + " dari " + semua.length + " kelas";
      kosong.hidden = tampil !== 0;
    }

    cari.addEventListener("input", saring);

    $("#g-bersih").addEventListener("click", function () {
      cari.value = "";
      tabs.querySelector(".tab").click();
      cari.focus();
    });

    saring();

    /* --- sambungan belajar --- */
    /* Catatan progres tiap kelas disimpan terpisah dengan kunci berisi slug.
       Yang ditawarkan di sini: kelas pertama yang sudah dimulai tapi belum
       tuntas. Materi berikutnya dihitung di halaman kelas, bukan di sini. */
    for (var i = 0; i < semua.length; i++) {
      var k = semua[i];
      var paham = [];
      try {
        paham = JSON.parse(localStorage.getItem("invishar.course." + k.slug + ".paham")) || [];
      } catch (e) {
        break; // penyimpanan diblokir — strip tidak usah ditampilkan
      }
      if (!Array.isArray(paham) || !paham.length) continue;

      var selesai = Math.min(paham.length, k.materi);
      if (selesai >= k.materi) continue;

      var persen = k.materi ? Math.round((selesai / k.materi) * 100) : 0;
      $("#g-lanjut-judul").textContent = k.judul;
      $("#g-lanjut-persen").textContent = persen + "%";
      $("#g-lanjut-ring").style.setProperty("--p", persen + "%");
      $("#g-lanjut-sub").textContent = selesai + " dari " + k.materi + " materi sudah dipahami";
      $("#g-lanjut").href = k.tautan || ("course.html?k=" + encodeURIComponent(k.slug));
      $("#g-lanjut-wrap").hidden = false;
      break;
    }
  }
})();
