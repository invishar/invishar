/* =============================================================================
   Invishar — halaman kelas
   Satu berkas untuk dua halaman:
     course.html  → ikhtisar, daftar materi, sumber belajar, tanya jawab
     materi.html  → pemutar video, penanda "sudah paham", tombol materi berikutnya

   Isi kelas datang dari js/course-data.js (window.INVISHAR_COURSE).
   Catatan progres disimpan di localStorage peramban pengunjung, jadi tidak
   butuh server. Saat panel admin dan akun pengguna dibuat, cukup ganti
   simpanan() dan catat() di bagian 2 dengan panggilan ke API.
   ============================================================================= */
(function () {
  "use strict";

  var kelas = window.INVISHAR_COURSE;
  if (!kelas) return;

  var MOBILE = 760;

  /* ------------------------------------------------------------ 1. Bantuan */
  function $(sel, induk) { return (induk || document).querySelector(sel); }
  function buat(tag, kelasNama, teks) {
    var el = document.createElement(tag);
    if (kelasNama) el.className = kelasNama;
    if (teks != null) el.textContent = teks;
    return el;
  }
  function isiDaftar(ul, arr, kelasNama) {
    if (!ul) return;
    ul.textContent = "";
    (arr || []).forEach(function (teks) {
      ul.appendChild(buat("li", kelasNama || null, teks));
    });
  }

  // Semua materi dari semua modul, berurutan — dipakai untuk tombol maju/mundur.
  var urut = [];
  kelas.modul.forEach(function (mod, i) {
    (mod.materi || []).forEach(function (mat) {
      urut.push({ data: mat, modul: mod, modulKe: i + 1 });
    });
  });
  var total = urut.length;

  function indeksDari(id) {
    for (var i = 0; i < urut.length; i++) if (urut[i].data.id === id) return i;
    return -1;
  }

  /* ------------------------------------------------------ 2. Catatan progres */
  var KUNCI = "invishar.course." + kelas.slug + ".paham";

  function simpanan() {
    try {
      var mentah = localStorage.getItem(KUNCI);
      var arr = mentah ? JSON.parse(mentah) : [];
      return Array.isArray(arr) ? arr : [];
    } catch (e) {
      return []; // mode penyamaran / penyimpanan diblokir
    }
  }

  function catat(daftar) {
    try {
      localStorage.setItem(KUNCI, JSON.stringify(daftar));
    } catch (e) { /* diabaikan: progres cuma tidak tersimpan */ }
  }

  function sudahPaham(id) { return simpanan().indexOf(id) !== -1; }

  function setPaham(id, nilai) {
    var daftar = simpanan();
    var posisi = daftar.indexOf(id);
    if (nilai && posisi === -1) daftar.push(id);
    if (!nilai && posisi !== -1) daftar.splice(posisi, 1);
    catat(daftar);
  }

  function jumlahPaham() {
    var daftar = simpanan();
    return urut.filter(function (m) { return daftar.indexOf(m.data.id) !== -1; }).length;
  }

  // Materi pertama yang belum ditandai paham — tujuan tombol "Lanjutkan".
  function materiBerikutnya() {
    var daftar = simpanan();
    for (var i = 0; i < urut.length; i++) {
      if (daftar.indexOf(urut[i].data.id) === -1) return urut[i];
    }
    return urut[0];
  }

  function tautanMateri(id) { return "materi.html?m=" + encodeURIComponent(id); }

  /* --------------------------------------------------------- 3. Menu mobile */
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

  /* =========================================================================
     4. Halaman kelas — course.html
     ========================================================================= */
  function halamanKelas() {
    var wadahModul = $("#c-modul");
    if (!wadahModul) return false;

    /* --- kepala halaman --- */
    $("#c-kicker").textContent = kelas.kicker || "Kelas";
    $("#c-judul").textContent = kelas.judul;
    $("#c-ringkas").textContent = kelas.ringkas;
    $("#c-harga").textContent = kelas.harga || "";
    document.title = kelas.judul + " — Kelas Invishar";

    var menit = urut.reduce(function (jml, m) {
      var angka = parseInt(m.data.durasi, 10);
      return jml + (isNaN(angka) ? 0 : angka);
    }, 0);
    var jam = Math.floor(menit / 60);
    var durasiTotal = (jam ? jam + " jam " : "") + (menit % 60) + " menit";

    isiDaftar($("#c-meta"), [
      total + " materi",
      durasiTotal,
      kelas.level,
      kelas.bahasa,
      kelas.akses,
    ].filter(Boolean));

    isiDaftar($("#c-hasil"), kelas.ikhtisar.hasil);
    isiDaftar($("#c-untuk"), kelas.ikhtisar.untukSiapa);
    isiDaftar($("#c-syarat"), kelas.ikhtisar.syarat);

    /* --- daftar materi --- */
    kelas.modul.forEach(function (mod, i) {
      var blok = buat("section", "modul-blok");

      var kepala = buat("div", "modul-kepala");
      kepala.appendChild(buat("p", "modul-no", "Modul " + ("0" + (i + 1)).slice(-2)));
      kepala.appendChild(buat("h3", null, mod.judul));
      kepala.appendChild(buat("p", "modul-jml", mod.materi.length + " materi"));
      blok.appendChild(kepala);

      mod.materi.forEach(function (mat) {
        var baris = document.createElement("a");
        baris.className = "baris";
        baris.href = tautanMateri(mat.id);
        baris.dataset.id = mat.id;

        baris.appendChild(buat("span", "tanda", "✓"));

        var tengah = buat("span", "baris-teks");
        tengah.appendChild(buat("span", "baris-judul", mat.judul));
        tengah.appendChild(buat("span", "baris-ringkas", mat.ringkas));
        baris.appendChild(tengah);

        baris.appendChild(buat("span", "baris-durasi", mat.durasi));
        baris.appendChild(buat("span", "baris-tonton", "Tonton →"));

        blok.appendChild(baris);
      });

      wadahModul.appendChild(blok);
    });

    /* --- sumber belajar --- */
    var wadahSumber = $("#c-sumber");
    (kelas.sumber || []).forEach(function (s) {
      var kartu = buat("article", "item");
      var atas = buat("div", "item-top");
      atas.appendChild(buat("span", "kicker", "Berkas"));
      atas.appendChild(buat("span", "badge", s.aksi || "Buka"));
      kartu.appendChild(atas);
      kartu.appendChild(buat("h3", null, s.judul));
      kartu.appendChild(buat("p", null, s.desc));

      var kaki = buat("div", "item-foot");
      var tautan = document.createElement("a");
      tautan.href = s.url && s.url.charAt(0) === "#" ? "index.html" + s.url : (s.url || "#");
      tautan.textContent = (s.aksi || "Buka") + " →";
      kaki.appendChild(tautan);
      kartu.appendChild(kaki);

      wadahSumber.appendChild(kartu);
    });

    /* --- tanya jawab --- */
    var wadahTanya = $("#c-tanya");
    (kelas.tanya || []).forEach(function (t, i) {
      var baris = buat("div", "faq-row");

      var tombol = document.createElement("button");
      tombol.className = "faq-q";
      tombol.type = "button";
      tombol.setAttribute("aria-expanded", "false");
      tombol.setAttribute("aria-controls", "c-faq-" + i);
      tombol.appendChild(buat("span", null, t.q));
      var ikon = buat("span", "faq-icon", "+");
      ikon.setAttribute("aria-hidden", "true");
      tombol.appendChild(ikon);

      var jawab = buat("div", "faq-a");
      jawab.id = "c-faq-" + i;
      var dalam = document.createElement("div");
      dalam.appendChild(buat("p", null, t.a));
      jawab.appendChild(dalam);

      tombol.addEventListener("click", function () {
        var terbuka = tombol.getAttribute("aria-expanded") === "true";
        wadahTanya.querySelectorAll(".faq-q").forEach(function (lain) {
          lain.setAttribute("aria-expanded", "false");
          var panel = document.getElementById(lain.getAttribute("aria-controls"));
          if (panel) panel.classList.remove("is-open");
        });
        if (!terbuka) {
          tombol.setAttribute("aria-expanded", "true");
          jawab.classList.add("is-open");
        }
      });

      baris.appendChild(tombol);
      baris.appendChild(jawab);
      wadahTanya.appendChild(baris);
    });

    /* --- progres --- */
    function segarkanProgres() {
      var selesai = jumlahPaham();
      var persen = total ? Math.round((selesai / total) * 100) : 0;

      $("#c-ring").style.setProperty("--p", persen + "%");
      $("#c-ring-num").textContent = persen + "%";
      $("#c-prog-teks").textContent = selesai + " dari " + total + " materi";
      $("#c-bar").style.width = persen + "%";
      $("#c-materi-sub").textContent =
        total + " materi · " + durasiTotal + " · " + selesai + " sudah dipahami";

      var berikut = materiBerikutnya();
      var tuntas = selesai === total && total > 0;

      $("#c-prog-sub").textContent = tuntas
        ? "Semua materi sudah ditandai paham. Mantap."
        : selesai === 0
          ? "Belum ada yang ditandai paham."
          : "Lanjut: " + berikut.data.judul;

      var tombol = $("#c-lanjut");
      tombol.href = tautanMateri(berikut.data.id);
      tombol.textContent = tuntas
        ? "Ulangi dari awal"
        : selesai === 0 ? "Mulai belajar" : "Lanjutkan belajar";

      var tautanLanjut = $("#c-lanjut-2");
      tautanLanjut.href = tombol.href;
      tautanLanjut.textContent = (tuntas ? "Ulangi dari awal" : "Lanjutkan") + " →";

      $("#c-reset").hidden = selesai === 0;

      wadahModul.querySelectorAll(".baris").forEach(function (baris) {
        baris.classList.toggle("is-done", sudahPaham(baris.dataset.id));
      });
    }

    $("#c-reset").addEventListener("click", function () {
      catat([]);
      segarkanProgres();
    });

    segarkanProgres();

    // Progres bisa berubah di tab lain (halaman materi) — ikut menyesuaikan.
    window.addEventListener("storage", function (e) {
      if (e.key === KUNCI) segarkanProgres();
    });

    /* --- tab --- */
    var tabs = Array.prototype.slice.call(document.querySelectorAll(".tabbar .tab"));

    function pilihTab(tab, geser) {
      tabs.forEach(function (t) {
        var aktif = t === tab;
        t.classList.toggle("is-on", aktif);
        t.setAttribute("aria-selected", aktif ? "true" : "false");
        document.getElementById(t.getAttribute("aria-controls")).hidden = !aktif;
      });
      var nama = tab.id.replace("tab-", "");
      if (history.replaceState) history.replaceState(null, "", "#" + nama);
      if (geser) {
        var atas = $(".tabbar-wrap").getBoundingClientRect().top + window.pageYOffset - 60;
        window.scrollTo({ top: atas, behavior: "smooth" });
      }
    }

    tabs.forEach(function (tab) {
      tab.addEventListener("click", function () { pilihTab(tab, false); });
    });

    function dariHash() {
      var nama = (location.hash || "").replace("#", "");
      var tab = nama && document.getElementById("tab-" + nama);
      if (tab) pilihTab(tab, false);
    }
    dariHash();
    window.addEventListener("hashchange", dariHash);

    // Tautan "#materi" di dalam halaman membuka tab sekaligus menggulir.
    document.addEventListener("click", function (e) {
      var a = e.target.closest('a[href="#materi"]');
      if (!a) return;
      e.preventDefault();
      pilihTab($("#tab-materi"), true);
    });

    return true;
  }

  /* =========================================================================
     5. Halaman materi — materi.html
     ========================================================================= */
  function halamanMateri() {
    var wadahPlayer = $("#m-player");
    if (!wadahPlayer) return false;

    var minta = new URLSearchParams(location.search).get("m");
    var ke = indeksDari(minta);
    if (ke === -1) ke = 0;

    var kini = urut[ke];
    var mat = kini.data;
    var sebelum = ke > 0 ? urut[ke - 1] : null;
    var sesudah = ke < total - 1 ? urut[ke + 1] : null;

    document.title = mat.judul + " — " + kelas.judul;

    $("#m-crumb-modul").textContent = kini.modul.judul;
    $("#m-kicker").textContent =
      "Modul " + ("0" + kini.modulKe).slice(-2) + " · " + kini.modul.judul + " · " + mat.durasi;
    $("#m-judul").textContent = mat.judul;
    $("#m-ringkas").textContent = mat.ringkas;
    $("#m-kelas-judul").textContent = kelas.judul;
    isiDaftar($("#m-poin"), mat.poin);

    /* --- pemutar video ---
       Sampul dulu, iframe baru dimuat saat diklik: halaman ringan dan
       YouTube tidak memasang cookie sebelum pengunjung benar-benar menonton. */
    function pasangPemutar() {
      var tombol = document.createElement("button");
      tombol.className = "player-sampul";
      tombol.type = "button";
      tombol.setAttribute("aria-label", "Putar video: " + mat.judul);

      var gambar = document.createElement("img");
      gambar.src = "https://i.ytimg.com/vi/" + mat.youtube + "/hqdefault.jpg";
      gambar.alt = "";
      gambar.loading = "lazy";
      tombol.appendChild(gambar);

      var lencana = buat("span", "player-tombol");
      lencana.setAttribute("aria-hidden", "true");
      tombol.appendChild(lencana);

      tombol.addEventListener("click", function () {
        var bingkai = document.createElement("iframe");
        bingkai.src = "https://www.youtube-nocookie.com/embed/" + mat.youtube +
          "?autoplay=1&rel=0&modestbranding=1";
        bingkai.title = mat.judul;
        bingkai.allow = "accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture";
        bingkai.allowFullscreen = true;
        bingkai.referrerPolicy = "strict-origin-when-cross-origin";
        wadahPlayer.textContent = "";
        wadahPlayer.appendChild(bingkai);
      });

      wadahPlayer.appendChild(tombol);
    }
    pasangPemutar();

    /* --- daftar putar di samping --- */
    var wadahDaftar = $("#m-daftar");
    kelas.modul.forEach(function (mod, i) {
      var blok = buat("div", "daftar-modul");
      blok.appendChild(buat("p", "daftar-modul-judul",
        "Modul " + ("0" + (i + 1)).slice(-2) + " · " + mod.judul));

      mod.materi.forEach(function (m) {
        var baris = document.createElement("a");
        baris.className = "daftar-baris";
        baris.href = tautanMateri(m.id);
        baris.dataset.id = m.id;
        if (m.id === mat.id) {
          baris.classList.add("is-now");
          baris.setAttribute("aria-current", "true");
        }
        baris.appendChild(buat("span", "tanda", "✓"));
        var teks = buat("span", "daftar-teks");
        teks.appendChild(buat("span", "daftar-baris-judul", m.judul));
        teks.appendChild(buat("span", "daftar-baris-durasi", m.durasi));
        baris.appendChild(teks);
        blok.appendChild(baris);
      });

      wadahDaftar.appendChild(blok);
    });

    /* --- tombol maju / mundur --- */
    var tPrev = $("#m-prev");
    var tNext = $("#m-next");

    if (sebelum) {
      tPrev.href = tautanMateri(sebelum.data.id);
    } else {
      tPrev.href = "course.html#materi";
      tPrev.textContent = "← Daftar materi";
    }

    if (sesudah) {
      tNext.href = tautanMateri(sesudah.data.id);
    } else {
      tNext.href = "course.html#materi";
      tNext.textContent = "Selesai — lihat progres →";
    }

    /* --- penanda "sudah paham" --- */
    var kotak = $("#m-paham");
    var subPaham = $("#m-paham-sub");

    function segarkan() {
      var selesai = jumlahPaham();
      var persen = total ? Math.round((selesai / total) * 100) : 0;

      $("#m-prog").textContent = selesai + " dari " + total + " materi selesai";
      $("#m-bar").style.width = persen + "%";

      wadahDaftar.querySelectorAll(".daftar-baris").forEach(function (baris) {
        baris.classList.toggle("is-done", sudahPaham(baris.dataset.id));
      });

      var paham = kotak.checked;
      document.querySelector(".paham").classList.toggle("is-on", paham);
      tNext.classList.toggle("is-siap", paham);
      subPaham.textContent = paham
        ? (sesudah ? "Tercatat. Lanjut ke: " + sesudah.data.judul : "Tercatat. Semua materi sudah dilalui.")
        : "Tandai kalau sudah dicoba sendiri, bukan hanya ditonton.";
    }

    kotak.checked = sudahPaham(mat.id);
    kotak.addEventListener("change", function () {
      setPaham(mat.id, kotak.checked);
      segarkan();
    });
    segarkan();

    // Materi yang sedang dibuka digulirkan ke tengah daftar samping.
    var aktif = wadahDaftar.querySelector(".is-now");
    if (aktif && $(".daftar-isi").scrollHeight > $(".daftar-isi").clientHeight) {
      $(".daftar-isi").scrollTop = aktif.offsetTop - 80;
    }

    return true;
  }

  halamanKelas() || halamanMateri();
})();
