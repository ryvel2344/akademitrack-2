<?php
// includes/functions.php
// Kumpulan fungsi bantu: database, format tanggal, keamanan form, agenda.

date_default_timezone_set('Asia/Jakarta');
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // dipakai hanya untuk pesan notifikasi & token CSRF (bukan login)
}

const NAMA_HARI = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
const NAMA_BULAN = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
const BULAN_SINGKAT = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
    7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
const PRIORITAS = ['Rendah', 'Sedang', 'Tinggi'];
const STATUS_TUGAS = ['Belum Mulai', 'Dikerjakan', 'Selesai'];
const JENIS_SESI = ['Mengerjakan Tugas', 'Review Materi', 'Latihan Skill'];
const STATUS_SESI = ['Terjadwal', 'Selesai', 'Dibatalkan'];
const STATUS_SKILL = ['Berjalan', 'Tercapai', 'Ditunda'];

/* ---------------------------------------------------------------
 * Umum
 * ------------------------------------------------------------- */
function e($teks)
{
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function flash($tipe, $pesan)
{
    $_SESSION['flash'][] = ['tipe' => $tipe, 'pesan' => $pesan];
}

function ambil_flash()
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check()
{
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals(csrf_token(), (string)$token)) {
        http_response_code(400);
        die('Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
    }
}

/* ---------------------------------------------------------------
 * Database (prepared statement, agar aman dari SQL injection)
 * $types: string tipe parameter, mis. 'sis' (s=string, i=integer)
 * ------------------------------------------------------------- */
function db_query($conn, $sql, $types = '', $params = [])
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        die('Query gagal disiapkan: ' . e(mysqli_error($conn)));
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        die('Query gagal dijalankan: ' . e($err));
    }
    return $stmt;
}

function db_all($conn, $sql, $types = '', $params = [])
{
    $stmt = db_query($conn, $sql, $types, $params);
    $res = mysqli_stmt_get_result($stmt);
    $rows = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);
    return $rows;
}

function db_one($conn, $sql, $types = '', $params = [])
{
    $rows = db_all($conn, $sql, $types, $params);
    return $rows[0] ?? null;
}

function db_exec($conn, $sql, $types = '', $params = [])
{
    $stmt = db_query($conn, $sql, $types, $params);
    $n = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $n;
}

/* ---------------------------------------------------------------
 * Tanggal & waktu
 * ------------------------------------------------------------- */
function valid_tanggal($s)
{
    return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)
        && checkdate((int)substr($s, 5, 2), (int)substr($s, 8, 2), (int)substr($s, 0, 4));
}

function valid_jam($s)
{
    return is_string($s) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $s);
}

function tanggal_indo($tgl)
{
    $t = strtotime($tgl);
    return date('j', $t) . ' ' . BULAN_SINGKAT[(int)date('n', $t)] . ' ' . date('Y', $t);
}

function tanggal_panjang($tgl)
{
    $t = strtotime($tgl);
    return NAMA_HARI[(int)date('N', $t)] . ', ' . date('j', $t) . ' ' . NAMA_BULAN[(int)date('n', $t)] . ' ' . date('Y', $t);
}

function nama_hari_tanggal($tgl)
{
    return NAMA_HARI[(int)date('N', strtotime($tgl))];
}

function jam($waktu)
{
    return $waktu === null ? '' : substr((string)$waktu, 0, 5);
}

// Selisih hari bertanda: positif jika $b setelah $a.
function selisih_hari($a, $b)
{
    return (int)(new DateTime($a))->diff(new DateTime($b))->format('%r%a');
}

function fmt_durasi($menit)
{
    if ($menit === null || $menit === '') {
        return '-';
    }
    $menit = (int)$menit;
    if ($menit < 60) {
        return $menit . ' mnt';
    }
    $j = intdiv($menit, 60);
    $m = $menit % 60;
    return $m === 0 ? $j . ' jam' : $j . ' jam ' . $m . ' mnt';
}

function persen($bagian, $total)
{
    return $total > 0 ? (int)round($bagian * 100 / $total) : 0;
}

/* ---------------------------------------------------------------
 * Tampilan kecil (badge, opsi select)
 * ------------------------------------------------------------- */
function badge($teks, $kelas = 'badge-muted')
{
    return '<span class="badge ' . e($kelas) . '">' . e($teks) . '</span>';
}

