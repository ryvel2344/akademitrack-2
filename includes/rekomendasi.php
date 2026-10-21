<?php
// includes/rekomendasi.php
// Mesin rekomendasi waktu belajar berbasis ATURAN (tanpa AI eksternal).
//
// ALUR LOGIKA:
//  1. Hitung "slot kosong" tiap hari (14 hari ke depan):
//       jendela belajar 08:00-21:00  dikurangi  jadwal kuliah & sesi 'Terjadwal'
//       (+ jeda 15 menit di sekitar kegiatan). Hari ini dimulai dari jam sekarang.
//  2. Untuk tiap tugas yang belum selesai, hitung kebutuhan waktu:
//       sisa = estimasi_menit - menit sesi (Terjadwal/Selesai) yang sudah terkait tugas itu.
//       Jika estimasi kosong, dipakai 60 menit.
//  3. Hitung skor prioritas:
//       skor = bobot prioritas (Tinggi 30, Sedang 20, Rendah 10) + skor urgensi
//       skor urgensi = 40 jika terlambat, selain itu max(0, 30 - 3 x sisa hari)
//  4. Urutkan skor dari tertinggi. Tempatkan blok belajar pada slot kosong paling awal,
//       maksimal 120 menit per blok, minimal 30 menit, dan awalnya maksimal 180 menit/hari
//       per tugas (agar beban tersebar). Jika masih kurang, batas harian dilonggarkan.
//  5. Batas akhir penempatan = tanggal deadline (tugas terlambat: 3 hari ke depan).
//       Tugas dengan deadline lebih dari 14 hari tidak diproses.

const JAM_BELAJAR_MULAI = '08:00';
const JAM_BELAJAR_SELESAI = '21:00';
const BUFFER_MENIT = 15;
const BLOK_MIN = 30;
const BLOK_MAX = 120;
const MAKS_PER_HARI = 180;
const HORIZON_HARI = 14;
const ESTIMASI_DEFAULT = 60;

function ke_menit($hhmm)
{
    $bagian = explode(':', (string)$hhmm);
    return (int)$bagian[0] * 60 + (int)($bagian[1] ?? 0);
}

function dari_menit($menit)
{
    return sprintf('%02d:%02d', intdiv($menit, 60), $menit % 60);
}

function bobot_prioritas($prioritas)
{
    return ['Tinggi' => 30, 'Sedang' => 20, 'Rendah' => 10][$prioritas] ?? 20;
}

function skor_urgensi($sisaHari)
{
    return $sisaHari < 0 ? 40 : max(0, 30 - 3 * $sisaHari);
}

