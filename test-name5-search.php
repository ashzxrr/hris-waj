<?php
$ip = "192.168.0.102";
$port = 80;
$key = 0;

// Get selected date from URL parameter or use today as default
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedMonth = $_GET['month'] ?? date('Y-m');

// =========================
// 1. Ambil Data User dengan Mapping yang Benar
// =========================
function getUserList($ip, $port, $key) {
    $soap_request = "<GetAllUserInfo>
                        <ArgComKey xsi:type=\"xsd:integer\">{$key}</ArgComKey>
                     </GetAllUserInfo>";
                     
    $connect = fsockopen($ip, $port, $errno, $errstr, 3);
    $userMapping = [];
    $allUsers = [];
    
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
        preg_match_all('/<Row>.*?<PIN>(.*?)<\/PIN>.*?<n>(.*?)<\/Name>.*?<PIN2>(.*?)<\/PIN2>.*?<\/Row>/s', $buffer, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $internal_pin = $match[1];
            $name = $match[2];
            $pin2 = $match[3];
            $display_id = $pin2 ?: $internal_pin;
            
            $userMapping[$internal_pin] = $name;
            $allUsers[$internal_pin] = [
                'name' => $name,
                'display_id' => $display_id,
                'pin2' => $pin2
            ];
        }
    }
    
    return ['mapping' => $userMapping, 'all_users' => $allUsers];
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
            
            // Filter berdasarkan tanggal
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
// 3. Proses Data
// =========================
$userData = getUserList($ip, $port, $key);
$userMapping = $userData['mapping'];
$allUsers = $userData['all_users'];
$logs = getAttLog($ip, $port, $key, $selectedDate);

// Group logs by PIN to get first attendance time
$attendanceByPin = [];
foreach ($logs as $log) {
    $pin = $log['pin'];
    if (!isset($attendanceByPin[$pin])) {
        $attendanceByPin[$pin] = $log['datetime'];
    } else {
        // Keep earliest time for the day
        if ($log['datetime'] < $attendanceByPin[$pin]) {
            $attendanceByPin[$pin] = $log['datetime'];
        }
    }
}

// Create complete attendance data (including absent employees)
$completeAttendance = [];
foreach ($allUsers as $pin => $userData) {
    $completeAttendance[] = [
        'pin' => $pin,
        'display_id' => $userData['display_id'],
        'name' => $userData['name'],
        'datetime' => $attendanceByPin[$pin] ?? null,
        'status' => isset($attendanceByPin[$pin]) ? 'PRESENT' : 'ABSENT'
    ];
}