function badge_status($status)
{
    $peta = [
        'Belum Mulai' => 'badge-muted', 'Dikerjakan' => 'badge-info', 'Selesai' => 'badge-ok',
        'Terjadwal' => 'badge-info', 'Dibatalkan' => 'badge-muted',
        'Berjalan' => 'badge-info', 'Tercapai' => 'badge-ok', 'Ditunda' => 'badge-warn',
        'Tinggi' => 'badge-danger', 'Sedang' => 'badge-warn', 'Rendah' => 'badge-muted',
    ];
    return badge($status, $peta[$status] ?? 'badge-muted');
}

// Mengembalikan [teks, kelas badge, jumlah hari tersisa]
function info_deadline($deadline, $status = '')
{
    $hari = selisih_hari(date('Y-m-d'), $deadline);
    if ($status === 'Selesai') {
        return ['Selesai', 'badge-ok', $hari];
    }
    if ($hari < 0) {
        return ['Terlambat ' . abs($hari) . ' hari', 'badge-danger', $hari];
    }
    if ($hari === 0) {
        return ['Hari ini', 'badge-danger', $hari];
    }
    if ($hari === 1) {
        return ['Besok', 'badge-warn', $hari];
    }
    if ($hari <= 3) {
        return ['H-' . $hari, 'badge-warn', $hari];
    }
    return ['H-' . $hari, 'badge-muted', $hari];
}

function opsi_html($opsi, $terpilih)
{
    $out = '';
    foreach ($opsi as $nilai => $label) {
        $sel = ((string)$nilai === (string)$terpilih) ? ' selected' : '';
        $out .= '<option value="' . e($nilai) . '"' . $sel . '>' . e($label) . '</option>';
    }
    return $out;
}

function opsi_list($daftar, $terpilih)
{
    return opsi_html(array_combine($daftar, $daftar), $terpilih);
}

/* ---------------------------------------------------------------
 * Agenda gabungan (jadwal kuliah + deadline tugas + sesi belajar)
 * Dipakai oleh Kalender dan Dashboard.
 * Setiap item: jenis, tanggal, mulai, selesai, judul, sub, warna, status, url
 * ------------------------------------------------------------- */
