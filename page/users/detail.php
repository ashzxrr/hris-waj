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
            $result = mysqli_query($mysqli, "SELECT pin, nip, nama, bagian, nik, jk, job_title, job_level, bagian, departemen FROM users WHERE pin IN ($pin_list)");
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
    $result = mysqli_query($mysqli, "SELECT pin, nip, nama, bagian, nik, jk, job_title, job_level, bagian, departemen FROM users WHERE pin IN ($pin_list)");
    while ($row = mysqli_fetch_assoc($result)) {
        $nip_data[$row['pin']] = [
            'nip' => $row['nip'],
            'nama' => $row['nama'],
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

// Load existing absence notes for selected pins and date range
$absence_notes = [];
if (!empty($pins)) {
    $start = mysqli_real_escape_string($mysqli, $tanggal_dari);
    $end = mysqli_real_escape_string($mysqli, $tanggal_sampai);
    $pin_list_esc = implode(',', array_map('intval', $pins));
    $q = "SELECT pin, date, code FROM absence_notes WHERE pin IN ($pin_list_esc) AND date BETWEEN '$start' AND '$end'";
    $res = mysqli_query($mysqli, $q);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $absence_notes[$r['pin']][$r['date']] = $r['code'];
        }
    }
}

// Build attendance index for quick lookup: attendance_index[pin][Y-m-d] = true
$attendance_index = [];
foreach ($filtered_attendance as $rec) {
    $d = date('Y-m-d', strtotime($rec['datetime']));
    $p = $rec['pin'];
    $attendance_index[$p][$d] = true;
}

// Initialize counters
$counts_codes = ['S' => 0, 'A' => 0, 'I' => 0];
$total_no_absen = 0; // total days without attendance (excluding Sundays)
$total_present_days = 0; // total days with any IN/OUT (includes Sundays)

// Per-user summary: total present days per pin, no-absen count and codes list
$per_user = [];
foreach ($selected_users as $pin) {
    $per_user[$pin] = [
        'nip' => $nip_data[$pin]['nip'] ?? '-',
        'nama' => $nip_data[$pin]['nama'] ?? '-',
        'present' => 0,
        'no_absen' => 0,
        'A' => 0,
        'S' => 0,
        'I' => 0,
        'notes' => []
    ];
}

// Aggregate per-user over the period
$agg_period = new DatePeriod(new DateTime($tanggal_dari), new DateInterval('P1D'), (new DateTime($tanggal_sampai))->modify('+1 day'));
foreach ($selected_users as $pin) {
    foreach ($agg_period as $d) {
        $ds = $d->format('Y-m-d');
        $is_sunday = (date('N', strtotime($ds)) == 7);
        $has = isset($attendance_index[$pin]) && !empty($attendance_index[$pin][$ds]);

        if ($has) {
            $per_user[$pin]['present']++;
            $total_present_days++;
        } else {
            // don't count Sundays as no-absen
            if (!$is_sunday) {
                $per_user[$pin]['no_absen']++;
                $total_no_absen++;
                $code = $absence_notes[$pin][$ds] ?? '';
                if ($code && isset($per_user[$pin][$code])) {
                    $per_user[$pin][$code]++;
                    $counts_codes[$code]++;
                }
                $per_user[$pin]['notes'][] = ['date' => $ds, 'code' => $code];
            }
        }
    }
}

// Total IN from stats (deprecated) — we now use total_present_days if needed
$total_in_count = $total_present_days;

