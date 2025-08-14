<?php
// detail.php

// Konfigurasi mesin fingerprint
$ip = "192.168.0.102";
$port = 80;
$key = 0;

// Koneksi ke database
$mysqli = new mysqli("localhost", "root", "", "absensi");
if ($mysqli->connect_errno) {
    die("Gagal konek MySQL: " . $mysqli->connect_error);
}

// Ambil data dari parameter
$pin = isset($_GET['pin']) ? $_GET['pin'] : '';
$tanggal_dari = isset($_GET['dari']) ? $_GET['dari'] : '';
$tanggal_sampai = isset($_GET['sampai']) ? $_GET['sampai'] : '';

if (empty($pin) || empty($tanggal_dari) || empty($tanggal_sampai)) {
    die("Parameter tidak lengkap");
}

// Ambil NIP dari database
$query_karyawan = $mysqli->query("SELECT pin, nip, bagian nama FROM karyawan_test WHERE pin = '$pin' LIMIT 1");
$karyawan = $query_karyawan->fetch_assoc();
if (!$karyawan) {
    die("Karyawan tidak ditemukan");
}

// Ambil log absensi dari database
$query_logs = $mysqli->query("SELECT pin, datetime FROM log_absensi WHERE pin = '$pin' AND DATE(datetime) BETWEEN '$tanggal_dari' AND '$tanggal_sampai' ORDER BY datetime ASC");
$logs = [];
while ($row = $query_logs->fetch_assoc()) {
    $logs[] = $row;
}

// Buat array tanggal dari rentang yang dipilih
$period = new DatePeriod(
    new DateTime($tanggal_dari),
    new DateInterval('P1D'),
    (new DateTime($tanggal_sampai))->modify('+1 day')
);

$data_harian = [];
foreach ($period as $date) {
    $hari = $date->format('w'); // 0 = Minggu, 1 = Senin, dst
    $tanggal_str = $date->format('Y-m-d');

    if ($hari == 0) {
        // Hari Minggu, tandai sebagai libur
        $data_harian[] = [
            'tanggal' => $tanggal_str,
            'status' => 'Libur Minggu',
            'jam' => '-'
        ];
    } else {
        // Cek apakah ada log pada tanggal ini
        $ada_log = false;
        foreach ($logs as $log) {
            if (date('Y-m-d', strtotime($log['datetime'])) == $tanggal_str) {
                $ada_log = true;
                $data_harian[] = [
                    'tanggal' => $tanggal_str,
                    'status' => 'Hadir',
                    'jam' => date('H:i:s', strtotime($log['datetime']))
                ];
                break;
            }
        }

        if (!$ada_log) {
            $data_harian[] = [
                'tanggal' => $tanggal_str,
                'status' => 'Tidak ada checklog',
                'jam' => '-'
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Detail Absensi</title>
    <style>
        table {
            border-collapse: collapse;
            width: 60%;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: center;
        }
        th {
            background: #f4f4f4;
        }
        .libur { background: #ffcccc; }
        .tidak-ada { background: #ffeeba; }
        .hadir { background: #ccffcc; }
    </style>
</head>
<body>
    <h2>Detail Absensi: <?= htmlspecialchars($karyawan['nama']) ?> (NIP: <?= htmlspecialchars($karyawan['nip']) ?>)</h2>
    <p>Periode: <?= $tanggal_dari ?> sampai <?= $tanggal_sampai ?></p>

    <table>
        <tr>
            <th>Tanggal</th>
            <th>Status</th>
            <th>Jam</th>
        </tr>
        <?php foreach ($data_harian as $data): ?>
            <tr class="<?= $data['status'] == 'Libur Minggu' ? 'libur' : ($data['status'] == 'Tidak ada checklog' ? 'tidak-ada' : 'hadir') ?>">
                <td><?= $data['tanggal'] ?></td>
                <td><?= $data['status'] ?></td>
                <td><?= $data['jam'] ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
