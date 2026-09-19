/* =============================================================================
   Panel Invishar — perilaku antarmuka.

   Isinya: menu samping, penegasan hapus, baris berulang, pelindung perubahan
   yang belum tersimpan, pemulihan posisi gulir, pratinjau kartu, dan tombol
   bantuan AI.

   Semua bersifat tambahan. Tanpa JavaScript, seluruh formulir tetap bisa
   diisi dan disimpan — hanya kenyamanannya yang berkurang.
   ============================================================================= */
(function () {
  "use strict";

  function $(sel, induk) { return (induk || document).querySelector(sel); }
  function $$(sel, induk) { return Array.prototype.slice.call((induk || document).querySelectorAll(sel)); }

  /* ------------------------------------------------------- 1. Menu samping */
  var burger = $("#burger");
  var sisi = $("#sisi");

  if (burger && sisi) {
    var setMenu = function (buka) {
      sisi.classList.toggle("is-buka", buka);
      burger.setAttribute("aria-expanded", buka ? "true" : "false");
      burger.setAttribute("aria-label", buka ? "Tutup menu" : "Buka menu");
    };

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

  /* --------------------------------------------- 2. Penegasan sebelum hapus */
  document.addEventListener("click", function (e) {
    var tombol = e.target.closest("[data-pastikan]");
    if (tombol && !window.confirm(tombol.dataset.pastikan)) {
      e.preventDefault();
    }
  });

  /* ------------------------------------------------- 3. Baris tabel diklik */
  $$(".tabel tbody tr").forEach(function (baris) {
    baris.addEventListener("click", function (e) {
      if (e.target.closest("a, button, input, select, textarea")) {
        e.stopPropagation();
      }
    }, true);
  });

  /* ------------------------------------------- 3b. Tabel jadi kartu di HP */
  /* Di layar sempit, tabel lebar memaksa gulir ke samping dan kolom penting
     (jumlah, status) tersembunyi di kanan. Tiap sel diberi label dari judul
     kolomnya; CSS lalu menyusun satu baris menjadi satu kartu. */
  $$("table.tabel").forEach(function (tabel) {
    var judul = [];
    $$("thead th", tabel).forEach(function (th) {
      var rentang = parseInt(th.getAttribute("colspan") || "1", 10);
      for (var i = 0; i < rentang; i++) judul.push(th.textContent.trim());
    });
    if (!judul.length) return;
    $$("tbody tr", tabel).forEach(function (baris) {
      var kolom = 0;
      Array.prototype.forEach.call(baris.children, function (sel) {
        if (judul[kolom]) sel.setAttribute("data-label", judul[kolom]);
        if (!sel.textContent.trim() && !sel.querySelector("form, button, a, img, input, select")) {
          sel.classList.add("sel-kosong");
        }
        // Isi sel dibungkus satu elemen supaya teks + keterangannya tetap
        // bertumpuk di sisi kanan, tidak terpecah menjadi beberapa kolom.
        var isi = document.createElement("div");
        isi.className = "sel-isi";
        while (sel.firstChild) isi.appendChild(sel.firstChild);
        sel.appendChild(isi);
        kolom += parseInt(sel.getAttribute("colspan") || "1", 10);
      });
    });
    tabel.classList.add("tabel-kartu");
  });

  /* ------------------------------------------- 4. Bagian yang bisa dilipat */
  document.addEventListener("click", function (e) {
    var pemicu = e.target.closest("[data-buka]");
    if (!pemicu) return;

    var sasaran = $(pemicu.dataset.buka);
    if (!sasaran) return;

    var tertutup = sasaran.hasAttribute("hidden");
    if (tertutup) {
      sasaran.removeAttribute("hidden");
      var pertama = sasaran.querySelector("input:not([type=hidden]), textarea, select");
      if (pertama) pertama.focus();
    } else {
      sasaran.setAttribute("hidden", "");
    }
    pemicu.classList.toggle("is-buka", tertutup);
  });

  /* ---------------------------------------------------- 5. Posisi gulir */
  /* Menyimpan dan menyalakan mengurangi rasa "terlempar" setelah menyimpan:
     halaman dimuat ulang oleh server, tapi mata kembali ke tempat semula. */
  var KUNCI_GULIR = "panel.gulir." + location.pathname + location.search;

  document.addEventListener("submit", function () {
    try {
      sessionStorage.setItem(KUNCI_GULIR, String(window.scrollY));
    } catch (e) { /* penyimpanan diblokir — abaikan */ }
  });

  try {
    var simpanan = sessionStorage.getItem(KUNCI_GULIR);
    if (simpanan !== null && !location.hash) {
      sessionStorage.removeItem(KUNCI_GULIR);
      window.scrollTo(0, parseInt(simpanan, 10) || 0);
    }
  } catch (e) { /* abaikan */ }

  /* ------------------------------------- 6. Perubahan yang belum tersimpan */
  var formJaga = $("[data-jaga]");

  if (formJaga) {
    var kotor = false;
    var tandai = function () {
      if (kotor) return;
      kotor = true;
      document.body.classList.add("ada-perubahan");
      var kabar = $("#bilah-kabar");
      if (kabar) kabar.innerHTML = '<span class="titik-kuning"></span> Ada perubahan yang belum disimpan';
    };

    formJaga.addEventListener("input", tandai);
    formJaga.addEventListener("change", tandai);
    formJaga.addEventListener("submit", function () { kotor = false; });

    window.addEventListener("beforeunload", function (e) {
      if (!kotor) return;
      e.preventDefault();
      e.returnValue = "";
    });

    /* Menyimpan modul atau materi memuat ulang halaman, jadi suntingan
       keterangan kelas yang belum disimpan akan hilang. Beri tahu dulu. */
    document.addEventListener("submit", function (e) {
      if (!kotor || e.target === formJaga) {
        kotor = false;
        return;
      }
      var lanjut = window.confirm(
        "Ada perubahan di formulir yang belum disimpan dan akan hilang. Lanjutkan?"
      );
      if (!lanjut) {
        e.preventDefault();
        return;
      }
      kotor = false;
    });
  }

  /* --------------------------------------------------- 7. Hitungan karakter */
  $$("[data-hitung]").forEach(function (medan) {
    var keluaran = $(medan.dataset.hitung);
    if (!keluaran) return;

    var batas = 160;   // panjang yang masih utuh di kartu galeri
    var segarkan = function () {
      var n = medan.value.length;
      keluaran.textContent = n + " karakter";
      keluaran.classList.toggle("hitung-lebih", n > batas);
      keluaran.title = n > batas
        ? "Lebih dari " + batas + " karakter — di kartu galeri akan terpotong."
        : "";
    };
    medan.addEventListener("input", segarkan);
    segarkan();
  });

  /* ------------------------------------------------------ 8. Kunci slug */
  var slugBuka = $("#slug-buka");
  if (slugBuka) {
    slugBuka.addEventListener("click", function () {
      var medan = $("#f-slug");
      var setuju = window.confirm(
        "Mengubah alamat kelas akan:\n\n" +
        "• mematikan tautan lama yang sudah dibagikan\n" +
        "• menghapus berkas kelas yang lama saat Terbitkan\n" +
        "• menghapus catatan progres belajar pengunjung\n\n" +
        "Lanjutkan?"
      );
      if (!setuju) return;

      medan.removeAttribute("readonly");
      medan.focus();
      medan.select();
      $("#slug-ubah").value = "1";
      slugBuka.remove();
      var catatan = $("#slug-catatan");
      catatan.textContent = "Terbuka. Progres belajar pengunjung akan hilang setelah ini disimpan dan diterbitkan.";
      catatan.classList.add("petunjuk-awas");
    });
  }

  /* ---------------------------------------------------- 9. Baris berulang */
  document.addEventListener("click", function (e) {
    var tambah = e.target.closest("[data-tambah]");
    if (tambah) {
      var wadah = $(tambah.dataset.tambah);
      var templat = $(wadah.dataset.templat);
      var baris = templat.content.firstElementChild.cloneNode(true);
      wadah.appendChild(baris);
      var pertama = baris.querySelector("input, textarea");
      if (pertama) pertama.focus();
      return;
    }

    var buang = e.target.closest(".ulang-buang");
    if (buang) {
      var induk = buang.closest(".ulang");
      var baris2 = buang.closest(".ulang-baris");
      // Sisakan satu baris kosong supaya tidak ada keadaan tanpa jalan masuk.
      if (induk.querySelectorAll(".ulang-baris").length === 1) {
        $$("input, textarea", baris2).forEach(function (m) { m.value = ""; });
      } else {
        baris2.remove();
      }
      induk.dispatchEvent(new Event("input", { bubbles: true }));
    }
  });

  /* --------------------------------------------------- 9b. Kabar melayang */
  $$("[data-kabar]").forEach(function (kabar, i) {
    var buang = function () {
      kabar.classList.add("kabar-pudar");
      setTimeout(function () { kabar.remove(); }, 400);
    };
    kabar.querySelector(".kabar-tutup").addEventListener("click", buang);

    // Pesan galat dibiarkan sampai ditutup sendiri; yang lain menghilang.
    if (!kabar.classList.contains("kabar-buruk")) {
      setTimeout(buang, 7000 + i * 600);
    }
  });

  /* ---------------------------------------------- 9c. Isi kolom dari cip */
  document.addEventListener("click", function (e) {
    var cip = e.target.closest("[data-isi]");
    if (!cip) return;

    var medan = $(cip.dataset.isi);
    if (!medan) return;
    medan.value = cip.dataset.nilai;
    medan.dispatchEvent(new Event("input", { bubbles: true }));

    var sekitar = cip.parentNode.querySelectorAll("[data-isi]");
    Array.prototype.forEach.call(sekitar, function (lain) {
      lain.classList.toggle("is-on", lain === cip);
    });
  });

  /* ------------------------------------------ 9d. Gambar sampul terpilih */
  var medanGambar = $("[data-gambar]");
  if (medanGambar) {
    medanGambar.addEventListener("change", function () {
      var berkas = medanGambar.files && medanGambar.files[0];
      var nama = $("#unggah-nama");

      if (!berkas) {
        if (nama) nama.textContent = "Belum ada gambar";
        return;
      }
      if (nama) {
        nama.textContent = berkas.name + " · " + Math.round(berkas.size / 1024) + " KB"
          + (berkas.size > 3 * 1024 * 1024 ? " — terlalu besar, maksimal 3 MB" : "");
      }

      // Pratinjau langsung dari berkas di komputer, tanpa menunggu unggahan.
      var gambar = $("#mini-gambar");
      var ikon = $("#mini-ikon");
      if (gambar && berkas.size <= 3 * 1024 * 1024) {
        gambar.src = URL.createObjectURL(berkas);
        gambar.hidden = false;
        if (ikon) ikon.hidden = true;
      }
    });
  }

  /* ------------------------------------------------- 10. Pratinjau kartu */
  var pratinjau = $("#pratinjau-kartu");
  if (pratinjau) {
    var ikat = function (medan, tujuan, ubah) {
      if (!medan) return;
      var terapkan = function () {
        var el = $(tujuan, pratinjau);
        if (el) el.textContent = ubah ? ubah(medan.value) : medan.value;
      };
      medan.addEventListener("input", terapkan);
      medan.addEventListener("change", terapkan);
    };

    ikat($("#f-judul"), ".mini-judul");
    ikat($("#f-ringkas"), ".mini-ringkas");
    ikat($("#f-harga"), ".mini-harga");
    ikat($("#f-kategori"), ".mini-kategori");
    ikat($("#f-status"), ".mini-status");

    $$("input[name=ikon]").forEach(function (radio) {
      radio.addEventListener("change", function () {
        $$(".ikon-satu").forEach(function (l) { l.classList.remove("is-on"); });
        radio.closest(".ikon-satu").classList.add("is-on");
        var asal = radio.closest(".ikon-satu").querySelector("svg");
        var tujuan = $(".mini-ikon", pratinjau);
        if (asal && tujuan) tujuan.innerHTML = asal.outerHTML;
      });
    });
  }

  /* Bagian 12–15 sengaja ditaruh SEBELUM bagian AI: bagian AI berhenti lebih
     awal (return) di halaman tanpa tombol AI, jadi apa pun sesudahnya tidak
     akan pernah jalan di halaman lain. */

  /* ================================================= 12. Salin ke papan klip */
  /* <button data-salin="teks yang disalin">Salin</button> */
  function salinTeks(teks) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(teks);
    }
    return new Promise(function (berhasil, gagal) {
      var t = document.createElement("textarea");
      t.value = teks;
      t.setAttribute("readonly", "");
      t.style.position = "fixed";
      t.style.opacity = "0";
      document.body.appendChild(t);
      t.select();
      try { document.execCommand("copy") ? berhasil() : gagal(); } catch (e) { gagal(e); }
      document.body.removeChild(t);
    });
  }

  document.addEventListener("click", function (e) {
    var tombol = e.target.closest("[data-salin]");
    if (!tombol) return;
    e.preventDefault();
    var asli = tombol.dataset.teksAsli || tombol.textContent;
    tombol.dataset.teksAsli = asli;
    salinTeks(tombol.dataset.salin).then(function () {
      tombol.textContent = "Tersalin ✓";
      tombol.classList.add("is-disalin");
    }, function () {
      tombol.textContent = "Salin manual";
    });
    setTimeout(function () {
      tombol.textContent = asli;
      tombol.classList.remove("is-disalin");
    }, 1800);
  });

  /* =================================== 13. Bagian yang bergantung pada pilihan */
  /* <div data-tampil-jika="jenis=sekali,langganan"> tampil hanya kalau radio/
     select bernama "jenis" bernilai salah satunya. Isian di dalam bagian yang
     tersembunyi ikut dinonaktifkan supaya tidak ikut divalidasi. */
  var bergantung = $$("[data-tampil-jika]");

  function nilaiMedan(nama) {
    var terpilih = $("[name='" + nama + "']:checked") || $("select[name='" + nama + "']");
    if (!terpilih) {
      var kotakCentang = $("input[type=checkbox][name='" + nama + "']");
      return kotakCentang ? (kotakCentang.checked ? "1" : "0") : "";
    }
    return terpilih.value;
  }

  function segarkanBergantung() {
    bergantung.forEach(function (bagian) {
      var aturan = bagian.dataset.tampilJika.split("=");
      var cocok = aturan[1].split(",").indexOf(nilaiMedan(aturan[0])) !== -1;
      bagian.hidden = !cocok;
      $$("input, select, textarea", bagian).forEach(function (m) { m.disabled = !cocok; });
    });
  }

  if (bergantung.length) {
    document.addEventListener("change", segarkanBergantung);
    segarkanBergantung();
  }

  /* ============================================ 14. Contoh perhitungan komisi */
  var contoh = $("[data-contoh-komisi]");
  if (contoh) {
    var angka = function (teks) {
      var bersih = String(teks || "").replace(/,\d{1,2}$/, "").replace(/\D/g, "");
      return bersih ? parseInt(bersih, 10) : 0;
    };
    var desimal = function (teks) {
      var n = parseFloat(String(teks || "").replace(/\./g, "").replace(",", "."));
      return isNaN(n) ? 0 : n;
    };
    var rp = function (n) { return "Rp " + Math.max(0, n).toLocaleString("id-ID"); };

    var hitung = function () {
      var harga = angka(($("#f-harga") || {}).value);
      var jenisFee = nilaiMedan("fee_jenis");
      var nilai = ($("#f-fee-nilai") || {}).value;
      var komisi = jenisFee === "tetap"
        ? Math.min(harga || angka(nilai), angka(nilai))
        : Math.floor(harga * Math.round(desimal(nilai) * 100) / 10000);
      var akhiran = $("#f-fee-akhiran");
      if (akhiran) akhiran.textContent = jenisFee === "tetap" ? "Rp" : "%";

      var teks = contoh.querySelector("[data-teks]");
      if (!harga) {
        teks.innerHTML = jenisFee === "tetap"
          ? "Affiliator mendapat <strong>" + rp(angka(nilai)) + "</strong> dari setiap penjualan."
          : "Isi harga untuk melihat contoh komisi.";
        return;
      }
      var langganan = nilaiMedan("jenis") === "langganan";
      teks.innerHTML = "Penjualan " + rp(harga) + (langganan ? " per bulan" : "") +
        " &rarr; affiliator mendapat <strong>" + rp(komisi) + "</strong>" + (langganan ? " per bulan" : "");
    };

    document.addEventListener("input", hitung);
    document.addEventListener("change", hitung);
    hitung();
  }

  /* ======================================== 15. Slug otomatis & kunci slug */
  /* <input data-slug-dari="#f-nama">: terisi dari nama selama belum diketik
     sendiri. <button data-buka-kunci="#f-slug" data-pesan="…">: membuka isian
     terkunci setelah ditegaskan. */
  $$("[data-slug-dari]").forEach(function (medan) {
    var sumber = $(medan.dataset.slugDari);
    if (!sumber) return;
    var disentuh = medan.value !== "";
    var jadikanSlug = function (t) {
      return t.toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "")
        .replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 80);
    };
    medan.addEventListener("input", function () { disentuh = medan.value !== ""; });
    sumber.addEventListener("input", function () {
      if (!disentuh) medan.value = jadikanSlug(sumber.value);
    });
  });

  document.addEventListener("click", function (e) {
    var tombol = e.target.closest("[data-buka-kunci]");
    if (!tombol) return;
    if (!window.confirm(tombol.dataset.pesan)) return;
    var medan = $(tombol.dataset.bukaKunci);
    medan.removeAttribute("readonly");
    medan.focus();
    medan.select();
    tombol.remove();
  });

  /* ======================================== 16. Produk affiliate (per baris) */
  /* Setiap <form data-pa> berdiri sendiri (banyak formulir dalam satu halaman),
     jadi semua pencarian dibatasi ke formulirnya. Mengatur: saklar membuka
     isian komisi, Persen/Rupiah mengganti imbuhan, perkiraan komisi, dan
     tombol Simpan yang menyala bila ada perubahan. */
  $$("form[data-pa]").forEach(function (form) {
    var saklar = $("[data-pa-saklar]", form);
    var bagianFee = $("[data-pa-fee]", form);
    var nilai = $("input[name=fee_nilai]", form);
    var bulan = $("input[name=fee_bulan_berulang]", form);
    var hargaIsian = $("[data-pa-harga]", form);
    var perkiraan = $("[data-pa-perkiraan]", form);
    var awal = $("[data-pa-awal]", form);
    var akhir = $("[data-pa-akhir]", form);
    var langganan = form.dataset.jenis === "langganan";
    var jenisProduk = form.dataset.jenis;

    var angka = function (t) {
      var b = String(t || "").replace(/,\d{1,2}$/, "").replace(/\D/g, "");
      return b ? parseInt(b, 10) : 0;
    };
    var desimal = function (t) {
      var n = parseFloat(String(t || "").replace(/\./g, "").replace(",", "."));
      return isNaN(n) ? 0 : n;
    };
    var rp = function (n) { return "Rp " + Math.max(0, n).toLocaleString("id-ID"); };
    var jenisFee = function () {
      var r = $("input[name=fee_jenis]:checked", form);
      return r ? r.value : "persen";
    };
    var bulanUmum = bulan ? bulan.getAttribute("placeholder") : "";

    var tanda = function () {
      return JSON.stringify(Array.from(new FormData(form).entries()).filter(function (p) { return p[0] !== "csrf"; }));
    };
    var tandaMula = null;   // diambil setelah segarkan() pertama (isian nonaktif tidak ikut)

    var segarkan = function () {
      var buka = !saklar || saklar.checked;
      if (bagianFee) {
        bagianFee.hidden = !buka;
        $$("input", bagianFee).forEach(function (m) { m.disabled = !buka; });
      }
      var tetap = jenisFee() === "tetap";
      if (awal) awal.hidden = !tetap;
      if (akhir) akhir.hidden = tetap;
      if (nilai) nilai.placeholder = tetap ? "mis. 50.000" : "mis. 20";

      if (perkiraan && buka) {
        var harga = hargaIsian ? angka(hargaIsian.value) : parseInt(form.dataset.harga || "0", 10);
        var n = tetap ? angka(nilai.value) : desimal(nilai.value);
        var teks = "";
        var awas = false;
        if (!n) {
          teks = tetap ? "Isi komisi dalam rupiah, mis. 50.000." : "Isi persen komisi, mis. 20.";
        } else if (!tetap && n > 100) {
          teks = "Persen tidak boleh lebih dari 100."; awas = true;
        } else if (tetap && harga && n > harga) {
          teks = "Komisi melebihi harga (" + rp(harga) + ")."; awas = true;
        } else {
          var komisi = tetap ? n : Math.floor(harga * Math.round(n * 100) / 10000);
          if (!tetap && !harga) {
            teks = jenisProduk === "penawaran" || jenisProduk === "eksternal"
              ? "Mitra mendapat " + String(n).replace(".", ",") + "% dari nilai pembayaran yang dicatat."
              : "Isi harga untuk melihat perkiraan komisi.";
          } else {
            var bln = langganan ? (bulan && bulan.value ? bulan.value : bulanUmum) : "";
            teks = (harga && !tetap ? "Penjualan " + rp(harga) + (langganan ? "/bulan" : "") + " → " : "") +
              "mitra mendapat <strong>" + rp(komisi) + "</strong>" +
              (langganan ? " per bulan, selama " + bln + " bulan" : " per penjualan");
          }
        }
        perkiraan.innerHTML = teks;
        perkiraan.classList.toggle("is-awas", awas);
      }

      if (tandaMula === null) tandaMula = tanda();
      form.classList.toggle("is-ubah", tanda() !== tandaMula);
    };

    form.addEventListener("input", segarkan);
    form.addEventListener("change", segarkan);
    segarkan();
    // Isian yang gagal disimpan (dikembalikan server) langsung tampak "berubah".
    if (form.querySelector(".pa-galat")) form.classList.add("is-ubah");
  });

  /* Tiap baris disimpan sendiri: menyimpan satu baris memuat ulang halaman,
     jadi ubahan di baris lain akan hilang. Beri tahu dulu. */
  if ($("form[data-pa]")) {
    var dikirim = null;
    document.addEventListener("submit", function (e) {
      var lain = $$("form[data-pa].is-ubah").filter(function (f) { return f !== e.target; });
      if (lain.length && !window.confirm(
        "Ada " + lain.length + " baris lain yang sudah diubah tapi belum disimpan dan akan hilang. Lanjutkan?"
      )) {
        e.preventDefault();
        return;
      }
      dikirim = e.target;
    });
    window.addEventListener("beforeunload", function (e) {
      if (dikirim) return;
      if ($("form[data-pa].is-ubah")) { e.preventDefault(); e.returnValue = ""; }
    });
  }

  /* ============================================================ 11. AI */
  var tombolAI = $$("[data-ai]");
  if (!tombolAI.length) return;

  var csrf = ($("input[name=csrf]") || {}).value || "";

  function konteksKelas() {
    return {
      judul: ($("#f-judul") || {}).value || "",
      kategori: ($("#f-kategori") || {}).value || "",
      level: ($("#f-level") || {}).value || "",
      ringkas: ($("#f-ringkas") || {}).value || "",
    };
  }

  function buat(tag, kelas, teks) {
    var el = document.createElement(tag);
    if (kelas) el.className = kelas;
    if (teks != null) el.textContent = teks;
    return el;
  }

  /** Panel usulan: hasil AI tidak pernah langsung menimpa isian. */
  function tampilkanUsulan(tombol, isiRingkas, terapkan) {
    var lama = tombol.parentNode.querySelector(".usulan");
    if (lama) lama.remove();

    var panel = buat("div", "usulan");
    panel.appendChild(buat("p", "usulan-kepala", "Usulan AI — periksa dulu sebelum dipakai"));

    var isi = buat("div", "usulan-isi");
    isi.appendChild(isiRingkas);
    panel.appendChild(isi);

    var aksi = buat("div", "usulan-aksi");

    var pakai = buat("button", "tbl tbl-utama tbl-kecil", "Terapkan");
    pakai.type = "button";
    pakai.addEventListener("click", function () {
      terapkan();
      panel.remove();
      if (formJaga) formJaga.dispatchEvent(new Event("input", { bubbles: true }));
    });

    var ulang = buat("button", "tbl tbl-kecil", "Buat ulang");
    ulang.type = "button";
    ulang.addEventListener("click", function () {
      panel.remove();
      tombol.click();
    });

    var tutup = buat("button", "tbl tbl-kecil", "Tutup");
    tutup.type = "button";
    tutup.addEventListener("click", function () { panel.remove(); });

    aksi.appendChild(pakai);
    aksi.appendChild(ulang);
    aksi.appendChild(tutup);
    panel.appendChild(aksi);

    tombol.parentNode.appendChild(panel);
    panel.scrollIntoView({ block: "nearest", behavior: "smooth" });
  }

  function tampilkanGalat(tombol, pesan) {
    var lama = tombol.parentNode.querySelector(".usulan");
    if (lama) lama.remove();

    var panel = buat("div", "usulan usulan-galat");
    panel.appendChild(buat("p", null, pesan));
    var tutup = buat("button", "tbl tbl-kecil", "Tutup");
    tutup.type = "button";
    tutup.addEventListener("click", function () { panel.remove(); });
    panel.appendChild(tutup);
    tombol.parentNode.appendChild(panel);
  }

  function blokDaftar(judul, butir) {
    var kotak = buat("div", "usulan-bagian");
    kotak.appendChild(buat("p", "usulan-judul", judul));
    var ul = document.createElement("ul");
    butir.forEach(function (b) { ul.appendChild(buat("li", null, b)); });
    kotak.appendChild(ul);
    return kotak;
  }

  tombolAI.forEach(function (tombol) {
    tombol.addEventListener("click", function () {
      var tugas = tombol.dataset.ai;
      var konteks = konteksKelas();

      // Untuk materi, judulnya diambil dari formulir materi terdekat.
      var formMateri = tombol.closest("[data-materi]");
      if (tugas === "materi") {
        var medanJudul = formMateri && formMateri.querySelector("[data-judul-materi]");
        konteks.judul_materi = medanJudul ? medanJudul.value : "";
        if (!konteks.judul_materi.trim()) {
          tampilkanGalat(tombol, "Isi judul materi dulu — itu bahan utamanya.");
          return;
        }
      }

      if (!konteks.judul.trim()) {
        tampilkanGalat(tombol, "Isi judul kelas dulu — itu bahan utamanya.");
        return;
      }

      var teksAsli = tombol.textContent;
      tombol.disabled = true;
      tombol.textContent = "Menyusun…";

      fetch((document.body.dataset.akar || "") + "api-ai", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ csrf: csrf, tugas: tugas, konteks: konteks }),
      })
        .then(function (jawab) {
          return jawab.json().then(function (data) {
            if (!jawab.ok) throw new Error(data.galat || "Permintaan gagal.");
            return data.hasil;
          });
        })
        .then(function (hasil) {
          if (tugas === "ringkasan") {
            tampilkanUsulan(tombol, buat("p", null, hasil.ringkas), function () {
              var medan = $(tombol.dataset.tujuan);
              medan.value = hasil.ringkas;
              medan.dispatchEvent(new Event("input", { bubbles: true }));
            });
          }

          if (tugas === "ikhtisar") {
            var kotak = document.createElement("div");
            kotak.appendChild(blokDaftar("Yang akan dikuasai", hasil.hasil));
            kotak.appendChild(blokDaftar("Cocok untuk", hasil.untukSiapa));
            kotak.appendChild(blokDaftar("Perlu disiapkan", hasil.syarat));
            tampilkanUsulan(tombol, kotak, function () {
              $("#f-hasil").value = hasil.hasil.join("\n");
              $("#f-untuk").value = hasil.untukSiapa.join("\n");
              $("#f-syarat").value = hasil.syarat.join("\n");
            });
          }

          if (tugas === "tanya") {
            var kotakT = document.createElement("div");
            hasil.tanya.forEach(function (t) {
              var b = buat("div", "usulan-bagian");
              b.appendChild(buat("p", "usulan-judul", t.q));
              b.appendChild(buat("p", null, t.a));
              kotakT.appendChild(b);
            });
            tampilkanUsulan(tombol, kotakT, function () {
              var wadah = $("#ulang-tanya");
              var templat = $(wadah.dataset.templat);
              // Baris kosong yang tersisa dibuang supaya tidak menumpuk.
              $$(".ulang-baris", wadah).forEach(function (baris) {
                if (!baris.querySelector("input").value.trim()) baris.remove();
              });
              hasil.tanya.forEach(function (t) {
                var baris = templat.content.firstElementChild.cloneNode(true);
                baris.querySelector("input").value = t.q;
                baris.querySelector("textarea").value = t.a;
                wadah.appendChild(baris);
              });
            });
          }

          if (tugas === "kerangka") {
            var kotakK = document.createElement("div");
            var jml = 0;
            hasil.modul.forEach(function (m) {
              var b = buat("div", "usulan-bagian");
              b.appendChild(buat("p", "usulan-judul", m.judul));
              var ul = document.createElement("ul");
              m.materi.forEach(function (x) {
                jml++;
                ul.appendChild(buat("li", null, x.judul + " · " + x.durasi));
              });
              b.appendChild(ul);
              kotakK.appendChild(b);
            });
            kotakK.appendChild(buat("p", "usulan-catatan",
              hasil.modul.length + " modul, " + jml + " materi akan DITAMBAHKAN di bawah yang sudah ada. " +
              "ID video tetap kosong dan harus diisi sendiri."));

            tampilkanUsulan(tombol, kotakK, function () {
              $("#kerangka-isi").value = JSON.stringify(hasil);
              $("#form-kerangka").submit();
            });
          }

          if (tugas === "materi") {
            var kotakM = document.createElement("div");
            kotakM.appendChild(buat("p", null, hasil.ringkas));
            kotakM.appendChild(blokDaftar("Poin penting", hasil.poin));
            tampilkanUsulan(tombol, kotakM, function () {
              formMateri.querySelector("[data-materi-ringkas]").value = hasil.ringkas;
              formMateri.querySelector("[data-materi-poin]").value = hasil.poin.join("\n");
            });
          }
        })
        .catch(function (galat) {
          tampilkanGalat(tombol, galat.message || "Layanan AI tidak bisa dihubungi.");
        })
        .then(function () {
          tombol.disabled = false;
          tombol.textContent = teksAsli;
        });
    });
  });

})();
