<?php
// includes/init.php
// Dipanggil paling awal oleh setiap halaman.
// ob_start() ditambahkan agar header("Location: ...") tetap aman
// meskipun ada karakter kosong sebelum <?php di config/database.php.
ob_start();

require_once __DIR__ . '/../config/database.php';   // menyediakan variabel $conn (mysqli)
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/rekomendasi.php';
