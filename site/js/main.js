/* =============================================================================
   Invishar — landing page
   Perilaku halaman: menu mobile, saringan katalog, akordion tanya jawab,
   form kontak, dan animasi muncul saat scroll.

   Semuanya progressive enhancement: tanpa file ini halaman tetap terbaca
   penuh — konten terlihat, semua kartu katalog tampil, jawaban FAQ pertama
   terbuka, dan form jatuh ke pengiriman HTML biasa.
   ============================================================================= */
(function () {
  "use strict";

  var MOBILE = 760;
  var kurangGerak = window.matchMedia("(prefers-reduced-motion: reduce)");

  /* ---------------------------------------------------------- 1. Menu mobile */
  var burger = document.getElementById("burger");
  var navLinks = document.getElementById("nav-links");

  function setMenu(buka) {
    if (!burger || !navLinks) return;
    navLinks.classList.toggle("is-open", buka);
    burger.setAttribute("aria-expanded", buka ? "true" : "false");
    burger.setAttribute("aria-label", buka ? "Tutup menu" : "Buka menu");
  }

  if (burger && navLinks) {
    burger.addEventListener("click", function () {
      setMenu(!navLinks.classList.contains("is-open"));
    });

    // Tutup setelah memilih tautan.
    navLinks.addEventListener("click", function (e) {
      if (e.target.closest("a")) setMenu(false);
    });

    // Tutup dengan Escape.
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && navLinks.classList.contains("is-open")) {
        setMenu(false);
        burger.focus();
      }
    });

    // Tutup saat klik di luar bilah nav.
    document.addEventListener("click", function (e) {
      if (!navLinks.classList.contains("is-open")) return;
      if (e.target.closest(".nav-inner")) return;
      setMenu(false);
    });

    // Tutup saat layar melebar melewati ambang mobile.
    window.addEventListener("resize", function () {
      if (window.innerWidth > MOBILE) setMenu(false);
    });
  }

  /* ------------------------------------------------------- 2. Saringan katalog */
  var tabs = document.querySelectorAll(".tab");
  var items = document.querySelectorAll("#katalog-grid .item");

  if (tabs.length && items.length) {
    Array.prototype.forEach.call(tabs, function (tab) {
      tab.addEventListener("click", function () {
        var pilih = tab.dataset.filter;

        Array.prototype.forEach.call(tabs, function (t) {
          var aktif = t === tab;
          t.classList.toggle("is-on", aktif);
          t.setAttribute("aria-pressed", aktif ? "true" : "false");
        });

        Array.prototype.forEach.call(items, function (item) {
          item.hidden = !(pilih === "Semua" || item.dataset.kategori === pilih);
        });
      });
    });
  }

  /* ---------------------------------------------------- 3. Akordion tanya jawab */
  var faqButtons = document.querySelectorAll(".faq-q");

  Array.prototype.forEach.call(faqButtons, function (btn) {
    btn.addEventListener("click", function () {
      var terbuka = btn.getAttribute("aria-expanded") === "true";

      // Satu jawaban terbuka pada satu waktu.
      Array.prototype.forEach.call(faqButtons, function (other) {
        other.setAttribute("aria-expanded", "false");
        var panelLain = document.getElementById(other.getAttribute("aria-controls"));
        if (panelLain) panelLain.classList.remove("is-open");
      });

      if (!terbuka) {
        btn.setAttribute("aria-expanded", "true");
        var panel = document.getElementById(btn.getAttribute("aria-controls"));
        if (panel) panel.classList.add("is-open");
      }
    });
  });

  /* ------------------------------------------------------------ 4. Form kontak */
  /*
     Saat ini form berjalan dalam mode demo: tidak mengirim ke mana pun,
     hanya mengubah teks tombol — persis seperti di desain aslinya.

     Untuk mengaktifkan pengiriman sungguhan, pilih salah satu:

     a) Layanan form (paling cepat, tanpa server). Di index.html ubah:
          <form ... action="https://formspree.io/f/KODE-ANDA" method="post">
        lalu HAPUS atribut data-demo. Blok di bawah akan melepas form
        ke pengiriman normal.

     b) Endpoint sendiri. Hapus data-demo, lalu ganti blok ini dengan fetch()
        ke API Anda.

     c) Arahkan ke WhatsApp: rakit teks pesan dari isian form lalu
        window.location = "https://wa.me/628120000000?text=" + encodeURIComponent(teks);
  */
  var form = document.getElementById("form-kontak");

  if (form && form.hasAttribute("data-demo")) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();

      var tombol = document.getElementById("tombol-kirim");
      var catatan = document.getElementById("form-note");

      form.classList.add("is-sent");
      if (tombol) {
        tombol.textContent = "Terima kasih — segera kami balas";
        tombol.disabled = true;
      }
      if (catatan) {
        catatan.textContent = "Pesan tercatat. Dibalas dalam 1–2 hari kerja.";
      }
    });
  }

  /* ------------------------------------------------- 5. Animasi muncul (reveal) */
  var nodes = document.querySelectorAll("[data-reveal], [data-reveal-fade]");

  function tampilkan(el) {
    if (el.dataset.revealed) return;
    var jeda = el.dataset.reveal || el.dataset.revealFade || "0";
    el.style.transitionDelay = parseInt(jeda, 10) + "ms";
    el.classList.add("is-in");
    el.dataset.revealed = "1";
  }

  function tampilkanSemua() {
    Array.prototype.forEach.call(nodes, function (el) {
      el.style.transitionDelay = "0ms";
      el.classList.add("is-in");
      el.dataset.revealed = "1";
    });
  }

  if (!nodes.length) {
    // tidak ada yang perlu dianimasikan
  } else if (kurangGerak.matches || !("IntersectionObserver" in window)) {
    tampilkanSemua();
  } else {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        tampilkan(en.target);
        io.unobserve(en.target);
      });
    }, { rootMargin: "0px 0px -6% 0px", threshold: 0.05 });

    Array.prototype.forEach.call(nodes, function (el) { io.observe(el); });

    // Jaring pengaman: apa pun yang terjadi, jangan biarkan halaman tetap kosong.
    setTimeout(function () {
      Array.prototype.forEach.call(nodes, function (el) {
        var kotak = el.getBoundingClientRect();
        if (kotak.top < window.innerHeight && kotak.bottom > 0) tampilkan(el);
      });
    }, 1400);

    // Kalau pengguna menyalakan "kurangi gerak" di tengah jalan.
    var onGerak = function () { if (kurangGerak.matches) tampilkanSemua(); };
    if (kurangGerak.addEventListener) kurangGerak.addEventListener("change", onGerak);
    else if (kurangGerak.addListener) kurangGerak.addListener(onGerak);
  }
})();
