<?php
$ip = "192.168.0.102";
$port = 80;
$key = 0;

// =========================
// 1. Ambil Data User dengan Mapping yang Benar
// =========================
function getUserList($ip, $port, $key) {
    $soap_request = "<GetAllUserInfo>
                        <ArgComKey xsi:type=\"xsd:integer\">{$key}</ArgComKey>
                     </GetAllUserInfo>";
                     
    $connect = fsockopen($ip, $port, $errno, $errstr, 3);
    $userMapping = [];
    
    if ($connect) {
        $newLine = "\r\n";
        fputs($connect, "POST /iWsService HTTP/1.0" . $newLine);
        fputs($connect, "Content-Type: text/xml" . $newLine);
        fputs($connect, "Content-Length: " . strlen($soap_request) . $newLine . $newLine);
        fputs($connect, $soap_request . $newLine);
        
        $buffer = "";
        while ($line = fgets($connect, 1024)) {
            $buffer .= $line;
        }
        fclose($connect);
        
        // Parse semua user dengan pattern yang benar
        preg_match_all('/<Row>.*?<PIN>(.*?)<\/PIN>.*?<Name>(.*?)<\/Name>.*?<PIN2>(.*?)<\/PIN2>.*?<\/Row>/s', $buffer, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $internal_pin = $match[1];    // PIN internal (untuk log absensi)
            $name = $match[2];            // Nama user
            $pin2 = $match[3];            // PIN2 (mungkin ID Number sebenarnya)
            
            // Buat mapping dengan berbagai kemungkinan
            $userMapping[$internal_pin] = $name;  // PIN internal -> Nama
            if (!empty($pin2) && $pin2 != $internal_pin) {
                $userMapping[$pin2] = $name;      // PIN2 -> Nama (jika berbeda)
            }
        }
        
        // Debug: tampilkan beberapa mapping untuk verifikasi
        if (count($userMapping) > 0) {
            $sample = array_slice($userMapping, 0, 5, true);
            // echo "<!-- DEBUG Mapping: " . print_r($sample, true) . " -->\n";
        }
    }
    
    return $userMapping;
}

// =========================
// 2. Ambil Data Absensi
// =========================
function getAttLog($ip, $port, $key, $filterDate) {
    $soap_request = "<GetAttLog>
                        <ArgComKey xsi:type=\"xsd:integer\">{$key}</ArgComKey>
                        <Arg><PIN xsi:type=\"xsd:integer\">All</PIN></Arg>
                     </GetAttLog>";
                     
    $connect = fsockopen($ip, $port, $errno, $errstr, 3);
    $logList = [];
    
    if ($connect) {
        $newLine = "\r\n";
        fputs($connect, "POST /iWsService HTTP/1.0" . $newLine);
        fputs($connect, "Content-Type: text/xml" . $newLine);
        fputs($connect, "Content-Length: " . strlen($soap_request) . $newLine . $newLine);
        fputs($connect, $soap_request . $newLine);
        
        $buffer = "";
        while ($line = fgets($connect, 1024)) {
            $buffer .= $line;
        }
        fclose($connect);
        
        preg_match_all('/<Row>.*?<PIN>(.*?)<\/PIN>.*?<DateTime>(.*?)<\/DateTime>.*?<\/Row>/s', $buffer, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $pin = $match[1];
            $datetime = $match[2];
            
            // Filter hanya tanggal yang diminta
            if (!empty($datetime) && strpos($datetime, $filterDate) === 0) {
                $logList[] = [
                    'pin' => $pin,
                    'datetime' => $datetime
                ];
            }
        }
    }
    
    return $logList;
}

