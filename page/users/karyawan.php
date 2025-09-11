<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();

}
// Proteksi: redirect ke login jika belum login
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

$database_users = [];
$database_users_by_id = [];
// Ambil semua data user dari database (termasuk yang NIP-nya RESIGN untuk styling)
$query = "SELECT id, pin, tl_id, nip, nik, nama, jk, job_title, job_level, bagian, departemen FROM users ORDER BY CAST(pin AS UNSIGNED)";
$result = mysqli_query($mysqli, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $database_users[$row['pin']] = $row;
        $database_users_by_id[$row['id']] = $row;
    }
}

// Gabungkan data dari mesin dan database
$combined_users = [];

// 1. Tambahkan semua user dari mesin fingerprint
foreach ($users as $pin => $name) {
    $tl_id_val = isset($database_users[$pin]) ? ($database_users[$pin]['tl_id'] ?? null) : null;
    $tl_name_val = ($tl_id_val && isset($database_users_by_id[$tl_id_val])) ? $database_users_by_id[$tl_id_val]['nama'] : '-';

    $combined_users[$pin] = [
        'pin' => $pin,
        'id' => isset($database_users[$pin]) ? $database_users[$pin]['id'] : null,
        'tl_id' => $tl_id_val,
        'tl_name' => $tl_name_val,
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
        $tl_id_val = $data['tl_id'] ?? null;
        $tl_name_val = ($tl_id_val && isset($database_users_by_id[$tl_id_val])) ? $database_users_by_id[$tl_id_val]['nama'] : '-';

        $combined_users[$pin] = [
            'pin' => $pin,
            'id' => $data['id'],
            'tl_id' => $tl_id_val,
            'tl_name' => $tl_name_val,
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

<?php
// Build TL options: collect unique tl_id values referenced in users and map to names
$tl_options = [];
foreach ($database_users as $row) {
    if (!empty($row['tl_id'])) {
        $tid = $row['tl_id'];
        if (isset($database_users_by_id[$tid])) {
            $tl_options[$tid] = $database_users_by_id[$tid]['nama'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/style-user.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            justify-content: space-between;
            gap: 15px;
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

        .filter-dropdown {
            position: relative;
            flex: 1;
            min-width: 180px;
        }

        .filter-select {
            width: 100%;
            padding: 10px 16px;
            background: #e2e8f0;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: #475569;
            cursor: pointer;
            appearance: none;
        }

        .filter-select:hover {
            background: #cbd5e1;
            border-color: #94a3b8;
        }

        .filter-select:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(69, 202, 255, 0.3);
        }

        .filter-dropdown::after {
            content: '▼';
            font-size: 0.7rem;
            color: #475569;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
        }

        .filter-container {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .filter-select {
            min-width: 200px;
        }

        .filter-select optgroup {
            font-weight: 600;
            color: #64748b;
            background: white;
        }

        .filter-select option {
            padding: 4px 8px;
            color: #475569;
        }

        .filter-select {
            height: 32px;
            padding: 0 35px 0 15px;
            min-width: 180px;
        }

        /* Tambahkan CSS berikut di dalam tag <style> */
        .action-buttons {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #34d399 0%, #059669 100%);
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            height: 32px;
            padding: 0 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin: 0;
            /* Reset margin */
        }

        .btn-secondary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(52, 211, 153, 0.3);
            background: linear-gradient(135deg, #059669 0%, #34d399 100%);
        }

        .btn-secondary:disabled {
            background: linear-gradient(135deg, #9ca3af, #d1d5db);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-secondary .emoji {
            font-size: 14px;
        }

        /* Tambahkan/update CSS berikut */
        .top-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 20px;
        }

        .stats-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            flex: 1;
        }

        .date-container {
            min-width: 300px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .stat-box {
            flex: 1;
            min-width: 120px;
            padding: 12px;
        }

        /* Tambahkan/update CSS berikut */
        .main-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 20px;
        }

        .stats-wrapper {
            display: flex;
            gap: 8px;
            padding: 8px;
            max-width: 400px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 12px;
        }

        .stat-box {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 6px 8px;
            text-align: center;
            flex: 1;
            min-width: 70px;
        }

        .stat-box.resign {
            background: #fef2f2;
            border-color: #fecaca;
        }

        .stat-icon {
            font-size: 14px;
            margin-bottom: 2px;
            opacity: 0.7;
        }

        .stat-number {
            font-size: 16px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1px;
            line-height: 1;
        }

        .stat-label {
            font-size: 9px;
            font-weight: 500;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin: 0;
        }

        .stat-box.resign .stat-number {
            color: #dc2626;
        }

        .stat-box.resign .stat-label {
            color: #b91c1c;
        }

        .period-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        /* Update form style */
        #absenForm {
            width: 100%;
        }

        /* Fix date container */
        .date-container {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
    </style>
    <script>

        let currentFilter = 'all';
        document.addEventListener('DOMContentLoaded', function () {
            <?php if (isset($_SESSION['login_warning'])): ?>
                Swal.fire({
                    icon: 'warning',
                    title: 'Peringatan!',
                    text: '<?php echo $_SESSION['login_warning']; ?>',
                    confirmButtonText: 'OK'
                });
                <?php unset($_SESSION['login_warning']); ?>
            <?php endif; ?>
        });
        function toggleAll(source) {
            const checkboxes = document.querySelectorAll('input[name="selected_users[]"]:not(.hidden):not(:disabled)');
            checkboxes.forEach(checkbox => checkbox.checked = source.checked);
            updateSelectedCount();
        }

        function validateForm() {
            const checkboxes = document.querySelectorAll('input[name="selected_users[]"]:checked:not(.hidden)');

            if (checkboxes.length === 0) {
                alert('⚠️ Pilih minimal satu user!');
                return false;
            }

            return true;
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
                const namaMesin = row.cells[3].textContent.trim();

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

        // showLoading / hideLoading removed (attendance submission UI not needed on employee-only page)

        // Tambahkan variabel global
        let currentBagian = 'all';
        let currentTL = 'all';

        // Update fungsi searchAndFilter
        function searchAndFilter() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const tableRows = document.querySelectorAll('tbody tr');
            let visibleCount = 0;

            tableRows.forEach(row => {
                const bagianCell = (row.cells[10] && row.cells[10].textContent) ? row.cells[10].textContent.toLowerCase() : '';
                const matchBagian = currentBagian === 'all' || bagianCell === currentBagian.toLowerCase();

                // Use data attribute for tl id matching (more reliable than comparing names)
                const tlId = row.dataset.tlId ? String(row.dataset.tlId) : '';
                const matchTL = currentTL === 'all' || tlId === String(currentTL);

                // Existing search logic
                const matchSearch = Array.from(row.cells).some(cell =>
                    cell.textContent.toLowerCase().includes(searchInput)
                );

                // Check filter criteria
                const matchFilter = currentFilter === 'all' ||
                    (currentFilter === 'machine' && row.classList.contains('machine-only'));

                if (matchSearch && matchFilter && matchBagian && matchTL) {
                    row.style.display = '';
                    row.classList.remove('hidden');
                    const checkbox = row.querySelector('input[type="checkbox"]');
                    if (checkbox) checkbox.classList.remove('hidden');
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                    row.classList.add('hidden');
                    const checkbox = row.querySelector('input[type="checkbox"]');
                    if (checkbox) {
                        checkbox.classList.add('hidden');
                        checkbox.checked = false;
                    }
                }

                function validateEditUsers() {
                    const checked = Array.from(document.querySelectorAll('input[name="selected_users[]"]:checked:not(.hidden)'));
                    const dbSelected = checked.filter(cb => !cb.closest('tr').classList.contains('machine-only'));

                    if (dbSelected.length === 0) {
                        alert('⚠️ Pilih minimal satu user yang sudah ada di database untuk diedit.');
                        return false;
                    }

                    const form = document.getElementById('absenForm');
                    // remove old user_data inputs
                    const old = form.querySelectorAll('input[name^="user_data"]');
                    old.forEach(i => i.remove());

                    dbSelected.forEach((cb, idx) => {
                        const tr = cb.closest('tr');
                        const pin = cb.value;
                        // create hidden input user_data[idx][pin]
                        const inpPin = document.createElement('input');
                        inpPin.type = 'hidden';
                        inpPin.name = `user_data[${idx}][pin]`;
                        inpPin.value = pin;
                        form.appendChild(inpPin);

                        // Robustly find the NIP cell by header name (fallback to index 5)
                        let nipVal = '';
                        try {
                            let nipIndex = -1;
                            const thead = document.querySelector('table thead');
                            if (thead) {
                                const ths = thead.querySelectorAll('th');
                                ths.forEach((th, i) => {
                                    if (th.textContent.trim().toLowerCase() === 'nip') nipIndex = i;
                                });
                            }
                            if (nipIndex === -1) nipIndex = 5; // legacy fallback
                            const nipCell = tr.cells[nipIndex];
                            if (nipCell) nipVal = nipCell.textContent.trim();
                        } catch (e) {
                            nipVal = '';
                        }
                        // normalize placeholder values
                        if (nipVal === '-' || nipVal === '') nipVal = '';
                        const inpNip = document.createElement('input');
                        inpNip.type = 'hidden';
                        inpNip.name = `user_data[${idx}][nip]`;
                        inpNip.value = nipVal;
                        form.appendChild(inpNip);
                    });

                    // allow form to submit to edit page
                    return true;
                }
            });

            document.getElementById('userCount').textContent = visibleCount;
            document.querySelector('input[onchange="toggleAll(this)"]').checked = false;
            updateSelectedCount();
        }

        // Tambahkan fungsi filterByBagian
        function filterByBagian(bagian) {
            currentBagian = bagian;
            searchAndFilter();
        }

        function filterByTL(tl) {
            currentTL = tl;
            searchAndFilter();
        }
        function setFilter(filter) {
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
            // Update edit button state
            updateEditButton();
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

        // Enable/disable Edit button: only enable when selected users exist AND are in database
        function updateEditButton() {
            const editBtn = document.getElementById('editUserBtn');
            const checked = Array.from(document.querySelectorAll('input[name="selected_users[]"]:checked:not(.hidden)'));
            // Only allow editing users that are in database (rows without class machine-only)
            const dbSelected = checked.filter(cb => !cb.closest('tr').classList.contains('machine-only'));
            if (dbSelected.length > 0) {
                editBtn.disabled = false;
                editBtn.innerHTML = `<span class="emoji">✏️</span> Edit ${dbSelected.length} User`;
            } else {
                editBtn.disabled = true;
                editBtn.innerHTML = `<span class="emoji">✏️</span> Edit User`;
            }
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('searchInput').addEventListener('input', searchAndFilter);

            // Add change event to all checkboxes
            document.querySelectorAll('input[name="selected_users[]"]').forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedCount);
            });

            // Date/attendance UI removed for employee-only page
        });

        // Date helpers removed
    </script>
</head>

<body>
    <h2>👥 Data User Fingerprint & Database</h2>
    <form method="POST" id="absenForm">
        <div class="main-container">
            <!-- Stats Section -->
            <div class="stats-wrapper">
                <div class="stat-box">
                    <div class="stat-icon">👥</div>
                    <div class="stat-number"><?= $total_users ?></div>
                    <div class="stat-label">Total Karyawan</div>
                </div>
                <div class="stat-box">
                    <div class="stat-icon">💾</div>
                    <div class="stat-number"><?= count(array_filter($combined_users, function ($user) {
                        return $user['in_database'] && !$user['is_resign'];
                    })) ?></div>
                    <div class="stat-label">Database</div>
                </div>
                <div class="stat-box">
                    <div class="stat-icon">🖥️</div>
                    <div class="stat-number"><?= $users_machine_only ?></div>
                    <div class="stat-label">Mesin</div>
                </div>
                <?php if ($resign_count > 0): ?>
                    <div class="stat-box resign">
                        <div class="stat-icon">🚪</div>
                        <div class="stat-number"><?= $resign_count ?></div>
                        <div class="stat-label">Resign</div>
                    </div>
                <?php endif; ?>
            </div>


            <!-- Filters grouped with search -->
            <div class="filters-group" style="display:flex;gap:8px;align-items:center;margin:8px 0;flex-wrap:wrap">
                <div class="filter-dropdown">
                    <select class="filter-select" onchange="setFilter(this.value)">
                        <option value="all">👥 Semua User</option>
                        <option value="machine">🖥️ Hanya di Mesin</option>
                    </select>
                </div>
                <div class="filter-dropdown">
                    <select class="filter-select" id="bagianFilter" onchange="filterByBagian(this.value)">
                        <option value="all">🏢 Semua Bagian</option>
                        <optgroup label="Produksi">
                            <option value="Bahan Baku">Bahan Baku</option>
                            <option value="Cabut">Cabut</option>
                            <option value="Dry A">Dry A</option>
                            <option value="Dry B & HCR">Dry B & HCR</option>
                            <option value="Moulding">Moulding</option>
                            <option value="HCR Moulding">HCR Moulding</option>
                            <option value="Moulding Indomie">Moulding Indomie</option>
                            <option value="Cuci Bersih">Cuci Bersih</option>
                            <option value="Cuci Kotor">Cuci Kotor</option>
                            <option value="Rambang">Rambang</option>
                            <option value="Cutter & Flek">Cutter & Flek</option>
                            <option value="Packing">Packing</option>
                            <option value="Grading">Grading</option>
                            <option value="Final Grading">Final Grading</option>
                        </optgroup>
                        <optgroup label="Administrasi">
                            <option value="Admin">Admin</option>
                            <option value="Admin Cabut & Bahan Baku">Admin Cabut & Bahan Baku</option>
                            <option value="Admin Drying & Moulding">Admin Drying & Moulding</option>
                            <option value="Admin Packing">Admin Packing</option>
                            <option value="Admin Cabut">Admin Cabut</option>
                            <option value="Administrasi">Administrasi</option>
                            <option value="Finance Accounting">Finance Accounting</option>
                            <option value="Kasir Perusahaan">Kasir Perusahaan</option>
                        </optgroup>
                        <optgroup label="Supervisor & Manager">
                            <option value="Manager Produksi">Manager Produksi</option>
                            <option value="SPV">SPV</option>
                            <option value="TL Pre Cleaning">TL Pre Cleaning</option>
                            <option value="Checker Moulding">Checker Moulding</option>
                        </optgroup>
                        <optgroup label="Support">
                            <option value="Security">Security</option>
                            <option value="Sanitasi">Sanitasi</option>
                            <option value="Driver">Driver</option>
                            <option value="Maintenance">Maintenance</option>
                            <option value="Maintenance IT">Maintenance IT</option>
                            <option value="CCP 1">CCP 1</option>
                            <option value="Prewash">Prewash</option>
                        </optgroup>
                    </select>
                </div>
                <div class="filter-dropdown">
                    <select class="filter-select" id="tlFilter" onchange="filterByTL(this.value)">
                        <option value="all">👥 Semua TL</option>

                        <optgroup label="CABUT">
                            <option value="8">Karyawati</option>
                            <option value="3">Sri Utami</option>
                            <option value="2">ST Nur Farokah</option>
                            <option value="25">Fhilis Sulestari</option>
                            <option value="22">Muhammad Regatana Hidayatulloh</option>
                            <option value="119">Zusita Arsdhia Indrayani</option>
                            <option value="34">Wahyu Surodo</option>
                            <option value="60">Lutfi Dwi Firmansyah</option>
                            <option value="109">Ruliatul Fidiah</option>
                        </optgroup>

                        <optgroup label="Cetak">
                            <option value="57">Muhammad Tamamur Ridlwan</option>
                            <option value="53">Abdul Rouf Khoiri</option>
                            <option value="7">Anita</option>
                            <option value="24">Patur Albertino</option>
                            <option value="27">Anas Ja'far</option>
                            <option value="48"> M.Jamaludin</option>
                            <option value="99">Nila Widya Sari</option>
                            <option value="113">Nurul Izzuddin</option>
                        </optgroup>

                        <optgroup label="Dan Lain lain">
                            <option value="1">Anik</option>
                            <option value="98">M Gaung Sidiq</option>
                            <option value="40">Cankiswan</option>
                            <option value="118">Kerinna</option>
                        </optgroup>
                    </select>
                </div>
            </div>

            <div class="search-container">
                <div>
                    <input type="text" id="searchInput" class="search-input"
                        placeholder=" 🔍 Ketik PIN, Nama, NIP, atau Bagian..." />
                </div>
                <div style="color: #666; font-size: 12px;">
                    <span id="userCount"><?= count($combined_users) ?></span> user ditampilkan |
                    <span id="selectedCount">0</span> dipilih
                </div>
            </div>
        </div>

        <div class="select-all">
            <div class="d-flex align-items-center gap-2" style="justify-content: space-between">
                <div class="d-flex align-items-center gap-2">
                    <label>
                        <input type="checkbox" onchange="toggleAll(this)">
                    </label>
                    <div class="small-legend">
                        <div class="legend-item">
                            <div class="legend-color legend-machine-only"></div>
                            <span>Tambahkan</span>
                        </div>
                        <?php if ($resign_count > 0): ?>
                            <div class="legend-item resign-legend">
                                <div class="legend-color legend-resign"></div>
                                <span>User Resign</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Ganti div filter-buttons dengan kode berikut -->

                <!-- Ganti button add user yang lama dengan yang baru -->
                <div class="action-buttons">
                    <button type="submit" name="addUserBtn" value="1" id="addUserBtn" class="btn-secondary"
                        onclick="return validateAddUsers()" formaction="?page=users-add" disabled>
                        <span class="emoji">👤</span>
                        <span class="button-text">Tambah ke Database</span>
                        <div class="spinner" style="display: none;"></div>
                    </button>
                    <!-- Edit existing database users (enabled only when selected users exist in database) -->
                    <button type="submit" name="editUserBtn" value="1" id="editUserBtn" class="btn-secondary"
                        onclick="return validateEditUsers()" formaction="?page=edit-karyawan" disabled
                        style="margin-left:8px;">
                        <span class="emoji">✏️</span>
                        <span class="button-text">Edit User</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="table-container"></div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th class="checkbox-col">✓</th>
                        <th>ID</th>
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
                        <th>TL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($combined_users as $user): ?>
                        <tr class="user-row <?= (!$user['in_database']) ? 'machine-only' : '' ?> <?= $user['is_resign'] ? 'resign' : '' ?>"
                            data-status="<?= $user['in_machine'] && $user['in_database'] ? 'both' : ($user['in_machine'] ? 'machine' : 'database') ?>"
                            data-tl-id="<?= htmlspecialchars($user['tl_id'] ?? '') ?>">
                            <td class="checkbox-col">
                                <?php if ($user['in_machine'] && !$user['is_resign']): ?>
                                    <input type="checkbox" name="selected_users[]" value="<?= htmlspecialchars($user['pin']) ?>"
                                        class="user-checkbox <?= !$user['in_database'] ? 'add-user' : '' ?>">
                                <?php elseif ($user['is_resign']): ?>
                                    <input type="checkbox" name="selected_users[]" value="<?= htmlspecialchars($user['pin']) ?>"
                                        class="user-checkbox" disabled title="User dengan status RESIGN tidak dapat dipilih">
                                <?php else: ?>
                                    <span style="color: #ccc;">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($user['id'] ?? '-') ?></td>
                            <td class="pin-col"><?= htmlspecialchars($user['pin']) ?></td>
                            <td><?= htmlspecialchars($user['nama_mesin']) ?></td>
                            <td><?= htmlspecialchars($user['nama_db']) ?><?= $user['is_resign'] ? ' <small>(RESIGN)</small>' : '' ?>
                            </td>
                            <td style="<?= $user['is_resign'] ? 'font-weight: bold;' : '' ?>">
                                <?= htmlspecialchars($user['nip']) ?>
                            </td>
                            <td><?= htmlspecialchars($user['nik']) ?></td>
                            <td><?= htmlspecialchars($user['jk']) ?></td>
                            <td><?= htmlspecialchars($user['job_title']) ?></td>
                            <td><?= htmlspecialchars($user['job_level']) ?></td>
                            <td><?= htmlspecialchars($user['bagian']) ?></td>
                            <td><?= htmlspecialchars($user['departemen']) ?></td>
                            <td><?= htmlspecialchars($user['tl_name'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
    </div>
</body>

</html>