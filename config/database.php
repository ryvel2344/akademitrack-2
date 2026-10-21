
<?php
$host = "localhost";
$username = "root";
$password = "root";
$database = "akademitrack";
$port = 8889;

$conn = mysqli_connect(
    $host,
    $username,
    $password,
    $database,
    $port
);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");
?>
