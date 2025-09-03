<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    // Set flash message in session
    $_SESSION['login_warning'] = "Anda harus login terlebih dahulu!";
    header('Location: router.php?page=login');
    exit();
}
require __DIR__ . '/../../includes/config.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/header.php';

// Ambil data user dari mesin fingerprint
$users = getUsers($ip, $port, $key);

// Ambil semua data user dari database (termasuk yang NIP-nya RESIGN untuk styling)
$database_users = [];
$query = "SELECT pin, nip, nik, nama, jk, job_title, job_level, bagian, departemen FROM users ORDER BY CAST(pin AS UNSIGNED)";
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
        'in_database' => isset($database_users[$pin]),
        'is_resign' => isset($database_users[$pin]) && strtolower(trim($database_users[$pin]['nip'])) === 'resign'
    ];
}

// 2. Tambahkan user yang hanya ada di database
foreach ($database_users as $pin => $data) {
    if (!isset($users[$pin])) {
        $is_resign = strtolower(trim($data['nip'])) === 'resign';
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
            'in_database' => true,
            'is_resign' => $is_resign
        ];
    }
}

// Urutkan berdasarkan PIN (numerik)
uksort($combined_users, function ($a, $b) {
    return (int) $a - (int) $b;
});

// Hitung statistik (exclude resign users)
$non_resign_users = array_filter($combined_users, function ($user) {
    return !$user['is_resign'];
});

$total_users = count($non_resign_users);
$users_both = count(array_filter($non_resign_users, function ($user) {
    return $user['in_machine'] && $user['in_database'];
}));
$users_machine_only = count(array_filter($non_resign_users, function ($user) {
    return $user['in_machine'] && !$user['in_database'];
}));
$users_database_only = count(array_filter($non_resign_users, function ($user) {
    return !$user['in_machine'] && $user['in_database'];
}));

