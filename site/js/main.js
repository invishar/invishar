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
     Dua mode, ditentukan oleh atribut pada <form> di index.html:

     a) data-kirim="https://panel.invishar.com/api-pesan.php"
        Pesan dikirim ke panel admin dan muncul sebagai order jasa.

     b) data-demo
        Mode sekarang: tidak mengirim ke mana pun, hanya mengubah teks tombol.
        Dipakai selama panel belum hidup — begitu panel siap, ganti data-demo
        menjadi data-kirim dengan alamat di atas.
  */
  var form = document.getElementById("form-kontak");
  var tombol = document.getElementById("tombol-kirim");
  var catatan = document.getElementById("form-note");

  function tandaiTerkirim() {
    form.classList.add("is-sent");
    if (tombol) {
      tombol.textContent = "Terima kasih — segera kami balas";
      tombol.disabled = true;
    }
    if (catatan) catatan.textContent = "Pesan tercatat. Dibalas dalam 1–2 hari kerja.";
  }

  if (form && form.hasAttribute("data-demo")) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      tandaiTerkirim();
    });
  }

  if (form && form.dataset.kirim) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();

      if (tombol) {
        tombol.disabled = true;
        tombol.textContent = "Mengirim…";
      }

      fetch(form.dataset.kirim, { method: "POST", body: new FormData(form) })
        .then(function (jawab) {
          if (!jawab.ok) throw new Error(String(jawab.status));
          tandaiTerkirim();
        })
        .catch(function () {
          // Jangan pura-pura terkirim: beri jalan lain supaya pesannya tidak hilang.
          if (tombol) {
            tombol.disabled = false;
            tombol.textContent = "Kirim pesan";
          }
          if (catatan) {
            catatan.textContent = "Pesan gagal terkirim. Hubungi kami lewat WhatsApp di bawah.";
          }
          form.classList.add("is-failed");
        });
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
