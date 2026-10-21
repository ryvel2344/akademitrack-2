<?php
require_once __DIR__ . '/includes/init.php';

$sampai = valid_tanggal($_GET['sampai'] ?? '') ? $_GET['sampai'] : date('Y-m-d');
$dari = valid_tanggal($_GET['dari'] ?? '') ? $_GET['dari'] : date('Y-m-d', strtotime('-29 days'));
if ($dari > $sampai) {
    [$dari, $sampai] = [$sampai, $dari];
}
$hariIni = date('Y-m-d');

/* ---------- 1. Tugas (berdasarkan deadline dalam periode) ---------- */
$tugas = db_all($conn, "SELECT t.*, m.nama_mata_kuliah FROM tugas t
    LEFT JOIN mata_kuliah m ON m.id = t.mata_kuliah_id
    WHERE t.deadline BETWEEN ? AND ?", 'ss', [$dari, $sampai]);
$tTotal = count($tugas);
$tStatus = ['Belum Mulai' => 0, 'Dikerjakan' => 0, 'Selesai' => 0];
$tTerlambat = 0;
$perMk = [];
$perPrioritas = ['Tinggi' => [0, 0], 'Sedang' => [0, 0], 'Rendah' => [0, 0]];   // [total, selesai]
foreach ($tugas as $t) {
    $tStatus[$t['status']]++;
    $selesai = $t['status'] === 'Selesai';
    if (!$selesai && $t['deadline'] < $hariIni) {
        $tTerlambat++;
    }
    $mk = $t['nama_mata_kuliah'] ?: 'Tanpa mata kuliah';
    $perMk[$mk] = $perMk[$mk] ?? [0, 0];
    $perMk[$mk][0]++;
    if ($selesai) {
        $perMk[$mk][1]++;
        $perPrioritas[$t['prioritas']][1]++;
    }
    $perPrioritas[$t['prioritas']][0]++;
}
ksort($perMk);

/* ---------- 2. Aktivitas belajar (sesi dengan tanggal mulai dalam periode) ---------- */
$sesi = db_all($conn, "SELECT s.*, TIMESTAMPDIFF(MINUTE, s.waktu_mulai, s.waktu_selesai) AS menit
    FROM sesi_belajar s WHERE DATE(s.waktu_mulai) BETWEEN ? AND ?", 'ss', [$dari, $sampai]);
$sStatus = ['Terjadwal' => 0, 'Selesai' => 0, 'Dibatalkan' => 0];
$menitSelesai = 0;
$menitRencana = 0;
$perJenis = [];
foreach (JENIS_SESI as $j) {
    $perJenis[$j] = ['jumlah' => 0, 'selesai' => 0, 'menit' => 0];
}
$perMinggu = [];
$cur = strtotime($dari);
$cur = strtotime('-' . ((int)date('N', $cur) - 1) . ' day', $cur);          // Senin pekan pertama
while ($cur <= strtotime($sampai)) {
    $perMinggu[date('Y-m-d', $cur)] = ['selesai' => 0, 'rencana' => 0];
    $cur = strtotime('+7 day', $cur);
}
foreach ($sesi as $s) {
    $sStatus[$s['status']]++;
    $perJenis[$s['jenis']]['jumlah']++;
    $ts = strtotime(substr($s['waktu_mulai'], 0, 10));
    $senin = date('Y-m-d', strtotime('-' . ((int)date('N', $ts) - 1) . ' day', $ts));
    if ($s['status'] === 'Selesai') {
        $menitSelesai += (int)$s['menit'];
        $perJenis[$s['jenis']]['selesai']++;
        $perJenis[$s['jenis']]['menit'] += (int)$s['menit'];
        if (isset($perMinggu[$senin])) { $perMinggu[$senin]['selesai'] += (int)$s['menit']; }
    } elseif ($s['status'] === 'Terjadwal') {
        $menitRencana += (int)$s['menit'];
        if (isset($perMinggu[$senin])) { $perMinggu[$senin]['rencana'] += (int)$s['menit']; }
    }
}
$sTotal = count($sesi);
$sAktif = $sStatus['Selesai'] + $sStatus['Terjadwal'] + $sStatus['Dibatalkan'];
$maksMinggu = 1;
foreach ($perMinggu as $v) {
    $maksMinggu = max($maksMinggu, $v['selesai'] + $v['rencana']);
}

/* ---------- 3. Target skill (kondisi saat ini) ---------- */
$skills = db_all($conn, "SELECT k.*,
    (SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, s.waktu_mulai, s.waktu_selesai)), 0)
       FROM sesi_belajar s WHERE s.target_skill_id = k.id AND s.status = 'Selesai') AS menit_latihan
    FROM target_skill k ORDER BY k.status, k.nama_skill");
