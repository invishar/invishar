/* =============================================================================
   Invishar — landing page produk.

   Alamat: /p/{slug}  (atau produk.html?p={slug} sebagai cadangan)
   Isi:    /data/produk.json — diterbitkan otomatis oleh panel setiap kali
           produk disimpan.

   Link affiliate TIDAK diurus di sini. /r/KODE/slug ditangani server
   (toko/r.php), yang menanam cookie lalu mengalihkan ke halaman ini. Kalau
   seseorang membagikan /p/slug?ref=KODE, halaman ini meneruskannya sekali ke
   /r/KODE/slug supaya pencatatannya tetap lewat server.
   ============================================================================= */
(function () {
  "use strict";

  function $(id) { return document.getElementById(id); }
  function tampil(el, ya) { if (el) el.hidden = !ya; }

  var params = new URLSearchParams(location.search);
  var cocok = location.pathname.match(/^\/p\/([a-z0-9-]+)\/?$/);
  var slug = cocok ? cocok[1] : (params.get("p") || "").toLowerCase();

  /* ---- ?ref=KODE → lewat server dulu ---- */
  var ref = params.get("ref") || "";
  if (slug && /^[A-Za-z0-9]{4,16}$/.test(ref)) {
    location.replace("/r/" + ref.toUpperCase() + "/" + slug);
    return;
  }

  function hilang() {
    tampil($("p-muat"), false);
    tampil($("p-hilang"), true);
    document.title = "Produk tidak ditemukan — Invishar";
  }

  if (!/^[a-z0-9-]+$/.test(slug)) {
    hilang();
    return;
  }

  /* Server Domainesia menyimpan salinan (cache) berkas JSON dan tidak
     menghiraukan kepala no-cache dari peramban. Penanda waktu yang berganti
     tiap 30 detik memastikan perubahan dari panel tampil paling lama 30 detik
     kemudian, tanpa membebani server di setiap kunjungan. */
  var penanda = Math.floor(Date.now() / 30000);
  fetch("/data/produk.json?t=" + penanda, { cache: "no-cache" })
    .then(function (jawab) {
      if (!jawab.ok) throw new Error(String(jawab.status));
      return jawab.json();
    })
    .then(function (data) {
      var produk = (data.produk || []).filter(function (p) { return p.slug === slug; })[0];
      if (!produk) { hilang(); return; }
      pasang(produk);
    })
    .catch(hilang);

  /* ------------------------------------------------------------ Isi halaman */
  function teks(el, isi) { if (el) el.textContent = isi || ""; }

  function pasang(p) {
    document.title = p.nama + " — Invishar";
    var meta = document.querySelector('meta[name="description"]');
    if (meta && (p.tagline || p.ringkas)) meta.setAttribute("content", p.tagline || p.ringkas);

    teks($("p-crumb"), p.nama);
    teks($("p-jenis"), p.jenis_label);
    teks($("p-nama"), p.nama);
    teks($("p-tagline"), p.tagline);
    tampil($("p-tagline"), !!p.tagline);
    teks($("p-ringkas"), p.ringkas);
    tampil($("p-ringkas"), !!p.ringkas);

    if (p.kelas) {
      $("p-kelas").href = p.kelas;
      tampil($("p-kelas"), true);
    }

    /* --- harga & tombol --- */
    var penawaran = !p.url_aksi;               // tanpa alamat aksi = pakai form
    var tujuan = penawaran ? "#penawaran" : p.url_aksi;
    var label = p.label_tombol || "Beli sekarang";

    teks($("p-harga-label"), p.jenis === "penawaran" ? "Perkiraan biaya" : "Harga");
    teks($("p-harga"), p.harga_teks || "Hubungi kami");
    teks($("p-harga-sub"), {
      sekali: "Sekali bayar.",
      langganan: "Dibayar per bulan.",
      penawaran: "Biaya pasti diberikan setelah kami memahami kebutuhan Anda.",
      eksternal: "Pendaftaran dan pembayaran dilakukan di aplikasinya langsung."
    }[p.jenis] || "");

    ["p-tombol", "p-tombol-2", "p-tombol-3"].forEach(function (id) {
      var t = $(id);
      t.textContent = label;
      t.href = tujuan;
      if (p.jenis === "eksternal") t.rel = "noopener";
    });
    teks($("p-harga-2"), p.harga_teks);
    teks($("p-harga-3"), p.harga_teks || p.nama);

    /* Hanya janji yang benar-benar dijalankan sistem dan situs ini. */
    var janji = {
      sekali: ["Bayar lewat transfer bank, e-wallet, atau QRIS", "Akses dikirim ke WhatsApp Anda setelah pembayaran terkonfirmasi", "Ada pertanyaan? Kami balas lewat WhatsApp"],
      langganan: ["Bulan pertama dibayar sekarang", "Tagihan bulan berikutnya dikirim lewat WhatsApp", "Ada pertanyaan? Kami balas lewat WhatsApp"],
      penawaran: ["Konsultasi awal gratis", "Rincian biaya jelas sebelum mulai", "Dikerjakan bertahap, bisa dicoba tiap dua pekan"],
      eksternal: ["Dikembangkan dan dirawat oleh Invishar"]
    }[p.jenis] || [];
    var wadahJanji = $("p-janji");
    janji.forEach(function (j) {
      var li = document.createElement("li");
      li.textContent = j;
      wadahJanji.appendChild(li);
    });

    /* --- gambar --- */
    if (p.gambar) {
      var img = $("p-gambar");
      img.src = p.gambar;
      img.alt = p.nama;
      tampil($("p-gambar-wadah"), true);
    }

    /* --- manfaat & penjelasan --- */
    var adaManfaat = (p.manfaat || []).length > 0;
    var adaIsi = (p.isi || []).length > 0;
    (p.manfaat || []).forEach(function (m) {
      var li = document.createElement("li");
      li.textContent = m;
      $("p-manfaat").appendChild(li);
    });
    (p.isi || []).forEach(function (par) {
      var el = document.createElement("p");
      el.textContent = par;
      $("p-teks").appendChild(el);
    });
    tampil($("p-manfaat-wadah"), adaManfaat);
    tampil($("p-teks"), adaIsi);
    tampil($("p-bagian-isi"), adaManfaat || adaIsi);

    /* --- tanya jawab --- */
    pasangTanya(p.tanya || []);

    /* --- form penawaran --- */
    if (penawaran) {
      $("p-form-produk").value = p.slug;
      tampil($("penawaran"), true);
      pasangForm();
    }

    tampil($("p-muat"), false);
    tampil($("p-isi"), true);
    tampil($("p-bilah"), true);
    document.body.classList.add("ada-bilah-beli");
  }

  function pasangTanya(daftar) {
    var wadah = $("p-tanya");
    if (!daftar.length) return;
    daftar.forEach(function (t, i) {
      var baris = document.createElement("div");
      baris.className = "faq-row";

      var tombol = document.createElement("button");
      tombol.className = "faq-q";
      tombol.type = "button";
      tombol.setAttribute("aria-expanded", i === 0 ? "true" : "false");
      tombol.setAttribute("aria-controls", "p-faq-" + i);
      var q = document.createElement("span");
      q.textContent = t.q;
      var ikon = document.createElement("span");
      ikon.className = "faq-icon";
      ikon.setAttribute("aria-hidden", "true");
      ikon.textContent = "+";
      tombol.appendChild(q);
      tombol.appendChild(ikon);

      var jawab = document.createElement("div");
      jawab.className = "faq-a" + (i === 0 ? " is-open" : "");
      jawab.id = "p-faq-" + i;
      var dalam = document.createElement("div");
      var a = document.createElement("p");
      a.textContent = t.a;
      dalam.appendChild(a);
      jawab.appendChild(dalam);

      tombol.addEventListener("click", function () {
        var terbuka = tombol.getAttribute("aria-expanded") === "true";
        Array.prototype.forEach.call(wadah.querySelectorAll(".faq-q"), function (lain) {
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
      wadah.appendChild(baris);
    });
    tampil($("p-bagian-tanya"), true);
  }

  /* ------------------------------------------------------ Form penawaran */
  function pasangForm() {
    var form = $("p-form");
    var tombol = $("p-form-tombol");
    var catatan = $("p-form-catatan");

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      form.classList.remove("is-failed");

      var nama = form.nama.value.trim();
      var wa = form.whatsapp.value.trim();
      var pesan = form.pesan.value.trim();
      if (!nama || !wa || !pesan) {
        form.classList.add("is-failed");
        catatan.textContent = "Nama, WhatsApp, dan kebutuhan wajib diisi.";
        (!nama ? form.nama : !wa ? form.whatsapp : form.pesan).focus();
        return;
      }

      tombol.disabled = true;
      tombol.textContent = "Mengirim…";

      fetch("/panel/api-pesan.php", { method: "POST", body: new FormData(form), credentials: "same-origin" })
        .then(function (jawab) {
          return jawab.json().catch(function () { return {}; }).then(function (isi) {
            if (!jawab.ok) throw new Error(isi.galat || "Gagal terkirim.");
          });
        })
        .then(function () {
          form.reset();
          tombol.textContent = "Terkirim ✓";
          catatan.textContent = "Terima kasih! Kami balas lewat WhatsApp dalam 1–2 hari kerja.";
        })
        .catch(function (galat) {
          tombol.disabled = false;
          tombol.textContent = "Kirim permintaan";
          form.classList.add("is-failed");
          catatan.textContent = (galat && galat.message ? galat.message + " " : "") + "Atau hubungi kami langsung lewat WhatsApp.";
        });
    });
  }
})();
