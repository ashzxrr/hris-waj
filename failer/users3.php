<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/header.php';

// Ambil data user dari mesin fingerprint
$users = getUsers($ip, $port, $key);

// Ambil semua data user dari database (kecuali yang NIP-nya RESIGN)
$database_users = [];
$query = "SELECT pin, nip, nik, nama, jk, job_title, job_level, bagian, departemen FROM users WHERE LOWER(TRIM(nip)) != 'resign' ORDER BY CAST(pin AS UNSIGNED)";
$result = mysqli_query($mysqli, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $database_users[$row['pin']] = $row;
    }
}

// Gabungkan data dari mesin dan database
$combined_users = [];

// 1. Tambahkan semua user dari mesin fingerprint
foreach ($users as $pin => $name) {
    $combined_users[$pin] = [
        'pin' => $pin,
        'nama_mesin' => $name,
        'nama_db' => isset($database_users[$pin]) ? $database_users[$pin]['nama'] : '-',
        'nip' => isset($database_users[$pin]) ? $database_users[$pin]['nip'] : '-',
        'nik' => isset($database_users[$pin]) ? $database_users[$pin]['nik'] : '-',
        'jk' => isset($database_users[$pin]) ? $database_users[$pin]['jk'] : '-',
        'job_title' => isset($database_users[$pin]) ? $database_users[$pin]['job_title'] : '-',
        'job_level' => isset($database_users[$pin]) ? $database_users[$pin]['job_level'] : '-',
        'bagian' => isset($database_users[$pin]) ? $database_users[$pin]['bagian'] : '-',
        'departemen' => isset($database_users[$pin]) ? $database_users[$pin]['departemen'] : '-',
        'status' => isset($database_users[$pin]) ? 'Ada di Keduanya' : 'Hanya di Mesin',
        'in_machine' => true,
        'in_database' => isset($database_users[$pin])
    ];
}

// 2. Tambahkan user yang hanya ada di database
foreach ($database_users as $pin => $data) {
    if (!isset($users[$pin])) {
        $combined_users[$pin] = [
            'pin' => $pin,
            'nama_mesin' => '-',
            'nama_db' => $data['nama'],
            'nip' => $data['nip'],
            'nik' => $data['nik'],
            'jk' => $data['jk'],
            'job_title' => $data['job_title'],
            'job_level' => $data['job_level'],
            'bagian' => $data['bagian'],
            'departemen' => $data['departemen'],
            'status' => 'Hanya di Database',
            'in_machine' => false,
            'in_database' => true
        ];
    }
}

// Urutkan berdasarkan PIN (numerik)
uksort($combined_users, function ($a, $b) {
    return (int) $a - (int) $b;
});

