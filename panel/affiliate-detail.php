<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
require_once __DIR__ . '/inc/affiliate.php';
wajibMasuk();
wajibPenjualanSiap();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$a = ambilSatu('SELECT * FROM affiliate WHERE id = ?', [$id]);
if ($a === null) {
    pesan('Affiliator tidak ditemukan.', 'buruk');
    pergi(tautan('affiliate'));
}
$kembali = tautan('affiliate/' . $id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $aksi = masukan('aksi');

    switch ($aksi) {
        case 'setujui':
            if (!in_array($a['status'], ['menunggu', 'ditolak'], true)) {
                break;
            }
            $kode = strtoupper(masukan('kode'));
            if (!kodeSah($kode)) {
                pesan('Kode harus 4–16 huruf besar atau angka, tanpa spasi dan tanda baca.', 'buruk');
                pergi($kembali);
            }
            if (ambilNilai('SELECT 1 FROM affiliate WHERE kode = ? AND id <> ?', [$kode, $id]) !== null) {
                pesan('Kode ' . $kode . ' sudah dipakai affiliator lain. Pilih kode lain.', 'buruk');
                pergi($kembali);
            }
            q("UPDATE affiliate SET status = 'aktif', kode = ?, disetujui_pada = NOW() WHERE id = ?", [$kode, $id]);
            catatLog('setujui affiliate', $a['nama'] . ' · ' . $kode);
            pesan($a['nama'] . ' disetujui dengan kode ' . $kode . '. Kabari lewat WhatsApp bahwa ia sudah bisa masuk.');
            break;

        case 'tolak':
            if ($a['status'] !== 'menunggu') {
                break;
            }
            $alasan = masukan('alasan');
            q("UPDATE affiliate SET status = 'ditolak', catatan_admin = CONCAT_WS('\n', catatan_admin, ?) WHERE id = ?",
                ['Ditolak ' . date('d/m/Y') . ($alasan !== '' ? ': ' . $alasan : ''), $id]);
            catatLog('tolak affiliate', $a['nama'] . ($alasan !== '' ? ' · ' . $alasan : ''));
            pesan('Pendaftaran ' . $a['nama'] . ' ditolak.');
            break;

        case 'bekukan':
            if ($a['status'] !== 'aktif') {
                break;
            }
            $alasan = masukan('alasan');
            q("UPDATE affiliate SET status = 'dibekukan', catatan_admin = CONCAT_WS('\n', catatan_admin, ?) WHERE id = ?",
                ['Dibekukan ' . date('d/m/Y') . ($alasan !== '' ? ': ' . $alasan : ''), $id]);
            catatLog('bekukan affiliate', $a['nama'] . ($alasan !== '' ? ' · ' . $alasan : ''));
            pesan($a['nama'] . ' dibekukan. Link-nya berhenti mencatat penjualan baru.');
            break;

        case 'aktifkan':
            if ($a['status'] !== 'dibekukan') {
                break;
            }
            q("UPDATE affiliate SET status = 'aktif', catatan_admin = CONCAT_WS('\n', catatan_admin, ?) WHERE id = ?",
                ['Diaktifkan kembali ' . date('d/m/Y'), $id]);
            catatLog('aktifkan affiliate', $a['nama']);
            pesan($a['nama'] . ' aktif kembali.');
            break;

        case 'reset_sandi':
            $sandi = strtolower(kodeAcak(4)) . '-' . strtolower(kodeAcak(4)) . '-' . strtolower(kodeAcak(4));
            q('UPDATE affiliate SET kata_sandi_hash = ? WHERE id = ?', [password_hash($sandi, PASSWORD_DEFAULT), $id]);
            catatLog('atur ulang sandi affiliate', $a['nama']);
            // Ditampilkan sekali di halaman ini, tidak ikut pesan melayang yang hilang sendiri.
            $_SESSION['sandi_sementara'] = ['id' => $id, 'sandi' => $sandi];
            break;

        case 'catatan':
            q('UPDATE affiliate SET catatan_admin = ? WHERE id = ?', [masukan('catatan_admin'), $id]);
            pesan('Catatan disimpan.');
            break;

        case 'cairkan':
            $komisiId = masukan('komisi_id') !== '' ? (int) masukan('komisi_id') : null;
            $n = cairkanSekarang($id, $komisiId);
            if ($n > 0) {
                catatLog('cairkan komisi lebih awal', $a['nama'] . ' · ' . $n . ' komisi');
                pesan($n . ' komisi dicairkan dan sekarang masuk saldo siap ditarik.');
            } else {
                pesan('Tidak ada komisi tertahan yang bisa dicairkan.', 'peringatan');
            }
            break;

        case 'hapus':
            $adaData = ambilNilai(
                'SELECT (SELECT COUNT(*) FROM komisi WHERE affiliate_id = ?) + (SELECT COUNT(*) FROM transaksi WHERE affiliate_id = ?)
                        + (SELECT COUNT(*) FROM penarikan WHERE affiliate_id = ?) + (SELECT COUNT(*) FROM langganan WHERE affiliate_id = ?)',
                [$id, $id, $id, $id]
            );
            if ((int) $adaData > 0) {
                pesan('Affiliator ini sudah punya transaksi atau komisi, jadi tidak bisa dihapus. Bekukan saja.', 'buruk');
                break;
            }
            q('UPDATE order_jasa SET affiliate_id = NULL WHERE affiliate_id = ?', [$id]);
            q('DELETE FROM affiliate WHERE id = ?', [$id]);
            catatLog('hapus affiliate', $a['nama']);
            pesan('Akun ' . $a['nama'] . ' dihapus.');
            pergi(tautan('affiliate'));
    }
    pergi($kembali);
}