function ambil_agenda($conn, $dari, $sampai)
{
    $events = [];

    // 1) Jadwal kuliah mingguan -> diulang pada setiap hari yang sesuai
    $jadwal = db_all($conn, "SELECT j.*, m.nama_mata_kuliah, m.dosen, m.warna
                             FROM jadwal_kuliah j
                             JOIN mata_kuliah m ON m.id = j.mata_kuliah_id");
    $d = new DateTime($dari);
    $akhir = new DateTime($sampai);
    for (; $d <= $akhir; $d->modify('+1 day')) {
        $hari = (int)$d->format('N');
        foreach ($jadwal as $j) {
            if ((int)$j['hari'] === $hari) {
                $sub = trim(($j['ruangan'] ? 'Ruang ' . $j['ruangan'] : '') . ($j['dosen'] ? ' · ' . $j['dosen'] : ''), ' ·');
                $events[] = [
                    'jenis' => 'kuliah', 'tanggal' => $d->format('Y-m-d'),
                    'mulai' => jam($j['jam_mulai']), 'selesai' => jam($j['jam_selesai']),
                    'judul' => $j['nama_mata_kuliah'], 'sub' => $sub, 'warna' => $j['warna'],
                    'status' => null, 'url' => 'jadwal.php?edit=' . (int)$j['id'],
                ];
            }
        }
    }

    // 2) Deadline tugas
    $tugas = db_all($conn, "SELECT t.*, m.nama_mata_kuliah FROM tugas t
                            LEFT JOIN mata_kuliah m ON m.id = t.mata_kuliah_id
                            WHERE t.deadline BETWEEN ? AND ?", 'ss', [$dari, $sampai]);
    foreach ($tugas as $t) {
        $events[] = [
            'jenis' => 'tugas', 'tanggal' => $t['deadline'], 'mulai' => null, 'selesai' => null,
            'judul' => 'Deadline: ' . $t['judul'],
            'sub' => trim(($t['nama_mata_kuliah'] ?? '') . ' · Prioritas ' . $t['prioritas'], ' ·'),
            'warna' => null, 'status' => $t['status'], 'url' => 'tugas.php?edit=' . (int)$t['id'],
        ];
    }

    // 3) Sesi belajar (yang dibatalkan tidak ditampilkan)
    $sesi = db_all($conn, "SELECT * FROM sesi_belajar
                           WHERE DATE(waktu_mulai) BETWEEN ? AND ? AND status <> 'Dibatalkan'", 'ss', [$dari, $sampai]);
    foreach ($sesi as $s) {
        $events[] = [
            'jenis' => 'sesi', 'tanggal' => substr($s['waktu_mulai'], 0, 10),
            'mulai' => substr($s['waktu_mulai'], 11, 5), 'selesai' => substr($s['waktu_selesai'], 11, 5),
            'judul' => $s['judul'], 'sub' => $s['jenis'], 'warna' => null,
            'status' => $s['status'], 'url' => 'sesi.php?edit=' . (int)$s['id'],
        ];
    }

    usort($events, function ($a, $b) {
        return strcmp($a['tanggal'] . ' ' . ($a['mulai'] ?? '99:99'), $b['tanggal'] . ' ' . ($b['mulai'] ?? '99:99'));
    });
    return $events;
}

function label_jenis_agenda($jenis)
{
    return ['kuliah' => 'Kuliah', 'tugas' => 'Deadline', 'sesi' => 'Belajar'][$jenis] ?? $jenis;
}

// Baris agenda untuk daftar (dashboard / kalender)
function item_agenda_html($ev, $tampilTanggal = false)
{
    $waktu = $ev['mulai'] !== null ? $ev['mulai'] . '–' . $ev['selesai'] : 'Seharian';
    if ($tampilTanggal) {
        $waktu = tanggal_indo($ev['tanggal']) . ' · ' . $waktu;
    }
    $gaya = ($ev['jenis'] === 'kuliah' && $ev['warna']) ? ' style="border-left-color:' . e($ev['warna']) . '"' : '';
    $html = '<a class="agenda-item tipe-' . e($ev['jenis']) . '"' . $gaya . ' href="' . e($ev['url']) . '">';
    $html .= '<span class="agenda-waktu">' . e($waktu) . '</span>';
    $html .= '<span class="agenda-judul">' . e($ev['judul']) . '</span>';
    $html .= '<span class="agenda-sub">' . badge(label_jenis_agenda($ev['jenis']), 'badge-tipe-' . $ev['jenis']);
    if ($ev['sub']) {
        $html .= ' ' . e($ev['sub']);
    }
    if ($ev['status']) {
        $html .= ' ' . badge_status($ev['status']);
    }
    $html .= '</span></a>';
    return $html;
}

/* ---------------------------------------------------------------
 * Deteksi bentrok untuk sesi belajar baru
 * $mulai / $selesai berformat 'Y-m-d H:i:s'
 * ------------------------------------------------------------- */
function cari_bentrok($conn, $mulai, $selesai, $kecualiSesiId = 0)
{
    $hasil = [];
    $hari = (int)date('N', strtotime(substr($mulai, 0, 10)));
    $jm = substr($mulai, 11, 8);
    $js = substr($selesai, 11, 8);

    $kuliah = db_all($conn, "SELECT j.jam_mulai, j.jam_selesai, m.nama_mata_kuliah
                             FROM jadwal_kuliah j JOIN mata_kuliah m ON m.id = j.mata_kuliah_id
                             WHERE j.hari = ? AND j.jam_mulai < ? AND j.jam_selesai > ?", 'iss', [$hari, $js, $jm]);
    foreach ($kuliah as $k) {
        $hasil[] = 'kuliah ' . $k['nama_mata_kuliah'] . ' (' . jam($k['jam_mulai']) . '–' . jam($k['jam_selesai']) . ')';
    }

    $sesi = db_all($conn, "SELECT judul, waktu_mulai, waktu_selesai FROM sesi_belajar
                           WHERE status = 'Terjadwal' AND id <> ? AND waktu_mulai < ? AND waktu_selesai > ?",
        'iss', [(int)$kecualiSesiId, $selesai, $mulai]);
    foreach ($sesi as $s) {
        $hasil[] = 'sesi "' . $s['judul'] . '" (' . substr($s['waktu_mulai'], 11, 5) . '–' . substr($s['waktu_selesai'], 11, 5) . ')';
    }
    return $hasil;
}