// Sort by name
usort($completeAttendance, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

// Statistics
$totalUsers = count($allUsers);
$presentCount = count($attendanceByPin);
$absentCount = $totalUsers - $presentCount;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Data Absensi Lengkap</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        
        /* Date Filter Styles */
        .date-filter { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #dee2e6; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-label { font-weight: 600; margin-bottom: 5px; color: #495057; }
        .filter-input { padding: 10px; border: 2px solid #ced4da; border-radius: 6px; font-size: 14px; }
        .filter-btn { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .filter-btn:hover { background: #0056b3; }
        .quick-dates { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
        .quick-btn { padding: 8px 12px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .quick-btn:hover { background: #5a6268; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 6px; text-align: center; }
        .stat-number { font-size: 2em; font-weight: bold; color: #495057; }
        .stat-label { color: #6c757d; font-size: 0.9em; }
        .success { color: #28a745; } .danger { color: #dc3545; } .warning { color: #ffc107; } .info { color: #17a2b8; }
        
        /* Search Styles */
        .search-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #dee2e6; }
        .search-grid { display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
        .search-input { flex: 1; min-width: 250px; padding: 10px 15px; border: 2px solid #ced4da; border-radius: 6px; font-size: 14px; }
        .search-input:focus { border-color: #007bff; outline: none; }
        .clear-btn { padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 6px; cursor: pointer; }
        .clear-btn:hover { background: #5a6268; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background: #495057; color: white; padding: 12px; text-align: left; font-weight: 600; }
        td { padding: 10px; border-bottom: 1px solid #dee2e6; }
        tr:nth-child(even) { background: #f8f9fa; }
        tr:hover { background: #e9ecef; }
        
        .status-present { background: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 0.85em; font-weight: 600; }
        .status-absent { background: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 4px; font-size: 0.85em; font-weight: 600; }
        
        .time-present { color: #155724; font-weight: 600; }
        .time-absent { color: #6c757d; font-style: italic; }
        
        .debug-section { margin-top: 30px; padding: 15px; background: #f1f3f4; border-radius: 6px; }
        .toggle-btn { background: #007bff; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; }
        .hidden { display: none; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .filter-grid { grid-template-columns: 1fr; }
            .search-grid { flex-direction: column; align-items: stretch; }
            .search-input { min-width: 100%; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h2>📋 Data Absensi Lengkap</h2>
        <p>Semua karyawan terdaftar dengan status kehadiran - <?= date('d F Y', strtotime($selectedDate)) ?></p>
    </div>

    <!-- Date Filter Section -->
    <div class="date-filter">
        <form method="GET" id="dateForm">
            <div class="filter-grid">
                <div class="filter-group">
                    <label class="filter-label">📅 Pilih Tanggal:</label>
                    <input type="date" name="date" class="filter-input" value="<?= $selectedDate ?>" id="dateInput">
                </div>
                <div class="filter-group">
                    <label class="filter-label">📊 Filter Bulan:</label>
                    <input type="month" name="month" class="filter-input" value="<?= $selectedMonth ?>" id="monthInput">
                </div>
                <div class="filter-group">
                    <button type="submit" class="filter-btn">🔍 Tampilkan Data</button>
                </div>
            </div>
        </form>
        
        <div class="quick-dates">
            <strong style="margin-right: 10px;">Quick Select:</strong>
            <button class="quick-btn" onclick="setDate('<?= date('Y-m-d') ?>')">Hari Ini</button>
            <button class="quick-btn" onclick="setDate('<?= date('Y-m-d', strtotime('-1 day')) ?>')">Kemarin</button>
            <button class="quick-btn" onclick="setDate('<?= date('Y-m-d', strtotime('monday this week')) ?>')">Senin</button>
            <button class="quick-btn" onclick="setDate('<?= date('Y-m-d', strtotime('friday this week')) ?>')">Jumat</button>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number info"><?= $totalUsers ?></div>
            <div class="stat-label">Total Karyawan</div>
        </div>
        <div class="stat-card">
            <div class="stat-number success"><?= $presentCount ?></div>
            <div class="stat-label">Hadir</div>
        </div>
        <div class="stat-card">
            <div class="stat-number danger"><?= $absentCount ?></div>
            <div class="stat-label">Tidak Hadir</div>
        </div>
        <div class="stat-card">
            <div class="stat-number info"><?= $totalUsers > 0 ? round(($presentCount / $totalUsers) * 100, 1) : 0 ?>%</div>
            <div class="stat-label">Tingkat Kehadiran</div>
        </div>
    </div>

    <!-- Search Section -->
    <div class="search-section">
        <div class="search-grid">
            <input type="text" 
                   id="searchInput" 
                   class="search-input"
                   placeholder="🔍 Cari berdasarkan PIN, Display ID, Nama, atau Waktu..." 
                   onkeyup="searchTable()">
            <button onclick="clearSearch()" class="clear-btn">✕ Clear</button>
            <div id="searchResults" style="font-size: 14px; color: #495057; font-weight: 500;"></div>
        </div>
        
        <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
            <button class="quick-btn" onclick="filterByStatus('PRESENT')">👥 Tampilkan Hadir</button>
            <button class="quick-btn" onclick="filterByStatus('ABSENT')">❌ Tampilkan Tidak Hadir</button>
            <button class="quick-btn" onclick="filterByStatus('ALL')">📋 Tampilkan Semua</button>
        </div>
    </div>

    <table id="attendanceTable">
        <thead>
            <tr>
                <th>No</th>
                <th>PIN</th>
                <th>Display ID</th>
                <th>Nama Karyawan</th>
                <th>Waktu Masuk</th>
                <th>Status Kehadiran</th>
            </tr>
        </thead>
        <tbody id="tableBody">
        </tbody>
    </table>

    <div class="debug-section">
        <button class="toggle-btn" onclick="toggleDebug()">🔍 Toggle Debug Info</button>
        <div id="debugInfo" class="hidden" style="margin-top: 15px;">
            <h4>Debug Information:</h4>
            <p><strong>Selected Date:</strong> <?= $selectedDate ?></p>
            <p><strong>Attendance Logs Today:</strong> <?= count($logs) ?></p>
            <p><strong>Sample Data:</strong></p>
            <pre style="background: white; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;">
<?= print_r(array_slice($completeAttendance, 0, 3, true), true) ?>
            </pre>
        </div>
    </div>

    <div style="margin-top: 20px; padding: 15px; background: #e9ecef; border-radius: 6px; font-size: 0.9em;">
        <strong>ℹ️ Informasi Sistem:</strong><br>
        • Koneksi: <?= $ip ?>:<?= $port ?><br>
        • Tanggal: <?= date('d F Y', strtotime($selectedDate)) ?><br>
        • Status: ✅ Menampilkan semua karyawan (hadir & tidak hadir)<br>
        • Update: Real-time dari mesin fingerprint
    </div>
</div>

<script>
// Data untuk search dan filter
const attendanceData = <?= json_encode($completeAttendance) ?>;

// Render table
function renderTable(data = attendanceData) {
    const tbody = document.getElementById('tableBody');
    
    if (data.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; color: #6c757d; font-style: italic; padding: 30px;">
                    Tidak ada data karyawan
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = data.map((row, index) => {
        const timeDisplay = row.datetime ? 
            `<span class="time-present">${row.datetime.split(' ')[1]}</span>` : 
            `<span class="time-absent">-</span>`;
            
        const statusClass = row.status === 'PRESENT' ? 'status-present' : 'status-absent';
        const statusText = row.status === 'PRESENT' ? 'HADIR' : 'TIDAK HADIR';
        
        return `
            <tr>
                <td>${index + 1}</td>
                <td>${row.pin}</td>
                <td>${row.display_id}</td>
                <td>${row.name}</td>
                <td>${timeDisplay}</td>
                <td><span class="${statusClass}">${statusText}</span></td>
            </tr>
        `;
    }).join('');
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
        row.display_id.toString().toLowerCase().includes(searchTerm) ||
        row.name.toLowerCase().includes(searchTerm) ||
        (row.datetime && row.datetime.toLowerCase().includes(searchTerm)) ||
        (row.status === 'PRESENT' ? 'hadir' : 'tidak hadir').includes(searchTerm)
    );
    
    renderTable(filtered);
    updateSearchResults(filtered.length, attendanceData.length);
}

// Filter by status
function filterByStatus(status) {
    const resultsDiv = document.getElementById('searchResults');
    
    if (status === 'ALL') {
        renderTable();
        resultsDiv.innerHTML = `<span style="color: #007bff;">📋 Menampilkan semua karyawan (${attendanceData.length})</span>`;
        return;
    }
    
    const filtered = attendanceData.filter(row => row.status === status);
    renderTable(filtered);
    
    const statusText = status === 'PRESENT' ? 'Hadir' : 'Tidak Hadir';
    resultsDiv.innerHTML = `<span style="color: ${status === 'PRESENT' ? '#28a745' : '#dc3545'};">
        ${status === 'PRESENT' ? '👥' : '❌'} ${statusText}: ${filtered.length} karyawan
    </span>`;
}

// Update search results info
function updateSearchResults(found, total) {
    const resultsDiv = document.getElementById('searchResults');
    
    if (found === 0) {
        resultsDiv.innerHTML = `<span style="color: #dc3545;">❌ Tidak ditemukan</span>`;
    } else if (found === total) {
        resultsDiv.innerHTML = `<span style="color: #28a745;">📋 Menampilkan semua data (${found})</span>`;
    } else {
        resultsDiv.innerHTML = `<span style="color: #007bff;">🔍 Ditemukan ${found} dari ${total} karyawan</span>`;
    }
}

// Clear search
function clearSearch() {
    document.getElementById('searchInput').value = '';
    document.getElementById('searchResults').innerHTML = '';
    renderTable();
}

// Date functions
function setDate(date) {
    document.getElementById('dateInput').value = date;
    document.getElementById('dateForm').submit();
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