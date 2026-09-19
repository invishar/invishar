<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
require_once __DIR__ . '/inc/affiliate.php';
wajibMasuk();
wajibPenjualanSiap();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $id = (int) masukan('id');
    $p = ambilSatu('SELECT p.*, a.nama FROM penarikan p JOIN affiliate a ON a.id = p.affiliate_id WHERE p.id = ?', [$id]);
    $aksi = masukan('aksi');

    if (!$p) {
        pesan('Withdraw tidak ditemukan.', 'buruk');
    } elseif ($aksi === 'bayar') {
        $ref = masukan('referensi');
        if ($ref === '') {
            pesan('Isi nomor referensi transfer — affiliator melihatnya sebagai bukti.', 'buruk');
        } elseif (bayarPenarikan($id, $ref, masukan('catatan'))) {
            catatLog('bayar penarikan', $p['nama'] . ' · ' . rupiah((int) $p['jumlah']) . ' · ' . $ref);
            pesan('Withdraw ' . rupiah((int) $p['jumlah']) . ' untuk ' . $p['nama'] . ' ditandai sudah ditransfer.');
        } else {
            pesan('Withdraw ini sudah diproses sebelumnya.', 'peringatan');
        }
    } elseif ($aksi === 'tolak') {
        $alasan = masukan('alasan');
        if ($alasan === '') {
            pesan('Tulis alasan penolakan — affiliator melihatnya.', 'buruk');
        } elseif (tolakPenarikan($id, $alasan)) {
            catatLog('tolak penarikan', $p['nama'] . ' · ' . $alasan);
            pesan('Withdraw ditolak. Saldo ' . rupiah((int) $p['jumlah']) . ' kembali ke ' . $p['nama'] . ' dan bisa diajukan lagi.');
        } else {
            pesan('Withdraw ini sudah diproses sebelumnya.', 'peringatan');
        }
    }
    pergi(tautan('penarikan') . (masukan('kembali_ke') === 'detail' ? '/' . $id : ''));
}

$fokus = (int) ($_GET['id'] ?? 0);
$saring = $_GET['status'] ?? ($fokus ? '' : 'diajukan');
$saring = isset(STATUS_PENARIKAN[$saring]) ? $saring : '';

$hitung = [];
foreach (ambilSemua('SELECT status, COUNT(*) AS jml, COALESCE(SUM(jumlah), 0) AS rp FROM penarikan GROUP BY status') as $b) {
    $hitung[$b['status']] = ['n' => (int) $b['jml'], 'rp' => (int) $b['rp']];
}

$syarat = $fokus ? 'WHERE p.id = ?' : ($saring !== '' ? 'WHERE p.status = ?' : '');
$isi = $fokus ? [$fokus] : ($saring !== '' ? [$saring] : []);
$daftar = ambilSemua(
    "SELECT p.*, a.nama, a.kode, a.whatsapp, a.status AS status_affiliate
       FROM penarikan p JOIN affiliate a ON a.id = p.affiliate_id
       $syarat
      ORDER BY FIELD(p.status, 'diajukan', 'dibayar', 'ditolak'), p.diajukan_pada " . ($saring === 'diajukan' ? 'ASC' : 'DESC') . "
      LIMIT 300",
    $isi
);

$judul = 'Withdraw';
$menu  = 'penarikan';
require __DIR__ . '/inc/kepala.php';
?>

<p class="pengantar">Transfer dilakukan manual dari rekening Invishar. Setelah mentransfer, tandai &ldquo;sudah ditransfer&rdquo;
  beserta nomor referensinya — affiliator melihatnya sebagai bukti. Yang terlama ditampilkan paling atas.</p>

<div class="saring">
  <?php foreach (STATUS_PENARIKAN as $kunci => $label): ?>
    <a class="cip<?= $saring === $kunci && !$fokus ? ' is-on' : '' ?>" href="<?= tautan('penarikan') ?>?status=<?= e($kunci) ?>">
      <?= e($label) ?> <span><?= $hitung[$kunci]['n'] ?? 0 ?></span>
    </a>
  <?php endforeach; ?>
  <a class="cip<?= $saring === '' && !$fokus ? ' is-on' : '' ?>" href="<?= tautan('penarikan') ?>?status=semua">Semua</a>
</div>

<?php if (!$daftar): ?>
  <div class="kosong kosong-besar">
    <h3><?= $saring === 'diajukan' ? 'Tidak ada yang perlu dibayar' : 'Tidak ada withdraw' ?></h3>
    <p><?= $saring === 'diajukan' ? 'Semua pengajuan withdraw sudah diproses.' : 'Belum ada withdraw dengan status ini.' ?></p>
  </div>
