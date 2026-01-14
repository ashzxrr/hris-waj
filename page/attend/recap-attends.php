<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/../../includes/config.php';
require __DIR__ . '/../../includes/functions.php';

// Define export function before using it
function exportRecapToCsv($data, $filename, $tanggal_dari, $tanggal_sampai) {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Write summary info
    fputcsv($output, ['Rekap Absensi Karyawan']);
    fputcsv($output, ['Periode', date('d/m/Y', strtotime($tanggal_dari)) . ' - ' . date('d/m/Y', strtotime($tanggal_sampai))]);
    fputcsv($output, []);

    // Calculate statistics
    $total_karyawan = count($data);
    $total_hadir = count(array_filter($data, function($row) { return $row['jumlah_hadir'] > 0; }));
    $total_tidak_hadir = $total_karyawan - $total_hadir;
    $total_hari_kerja = array_sum(array_column($data, 'jumlah_hadir'));

    fputcsv($output, ['STATISTIK RINGKAS']);
    fputcsv($output, ['Total Karyawan', $total_karyawan]);
    fputcsv($output, ['Karyawan Hadir', $total_hadir]);
    fputcsv($output, ['Karyawan Tidak Hadir', $total_tidak_hadir]);
    fputcsv($output, ['Total Hari Hadir', $total_hari_kerja]);
    fputcsv($output, []);

    // Write headers
    fputcsv($output, [
        'PIN',
        'Nama',
        'NIP',
        'NIK',
        'Gender',
        'Jabatan',
        'Level',
        'Bagian',
        'Kategori Gaji',
        'Departemen',
        'TL',
        'Jumlah Hadir'
    ]);

    // Write data
    foreach ($data as $row) {
        fputcsv($output, [
            $row['pin'],
            $row['nama'],
            $row['nip'],
            $row['nik'],
            $row['jk'],
            $row['job_title'],
            $row['job_level'],
            $row['bagian'],
            $row['kategori_gaji'],
            $row['departemen'],
            $row['tl'],
            $row['jumlah_hadir']
        ]);
    }

}

// Handle export CSV - MUST be before any output/header
if (isset($_POST['exportBtn'])) {
    try {
        if (empty($_POST['selected_users']) || empty($_POST['tanggal_dari']) || empty($_POST['tanggal_sampai'])) {
            throw new Exception('Data tidak lengkap untuk export');
        }

        $selected_users = $_POST['selected_users'];
        $tanggal_dari = $_POST['tanggal_dari'];
        $tanggal_sampai = $_POST['tanggal_sampai'];

        // Re-process the data for export
        $raw_selected = $selected_users;
        $selected_users_normalized = array_map(function($p){ return preg_match('/^\d+$/', trim((string)$p)) ? (string)intval(trim((string)$p)) : trim((string)$p); }, $raw_selected);

        $users = getUsers($ip, $port, $key);
        $all_attendance = getAttendanceRange($ip, $port, $key, $tanggal_dari, $tanggal_sampai, $users);
        $filtered_attendance = array_filter($all_attendance, function ($record) use ($selected_users_normalized) {
            return in_array($record['pin'], $selected_users_normalized);
        });

        $pins = array_map('intval', $selected_users);
        $nip_data_raw = get_nip_data_from_db($mysqli, $pins);
        $nip_data = [];
        foreach ($nip_data_raw as $k => $v) {
            $nip_data[normalize_pin($k)] = $v;
        }

        // Generate filename
        $filename = sprintf(
            'rekap_absensi_%s_to_%s.csv',
            date('Y-m-d', strtotime($tanggal_dari)),
            date('Y-m-d', strtotime($tanggal_sampai))
        );

        exportRecapToCsv($attendance_summary, $filename, $tanggal_dari, $tanggal_sampai);

    } catch (Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo "Error generating export: " . $e->getMessage();
        exit;
    }
}

// Now include header after export handler check
require __DIR__ . '/../../includes/header.php';
if (!isset($_POST['selected_users']) || empty($_POST['selected_users']) || empty($_POST['tanggal_dari']) || empty($_POST['tanggal_sampai'])) {
    header('Location: ?page=attend');
    exit;
}