// Hitung statistik
$total_users = count($combined_users);
$users_both = count(array_filter($combined_users, function ($user) {
    return $user['in_machine'] && $user['in_database'];
}));
$users_machine_only = count(array_filter($combined_users, function ($user) {
    return $user['in_machine'] && !$user['in_database'];
}));
$users_database_only = count(array_filter($combined_users, function ($user) {
    return !$user['in_machine'] && $user['in_database'];
}));
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
    <title>Data User Fingerprint & Database</title>
    <script>
        let currentFilter = 'all';

        function toggleAll(source) {
            const checkboxes = document.querySelectorAll('input[name="selected_users[]"]:not(.hidden)');
            checkboxes.forEach(checkbox => checkbox.checked = source.checked);
            updateSelectedCount();
        }

        function validateForm() {
            const checkboxes = document.querySelectorAll('input[name="selected_users[]"]:checked:not(.hidden)');
            const tanggalDari = document.querySelector('input[name="tanggal_dari"]').value;
            const tanggalSampai = document.querySelector('input[name="tanggal_sampai"]').value;

            if (checkboxes.length === 0) {
                alert('⚠️ Pilih minimal satu user!');
                return false;
            }

            if (!tanggalDari || !tanggalSampai) {
                alert('⚠️ Tanggal dari dan sampai harus diisi!');
                return false;
            }

            if (tanggalDari > tanggalSampai) {
                alert('⚠️ Tanggal dari tidak boleh lebih besar dari tanggal sampai!');
                return false;
            }

            // Show loading spinner
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.classList.add('loading');

            // Tampilkan loading tanpa mengganggu submit form
            const loadingIndicator = document.getElementById('loadingIndicator');
            if (loadingIndicator) {
                loadingIndicator.classList.add('show');
            }

            return true; // Ini penting! Harus return true agar form bisa submit
        }
        function showLoading() {
            const submitBtn = document.getElementById('submitBtn');
            const loadingText = document.getElementById('loadingText');

            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<div class="loading-spinner show"></div> Memproses...';
            loadingText.classList.add('show');

            // Optional: Hide loading after some time if form doesn't submit
            setTimeout(() => {
                if (submitBtn.disabled) {
                    hideLoading();
                }
            }, 30000); // 30 seconds timeout
        }

        function hideLoading() {
            const submitBtn = document.getElementById('submitBtn');
            const loadingText = document.getElementById('loadingText');

            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Detail Absensi <span class="emoji">➡️</span>';
            loadingText.classList.remove('show');
        }

        function searchAndFilter() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const tableRows = document.querySelectorAll('tbody tr');
            let visibleCount = 0;

            tableRows.forEach(row => {
                // Get all cell values from the row
                const pin = row.cells[1].textContent.toLowerCase();
                const nama = row.cells[2].textContent.toLowerCase();
                const nip = row.cells[3].textContent.toLowerCase();
                const nik = row.cells[4].textContent.toLowerCase();
                const jk = row.cells[5].textContent.toLowerCase();
                const job_title = row.cells[6].textContent.toLowerCase();
                const job_level = row.cells[7].textContent.toLowerCase();
                const bagian = row.cells[8].textContent.toLowerCase();
                const departemen = row.cells[9].textContent.toLowerCase();

                const status = row.getAttribute('data-status');
                const checkbox = row.querySelector('input[type="checkbox"]');

                // Check search criteria against all columns
                const matchSearch = pin.includes(searchInput) ||
                    nama.includes(searchInput) ||
                    nip.includes(searchInput) ||
                    nik.includes(searchInput) ||
                    jk.includes(searchInput) ||
                    job_title.includes(searchInput) ||
                    job_level.includes(searchInput) ||
                    bagian.includes(searchInput) ||
                    departemen.includes(searchInput);

                // Check filter criteria
                const matchFilter = currentFilter === 'all' ||
                    (currentFilter === 'both' && status === 'both') ||
                    (currentFilter === 'machine' && status === 'machine') ||
                    (currentFilter === 'database' && status === 'database');

                if (matchSearch && matchFilter) {
                    row.style.display = '';
                    row.classList.remove('hidden');
                    if (checkbox) checkbox.classList.remove('hidden');
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                    row.classList.add('hidden');
                    if (checkbox) {
                        checkbox.classList.add('hidden');
                        checkbox.checked = false;
                    }
                }
            });

            document.getElementById('userCount').textContent = visibleCount;
            document.querySelector('input[onchange="toggleAll(this)"]').checked = false;
            updateSelectedCount();
        }

        function setFilter(filter) {
            // Update active button
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelector(`[onclick="setFilter('${filter}')"]`).classList.add('active');

            currentFilter = filter;
            searchAndFilter();
        }

        function updateSelectedCount() {
            const selectedCount = document.querySelectorAll('input[name="selected_users[]"]:checked:not(.hidden)').length;
            const selectedCountElement = document.getElementById('selectedCount');
            if (selectedCountElement) {
                selectedCountElement.textContent = selectedCount;
            }
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('searchInput').addEventListener('input', searchAndFilter);

            // Add change event to all checkboxes
            document.querySelectorAll('input[name="selected_users[]"]').forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedCount);
            });

            // Set default dates
            const today = new Date();
            const todayStr = today.toISOString().split('T')[0];
            document.querySelector('input[name="tanggal_dari"]').value = todayStr;
            document.querySelector('input[name="tanggal_sampai"]').value = todayStr;
        });

        function setToday() {
            const today = new Date();
            const todayStr = formatDate(today);

            document.getElementById('startDate').value = todayStr;
            document.getElementById('endDate').value = todayStr;
            updateDateButtons(this);
        }

        function setCurrentMonth() {
            const now = new Date();
            // Set first day of current month
            const firstDay = new Date(now.getFullYear(), now.getMonth(), 2);
            // Set last day by getting day 0 of next month (which is last day of current month)
            const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 1);

            document.getElementById('startDate').value = formatDate(firstDay);
            document.getElementById('endDate').value = formatDate(lastDay);
            updateDateButtons(this);
        }

        function setPreviousMonth() {
            const now = new Date();
            const firstDay = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            const lastDay = new Date(now.getFullYear(), now.getMonth(), 0);

            document.getElementById('startDate').value = formatDate(firstDay);
            document.getElementById('endDate').value = formatDate(lastDay);
            updateDateButtons(this);
        }

        function setCustomRange() {
            document.getElementById('startDate').focus();
            updateDateButtons(this);
        }

        function formatDate(date) {
            return date.toISOString().split('T')[0];
        }

        function updateDateButtons(clickedBtn) {
            document.querySelectorAll('.date-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            clickedBtn.classList.add('active');
        }

        // Initialize with current month
        document.addEventListener('DOMContentLoaded', () => {
            setCurrentMonth();
        });
    </script>
</head>

<body>
    <h2>👥 Data User Fingerprint & Database</h2>
    <div class="stats-container">
        <div class="stat-box">
            <div class="stat-number"><?= $total_users ?></div>
            <div class="stat-label">Total User</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?= $users_both ?></div>
            <div class="stat-label">Ada di Keduanya</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?= $users_machine_only ?></div>
            <div class="stat-label">Hanya di Mesin</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?= $users_database_only ?></div>
            <div class="stat-label">Hanya di Database</div>
        </div>
    </div>

    <div class="form-container">
        <form method="POST" action="detail-fix.php" onsubmit="return validateForm()" id="absenForm">

            <div class="form-row">
                <div class="date-container">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <label>📅 Periode:</label>
                        <input type="date" id="startDate" name="tanggal_dari" class="date-input"
                            value="<?= date('Y-m-01') ?>">
                        <span>s/d</span>
                        <input type="date" id="endDate" name="tanggal_sampai" class="date-input"
                            value="<?= date('Y-m-t') ?>">
                    </div>
                    <div class="date-buttons">
                        <button type="button" class="date-btn active" onclick="setCurrentMonth()">Bulan Ini</button>
                        <button type="button" class="date-btn" onclick="setToday()">Hari Ini</button>
                        <button type="button" class="date-btn" onclick="setCustomRange()">Custom</button>
                        <button type="submit" name="detailBtn" value="1" id="submitBtn" class="btn-primary">
                            <div class="spinner"></div>
                            Detail Absensi <span class="emoji">➡️</span>
                        </button>
                        <div class="loading-indicator" id="loadingIndicator">
                            <div class="loading-dots">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                            <span class="loading-text">Memproses data...</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="search-container">
                <div>
                    <label>🔍 Cari: </label>
                    <input type="text" id="searchInput" class="search-input"
                        placeholder="Ketik PIN, Nama, NIP, atau Bagian..." />
                </div>
                <div style="color: #666; font-size: 12px;">
                    <span id="userCount"><?= $total_users ?></span> user ditampilkan |
                    <span id="selectedCount">0</span> dipilih
                </div>
            </div>

            <div class="select-all">
                <div class="d-flex align-items-center gap-2">
                    <label>
                        <input type="checkbox" onchange="toggleAll(this)">
                        <strong>Pilih Semua User (yang terlihat)</strong>
                    </label>
                    <div class="small-legend">
                        <div class="legend-item">
                            <div class="legend-color legend-machine-only"></div>
                            <span>Data hanya di mesin</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="table-container"></div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th class="checkbox-col">✓</th>
                            <th class="pin-col">PIN</th>
                            <th>Nama (Mesin)</th>
                            <th>Nama (Database)</th>
                            <th>NIP</th>
                            <th>NIK</th>
                            <th>Gender</th>
                            <th>Jabatan</th>
                            <th>Level</th>
                            <th>Bagian</th>
                            <th>Departemen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($combined_users as $user): ?>
                            <tr class="user-row <?= (!$user['in_database']) ? 'machine-only' : '' ?>"
                                data-status="<?= $user['in_machine'] && $user['in_database'] ? 'both' : ($user['in_machine'] ? 'machine' : 'database') ?>">
                                <td class="checkbox-col">
                                    <?php if ($user['in_machine']): ?>
                                        <input type="checkbox" name="selected_users[]"
                                            value="<?= htmlspecialchars($user['pin']) ?>" class="user-checkbox">
                                    <?php else: ?>
                                        <span style="color: #ccc;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pin-col"><?= htmlspecialchars($user['pin']) ?></td>
                                <td><?= htmlspecialchars($user['nama_mesin']) ?></td>
                                <td><?= htmlspecialchars($user['nama_db']) ?></td>
                                <td><?= htmlspecialchars($user['nip']) ?></td>
                                <td><?= htmlspecialchars($user['nik']) ?></td>
                                <td><?= htmlspecialchars($user['jk']) ?></td>
                                <td><?= htmlspecialchars($user['job_title']) ?></td>
                                <td><?= htmlspecialchars($user['job_level']) ?></td>
                                <td><?= htmlspecialchars($user['bagian']) ?></td>
                                <td><?= htmlspecialchars($user['departemen']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</body>

</html>




<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_users'])) {
    $success_count = 0;
    $error_messages = [];
    
    if (isset($_POST['users']) && is_array($_POST['users'])) {
        foreach ($_POST['users'] as $user_data) {
            // Validasi data required
            if (empty($user_data['pin']) || empty($user_data['nama'])) {
                $error_messages[] = "PIN dan Nama harus diisi untuk user PIN: " . ($user_data['pin'] ?? 'Unknown');
                continue;
            }
            
            // Cek apakah user sudah ada di database berdasarkan PIN
            $pin_value = $user_data['pin'];
            $check_query = "SELECT pin FROM users WHERE pin = ?";
            $check_stmt = mysqli_prepare($mysqli, $check_query);
            mysqli_stmt_bind_param($check_stmt, "s", $pin_value);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($check_result) > 0) {
                $error_messages[] = "User dengan PIN {$user_data['pin']} sudah ada di database";
                mysqli_stmt_close($check_stmt);
                continue;
            }
            mysqli_stmt_close($check_stmt);
            
            // Siapkan data untuk insert
            $pin = $user_data['pin'];
            $nama = $user_data['nama'];
            $nip = !empty($user_data['nip']) ? $user_data['nip'] : '';
            $nik = !empty($user_data['nik']) ? $user_data['nik'] : '';
            $jk = !empty($user_data['jk']) ? $user_data['jk'] : '';
            $job_title = !empty($user_data['job_title']) ? $user_data['job_title'] : '';
            $job_level = !empty($user_data['job_level']) ? $user_data['job_level'] : '';
            $bagian = !empty($user_data['bagian']) ? $user_data['bagian'] : '';
            $departemen = !empty($user_data['departemen']) ? $user_data['departemen'] : '';
            
            // Insert user baru
            $insert_query = "INSERT INTO users (pin, nip, nama, nik, jk, job_title, job_level, bagian, departemen) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = mysqli_prepare($mysqli, $insert_query);
            
            if ($insert_stmt) {
                mysqli_stmt_bind_param($insert_stmt, "sssssssss", $pin, $nip, $nama, $nik, $jk, $job_title, $job_level, $bagian, $departemen);
                
                if (mysqli_stmt_execute($insert_stmt)) {
                    $success_count++;
                } else {
                    $error_messages[] = "Gagal menyimpan user PIN {$pin}: " . mysqli_error($mysqli);
                }
                mysqli_stmt_close($insert_stmt);
            } else {
                $error_messages[] = "Error prepare statement untuk user PIN {$pin}: " . mysqli_error($mysqli);
            }
        }
    }
    
    // Redirect dengan pesan
    if ($success_count > 0) {
        echo "<script>
            alert('✅ Berhasil menambahkan $success_count user ke database!');
            window.location.href = 'index.php';
        </script>";
        exit;
    } else {
        $error_msg = !empty($error_messages) ? implode("\\n", array_slice($error_messages, 0, 3)) : "Tidak ada data yang berhasil disimpan";
        echo "<script>alert('⚠️ $error_msg');</script>";
    }
}