<?php else: ?>
  <?php if ($saring === 'diajukan' && ($hitung['diajukan']['n'] ?? 0) > 0): ?>
    <p class="teks-kecil" style="margin:-6px 0 14px">Total perlu ditransfer: <strong><?= e(rupiah($hitung['diajukan']['rp'])) ?></strong></p>
  <?php endif; ?>

  <div class="kartu-kisi" style="grid-template-columns:repeat(auto-fill,minmax(min(340px,100%),1fr))">
    <?php foreach ($daftar as $p): ?>
      <section class="kotak" style="margin:0<?= $p['status'] === 'diajukan' ? ';border-color:var(--green-300)' : '' ?>" id="tarik-<?= (int) $p['id'] ?>">
        <div class="kotak-kepala">
          <div>
            <h2 style="font-size:22px;letter-spacing:-0.035em"><?= e(rupiah((int) $p['jumlah'])) ?></h2>
            <a class="teks-kecil" href="<?= tautan('affiliate/' . (int) $p['affiliate_id']) ?>"><?= e($p['nama']) ?> · <?= e((string) $p['kode']) ?></a>
          </div>
          <span class="tanda tanda-<?= e($p['status']) ?>" style="margin-left:auto"><?= e(STATUS_PENARIKAN[$p['status']]) ?></span>
        </div>

        <dl class="keadaan">
          <dt>Transfer ke</dt>
          <dd>
            <strong><?= e($p['bank_nama']) ?></strong> · a.n. <?= e($p['bank_atas_nama']) ?>
            <div class="salin-baris" style="margin-top:6px">
              <code><?= e($p['bank_nomor']) ?></code>
              <button class="tbl-salin" type="button" data-salin="<?= e($p['bank_nomor']) ?>">Salin nomor</button>
            </div>
          </dd>
          <dt>Diajukan</dt><dd><?= e(waktuIndo($p['diajukan_pada'])) ?></dd>
          <?php if ($p['diproses_pada']): ?><dt>Diproses</dt><dd><?= e(waktuIndo($p['diproses_pada'])) ?></dd><?php endif; ?>
          <?php if ($p['referensi']): ?><dt>Referensi</dt><dd><span class="tabel-kode"><?= e($p['referensi']) ?></span></dd><?php endif; ?>
          <?php if ($p['catatan']): ?><dt><?= $p['status'] === 'ditolak' ? 'Alasan ditolak' : 'Catatan' ?></dt><dd><?= e($p['catatan']) ?></dd><?php endif; ?>
        </dl>

        <?php if ($p['status'] === 'diajukan'): ?>
          <?php if ($p['status_affiliate'] !== 'aktif'): ?>
            <p class="petunjuk petunjuk-awas">Akun affiliator ini sedang <?= e(strtolower(STATUS_AFFILIATE[$p['status_affiliate']])) ?>. Periksa dulu sebelum mentransfer.</p>
          <?php endif; ?>
          <form method="post" class="form-panel" style="margin-top:16px">
            <?= csrfInput() ?>
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <input type="hidden" name="kembali_ke" value="<?= $fokus ? 'detail' : '' ?>">
            <div class="bidang">
              <label for="ref-<?= (int) $p['id'] ?>">No. referensi transfer</label>
              <input id="ref-<?= (int) $p['id'] ?>" name="referensi" type="text" placeholder="Dari bukti transfer / m-banking" required>
            </div>
            <div class="aksi-kisi">
              <button class="tbl tbl-utama" type="submit" name="aksi" value="bayar"
                      data-pastikan="Sudah mentransfer <?= e(rupiah((int) $p['jumlah'])) ?> ke <?= e($p['bank_nama'] . ' ' . $p['bank_nomor']) ?> a.n. <?= e($p['bank_atas_nama']) ?>?">Tandai sudah ditransfer</button>
              <button class="tbl tbl-bahaya" type="button" data-buka="#tolak-<?= (int) $p['id'] ?>">Tolak…</button>
            </div>
          </form>
          <form method="post" class="form-panel lipatan-aksi" id="tolak-<?= (int) $p['id'] ?>" hidden>
            <?= csrfInput() ?>
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <div class="bidang">
              <label for="alasan-<?= (int) $p['id'] ?>">Alasan penolakan (dilihat affiliator)</label>
              <input id="alasan-<?= (int) $p['id'] ?>" name="alasan" type="text" placeholder="Mis. nama rekening tidak cocok, mohon perbarui" required>
            </div>
            <div><button class="tbl tbl-bahaya" type="submit" name="aksi" value="tolak">Tolak &amp; kembalikan saldo</button></div>
          </form>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/inc/kaki.php'; ?>
