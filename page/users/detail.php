<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/../../includes/config.php';
require __DIR__ . '/../../includes/functions.php';

// Handle export CSV
if (isset($_POST['exportBtn'])) {
    ob_start(); // Start output buffering
    
    try {
        if (empty($_POST['selected_users']) || empty($_POST['tanggal_dari']) || empty($_POST['tanggal_sampai'])) {
            throw new Exception('Data tidak lengkap untuk export');
        }
        
        $selected_users = $_POST['selected_users'];
        $tanggal_dari = $_POST['tanggal_dari'];
        $tanggal_sampai = $_POST['tanggal_sampai'];
        
        // Ambil data user dan absensi
        $users = getUsers($ip, $port, $key);
        $all_attendance = getAttendanceRange($ip, $port, $key, $tanggal_dari, $tanggal_sampai, $users);
        
        // Filter data absensi
        $filtered_attendance = array_filter($all_attendance, function ($record) use ($selected_users) {
            return in_array($record['pin'], $selected_users);
        });
        
        // Tambahkan data dari database
        $pins = array_map('intval', $selected_users);
        $pin_list = implode(',', $pins);
        $nip_data = [];
        
        if (!empty($pins)) {
            $result = mysqli_query($mysqli, "SELECT pin, nip, bagian, nik, jk, job_title, job_level, bagian, departemen FROM users WHERE pin IN ($pin_list)");
            if (!$result) {
                throw new Exception('Error querying database: ' . mysqli_error($mysqli));
            }
            
            while ($row = mysqli_fetch_assoc($result)) {
                $nip_data[$row['pin']] = [
                    'nip' => $row['nip'],
                    'nik' => $row['nik'],
                    'jk' => $row['jk'],
                    'job_title' => $row['job_title'],
                    'job_level' => $row['job_level'],
                    'bagian' => $row['bagian'],
                    'departemen' => $row['departemen']
                ];
            }
        }
        
        // Tambahkan data database ke setiap record
        foreach ($filtered_attendance as &$record) {
            if (isset($nip_data[$record['pin']])) {
                $record = array_merge($record, $nip_data[$record['pin']]);
            }
        }
        unset($record);
        
        ob_end_clean(); // Clear buffer before export
        
        // Generate filename
        $filename = sprintf(
            'absensi_%s_to_%s.csv',
            date('Y-m-d', strtotime($tanggal_dari)),
            date('Y-m-d', strtotime($tanggal_sampai))
        );
        
        exportToCsv($filtered_attendance, $filename);
        
    } catch (Exception $e) {
        ob_end_clean();
        header('HTTP/1.1 500 Internal Server Error');
        echo "Error generating export: " . $e->getMessage();
        exit;
    }
}

require __DIR__ . '/../../includes/header.php';

// Debug untuk melihat data yang diterima
error_log('POST Data: ' . print_r($_POST, true));

// Cek apakah form sudah disubmit
if (!isset($_POST['detailBtn']) || empty($_POST['selected_users']) || empty($_POST['tanggal_dari']) || empty($_POST['tanggal_sampai'])) {
    header('Location: ?page=users');
    exit;
}

$selected_users = $_POST['selected_users'];
$tanggal_dari = $_POST['tanggal_dari'];
$tanggal_sampai = $_POST['tanggal_sampai'];

// Ambil data user dan absensi
$users = getUsers($ip, $port, $key);
$all_attendance = getAttendanceRange($ip, $port, $key, $tanggal_dari, $tanggal_sampai, $users);

// Filter data absensi hanya untuk user yang dipilih
$filtered_attendance = array_filter($all_attendance, function ($record) use ($selected_users) {
    return in_array($record['pin'], $selected_users);
});

// Ambil NIP & Bagian dari database
$pins = array_map('intval', $selected_users);
$pin_list = implode(',', $pins);

function getNamaHari($date)
{
    $hari = array(
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu'
    );

    return $hari[date('l', strtotime($date))];
}

$nip_data = [];
if (!empty($pins)) {
    $result = mysqli_query($mysqli, "SELECT pin, nip, bagian,nik, jk, job_title, job_level, bagian, departemen FROM users WHERE pin IN ($pin_list)");
    while ($row = mysqli_fetch_assoc($result)) {
        $nip_data[$row['pin']] = [
            'nip' => $row['nip'],
            'nik' => $row['nik'],
            'jk' => $row['jk'],
            'job_title' => $row['job_title'],
            'job_level' => $row['job_level'],
            'bagian' => $row['bagian'],
            'departemen' => $row['departemen']
        ];
    }
}

