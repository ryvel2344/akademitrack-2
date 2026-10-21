<?php
require_once __DIR__ . '/includes/init.php';

$errors = [];
$form = ['id' => 0, 'mata_kuliah_id' => '', 'hari' => 1, 'jam_mulai' => '08:00', 'jam_selesai' => '09:40', 'ruangan' => ''];
$daftarMk = db_all($conn, "SELECT id, nama_mata_kuliah FROM mata_kuliah ORDER BY nama_mata_kuliah");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'simpan') {
        $form['id'] = (int)($_POST['id'] ?? 0);
        $form['mata_kuliah_id'] = (int)($_POST['mata_kuliah_id'] ?? 0);
        $form['hari'] = (int)($_POST['hari'] ?? 0);
        $form['jam_mulai'] = trim($_POST['jam_mulai'] ?? '');
        $form['jam_selesai'] = trim($_POST['jam_selesai'] ?? '');
        $form['ruangan'] = trim($_POST['ruangan'] ?? '');

        if (!db_one($conn, "SELECT id FROM mata_kuliah WHERE id = ?", 'i', [$form['mata_kuliah_id']])) {
            $errors[] = 'Pilih mata kuliah yang valid.';
        }
        if ($form['hari'] < 1 || $form['hari'] > 7) {
            $errors[] = 'Hari tidak valid.';
        }
        if (!valid_jam($form['jam_mulai']) || !valid_jam($form['jam_selesai'])) {
            $errors[] = 'Jam mulai dan jam selesai wajib diisi dengan format yang benar.';
        } elseif ($form['jam_selesai'] <= $form['jam_mulai']) {
            $errors[] = 'Jam selesai harus lebih besar dari jam mulai.';
        }
        if (mb_strlen($form['ruangan']) > 100) {
            $errors[] = 'Ruangan maksimal 100 karakter.';
        }

        // Cek bentrok dengan jadwal kuliah lain pada hari yang sama
        if (!$errors) {
            $bentrok = db_all($conn, "SELECT j.jam_mulai, j.jam_selesai, m.nama_mata_kuliah
                FROM jadwal_kuliah j JOIN mata_kuliah m ON m.id = j.mata_kuliah_id
                WHERE j.hari = ? AND j.id <> ? AND j.jam_mulai < ? AND j.jam_selesai > ?",
                'iiss', [$form['hari'], $form['id'], $form['jam_selesai'] . ':00', $form['jam_mulai'] . ':00']);
            foreach ($bentrok as $b) {
                $errors[] = 'Bentrok dengan jadwal ' . $b['nama_mata_kuliah'] . ' (' . jam($b['jam_mulai']) . '–' . jam($b['jam_selesai']) . ').';
            }
        }

        if (!$errors) {
            $ruangan = $form['ruangan'] === '' ? null : $form['ruangan'];
            $mulai = $form['jam_mulai'] . ':00';
            $selesai = $form['jam_selesai'] . ':00';
            if ($form['id'] > 0) {
                db_exec($conn, "UPDATE jadwal_kuliah SET mata_kuliah_id = ?, hari = ?, jam_mulai = ?, jam_selesai = ?, ruangan = ? WHERE id = ?",
                    'iisssi', [$form['mata_kuliah_id'], $form['hari'], $mulai, $selesai, $ruangan, $form['id']]);
                flash('sukses', 'Jadwal kuliah berhasil diperbarui.');
            } else {
                db_exec($conn, "INSERT INTO jadwal_kuliah (mata_kuliah_id, hari, jam_mulai, jam_selesai, ruangan) VALUES (?, ?, ?, ?, ?)",
                    'iisss', [$form['mata_kuliah_id'], $form['hari'], $mulai, $selesai, $ruangan]);
                flash('sukses', 'Jadwal kuliah berhasil ditambahkan.');
            }
            redirect('jadwal.php');
        }
    } elseif ($aksi === 'hapus') {
        db_exec($conn, "DELETE FROM jadwal_kuliah WHERE id = ?", 'i', [(int)($_POST['id'] ?? 0)]);
        flash('sukses', 'Jadwal kuliah dihapus.');
        redirect('jadwal.php');
    }
}

