<?php
require_once __DIR__ . '/includes/init.php';

$errors = [];
$form = ['id' => 0, 'nama_skill' => '', 'deskripsi' => '', 'target_tanggal' => '', 'progres' => 0, 'status' => 'Berjalan'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'simpan') {
        $form['id'] = (int)($_POST['id'] ?? 0);
        $form['nama_skill'] = trim($_POST['nama_skill'] ?? '');
        $form['deskripsi'] = trim($_POST['deskripsi'] ?? '');
        $form['target_tanggal'] = trim($_POST['target_tanggal'] ?? '');
        $form['progres'] = (int)($_POST['progres'] ?? 0);
        $form['status'] = $_POST['status'] ?? '';

        if ($form['nama_skill'] === '') {
            $errors[] = 'Nama skill wajib diisi.';
        } elseif (mb_strlen($form['nama_skill']) > 100) {
            $errors[] = 'Nama skill maksimal 100 karakter.';
        }
        if ($form['target_tanggal'] !== '' && !valid_tanggal($form['target_tanggal'])) {
            $errors[] = 'Target tanggal tidak valid.';
        }
        if ($form['progres'] < 0 || $form['progres'] > 100) {
            $errors[] = 'Progres harus antara 0 dan 100.';
        }
        if (!in_array($form['status'], STATUS_SKILL, true)) {
            $errors[] = 'Status tidak valid.';
        }

        if (!$errors) {
            // Aturan sinkronisasi: status Tercapai => progres 100; progres 100 pada status Berjalan => Tercapai
            if ($form['status'] === 'Tercapai') {
                $form['progres'] = 100;
            } elseif ($form['progres'] === 100 && $form['status'] === 'Berjalan') {
                $form['status'] = 'Tercapai';
            }
            $desk = $form['deskripsi'] === '' ? null : $form['deskripsi'];
            $tgl = $form['target_tanggal'] === '' ? null : $form['target_tanggal'];
            if ($form['id'] > 0) {
                db_exec($conn, "UPDATE target_skill SET nama_skill = ?, deskripsi = ?, target_tanggal = ?, progres = ?, status = ? WHERE id = ?",
                    'sssisi', [$form['nama_skill'], $desk, $tgl, $form['progres'], $form['status'], $form['id']]);
                flash('sukses', 'Target skill berhasil diperbarui.');
            } else {
                db_exec($conn, "INSERT INTO target_skill (nama_skill, deskripsi, target_tanggal, progres, status) VALUES (?, ?, ?, ?, ?)",
                    'sssis', [$form['nama_skill'], $desk, $tgl, $form['progres'], $form['status']]);
                flash('sukses', 'Target skill berhasil ditambahkan.');
            }
            redirect('skill.php');
        }
    } elseif ($aksi === 'hapus') {
        db_exec($conn, "DELETE FROM target_skill WHERE id = ?", 'i', [(int)($_POST['id'] ?? 0)]);
        flash('sukses', 'Target skill dihapus (sesi latihan terkait tetap ada tanpa skill).');
        redirect('skill.php');
    }
}

if (!$errors && isset($_GET['edit'])) {
    $row = db_one($conn, "SELECT * FROM target_skill WHERE id = ?", 'i', [(int)$_GET['edit']]);
    if ($row) {
        $form = $row;
        $form['target_tanggal'] = $row['target_tanggal'] ?? '';
    } else {
        flash('error', 'Data skill tidak ditemukan.');
        redirect('skill.php');
    }
}
$tampilForm = $errors || isset($_GET['tambah']) || isset($_GET['edit']);

$daftar = db_all($conn, "SELECT k.*,
        (SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, s.waktu_mulai, s.waktu_selesai)), 0)
           FROM sesi_belajar s WHERE s.target_skill_id = k.id AND s.status = 'Selesai') AS menit_latihan,
        (SELECT COUNT(*) FROM sesi_belajar s WHERE s.target_skill_id = k.id AND s.status = 'Terjadwal' AND s.waktu_mulai >= ?) AS sesi_depan
    FROM target_skill k
    ORDER BY (k.status = 'Tercapai'), (k.target_tanggal IS NULL), k.target_tanggal ASC, k.nama_skill", 's', [date('Y-m-d H:i:s')]);