$resign_count = count($combined_users) - count($non_resign_users);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
    <title>Data User Fingerprint & Database</title>
    <style>
        /* Style untuk baris RESIGN */
        .user-row.resign {
            background-color: #ffe6e6 !important;
            color: #cc0000;
        }

        .user-row.resign:hover {
            background-color: #ffcccc !important;
        }

        .user-row.resign td {
            border-color: #ffb3b3;
        }

        /* Style untuk checkbox yang disabled pada user resign */
        .user-row.resign input[type="checkbox"] {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Update legend untuk menambah info resign */
        .resign-legend {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            color: #cc0000;
        }

        .legend-color.legend-resign {
            width: 12px;
            height: 12px;
            background-color: #ffe6e6;
            border: 1px solid #cc0000;
            border-radius: 2px;
        }

        .stats-container .stat-box.resign {
            background: linear-gradient(135deg, #ffe6e6, #ffcccc);
            color: #cc0000;
            border: 1px solid #ffb3b3;
        }

        .btn-secondary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Style untuk highlight user yang hanya ada di mesin */
        .user-row.machine-only-highlight {
            background-color: #e8f5e8 !important;
            border-left: 4px solid #28a745;
        }

        .user-row.machine-only-highlight:hover {
            background-color: #d4edda !important;
        }

        /* Update checkbox styling untuk user yang bisa ditambahkan */
        .user-row.machine-only input[type="checkbox"].add-user {
            accent-color: #28a745;
        }

        .select-all {
            background-color: #f8fafc;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .select-all .d-flex {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .small-legend {
            display: flex;
            gap: 15px;
            margin-left: 15px;
        }

        /* Update button style untuk konsistensi dengan layout baru */
        .btn-secondary {
            padding: 6px 15px;
            height: 32px;
            font-size: 0.85rem;
            white-space: nowrap;
        }

        /* Style untuk filter buttons yang selaras */
        .filter-buttons {
            display: inline-flex;
            gap: 8px;
            align-items: center;
        }

        .filter-btn {
            background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
            color: #475569;
            border: none;
            padding: 6px 15px;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            height: 32px;
            display: inline-flex;
            align-items: center;
            white-space: nowrap;
        }

        .filter-btn:hover {
            transform: translateY(-1px);
            background: linear-gradient(135deg, #cbd5e1, #94a3b8);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #45caff, #5c9dff);
            color: white;
            box-shadow: 0 2px 8px rgba(69, 202, 255, 0.3);
        }

        .filter-btn.active:hover {
            background: linear-gradient(135deg, #5c9dff, #45caff);
        }

        .filter-btn:active {
            transform: translateY(0);
        }

        .btn-primary,
        .btn-secondary,
        .date-btn,
        .filter-btn {
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.15);
        }
    </style>
    <script>
        let currentFilter = 'all';

        function toggleAll(source) {
            const checkboxes = document.querySelectorAll('input[name="selected_users[]"]:not(.hidden):not(:disabled)');
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

        function validateAddUsers() {
            const machineOnlyCheckboxes = document.querySelectorAll('input[name="selected_users[]"]:checked:not(.hidden)');
            const selectedMachineOnly = Array.from(machineOnlyCheckboxes).filter(checkbox => {
                return checkbox.closest('tr').classList.contains('machine-only');
            });

            if (selectedMachineOnly.length === 0) {
                alert('⚠️ Pilih minimal satu user yang hanya ada di mesin (baris hijau) untuk ditambahkan ke database!');
                return false;
            }

            // Buat hidden inputs untuk data user yang dipilih
            const form = document.getElementById('absenForm');

            // Hapus hidden inputs yang lama jika ada
            const oldInputs = form.querySelectorAll('input[name^="user_data"]');
            oldInputs.forEach(input => input.remove());

            // Tambahkan data user yang dipilih
            selectedMachineOnly.forEach((checkbox, index) => {
                const row = checkbox.closest('tr');
                const pin = checkbox.value;
                const namaMesin = row.cells[2].textContent.trim();

                // Buat hidden inputs untuk pin dan nama
                const pinInput = document.createElement('input');
                pinInput.type = 'hidden';
                pinInput.name = `user_data[${index}][pin]`;
                pinInput.value = pin;
                form.appendChild(pinInput);

                const namaInput = document.createElement('input');
                namaInput.type = 'hidden';
                namaInput.name = `user_data[${index}][nama]`;
                namaInput.value = namaMesin;
                form.appendChild(namaInput);
            });
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

            // Update tombol tambah user berdasarkan selection
            updateAddUserButton();
        }

        function updateAddUserButton() {
            const addUserBtn = document.getElementById('addUserBtn');
            const machineOnlyCheckboxes = document.querySelectorAll('input[name="selected_users[]"]:checked:not(.hidden)');
            const selectedMachineOnly = Array.from(machineOnlyCheckboxes).filter(checkbox => {
                return checkbox.closest('tr').classList.contains('machine-only');
            });

            if (selectedMachineOnly.length > 0) {
                addUserBtn.disabled = false;
                addUserBtn.innerHTML = `<span class="emoji">👤➕</span> Tambah ${selectedMachineOnly.length} User ke Database`;

                // Highlight selected machine-only rows
                document.querySelectorAll('.machine-only').forEach(row => {
                    const checkbox = row.querySelector('input[name="selected_users[]"]');
                    if (checkbox && checkbox.checked && !checkbox.classList.contains('hidden')) {
                        row.classList.add('machine-only-highlight');
                    } else {
                        row.classList.remove('machine-only-highlight');
                    }
                });
            } else {
                addUserBtn.disabled = true;
                addUserBtn.innerHTML = '<span class="emoji">👤➕</span> Tambah User ke Database';

                // Remove all highlights
                document.querySelectorAll('.machine-only-highlight').forEach(row => {
                    row.classList.remove('machine-only-highlight');
                });
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

    <div class="form-container">
        <form method="POST" action="?page=users-detail" onsubmit="return validateForm()" id="absenForm">

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
                    <span id="userCount"><?= count($combined_users) ?></span> user ditampilkan |
                    <span id="selectedCount">0</span> dipilih
                </div>
            </div>
            <div class="select-all">
                <div class="d-flex align-items-center gap-2" style="justify-content: space-between">
                    <div class="d-flex align-items-center gap-2">
                        <label>
                            <input type="checkbox" onchange="toggleAll(this)">
                            <strong>Pilih Semua User Aktif (yang terlihat)</strong>
                        </label>
                        <div class="small-legend">
                            <div class="legend-item">
                                <div class="legend-color legend-machine-only"></div>
                                <span>Hanya di mesin (bisa ditambahkan)</span>
                            </div>
                            <?php if ($resign_count > 0): ?>
                                <div class="legend-item resign-legend">
                                    <div class="legend-color legend-resign"></div>
                                    <span>User Resign</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="filter-buttons">
                        <button type="button" class="filter-btn active" onclick="setFilter('all')">Semua</button>
                        <button type="button" class="filter-btn" onclick="setFilter('machine')">Hanya di Mesin</button>
                    </div>
                    <button type="submit" name="addUserBtn" value="1" id="addUserBtn" class="btn-secondary"
                        onclick="return validateAddUsers()" formaction="form-add-user.php">
                        <span class="emoji">👤➕</span> Tambah User ke Database
                    </button>
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
                            <tr class="user-row <?= (!$user['in_database']) ? 'machine-only' : '' ?> <?= $user['is_resign'] ? 'resign' : '' ?>"
                                data-status="<?= $user['in_machine'] && $user['in_database'] ? 'both' : ($user['in_machine'] ? 'machine' : 'database') ?>">
                                <td class="checkbox-col">
                                    <?php if ($user['in_machine'] && !$user['is_resign']): ?>
                                        <input type="checkbox" name="selected_users[]"
                                            value="<?= htmlspecialchars($user['pin']) ?>"
                                            class="user-checkbox <?= !$user['in_database'] ? 'add-user' : '' ?>">
                                    <?php elseif ($user['is_resign']): ?>
                                        <input type="checkbox" name="selected_users[]"
                                            value="<?= htmlspecialchars($user['pin']) ?>" class="user-checkbox" disabled
                                            title="User dengan status RESIGN tidak dapat dipilih">
                                    <?php else: ?>
                                        <span style="color: #ccc;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pin-col"><?= htmlspecialchars($user['pin']) ?></td>
                                <td><?= htmlspecialchars($user['nama_mesin']) ?></td>
                                <td><?= htmlspecialchars($user['nama_db']) ?><?= $user['is_resign'] ? ' <small>(RESIGN)</small>' : '' ?>
                                </td>
                                <td style="<?= $user['is_resign'] ? 'font-weight: bold;' : '' ?>">
                                    <?= htmlspecialchars($user['nip']) ?></td>
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