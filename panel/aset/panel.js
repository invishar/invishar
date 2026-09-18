/* =============================================================================
   Panel Invishar — perilaku antarmuka.
   Kecil saja: menu samping di layar sempit, penegasan sebelum menghapus, dan
   baris tabel yang bisa diklik.
   ============================================================================= */
(function () {
  "use strict";

  /* ------------------------------------------------------- Menu samping */
  var burger = document.getElementById("burger");
  var sisi = document.getElementById("sisi");

  if (burger && sisi) {
    function setMenu(buka) {
      sisi.classList.toggle("is-buka", buka);
      burger.setAttribute("aria-expanded", buka ? "true" : "false");
      burger.setAttribute("aria-label", buka ? "Tutup menu" : "Buka menu");
    }

    burger.addEventListener("click", function () {
      setMenu(!sisi.classList.contains("is-buka"));
    });

    document.addEventListener("click", function (e) {
      if (!sisi.classList.contains("is-buka")) return;
      if (e.target.closest("#sisi") || e.target.closest("#burger")) return;
      setMenu(false);
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && sisi.classList.contains("is-buka")) {
        setMenu(false);
        burger.focus();
      }
    });
  }

  /* ------------------------------------------- Penegasan sebelum menghapus */
  document.addEventListener("click", function (e) {
    var tombol = e.target.closest("[data-pastikan]");
    if (tombol && !window.confirm(tombol.dataset.pastikan)) {
      e.preventDefault();
    }
  });

  /* ------------------------------------------------ Baris tabel bisa diklik */
  /* Atribut onclick di markup hanya jaring pengaman untuk peramban lama;
     di sini dipastikan klik pada tautan atau tombol tidak ikut terbawa. */
  document.querySelectorAll(".tabel tbody tr").forEach(function (baris) {
    baris.addEventListener("click", function (e) {
      if (e.target.closest("a, button, input, select, textarea")) {
        e.stopPropagation();
      }
    }, true);
  });
})();
