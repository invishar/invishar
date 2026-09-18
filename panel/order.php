<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
wajibMasuk();

/* Tambah order manual — banyak permintaan masuk lewat WhatsApp, bukan form. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();

    $nama      = masukan('nama');
    $kebutuhan = masukan('kebutuhan');

    if ($nama === '' || $kebutuhan === '') {
        pesan('Nama dan kebutuhan wajib diisi.', 'buruk');
    } else {
        q(
            'INSERT INTO order_jasa (nama, lembaga, surel, whatsapp, kebutuhan, sumber, status, dibuat_pada, diperbarui_pada)
             VALUES (?, ?, ?, ?, ?, ?, \'baru\', NOW(), NOW())',
            [$nama, masukan('lembaga'), masukan('surel'), masukan('whatsapp'), $kebutuhan, masukan('sumber') ?: 'manual']
        );
        $id = (int) db()->lastInsertId();
        q('INSERT INTO order_riwayat (order_id, status_baru, catatan, dibuat_pada) VALUES (?, \'baru\', ?, NOW())',
            [$id, 'Dicatat manual']);
        catatLog('tambah order', $nama);
        pesan('Order dari "' . $nama . '" dicatat.');
        pergi('order-detail.php?id=' . $id);
    }
    pergi('order.php');
}

$saring = $_GET['status'] ?? '';
$saring = isset(STATUS_ORDER[$saring]) ? $saring : '';

$daftar = $saring === ''
    ? ambilSemua('SELECT * FROM order_jasa ORDER BY dibuat_pada DESC')
    : ambilSemua('SELECT * FROM order_jasa WHERE status = ? ORDER BY dibuat_pada DESC', [$saring]);

$hitung = [];
foreach (ambilSemua('SELECT status, COUNT(*) AS jml FROM order_jasa GROUP BY status') as $b) {
    $hitung[$b['status']] = (int) $b['jml'];
}

$judul = 'Order jasa';
$menu  = 'order';
require __DIR__ . '/inc/kepala.php';
?>

<div class="saring">
  <a class="cip<?= $saring === '' ? ' is-on' : '' ?>" href="order.php">
    Semua <span><?= array_sum($hitung) ?></span>
  </a>
  <?php foreach (STATUS_ORDER as $kunci => $label): ?>
    <a class="cip<?= $saring === $kunci ? ' is-on' : '' ?>" href="order.php?status=<?= e($kunci) ?>">
      <?= e($label) ?> <span><?= $hitung[$kunci] ?? 0 ?></span>
    </a>
  <?php endforeach; ?>
</div>

<?php if (!$daftar): ?>
  <p class="kosong">
    <?= $saring === '' ? 'Belum ada order.' : 'Tidak ada order berstatus ini.' ?>
  </p>
<?php else: ?>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr>
          <th>Nama</th>
          <th>Kebutuhan</th>
          <th>Sumber</th>
          <th>Masuk</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($daftar as $o): ?>
          <tr onclick="location='order-detail.php?id=<?= (int) $o['id'] ?>'">
            <td>
              <a class="tabel-utama" href="order-detail.php?id=<?= (int) $o['id'] ?>"><?= e($o['nama']) ?></a>
              <?php if ($o['lembaga']): ?><span class="tabel-sub"><?= e($o['lembaga']) ?></span><?php endif; ?>
            </td>
            <td class="tabel-panjang"><?= e(mb_strimwidth($o['kebutuhan'], 0, 110, '…')) ?></td>
            <td><?= e($o['sumber']) ?></td>
            <td class="tabel-tipis"><?= e(waktuIndo($o['dibuat_pada'])) ?></td>
            <td><span class="tanda tanda-<?= e($o['status']) ?>"><?= e(STATUS_ORDER[$o['status']] ?? $o['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<section class="kotak kotak-form">
  <div class="kotak-kepala">
    <h2>Catat order manual</h2>
  </div>

  <form method="post" class="form-panel">
    <?= csrfInput() ?>
    <div class="baris-form">
      <div class="bidang">
        <label for="nama">Nama</label>
        <input id="nama" name="nama" type="text" required>
      </div>
      <div class="bidang">
        <label for="lembaga">Lembaga</label>
        <input id="lembaga" name="lembaga" type="text">
      </div>
    </div>

    <div class="baris-form">
      <div class="bidang">
        <label for="surel">Surel</label>
        <input id="surel" name="surel" type="email">
      </div>
      <div class="bidang">
        <label for="whatsapp">WhatsApp</label>
        <input id="whatsapp" name="whatsapp" type="text" placeholder="+62…">
      </div>
      <div class="bidang bidang-kecil">
        <label for="sumber">Sumber</label>
        <input id="sumber" name="sumber" type="text" value="manual">
      </div>
    </div>

    <div class="bidang">
      <label for="kebutuhan">Yang ingin dibereskan</label>
      <textarea id="kebutuhan" name="kebutuhan" rows="3" required></textarea>
    </div>

    <div class="form-aksi">
      <button class="tbl tbl-utama" type="submit">Catat order</button>
    </div>
  </form>
</section>

<?php require __DIR__ . '/inc/kaki.php'; ?>
