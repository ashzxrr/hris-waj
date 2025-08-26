<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/functions.php';

// Handle date range filter
$tanggal_dari = isset($_GET['tanggal_dari']) ? $_GET['tanggal_dari'] : date('Y-m-d');
$tanggal_sampai = isset($_GET['tanggal_sampai']) ? $_GET['tanggal_sampai'] : date('Y-m-d');

// Validasi tanggal
if (strtotime($tanggal_dari) > strtotime($tanggal_sampai)) {
    $temp = $tanggal_dari;
    $tanggal_dari = $tanggal_sampai;
    $tanggal_sampai = $temp;
}

$users = getUsers($ip, $port, $key);

// Menggunakan function baru getAttendanceRange untuk mendukung date range
$data_absen = getAttendanceRange($ip, $port, $key, $tanggal_dari, $tanggal_sampai, $users);

// Mendapatkan statistik untuk summary
$stats = getAttendanceStats($data_absen);

// Handle export CSV jika diminta
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    $filename = 'absensi_' . date('Ymd', strtotime($tanggal_dari));
    if ($tanggal_dari != $tanggal_sampai) {
        $filename .= '_sampai_' . date('Ymd', strtotime($tanggal_sampai));
    }
    $filename .= '.csv';
    exportToCsv($data_absen, $filename);
}
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
        .filter-section { 
            background: #f8f9fa; 
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 20px;
        }
        .date-input {
            max-width: 200px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="mb-4">📋 Data Absensi Fingerprint</h2>

        <!-- Date Range Filter -->
        <div class="filter-section">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="tanggal_dari" class="form-label">Tanggal Dari:</label>
                    <input type="date" class="form-control date-input" id="tanggal_dari" 
                           name="tanggal_dari" value="<?= htmlspecialchars($tanggal_dari) ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="tanggal_sampai" class="form-label">Tanggal Sampai:</label>
                    <input type="date" class="form-control date-input" id="tanggal_sampai" 
                           name="tanggal_sampai" value="<?= htmlspecialchars($tanggal_sampai) ?>" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Filter Data
                    </button>
                    <button type="button" class="btn btn-outline-secondary ms-2" onclick="resetToToday()">
                        Hari Ini
                    </button>
                    <a href="?tanggal_dari=<?= $tanggal_dari ?>&tanggal_sampai=<?= $tanggal_sampai ?>&export=csv" 
                       class="btn btn-success ms-2" target="_blank">
                        📊 Export CSV
                    </a>
                </div>
            </form>
            
            <?php if ($tanggal_dari != $tanggal_sampai): ?>
                <div class="mt-2">
                    <small class="text-muted">
                        Menampilkan data dari <strong><?= date('d/m/Y', strtotime($tanggal_dari)) ?></strong> 
                        sampai <strong><?= date('d/m/Y', strtotime($tanggal_sampai)) ?></strong>
                    </small>
                </div>
            <?php else: ?>
                <div class="mt-2">
                    <small class="text-muted">
                        Menampilkan data tanggal <strong><?= date('d/m/Y', strtotime($tanggal_dari)) ?></strong>
                    </small>
                </div>
            <?php endif; ?>
        </div>

        <!-- Search Input -->
        <div class="mb-3">
            <input type="text" id="searchInput" class="form-control" placeholder="Cari nama atau PIN...">
        </div>

        <!-- Data Table -->
        <?php if (count($data_absen) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>No</th>
                        <th>Nama</th>
                        <th>PIN</th>
                        <th>Tanggal</th>
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
                        <td><?= date('d/m/Y', strtotime($absen['datetime'])) ?></td>
                        <td><?= date('H:i:s', strtotime($absen['datetime'])) ?></td>
                        <td><?= $absen['verified'] ?></td>
                        <td class="<?= $absen['status'] == 'IN' ? 'status-in' : 'status-out' ?>">
                            <?= $absen['status'] ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Summary dengan statistik lebih detail -->
        <div class="row mt-3">
            <div class="col-md-3">
                <div class="alert alert-info mb-2">
                    <strong>Total Log:</strong> <?= $stats['total'] ?> record
                </div>
            </div>
            <div class="col-md-3">
                <div class="alert alert-success mb-2">
                    <strong class="status-in">Masuk:</strong> <?= $stats['total_in'] ?> log
                </div>
            </div>
            <div class="col-md-3">
                <div class="alert alert-danger mb-2">
                    <strong class="status-out">Keluar:</strong> <?= $stats['total_out'] ?> log
                </div>
            </div>
            <div class="col-md-3">
                <div class="alert alert-secondary mb-2">
                    <strong>User Aktif:</strong> <?= count($stats['by_user']) ?> orang
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> 
            Tidak ada data absensi untuk periode <?= date('d/m/Y', strtotime($tanggal_dari)) ?> 
            <?php if ($tanggal_dari != $tanggal_sampai): ?>
                - <?= date('d/m/Y', strtotime($tanggal_sampai)) ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#absensiTable tr');
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });

        // Reset to today
        function resetToToday() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('tanggal_dari').value = today;
            document.getElementById('tanggal_sampai').value = today;
        }

        // Auto-submit on date change (optional)
        document.getElementById('tanggal_dari').addEventListener('change', function() {
            // Uncomment if you want auto-submit
            // this.form.submit();
        });
        
        document.getElementById('tanggal_sampai').addEventListener('change', function() {
            // Uncomment if you want auto-submit  
            // this.form.submit();
        });
    </script>
</body>
</html>