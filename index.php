<?php
require_once __DIR__ . '/includes/init.php';

$hariIni = date('Y-m-d');
$sekarang = date('H:i');

// Agenda 7 hari ke depan (kuliah + deadline + sesi) dari database
$agenda7 = ambil_agenda($conn, $hariIni, date('Y-m-d', strtotime('+7 days')));

// Jadwal hari ini: kuliah + sesi belajar (deadline hari ini tampil di kartu tugas)
$hariIniAgenda = array_values(array_filter($agenda7, function ($ev) use ($hariIni) {
    return $ev['tanggal'] === $hariIni && $ev['jenis'] !== 'tugas';
}));

// Agenda berikutnya: kuliah / sesi 'Terjadwal' yang belum dimulai
$berikutnya = array_values(array_filter($agenda7, function ($ev) use ($hariIni, $sekarang) {
    if ($ev['jenis'] === 'tugas') {
        return false;
    }
    if ($ev['jenis'] === 'sesi' && $ev['status'] !== 'Terjadwal') {
        return false;
    }
    return $ev['tanggal'] > $hariIni || ($ev['tanggal'] === $hariIni && $ev['mulai'] > $sekarang);
}));
$berikutnya = array_slice($berikutnya, 0, 6);

// Tugas terlambat / mendekati deadline (<= 7 hari)
$tugasDekat = db_all($conn, "SELECT t.*, m.nama_mata_kuliah, m.warna FROM tugas t
    LEFT JOIN mata_kuliah m ON m.id = t.mata_kuliah_id
    WHERE t.status <> 'Selesai' AND t.deadline <= DATE_ADD(?, INTERVAL 7 DAY)
    ORDER BY t.deadline ASC, FIELD(t.prioritas, 'Tinggi', 'Sedang', 'Rendah') LIMIT 8", 's', [$hariIni]);

$skills = db_all($conn, "SELECT * FROM target_skill WHERE status = 'Berjalan'
    ORDER BY (target_tanggal IS NULL), target_tanggal ASC LIMIT 6");

// Kartu ringkasan
$stat = db_one($conn, "SELECT
    (SELECT COUNT(*) FROM tugas WHERE status <> 'Selesai') AS tugas_aktif,
    (SELECT COUNT(*) FROM tugas WHERE status <> 'Selesai' AND deadline < ?) AS tugas_terlambat,
    (SELECT COUNT(*) FROM tugas WHERE status <> 'Selesai' AND deadline BETWEEN ? AND DATE_ADD(?, INTERVAL 7 DAY)) AS tugas_minggu,
    (SELECT COUNT(*) FROM sesi_belajar WHERE status = 'Terjadwal' AND waktu_mulai >= ?) AS sesi_depan,
    (SELECT COUNT(*) FROM target_skill WHERE status = 'Berjalan') AS skill_berjalan,
    (SELECT COUNT(*) FROM mata_kuliah) AS jml_mk", 'ssss', [$hariIni, $hariIni, $hariIni, date('Y-m-d H:i:s')]);

$judul_halaman = 'Dashboard';
$menu_aktif = 'dashboard';
include __DIR__ . '/includes/header.php';
?>

<?php if ((int)$stat['jml_mk'] === 0): ?>
    <div class="alert alert-info">Database masih kosong. Mulai dengan menambah <a href="mata_kuliah.php?tambah=1">mata kuliah</a>, lalu <a href="jadwal.php">jadwal kuliah</a> dan <a href="tugas.php?tambah=1">tugas</a>.</div>
<?php endif; ?>

<div class="grid grid-4" style="margin-bottom:20px">
    <div class="card stat"><div class="angka"><?= (int)$stat['tugas_aktif'] ?></div><div class="label">Tugas belum selesai</div></div>
    <div class="card stat <?= (int)$stat['tugas_terlambat'] > 0 ? 'peringatan' : '' ?>"><div class="angka"><?= (int)$stat['tugas_terlambat'] ?></div><div class="label">Tugas terlambat</div></div>
    <div class="card stat"><div class="angka"><?= (int)$stat['tugas_minggu'] ?></div><div class="label">Deadline 7 hari ke depan</div></div>
    <div class="card stat"><div class="angka"><?= (int)$stat['sesi_depan'] ?></div><div class="label">Sesi belajar terjadwal</div></div>
</div>

<div class="grid grid-2" style="margin-bottom:20px">
    <div class="card">
        <div class="card-header"><h2>Jadwal Hari Ini</h2><a class="kecil" href="kalender.php">Buka kalender</a></div>
        <div class="muted kecil" style="margin-bottom:10px"><?= e(tanggal_panjang($hariIni)) ?></div>
        <?php if (!$hariIniAgenda): ?>
            <div class="kosong">Tidak ada kuliah atau sesi belajar hari ini.</div>
        <?php else: foreach ($hariIniAgenda as $ev) { echo item_agenda_html($ev); } endif; ?>
    </div>
    <div class="card">
        <div class="card-header"><h2>Agenda Berikutnya</h2><a class="kecil" href="sesi.php">Agenda belajar</a></div>
        <?php if (!$berikutnya): ?>
            <div class="kosong">Tidak ada agenda dalam 7 hari ke depan.</div>
        <?php else: foreach ($berikutnya as $ev) { echo item_agenda_html($ev, true); } endif; ?>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-header"><h2>Tugas Mendekati Deadline</h2><a class="kecil" href="tugas.php">Semua tugas</a></div>
        <?php if (!$tugasDekat): ?>
            <div class="kosong">Tidak ada tugas yang terlambat atau jatuh tempo dalam 7 hari.</div>
        <?php else: ?>
        <div class="tabel-wrap">
        <table class="data">
            <tbody>
            <?php foreach ($tugasDekat as $t): $dl = info_deadline($t['deadline'], $t['status']); ?>
                <tr>
                    <td><a href="tugas.php?edit=<?= (int)$t['id'] ?>"><strong><?= e($t['judul']) ?></strong></a>
                        <div class="muted kecil"><?= e($t['nama_mata_kuliah'] ?: 'Tanpa mata kuliah') ?> · <?= e(tanggal_indo($t['deadline'])) ?></div></td>
                    <td><?= badge_status($t['prioritas']) ?></td>
                    <td><?= badge($dl[0], $dl[1]) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
    <div class="card">
        <div class="card-header"><h2>Progres Target Skill</h2><a class="kecil" href="skill.php">Kelola</a></div>
        <?php if (!$skills): ?>
            <div class="kosong">Belum ada target skill yang berjalan.</div>
        <?php else: foreach ($skills as $k): ?>
            <div class="item-skill">
                <div class="baris">
                    <strong><?= e($k['nama_skill']) ?></strong>
                    <span class="muted"><?= (int)$k['progres'] ?>%</span>
                </div>
                <div class="progress"><span style="width:<?= (int)$k['progres'] ?>%"></span></div>
                <?php if ($k['target_tanggal']): ?><div class="muted kecil" style="margin-top:4px">Target <?= e(tanggal_indo($k['target_tanggal'])) ?></div><?php endif; ?>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
