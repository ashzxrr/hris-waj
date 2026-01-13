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

// Handle save_notes: create table if missing and insert/update absence_notes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_notes'])) {
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
    echo "<script>alert('✅ {$save_msg}'); window.location.href='?page=attends';</script>";
    exit;
}

// Ambil data user dari mesin fingerprint
$users = getUsers($ip, $port, $key);

$database_users = [];
$database_users_by_id = [];
$query = "SELECT id, pin, tl_id, nip, nik, nama, jk, job_title, job_level, bagian, departemen, kategori_gaji FROM users ORDER BY CAST(pin AS UNSIGNED)";
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
        'kategori_gaji' => isset($database_users[$pin]) ? ($database_users[$pin]['kategori_gaji'] ?? '-') : '-',
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
            'kategori_gaji' => $data['kategori_gaji'] ?? '-',
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

// Build unique kategori_gaji options (case-insensitive dedupe)
$kategori_map = [];
foreach ($database_users as $row) {
    $k = trim($row['kategori_gaji'] ?? '');
    if ($k === '') continue;
    $key = mb_strtolower($k);
    if (!isset($kategori_map[$key])) {
        // preserve original casing from first occurrence
        $kategori_map[$key] = $k;
    }
}

// Ensure common categories exist (without duplicating)
$extras = ['Borongan Cabut', 'Borongan Cetak', 'Harian', 'Bulanan'];
foreach ($extras as $e) {
    $ek = mb_strtolower(trim($e));
    if ($ek === '') continue;
    if (!isset($kategori_map[$ek])) $kategori_map[$ek] = $e;
}

// Sort by key (case-insensitive order)
ksort($kategori_map);

