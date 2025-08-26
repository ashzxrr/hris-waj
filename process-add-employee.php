<?php
session_start();
require __DIR__ . '/includes/config.php';

try {
    // Ambil data dari GET parameters
    $pin = trim(mysqli_real_escape_string($mysqli, $_GET['pin']));
    $nip = trim(mysqli_real_escape_string($mysqli, $_GET['nip'])) ?: null;
    $nama = trim(mysqli_real_escape_string($mysqli, $_GET['nama_mesin']));
    $nik = trim(mysqli_real_escape_string($mysqli, $_GET['nik'])) ?: null;
    $jk = trim(mysqli_real_escape_string($mysqli, $_GET['jk'])) ?: null;
    $job_title = trim(mysqli_real_escape_string($mysqli, $_GET['job_title'])) ?: null;
    $job_level = trim(mysqli_real_escape_string($mysqli, $_GET['job_level'])) ?: null;
    $bagian = trim(mysqli_real_escape_string($mysqli, $_GET['bagian'])) ?: null;
    $departemen = trim(mysqli_real_escape_string($mysqli, $_GET['departemen'])) ?: null;

    // Cek PIN duplikat
    $check = mysqli_query($mysqli, "SELECT pin FROM users WHERE pin = '$pin'");
    if (mysqli_num_rows($check) > 0) {
        $_SESSION['error'] = "PIN sudah terdaftar di database!";
        header('Location: users2.php');
        exit;
    }

    // Query insert
    $query = "INSERT INTO users (
        pin, nip, nama, nik, jk, job_title, job_level, bagian, departemen, created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("sssssssss", 
        $pin, $nip, $nama, $nik, $jk, $job_title, $job_level, $bagian, $departemen
    );

    if ($stmt->execute()) {
        $_SESSION['success'] = "Data karyawan berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal menambahkan data karyawan!";
    }

    $stmt->close();

} catch (Exception $e) {
    $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
}

header('Location: users2.php');
exit;