<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
$a = wajibMitra();
$aid = (int) $a['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    wajibAktif($a);
    try {
        $hasil = ajukanPenarikan($aid);
    } catch (Throwable $e) {
        error_log('[invishar penarikan] ' . $e->getMessage());
        $hasil = ['baik' => false, 'pesan' => 'Penarikan gagal diajukan. Coba lagi sebentar.'];
    }
    if ($hasil['baik']) {
        catatLog('penarikan diajukan', $a['nama'] . ' · ' . $hasil['pesan']);
        pesan($hasil['pesan'] . ' Admin akan mentransfer ke rekening Anda.');
    } else {
        pesan($hasil['pesan'], 'buruk');
    }
    pergi(tautan('penarikan'));
}

$saldo = saldoAffiliate($aid);
$min = setelanAngka('affiliate.min_tarik');
$rekeningLengkap = trim((string) $a['bank_nama']) !== '' && trim((string) $a['bank_nomor']) !== '' && trim((string) $a['bank_atas_nama']) !== '';
$terbuka = ambilSatu("SELECT * FROM penarikan WHERE affiliate_id = ? AND status = 'diajukan'", [$aid]);
$riwayat = ambilSemua('SELECT * FROM penarikan WHERE affiliate_id = ? ORDER BY diajukan_pada DESC, id DESC', [$aid]);

// Alasan tombol belum bisa dipakai — ditampilkan apa adanya, bukan tombol mati tanpa penjelasan.
$halangan = [];
if ($a['status'] !== 'aktif') {
    $halangan[] = 'Akun sedang dibekukan.';
}
if ($terbuka) {
    $halangan[] = 'Masih ada penarikan ' . rupiah((int) $terbuka['jumlah']) . ' yang sedang diproses.';
}
if (!$rekeningLengkap) {
    $halangan[] = 'Data rekening belum lengkap.';
}
if ($saldo['siap'] <= 0) {
    $halangan[] = 'Belum ada saldo yang siap ditarik.';
} elseif ($saldo['siap'] < $min) {
    $halangan[] = 'Saldo siap ' . rupiah($saldo['siap']) . ', minimal penarikan ' . rupiah($min) . '.';
}

$judul = 'Penarikan';
$sub = 'Tarik saldo yang sudah siap ke rekening Anda. Transfer dilakukan oleh admin Invishar.';
$menu = 'penarikan';
require __DIR__ . '/inc/kepala.php';
?>

<div class="dua-kolom-lebar">
  <section class="kotak">
    <div class="kotak-kepala"><h2>Ajukan penarikan</h2></div>

    <p class="angka-num" style="font-size:36px;margin:0 0 4px<?= $saldo['siap'] < 0 ? ';color:var(--merah)' : '' ?>"><?= e(rupiah($saldo['siap'])) ?></p>
    <p class="teks-kecil" style="margin:0 0 18px">Saldo siap ditarik · minimal <?= e(rupiah($min)) ?></p>

    <?php if ($saldo['siap'] < 0): ?>
      <div class="pita pita-info"><span>Saldo minus karena ada transaksi yang dikembalikan setelah komisinya dicairkan.
        Nilainya akan terpotong otomatis dari komisi berikutnya.</span></div>
    <?php endif; ?>

    <dl class="keadaan" style="margin-bottom:18px">
      <dt>Rekening tujuan</dt>
      <dd>
        <?php if ($rekeningLengkap): ?>
          <?= e($a['bank_nama']) ?> · <?= e($a['bank_nomor']) ?><br>a.n. <?= e($a['bank_atas_nama']) ?>
          <br><a class="teks-kecil" href="<?= tautan('profil') ?>#rekening">Ubah rekening</a>
        <?php else: ?>
          <a href="<?= tautan('profil') ?>#rekening">Isi data rekening dulu &rarr;</a>
        <?php endif; ?>
      </dd>
    </dl>

    <?php if (!$halangan): ?>
      <form method="post">
        <?= csrfInput() ?>
        <button class="tbl tbl-utama" type="submit"
                data-pastikan="Ajukan penarikan <?= e(rupiah($saldo['siap'])) ?> ke <?= e($a['bank_nama'] . ' ' . $a['bank_nomor']) ?> a.n. <?= e($a['bank_atas_nama']) ?>?">
          Ajukan penarikan <?= e(rupiah($saldo['siap'])) ?>
        </button>
      </form>
      <p class="petunjuk">Seluruh saldo siap ditarik sekaligus. Komisi yang masih tertahan (<?= e(rupiah($saldo['tertahan'])) ?>) bisa ditarik setelah masa tahannya selesai.</p>
    <?php else: ?>
      <button class="tbl" type="button" disabled style="opacity:.55;cursor:not-allowed">Ajukan penarikan</button>
      <ul class="petunjuk" style="padding-left:18px;margin-top:10px">
        <?php foreach ($halangan as $h): ?><li><?= e($h) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <aside>
    <section class="kotak">
      <div class="kotak-kepala"><h2>Cara kerjanya</h2></div>
      <ul class="garis-waktu">
        <li><span class="gw-judul">Komisi tertahan <?= setelanAngka('affiliate.masa_tahan_hari') ?> hari</span><span class="gw-sub">Ruang untuk pembatalan dari pembeli.</span></li>
        <li><span class="gw-judul">Masuk saldo siap</span><span class="gw-sub">Bisa diajukan kalau sudah mencapai minimal.</span></li>
        <li><span class="gw-judul">Admin mentransfer</span><span class="gw-sub">Status berubah menjadi Dibayar, lengkap dengan nomor referensinya.</span></li>
      </ul>
    </section>
  </aside>
</div>

<section class="kotak">
  <div class="kotak-kepala"><h2>Riwayat penarikan</h2></div>
  <?php if (!$riwayat): ?>
    <p class="kosong">Belum pernah ada penarikan.</p>
  <?php else: ?>
    <div class="tabel-bungkus" style="margin:0">
      <table class="tabel tabel-diam">
        <thead>
          <tr><th>Diajukan</th><th class="kanan">Jumlah</th><th>Rekening</th><th>Status</th><th>Keterangan</th></tr>
        </thead>
        <tbody>
          <?php foreach ($riwayat as $r): ?>
            <tr>
              <td class="tabel-tipis"><?= e(waktuIndo($r['diajukan_pada'])) ?></td>
              <td class="kanan tebal"><?= e(rupiah((int) $r['jumlah'])) ?></td>
              <td><?= e($r['bank_nama']) ?> · <?= e($r['bank_nomor']) ?><span class="tabel-sub">a.n. <?= e($r['bank_atas_nama']) ?></span></td>
              <td>
                <span class="tanda tanda-<?= $r['status'] === 'diajukan' ? 'diproses' : e($r['status']) ?>"><?= e($r['status'] === 'diajukan' ? 'Sedang diproses' : STATUS_PENARIKAN[$r['status']]) ?></span>
                <?php if ($r['diproses_pada']): ?><span class="tabel-sub"><?= e(waktuIndo($r['diproses_pada'])) ?></span><?php endif; ?>
              </td>
              <td class="tabel-panjang">
                <?php if ($r['status'] === 'dibayar' && $r['referensi']): ?>Ref. transfer <span class="tabel-kode"><?= e($r['referensi']) ?></span><?php endif; ?>
                <?php if ($r['catatan']): ?><span class="tabel-sub"><?= e($r['catatan']) ?></span><?php endif; ?>
                <?php if ($r['status'] === 'ditolak'): ?><span class="tabel-sub">Saldo sudah dikembalikan dan bisa diajukan lagi.</span><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/inc/kaki.php'; ?>
