<?php
require_once __DIR__ . '/includes/init.php';

// Bulan yang ditampilkan (format YYYY-MM)
$bulan = $_GET['bulan'] ?? date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
    $bulan = date('Y-m');
}
$awalBulan = $bulan . '-01';
$jmlHari = (int)date('t', strtotime($awalBulan));
$offset = (int)date('N', strtotime($awalBulan)) - 1;            // Senin = 0
$totalSel = (int)(ceil(($offset + $jmlHari) / 7) * 7);
$gridAwal = date('Y-m-d', strtotime($awalBulan . ' -' . $offset . ' day'));
$gridAkhir = date('Y-m-d', strtotime($gridAwal . ' +' . ($totalSel - 1) . ' day'));

$tanggalPilih = $_GET['tanggal'] ?? '';
if (!valid_tanggal($tanggalPilih)) {
    $tanggalPilih = (date('Y-m') === $bulan) ? date('Y-m-d') : $awalBulan;
}

// Gabungan jadwal kuliah + deadline tugas + sesi belajar
$agenda = ambil_agenda($conn, $gridAwal, $gridAkhir);
$perTanggal = [];
foreach ($agenda as $ev) {
    $perTanggal[$ev['tanggal']][] = $ev;
}
$agendaPilih = $perTanggal[$tanggalPilih] ?? [];
if ($tanggalPilih < $gridAwal || $tanggalPilih > $gridAkhir) {
    $agendaPilih = ambil_agenda($conn, $tanggalPilih, $tanggalPilih);
}

$bulanLalu = date('Y-m', strtotime($awalBulan . ' -1 month'));
$bulanDepan = date('Y-m', strtotime($awalBulan . ' +1 month'));
$namaBulan = NAMA_BULAN[(int)date('n', strtotime($awalBulan))] . ' ' . date('Y', strtotime($awalBulan));
$hariIni = date('Y-m-d');

$judul_halaman = 'Kalender Akademik';
$menu_aktif = 'kalender';
include __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2><?= e($namaBulan) ?></h2>
        <div class="kal-nav tidak-cetak">
            <a class="btn btn-sekunder btn-kecil" href="kalender.php?bulan=<?= e($bulanLalu) ?>">&laquo; Sebelumnya</a>
            <a class="btn btn-sekunder btn-kecil" href="kalender.php">Bulan Ini</a>
            <a class="btn btn-sekunder btn-kecil" href="kalender.php?bulan=<?= e($bulanDepan) ?>">Berikutnya &raquo;</a>
        </div>
    </div>
    <div class="kal-grid">
        <?php foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $h): ?>
            <div class="kal-head"><?= $h ?></div>
        <?php endforeach; ?>
        <?php for ($i = 0; $i < $totalSel; $i++):
            $tgl = date('Y-m-d', strtotime($gridAwal . ' +' . $i . ' day'));
            $item = $perTanggal[$tgl] ?? [];
            $kelas = 'kal-sel';
            if (substr($tgl, 0, 7) !== $bulan) { $kelas .= ' luar'; }
            if ($tgl === $hariIni) { $kelas .= ' hari-ini'; }
            if ($tgl === $tanggalPilih) { $kelas .= ' dipilih'; }
            ?>
            <a class="<?= $kelas ?>" href="kalender.php?bulan=<?= e($bulan) ?>&amp;tanggal=<?= e($tgl) ?>">
                <span class="kal-tgl"><?= (int)date('j', strtotime($tgl)) ?></span>
                <?php foreach (array_slice($item, 0, 3) as $ev):
                    $gaya = ($ev['jenis'] === 'kuliah' && $ev['warna']) ? ' style="border-left-color:' . e($ev['warna']) . '"' : ''; ?>
                    <span class="pill tipe-<?= e($ev['jenis']) ?>"<?= $gaya ?>><?= $ev['mulai'] ? e($ev['mulai']) . ' ' : '' ?><?= e($ev['judul']) ?></span>
                <?php endforeach; ?>
                <?php if (count($item) > 3): ?><span class="pill-lagi">+<?= count($item) - 3 ?> lagi</span><?php endif; ?>
            </a>
        <?php endfor; ?>
    </div>
    <div class="legenda">
        <span><i style="background:var(--navy)"></i>Jadwal kuliah</span>
        <span><i style="background:#C98A1B"></i>Deadline tugas</span>
        <span><i style="background:#6F8BBA"></i>Sesi belajar</span>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Agenda <?= e(tanggal_panjang($tanggalPilih)) ?></h2>
        <div class="aksi tidak-cetak">
            <a class="btn btn-sekunder btn-kecil" href="sesi.php?tambah=1">+ Sesi Belajar</a>
            <a class="btn btn-sekunder btn-kecil" href="tugas.php?tambah=1">+ Tugas</a>
        </div>
    </div>
    <?php if (!$agendaPilih): ?>
        <div class="kosong">Tidak ada agenda pada tanggal ini.</div>
    <?php else: ?>
        <?php foreach ($agendaPilih as $ev) { echo item_agenda_html($ev); } ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