// =========================
// 3. Fungsi Tambahan: Ambil User Detail untuk Cross-Check
// =========================
function getUserDetail($ip, $port, $key) {
    $soap_request = "<GetUserInfo>
                        <ArgComKey xsi:type=\"xsd:integer\">{$key}</ArgComKey>
                        <Arg><PIN xsi:type=\"xsd:integer\">All</PIN></Arg>
                     </GetUserInfo>";
                     
    $connect = fsockopen($ip, $port, $errno, $errstr, 3);
    $detailMapping = [];
    
    if ($connect) {
        $newLine = "\r\n";
        fputs($connect, "POST /iWsService HTTP/1.0" . $newLine);
        fputs($connect, "Content-Type: text/xml" . $newLine);
        fputs($connect, "Content-Length: " . strlen($soap_request) . $newLine . $newLine);
        fputs($connect, $soap_request . $newLine);
        
        $buffer = "";
        while ($line = fgets($connect, 1024)) {
            $buffer .= $line;
        }
        fclose($connect);
        
        // Parse dengan pattern yang sama
        preg_match_all('/<Row>.*?<PIN>(.*?)<\/PIN>.*?<Name>(.*?)<\/Name>.*?<PIN2>(.*?)<\/PIN2>.*?<\/Row>/s', $buffer, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $internal_pin = $match[1];
            $name = $match[2];
            $pin2 = $match[3];
            
            $detailMapping[$internal_pin] = [
                'name' => $name,
                'pin2' => $pin2,
                'display_id' => $pin2 ?: $internal_pin
            ];
        }
    }
    
    return $detailMapping;
}

// =========================
// 4. Proses Data (Optimasi untuk menampilkan user yang tidak hadir juga)
// =========================
$today = date('Y-m-d');
$userList = getUserList($ip, $port, $key);
$userDetail = getUserDetail($ip, $port, $key); 
$logs = getAttLog($ip, $port, $key, $today);

// Buat array logs per PIN untuk mudah lookup
$logsByPin = [];
foreach ($logs as $log) {
    $pin = $log['pin'];
    // Jika PIN sudah ada, kita simpan waktu absen pertama saja (atau bisa disesuaikan)
    if (!isset($logsByPin[$pin])) {
        $logsByPin[$pin] = $log['datetime'];
    } else {
        // bisa juga simpan log paling awal atau paling akhir, misal:
        if ($log['datetime'] < $logsByPin[$pin]) {
            $logsByPin[$pin] = $log['datetime'];
        }
    }
}

// Gabungkan data user dengan absensi hari ini
$combinedData = [];
foreach ($userList as $pin => $name) {
    $displayId = isset($userDetail[$pin]) ? $userDetail[$pin]['display_id'] : $pin;
    if (isset($logsByPin[$pin])) {
        // User hadir
        $combinedData[] = [
            'pin' => $pin,
            'displayId' => $displayId,
            'nama' => $name,
            'datetime' => $logsByPin[$pin],
            'status' => 'HADIR'
        ];
    } else {
        // User tidak hadir
        $combinedData[] = [
            'pin' => $pin,
            'displayId' => $displayId,
            'nama' => $name,
            'datetime' => '-',
            'status' => 'TIDAK HADIR'
        ];
    }
}

// Hitung statistik
$totalUsers = count($userList);
$totalLogs = count($logs);
$totalHadir = count(array_filter($combinedData, fn($d) => $d['status'] === 'HADIR'));
$totalTidakHadir = $totalUsers - $totalHadir;
?>

