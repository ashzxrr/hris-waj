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
    <a href="halaman-users.php" class="btn btn-secondary">⬅️ Kembali</a>
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
<?php if (empty($filtered_attendance)): ?>
    <div class="no-data">
        <h3>😔 Tidak Ada Data</h3>
        <p>Tidak ditemukan data absensi untuk user dan periode yang dipilih.</p>
    </div>
<?php else: ?>
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
            <?php foreach ($filtered_attendance as $i => $record): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($record['pin']) ?></td>
                    <td><?= htmlspecialchars($record['nama']) ?></td>
                    <td><?= date('d/m/Y', strtotime($record['datetime'])) ?></td>
                    <td><?= date('H:i:s', strtotime($record['datetime'])) ?></td>
                    <td>
                        <span class="<?= $record['status'] == 'IN' ? 'status-in' : 'status-out' ?>">
                            <?= $record['status'] ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($record['verified']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<!-- Statistik Per User -->
<?php if (!empty($stats['by_user'])): ?>
    <h3 style="margin-top: 30px;">📈 Statistik Per User</h3>
    <table>
        <thead>
            <tr>
                <th>User (PIN - Nama)</th>
                <th>Total Record</th>
                <th>Masuk (IN)</th>
                <th>Keluar (OUT)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stats['by_user'] as $user => $stat): ?>
                <tr>
                    <td><?= htmlspecialchars($user) ?></td>
                    <td><?= $stat['total'] ?></td>
                    <td style="color: #28a745; font-weight: bold;"><?= $stat['in'] ?></td>
                    <td style="color: #dc3545; font-weight: bold;"><?= $stat['out'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<!-- Statistik Per Tanggal -->
<?php if (!empty($stats['by_date']) && count($stats['by_date']) > 1): ?>
    <h3 style="margin-top: 30px;">📅 Statistik Per Tanggal</h3>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Total Record</th>
                <th>Masuk (IN)</th>
                <th>Keluar (OUT)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            // Urutkan tanggal
            krsort($stats['by_date']);
            foreach ($stats['by_date'] as $date => $stat): 
            ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($date)) ?></td>
                    <td><?= $stat['total'] ?></td>
                    <td style="color: #28a745; font-weight: bold;"><?= $stat['in'] ?></td>
                    <td style="color: #dc3545; font-weight: bold;"><?= $stat['out'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

</body>
</html>