// Ambil data user dari request
$selected_users = [];

// Debug informasi
if (empty($_POST)) {
    echo "<script>
        alert('⚠️ Tidak ada data yang diterima! Silakan kembali dan pilih user terlebih dahulu.');
        window.location.href = 'index.php';
    </script>";
    exit;
}

if (isset($_POST['user_data']) && is_array($_POST['user_data'])) {
    $selected_users = $_POST['user_data'];
} elseif (isset($_POST['selected_users']) && is_array($_POST['selected_users'])) {
    // Jika data dikirim dalam format selected_users, ambil dari mesin
    try {
        $machine_users = getUsers($ip, $port, $key);
        foreach ($_POST['selected_users'] as $pin) {
            if (isset($machine_users[$pin])) {
                $selected_users[] = [
                    'pin' => $pin,
                    'nama' => $machine_users[$pin]
                ];
            }
        }
    } catch (Exception $e) {
        echo "<script>
            alert('⚠️ Error mengambil data dari mesin fingerprint: " . addslashes($e->getMessage()) . "');
            window.location.href = 'index.php';
        </script>";
        exit;
    }
}

if (empty($selected_users)) {
    echo "<script>
        alert('⚠️ Tidak ada data user yang valid!');
        window.location.href = 'index.php';
    </script>";
    exit;
}

