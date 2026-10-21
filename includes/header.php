<?php
// includes/header.php
// Variabel yang diisi halaman sebelum include: $judul_halaman, $menu_aktif
$judul_halaman = $judul_halaman ?? 'AkademiTrack';
$menu_aktif = $menu_aktif ?? '';

$menu = [
    'dashboard'   => ['index.php', 'Dashboard', 'dashboard.png'],
    'kalender'    => ['kalender.php', 'Kalender Akademik', 'kalender.png'],
    'mata_kuliah' => ['mata_kuliah.php', 'Mata Kuliah', 'mata-kuliah.png'],
    'jadwal'      => ['jadwal.php', 'Jadwal Kuliah', 'jadwal-kuliah.png'],
    'tugas'       => ['tugas.php', 'Tugas', 'tugas.png'],
    'sesi'        => ['sesi.php', 'Agenda Belajar', 'agenda-belajar.png'],
    'rekomendasi' => ['rekomendasi.php', 'Rekomendasi Waktu', 'rekomendasi-waktu.png'],
    'skill'       => ['skill.php', 'Target Skill', 'target-skill.png'],
    'laporan'     => ['laporan.php', 'Laporan', 'laporan.png'],
];

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($judul_halaman) ?> · AkademiTrack</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="layout">
    <aside class="sidebar" id="sidebar">
        
<div class="brand">
    <img class="brand-logo" src="assets/images/logo-akademitrack.png" alt="Logo AkademiTrack">
    <span class="brand-name">AkademiTrack</span>
</div>

<nav class="nav">
    <?php foreach ($menu as $kunci => $m): ?>
        <a href="<?= e($m[0]) ?>" class="<?= $kunci === $menu_aktif ? 'aktif' : '' ?>">
            <img class="nav-icon" src="assets/images/<?= e($m[2]) ?>" alt="">
            <span><?= e($m[1]) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

    </aside>
    <div class="main">
        <header class="topbar">
            <button type="button" class="menu-toggle" id="menuToggle" aria-label="Buka menu">&#9776;</button>
            <h1><?= e($judul_halaman) ?></h1>
            <span class="topbar-tanggal"><?= e(tanggal_panjang(date('Y-m-d'))) ?></span>
        </header>
        <main class="content">
            <?php foreach (ambil_flash() as $f): ?>
                <div class="alert alert-<?= e($f['tipe']) ?>"><?= e($f['pesan']) ?></div>
            <?php endforeach; ?>