$saldo = saldoAffiliate($id);
$klik30 = (int) ambilNilai('SELECT COUNT(*) FROM affiliate_klik WHERE affiliate_id = ? AND dibuat_pada > (NOW() - INTERVAL 30 DAY)', [$id]);
$terjual = (int) ambilNilai("SELECT COUNT(*) FROM transaksi WHERE affiliate_id = ? AND status = 'lunas' AND beli_sendiri = 0", [$id]);
$omzet = (int) ambilNilai("SELECT COALESCE(SUM(jumlah), 0) FROM transaksi WHERE affiliate_id = ? AND status = 'lunas' AND beli_sendiri = 0", [$id]);
$komisi = ambilSemua(
    'SELECT k.*, p.nama AS produk_nama, t.kode_order, t.pembeli_nama, ' . SQL_KEADAAN_KOMISI . '
       FROM komisi k
       LEFT JOIN produk p     ON p.id = k.produk_id
       LEFT JOIN transaksi t  ON t.id = k.transaksi_id
       LEFT JOIN penarikan tp ON tp.id = k.penarikan_id
      WHERE k.affiliate_id = ?
      ORDER BY k.dibuat_pada DESC, k.id DESC LIMIT 200',
    [$id]
);
$jumlahTertahan = count(array_filter($komisi, function ($k) {
    return keadaanKomisi($k) === 'tertahan';
}));
$penarikan = ambilSemua('SELECT * FROM penarikan WHERE affiliate_id = ? ORDER BY diajukan_pada DESC LIMIT 20', [$id]);
$sandiSementara = ($_SESSION['sandi_sementara']['id'] ?? 0) === $id ? $_SESSION['sandi_sementara']['sandi'] : null;
unset($_SESSION['sandi_sementara']);
$waLink = 'https://wa.me/' . normalWa($a['whatsapp']);

$judul = $a['nama'];
$menu  = 'affiliate';
require __DIR__ . '/inc/kepala.php';
?>

<p class="remah"><a href="<?= tautan('affiliate') ?>">&larr; Semua affiliator</a></p>

<?php if ($sandiSementara): ?>
  <div class="pita pita-baik" role="alert">
    <span><strong>Kata sandi sementara:</strong> <code style="font-size:15px"><?= e($sandiSementara) ?></code><br>
      Kirim ke <?= e($a['nama']) ?> lewat WhatsApp dan minta ia menggantinya di halaman Profil. Kata sandi ini hanya tampil sekali.</span>
    <button class="tbl tbl-kecil" type="button" data-salin="<?= e($sandiSementara) ?>">Salin</button>
  </div>
<?php endif; ?>

