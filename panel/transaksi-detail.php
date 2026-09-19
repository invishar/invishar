<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
require_once __DIR__ . '/inc/order.php';
wajibMasuk();
wajibPenjualanSiap();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$t = ambilSatu('SELECT * FROM transaksi WHERE id = ?', [$id]);
if ($t === null) {
    pesan('Transaksi tidak ditemukan.', 'buruk');
    pergi(tautan('transaksi'));
}
$kembali = tautan('transaksi/' . $id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $aksi = masukan('aksi');
    $catatan = masukan('catatan');

    $peta = [
        'lunas'  => ['lunas', 'Ditandai lunas oleh admin' . (masukan('metode') !== '' ? ' · ' . masukan('metode') : '')],
        'batal'  => ['gagal', 'Dibatalkan oleh admin'],
        'refund' => ['refund', 'Dana dikembalikan'],
    ];
    if (isset($peta[$aksi])) {
        [$statusBaru, $keterangan] = $peta[$aksi];
        if (($aksi === 'refund') && $catatan === '') {
            pesan('Tulis alasan pengembalian dana — tercatat di riwayat dan terlihat affiliator.', 'buruk');
            pergi($kembali);
        }
        $tambahan = $aksi === 'lunas' && masukan('metode') !== '' ? ['metode' => masukan('metode')] : [];
        $hasil = ubahStatusTransaksi($id, $statusBaru, 'admin', $keterangan . ($catatan !== '' ? ': ' . $catatan : ''), null, $tambahan);
        if ($hasil['berubah']) {
            catatLog('transaksi ' . $statusBaru, $t['kode_order']);
            pesan('Transaksi ' . $t['kode_order'] . ' sekarang ' . strtolower(STATUS_TRANSAKSI[$statusBaru]) . '.');
        } else {
            pesan('Status tidak berubah: dari "' . STATUS_TRANSAKSI[$t['status']] . '" tidak bisa menjadi "' . STATUS_TRANSAKSI[$statusBaru] . '".', 'peringatan');
        }
    }
    if ($aksi === 'proses') {
        $galat = ubahProsesTransaksi($id, masukan('ke'), $catatan);
        if ($galat) {
            pesan($galat, 'buruk');
        } else {
            pesan('Status pesanan sekarang ' . (STATUS_PROSES[masukan('ke')] ?? masukan('ke')) . '.');
        }
    }
    if ($aksi === 'catatan') {
        q('UPDATE transaksi SET catatan = ?, diperbarui_pada = NOW() WHERE id = ?', [$catatan, $id]);
        pesan('Catatan disimpan.');
    }
    pergi($kembali);
}

$produk = ambilSatu('SELECT * FROM produk WHERE id = ?', [$t['produk_id']]);
$aff = $t['affiliate_id'] ? ambilSatu('SELECT * FROM affiliate WHERE id = ?', [$t['affiliate_id']]) : null;
$komisi = ambilSemua(
    'SELECT k.*, ' . SQL_KEADAAN_KOMISI . ' FROM komisi k LEFT JOIN penarikan tp ON tp.id = k.penarikan_id WHERE k.transaksi_id = ? ORDER BY k.id',
    [$id]
);
$riwayat = ambilSemua('SELECT * FROM transaksi_riwayat WHERE transaksi_id = ? ORDER BY dibuat_pada DESC, id DESC', [$id]);
$langganan = $t['langganan_id'] ? ambilSatu('SELECT * FROM langganan WHERE id = ?', [$t['langganan_id']]) : null;
$periodeTerakhir = $langganan ? (int) ambilNilai("SELECT MAX(periode_ke) FROM transaksi WHERE langganan_id = ? AND status = 'lunas'", [$langganan['id']]) : 0;
$order = $t['order_jasa_id'] ? ambilSatu('SELECT id, nama FROM order_jasa WHERE id = ?', [$t['order_jasa_id']]) : null;

/** Kenapa transaksi ini tidak menghasilkan komisi — supaya admin tidak menebak. */
function alasanTanpaKomisi(array $t, ?array $produk, ?array $aff): string
{
    if (!$aff) {
        return 'Pembeli tidak datang lewat link affiliate.';
    }
    if ((int) $t['beli_sendiri'] === 1) {
        return 'Pembeli adalah affiliator itu sendiri (surel atau WhatsApp sama), jadi tidak ada komisi.';
    }
    if ($t['status'] !== 'lunas' && $t['status'] !== 'refund') {
        return 'Komisi dibuat begitu transaksi lunas.';
    }
    if ($aff['status'] !== 'aktif') {
        return 'Affiliator sedang ' . strtolower(STATUS_AFFILIATE[$aff['status']] ?? $aff['status']) . ' saat pembayaran lunas.';
    }
    if ($produk && !(int) $produk['affiliate_aktif']) {
        return 'Produk ini sedang tidak dibuka untuk affiliate saat pembayaran lunas.';
    }
    if ($produk && $produk['jenis'] === 'langganan' && (int) $t['periode_ke'] > bulanBerulang($produk)) {
        return 'Komisi langganan hanya sampai bulan ke-' . bulanBerulang($produk) . '; ini bulan ke-' . (int) $t['periode_ke'] . '.';
    }
    return 'Tidak ada komisi.';
}

