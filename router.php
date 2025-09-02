<?php
session_start();
require __DIR__ . '/includes/config.php';

// Default page
$page = $_GET['page'] ?? 'dash';

// Daftar halaman yang diizinkan
$allowed_pages = [
    'login'    => __DIR__ . '/index.php',
    'dash'     => __DIR__ . '/page/dash/home.php',
    'logout'   => __DIR__ . '/page/auth/logout.php',
    'payroll'  => __DIR__ . '/page/payroll/payroll.php',
    'users'    => __DIR__ . '/page/users/karyawan.php',
    'users-detail'    => __DIR__ . '/page/users/detail.php',
    'users-add'    => __DIR__ . '/page/users/add-karyawan.php'
];

// Cek apakah page ada di daftar
if (array_key_exists($page, $allowed_pages)) {
    require $allowed_pages[$page];
} else {
    http_response_code(404);
    echo "<h1>404 - Halaman tidak</h1>";
}
