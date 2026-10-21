<?php
require_once __DIR__ . '/includes/init.php';

$errors = [];
$form = ['id' => 0, 'nama_mata_kuliah' => '', 'dosen' => '', 'warna' => '#243B64'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'simpan') {
        $form['id'] = (int)($_POST['id'] ?? 0);
        $form['nama_mata_kuliah'] = trim($_POST['nama_mata_kuliah'] ?? '');
        $form['dosen'] = trim($_POST['dosen'] ?? '');
        $form['warna'] = trim($_POST['warna'] ?? '#243B64');

        if ($form['nama_mata_kuliah'] === '') {
            $errors[] = 'Nama mata kuliah wajib diisi.';
        } elseif (mb_strlen($form['nama_mata_kuliah']) > 100) {
            $errors[] = 'Nama mata kuliah maksimal 100 karakter.';
        }
        if (mb_strlen($form['dosen']) > 100) {
            $errors[] = 'Nama dosen maksimal 100 karakter.';
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $form['warna'])) {
            $errors[] = 'Format warna tidak valid.';
        }

        if (!$errors) {
            $dosen = $form['dosen'] === '' ? null : $form['dosen'];
            if ($form['id'] > 0) {
                db_exec($conn, "UPDATE mata_kuliah SET nama_mata_kuliah = ?, dosen = ?, warna = ? WHERE id = ?",
                    'sssi', [$form['nama_mata_kuliah'], $dosen, $form['warna'], $form['id']]);
                flash('sukses', 'Mata kuliah berhasil diperbarui.');
            } else {
                db_exec($conn, "INSERT INTO mata_kuliah (nama_mata_kuliah, dosen, warna) VALUES (?, ?, ?)",
                    'sss', [$form['nama_mata_kuliah'], $dosen, $form['warna']]);
                flash('sukses', 'Mata kuliah berhasil ditambahkan.');
            }
            redirect('mata_kuliah.php');
        }
    } elseif ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        db_exec($conn, "DELETE FROM mata_kuliah WHERE id = ?", 'i', [$id]);
        flash('sukses', 'Mata kuliah dihapus (jadwal kuliahnya ikut terhapus; tugas terkait tetap ada tanpa mata kuliah).');
        redirect('mata_kuliah.php');
    }
}

if (!$errors && isset($_GET['edit'])) {
    $row = db_one($conn, "SELECT * FROM mata_kuliah WHERE id = ?", 'i', [(int)$_GET['edit']]);
    if ($row) {
        $form = $row;
    } else {
        flash('error', 'Data mata kuliah tidak ditemukan.');
        redirect('mata_kuliah.php');
    }
}
$tampilForm = $errors || isset($_GET['tambah']) || isset($_GET['edit']);

$daftar = db_all($conn, "SELECT m.*,
        (SELECT COUNT(*) FROM jadwal_kuliah j WHERE j.mata_kuliah_id = m.id) AS jml_jadwal,
        (SELECT COUNT(*) FROM tugas t WHERE t.mata_kuliah_id = m.id) AS jml_tugas
    FROM mata_kuliah m ORDER BY m.nama_mata_kuliah");

$judul_halaman = 'Mata Kuliah';
$menu_aktif = 'mata_kuliah';
include __DIR__ . '/includes/header.php';
?>

<?php if ($errors): ?>
    <div class="alert alert-error">Data belum bisa disimpan:<ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if ($tampilForm): ?>
<div class="card">
    <div class="card-header"><h2><?= (int)$form['id'] > 0 ? 'Ubah Mata Kuliah' : 'Tambah Mata Kuliah' ?></h2></div>
    <form method="post" action="mata_kuliah.php" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
        <div class="field penuh">
            <label for="nama">Nama Mata Kuliah *</label>
            <input type="text" id="nama" name="nama_mata_kuliah" maxlength="100" required value="<?= e($form['nama_mata_kuliah']) ?>">
        </div>
        <div class="field">
            <label for="dosen">Dosen</label>
            <input type="text" id="dosen" name="dosen" maxlength="100" value="<?= e($form['dosen']) ?>">
        </div>
        <div class="field">
            <label for="warna">Warna Penanda</label>
            <input type="color" id="warna" name="warna" value="<?= e($form['warna']) ?>">
        </div>
        <div class="form-aksi">
            <button type="submit" class="btn">Simpan</button>
            <a href="mata_kuliah.php" class="btn btn-sekunder">Batal</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Daftar Mata Kuliah (<?= count($daftar) ?>)</h2>
        <div class="aksi"><a href="mata_kuliah.php?tambah=1" class="btn">+ Tambah Mata Kuliah</a></div>
    </div>
    <?php if (!$daftar): ?>
        <div class="kosong">Belum ada mata kuliah. Klik “Tambah Mata Kuliah” untuk memulai.</div>
    <?php else: ?>
    <div class="tabel-wrap">
    <table class="data">
        <thead><tr><th>Mata Kuliah</th><th>Dosen</th><th>Jadwal</th><th>Tugas</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($daftar as $m): ?>
            <tr>
                <td><span class="dot" style="background:<?= e($m['warna']) ?>"></span><?= e($m['nama_mata_kuliah']) ?></td>
                <td><?= e($m['dosen'] ?: '-') ?></td>
                <td><?= (int)$m['jml_jadwal'] ?> sesi/minggu</td>
                <td><?= (int)$m['jml_tugas'] ?></td>
                <td class="aksi">
                    <a class="btn btn-sekunder btn-kecil" href="mata_kuliah.php?edit=<?= (int)$m['id'] ?>">Ubah</a>
                    <form method="post" action="mata_kuliah.php" class="inline"
                          data-confirm="Hapus mata kuliah ini? Jadwal kuliahnya ikut terhapus, tugas terkait tetap ada tanpa mata kuliah.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
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
