<?php
declare(strict_types=1);
require __DIR__ . '/inc/awal.php';
$a = wajibMitra();
$aid = (int) $a['id'];

$produk = ambilSemua(
    "SELECT p.*,
            (SELECT COUNT(*) FROM affiliate_klik k WHERE k.affiliate_id = ? AND k.produk_id = p.id AND k.dibuat_pada > (NOW() - INTERVAL 30 DAY)) AS klik30,
            (SELECT COUNT(*) FROM transaksi t WHERE t.affiliate_id = ? AND t.produk_id = p.id AND t.status = 'lunas' AND t.beli_sendiri = 0) AS terjual
       FROM produk p
      WHERE p.status = 'aktif' AND p.affiliate_aktif = 1
      ORDER BY p.urutan, p.nama",
    [$aid, $aid]
);
$hari = setelanAngka('affiliate.cookie_hari');

$judul = 'Link produk';
$sub = 'Bagikan link di bawah. Orang yang membukanya ditandai selama <strong>' . $hari . ' hari</strong> — kalau ia membeli dalam waktu itu, komisinya milik Anda. Kalau ia membuka link mitra lain sesudahnya, link terakhir yang berlaku.';
$menu = 'tautan';
require __DIR__ . '/inc/kepala.php';
?>

<?php if ($a['status'] !== 'aktif'): ?>
  <p class="kosong">Selama akun dibekukan, link di bawah tidak mencatat penjualan baru.</p>
<?php endif; ?>

<?php if (!$produk): ?>
  <div class="kosong kosong-besar">
    <h3>Belum ada produk yang dibuka untuk mitra</h3>
    <p>Begitu admin membuka produk untuk affiliate, link Anda untuk produk itu muncul di sini secara otomatis.</p>
  </div>
<?php else: ?>
  <div class="link-kisi">
    <?php foreach ($produk as $p): ?>
      <?php
        $link = linkAffiliate((string) $a['kode'], $p['slug']);
        $contoh = $p['harga'] !== null ? hitungKomisi($p['fee_jenis'], $p['fee_nilai'], (int) $p['harga']) : null;
        $pesanWa = $p['nama'] . ($p['tagline'] ? ' — ' . $p['tagline'] : '') . "\n" . $link;
      ?>
      <article class="link-kartu">
        <div class="link-atas">
          <div>
            <h2><?= e($p['nama']) ?></h2>
            <span class="link-jenis"><?= e(JENIS_PRODUK[$p['jenis']]['label']) ?></span>
          </div>
          <span class="link-harga"><?= e(teksHargaProduk($p)) ?></span>
        </div>

        <div class="link-komisi">
          <span aria-hidden="true">💰</span>
          <span>
            Komisi <strong><?= e(teksFee($p['fee_jenis'], $p['fee_nilai'])) ?></strong>
            <?php if ($p['jenis'] === 'langganan'): ?>
              per bulan, hingga <?= bulanBerulang($p) ?> bulan<?= $p['fee_jenis'] === 'persen' && $contoh ? ' (± ' . e(rupiah($contoh)) . '/bulan)' : '' ?>
            <?php elseif ($p['jenis'] === 'penawaran'): ?>
              <?= $p['fee_jenis'] === 'persen' ? 'dari nilai proyek yang disepakati' : 'per proyek yang dibayar' ?>
              <?php if ($p['fee_jenis'] === 'persen' && $contoh): ?>
                <br><span class="teks-kecil">Mis. proyek <?= e(rupiah((int) $p['harga'])) ?> → komisi <?= e(rupiah($contoh)) ?></span>
              <?php endif; ?>
            <?php elseif ($p['fee_jenis'] === 'persen' && $contoh): ?>
              ≈ <?= e(rupiah($contoh)) ?> per penjualan
            <?php else: ?>
              per penjualan
            <?php endif; ?>
          </span>
        </div>

        <div class="salin-baris">
          <code><?= e($link) ?></code>
          <button class="tbl-salin" type="button" data-salin="<?= e($link) ?>">Salin</button>
        </div>

        <div class="link-statistik">
          <span><b><?= (int) $p['klik30'] ?></b> klik (30 hari)</span>
          <span><b><?= (int) $p['terjual'] ?></b> terjual</span>
        </div>

        <div class="link-aksi">
          <a class="tbl tbl-kecil tbl-wa" href="https://wa.me/?text=<?= e(rawurlencode($pesanWa)) ?>" target="_blank" rel="noopener">Bagikan ke WhatsApp</a>
          <a class="tbl tbl-kecil" href="/p/<?= e($p['slug']) ?>" target="_blank" rel="noopener">Lihat halaman ↗</a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<section class="kotak" style="margin-top:18px">
  <div class="kotak-kepala"><h2>Tips membagikan</h2></div>
  <ul class="titik-daftar">
    <li>Ceritakan pengalaman atau alasan Anda merekomendasikan produknya — lebih dipercaya daripada sekadar menempel link.</li>
    <li>Membeli lewat link sendiri tidak menghasilkan komisi.</li>
    <li>Jangan menjanjikan hal yang tidak tertulis di halaman produk. Baca <a href="<?= tautan('syarat') ?>">syarat &amp; ketentuan</a>.</li>
  </ul>
</section>

<?php require __DIR__ . '/inc/kaki.php'; ?>