$sumberLabel = ['checkout' => 'Checkout', 'uji' => 'Simulasi uji', 'midtrans' => 'Midtrans', 'admin' => 'Admin'];

$judul = 'Pesanan ' . $t['kode_order'];
$menu  = 'transaksi';
require __DIR__ . '/inc/kepala.php';
?>

<p class="remah">
  <a href="<?= tautan('transaksi') ?>">&larr; Semua transaksi</a>
  <?php if ($order): ?><a href="<?= tautan('order/' . (int) $order['id']) ?>">Order jasa dari <?= e($order['nama']) ?> &rarr;</a><?php endif; ?>
</p>

<div class="dua-kolom-lebar">
  <div>
    <section class="kotak">
      <div class="kotak-kepala">
        <h2><?= e(rupiah((int) $t['jumlah'])) ?></h2>
        <span class="tanda tanda-<?= e($t['status']) ?>"><?= e(STATUS_TRANSAKSI[$t['status']]) ?></span>
      </div>
      <dl class="keadaan">
        <dt>Produk</dt>
        <dd>
          <a href="<?= tautan('produk/' . (int) $t['produk_id']) ?>"><?= e($produk['nama'] ?? '—') ?></a>
          <?php if ($langganan): ?> · langganan bulan ke-<?= (int) $t['periode_ke'] ?><?php endif; ?>
        </dd>
        <dt>Pembeli</dt>
        <dd>
          <?= e($t['pembeli_nama']) ?>
          <?php if ($t['whatsapp']): ?><br><a href="https://wa.me/<?= e(normalWa($t['whatsapp'])) ?>" target="_blank" rel="noopener"><?= e($t['whatsapp']) ?></a><?php endif; ?>
          <?php if ($t['surel']): ?><br><a href="mailto:<?= e($t['surel']) ?>"><?= e($t['surel']) ?></a><?php endif; ?>
        </dd>
        <dt>Pembayaran</dt>
        <dd>
          <?= e(['uji' => 'Simulasi (mode uji)', 'midtrans' => 'Midtrans', 'manual' => 'Dicatat manual'][$t['gerbang']] ?? $t['gerbang']) ?>
          <?= $t['metode'] ? ' · ' . e($t['metode']) : '' ?>
          <?= $t['gerbang_ref'] && $t['gerbang'] === 'midtrans' ? '<br><span class="teks-kecil">ID Midtrans: ' . e($t['gerbang_ref']) . '</span>' : '' ?>
        </dd>
        <dt>Dibuat</dt><dd><?= e(waktuIndo($t['dibuat_pada'])) ?></dd>
        <?php if ($t['dibayar_pada']): ?><dt>Dibayar</dt><dd><?= e(waktuIndo($t['dibayar_pada'])) ?></dd><?php endif; ?>
      </dl>
    </section>

    <!-- ============ Status proses ============ -->
    <?php $proses = $t['status_proses']; ?>
    <section class="kotak">
      <div class="kotak-kepala">
        <h2>Status pesanan</h2>
        <span class="tanda tanda-proses-<?= e($proses) ?>"><?= e(STATUS_PROSES[$proses]) ?></span>
      </div>
      <ol class="alur-proses" aria-label="Tahap pesanan">
        <?php foreach (['baru' => 'Baru masuk', 'diproses' => 'Diproses', 'selesai' => 'Selesai'] as $kunci => $label): ?>
          <?php $urut = array_search($kunci, ['baru', 'diproses', 'selesai'], true); $kini = array_search($proses, ['baru', 'diproses', 'selesai'], true); ?>
          <li class="<?= $proses === 'batal' ? '' : ($kunci === $proses ? 'is-kini' : ($kini !== false && $urut < $kini ? 'is-lewat' : '')) ?>"><?= e($label) ?></li>
        <?php endforeach; ?>
      </ol>

      <?php if ($t['status'] === 'menunggu'): ?>
        <p class="bagian-sub" style="margin:0">Pesanan diproses setelah pembayarannya lunas.</p>
      <?php elseif ($t['status'] === 'lunas' && $proses !== 'batal'): ?>
        <form method="post" class="aksi-kisi">
          <?= csrfInput() ?>
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="aksi" value="proses">
          <?php if ($proses === 'baru'): ?>
            <button class="tbl tbl-utama" type="submit" name="ke" value="diproses">Mulai proses</button>
            <button class="tbl" type="submit" name="ke" value="selesai">Langsung selesai</button>
          <?php elseif ($proses === 'diproses'): ?>
            <button class="tbl tbl-utama" type="submit" name="ke" value="selesai">Tandai selesai</button>
          <?php else: ?>
            <button class="tbl tbl-kecil" type="submit" name="ke" value="diproses">Buka lagi (diproses)</button>
          <?php endif; ?>
        </form>
        <p class="petunjuk"><?= $produk && ($produk['kategori'] ?? '') === 'kelas'
            ? 'Untuk kelas: kirim akses ke WhatsApp pembeli, lalu tandai selesai.'
            : 'Selesai = pesanan sudah diserahkan ke pembeli.' ?></p>
        <?php if ($proses !== 'selesai'): ?>
          <button class="tbl tbl-kecil tbl-bahaya" type="button" data-buka="#form-batal" style="margin-top:12px">Batalkan pesanan…</button>
          <div class="lipatan-aksi" id="form-batal" hidden>
            <p class="bagian-sub">Pesanan ini sudah dibayar. Kalau dananya dikembalikan, pakai <strong>Kembalikan dana (refund)</strong> di bawah —
              komisi affiliate ikut dibatalkan. Kalau tidak ada dana yang dikembalikan, batalkan saja di sini.</p>
            <form method="post" class="form-panel">
              <?= csrfInput() ?>
              <input type="hidden" name="id" value="<?= $id ?>">
              <input type="hidden" name="aksi" value="proses">
              <div class="bidang">
                <label for="alasan-batal">Alasan</label>
                <input id="alasan-batal" name="catatan" type="text" placeholder="Mis. pembeli minta ganti ke produk lain">
              </div>
              <div><button class="tbl tbl-bahaya" type="submit" name="ke" value="batal"
                           data-pastikan="Batalkan pesanan tanpa mengembalikan dana? Komisi affiliate tetap berlaku.">Batalkan tanpa refund</button></div>
            </form>
          </div>
        <?php endif; ?>
      <?php elseif ($proses === 'batal'): ?>
        <p class="bagian-sub" style="margin:0">Pesanan dibatalkan<?= $t['status'] !== 'lunas' ? ' (' . e(strtolower(STATUS_TRANSAKSI[$t['status']])) . ')' : '' ?>.</p>
        <?php if ($t['status'] === 'lunas'): ?>
          <form method="post" style="margin-top:10px">
            <?= csrfInput() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="aksi" value="proses">
            <button class="tbl tbl-kecil" type="submit" name="ke" value="baru">Buka lagi</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <!-- ============ Tindakan ============ -->
    <?php if ($t['status'] === 'menunggu'): ?>
      <section class="kotak">
        <div class="kotak-kepala"><h2>Tindakan</h2></div>
        <p class="bagian-sub">Pembeli membayar di luar sistem (transfer, tunai)? Tandai lunas di sini — komisi affiliate ikut dibuat.</p>
        <form method="post" class="form-sebaris">
          <?= csrfInput() ?>
          <input type="hidden" name="id" value="<?= $id ?>">
          <div class="bidang" style="max-width:200px">
            <label for="metode">Cara bayar</label>
            <select id="metode" name="metode">
              <?php foreach (METODE_MANUAL as $m): ?><option><?= e($m) ?></option><?php endforeach; ?>
            </select>
          </div>
          <button class="tbl tbl-utama" type="submit" name="aksi" value="lunas"
                  data-pastikan="Tandai <?= e($t['kode_order']) ?> sebagai lunas <?= e(rupiah((int) $t['jumlah'])) ?>? Pastikan uangnya sudah masuk.">Tandai lunas</button>
          <button class="tbl tbl-bahaya" type="submit" name="aksi" value="batal"
                  data-pastikan="Batalkan transaksi ini? Pembeli harus checkout ulang kalau ingin membeli.">Batalkan</button>
        </form>
      </section>
    <?php elseif ($t['status'] === 'lunas'): ?>
      <section class="kotak">
        <div class="kotak-kepala"><h2>Tindakan</h2></div>
        <?php if ($langganan && $produk && $produk['jenis'] === 'langganan'): ?>
          <p class="bagian-sub">Langganan <?= e($langganan['pembeli_nama']) ?> — pembayaran terakhir bulan ke-<?= $periodeTerakhir ?>.</p>
          <div class="aksi-kisi" style="margin-bottom:18px">
            <a class="tbl tbl-utama" href="<?= tautan('transaksi-catat') ?>?langganan=<?= (int) $langganan['id'] ?>">Catat pembayaran bulan ke-<?= $periodeTerakhir + 1 ?></a>
          </div>
        <?php endif; ?>
        <button class="tbl tbl-bahaya tbl-kecil" type="button" data-buka="#form-refund">Kembalikan dana (refund)…</button>
        <form method="post" class="form-panel lipatan-aksi" id="form-refund" hidden>
          <?= csrfInput() ?>
          <input type="hidden" name="id" value="<?= $id ?>">
          <div class="bidang">
            <label for="alasan-refund">Alasan pengembalian</label>
            <input id="alasan-refund" name="catatan" type="text" placeholder="Mis. pembeli membatalkan dalam 3 hari" required>
          </div>
          <p class="petunjuk">Catat di sini setelah dana benar-benar dikembalikan. Komisi affiliate yang belum ditarik dibatalkan;
            yang sudah ditarik dipotong dari komisi berikutnya.</p>
          <div><button class="tbl tbl-bahaya" type="submit" name="aksi" value="refund" data-pastikan="Tandai dana transaksi ini sudah dikembalikan?">Tandai sudah dikembalikan</button></div>
        </form>
      </section>
    <?php endif; ?>

    <section class="kotak">
      <div class="kotak-kepala"><h2>Riwayat</h2></div>
      <ul class="garis-waktu">
        <?php foreach ($riwayat as $r): ?>
          <?php if (str_starts_with((string) $r['catatan'], 'Proses: ')): /* perubahan status proses, bukan pembayaran */ ?>
            <?php [$judulProses, $ketProses] = array_pad(explode(' · ', substr((string) $r['catatan'], 8), 2), 2, ''); ?>
            <li>
              <span class="gw-judul"><?= e($judulProses) ?></span>
              <span class="gw-sub">Status pesanan · <?= e(waktuIndo($r['dibuat_pada'])) ?><?= $ketProses !== '' ? ' · ' . e($ketProses) : '' ?></span>
            </li>
          <?php else: ?>
            <li>
              <span class="gw-judul">
                <?= $r['status_lama'] && $r['status_lama'] !== $r['status_baru'] ? e(STATUS_TRANSAKSI[$r['status_lama']] ?? $r['status_lama']) . ' → ' : '' ?><?= e(STATUS_TRANSAKSI[$r['status_baru']] ?? $r['status_baru']) ?>
              </span>
              <span class="gw-sub"><?= e($sumberLabel[$r['sumber']] ?? $r['sumber']) ?> · <?= e(waktuIndo($r['dibuat_pada'])) ?><?= $r['catatan'] ? ' · ' . e($r['catatan']) : '' ?></span>
            </li>
          <?php endif; ?>
        <?php endforeach; ?>
      </ul>
    </section>
  </div>

  <aside>
    <section class="kotak">
      <div class="kotak-kepala"><h2>Affiliate</h2></div>
      <?php if ($aff): ?>
        <p style="margin:0 0 12px">
          <a class="tabel-utama" href="<?= tautan('affiliate/' . (int) $aff['id']) ?>"><?= e($aff['nama']) ?></a>
          <span class="tabel-kode"> · <?= e((string) $aff['kode']) ?></span>
        </p>
      <?php endif; ?>
      <?php if ($komisi): ?>
        <ul class="daftar-ringkas">
          <?php foreach ($komisi as $k): ?>
            <?php $keadaan = keadaanKomisi($k); ?>
            <li>
              <span>
                <span class="dr-judul<?= (int) $k['jumlah'] < 0 ? ' minus' : '' ?>"><?= e(rupiah((int) $k['jumlah'])) ?></span>
                <span class="dr-sub"><?= $k['jenis'] === 'penyesuaian' ? 'Penyesuaian' : e(teksFee((string) $k['fee_jenis'], $k['fee_nilai'])) . ' dari ' . e(rupiah((int) $k['dasar'])) ?></span>
              </span>
              <span class="tanda tanda-<?= e($keadaan) ?>"><?= e(KEADAAN_KOMISI[$keadaan]) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="teks-kecil" style="margin:0"><?= e(alasanTanpaKomisi($t, $produk, $aff)) ?></p>
      <?php endif; ?>
    </section>

    <section class="kotak">
      <div class="kotak-kepala"><h2>Catatan</h2></div>
      <form method="post" class="form-panel">
        <?= csrfInput() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <textarea name="catatan" rows="3" placeholder="Mis. akses sudah dikirim lewat WA"><?= e((string) $t['catatan']) ?></textarea>
        <div class="form-aksi"><button class="tbl tbl-kecil" type="submit" name="aksi" value="catatan">Simpan catatan</button></div>
      </form>
    </section>
  </aside>
</div>

<?php require __DIR__ . '/inc/kaki.php'; ?>
