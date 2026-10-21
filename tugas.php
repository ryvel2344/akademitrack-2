<?php
require_once __DIR__ . '/includes/init.php';

$errors = [];
$form = ['id' => 0, 'mata_kuliah_id' => '', 'judul' => '', 'deskripsi' => '', 'deadline' => date('Y-m-d', strtotime('+7 days')),
    'prioritas' => 'Sedang', 'estimasi_menit' => '', 'status' => 'Belum Mulai'];
$daftarMk = db_all($conn, "SELECT id, nama_mata_kuliah FROM mata_kuliah ORDER BY nama_mata_kuliah");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'simpan') {
        $form['id'] = (int)($_POST['id'] ?? 0);
        $form['mata_kuliah_id'] = (int)($_POST['mata_kuliah_id'] ?? 0);
        $form['judul'] = trim($_POST['judul'] ?? '');
        $form['deskripsi'] = trim($_POST['deskripsi'] ?? '');
        $form['deadline'] = trim($_POST['deadline'] ?? '');
        $form['prioritas'] = $_POST['prioritas'] ?? '';
        $form['estimasi_menit'] = trim($_POST['estimasi_menit'] ?? '');
        $form['status'] = $_POST['status'] ?? '';

        if ($form['judul'] === '') {
            $errors[] = 'Judul tugas wajib diisi.';
        } elseif (mb_strlen($form['judul']) > 150) {
            $errors[] = 'Judul maksimal 150 karakter.';
        }
        if (!valid_tanggal($form['deadline'])) {
            $errors[] = 'Deadline wajib diisi dengan tanggal yang valid.';
        }
        if (!in_array($form['prioritas'], PRIORITAS, true)) {
            $errors[] = 'Prioritas tidak valid.';
        }
        if (!in_array($form['status'], STATUS_TUGAS, true)) {
            $errors[] = 'Status tidak valid.';
        }
        $estimasi = null;
        if ($form['estimasi_menit'] !== '') {
            if (!ctype_digit($form['estimasi_menit']) || (int)$form['estimasi_menit'] < 5 || (int)$form['estimasi_menit'] > 6000) {
                $errors[] = 'Estimasi waktu harus berupa angka menit antara 5 dan 6000.';
            } else {
                $estimasi = (int)$form['estimasi_menit'];
            }
        }
        $mkId = null;
        if ($form['mata_kuliah_id'] > 0) {
            if (db_one($conn, "SELECT id FROM mata_kuliah WHERE id = ?", 'i', [$form['mata_kuliah_id']])) {
                $mkId = $form['mata_kuliah_id'];
            } else {
                $errors[] = 'Mata kuliah tidak ditemukan.';
            }
        }

        if (!$errors) {
            $desk = $form['deskripsi'] === '' ? null : $form['deskripsi'];
            if ($form['id'] > 0) {
                db_exec($conn, "UPDATE tugas SET mata_kuliah_id = ?, judul = ?, deskripsi = ?, deadline = ?, prioritas = ?, estimasi_menit = ?, status = ? WHERE id = ?",
                    'issssisi', [$mkId, $form['judul'], $desk, $form['deadline'], $form['prioritas'], $estimasi, $form['status'], $form['id']]);
                flash('sukses', 'Tugas berhasil diperbarui.');
            } else {
                db_exec($conn, "INSERT INTO tugas (mata_kuliah_id, judul, deskripsi, deadline, prioritas, estimasi_menit, status) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    'issssis', [$mkId, $form['judul'], $desk, $form['deadline'], $form['prioritas'], $estimasi, $form['status']]);
                flash('sukses', 'Tugas berhasil ditambahkan.');
            }
            redirect('tugas.php');
        }
    } elseif ($aksi === 'status') {
        $status = $_POST['status'] ?? '';
        if (in_array($status, STATUS_TUGAS, true)) {
            db_exec($conn, "UPDATE tugas SET status = ? WHERE id = ?", 'si', [$status, (int)($_POST['id'] ?? 0)]);
            flash('sukses', 'Status tugas diubah menjadi "' . $status . '".');
        }
        redirect('tugas.php?' . http_build_query(['status' => $_POST['kembali_status'] ?? 'aktif', 'mk' => (int)($_POST['kembali_mk'] ?? 0)]));
    } elseif ($aksi === 'hapus') {
        db_exec($conn, "DELETE FROM tugas WHERE id = ?", 'i', [(int)($_POST['id'] ?? 0)]);
        flash('sukses', 'Tugas dihapus (sesi belajar terkait tetap ada tanpa tugas).');
        redirect('tugas.php');
    }
}