$kTotal = count($skills);
$kStatus = ['Berjalan' => 0, 'Tercapai' => 0, 'Ditunda' => 0];
$jumlahProgres = 0;
foreach ($skills as $k) {
    $kStatus[$k['status']]++;
    $jumlahProgres += (int)$k['progres'];
}

$judul_halaman = 'Laporan';
$menu_aktif = 'laporan';
include __DIR__ . '/includes/header.php';
?>


<div class="card tidak-cetak laporan-filter-card">
    <form method="get" action="laporan.php" class="filter">
        <div class="field"><label for="dari">Dari tanggal</label><input type="date" id="dari" name="dari" value="<?= e($dari) ?>"></div>
        <div class="field"><label for="sampai">Sampai tanggal</label><input type="date" id="sampai" name="sampai" value="<?= e($sampai) ?>"></div>
        <button type="submit" class="btn">Tampilkan</button>
        <button type="button" class="btn btn-sekunder" onclick="window.print()">Cetak</button>
    </form>
</div>
<p class="muted">Periode laporan: <strong><?= e(tanggal_indo($dari)) ?></strong> s.d. <strong><?= e(tanggal_indo($sampai)) ?></strong>. Tugas dihitung berdasarkan tanggal deadline, sesi belajar berdasarkan tanggal mulai; target skill menampilkan kondisi terkini.</p>

<h2 style="margin:6px 0 12px">1. Perkembangan Tugas</h2>
<div class="grid grid-4" style="margin-bottom:20px">
    <div class="card stat"><div class="angka"><?= $tTotal ?></div><div class="label">Total tugas (deadline dalam periode)</div></div>
    <div class="card stat"><div class="angka"><?= $tStatus['Selesai'] ?></div><div class="label">Selesai (<?= persen($tStatus['Selesai'], $tTotal) ?>%)</div></div>
    <div class="card stat"><div class="angka"><?= $tStatus['Dikerjakan'] + $tStatus['Belum Mulai'] ?></div><div class="label">Belum selesai</div></div>
    <div class="card stat <?= $tTerlambat > 0 ? 'peringatan' : '' ?>"><div class="angka"><?= $tTerlambat ?></div><div class="label">Terlambat (lewat deadline, belum selesai)</div></div>
</div>
<div class="grid grid-2" style="margin-bottom:20px">
    <div class="card">
        <div class="card-header"><h3>Per Mata Kuliah</h3></div>
        <?php if (!$perMk): ?><div class="kosong">Tidak ada tugas pada periode ini.</div><?php else: ?>
        <table class="data"><thead><tr><th>Mata Kuliah</th><th>Total</th><th>Selesai</th><th>%</th></tr></thead><tbody>
            <?php foreach ($perMk as $nama => $v): ?>
                <tr><td><?= e($nama) ?></td><td><?= $v[0] ?></td><td><?= $v[1] ?></td><td><?= persen($v[1], $v[0]) ?>%</td></tr>
            <?php endforeach; ?>
        </tbody></table>
        <?php endif; ?>
    </div>
    <div class="card">
        <div class="card-header"><h3>Per Prioritas &amp; Status</h3></div>
        <table class="data"><thead><tr><th>Prioritas</th><th>Total</th><th>Selesai</th><th>%</th></tr></thead><tbody>
            <?php foreach ($perPrioritas as $nama => $v): ?>
                <tr><td><?= badge_status($nama) ?></td><td><?= $v[0] ?></td><td><?= $v[1] ?></td><td><?= persen($v[1], $v[0]) ?>%</td></tr>
            <?php endforeach; ?>
        </tbody></table>
        
