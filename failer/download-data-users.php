<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';
// Koneksi database
$mysqli = new mysqli("localhost","root","","absensi"); // Ganti sesuai DB
if ($mysqli->connect_error) { die(json_encode(['error' => 'DB connection failed'])); }


// Ambil data user dari mesin fingerprint
$users = getUsers($ip, $port, $key);

// Simpan ke tabel_user
foreach($users as $pin2 => $name) {
    $pin2_val = $mysqli->real_escape_string($pin2);
    $name_val = $mysqli->real_escape_string($name);

    $mysqli->query("INSERT INTO user_coba (pin, name) 
        VALUES ('$pin2_val', '$name_val') 
        ON DUPLICATE KEY UPDATE name='$name_val'");
}
