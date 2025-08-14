<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

// Cek apakah form sudah disubmit
if (!isset($_POST['detailBtn']) || empty($_POST['selected_users']) || empty($_POST['tanggal_dari']) || empty($_POST['tanggal_sampai'])) {
    header('Location: halaman-users.php');
    exit;
}

$selected_users = $_POST['selected_users'];
$tanggal_dari = $_POST['tanggal_dari'];
$tanggal_sampai = $_POST['tanggal_sampai'];

// Ambil data user dan absensi
$users = getUsers($ip, $port, $key);
$all_attendance = getAttendanceRange($ip, $port, $key, $tanggal_dari, $tanggal_sampai, $users);

// Filter data absensi hanya untuk user yang dipilih
$filtered_attendance = array_filter($all_attendance, function($record) use ($selected_users) {
    return in_array($record['pin'], $selected_users);
});

// Ambil NIP & Bagian dari database
$pins = array_map('intval', $selected_users);
$pin_list = implode(',', $pins);

$nip_data = [];
if (!empty($pins)) {
    $result = mysqli_query($mysqli, "SELECT pin, nip, bagian FROM karyawan_test WHERE pin IN ($pin_list)");
    while ($row = mysqli_fetch_assoc($result)) {
        $nip_data[$row['pin']] = [
            'nip'    => $row['nip'],
            'bagian' => $row['bagian']
        ];
    }
}

// Tambahkan NIP & Bagian ke setiap record
foreach ($filtered_attendance as &$record) {
    if (isset($nip_data[$record['pin']])) {
        $record['nip']    = $nip_data[$record['pin']]['nip'];
        $record['bagian'] = $nip_data[$record['pin']]['bagian'];
    } else {
        $record['nip']    = '-';
        $record['bagian'] = '-';
    }
}
unset($record);

// Dapatkan statistik
$stats = getAttendanceStats($filtered_attendance);

// Handle export CSV
if (isset($_POST['exportBtn'])) {
    exportToCsv($filtered_attendance, 'absensi_' . $tanggal_dari . '_to_' . $tanggal_sampai . '.csv');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Detail Absensi</title>
<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; width: 100%; margin-top: 10px; }
th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
th { background: #f0f0f0; font-weight: bold; }
tr:nth-child(even) { background: #f9f9f9; }
.info-box { background: #e3f2fd; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
.stats-container { display: flex; gap: 20px; margin-bottom: 20px; }
.stat-box { background: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 5px; flex: 1; text-align: center; }
.stat-number { font-size: 24px; font-weight: bold; color: #007bff; }
.btn { padding: 8px 15px; cursor: pointer; border: none; border-radius: 3px; text-decoration: none; display: inline-block; margin: 5px; }
.btn-primary { background: #007bff; color: white; }
.btn-success { background: #28a745; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.status-in { color: #28a745; font-weight: bold; }
.status-out { color: #dc3545; font-weight: bold; }
.no-data { text-align: center; padding: 30px; color: #6c757d; font-style: italic; }
.filter-info { background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
.status-none { color: #ff9800; font-weight: bold; }
</style>
</head>
<body>

<h2>📊 Detail Absensi</h2>

<div class="filter-info">
    <strong>Filter:</strong> 
    📅 <?= formatTanggalIndonesia($tanggal_dari) ?> 
    <?php if ($tanggal_dari !== $tanggal_sampai): ?>
        s/d <?= formatTanggalIndonesia($tanggal_sampai) ?>
    <?php endif; ?>
    | 👥 <?= count($selected_users) ?> User Dipilih
</div>

<!-- Statistik -->
<div class="stats-container">
    <div class="stat-box">
        <div class="stat-number"><?= $stats['total'] ?></div>
        <div>Total Record</div>
    </div>
    <div class="stat-box">
        <div class="stat-number" style="color: #28a745;"><?= $stats['total_in'] ?></div>
        <div>Masuk (IN)</div>
    </div>
    <div class="stat-box">
        <div class="stat-number" style="color: #dc3545;"><?= $stats['total_out'] ?></div>
        <div>Keluar (OUT)</div>
    </div>
    <div class="stat-box">
        <div class="stat-number"><?= count($stats['by_user']) ?></div>
        <div>User Aktif</div>
    </div>
</div>

<!-- Action Buttons -->
<div style="margin-bottom: 15px;">
    <a href="halaman-users-fix.php" class="btn btn-secondary">⬅️ Kembali</a>
    <?php if (!empty($filtered_attendance)): ?>
        <form method="POST" style="display: inline;">
            <input type="hidden" name="detailBtn" value="1">
            <?php foreach ($selected_users as $user): ?>
                <input type="hidden" name="selected_users[]" value="<?= htmlspecialchars($user) ?>">
            <?php endforeach; ?>
            <input type="hidden" name="tanggal_dari" value="<?= htmlspecialchars($tanggal_dari) ?>">
            <input type="hidden" name="tanggal_sampai" value="<?= htmlspecialchars($tanggal_sampai) ?>">
            <button type="submit" name="exportBtn" class="btn btn-success">📥 Export CSV</button>
        </form>
    <?php endif; ?>
</div>

<!-- Tabel Data Absensi -->
<table>
    <thead>
        <tr>
            <th>No</th>
            <th>PIN</th>
            <th>NIP</th>
            <th>Nama</th>
            <th>Bagian</th>
            <th>Tanggal</th>
            <th>Waktu</th>
            <th>Status</th>
            <th>Verified</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $no = 1;
    $periode = new DatePeriod(
        new DateTime($tanggal_dari),
        new DateInterval('P1D'),
        (new DateTime($tanggal_sampai))->modify('+1 day')
    );

    foreach ($selected_users as $pin) {
        foreach ($periode as $tgl) {
            $tanggal_str = $tgl->format('Y-m-d');
            $found = array_filter($filtered_attendance, function($item) use ($pin, $tanggal_str) {
                return $item['pin'] == $pin && date('Y-m-d', strtotime($item['datetime'])) == $tanggal_str;
            });

            if (!empty($found)) {
                foreach ($found as $record) {
                    echo "<tr>
                        <td>" . $no++ . "</td>
                        <td>{$record['pin']}</td>
                        <td>{$record['nip']}</td>
                        <td>{$record['nama']}</td>
                        <td>{$record['bagian']}</td>
                        <td>" . date('d/m/Y', strtotime($record['datetime'])) . "</td>
                        <td>" . date('H:i:s', strtotime($record['datetime'])) . "</td>
                        <td><span class='" . ($record['status'] == 'IN' ? 'status-in' : 'status-out') . "'>{$record['status']}</span></td>
                        <td>{$record['verified']}</td>
                    </tr>";
                }
            } else {
                echo "<tr style='background:#fff8e1;'>
                    <td>" . $no++ . "</td>
                    <td>{$pin}</td>
                    <td>" . ($nip_data[$pin]['nip'] ?? '-') . "</td>
                    <td>" . ($users[$pin] ?? '-') . "</td>
                    <td>" . ($nip_data[$pin]['bagian'] ?? '-') . "</td>
                    <td>" . date('d/m/Y', strtotime($tanggal_str)) . "</td>
                    <td>-</td>
                    <td class='status-none'>Tidak Absen</td>
                    <td>-</td>
                </tr>";
            }
        }
    }
    ?>
    </tbody>
</table>

</body>
</html>
