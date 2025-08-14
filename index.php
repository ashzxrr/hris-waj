<?php
require 'config.php';
require 'functions.php';

if(!isset($_POST['selected_users']) || !isset($_POST['tanggal_dari']) || !isset($_POST['tanggal_sampai'])) {
    die("❌ Data tidak lengkap!");
}

$selected_users = $_POST['selected_users']; // array PIN
$tanggal_dari = $_POST['tanggal_dari'];
$tanggal_sampai = $_POST['tanggal_sampai'];

// Ambil semua user dari mesin (nama)
$all_users = getUsers($ip, $port, $key);

// Filter users yang dipilih
$users_filtered = [];
foreach($selected_users as $pin) {
    $users_filtered[$pin] = $all_users[$pin] ?? '(Tidak Diketahui)';
}

// Ambil absensi dari mesin untuk tanggal range
$data_absen = getAttendanceRange($ip, $port, $key, $tanggal_dari, $tanggal_sampai, $users_filtered);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Detail Absensi</title>
<style>
table { border-collapse: collapse; width: 100%; margin-top: 10px; }
th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
th { background: #f0f0f0; }
tr:nth-child(even) { background: #f9f9f9; }
</style>
</head>
<body>

<h2>📊 Detail Absensi</h2>
<p>Dari: <?= htmlspecialchars($tanggal_dari) ?> | Sampai: <?= htmlspecialchars($tanggal_sampai) ?></p>

<table>
    <thead>
        <tr>
            <th>No</th>
            <th>PIN</th>
            <th>Nama</th>
            <th>Tanggal</th>
            <th>Waktu</th>
            <th>Status</th>
            <th>Verified</th>
        </tr>
    </thead>
    <tbody>
        <?php $no=1; foreach($data_absen as $row): ?>
            <tr>
                <td><?= $no ?></td>
                <td><?= htmlspecialchars($row['pin']) ?></td>
                <td><?= htmlspecialchars($row['nama']) ?></td>
                <td><?= date('d/m/Y', strtotime($row['datetime'])) ?></td>
                <td><?= date('H:i:s', strtotime($row['datetime'])) ?></td>
                <td><?= htmlspecialchars($row['status']) ?></td>
                <td><?= htmlspecialchars($row['verified']) ?></td>
            </tr>
        <?php $no++; endforeach; ?>
    </tbody>
</table>

</body>
</html>
