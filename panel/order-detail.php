<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
wajibMasuk();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$order = ambilSatu('SELECT * FROM order_jasa WHERE id = ?', [$id]);

if ($order === null) {
    pesan('Order tidak ditemukan.', 'buruk');
    pergi(tautan('order'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();

    if (masukan('aksi') === 'hapus') {
        q('DELETE FROM order_jasa WHERE id = ?', [$id]);
        catatLog('hapus order', $order['nama']);
        pesan('Order dari "' . $order['nama'] . '" dihapus.');
        pergi(tautan('order'));
    }

    $statusBaru = masukan('status', $order['status']);
    if (!isset(STATUS_ORDER[$statusBaru])) {
        $statusBaru = $order['status'];
    }
    $nilai = masukan('nilai') === '' ? null : (int) preg_replace('/\D/', '', masukan('nilai'));

    q(
        'UPDATE order_jasa SET status = ?, nilai = ?, catatan = ?, diperbarui_pada = NOW() WHERE id = ?',
        [$statusBaru, $nilai, masukan('catatan'), $id]
    );

    if ($statusBaru !== $order['status']) {
        q(
            'INSERT INTO order_riwayat (order_id, status_lama, status_baru, catatan, dibuat_pada) VALUES (?, ?, ?, ?, NOW())',
            [$id, $order['status'], $statusBaru, masukan('riwayat_catatan')]
        );
        catatLog('ubah status order', $order['nama'] . ' → ' . STATUS_ORDER[$statusBaru]);
    }

    pesan('Order diperbarui.');
    pergi(tautan('order/' . $id));
}

$riwayat = ambilSemua('SELECT * FROM order_riwayat WHERE order_id = ? ORDER BY dibuat_pada DESC', [$id]);

$judul = $order['nama'];
$menu  = 'order';
require __DIR__ . '/inc/kepala.php';
?>

<p class="remah"><a href="<?= tautan('order') ?>">&larr; Semua order</a></p>

<div class="dua-kolom">

  <section class="kotak">
    <div class="kotak-kepala">
      <h2>Permintaan</h2>
      <span class="tanda tanda-<?= e($order['status']) ?>"><?= e(STATUS_ORDER[$order['status']] ?? $order['status']) ?></span>
    </div>

    <dl class="keadaan">
      <dt>Nama</dt><dd><?= e($order['nama']) ?></dd>
      <?php if ($order['lembaga']): ?><dt>Lembaga</dt><dd><?= e($order['lembaga']) ?></dd><?php endif; ?>
      <?php if ($order['surel']): ?>
        <dt>Surel</dt><dd><a href="mailto:<?= e($order['surel']) ?>"><?= e($order['surel']) ?></a></dd>
      <?php endif; ?>
      <?php if ($order['whatsapp']): ?>
        <dt>WhatsApp</dt>
        <dd><a href="https://wa.me/<?= e(preg_replace('/\D/', '', $order['whatsapp'])) ?>" target="_blank" rel="noopener"><?= e($order['whatsapp']) ?></a></dd>
      <?php endif; ?>
      <dt>Sumber</dt><dd><?= e($order['sumber']) ?></dd>
      <dt>Masuk</dt><dd><?= e(waktuIndo($order['dibuat_pada'])) ?></dd>
    </dl>

    <h3 class="sub-judul">Yang ingin dibereskan</h3>
    <p class="kutipan"><?= nl2br(e($order['kebutuhan'])) ?></p>
  </section>

  <section class="kotak">
    <div class="kotak-kepala">
      <h2>Tindak lanjut</h2>
    </div>

    <form method="post" class="form-panel">
      <?= csrfInput() ?>
      <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">

      <div class="baris-form">
        <div class="bidang">
          <label for="status">Status</label>
          <select id="status" name="status">
            <?php foreach (STATUS_ORDER as $kunci => $label): ?>
              <option value="<?= e($kunci) ?>"<?= $order['status'] === $kunci ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="bidang">
          <label for="nilai">Nilai proyek (Rp)</label>
          <input id="nilai" name="nilai" type="text" inputmode="numeric" value="<?= $order['nilai'] !== null ? (int) $order['nilai'] : '' ?>">
        </div>
      </div>

      <div class="bidang">
        <label for="riwayat_catatan">Alasan perubahan status</label>
        <input id="riwayat_catatan" name="riwayat_catatan" type="text" placeholder="Hanya tercatat kalau statusnya berubah">
      </div>

      <div class="bidang">
        <label for="catatan">Catatan</label>
        <textarea id="catatan" name="catatan" rows="5"><?= e($order['catatan'] ?? '') ?></textarea>
      </div>

      <div class="form-aksi">
        <button class="tbl tbl-utama" type="submit">Simpan</button>
        <button class="tbl tbl-bahaya" type="submit" name="aksi" value="hapus"
                data-pastikan="Hapus order dari <?= e($order['nama']) ?>? Tidak bisa dibatalkan.">Hapus</button>
      </div>
    </form>

    <?php if ($riwayat): ?>
      <h3 class="sub-judul">Riwayat</h3>
      <ul class="jejak">
        <?php foreach ($riwayat as $r): ?>
          <li>
            <span class="jejak-aksi">
              <?= $r['status_lama'] ? e(STATUS_ORDER[$r['status_lama']] ?? $r['status_lama']) . ' → ' : '' ?>
              <?= e(STATUS_ORDER[$r['status_baru']] ?? $r['status_baru']) ?>
            </span>
            <?php if ($r['catatan']): ?><span class="jejak-objek"><?= e($r['catatan']) ?></span><?php endif; ?>
            <span class="jejak-waktu"><?= e(waktuIndo($r['dibuat_pada'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

</div>

<?php require __DIR__ . '/inc/kaki.php'; ?>