<!-- Bagian HTML & JavaScript akan diganti untuk gunakan $combinedData -->
<!DOCTYPE html>
<html>
<head>
    <title>Data Absensi Hari Ini - Fixed</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 6px; text-align: center; }
        .stat-number { font-size: 2em; font-weight: bold; color: #495057; }
        .stat-label { color: #6c757d; font-size: 0.9em; }
        .success { color: #28a745; } .danger { color: #dc3545; } .warning { color: #ffc107; } .info { color: #17a2b8; }
        
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 15px; }
        .alert-info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        .alert-warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background: #495057; color: white; padding: 12px; text-align: left; font-weight: 600; }
        td { padding: 10px; border-bottom: 1px solid #dee2e6; }
        tr:nth-child(even) { background: #f8f9fa; }
        tr:hover { background: #e9ecef; }
        
        .status-ok { background: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 0.85em; }
        .status-error { background: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 4px; font-size: 0.85em; }
        
        .debug-section { margin-top: 30px; padding: 15px; background: #f1f3f4; border-radius: 6px; }
        .toggle-btn { background: #007bff; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; }
        .hidden { display: none; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h2>📋 Data Absensi Tanggal: <?= $today ?></h2>
        <p>Real-time data dari mesin fingerprint X100C</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number info"><?= $totalUsers ?></div>
            <div class="stat-label">Total User Terdaftar</div>
        </div>
        <div class="stat-card">
            <div class="stat-number info"><?= $totalLogs ?></div>
            <div class="stat-label">Absensi Hari Ini</div>
        </div>
        <div class="stat-card">
            <div class="stat-number success"><?= $matchedUsers ?></div>
            <div class="stat-label">User Teridentifikasi</div>
        </div>
        <div class="stat-card">
            <div class="stat-number <?= $unmatchedCount > 0 ? 'danger' : 'success' ?>"><?= $unmatchedCount ?></div>
            <div class="stat-label">PIN Tidak Dikenali</div>
        </div>
    </div>

    <!-- Search Section -->
    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #dee2e6;">
        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 250px;">
                <input type="text" 
                       id="searchInput" 
                       placeholder="🔍 Cari berdasarkan PIN, Nama, atau Display ID..." 
                       style="width: 100%; padding: 10px 15px; border: 2px solid #ced4da; border-radius: 6px; font-size: 14px; transition: border-color 0.3s;"
                       onkeyup="searchTable()"
                       onfocus="this.style.borderColor='#007bff'"
                       onblur="this.style.borderColor='#ced4da'">
            </div>
            <button onclick="clearSearch()" 
                    style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 6px; cursor: pointer; transition: background-color 0.3s;"
                    onmouseover="this.style.backgroundColor='#5a6268'"
                    onmouseout="this.style.backgroundColor='#6c757d'">
                ✕ Clear
            </button>
            <div id="searchResults" 
                 style="font-size: 14px; color: #495057; font-weight: 500;">
            </div>
        </div>
    </div>

    <?php if ($unmatchedCount > 0): ?>
    <div class="alert alert-warning">
        <strong>⚠️ PIN Tidak Dikenali:</strong><br>
        <?= implode(', ', array_unique($unmatchedPins)) ?><br>
        <small><em>Kemungkinan: User baru yang belum disinkronkan atau ada perbedaan mapping PIN</em></small>
    </div>
    <?php endif; ?>

   <table id="attendanceTable">
    <thead>
        <tr>
            <th>No</th>
            <th>PIN</th>
            <th>Display ID</th>
            <th>Nama</th>
            <th>Waktu Absen</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody id="tableBody">
        <?php if (empty($combinedData)): ?>
        <tr>
            <td colspan="6" style="text-align: center; color: #6c757d; font-style: italic; padding: 30px;">
                Tidak ada data user
            </td>
        </tr>
        <?php else: ?>
        <?php foreach ($combinedData as $index => $row): ?>
        <tr>
            <td><?= $index + 1 ?></td>
            <td><?= htmlspecialchars($row['pin']) ?></td>
            <td><?= htmlspecialchars($row['displayId']) ?></td>
            <td><?= htmlspecialchars($row['nama']) ?></td>
            <td><?= htmlspecialchars($row['datetime']) ?></td>
            <td>
                <?php if ($row['status'] === 'HADIR'): ?>
                <span class="status-ok"><?= $row['status'] ?></span>
                <?php else: ?>
                <span class="status-error"><?= $row['status'] ?></span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

    <div class="debug-section">
        <button class="toggle-btn" onclick="toggleDebug()">🔍 Toggle Debug Info</button>
        <div id="debugInfo" class="hidden" style="margin-top: 15px;">
            <h4>Debug Information:</h4>
            <p><strong>Sample User Mapping (5 pertama):</strong></p>
            <pre style="background: white; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;">
<?= print_r(array_slice($userList, 0, 5, true), true) ?>
            </pre>
            
            <?php if (!empty($userDetail)): ?>
            <p><strong>Sample User Detail (3 pertama):</strong></p>
            <pre style="background: white; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;">
<?= print_r(array_slice($userDetail, 0, 3, true), true) ?>
            </pre>
            <?php endif; ?>
        </div>
    </div>

    <div style="margin-top: 20px; padding: 15px; background: #e9ecef; border-radius: 6px; font-size: 0.9em;">
        <strong>ℹ️ Informasi Sistem:</strong><br>
        • Koneksi: <?= $ip ?>:<?= $port ?><br>
        • Method: GetAllUserInfo + GetUserInfo<br>
        • Mapping: PIN Internal → Display ID<br>
        • Update: Real-time dari mesin<br>
        • Status: <?= $totalUsers > 0 ? '✅ Aktif' : '❌ Error' ?>
    </div>
</div>

<script>
// Data untuk search
const attendanceData = [
    <?php 
    $no = 1;
    foreach ($logs as $log): 
        $pin = $log['pin'];
        $nama = $userList[$pin] ?? 'TIDAK DIKENAL';
        $displayId = isset($userDetail[$pin]) ? $userDetail[$pin]['display_id'] : $pin;
        $status = isset($userList[$pin]) ? 'OK' : 'ERROR';
        echo json_encode([
            'no' => $no++,
            'pin' => $pin,
            'displayId' => $displayId,
            'nama' => $nama,
            'datetime' => $log['datetime'],
            'status' => $status
        ]) . ",\n    ";
    endforeach; ?>
];

// Render table
function renderTable(data = attendanceData) {
    const tbody = document.getElementById('tableBody');
    
    if (data.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; color: #6c757d; font-style: italic; padding: 30px;">
                    ${attendanceData.length === 0 ? 'Tidak ada data absensi untuk hari ini' : 'Tidak ada hasil yang ditemukan'}
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = data.map((row, index) => `
        <tr>
            <td>${index + 1}</td>
            <td>${row.pin}</td>
            <td>${row.displayId}</td>
            <td>${row.nama}</td>
            <td>${row.datetime}</td>
            <td><span class="status-${row.status.toLowerCase()}">${row.status}</span></td>
        </tr>
    `).join('');
}

// Search functionality
function searchTable() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
    const resultsDiv = document.getElementById('searchResults');
    
    if (searchTerm === '') {
        renderTable();
        resultsDiv.innerHTML = '';
        return;
    }
    
    const filtered = attendanceData.filter(row => 
        row.pin.toString().toLowerCase().includes(searchTerm) ||
        row.displayId.toString().toLowerCase().includes(searchTerm) ||
        row.nama.toLowerCase().includes(searchTerm) ||
        row.datetime.toLowerCase().includes(searchTerm)
    );
    
    renderTable(filtered);
    
    // Update search results info
    if (filtered.length === 0) {
        resultsDiv.innerHTML = `<span style="color: #dc3545;">❌ Tidak ditemukan</span>`;
    } else if (filtered.length === attendanceData.length) {
        resultsDiv.innerHTML = `<span style="color: #28a745;">📋 Menampilkan semua data (${filtered.length})</span>`;
    } else {
        resultsDiv.innerHTML = `<span style="color: #007bff;">🔍 Ditemukan ${filtered.length} dari ${attendanceData.length} data</span>`;
    }
}

// Clear search
function clearSearch() {
    document.getElementById('searchInput').value = '';
    document.getElementById('searchResults').innerHTML = '';
    renderTable();
}

// Toggle debug
function toggleDebug() {
    const debugInfo = document.getElementById('debugInfo');
    debugInfo.classList.toggle('hidden');
}

// Initialize table on page load
document.addEventListener('DOMContentLoaded', function() {
    renderTable();
});

// Enter key support
document.getElementById('searchInput').addEventListener('keypress', function(event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        searchTable();
    }
});
</script>

</body>
</html>