// Handle saving absence notes (S/A/I)
if (isset($_POST['save_notes'])) {
    $absence_notes_post = $_POST['absence_notes'] ?? [];

    // ensure table exists
    $create_sql = "CREATE TABLE IF NOT EXISTS absence_notes (
        pin VARCHAR(32) NOT NULL,
        date DATE NOT NULL,
        code VARCHAR(4) DEFAULT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY(pin,date)
    ) DEFAULT CHARSET=utf8mb4 ENGINE=InnoDB";
    mysqli_query($mysqli, $create_sql);

    foreach ($absence_notes_post as $pin => $dates) {
        foreach ($dates as $date => $code) {
            $pin_esc = mysqli_real_escape_string($mysqli, $pin);
            $date_esc = mysqli_real_escape_string($mysqli, $date);
            $code_esc = mysqli_real_escape_string($mysqli, $code);

            // Insert or update
            $sql = "INSERT INTO absence_notes (pin, date, code, updated_at) VALUES ('{$pin_esc}','{$date_esc}','{$code_esc}', NOW()) 
                    ON DUPLICATE KEY UPDATE code=VALUES(code), updated_at=VALUES(updated_at)";
            mysqli_query($mysqli, $sql);
        }
    }

    $save_msg = 'Keterangan absen disimpan.';
}

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

        .status-in {
            color: #28a745;
            font-weight: bold;
        }

        .status-out {
            color: #dc3545;
            font-weight: bold;
        }

        /* Overtime text - highlight with orange */
        .status-overtime {
            color: #ff8c00;
            font-weight: 700;
        }

        /* Baris "Tidak Absen" (kecuali hari Minggu) - kuning/oranye lembut */
        .no-absen-row td {
            background: linear-gradient(90deg, #fff9e6, #fff3cc);
        }

        /* Teks untuk status "Tidak Absen" */
        .status-none {
            color: #b45309;
            font-weight: 600;
        }

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

        /* Styled select for keterangan column (modern) */
               /* Styled select for keterangan column */
        .select-ket {
            padding: 6px 8px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #fff;
            font-weight: 600;
            color: #111827;
        }

        .select-ket:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.08);
            border-color: #6366f1;
        }
        /* Sunday label */
        .label-sunday {
            color: #dc2626; /* red */
            font-weight: 700;
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

    <!-- Per-user summary -->
    <div style="margin-top:12px; margin-bottom:14px;">
        <h3>Ringkasan Keterangan (periode)</h3>
        <div style="overflow:auto; background:#fff; padding:8px; border-radius:8px; border:1px solid #eef2ff;">
            <table style="width:100%; border-collapse:collapse; min-width:800px;">
                <thead>
                    <tr>
                        <th style="padding:8px; text-align:left">PIN</th>
                        <th style="padding:8px; text-align:left">NIP</th>
                        <th style="padding:8px; text-align:left">Nama</th>
                        <th style="padding:8px; text-align:left">Total Absen (hadir hari)</th>
                        <th style="padding:8px; text-align:left">No Absen</th>
                        <th style="padding:8px; text-align:left">A</th>
                        <th style="padding:8px; text-align:left">S</th>
                        <th style="padding:8px; text-align:left">I</th>
                        <th style="padding:8px; text-align:left">Keterangan (tanggal:code)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($per_user as $pin => $p): ?>
                        <tr>
                            <td style="padding:8px"><?= htmlspecialchars($pin) ?></td>
                            <td style="padding:8px"><?= htmlspecialchars($p['nip']) ?></td>
                            <td style="padding:8px"><?= htmlspecialchars($p['nama']) ?></td>
                            <td style="padding:8px"><?= $p['present'] ?></td>
                            <td style="padding:8px"><?= $p['no_absen'] ?></td>
                            <td style="padding:8px"><?= $p['A'] ?></td>
                            <td style="padding:8px"><?= $p['S'] ?></td>
                            <td style="padding:8px"><?= $p['I'] ?></td>
                            <td style="padding:8px">
                                <?php foreach ($p['notes'] as $n): if ($n['code']): ?>
                                    <?= $n['date'] ?>:<?= $n['code'] ?>
                                <?php endif; endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Statistik -->
   <!-- <div class="stats-container">
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
    </div>-->

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
    <form method="POST">
        <input type="hidden" name="detailBtn" value="1">
        <?php foreach ($selected_users as $user): ?>
            <input type="hidden" name="selected_users[]" value="<?= htmlspecialchars($user) ?>">
        <?php endforeach; ?>
        <input type="hidden" name="tanggal_dari" value="<?= htmlspecialchars($tanggal_dari) ?>">
        <input type="hidden" name="tanggal_sampai" value="<?= htmlspecialchars($tanggal_sampai) ?>">

        <div style="margin-bottom:10px; display:flex; gap:8px; align-items:center">
            <button type="submit" name="save_notes" class="btn btn-primary">💾 Simpan Keterangan</button>
            <?php if (!empty($save_msg)): ?><span style="color:green; font-weight:600; margin-left:8px"><?= $save_msg ?></span><?php endif; ?>
        </div>

        <div class="table-container">
            <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th style="display: none;">PIN</th> <!-- Sembunyikan kolom PIN -->
                    <th>NIP</th>
                    <th>Nama</th>
                    <th>L/P</th>
                    <th>Job Title</th>
                    <th>Job Level</th>
                    <th>Bagian</th>
                    <th>Departemen</th>
                    <th>Tanggal</th>
                    <th>In</th>
                    <th>Out</th>
                    <th>Overtime</th>
                    <th>Keterangan</th>
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
                        $records_on_date = array_filter($filtered_attendance, function ($item) use ($pin, $tanggal_str) {
                            return $item['pin'] == $pin && date('Y-m-d', strtotime($item['datetime'])) == $tanggal_str;
                        });

                        // Collect IN and OUT times
                        $in_times = [];
                        $out_times = [];
                        foreach ($records_on_date as $record) {
                            $ts = strtotime($record['datetime']);
                            if (strtoupper($record['status']) === 'IN') {
                                $in_times[] = $ts;
                            } else {
                                $out_times[] = $ts;
                            }
                        }

                        // Determine display values
                        $in_ts = !empty($in_times) ? min($in_times) : null;
                        $out_ts = !empty($out_times) ? max($out_times) : null;

                        $in_display = $in_ts ? date('H.i', $in_ts) : '-';
                        $out_display = $out_ts ? date('H.i', $out_ts) : '-';

                        // Overtime: minutes after 16:30
                        if ($out_ts) {
                            $threshold = strtotime($tanggal_str . ' 16:30:00');
                            $overtime_minutes = $out_ts > $threshold ? floor(($out_ts - $threshold) / 60) : 0;
                            $overtime_display = $overtime_minutes > 0 ? $overtime_minutes . ' menit' : '----';
                        } else {
                            $overtime_display = '----';
                        }

                        if (!empty($records_on_date)) {
                            $tanggal = date('d/m/Y', strtotime($tanggal_str));
                            $hari = getNamaHari($tanggal_str);
                            // Use common user/database info from first available record or fallback to nip_data/users
                            $sample = reset($records_on_date);
                            $nip = $sample['nip'] ?? ($nip_data[$pin]['nip'] ?? '-');
                            $nama = $nip_data[$pin]['nama'] ?? ($sample['nama'] ?? '-');
                            $jk = $sample['jk'] ?? ($nip_data[$pin]['jk'] ?? '-');
                            $job_title = $sample['job_title'] ?? ($nip_data[$pin]['job_title'] ?? '-');
                            $job_level = $sample['job_level'] ?? ($nip_data[$pin]['job_level'] ?? '-');
                            $bagian = $sample['bagian'] ?? ($nip_data[$pin]['bagian'] ?? '-');
                            $departemen = $sample['departemen'] ?? ($nip_data[$pin]['departemen'] ?? '-');

                            // Prepare overtime cell with styling
                            $overtime_cell = $overtime_display !== '----' ? "<span class='status-overtime'>{$overtime_display}</span>" : $overtime_display;

                            echo "<tr>
                                    <td>" . $no++ . "</td>
                                    <td style='display: none;'>{$pin}</td>
                                    <td>" . htmlspecialchars($nip) . "</td>
                                    <td>" . htmlspecialchars($nama) . "</td>
                                    <td>" . htmlspecialchars($jk) . "</td>
                                    <td>" . htmlspecialchars($job_title) . "</td>
                                    <td>" . htmlspecialchars($job_level) . "</td>
                                    <td>" . htmlspecialchars($bagian) . "</td>
                                    <td>" . htmlspecialchars($departemen) . "</td>
                                    <td>{$hari}, {$tanggal}</td>
                                    <td><span class='status-in'>{$in_display}</span></td>
                                    <td><span class='status-out'>{$out_display}</span></td>
                                    <td>{$overtime_cell}</td>
                                </tr>";
                        } else {
                            // No records on this date
                            $tanggal = date('d/m/Y', strtotime($tanggal_str));
                            $hari = getNamaHari($tanggal_str);
                            // jika bukan hari Minggu, beri warna kuning-oranye pada baris
                            $is_sunday = ($hari === 'Minggu');
                            $row_class = $is_sunday ? '' : 'no-absen-row';

                            // get existing code if any
                            $existing_code = $absence_notes[$pin][$tanggal_str] ?? '';

                            // render select only if not Sunday
                            if ($is_sunday) {
                                $keterangan_html = '<span class="label-sunday">Minggu</span>';
                            } else {
                                $keterangan_html = '<select name="absence_notes[' . htmlspecialchars($pin) . '][' . $tanggal_str . ']" class="select-ket">'
                                    . '<option value="">-</option>'
                                    . '<option value="S"' . ($existing_code === 'S' ? ' selected' : '') . '>S</option>'
                                    . '<option value="A"' . ($existing_code === 'A' ? ' selected' : '') . '>A</option>'
                                    . '<option value="I"' . ($existing_code === 'I' ? ' selected' : '') . '>I</option>'
                                    . '</select>';
                            }

                            echo "<tr class='" . $row_class . "'>
                                <td>" . $no++ . "</td>
                                <td style='display: none;'>{$pin}</td>
                                <td>" . ($nip_data[$pin]['nip'] ?? '-') . "</td>
                                <td>" . ($nip_data[$pin]['nama'] ?? '-') . "</td>
                                <td>" . ($nip_data[$pin]['jk'] ?? '-') . "</td>
                                <td>" . ($nip_data[$pin]['job_title'] ?? '-') . "</td>
                                <td>" . ($nip_data[$pin]['job_level'] ?? '-') . "</td>
                                <td>" . ($nip_data[$pin]['bagian'] ?? '-') . "</td>
                                <td>" . ($nip_data[$pin]['departemen'] ?? '-') . "</td>
                                <td>{$hari}, {$tanggal}</td>
                                <td>-</td>
                                <td>-</td>
                                <td>----</td>
                                <td>" . $keterangan_html . "</td>
                            </tr>";
                        }
                    }
                }
                ?>
            </tbody>
            </table>
        </div>
        </form>

        <?php if (!empty($save_msg)): ?>
            <script>
                (function(){
                    var msg = <?= json_encode($save_msg) ?>;
                    function doReload(){
                        // find the form used for details (the closest form)
                        var form = document.querySelector('form');
                        if (!form) return;
                        // remove/disable save button name to avoid re-triggering save on submit
                        var saveBtn = form.querySelector('button[name="save_notes"]');
                        if (saveBtn) saveBtn.removeAttribute('name');
                        // submit form to reload data (will POST without save_notes)
                        form.submit();
                    }

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: msg,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(function(){ setTimeout(doReload, 100); });
                    } else {
                        alert(msg);
                        setTimeout(doReload, 100);
                    }
                })();
            </script>
        <?php endif; ?>
</body>

</html>