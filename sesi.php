<?php
require_once __DIR__ . '/includes/init.php';

$errors = [];
$mulaiDefault = date('Y-m-d\TH:00', strtotime('+1 hour'));
$selesaiDefault = date('Y-m-d\TH:00', strtotime('+2 hours'));
$form = ['id' => 0, 'tugas_id' => (int)($_GET['tugas_id'] ?? 0), 'target_skill_id' => (int)($_GET['skill_id'] ?? 0),
    'judul' => '', 'jenis' => 'Mengerjakan Tugas', 'waktu_mulai' => $mulaiDefault, 'waktu_selesai' => $selesaiDefault,
    'status' => 'Terjadwal', 'catatan' => ''];
if ($form['target_skill_id'] > 0 && !$form['tugas_id']) {
    $form['jenis'] = 'Latihan Skill';
}

// Ubah "2026-10-21T14:00" menjadi "2026-10-21 14:00:00" (null jika format salah)
function waktu_dari_input($v)
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $v)) {
        return null;
    }
    $tgl = substr($v, 0, 10);
    $jam = substr($v, 11, 5);
    return (valid_tanggal($tgl) && valid_jam($jam)) ? $tgl . ' ' . $jam . ':00' : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'simpan') {
        $form['id'] = (int)($_POST['id'] ?? 0);
        $form['judul'] = trim($_POST['judul'] ?? '');
        $form['jenis'] = $_POST['jenis'] ?? '';
        $form['tugas_id'] = (int)($_POST['tugas_id'] ?? 0);
        $form['target_skill_id'] = (int)($_POST['target_skill_id'] ?? 0);
        $form['waktu_mulai'] = trim($_POST['waktu_mulai'] ?? '');
        $form['waktu_selesai'] = trim($_POST['waktu_selesai'] ?? '');
        $form['status'] = $_POST['status'] ?? '';
        $form['catatan'] = trim($_POST['catatan'] ?? '');

        if ($form['judul'] === '') {
            $errors[] = 'Judul sesi wajib diisi.';
        } elseif (mb_strlen($form['judul']) > 150) {
            $errors[] = 'Judul maksimal 150 karakter.';
        }
        if (!in_array($form['jenis'], JENIS_SESI, true)) {
            $errors[] = 'Jenis sesi tidak valid.';
        }
        if (!in_array($form['status'], STATUS_SESI, true)) {
            $errors[] = 'Status tidak valid.';
        }

        $tugasId = null;
        $skillId = null;
        if ($form['jenis'] === 'Mengerjakan Tugas') {
            if (db_one($conn, "SELECT id FROM tugas WHERE id = ?", 'i', [$form['tugas_id']])) {
                $tugasId = $form['tugas_id'];
            } else {
                $errors[] = 'Pilih tugas yang akan dikerjakan.';
            }
        } elseif ($form['jenis'] === 'Latihan Skill') {
            if (db_one($conn, "SELECT id FROM target_skill WHERE id = ?", 'i', [$form['target_skill_id']])) {
                $skillId = $form['target_skill_id'];
            } else {
                $errors[] = 'Pilih target skill yang akan dilatih.';
            }
        }

        $mulai = waktu_dari_input($form['waktu_mulai']);
        $selesai = waktu_dari_input($form['waktu_selesai']);
        if ($mulai === null || $selesai === null) {
            $errors[] = 'Waktu mulai dan waktu selesai wajib diisi.';
        } elseif ($selesai <= $mulai) {
            $errors[] = 'Waktu selesai harus setelah waktu mulai.';
        } elseif (substr($mulai, 0, 10) !== substr($selesai, 0, 10)) {
            $errors[] = 'Sesi belajar harus berada dalam satu hari yang sama.';
        }

        if (!$errors) {
            $catatan = $form['catatan'] === '' ? null : $form['catatan'];
            if ($form['id'] > 0) {
                db_exec($conn, "UPDATE sesi_belajar SET tugas_id = ?, target_skill_id = ?, judul = ?, jenis = ?, waktu_mulai = ?, waktu_selesai = ?, status = ?, catatan = ? WHERE id = ?",
                    'iissssssi', [$tugasId, $skillId, $form['judul'], $form['jenis'], $mulai, $selesai, $form['status'], $catatan, $form['id']]);
                $pesan = 'Sesi belajar berhasil diperbarui.';
                $idSesi = $form['id'];
            } else {
                db_exec($conn, "INSERT INTO sesi_belajar (tugas_id, target_skill_id, judul, jenis, waktu_mulai, waktu_selesai, status, catatan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    'iissssss', [$tugasId, $skillId, $form['judul'], $form['jenis'], $mulai, $selesai, $form['status'], $catatan]);
                $pesan = 'Sesi belajar berhasil ditambahkan.';
                $idSesi = 0;
            }
            // Peringatan bentrok (sesi tetap tersimpan)
            if ($form['status'] === 'Terjadwal') {
                $bentrok = cari_bentrok($conn, $mulai, $selesai, $idSesi);
                if ($bentrok) {
                    flash('warning', 'Tersimpan, tetapi waktunya bentrok dengan: ' . implode('; ', $bentrok) . '.');
                }
            }
            flash('sukses', $pesan);
            redirect('sesi.php');
        }
    } elseif ($aksi === 'status') {
        $status = $_POST['status'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        if (in_array($status, STATUS_SESI, true)) {
            db_exec($conn, "UPDATE sesi_belajar SET status = ? WHERE id = ?", 'si', [$status, $id]);
            // Aturan: sesi mengerjakan tugas yang selesai -> tugas "Belum Mulai" otomatis menjadi "Dikerjakan"
            if ($status === 'Selesai') {
                $s = db_one($conn, "SELECT tugas_id FROM sesi_belajar WHERE id = ?", 'i', [$id]);
                if ($s && $s['tugas_id']) {
                    db_exec($conn, "UPDATE tugas SET status = 'Dikerjakan' WHERE id = ? AND status = 'Belum Mulai'", 'i', [(int)$s['tugas_id']]);
                }
            }
            flash('sukses', 'Status sesi diubah menjadi "' . $status . '".');
        }
        redirect('sesi.php?status=' . urlencode($_POST['kembali_status'] ?? 'Terjadwal'));
    } elseif ($aksi === 'hapus') {
        db_exec($conn, "DELETE FROM sesi_belajar WHERE id = ?", 'i', [(int)($_POST['id'] ?? 0)]);
        flash('sukses', 'Sesi belajar dihapus.');
        redirect('sesi.php');
    }
}

if (!$errors && isset($_GET['edit'])) {
    $row = db_one($conn, "SELECT * FROM sesi_belajar WHERE id = ?", 'i', [(int)$_GET['edit']]);
    if ($row) {
        $form = $row;
        $form['tugas_id'] = (int)$row['tugas_id'];
        $form['target_skill_id'] = (int)$row['target_skill_id'];
        $form['waktu_mulai'] = str_replace(' ', 'T', substr($row['waktu_mulai'], 0, 16));
        $form['waktu_selesai'] = str_replace(' ', 'T', substr($row['waktu_selesai'], 0, 16));
    } else {
        flash('error', 'Data sesi tidak ditemukan.');
        redirect('sesi.php');
    }
}
$tampilForm = $errors || isset($_GET['tambah']) || isset($_GET['edit']);

$pilihanTugas = db_all($conn, "SELECT id, judul, deadline FROM tugas WHERE status <> 'Selesai' OR id = ? ORDER BY deadline", 'i', [(int)$form['tugas_id']]);
$pilihanSkill = db_all($conn, "SELECT id, nama_skill FROM target_skill WHERE status <> 'Tercapai' OR id = ? ORDER BY nama_skill", 'i', [(int)$form['target_skill_id']]);

// Daftar
$fStatus = $_GET['status'] ?? 'Terjadwal';
if (!in_array($fStatus, STATUS_SESI, true) && $fStatus !== 'semua') {
    $fStatus = 'Terjadwal';
}
$sql = "SELECT s.*, t.judul AS tugas_judul, k.nama_skill, TIMESTAMPDIFF(MINUTE, s.waktu_mulai, s.waktu_selesai) AS menit
        FROM sesi_belajar s
        LEFT JOIN tugas t ON t.id = s.tugas_id
        LEFT JOIN target_skill k ON k.id = s.target_skill_id";
if ($fStatus === 'semua') {
    $daftar = db_all($conn, $sql . " ORDER BY s.waktu_mulai DESC");
} else {
    $daftar = db_all($conn, $sql . " WHERE s.status = ? ORDER BY s.waktu_mulai " . ($fStatus === 'Terjadwal' ? 'ASC' : 'DESC'), 's', [$fStatus]);
}

$judul_halaman = 'Agenda Belajar';
$menu_aktif = 'sesi';
include __DIR__ . '/includes/header.php';
?>

<?php if ($errors): ?>
    <div class="alert alert-error">Data belum bisa disimpan:<ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if ($tampilForm): ?>
<div class="card">
    <div class="card-header"><h2><?= (int)$form['id'] > 0 ? 'Ubah Sesi Belajar' : 'Tambah Sesi Belajar' ?></h2></div>
    <form method="post" action="sesi.php" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
        <div class="field penuh">
            <label for="judul">Judul Sesi *</label>
            <input type="text" id="judul" name="judul" maxlength="150" required value="<?= e($form['judul']) ?>">
        </div>
        <div class="field">
            <label for="jenis">Jenis Sesi *</label>
            <select id="jenis" name="jenis"><?= opsi_list(JENIS_SESI, $form['jenis']) ?></select>
        </div>
        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status"><?= opsi_list(STATUS_SESI, $form['status']) ?></select>
        </div>
        <div class="field penuh" id="baris-tugas">
            <label for="tugas_id">Tugas yang Dikerjakan *</label>
            <select id="tugas_id" name="tugas_id">
                <option value="0">— Pilih tugas —</option>
                <?php foreach ($pilihanTugas as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= (int)$form['tugas_id'] === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['judul']) ?> (deadline <?= e(tanggal_indo($t['deadline'])) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field penuh" id="baris-skill">
            <label for="target_skill_id">Target Skill yang Dilatih *</label>
            <select id="target_skill_id" name="target_skill_id">
                <option value="0">— Pilih skill —</option>
                <?php foreach ($pilihanSkill as $k): ?>
                    <option value="<?= (int)$k['id'] ?>" <?= (int)$form['target_skill_id'] === (int)$k['id'] ? 'selected' : '' ?>><?= e($k['nama_skill']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="waktu_mulai">Waktu Mulai *</label>
            <input type="datetime-local" id="waktu_mulai" name="waktu_mulai" required value="<?= e($form['waktu_mulai']) ?>">
        </div>
        <div class="field">
            <label for="waktu_selesai">Waktu Selesai *</label>
            <input type="datetime-local" id="waktu_selesai" name="waktu_selesai" required value="<?= e($form['waktu_selesai']) ?>">
        </div>
        <div class="field penuh">
            <label for="catatan">Catatan</label>
            <textarea id="catatan" name="catatan"><?= e($form['catatan']) ?></textarea>
        </div>
        <div class="form-aksi">
            <button type="submit" class="btn">Simpan</button>
            <a href="sesi.php" class="btn btn-sekunder">Batal</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card tidak-cetak">
    <form method="get" action="sesi.php" class="filter">
        <div class="field">
            <label for="f_status">Tampilkan</label>
            <select id="f_status" name="status"><?= opsi_html(['Terjadwal' => 'Terjadwal', 'Selesai' => 'Selesai', 'Dibatalkan' => 'Dibatalkan', 'semua' => 'Semua'], $fStatus) ?></select>
        </div>
        <button type="submit" class="btn btn-sekunder">Terapkan</button>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2>Daftar Sesi Belajar (<?= count($daftar) ?>)</h2>
        <div class="aksi"><a href="sesi.php?tambah=1" class="btn">+ Tambah Sesi</a> <a href="rekomendasi.php" class="btn btn-sekunder">Lihat Rekomendasi Waktu</a></div>
    </div>
    <?php if (!$daftar): ?>
        <div class="kosong">Tidak ada sesi belajar pada filter ini.</div>
    <?php else: ?>
    <div class="tabel-wrap">
    <table class="data">
        <thead><tr><th>Waktu</th><th>Sesi</th><th>Jenis</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($daftar as $s): ?>
            <tr>
                <td><?= e(tanggal_indo($s['waktu_mulai'])) ?><br><span class="muted"><?= e(substr($s['waktu_mulai'], 11, 5)) ?>–<?= e(substr($s['waktu_selesai'], 11, 5)) ?> (<?= e(fmt_durasi($s['menit'])) ?>)</span></td>
                <td><strong><?= e($s['judul']) ?></strong>
                    <?php if ($s['tugas_judul']): ?><div class="muted kecil">Tugas: <?= e($s['tugas_judul']) ?></div><?php endif; ?>
                    <?php if ($s['nama_skill']): ?><div class="muted kecil">Skill: <?= e($s['nama_skill']) ?></div><?php endif; ?>
                    <?php if ($s['catatan']): ?><div class="muted kecil"><?= e($s['catatan']) ?></div><?php endif; ?></td>
                <td><?= e($s['jenis']) ?></td>
                <td><?= badge_status($s['status']) ?></td>
                <td class="aksi">
                    <?php if ($s['status'] === 'Terjadwal'): ?>
                        <form method="post" action="sesi.php" class="inline">
                            <?= csrf_field() ?><input type="hidden" name="aksi" value="status"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                            <input type="hidden" name="status" value="Selesai"><input type="hidden" name="kembali_status" value="<?= e($fStatus) ?>">
                            <button type="submit" class="btn btn-kecil">Tandai Selesai</button>
                        </form>
                        <form method="post" action="sesi.php" class="inline" data-confirm="Batalkan sesi ini?">
                            <?= csrf_field() ?><input type="hidden" name="aksi" value="status"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                            <input type="hidden" name="status" value="Dibatalkan"><input type="hidden" name="kembali_status" value="<?= e($fStatus) ?>">
                            <button type="submit" class="btn btn-sekunder btn-kecil">Batalkan</button>
                        </form>
                    <?php endif; ?>
                    <a class="btn btn-sekunder btn-kecil" href="sesi.php?edit=<?= (int)$s['id'] ?>">Ubah</a>
                    <form method="post" action="sesi.php" class="inline" data-confirm="Hapus sesi belajar ini?">
                        <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
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