if (!$errors && isset($_GET['edit'])) {
    $row = db_one($conn, "SELECT * FROM tugas WHERE id = ?", 'i', [(int)$_GET['edit']]);
    if ($row) {
        $form = $row;
        $form['mata_kuliah_id'] = (int)$row['mata_kuliah_id'];
    } else {
        flash('error', 'Data tugas tidak ditemukan.');
        redirect('tugas.php');
    }
}
$tampilForm = $errors || isset($_GET['tambah']) || isset($_GET['edit']);

// Filter daftar
$fStatus = $_GET['status'] ?? 'aktif';
$fMk = (int)($_GET['mk'] ?? 0);
$where = [];
$types = '';
$params = [];
if ($fStatus === 'aktif') {
    $where[] = "t.status <> 'Selesai'";
} elseif (in_array($fStatus, STATUS_TUGAS, true)) {
    $where[] = 't.status = ?';
    $types .= 's';
    $params[] = $fStatus;
} else {
    $fStatus = 'semua';
}
if ($fMk > 0) {
    $where[] = 't.mata_kuliah_id = ?';
    $types .= 'i';
    $params[] = $fMk;
}
$sql = "SELECT t.*, m.nama_mata_kuliah, m.warna,
        (SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, s.waktu_mulai, s.waktu_selesai)), 0)
           FROM sesi_belajar s WHERE s.tugas_id = t.id AND s.status IN ('Terjadwal', 'Selesai')) AS menit_sesi
        FROM tugas t LEFT JOIN mata_kuliah m ON m.id = t.mata_kuliah_id"
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . " ORDER BY (t.status = 'Selesai'), t.deadline ASC, FIELD(t.prioritas, 'Tinggi', 'Sedang', 'Rendah')";
$daftar = db_all($conn, $sql, $types, $params);

$judul_halaman = 'Tugas';
$menu_aktif = 'tugas';
include __DIR__ . '/includes/header.php';
?>

<?php if ($errors): ?>
    <div class="alert alert-error">Data belum bisa disimpan:<ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if ($tampilForm): ?>