// Mengembalikan daftar interval kosong [mulai, selesai] (dalam menit) pada satu tanggal.
function slot_kosong_hari($conn, $tanggal, $menitSekarang = null)
{
    $awal = ke_menit(JAM_BELAJAR_MULAI);
    $akhir = ke_menit(JAM_BELAJAR_SELESAI);
    if ($menitSekarang !== null) {
        // hari ini: mulai dari kelipatan 30 menit berikutnya
        $awal = max($awal, (int)(ceil($menitSekarang / 30) * 30));
    }

    $sibuk = [];
    $hari = (int)date('N', strtotime($tanggal));
    $kuliah = db_all($conn, "SELECT jam_mulai, jam_selesai FROM jadwal_kuliah WHERE hari = ?", 'i', [$hari]);
    foreach ($kuliah as $k) {
        $sibuk[] = [ke_menit($k['jam_mulai']) - BUFFER_MENIT, ke_menit($k['jam_selesai']) + BUFFER_MENIT];
    }
    $sesi = db_all($conn, "SELECT waktu_mulai, waktu_selesai FROM sesi_belajar
                           WHERE status = 'Terjadwal' AND DATE(waktu_mulai) = ?", 's', [$tanggal]);
    foreach ($sesi as $s) {
        $sibuk[] = [ke_menit(substr($s['waktu_mulai'], 11, 5)) - BUFFER_MENIT, ke_menit(substr($s['waktu_selesai'], 11, 5)) + BUFFER_MENIT];
    }
    usort($sibuk, function ($a, $b) {
        return $a[0] <=> $b[0];
    });

    $kosong = [];
    $kursor = $awal;
    foreach ($sibuk as $b) {
        if ($b[0] > $kursor) {
            $ujung = min($b[0], $akhir);
            if ($ujung - $kursor >= BLOK_MIN) {
                $kosong[] = [$kursor, $ujung];
            }
        }
        $kursor = max($kursor, $b[1]);
    }
    if ($akhir - $kursor >= BLOK_MIN) {
        $kosong[] = [$kursor, $akhir];
    }
    return $kosong;
}

function total_menit_slot($daftarSlot)
{
    $total = 0;
    foreach ($daftarSlot as $s) {
        $total += max(0, $s[1] - $s[0]);
    }
    return $total;
}

// Fungsi utama. Mengembalikan ['daftar' => [...], 'dilewati' => [...], 'ketersediaan' => [...]]
function buat_rekomendasi($conn)
{
    $hariIni = date('Y-m-d');
    $menitSekarang = (int)date('G') * 60 + (int)date('i');

    // Langkah 1: slot kosong per hari
    $slots = [];
    $ketersediaan = [];
    for ($i = 0; $i < HORIZON_HARI; $i++) {
        $tgl = date('Y-m-d', strtotime($hariIni . ' +' . $i . ' day'));
        $slots[$tgl] = slot_kosong_hari($conn, $tgl, $i === 0 ? $menitSekarang : null);
        $ketersediaan[$tgl] = ['kosong' => total_menit_slot($slots[$tgl]), 'dipakai' => 0];
    }

    // Langkah 2: kebutuhan waktu tiap tugas
    $tugas = db_all($conn, "SELECT t.*, m.nama_mata_kuliah FROM tugas t
                            LEFT JOIN mata_kuliah m ON m.id = t.mata_kuliah_id
                            WHERE t.status <> 'Selesai' ORDER BY t.deadline");
    $sudah = [];
    $baris = db_all($conn, "SELECT tugas_id, SUM(TIMESTAMPDIFF(MINUTE, waktu_mulai, waktu_selesai)) AS menit
                            FROM sesi_belajar
                            WHERE tugas_id IS NOT NULL AND status IN ('Terjadwal', 'Selesai')
                            GROUP BY tugas_id");
    foreach ($baris as $b) {
        $sudah[(int)$b['tugas_id']] = (int)$b['menit'];
    }

    $daftar = [];
    $dilewati = [];
    foreach ($tugas as $t) {
        $sisaHari = selisih_hari($hariIni, $t['deadline']);
        if ($sisaHari > HORIZON_HARI - 1) {
            $dilewati[] = ['tugas' => $t, 'sisa_hari' => $sisaHari];
            continue;
        }
        $estimasi = $t['estimasi_menit'] ? (int)$t['estimasi_menit'] : ESTIMASI_DEFAULT;
        $terjadwal = $sudah[(int)$t['id']] ?? 0;
        $bobot = bobot_prioritas($t['prioritas']);
        $urgensi = skor_urgensi($sisaHari);
        $daftar[] = [
            'tugas' => $t,
            'sisa_hari' => $sisaHari,
            'estimasi' => $estimasi,
            'estimasi_default' => !$t['estimasi_menit'],
            'terjadwal' => $terjadwal,
            'sisa' => max(0, $estimasi - $terjadwal),
            'bobot' => $bobot,
            'urgensi' => $urgensi,
            'skor' => $bobot + $urgensi,
            'idx_akhir' => $sisaHari < 0 ? 2 : $sisaHari,
            'usulan' => [],
            'kurang' => 0,
        ];
    }

    // Langkah 3: urutkan skor tertinggi -> terendah (seri: deadline lebih dekat dulu)
    usort($daftar, function ($a, $b) {
        $cmp = $b['skor'] <=> $a['skor'];
        return $cmp !== 0 ? $cmp : strcmp($a['tugas']['deadline'], $b['tugas']['deadline']);
    });

    // Langkah 4: tempatkan blok belajar pada slot kosong
    foreach ($daftar as $k => $item) {
        $sisa = $item['sisa'];
        $usulan = [];
        $dipakai = [];
        if ($sisa > 0) {
            foreach ([MAKS_PER_HARI, PHP_INT_MAX] as $batas) {
                for ($i = 0; $i <= $item['idx_akhir'] && $sisa > 0; $i++) {
                    $tgl = date('Y-m-d', strtotime($hariIni . ' +' . $i . ' day'));
                    foreach (array_keys($slots[$tgl]) as $idx) {
                        while ($sisa > 0) {
                            $iv = $slots[$tgl][$idx];
                            $boleh = $batas - ($dipakai[$tgl] ?? 0);
                            $panjang = min(BLOK_MAX, $sisa, $iv[1] - $iv[0], $boleh);
                            if ($panjang < min(BLOK_MIN, $sisa)) {
                                break;
                            }
                            $usulan[] = [
                                'tanggal' => $tgl,
                                'mulai' => dari_menit($iv[0]),
                                'selesai' => dari_menit($iv[0] + $panjang),
                                'menit' => $panjang,
                            ];
                            $dipakai[$tgl] = ($dipakai[$tgl] ?? 0) + $panjang;
                            $ketersediaan[$tgl]['dipakai'] += $panjang;
                            $sisa -= $panjang;
                            $slots[$tgl][$idx][0] = $iv[0] + $panjang + BUFFER_MENIT;
                        }
                    }
                }
            }
        }
        usort($usulan, function ($a, $b) {
            return strcmp($a['tanggal'] . $a['mulai'], $b['tanggal'] . $b['mulai']);
        });
        $daftar[$k]['usulan'] = $usulan;
        $daftar[$k]['kurang'] = $sisa;
    }

    return ['daftar' => $daftar, 'dilewati' => $dilewati, 'ketersediaan' => $ketersediaan];
}