$judul_halaman = 'Target Skill';
$menu_aktif = 'skill';
include __DIR__ . '/includes/header.php';
?>

<?php if ($errors): ?>
    <div class="alert alert-error">Data belum bisa disimpan:<ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if ($tampilForm): ?>
<div class="card">
    <div class="card-header"><h2><?= (int)$form['id'] > 0 ? 'Ubah Target Skill' : 'Tambah Target Skill' ?></h2></div>
    <form method="post" action="skill.php" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
        <div class="field penuh">
            <label for="nama_skill">Nama Skill *</label>
            <input type="text" id="nama_skill" name="nama_skill" maxlength="100" required value="<?= e($form['nama_skill']) ?>">
        </div>
        <div class="field">
            <label for="target_tanggal">Target Tanggal</label>
            <input type="date" id="target_tanggal" name="target_tanggal" value="<?= e($form['target_tanggal']) ?>">
        </div>
        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status"><?= opsi_list(STATUS_SKILL, $form['status']) ?></select>
        </div>
        <div class="field penuh">
            <label for="progres">Progres: <strong id="progres-nilai"><?= (int)$form['progres'] ?>%</strong></label>
            <input type="range" id="progres" name="progres" min="0" max="100" step="5" value="<?= (int)$form['progres'] ?>">
            <span class="bantuan">Progres 100% otomatis menandai skill sebagai Tercapai.</span>
        </div>
        <div class="field penuh">
            <label for="deskripsi">Deskripsi</label>
            <textarea id="deskripsi" name="deskripsi"><?= e($form['deskripsi']) ?></textarea>
        </div>
        <div class="form-aksi">
            <button type="submit" class="btn">Simpan</button>
            <a href="skill.php" class="btn btn-sekunder">Batal</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Daftar Target Skill (<?= count($daftar) ?>)</h2>
        <div class="aksi"><a href="skill.php?tambah=1" class="btn">+ Tambah Skill</a></div>
    </div>
    <?php if (!$daftar): ?>
        <div class="kosong">Belum ada target skill.</div>
    <?php else: ?>
    <div class="tabel-wrap">
    <table class="data">
        <thead><tr><th>Skill</th><th>Target</th><th style="width:200px">Progres</th><th>Latihan</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($daftar as $k):
            $sisa = $k['target_tanggal'] ? selisih_hari(date('Y-m-d'), $k['target_tanggal']) : null; ?>
            <tr>
                <td><strong><?= e($k['nama_skill']) ?></strong>
                    <?php if ($k['deskripsi']): ?><div class="muted kecil"><?= e($k['deskripsi']) ?></div><?php endif; ?></td>
                <td><?php if ($k['target_tanggal']): ?>
                        <?= e(tanggal_indo($k['target_tanggal'])) ?>
                        <?php if ($k['status'] !== 'Tercapai'): ?><div class="kecil <?= $sisa < 0 ? '' : 'muted' ?>"><?= $sisa < 0 ? badge('Lewat ' . abs($sisa) . ' hari', 'badge-danger') : e($sisa . ' hari lagi') ?></div><?php endif; ?>
                    <?php else: ?>-<?php endif; ?></td>
                <td><div class="progress <?= $k['status'] === 'Tercapai' ? 'ok' : '' ?>"><span style="width:<?= (int)$k['progres'] ?>%"></span></div>
                    <div class="kecil muted"><?= (int)$k['progres'] ?>%</div></td>
                <td><?= e(fmt_durasi($k['menit_latihan'])) ?>
                    <?php if ((int)$k['sesi_depan'] > 0): ?><div class="muted kecil"><?= (int)$k['sesi_depan'] ?> sesi terjadwal</div><?php endif; ?></td>
                <td><?= badge_status($k['status']) ?></td>
                <td class="aksi">
                    <a class="btn btn-sekunder btn-kecil" href="sesi.php?tambah=1&amp;skill_id=<?= (int)$k['id'] ?>">Jadwalkan Latihan</a>
                    <a class="btn btn-sekunder btn-kecil" href="skill.php?edit=<?= (int)$k['id'] ?>">Ubah</a>
                    <form method="post" action="skill.php" class="inline" data-confirm="Hapus target skill ini?">
                        <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
                        <button type="submit" class="btn btn-bahaya btn-kecil">Hapus</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