// Data referensi untuk dropdown
$departemen_options = [
    'IT' => 'Information Technology',
    'HR' => 'Human Resources', 
    'Finance' => 'Finance',
    'Operations' => 'Operations',
    'Marketing' => 'Marketing',
    'Production' => 'Production',
    'QC' => 'Quality Control',
    'Purchasing' => 'Purchasing'
];

$bagian_options = [
    'Staff' => 'Staff',
    'Supervisor' => 'Supervisor',
    'Asst Manager' => 'Assistant Manager',
    'Manager' => 'Manager',
    'General Manager' => 'General Manager'
];

$job_level_options = [
    '1' => 'Level 1',
    '2' => 'Level 2', 
    '3' => 'Level 3',
    '4' => 'Level 4',
    '5' => 'Level 5'
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
    <title>Tambah User ke Database</title>
    <style>
        body {
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 25px 30px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .content {
            padding: 30px;
        }
        
        .info-box {
            background: linear-gradient(135deg, #e3f2fd, #f3e5f5);
            border-left: 5px solid #2196f3;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 8px;
            color: #1976d2;
            font-size: 14px;
        }
        
        .user-cards {
            display: grid;
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .user-card {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            background: white;
        }
        
        .user-card:hover {
            border-color: #28a745;
            box-shadow: 0 5px 20px rgba(40, 167, 69, 0.15);
            transform: translateY(-2px);
        }
        
        .user-card-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 15px 20px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
        }
        
        .user-card-body {
            padding: 25px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-label.required::after {
            content: " *";
            color: #dc3545;
            font-weight: bold;
        }
        
        .form-control {
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #28a745;
            box-shadow: 0 0 0 4px rgba(40, 167, 69, 0.1);
        }
        
        .form-control:disabled {
            background-color: #f8f9fa;
            color: #6c757d;
            cursor: not-allowed;
        }
        
        .form-control.error {
            border-color: #dc3545;
        }
        
        select.form-control {
            cursor: pointer;
        }
        
        .actions {
            background: #f8f9fa;
            padding: 25px 30px;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            min-width: 160px;
            justify-content: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #218838, #1ea67a);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
            transform: translateY(-2px);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid #ffffff;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: none;
        }
        
        .btn.loading .spinner {
            display: block;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .user-count {
            background: #fff3cd;
            color: #856404;
            padding: 10px 15px;
            border-radius: 6px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 20px;
            border: 1px solid #ffeaa7;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <span>👤➕</span>
                Tambah User ke Database
            </h1>
        </div>
        
        <div class="content">
            <div class="info-box">
                <strong>📋 Informasi:</strong> Lengkapi data untuk <?= count($selected_users) ?> user yang akan ditambahkan ke database. 
                Data PIN dan Nama sudah diambil dari mesin fingerprint. Field bertanda <span style="color: #dc3545;">*</span> wajib diisi.
            </div>
            
            <div class="user-count">
                📊 Total User: <?= count($selected_users) ?>
            </div>
            
            <form method="POST" id="userForm">
                <div class="user-cards">
                    <?php foreach ($selected_users as $index => $user): ?>
                    <div class="user-card">
                        <div class="user-card-header">
                            <span>👤</span>
                            User <?= $index + 1 ?> - PIN: <?= htmlspecialchars($user['pin']) ?>
                        </div>
                        
                        <div class="user-card-body">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label required">PIN</label>
                                    <input type="text" 
                                           name="users[<?= $index ?>][pin]" 
                                           value="<?= htmlspecialchars($user['pin']) ?>" 
                                           class="form-control" 
                                           disabled>
                                    <input type="hidden" name="users[<?= $index ?>][pin]" value="<?= htmlspecialchars($user['pin']) ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label required">Nama Lengkap</label>
                                    <input type="text" 
                                           name="users[<?= $index ?>][nama]" 
                                           value="<?= htmlspecialchars($user['nama']) ?>" 
                                           class="form-control" 
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">NIP</label>
                                    <input type="text" 
                                           name="users[<?= $index ?>][nip]" 
                                           class="form-control" 
                                           placeholder="Masukkan NIP">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">NIK</label>
                                    <input type="text" 
                                           name="users[<?= $index ?>][nik]" 
                                           class="form-control" 
                                           placeholder="Masukkan NIK">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Jenis Kelamin</label>
                                    <select name="users[<?= $index ?>][jk]" class="form-control">
                                        <option value="">- Pilih -</option>
                                        <option value="L">Laki-laki</option>
                                        <option value="P">Perempuan</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Jt</label>
                                    <input type="text" 
                                           name="users[<?= $index ?>][job_title]" 
                                           class="form-control" 
                                           placeholder="Masukkan NIK">
                                </div>                                
                                <div class="form-group">
                                    <label class="form-label">jl</label>
                                    <input type="text" 
                                           name="users[<?= $index ?>][job_level]" 
                                           class="form-control" 
                                           placeholder="Masukkan NIK">
                                </div>   
                                <div class="form-group">
                                    <label class="form-label">bagian</label>
                                    <input type="text" 
                                           name="users[<?= $index ?>][bagian]" 
                                           class="form-control" 
                                           placeholder="Masukkan NIK">
                                </div>                               
                                 <div class="form-group">
                                    <label class="form-label">depart</label>
                                    <input type="text" 
                                           name="users[<?= $index ?>][departemen]" 
                                           class="form-control" 
                                           placeholder="Masukkan NIK">
                                </div>
                                
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="actions">
                    <button type="submit" name="save_users" class="btn btn-primary" id="saveBtn">
                        <div class="spinner"></div>
                        <span>💾</span>
                        Simpan <?= count($selected_users) ?> User
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <span>🔙</span>
                        Kembali
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        document.getElementById('userForm').addEventListener('submit', function(e) {
            const requiredFields = document.querySelectorAll('input[required]');
            let isValid = true;
            let firstInvalid = null;
            
            // Reset error styling
            document.querySelectorAll('.form-control.error').forEach(field => {
                field.classList.remove('error');
            });
            
            // Validate required fields
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('error');
                    if (!firstInvalid) {
                        firstInvalid = field;
                    }
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('⚠️ Mohon lengkapi semua field yang wajib diisi (PIN dan Nama)');
                if (firstInvalid) {
                    firstInvalid.focus();
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return false;
            }
            
            
            return true;
        });
    </script>
</body>
</html>