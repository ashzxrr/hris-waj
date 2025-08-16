<?php
// Konfigurasi mesin fingerprint
$ip = "192.168.0.100"; // IP mesin
$port = 80;  
$soap_port = $port;
$key = 0;              // Communication key

$mysqli = new mysqli("localhost", "root", "", "absensi");

if ($mysqli->connect_errno) {
    die("Gagal konek MySQL: " . $mysqli->connect_error);
}

?>