if (!$errors && isset($_GET['edit'])) {
    $row = db_one($conn, "SELECT * FROM jadwal_kuliah WHERE id = ?", 'i', [(int)$_GET['edit']]);
    if ($row) {
        $form = $row;
        $form['jam_mulai'] = jam($row['jam_mulai']);
        $form['jam_selesai'] = jam($row['jam_selesai']);
    } else {
        flash('error', 'Data jadwal tidak ditemukan.');
        redirect('jadwal.php');
    }
}
$tampilForm = $errors || isset($_GET['tambah']) || isset($_GET['edit']);

$daftar = db_all($conn, "SELECT j.*, m.nama_mata_kuliah, m.warna, m.dosen
    FROM jadwal_kuliah j JOIN mata_kuliah m ON m.id = j.mata_kuliah_id
    ORDER BY j.hari, j.jam_mulai");

$judul_halaman = 'Jadwal Kuliah';
$menu_aktif = 'jadwal';
include __DIR__ . '/includes/header.php';
?>

<?php if ($errors): ?>
    <div class="alert alert-error">Data belum bisa disimpan:<ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if (!$daftarMk): ?>
    <div class="alert alert-info">Jadwal kuliah memerlukan data mata kuliah. Tambahkan dulu di menu <a href="mata_kuliah.php?tambah=1">Mata Kuliah</a>.</div>
<?php endif; ?>

<?php if ($tampilForm && $daftarMk): ?>
<div class="card">
    <div class="card-header"><h2><?= (int)$form['id'] > 0 ? 'Ubah Jadwal Kuliah' : 'Tambah Jadwal Kuliah' ?></h2></div>
    <form method="post" action="jadwal.php" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
        <div class="field">
            <label for="mk">Mata Kuliah *</label>
            <select id="mk" name="mata_kuliah_id" required>
                <option value="">— Pilih —</option>
                <?php foreach ($daftarMk as $m): ?>
                    <option value="<?= (int)$m['id'] ?>" <?= (int)$form['mata_kuliah_id'] === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['nama_mata_kuliah']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="hari">Hari *</label>
            <select id="hari" name="hari"><?= opsi_html(NAMA_HARI, $form['hari']) ?></select>
        </div>
        <div class="field">
            <label for="jm">Jam Mulai *</label>
            <input type="time" id="jm" name="jam_mulai" required value="<?= e($form['jam_mulai']) ?>">
        </div>
        <div class="field">
            <label for="js">Jam Selesai *</label>
            <input type="time" id="js" name="jam_selesai" required value="<?= e($form['jam_selesai']) ?>">
        </div>
        <div class="field penuh">
            <label for="ruangan">Ruangan</label>
            <input type="text" id="ruangan" name="ruangan" maxlength="100" value="<?= e($form['ruangan']) ?>">
        </div>
        <div class="form-aksi">
            <button type="submit" class="btn">Simpan</button>
            <a href="jadwal.php" class="btn btn-sekunder">Batal</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Jadwal Mingguan (<?= count($daftar) ?>)</h2>
        <?php if ($daftarMk): ?><div class="aksi"><a href="jadwal.php?tambah=1" class="btn">+ Tambah Jadwal</a></div><?php endif; ?>
    </div>
    <?php if (!$daftar): ?>
        <div class="kosong">Belum ada jadwal kuliah.</div>
    <?php else: ?>
    <div class="tabel-wrap">
    <table class="data">
        <thead><tr><th>Hari</th><th>Jam</th><th>Mata Kuliah</th><th>Ruangan</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($daftar as $j): ?>
            <tr>
                <td><?= e(NAMA_HARI[(int)$j['hari']]) ?></td>
                <td><?= e(jam($j['jam_mulai'])) ?>–<?= e(jam($j['jam_selesai'])) ?></td>
                <td><span class="dot" style="background:<?= e($j['warna']) ?>"></span><?= e($j['nama_mata_kuliah']) ?>
                    <?php if ($j['dosen']): ?><div class="muted kecil"><?= e($j['dosen']) ?></div><?php endif; ?></td>
                <td><?= e($j['ruangan'] ?: '-') ?></td>
                <td class="aksi">
                    <a class="btn btn-sekunder btn-kecil" href="jadwal.php?edit=<?= (int)$j['id'] ?>">Ubah</a>
                    <form method="post" action="jadwal.php" class="inline" data-confirm="Hapus jadwal kuliah ini?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="id" value="<?= (int)$j['id'] ?>">
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