<?php if ($a['status'] === 'menunggu' || $a['status'] === 'ditolak'): ?>
  <!-- ============ Persetujuan ============ -->
  <section class="kotak" style="border-color:var(--green-300)">
    <div class="kotak-kepala">
      <h2><?= $a['status'] === 'menunggu' ? 'Tinjau pendaftaran' : 'Pendaftaran ditolak' ?></h2>
      <span class="tanda tanda-<?= e($a['status']) ?>"><?= e(STATUS_AFFILIATE[$a['status']]) ?></span>
    </div>
    <p class="bagian-sub">Rencana promosi dari <?= e($a['nama']) ?>:</p>
    <p class="kutipan" style="margin-bottom:18px"><?= nl2br(e($a['kanal_promosi'])) ?></p>

    <form method="post" class="form-sebaris">
      <?= csrfInput() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="bidang" style="max-width:260px">
        <label for="kode">Kode affiliate</label>
        <input id="kode" name="kode" type="text" value="<?= e(kodeAffiliateBaru()) ?>" maxlength="16"
               style="text-transform:uppercase;font-family:ui-monospace,monospace" required>
      </div>
      <button class="tbl tbl-utama" type="submit" name="aksi" value="setujui">Setujui &amp; aktifkan</button>
      <?php if ($a['status'] === 'menunggu'): ?>
        <button class="tbl tbl-bahaya" type="button" data-buka="#form-tolak">Tolak…</button>
      <?php endif; ?>
    </form>
    <p class="petunjuk">Kode ini muncul di semua link affiliator (<?= e(preg_replace('#^https?://#', '', urlSitus())) ?>/r/<b>KODE</b>/produk).
      Boleh diganti dengan kode yang mudah diingat, mis. nama panggilannya. Setelah disetujui, kode dikunci supaya link yang tersebar tidak mati.</p>

    <form method="post" class="form-sebaris lipatan-aksi" id="form-tolak" hidden>
      <?= csrfInput() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="bidang">
        <label for="alasan-tolak">Alasan (catatan internal, tidak dikirim)</label>
        <input id="alasan-tolak" name="alasan" type="text" placeholder="Mis. kanal promosi tidak jelas">
      </div>
      <button class="tbl tbl-bahaya" type="submit" name="aksi" value="tolak" data-pastikan="Tolak pendaftaran <?= e($a['nama']) ?>?">Tolak pendaftaran</button>
    </form>
  </section>
<?php endif; ?>

<?php if (in_array($a['status'], ['aktif', 'dibekukan'], true)): ?>
  <div class="angka-kisi">
    <div class="angka angka-diam angka-sorot"><span class="angka-num angka-rp<?= $saldo['siap'] < 0 ? ' minus' : '' ?>"><?= e(rupiah($saldo['siap'])) ?></span><span class="angka-lbl">Siap ditarik</span></div>
    <div class="angka angka-diam"><span class="angka-num angka-rp"><?= e(rupiah($saldo['tertahan'])) ?></span><span class="angka-lbl">Tertahan</span></div>
    <div class="angka angka-diam"><span class="angka-num angka-rp"><?= e(rupiah($saldo['diproses'])) ?></span><span class="angka-lbl">Sedang diproses</span></div>
    <div class="angka angka-diam"><span class="angka-num angka-rp"><?= e(rupiah($saldo['dicairkan'])) ?></span><span class="angka-lbl">Sudah dicairkan</span></div>
  </div>
<?php endif; ?>

