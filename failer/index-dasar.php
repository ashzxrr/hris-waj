<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/functions.php';

$users = getUsers($ip, $port, $key);
$data_absen = getAttendance($ip, $port, $key, $tanggal_filter, $users);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Data Absensi Fingerprint</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; }
        .status-in { color: green; font-weight: bold; }
        .status-out { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="mb-3">📋 Data Absensi Fingerprint - <?= date($tanggal_filter) ?></h2>

        <div class="mb-3">
            <input type="text" id="searchInput" class="form-control" placeholder="Cari nama atau PIN...">
        </div>

        <?php if (count($data_absen) > 0): ?>
        <table class="table table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th>No</th>
                    <th>Nama</th>
                    <th>PIN</th>
                    <th>Waktu</th>
                    <th>Verified</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="absensiTable">
                <?php foreach ($data_absen as $i => $absen): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($absen['nama']) ?></td>
                    <td><?= $absen['pin'] ?></td>
                    <td><?= $absen['datetime'] ?></td>
                    <td><?= $absen['verified'] ?></td>
                    <td class="<?= $absen['status'] == 'IN' ? 'status-in' : 'status-out' ?>">
                        <?= $absen['status'] ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p><strong>Total:</strong> <?= count($data_absen) ?> log</p>
        <?php else: ?>
        <div class="alert alert-warning">Tidak ada data absensi untuk tanggal ini.</div>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#absensiTable tr');
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    </script>
</body>
</html>