// Create kategori_options array for use in template
$kategori_options = array_values($kategori_map);
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

        /* Dropdown styles for Kategori Gaji */
        #kategoriGajiDropdown {
            display: none !important;
        }

        #kategoriGajiDropdown.show {
            display: block !important;
        }

        /* Style untuk button Recap dengan warna ungu */
        .btn-recap {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-recap::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            pointer-events: none;
        }

        .btn-recap::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -30%;
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            pointer-events: none;
        }

        .btn-recap:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(139, 92, 246, 0.3);
            background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%);
        }

        .btn-recap:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-recap:hover .emoji {
            transform: translateX(3px);
        }
    </style>
    <script>
        let currentFilter = 'all';
    let currentKategoriGaji = []; // Changed to array for multiple selection
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
            // update bulk button state when select-all used
            if (typeof updateBulkButtonState === 'function') updateBulkButtonState();
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

    // Tambahkan variabel global
    let currentBagian = 'all';
    let currentTL = 'all';
    // resolved header index for 'Bagian' column (computed on DOM ready)
    let bagianIndex = null;

        // Update fungsi searchAndFilter
        function searchAndFilter() {
            const searchInput = (document.getElementById('searchInput').value || '').toLowerCase();
            const tableRows = document.querySelectorAll('tbody tr');
            let visibleCount = 0;

            tableRows.forEach(row => {
                // Resolve bagian cell using the computed bagianIndex (robust to column order changes)
                const bagianCell = (typeof bagianIndex === 'number' && row.cells[bagianIndex] && row.cells[bagianIndex].textContent)
                    ? row.cells[bagianIndex].textContent.toLowerCase()
                    : '';
                const matchBagian = currentBagian === 'all' || bagianCell === currentBagian.toLowerCase();

                // Use data attribute for tl id matching (more reliable than comparing names)
                const tlId = row.dataset.tlId ? String(row.dataset.tlId) : '';
                const matchTL = currentTL === 'all' || tlId === String(currentTL);

                // Kategori gaji filter (uses data attribute set on the row)
                const kategoriVal = row.dataset.kategoriGaji ? String(row.dataset.kategoriGaji).toLowerCase() : '';
                const matchKategori = currentKategoriGaji.length === 0 || currentKategoriGaji.some(k => kategoriVal === String(k).toLowerCase());

                // Existing search logic: check all visible cells
                const matchSearch = searchInput === '' || Array.from(row.cells).some(cell =>
                    (cell.textContent || '').toLowerCase().includes(searchInput)
                );

                // Check filter criteria
                const matchFilter = currentFilter === 'all' ||
                    (currentFilter === 'machine' && row.classList.contains('machine-only'));

                if (matchSearch && matchFilter && matchBagian && matchTL && matchKategori) {
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
            });

            document.getElementById('userCount').textContent = visibleCount;
            const selAll = document.querySelector('input[onchange="toggleAll(this)"]');
            if (selAll) selAll.checked = false;
            updateSelectedCount();
        }

        // Tambahkan fungsi filterByBagian
        function filterByBagian(bagian) {
            currentBagian = bagian;
            searchAndFilter();
        }

        function filterByKategoriGaji(kat) {
            const checkbox = event.target;
            if (checkbox.checked) {
                if (!currentKategoriGaji.includes(kat)) {
                    currentKategoriGaji.push(kat);
                }
            } else {
                currentKategoriGaji = currentKategoriGaji.filter(k => k !== kat);
            }
            searchAndFilter();
            updateKategoriGajiDisplay();
        }
        
        function clearAllKategoriGaji() {
            currentKategoriGaji = [];
            document.querySelectorAll('.kategori-checkbox').forEach(cb => cb.checked = false);
            searchAndFilter();
            updateKategoriGajiDisplay();
        }

        function toggleKategoriGajiDropdown() {
            const dropdown = document.getElementById('kategoriGajiDropdown');
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
        }

        function updateKategoriGajiDisplay() {
            const display = document.getElementById('kategoriGajiDisplay');
            if (display) {
                if (currentKategoriGaji.length === 0) {
                    display.textContent = 'Pilih Kategori Gaji';
                    display.style.color = '#6b7280';
                } else {
                    display.textContent = currentKategoriGaji.length + ' terpilih';
                    display.style.color = '#1f2937';
                }
            }
        }

        // Close dropdown when clicking outside - must be defined after page load
        document.addEventListener('DOMContentLoaded', function() {
            document.addEventListener('click', function(e) {
                const kategoriDropdown = document.getElementById('kategoriGajiDropdown');
                const filterDropdownBtn = e.target.closest('[onclick="toggleKategoriGajiDropdown()"]');
                
                if (kategoriDropdown && !kategoriDropdown.contains(e.target) && !filterDropdownBtn) {
                    kategoriDropdown.classList.remove('show');
                }
            });
        });

        function filterByTL(tl) {
            currentTL = tl;
            searchAndFilter();
        }
        function setFilter(filter) {
            currentFilter = filter;
            searchAndFilter();
        }
      // Event listeners
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('searchInput').addEventListener('input', searchAndFilter);

            // Add change event to all checkboxes
            document.querySelectorAll('input[name="selected_users[]"]').forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedCount);
            });

            // Resolve header index for 'Bagian' so search/filter uses correct column
            try {
                const ths = document.querySelectorAll('table thead th');
                for (let i = 0; i < ths.length; i++) {
                    const txt = (ths[i].textContent || '').trim().toLowerCase();
                    if (txt.includes('bagian')) {
                        bagianIndex = i;
                        break;
                    }
                }
            } catch (e) {
                bagianIndex = null;
            }

            // Run initial filter to apply defaults and update counts
            searchAndFilter();

            // Wire up date display -> hidden date input behavior
            const startDateInput = document.getElementById('startDate');
            const endDateInput = document.getElementById('endDate');
            const startDisplay = document.getElementById('startDateDisplay');
            const endDisplay = document.getElementById('endDateDisplay');

            if (startDateInput && startDisplay) {
                // When display clicked, open native date picker
                startDisplay.addEventListener('click', () => startDateInput.showPicker ? startDateInput.showPicker() : startDateInput.click());
                // Sync hidden -> display
                startDateInput.addEventListener('change', () => {
                    const d = new Date(startDateInput.value);
                    startDisplay.value = isNaN(d) ? '' : formatDateDisplay(d);
                });
            }

            if (endDateInput && endDisplay) {
                endDisplay.addEventListener('click', () => endDateInput.showPicker ? endDateInput.showPicker() : endDateInput.click());
                endDateInput.addEventListener('change', () => {
                    const d = new Date(endDateInput.value);
                    endDisplay.value = isNaN(d) ? '' : formatDateDisplay(d);
                });
            }

            // Set default dates (ISO for hidden inputs, dd/mm/YYYY for visible)
            const today = new Date();
            const todayISO = formatDateISO(today);
            const todayDisplay = formatDateDisplay(today);
            if (startDateInput) startDateInput.value = todayISO;
            if (endDateInput) endDateInput.value = todayISO;
            if (startDisplay) startDisplay.value = todayDisplay;
            if (endDisplay) endDisplay.value = todayDisplay;
        });

        function setToday() {
            const today = new Date();
            document.getElementById('startDate').value = formatDateISO(today);
            document.getElementById('endDate').value = formatDateISO(today);
            document.getElementById('startDateDisplay').value = formatDateDisplay(today);
            document.getElementById('endDateDisplay').value = formatDateDisplay(today);
            updateDateButtons(this);
        }

        function setCurrentMonth() {
            const now = new Date();
            // Set first day of current month
            const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
            // Set last day by getting day 0 of next month (which is last day of current month)
            const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

            document.getElementById('startDate').value = formatDateISO(firstDay);
            document.getElementById('endDate').value = formatDateISO(lastDay);
            document.getElementById('startDateDisplay').value = formatDateDisplay(firstDay);
            document.getElementById('endDateDisplay').value = formatDateDisplay(lastDay);
            updateDateButtons(this);
        }

        function setPreviousMonth() {
            const now = new Date();
            const firstDay = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            const lastDay = new Date(now.getFullYear(), now.getMonth(), 0);

            document.getElementById('startDate').value = formatDateISO(firstDay);
            document.getElementById('endDate').value = formatDateISO(lastDay);
            document.getElementById('startDateDisplay').value = formatDateDisplay(firstDay);
            document.getElementById('endDateDisplay').value = formatDateDisplay(lastDay);
            updateDateButtons(this);
        }

        function setCustomRange() {
            document.getElementById('startDate').click();
            updateDateButtons(this);
        }

        // ISO format yyyy-mm-dd (for hidden/native date inputs)
        function formatDateISO(date) {
            // Build yyyy-mm-dd using local date components to avoid timezone/UTC shifts
            const d = String(date.getDate()).padStart(2, '0');
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const y = date.getFullYear();
            return `${y}-${m}-${d}`;
        }

        // Display format dd/mm/yyyy
        function formatDateDisplay(date) {
            const d = String(date.getDate()).padStart(2, '0');
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const y = date.getFullYear();
            return `${d }/${m}/${y}`;
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
    <h2>📄 Absensi Karyawan</h2>
    <form method="POST" action="?page=detail-attends" onsubmit="return validateForm()" id="absenForm">
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

            <!-- Period Section -->
            <div class="period-container">
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
                        <button type="button" class="date-btn" onclick="setPreviousMonth()">Bulan Lalu</button>
                        <button type="submit" name="detailBtn" value="1" id="submitBtn" class="btn-primary">
                            <div class="spinner"></div>
                            Detail Absensi <span class="emoji">➡️</span>
                        </button>
                        <button type="submit" name="recapBtn" value="1" id="submitBtn" class="btn-recap">
                            <div class="spinner"></div>
                            Detail Rekap <span class="emoji">📊</span>
                        </button>
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
                            <option value="48">M.Jamaludin</option>
                            <option value="99">Nila Widya Sari</option>
                            <option value="113">Nurul Izzuddin</option>
                        </optgroup>

                        <optgroup label="Dan Lain lain">
                            <option value="1">Anik</option>
                            <option value="98">M Gaung Sidiq</option>
                            <option value="40">Cankiswan</option>
                            <option value="118">Kerinna</option>
                            <option value="63">Puput Indarwati</option>
                        </optgroup>
                    </select>
                </div>
                <div class="filter-dropdown" style="position: relative;">
                    <button type="button" onclick="toggleKategoriGajiDropdown()" style="width: 100%; padding: 10px 12px; background: white; border: 1px solid #cbd5e1; border-radius: 8px; text-align: left; font-size: 0.9rem; cursor: pointer; display: flex; justify-content: space-between; align-items: center; color: #6b7280;">
                        <span id="kategoriGajiDisplay">Pilih Kategori Gaji</span>
                        <span style="font-size: 1.2rem;">▼</span>
                    </button>
                    
                    <div id="kategoriGajiDropdown" style="position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #cbd5e1; border-radius: 8px; margin-top: 8px; padding: 10px; max-height: 300px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); display: none;" class="dropdown-menu">
                        <div style="margin-bottom: 8px;">
                            <button type="button" class="filter-btn" onclick="clearAllKategoriGaji()" style="width: 100%; text-align: left; padding: 8px 10px; background: #f1f5f9; color: #475569; font-size: 0.85rem; border: none; border-radius: 4px; cursor: pointer;">Bersihkan Pilihan</button>
                        </div>
                        <div style="border-top: 1px solid #e2e8f0; padding-top: 8px;">
                            <?php 
                            $all_categories = array_unique(array_merge( ['Borongan Cabut', 'Borongan Cetak', 'Harian', 'Bulanan']));
                            sort($all_categories);
                            foreach ($all_categories as $kat): 
                            ?>
                                <div style="margin-bottom: 6px;">
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.9rem; padding: 4px 6px; border-radius: 4px; transition: background 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'">
                                        <input type="checkbox" class="kategori-checkbox" value="<?= htmlspecialchars($kat) ?>" onchange="filterByKategoriGaji(this.value)">
                                        <span><?= htmlspecialchars($kat) ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <!-- Bulk Add S/I/A button (enabled only for DB users) -->
                <div class="action-buttons">
                    <button type="button" id="addNoteBtn" class="btn-secondary" disabled onclick="openBulkNoteModal()">
                        <span class="emoji">�</span>
                        <span class="button-text">Tambah S/I/A</span>
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
                        <th class="pin-col">PIN</th>
                        <th>Nama (Database)</th>
                        <th>NIP</th>
                        <th>NIK</th>
                        <th>Gender</th>
                        <th>Jabatan</th>
                        <th>Level</th>
                        <th>Bagian</th>
                        <th>Kategori Gaji</th>
                        <th>Departemen</th>
                        <th>TL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($combined_users as $user): ?>
                        <tr class="user-row <?= (!$user['in_database']) ? 'machine-only' : '' ?> <?= $user['is_resign'] ? 'resign' : '' ?>"
                            data-status="<?= $user['in_machine'] && $user['in_database'] ? 'both' : ($user['in_machine'] ? 'machine' : 'database') ?>"
                            data-tl-id="<?= htmlspecialchars($user['tl_id'] ?? '') ?>"
                            data-kategori-gaji="<?= htmlspecialchars($user['kategori_gaji'] ?? '-') ?>">
                            <td class="checkbox-col">
                                <?php if ($user['in_database'] && !$user['is_resign']): ?>
                                    <input type="checkbox" name ="selected_users[]" value="<?= htmlspecialchars($user['pin']) ?>"
                                        class="user-checkbox <?= !$user['in_machine'] ? 'db-only' : '' ?>">
                                <?php elseif ($user['is_resign']): ?>
                                    <input type="checkbox" name="selected_users[]" value="<?= htmlspecialchars($user['pin'])?>"
                                       class="user-checkbox" disabled title="User dengan status RESIGN tidak dapat dipilih">
                                <?php else: ?>
                                    <span style="color: #ccc;">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="pin-col"><?= htmlspecialchars($user['pin']) ?></td>
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
                            <td><?= htmlspecialchars($user['kategori_gaji'] ?? '-') ?></td>
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

<!-- Row-level modal for single-pin absence note -->
<div id="rowNoteModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.35); align-items:center; justify-content:center; z-index:9999;">
    <div style="background:#fff; padding:18px; border-radius:10px; width:420px; max-width:95%; box-shadow:0 8px 30px rgba(2,6,23,0.3); margin:auto;">
        <h3 style="margin:0 0 10px 0;">Tambah Keterangan Absensi</h3>
        <form id="rowNoteForm" method="POST" action="?page=attends">
            <input type="hidden" name="save_notes" value="1">
            <div style="margin-bottom:10px;">
                <label style="display:block; font-weight:600; margin-bottom:6px;">Tanggal</label>
                <input type="date" id="rowNoteDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div style="margin-bottom:10px;">
                <label style="display:block; font-weight:600; margin-bottom:6px;">Kode</label>
                <select id="rowNoteCode" class="form-control" required>
                    <option value="">Pilih kode...</option>
                    <option value="S">S - Sakit</option>
                    <option value="I">I - Izin</option>
                    <option value="A">A - Alfa</option>
                </select>
            </div>
            <div id="rowNoteInfo" style="font-size:13px;color:#444;margin-bottom:12px;"></div>
            <div style="display:flex; gap:8px; justify-content:flex-end">
                <button type="button" onclick="closeRowNoteModal()" class="cancel-link">Batal</button>
                <button type="button" class="btn-primary" onclick="submitRowNote()">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
    let currentRowPin = null;

    document.addEventListener('click', function (e) {
        if (e.target && e.target.classList.contains('row-aksi-btn')) {
            const pin = e.target.dataset.pin;
            openRowNoteModal(pin);
        }
    });

    function openRowNoteModal(pin) {
        currentRowPin = pin;
        const modal = document.getElementById('rowNoteModal');
        const dateInput = document.getElementById('rowNoteDate');
        const codeSelect = document.getElementById('rowNoteCode');
        const info = document.getElementById('rowNoteInfo');
        dateInput.value = new Date().toISOString().slice(0,10);
        codeSelect.value = '';
        info.textContent = `Menambah keterangan untuk PIN: ${pin}`;
        modal.style.display = 'flex';
    }

    function closeRowNoteModal() {
        currentRowPin = null;
        document.getElementById('rowNoteModal').style.display = 'none';
    }

    function submitRowNote() {
        const dateInput = document.getElementById('rowNoteDate');
        const codeSelect = document.getElementById('rowNoteCode');
        if (!currentRowPin) return alert('Tidak ada PIN terpilih');
        if (!dateInput.value || !codeSelect.value) return alert('Lengkapi tanggal dan kode.');

        // Build a temporary form to submit absence_notes[PIN][DATE]=CODE
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '?page=attends';

        const saveInput = document.createElement('input');
        saveInput.type = 'hidden';
        saveInput.name = 'save_notes';
        saveInput.value = '1';
        form.appendChild(saveInput);

        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = `absence_notes[${currentRowPin}][${dateInput.value}]`;
        hidden.value = codeSelect.value;
        form.appendChild(hidden);

        document.body.appendChild(form);
        form.submit();
    }

    // Bulk helpers
    function updateSelectedCount() {
        const checked = document.querySelectorAll('input[name="selected_users[]"]:checked:not(.hidden):not(:disabled)');
        document.getElementById('selectedCount').textContent = checked.length;
        updateBulkButtonState();
    }

    function updateBulkButtonState() {
        const btn = document.getElementById('addNoteBtn');
        if (!btn) return;
        const has = document.querySelectorAll('input[name="selected_users[]"]:checked:not(.hidden):not(:disabled)').length > 0;
        btn.disabled = !has;
    }

    function openBulkNoteModal() {
        const checkedEls = Array.from(document.querySelectorAll('input[name="selected_users[]"]:checked:not(.hidden):not(:disabled)'));
        if (checkedEls.length === 0) {
            alert('Pilih minimal satu user untuk menambah S/I/A');
            return;
        }
        const pins = checkedEls.map(c => c.value);

        // Create a simple modal (reusable)
        let modal = document.getElementById('bulkNoteModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'bulkNoteModal';
            modal.style = 'display:none; position:fixed; inset:0; background:rgba(0,0,0,0.35); align-items:center; justify-content:center; z-index:9999;';
            modal.innerHTML = `
                <div style="background:#fff; padding:18px; border-radius:10px; width:520px; max-width:95%; box-shadow:0 8px 30px rgba(2,6,23,0.3); margin:auto;">
                    <h3 style="margin:0 0 10px 0;">Tambah Keterangan Absensi (Bulk)</h3>
                    <form id="bulkNoteForm" method="POST" action="?page=attends">
                        <input type="hidden" name="save_notes" value="1">
                        <div style="margin-bottom:10px;">
                            <label style="display:block; font-weight:600; margin-bottom:6px;">Tanggal</label>
                            <input type="date" id="bulkNoteDate" class="form-control" value="` + new Date().toISOString().slice(0,10) + `" required>
                        </div>
                        <div style="margin-bottom:10px;">
                            <label style="display:block; font-weight:600; margin-bottom:6px;">Kode</label>
                            <select id="bulkNoteCode" name="_bulk_code" class="form-control" required>
                                <option value="">Pilih kode...</option>
                                <option value="S">S - Sakit</option>
                                <option value="I">I - Izin</option>
                                <option value="A">A - Alfa</option>
                            </select>
                        </div>
                        <div id="bulkNotePins" style="font-size:13px;color:#444;margin-bottom:12px; max-height:120px; overflow:auto;"></div>
                        <div style="display:flex; gap:8px; justify-content:flex-end">
                            <button type="button" onclick="closeBulkNoteModal()" class="cancel-link">Batal</button>
                            <button type="button" class="btn-primary" onclick="submitBulkNote()">Simpan</button>
                        </div>
                    </form>
                </div>
            `;
            document.body.appendChild(modal);
        }

        // Populate pins list
        const pinsContainer = modal.querySelector('#bulkNotePins');
        pinsContainer.innerHTML = `<strong>Menambah keterangan untuk ${pins.length} user:</strong><br>` + pins.map(p => `<code style="display:inline-block;margin:4px;padding:4px 8px;background:#f4f4f4;border-radius:6px">${p}</code>`).join(' ');

        modal.style.display = 'flex';
        modal.dataset.pins = JSON.stringify(pins);
    }

    function closeBulkNoteModal() {
        const modal = document.getElementById('bulkNoteModal');
        if (modal) modal.style.display = 'none';
    }

    function submitBulkNote() {
        const modal = document.getElementById('bulkNoteModal');
        if (!modal) return;
        const pins = JSON.parse(modal.dataset.pins || '[]');
        const dateInput = document.getElementById('bulkNoteDate');
        const codeSelect = document.getElementById('bulkNoteCode');
        if (!dateInput.value || !codeSelect.value) return alert('Lengkapi tanggal dan kode.');

        // Build form and submit absence_notes[PIN][DATE]=CODE for each pin
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '?page=attends';

        const saveInput = document.createElement('input');
        saveInput.type = 'hidden';
        saveInput.name = 'save_notes';
        saveInput.value = '1';
        form.appendChild(saveInput);

        pins.forEach(pin => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = `absence_notes[${pin}][${dateInput.value}]`;
            hidden.value = codeSelect.value;
            form.appendChild(hidden);
        });

        document.body.appendChild(form);
        form.submit();
    }

    // Ensure checkbox change events update counts
    document.addEventListener('change', function (e) {
        if (e.target && e.target.matches('input[name="selected_users[]"]')) {
            updateSelectedCount();
        }
    });

    // Initialize selected count on load
    document.addEventListener('DOMContentLoaded', function () {
        updateSelectedCount();
    });

    // Handle different submit buttons
    document.querySelector('button[name="detailBtn"]').addEventListener('click', function() {
        document.getElementById('absenForm').action = '?page=detail-attends';
    });
    document.querySelector('button[name="recapBtn"]').addEventListener('click', function() {
        document.getElementById('absenForm').action = '?page=recap-attends';
    });
</script>