<?php $akunJalan = in_array($a['status'], ['aktif', 'dibekukan'], true); ?>
<div class="<?= $akunJalan ? 'dua-kolom-lebar' : 'dua-kolom' ?>">
  <?php if ($akunJalan): ?>
  <div>
      <!-- ============ Komisi ============ -->
      <section class="kotak">
        <div class="kotak-kepala">
          <h2>Komisi</h2>
          <?php if ($jumlahTertahan > 0): ?>
            <form method="post" class="sebaris" style="margin-left:auto">
              <?= csrfInput() ?>
              <input type="hidden" name="id" value="<?= $id ?>">
              <button class="tbl tbl-kecil" type="submit" name="aksi" value="cairkan"
                      data-pastikan="Cairkan semua <?= $jumlahTertahan ?> komisi tertahan (<?= e(rupiah($saldo['tertahan'])) ?>) sekarang? Komisi langsung bisa ditarik oleh <?= e($a['nama']) ?>.">Cairkan semua yang tertahan</button>
            </form>
          <?php endif; ?>
        </div>

        <?php if (!$komisi): ?>
          <p class="kosong">Belum ada komisi.</p>
        <?php else: ?>
          <div class="tabel-bungkus" style="margin:0">
            <table class="tabel tabel-diam">
              <thead>
                <tr><th>Tanggal</th><th>Transaksi</th><th class="kanan">Komisi</th><th>Keadaan</th><th></th></tr>
              </thead>
              <tbody>
                <?php foreach ($komisi as $k): ?>
                  <?php $keadaan = keadaanKomisi($k); ?>
                  <tr>
                    <td class="tabel-tipis"><?= e(tanggalIndo($k['dibuat_pada'])) ?></td>
                    <td>
                      <?php if ($k['jenis'] === 'penyesuaian'): ?>
                        <span class="tebal">Penyesuaian</span><span class="tabel-sub"><?= e((string) $k['catatan']) ?></span>
                      <?php else: ?>
                        <a class="tabel-utama" href="<?= tautan('transaksi/' . (int) $k['transaksi_id']) ?>"><?= e($k['produk_nama'] ?? 'Produk') ?></a>
                        <span class="tabel-sub"><?= e((string) $k['kode_order']) ?> · <?= e(rupiah((int) $k['dasar'])) ?><?= $k['periode_ke'] ? ' · bln ' . (int) $k['periode_ke'] : '' ?></span>
                      <?php endif; ?>
                    </td>
                    <td class="kanan tebal<?= (int) $k['jumlah'] < 0 ? ' minus' : '' ?>"><?= e(rupiah((int) $k['jumlah'])) ?></td>
                    <td>
                      <span class="tanda tanda-<?= e($keadaan) ?>"><?= e(KEADAAN_KOMISI[$keadaan]) ?></span>
                      <?php if ($keadaan === 'tertahan'): ?><span class="tabel-sub">cair <?= e(tanggalIndo($k['cair_pada'])) ?></span><?php endif; ?>
                      <?php if ($k['dipercepat_pada']): ?><span class="tabel-sub">dipercepat <?= e(tanggalIndo($k['dipercepat_pada'])) ?></span><?php endif; ?>
                    </td>
                    <td class="kanan">
                      <?php if ($keadaan === 'tertahan'): ?>
                        <form method="post" class="sebaris">
                          <?= csrfInput() ?>
                          <input type="hidden" name="id" value="<?= $id ?>">
                          <input type="hidden" name="komisi_id" value="<?= (int) $k['id'] ?>">
                          <button class="tbl tbl-kecil" type="submit" name="aksi" value="cairkan"
                                  data-pastikan="Cairkan komisi <?= e(rupiah((int) $k['jumlah'])) ?> sekarang?">Cairkan</button>
                        </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <?php if ($penarikan): ?>
        <section class="kotak">
          <div class="kotak-kepala">
            <h2>Withdraw</h2>
            <a class="tautan-lain" href="<?= tautan('penarikan') ?>">Ke antrean withdraw &rarr;</a>
          </div>
          <ul class="daftar-ringkas">
            <?php foreach ($penarikan as $p): ?>
              <li>
                <a href="<?= tautan('penarikan/' . (int) $p['id']) ?>">
                  <span class="dr-judul"><?= e(rupiah((int) $p['jumlah'])) ?></span>
                  <span class="dr-sub">Diajukan <?= e(waktuIndo($p['diajukan_pada'])) ?><?= $p['referensi'] ? ' · ref ' . e($p['referensi']) : '' ?></span>
                </a>
                <span class="tanda tanda-<?= e($p['status']) ?>"><?= e(STATUS_PENARIKAN[$p['status']]) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endif; ?>
  </div>
  <?php endif; ?>

  <aside<?= $akunJalan ? '' : ' class="aside-lebur"' ?>>
    <!-- ============ Profil ============ -->
    <section class="kotak">
      <div class="kotak-kepala">
        <h2>Profil</h2>
        <span class="tanda tanda-<?= e($a['status']) ?>"><?= e(STATUS_AFFILIATE[$a['status']]) ?></span>
      </div>
      <dl class="keadaan">
        <?php if ($a['kode']): ?>
          <dt>Kode</dt><dd><span class="tabel-kode" style="font-size:15px"><?= e($a['kode']) ?></span></dd>
          <dt>Link beranda</dt>
          <dd><div class="salin-baris"><code><?= e(linkAffiliate($a['kode'])) ?></code><button class="tbl-salin" type="button" data-salin="<?= e(linkAffiliate($a['kode'])) ?>">Salin</button></div></dd>
        <?php endif; ?>
        <dt>WhatsApp</dt><dd><a href="<?= e($waLink) ?>" target="_blank" rel="noopener"><?= e($a['whatsapp']) ?></a></dd>
        <dt>Surel</dt><dd><a href="mailto:<?= e($a['surel']) ?>"><?= e($a['surel']) ?></a></dd>
        <dt>Rekening</dt>
        <dd><?= $a['bank_nomor'] ? e($a['bank_nama']) . ' · ' . e($a['bank_nomor']) . '<br>a.n. ' . e($a['bank_atas_nama']) : '<span class="teks-kecil">Belum diisi</span>' ?></dd>
        <?php if (in_array($a['status'], ['aktif', 'dibekukan'], true)): ?>
          <dt>Kinerja</dt><dd><?= $klik30 ?> klik (30 hari) · <?= $terjual ?> terjual · <?= e(rupiah($omzet)) ?></dd>
        <?php endif; ?>
        <dt>Terdaftar</dt><dd><?= e(waktuIndo($a['dibuat_pada'])) ?></dd>
        <?php if ($a['disetujui_pada']): ?><dt>Disetujui</dt><dd><?= e(waktuIndo($a['disetujui_pada'])) ?></dd><?php endif; ?>
        <dt>Terakhir masuk</dt><dd><?= e(waktuIndo($a['terakhir_masuk'])) ?></dd>
      </dl>
      <?php if (in_array($a['status'], ['aktif', 'dibekukan'], true)): ?>
        <h3 class="sub-judul">Rencana promosi</h3>
        <p class="kutipan"><?= nl2br(e($a['kanal_promosi'])) ?></p>
      <?php endif; ?>
    </section>

    <!-- ============ Catatan & tindakan ============ -->
    <section class="kotak">
      <div class="kotak-kepala"><h2>Catatan admin</h2></div>
      <form method="post" class="form-panel">
        <?= csrfInput() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <textarea name="catatan_admin" rows="4" placeholder="Hanya terlihat oleh admin"><?= e((string) $a['catatan_admin']) ?></textarea>
        <div class="form-aksi"><button class="tbl tbl-kecil" type="submit" name="aksi" value="catatan">Simpan catatan</button></div>
      </form>
    </section>

    <section class="kotak kotak-bahaya">
      <div class="kotak-kepala"><h2>Tindakan akun</h2></div>
      <div class="form-panel">
        <?php if ($a['status'] === 'aktif'): ?>
          <form method="post" class="form-panel">
            <?= csrfInput() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="bidang">
              <label for="alasan-beku">Alasan pembekuan</label>
              <input id="alasan-beku" name="alasan" type="text" placeholder="Mis. promosi menyesatkan">
            </div>
            <button class="tbl tbl-kecil tbl-bahaya" type="submit" name="aksi" value="bekukan"
                    data-pastikan="Bekukan <?= e($a['nama']) ?>? Link-nya berhenti mencatat penjualan baru dan ia tidak bisa menarik saldo sampai diaktifkan lagi.">Bekukan akun</button>
          </form>
        <?php elseif ($a['status'] === 'dibekukan'): ?>
          <form method="post">
            <?= csrfInput() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <button class="tbl tbl-kecil tbl-utama" type="submit" name="aksi" value="aktifkan">Aktifkan kembali</button>
          </form>
        <?php endif; ?>

        <?php if (in_array($a['status'], ['aktif', 'dibekukan'], true)): ?>
          <form method="post">
            <?= csrfInput() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <button class="tbl tbl-kecil" type="submit" name="aksi" value="reset_sandi"
                    data-pastikan="Buat kata sandi sementara untuk <?= e($a['nama']) ?>? Kata sandi lamanya langsung tidak berlaku.">Atur ulang kata sandi</button>
            <p class="petunjuk">Untuk affiliator yang lupa kata sandi.</p>
          </form>
        <?php endif; ?>

        <form method="post">
          <?= csrfInput() ?>
          <input type="hidden" name="id" value="<?= $id ?>">
          <button class="tbl tbl-kecil tbl-bahaya" type="submit" name="aksi" value="hapus"
                  data-pastikan="Hapus akun <?= e($a['nama']) ?> selamanya?">Hapus akun</button>
          <p class="petunjuk">Hanya bisa kalau belum pernah punya transaksi atau komisi.</p>
        </form>
      </div>
    </section>
  </aside>
</div>

<?php require __DIR__ . '/inc/kaki.php'; ?>