<div class="status-ringkasan">
    <?php foreach ($tStatus as $nama => $n): ?>
        <div class="status-ringkasan-item"><?= badge_status($nama) ?><strong><?= $n ?></strong></div>
    <?php endforeach; ?>
</div>

    </div>
</div>

<h2 style="margin:6px 0 12px">2. Aktivitas Belajar</h2>
<div class="grid grid-4" style="margin-bottom:20px">
    <div class="card stat"><div class="angka"><?= $sTotal ?></div><div class="label">Total sesi dalam periode</div></div>
    <div class="card stat"><div class="angka"><?= e(fmt_durasi($menitSelesai)) ?></div><div class="label">Waktu belajar terealisasi (<?= $sStatus['Selesai'] ?> sesi selesai)</div></div>
    <div class="card stat"><div class="angka"><?= e(fmt_durasi($menitRencana)) ?></div><div class="label">Waktu masih terjadwal (<?= $sStatus['Terjadwal'] ?> sesi)</div></div>
    <div class="card stat"><div class="angka"><?= persen($sStatus['Selesai'], $sAktif) ?>%</div><div class="label">Sesi selesai dari seluruh sesi tercatat (<?= $sStatus['Dibatalkan'] ?> dibatalkan)</div></div>
</div>
<div class="grid grid-2" style="margin-bottom:20px">
    <div class="card">
        <div class="card-header"><h3>Per Jenis Sesi</h3></div>
        <table class="data"><thead><tr><th>Jenis</th><th>Sesi</th><th>Selesai</th><th>Waktu selesai</th></tr></thead><tbody>
            <?php foreach ($perJenis as $nama => $v): ?>
                <tr><td><?= e($nama) ?></td><td><?= $v['jumlah'] ?></td><td><?= $v['selesai'] ?></td><td><?= e(fmt_durasi($v['menit'])) ?></td></tr>
            <?php endforeach; ?>
        </tbody></table>
    </div>
    <div class="card">
        <div class="card-header"><h3>Waktu Belajar per Pekan</h3></div>
        <?php foreach ($perMinggu as $senin => $v): ?>
            <div class="bar-baris">
                <span><?= e(tanggal_indo($senin)) ?></span>
                <div class="bar">
                    <span class="b-selesai" style="width:<?= round($v['selesai'] * 100 / $maksMinggu) ?>%"></span>
                    <span class="b-rencana" style="width:<?= round($v['rencana'] * 100 / $maksMinggu) ?>%"></span>
                </div>
                <span class="muted"><?= e(fmt_durasi($v['selesai'] + $v['rencana'])) ?></span>
            </div>
        <?php endforeach; ?>
        <div class="legenda"><span><i style="background:var(--navy)"></i>Selesai</span><span><i style="background:#9DB0CF"></i>Terjadwal</span><span>Label = Senin awal pekan</span></div>
    </div>
</div>

<h2 style="margin:6px 0 12px">3. Target Skill</h2>
<div class="card">
    <?php if (!$skills): ?><div class="kosong">Belum ada target skill.</div><?php else: ?>
    <p class="muted" style="margin-top:0"><?= $kTotal ?> skill — <?= $kStatus['Berjalan'] ?> berjalan, <?= $kStatus['Tercapai'] ?> tercapai, <?= $kStatus['Ditunda'] ?> ditunda. Rata-rata progres <?= persen($jumlahProgres, $kTotal * 100) ?>%.</p>
    <table class="data"><thead><tr><th>Skill</th><th>Status</th><th>Target</th><th style="width:180px">Progres</th><th>Latihan selesai</th></tr></thead><tbody>
        <?php foreach ($skills as $k): ?>
            <tr>
                <td><?= e($k['nama_skill']) ?></td>
                <td><?= badge_status($k['status']) ?></td>
                <td><?= $k['target_tanggal'] ? e(tanggal_indo($k['target_tanggal'])) : '-' ?></td>
                <td><div class="progress <?= $k['status'] === 'Tercapai' ? 'ok' : '' ?>"><span style="width:<?= (int)$k['progres'] ?>%"></span></div><span class="kecil muted"><?= (int)$k['progres'] ?>%</span></td>
                <td><?= e(fmt_durasi($k['menit_latihan'])) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
