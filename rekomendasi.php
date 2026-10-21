<?php
require_once __DIR__ . '/includes/init.php';

// Menjadwalkan blok rekomendasi menjadi sesi belajar sungguhan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'jadwalkan') {
    csrf_check();
    $tugas = db_one($conn, "SELECT id, judul FROM tugas WHERE id = ?", 'i', [(int)($_POST['tugas_id'] ?? 0)]);
    $berhasil = 0;
    $gagal = [];
    if ($tugas) {
        foreach (($_POST['blok'] ?? []) as $b) {
            if (!preg_match('/^(\d{4}-\d{2}-\d{2})\|(\d{2}:\d{2})\|(\d{2}:\d{2})$/', (string)$b, $m)
                || !valid_tanggal($m[1]) || !valid_jam($m[2]) || !valid_jam($m[3]) || $m[3] <= $m[2]) {
                continue;
            }
            $mulai = $m[1] . ' ' . $m[2] . ':00';
            $selesai = $m[1] . ' ' . $m[3] . ':00';
            // Periksa ulang bentrok tepat sebelum menyimpan (data bisa berubah sejak halaman dibuka)
            if (cari_bentrok($conn, $mulai, $selesai)) {
                $gagal[] = tanggal_indo($m[1]) . ' ' . $m[2] . '–' . $m[3];
                continue;
            }
            db_exec($conn, "INSERT INTO sesi_belajar (tugas_id, judul, jenis, waktu_mulai, waktu_selesai, status, catatan)
                            VALUES (?, ?, 'Mengerjakan Tugas', ?, ?, 'Terjadwal', 'Dibuat dari rekomendasi waktu belajar')",
                'isss', [(int)$tugas['id'], 'Kerjakan: ' . mb_substr($tugas['judul'], 0, 130), $mulai, $selesai]);
            $berhasil++;
        }
    }
    if ($berhasil > 0) {
        flash('sukses', $berhasil . ' sesi belajar berhasil dijadwalkan. Rekomendasi dihitung ulang dengan sesi tersebut.');
    }
    if ($gagal) {
        flash('warning', 'Dilewati karena sudah bentrok: ' . implode(', ', $gagal) . '.');
    }
    if ($berhasil === 0 && !$gagal) {
        flash('error', 'Tidak ada sesi yang dijadwalkan.');
    }
    redirect('rekomendasi.php');
}

$hasil = buat_rekomendasi($conn);
$daftar = $hasil['daftar'];
$dilewati = $hasil['dilewati'];
$ketersediaan = $hasil['ketersediaan'];

$judul_halaman = 'Rekomendasi Waktu Belajar';
$menu_aktif = 'rekomendasi';
include __DIR__ . '/includes/header.php';
?>

<?php if (!$daftar): ?>
    <div class="card"><div class="kosong">Tidak ada tugas aktif dengan deadline dalam <?= HORIZON_HARI ?> hari ke depan. <a href="tugas.php?tambah=1">Tambah tugas</a>.</div></div>
<?php endif; ?>

<?php foreach ($daftar as $item):
    $t = $item['tugas'];
    $dl = info_deadline($t['deadline'], $t['status']); ?>
<div class="card">
    <div class="rek-kepala">
        <div>
            <h2><?= e($t['judul']) ?></h2>
            <div class="muted kecil"><?= e($t['nama_mata_kuliah'] ?: 'Tanpa mata kuliah') ?> · Deadline <?= e(tanggal_indo($t['deadline'])) ?></div>
        </div>
        <div><?= badge($dl[0], $dl[1]) ?> <?= badge_status($t['prioritas']) ?> <?= badge_status($t['status']) ?></div>
    </div>
    <div class="rek-meta">
        <span>Skor: <b><?= (int)$item['skor'] ?></b> (prioritas <?= (int)$item['bobot'] ?> + urgensi <?= (int)$item['urgensi'] ?>)</span>
        <span>Estimasi: <b><?= e(fmt_durasi($item['estimasi'])) ?></b><?= $item['estimasi_default'] ? ' (default)' : '' ?></span>
        <span>Sudah terjadwal/selesai: <b><?= e(fmt_durasi($item['terjadwal'])) ?></b></span>
        <span>Perlu dijadwalkan: <b><?= e(fmt_durasi($item['sisa'])) ?></b></span>
    </div>

    <?php if ($item['sisa'] === 0): ?>
        <div class="alert alert-info" style="margin:0">Kebutuhan waktu tugas ini sudah tertutup oleh sesi yang ada.</div>
    <?php else: ?>
        <?php if ($item['kurang'] > 0): ?>
            <div class="alert alert-warning">Slot kosong tidak cukup sampai batas waktu: masih kurang <?= e(fmt_durasi($item['kurang'])) ?>. Pertimbangkan memindahkan sesi lain, memperpanjang jendela waktu, atau menegosiasikan deadline.</div>
        <?php endif; ?>
        <?php if ($item['usulan']): ?>
        <div class="tabel-wrap">
        <table class="data">
            <thead><tr><th>Tanggal</th><th>Jam</th><th>Durasi</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($item['usulan'] as $u): ?>
                <tr>
                    <td><?= e(nama_hari_tanggal($u['tanggal'])) ?>, <?= e(tanggal_indo($u['tanggal'])) ?></td>
                    <td><?= e($u['mulai']) ?>–<?= e($u['selesai']) ?></td>
                    <td><?= e(fmt_durasi($u['menit'])) ?></td>
                    <td class="aksi">
                        <form method="post" action="rekomendasi.php" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="jadwalkan">
                            <input type="hidden" name="tugas_id" value="<?= (int)$t['id'] ?>">
                            <input type="hidden" name="blok[]" value="<?= e($u['tanggal'] . '|' . $u['mulai'] . '|' . $u['selesai']) ?>">
                            <button type="submit" class="btn btn-sekunder btn-kecil">Jadwalkan</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <form method="post" action="rekomendasi.php" style="margin-top:10px">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="jadwalkan">
            <input type="hidden" name="tugas_id" value="<?= (int)$t['id'] ?>">
            <?php foreach ($item['usulan'] as $u): ?>
                <input type="hidden" name="blok[]" value="<?= e($u['tanggal'] . '|' . $u['mulai'] . '|' . $u['selesai']) ?>">
            <?php endforeach; ?>
            <button type="submit" class="btn">Jadwalkan Semua Blok (<?= count($item['usulan']) ?>)</button>
        </form>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<?php if ($dilewati): ?>
<div class="card">
    <div class="card-header"><h2>Di luar jangkauan rekomendasi (deadline &gt; <?= HORIZON_HARI ?> hari)</h2></div>
    <?php foreach ($dilewati as $d): ?>
        <div class="muted"><?= e($d['tugas']['judul']) ?> — deadline <?= e(tanggal_indo($d['tugas']['deadline'])) ?> (H-<?= (int)$d['sisa_hari'] ?>)</div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h2>Ketersediaan waktu 7 hari ke depan</h2></div>
    <div class="tabel-wrap">
    <table class="data">
        <thead><tr><th>Tanggal</th><th>Waktu kosong</th><th>Disarankan terpakai</th></tr></thead>
        <tbody>
        <?php $n = 0; foreach ($ketersediaan as $tgl => $v): if (++$n > 7) { break; } ?>
            <tr>
                <td><?= e(nama_hari_tanggal($tgl)) ?>, <?= e(tanggal_indo($tgl)) ?></td>
                <td><?= e(fmt_durasi($v['kosong'])) ?></td>
                <td><?= e(fmt_durasi($v['dipakai'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