// Tambahkan NIP & Bagian ke setiap record
foreach ($filtered_attendance as &$record) {
    if (isset($nip_data[$record['pin']])) {
        $record['nip'] = $nip_data[$record['pin']]['nip'];
        $record['nik'] = $nip_data[$record['pin']]['nik'];
        $record['jk'] = $nip_data[$record['pin']]['jk'];
        $record['job_title'] = $nip_data[$record['pin']]['job_title'];
        $record['job_level'] = $nip_data[$record['pin']]['job_level'];
        $record['bagian'] = $nip_data[$record['pin']]['bagian'];
        $record['departemen'] = $nip_data[$record['pin']]['departemen'];
    } else {
        $record['nip'] = '-';
        $record['nik'] = '-';
        $record['jk'] = '-';
        $record['job_title'] = '-';
        $record['job_level'] = '-';
        $record['bagian'] = '-';
        $record['departemen'] = '-';
    }
}
unset($record);

// Dapatkan statistik
$stats = getAttendanceStats($filtered_attendance);

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Detail Absensi</title>
    <style>
        :root {
            --primary-1: #45caff;
            --primary-2: #5c9dff;
            --accent-green: #28a745;
            --accent-red: #dc3545;
            --muted: #6c757d;
            --card-bg: #ffffff;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f6f9ff;
            color: #1f2937;
        }

        /* Cards / stats */
        .stats-container {
            display: flex;
            gap: 16px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }
        .status-in { color: #28a745; font-weight: bold; }
        .status-out { color: #dc3545; font-weight: bold; }
        .stat-box {
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.9), var(--card-bg));
            border: 1px solid rgba(26, 115, 232, 0.06);
            padding: 14px;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.06);
            min-width: 160px;
            flex: 1 1 150px;
        }

        .stat-number {
            font-size: 22px;
            font-weight: 700;
            color: var(--primary-2);
        }

        .stat-sub {
            font-size: 12px;
            color: var(--muted);
            margin-top: 6px;
        }

        /* Buttons (rounded + bubble ornaments) */
        .btn {
            padding: 8px 14px;
            cursor: pointer;
            border: none;
            border-radius: 999px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: all .18s ease;
            position: relative;
            overflow: hidden;
        }

        .btn::before,
        .btn::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
            opacity: .12;
            transition: transform .25s ease, opacity .25s ease;
        }

        .btn::before {
            width: 80px;
            height: 80px;
            right: -30px;
            top: -30px;
            background: radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.35), rgba(255, 255, 255, 0));
            transform: scale(.8);
        }

        .btn::after {
            width: 50px;
            height: 50px;
            left: -20px;
            bottom: -20px;
            background: radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.18), rgba(255, 255, 255, 0));
            transform: scale(.8);
        }

        .btn:active {
            transform: translateY(1px) scale(.998);
        }

        .btn-primary {
            background: linear-gradient(90deg, var(--primary-1), var(--primary-2));
            color: #fff;
            box-shadow: 0 8px 20px rgba(92, 157, 255, 0.18);
        }

        .btn-primary:hover {
            box-shadow: 0 12px 28px rgba(92, 157, 255, 0.22);
        }

        .btn-success {
            background: linear-gradient(90deg, #34c759, #28a745);
            color: #fff;
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.12);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #111827;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.04);
        }

        /* Spinner inside button */
        .btn .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: rgba(255, 255, 255, 0.95);
            border-radius: 50%;
            animation: spin .8s linear infinite;
            flex: 0 0 16px;
        }

        .btn-primary.loading .spinner,
        .btn-success.loading .spinner {
            display: inline-block;
        }

        .btn .emoji {
            transition: transform .18s ease;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Table container - scrollable like halaman user */
        .table-container {
            max-height: 520px;
            overflow-y: auto;
            border: 1px solid #e6eefc;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 6px 22px rgba(15, 23, 42, 0.04);
            padding: 8px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            min-width: 1000px;
            /* allow horizontal scroll on small screens */
        }

        th,
        td {
            border-bottom: 1px solid #f1f5f9;
            padding: 10px 12px;
            text-align: left;
            font-size: 13px;
        }

        thead th {
            position: sticky;
            top: 0;
            background: linear-gradient(180deg, #fff, #f8fbff);
            z-index: 5;
        }

        tr:nth-child(even) td {
            background: #fcfdff;
        }

        /* responsive smaller table on narrow viewport */
        @media (max-width:900px) {
            .table-container {
                padding: 6px;
                max-height: 420px;
            }

            table {
                min-width: 900px;
            }
        }
    </style>
    <script>
        function searchUsers() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const tableRows = document.querySelectorAll('tbody tr');
            let visibleCount = 0;

            tableRows.forEach(row => {
                const pin = row.cells[1].textContent.toLowerCase();
                const nama = row.cells[2].textContent.toLowerCase();
                const checkbox = row.querySelector('input[type="checkbox"]');

                if (pin.includes(searchInput) || nama.includes(searchInput)) {
                    row.style.display = '';
                    row.classList.remove('hidden');
                    if (checkbox) checkbox.classList.remove('hidden');
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                    row.classList.add('hidden');
                    if (checkbox) {
                        checkbox.classList.add('hidden');
                        checkbox.checked = false; // Uncheck hidden items
                    }
                }
            });

            // Update counter
            document.getElementById('userCount').textContent = visibleCount;

            // Reset "Pilih Semua" checkbox
            const selectAll = document.querySelector('input[onchange="toggleAll(this)"]');
            if (selectAll) selectAll.checked = false;
        }

        // Auto search saat mengetik
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('searchInput').addEventListener('input', searchUsers);
        });</script>
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
            <div class="stat-sub">Total Record</div>
        </div>
        <div class="stat-box">
            <div class="stat-number" style="color: var(--accent-green);"><?= $stats['total_in'] ?></div>
            <div class="stat-sub">Masuk (IN)</div>
        </div>
        <div class="stat-box">
            <div class="stat-number" style="color: var(--accent-red);"><?= $stats['total_out'] ?></div>
            <div class="stat-sub">Keluar (OUT)</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?= count($stats['by_user']) ?></div>
            <div class="stat-sub">User Aktif</div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div style="margin-bottom: 15px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <a href="?page=users" class="btn btn-secondary">⬅️ Kembali</a>

        <?php if (!empty($filtered_attendance)): ?>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="detailBtn" value="1">
                <?php foreach ($selected_users as $user): ?>
                    <input type="hidden" name="selected_users[]" value="<?= htmlspecialchars($user) ?>">
                <?php endforeach; ?>
                <input type="hidden" name="tanggal_dari" value="<?= htmlspecialchars($tanggal_dari) ?>">
                <input type="hidden" name="tanggal_sampai" value="<?= htmlspecialchars($tanggal_sampai) ?>">
                <button type="submit" name="exportBtn" class="btn btn-success">
                    <span class="spinner" style="display:none"></span>
                    📥 Export CSV
                </button>
            </form>
        <?php endif; ?>
    </div>
     <!-- Tabel Data Absensi (scrollable) -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th style="display: none;">PIN</th> <!-- Sembunyikan kolom PIN -->
                    <th>NIP</th>
                    <th>Nama</th>
                    <th>NIK</th>
                    <th>L/P</th>
                    <th>Job Title</th>
                    <th>Job Level</th>
                    <th>Bagian</th>
                    <th>Departemen</th>
                    <th>Tanggal</th>
                    <th>Waktu</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <!-- ...existing rows generation code... -->
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
                        $found = array_filter($filtered_attendance, function ($item) use ($pin, $tanggal_str) {
                            return $item['pin'] == $pin && date('Y-m-d', strtotime($item['datetime'])) == $tanggal_str;
                        });

                        if (!empty($found)) {
                            foreach ($found as $record) {
                                $tanggal = date('d/m/Y', strtotime($record['datetime']));
                                $hari = getNamaHari($record['datetime']);
                                echo "<tr>
                                        <td>" . $no++ . "</td>
                                        <td style='display: none;'>{$record['pin']}</td>
                                        <td>{$record['nip']}</td>
                                        <td>{$record['nama']}</td>
                                        <td>{$record['nik']}</td>
                                        <td>{$record['jk']}</td>
                                        <td>{$record['job_title']}</td>
                                        <td>{$record['job_level']}</td>
                                        <td>{$record['bagian']}</td>
                                        <td>{$record['departemen']}</td>
                                        <td>{$hari}, {$tanggal}</td>
                                        <td>" . date('H:i:s', strtotime($record['datetime'])) . "</td>
                                        <td><span class='" . ($record['status'] == 'IN' ? 'status-in' : 'status-out') . "'>{$record['status']}</span></td>
                                    </tr>";
                            }
                        } else {
                            // Update bagian record yang tidak ada absensi
                            $tanggal = date('d/m/Y', strtotime($tanggal_str));
                            $hari = getNamaHari($tanggal_str);
                            echo "<tr style='background:#fff8e1;'>
                                <td>" . $no++ . "</td>
                                <td style='display: none;'>{$pin}</td>
                                <td>" . ($nip_data[$pin]['nip'] ?? '-') . "</td>
                                <td>" . ($users[$pin] ?? '-') . "</td>
                                <td>" . ($nip_data[$pin]['nik'] ?? '-') . "</td>
                                <td>" . ($nip_data[$pin]['jk'] ?? '-') . "</td>
                                <td>" . ($nip_data[$pin]['job_title'] ?? '-') . "</td>
                                <td>" . ($nip_data[$pin]['job_level'] ?? '-') . "</td>
                                <td>" . ($nip_data[$pin]['bagian'] ?? '-') . "</td>
                                <td>" . ($nip_data[$pin]['departemen'] ?? '-') . "</td>
                                <td>{$hari}, {$tanggal}</td>
                                <td>-</td>
                                <td class='status-none'>Tidak Absen</td>
                            </tr>";
                        }
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</body>

</html>