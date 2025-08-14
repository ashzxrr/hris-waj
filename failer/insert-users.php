<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

// Koneksi database
$mysqli = new mysqli("localhost","root","","absensi"); // Ganti sesuai DB
if ($mysqli->connect_error) {
    die("DB connection failed: " . $mysqli->connect_error);
}

// Set charset UTF8MB4
$mysqli->set_charset("utf8mb4");

// Ambil data user
$users = getUsers($ip, $port, $key);
if(empty($users)) die("Data user dari mesin kosong!");

// Batch insert
$values = [];
foreach($users as $pin => $name) {
    $pin_val = $mysqli->real_escape_string($pin);
    $name_val = $mysqli->real_escape_string($name);
    $values[] = "('$pin_val','$name_val')";
}

if(!empty($values)) {
    $sql = "INSERT INTO tb_user (pin,name) VALUES " . implode(',', $values) .
           " ON DUPLICATE KEY UPDATE name=VALUES(name)";
    if(!$mysqli->query($sql)) {
        die("Gagal menyimpan data: " . $mysqli->error);
    } else {
        echo "✅ Berhasil menyimpan " . count($users) . " user ke database!";
    }
}
