<?php
/**
 * ===============================================
 * KONFIGURASI MULTI MESIN FINGERPRINT
 * AUTO COMBINED - Data otomatis dari semua mesin
 * TANPA SWITCH - TANPA PILIH MESIN
 * ===============================================
 */

// ===============================================
// 1. KONFIGURASI MULTI MESIN   
// ===============================================
$machines = [
    [
        'ip' => '192.168.110.2',        // Router Tenda Produksi Produksi 2
        'port' => 8080,                 // Port forwarding existing
        'key' => 0,
        'name' => 'Fingerprint Produksi 2A',
        'active' => true
    ],
    [
        'ip' => '192.168.110.2',        // Router Rujie Packing  
        'port' => 8083,                 // Port forwarding baru - PERLU DITAMBAH DI ROUTER
        'key' => 0,
        'name' => 'Fingerprint Produksi 2B',
        'active' => true
    ]
];

// ===============================================
// 2. DATABASE CONFIGURATION
// ===============================================
$mysqli = new mysqli("localhost", "root", "", "absensi");

if ($mysqli->connect_errno) {
    die("Gagal konek MySQL: " . $mysqli->connect_error);
}

$dbHost = "localhost";
$dbUser = "root"; 
$dbPass = "";
$dbName = "absensi";

// ===============================================
// 3. SYSTEM SETTINGS  
// ===============================================
ini_set('default_socket_timeout', 10);

// ===============================================
// 4. BACKWARD COMPATIBILITY VARIABLES
// ===============================================
// Variables ini tetap ada supaya kode existing tidak error
// Tapi tidak dipakai lagi karena sekarang auto combined
$ip = "192.168.110.2";
$port = 8080;
$soap_port = 8080;
$key = 0;
?>