<div class="card">
    <div class="card-header"><h2><?= (int)$form['id'] > 0 ? 'Ubah Tugas' : 'Tambah Tugas' ?></h2></div>
    <form method="post" action="tugas.php" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
        <div class="field penuh">
            <label for="judul">Judul Tugas *</label>
            <input type="text" id="judul" name="judul" maxlength="150" required value="<?= e($form['judul']) ?>">
        </div>
        <div class="field">
            <label for="mk">Mata Kuliah</label>
            <select id="mk" name="mata_kuliah_id">
                <option value="0">— Tanpa mata kuliah —</option>
                <?php foreach ($daftarMk as $m): ?>
                    <option value="<?= (int)$m['id'] ?>" <?= (int)$form['mata_kuliah_id'] === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['nama_mata_kuliah']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="deadline">Deadline *</label>
            <input type="date" id="deadline" name="deadline" required value="<?= e($form['deadline']) ?>">
        </div>
        <div class="field">
            <label for="prioritas">Prioritas</label>
            <select id="prioritas" name="prioritas"><?= opsi_list(PRIORITAS, $form['prioritas']) ?></select>
        </div>
        <div class="field">
            <label for="estimasi">Estimasi Pengerjaan (menit)</label>
            <input type="number" id="estimasi" name="estimasi_menit" min="5" max="6000" step="5" value="<?= e($form['estimasi_menit']) ?>">
            <span class="bantuan">Kosong = dianggap 60 menit pada rekomendasi waktu belajar.</span>
        </div>
        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status"><?= opsi_list(STATUS_TUGAS, $form['status']) ?></select>
        </div>
        <div class="field penuh">
            <label for="deskripsi">Deskripsi</label>
            <textarea id="deskripsi" name="deskripsi"><?= e($form['deskripsi']) ?></textarea>
        </div>
        <div class="form-aksi">
            <button type="submit" class="btn">Simpan</button>
            <a href="tugas.php" class="btn btn-sekunder">Batal</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card tidak-cetak">
    <form method="get" action="tugas.php" class="filter">
        <div class="field">
            <label for="f_status">Status</label>
            <select id="f_status" name="status">
                <?= opsi_html(['aktif' => 'Belum selesai', 'semua' => 'Semua', 'Belum Mulai' => 'Belum Mulai', 'Dikerjakan' => 'Dikerjakan', 'Selesai' => 'Selesai'], $fStatus) ?>
            </select>
        </div>
        <div class="field">
            <label for="f_mk">Mata Kuliah</label>
            <select id="f_mk" name="mk">
                <option value="0">Semua</option>
                <?php foreach ($daftarMk as $m): ?>
                    <option value="<?= (int)$m['id'] ?>" <?= $fMk === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['nama_mata_kuliah']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-sekunder">Terapkan</button>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2>Daftar Tugas (<?= count($daftar) ?>)</h2>
        <div class="aksi"><a href="tugas.php?tambah=1" class="btn">+ Tambah Tugas</a></div>
    </div>
    <?php if (!$daftar): ?>
        <div class="kosong">Tidak ada tugas pada filter ini.</div>
    <?php else: ?>
    <div class="tabel-wrap">
    <table class="data">
        <thead><tr><th>Tugas</th><th>Deadline</th><th>Prioritas</th><th>Estimasi</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($daftar as $t):
            $dl = info_deadline($t['deadline'], $t['status']); ?>
            <tr class="<?= $t['status'] === 'Selesai' ? 'selesai' : '' ?>">
                <td>
                    <div class="judul-tugas"><strong><?= e($t['judul']) ?></strong></div>
                    <div class="muted kecil">
                        <?php if ($t['nama_mata_kuliah']): ?><span class="dot" style="background:<?= e($t['warna']) ?>"></span><?= e($t['nama_mata_kuliah']) ?><?php else: ?>Tanpa mata kuliah<?php endif; ?>
                    </div>
                </td>
                <td><?= e(tanggal_indo($t['deadline'])) ?><br><?= badge($dl[0], $dl[1]) ?></td>
                <td><?= badge_status($t['prioritas']) ?></td>
                <td><?= e(fmt_durasi($t['estimasi_menit'])) ?>
                    <?php if ((int)$t['menit_sesi'] > 0): ?><div class="muted kecil">Terjadwal: <?= e(fmt_durasi($t['menit_sesi'])) ?></div><?php endif; ?></td>
                <td>
                    <form method="post" action="tugas.php" class="inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="status">
                        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                        <input type="hidden" name="kembali_status" value="<?= e($fStatus) ?>">
                        <input type="hidden" name="kembali_mk" value="<?= (int)$fMk ?>">
                        <select name="status" onchange="this.form.submit()" aria-label="Ubah status"><?= opsi_list(STATUS_TUGAS, $t['status']) ?></select>
                    </form>
                </td>
                <td class="aksi">
                    <a class="btn btn-sekunder btn-kecil" href="sesi.php?tambah=1&amp;tugas_id=<?= (int)$t['id'] ?>">Jadwalkan Belajar</a>
                    <a class="btn btn-sekunder btn-kecil" href="tugas.php?edit=<?= (int)$t['id'] ?>">Ubah</a>
                    <form method="post" action="tugas.php" class="inline" data-confirm="Hapus tugas ini?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
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