$raw_selected = $_POST['selected_users'];
// Normalize selected users to canonical PIN strings to match normalized attendance data
$selected_users = array_map(function($p){ return trim((string)$p); }, $raw_selected);
// Also prepare normalized versions (no leading zeros)
$selected_users_normalized = array_map(function($p){ return preg_match('/^\d+$/', trim((string)$p)) ? (string)intval(trim((string)$p)) : trim((string)$p); }, $raw_selected);
$tanggal_dari = $_POST['tanggal_dari'];
$tanggal_sampai = $_POST['tanggal_sampai'];

// Ambil data user dan absensi
$users = getUsers($ip, $port, $key);
$all_attendance = getAttendanceRange($ip, $port, $key, $tanggal_dari, $tanggal_sampai, $users);

// Filter data absensi hanya untuk user yang dipilih (use normalized pins)
$filtered_attendance = array_filter($all_attendance, function ($record) use ($selected_users_normalized) {
    return in_array($record['pin'], $selected_users_normalized);
});

// Ambil data user dari database
$pins = array_map('intval', $selected_users);
$pin_list = implode(',', $pins);

$nip_data_raw = get_nip_data_from_db($mysqli, $pins);
$nip_data = [];
foreach ($nip_data_raw as $k => $v) {
    $nip_data[normalize_pin($k)] = $v;
}

// Tambahkan TL data
$database_users = [];
$database_users_by_id = [];
$query = "SELECT id, pin, tl_id, nama FROM users ORDER BY CAST(pin AS UNSIGNED)";
$result = mysqli_query($mysqli, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $database_users[normalize_pin($row['pin'])] = $row;
        $database_users_by_id[$row['id']] = $row;
    }
}

// Hitung jumlah hadir per user
$attendance_summary = [];
foreach ($selected_users_normalized as $pin) {
    $user_data = isset($nip_data[$pin]) ? $nip_data[$pin] : [
        'nip' => '-',
        'nama' => isset($users[$pin]) ? $users[$pin] : '-',
        'nik' => '-',
        'jk' => '-',
        'job_title' => '-',
        'job_level' => '-',
        'bagian' => '-',
        'departemen' => '-',
        'kategori_gaji' => '-'
    ];

    // Hitung jumlah hari hadir (hari unik dengan record)
    $unique_days = [];
    foreach ($filtered_attendance as $record) {
        if ($record['pin'] == $pin) {
            $day = date('Y-m-d', strtotime($record['datetime']));
            $unique_days[$day] = true;
        }
    }
    $jumlah_hadir = count($unique_days);

    // Ambil TL name
    $tl_name = '-';
    if (isset($database_users[$pin]) && $database_users[$pin]['tl_id']) {
        $tl_id = $database_users[$pin]['tl_id'];
        if (isset($database_users_by_id[$tl_id])) {
            $tl_name = $database_users_by_id[$tl_id]['nama'];
        }
    }

    $attendance_summary[] = [
        'pin' => $pin,
        'nama' => $user_data['nama'],
        'nip' => $user_data['nip'],
        'nik' => $user_data['nik'],
        'jk' => $user_data['jk'],
        'job_title' => $user_data['job_title'],
        'job_level' => $user_data['job_level'],
        'bagian' => $user_data['bagian'],
        'kategori_gaji' => $user_data['kategori_gaji'],
        'departemen' => $user_data['departemen'],
        'tl' => $tl_name,
        'jumlah_hadir' => $jumlah_hadir
    ];
}

/**
 * Fetch user info (nip_data) for given pins from DB.
 * Returns array keyed by pin with fields: nip, nama, nik, jk, job_title, job_level, bagian, departemen, kategori_gaji
 */
function get_nip_data_from_db($mysqli, $pins)
{
    $nip_data = [];
    if (empty($pins)) {
        return $nip_data;
    }

    $pin_list = implode(',', array_map('intval', $pins));
    $sql = "SELECT pin, nip, nama, bagian, nik, jk, job_title, job_level, bagian, departemen, kategori_gaji FROM users WHERE pin IN ($pin_list)";
    $res = mysqli_query($mysqli, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $nip_data[$row['pin']] = [
                'nip' => $row['nip'],
                'nama' => $row['nama'],
                'nik' => $row['nik'],
                'jk' => $row['jk'],
                'job_title' => $row['job_title'],
                'job_level' => $row['job_level'],
                'bagian' => $row['bagian'],
                'departemen' => $row['departemen'],
                'kategori_gaji' => $row['kategori_gaji'] ?? ''
            ];
        }
    }

    return $nip_data;
}


