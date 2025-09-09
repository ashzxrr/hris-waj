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
            return `${d}/${m}/${y}`;
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
    <form method="POST" action="?page=users-detail" onsubmit="return validateForm()" id="absenForm">
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
                        <!-- hidden native date inputs (used for form submit and native picker) -->
                        <input type="date" id="startDate" name="tanggal_dari" style="display:none;"
                            value="<?= date('Y-m-d') ?>">
                        <!-- visible display in dd/mm/yyyy -->
                        <input type="text" id="startDateDisplay" class="date-input" readonly
                            value="<?= date('d/m/Y') ?>">
                        <span>s/d</span>
                        <input type="date" id="endDate" name="tanggal_sampai" style="display:none;"
                            value="<?= date('Y-m-d') ?>">
                        <input type="text" id="endDateDisplay" class="date-input" readonly value="<?= date('d/m/Y') ?>">
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
                <!-- Ganti div filter-buttons dengan kode berikut -->
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
                            <option value="34">Patur Albertino</option>
                        </optgroup>

                        <optgroup label="Dan Lain lain">
                            <option value="1">Anik</option>
                            <option value="98">M Gaung Sidiq</option>
                            <option value="40">Cankiswa</option>
                            <option value="118">Kerinna</option>
                        </optgroup>
                    </select>
                </div>
                <!-- Ganti button add user yang lama dengan yang baru -->
                <div class="action-buttons">
                    <button type="submit" name="addUserBtn" value="1" id="addUserBtn" class="btn-secondary"
                        onclick="return validateAddUsers()" formaction="?page=users-add" disabled>
                        <span class="emoji">👤</span>
                        <span class="button-text">Tambah ke Database</span>
                        <div class="spinner" style="display: none;"></div>
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