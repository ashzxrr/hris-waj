<?php
// Konfigurasi mesin fingerprint via Port Forwarding
$ip = "192.168.110.2"; // IP Router Tenda (gateway)
$port = 8080;          // Port forwarding yang sudah diset di router Tenda
$soap_port = $port;    // Port untuk SOAP communication
$key = 0;              // Communication key

// Database tetap sama (lokal)
$mysqli = new mysqli("localhost", "root", "", "absensi");

if ($mysqli->connect_errno) {
    die("Gagal konek MySQL: " . $mysqli->connect_error);
}

$dbHost = "localhost";
$dbUser = "root"; 
$dbPass = "";
$dbName = "absensi";

// Optional: Tambah timeout untuk koneksi yang lebih lama
ini_set('default_socket_timeout', 2);
?>