?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/style-user.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <title>Rekap Absensi Karyawan</title>
    <style>
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }

        .header-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .period-info {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 20px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }

        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 10px;
            border: 1px solid #dee2e6;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #495057;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 500;
        }

        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }

        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }

        .filter-controls {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-input {
            padding: 10px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            width: 300px;
            background: #f8f9fa;
        }

        .search-input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }

        .filter-select {
            padding: 10px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            background: #f8f9fa;
            cursor: pointer;
            min-width: 200px;
        }

        .filter-select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .summary-table thead {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        }

        .summary-table th {
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .summary-table td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
            color: #495057;
        }

        .summary-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .summary-table tbody tr:nth-child(even) {
            background-color: #f8f9ff;
        }

        .pin-column {
            font-weight: 600;
            color: #007bff;
        }

        .hadir-column {
            font-weight: 700;
            text-align: center;
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-radius: 6px;
            padding: 8px 12px;
            margin: 2px;
        }

        .nama-column {
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .header-section {
                padding: 20px;
            }

            .header-title {
                font-size: 1.8rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .filter-controls {
                flex-direction: column;
                gap: 10px;
                align-items: stretch;
            }

            .search-input {
                width: 100%;
            }

            .filter-select {
                width: 100%;
                min-width: unset;
            }

            .summary-table {
                font-size: 0.8rem;
            }

            .summary-table th,
            .summary-table td {
                padding: 8px 6px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-section">
            <h1 class="header-title">
                📊 Rekap Absensi Karyawan
            </h1>
            <div class="period-info">
                Periode: <strong><?= date('d F Y', strtotime($tanggal_dari)) ?> - <?= date('d F Y', strtotime($tanggal_sampai)) ?></strong>
            </div>
            <div class="action-buttons">
                <button type="button" class="btn btn-primary" onclick="exportToExcel()">
                    📊 Export Excel
                </button>
                <a href="?page=attends" class="btn btn-secondary">
                    ⬅️ Kembali ke Absensi
                </a>
            </div>
        </div>

        <div class="stats-card">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?= count($attendance_summary) ?></div>
                    <div class="stat-label">Total Karyawan</div>
                </div>
                <div class="stat-item" style="background: linear-gradient(135deg, #d4edda, #c3e6cb); border-color: #28a745;">
                    <div class="stat-number" style="color: #155724;"><?= count(array_filter($attendance_summary, function($row) { return $row['jumlah_hadir'] > 0; })) ?></div>
                    <div class="stat-label" style="color: #155724;">Karyawan Hadir</div>
                </div>
                <div class="stat-item" style="background: linear-gradient(135deg, #f8d7da, #f5c6cb); border-color: #dc3545;">
                    <div class="stat-number" style="color: #721c24;"><?= count(array_filter($attendance_summary, function($row) { return $row['jumlah_hadir'] == 0; })) ?></div>
                    <div class="stat-label" style="color: #721c24;">Karyawan Tidak Hadir</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= array_sum(array_column($attendance_summary, 'jumlah_hadir')) ?></div>
                    <div class="stat-label">Total Hari Hadir</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= round(array_sum(array_column($attendance_summary, 'jumlah_hadir')) / count($attendance_summary), 1) ?></div>
                    <div class="stat-label">Rata-rata Hadir/Orang</div>
                </div>
            </div>
        </div>

        <div class="filter-section">
            <div class="filter-controls">
                <div>
                    <input type="text" id="searchInput" class="search-input" placeholder="🔍 Cari karyawan (nama, NIP, NIK, PIN)...">
                </div>
                <div>
                    <select id="statusFilter" class="filter-select" onchange="filterTable()">
                        <option value="all">👥 Semua Karyawan</option>
                        <option value="hadir">✅ Karyawan Hadir</option>
                        <option value="tidak-hadir">❌ Karyawan Tidak Hadir</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="table-container">
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>PIN</th>
                        <th>Nama</th>
                        <th>NIP</th>
                        <th>NIK</th>
                        <th>Gender</th>
                        <th>Jabatan</th>
                        <th>Level</th>
                        <th>Bagian</th>
                        <th>Kategori Gaji</th>
                        <th>Departemen</th>
                        <th>TL</th>
                        <th>Jumlah Hadir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attendance_summary as $summary): ?>
                    <tr>
                        <td class="pin-column"><?= htmlspecialchars($summary['pin']) ?></td>
                        <td class="nama-column"><?= htmlspecialchars($summary['nama']) ?></td>
                        <td><?= htmlspecialchars($summary['nip']) ?></td>
                        <td><?= htmlspecialchars($summary['nik']) ?></td>
                        <td><?= htmlspecialchars($summary['jk']) ?></td>
                        <td><?= htmlspecialchars($summary['job_title']) ?></td>
                        <td><?= htmlspecialchars($summary['job_level']) ?></td>
                        <td><?= htmlspecialchars($summary['bagian']) ?></td>
                        <td><?= htmlspecialchars($summary['kategori_gaji']) ?></td>
                        <td><?= htmlspecialchars($summary['departemen']) ?></td>
                        <td><?= htmlspecialchars($summary['tl']) ?></td>
                        <td><span class="hadir-column"><?= htmlspecialchars($summary['jumlah_hadir']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Simple search and filter functionality
        function searchAndFilter() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
            const statusFilter = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('.summary-table tbody tr');

            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length < 12) return;

                // Get data from cells
                const pin = cells[0].textContent.toLowerCase();
                const nama = cells[1].textContent.toLowerCase();
                const nip = cells[2].textContent.toLowerCase();
                const nik = cells[3].textContent.toLowerCase();
                // Get jumlah hadir - handle the span element
                let jumlahHadir = 0;
                const hadirSpan = cells[11].querySelector('.hadir-column');
                if (hadirSpan) {
                    jumlahHadir = parseInt(hadirSpan.textContent.trim()) || 0;
                } else {
                    // Fallback: parse the cell content directly
                    const cellText = cells[11].textContent.trim();
                    const numberMatch = cellText.match(/\d+/);
                    jumlahHadir = numberMatch ? parseInt(numberMatch[0]) : 0;
                }

                // Check search
                const matchesSearch = !searchTerm ||
                    pin.includes(searchTerm) ||
                    nama.includes(searchTerm) ||
                    nip.includes(searchTerm) ||
                    nik.includes(searchTerm);

                // Check filter
                let matchesFilter = true;
                if (statusFilter === 'hadir') {
                    matchesFilter = jumlahHadir > 0;
                } else if (statusFilter === 'tidak-hadir') {
                    matchesFilter = jumlahHadir === 0;
                }

                // Show/hide row
                row.style.display = (matchesSearch && matchesFilter) ? '' : 'none';
            });
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const statusFilter = document.getElementById('statusFilter');

            if (searchInput) {
                searchInput.addEventListener('input', searchAndFilter);
                searchInput.addEventListener('keyup', searchAndFilter);
            }

            if (statusFilter) {
                statusFilter.addEventListener('change', searchAndFilter);
            }

            // Initial run
            searchAndFilter();
        });
    </script>

    <script>
        function exportToExcel() {
            // Create a form to submit export request
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '?page=recap-attends';

            // Add the export button
            const exportBtn = document.createElement('input');
            exportBtn.type = 'hidden';
            exportBtn.name = 'exportBtn';
            exportBtn.value = '1';
            form.appendChild(exportBtn);

            // Add selected users
            <?php foreach ($selected_users as $user): ?>
            const userInput = document.createElement('input');
            userInput.type = 'hidden';
            userInput.name = 'selected_users[]';
            userInput.value = '<?= htmlspecialchars($user) ?>';
            form.appendChild(userInput);
            <?php endforeach; ?>

            // Add dates
            const tanggalDari = document.createElement('input');
            tanggalDari.type = 'hidden';
            tanggalDari.name = 'tanggal_dari';
            tanggalDari.value = '<?= htmlspecialchars($tanggal_dari) ?>';
            form.appendChild(tanggalDari);

            const tanggalSampai = document.createElement('input');
            tanggalSampai.type = 'hidden';
            tanggalSampai.name = 'tanggal_sampai';
            tanggalSampai.value = '<?= htmlspecialchars($tanggal_sampai) ?>';
            form.appendChild(tanggalSampai);

            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>
