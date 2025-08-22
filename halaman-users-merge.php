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
        $database_users[$row['pin']] = [
            'nip' => $row['nip'],
            'nik' => $row['nik'], 
            'nama' => $row['nama'],
            'jk' => $row['jk'],
            'job_title' => $row['job_title'],
            'job_level' => $row['job_level'],
            'bagian' => $row['bagian'],
            'departemen' => $row['departemen']
        ];
    }
}

// Gabungkan data dari mesin dan database
$combined_users = [];

// Tambahkan user dari mesin fingerprint
foreach ($users as $pin => $name) {
    // Skip jika user ada di database dengan NIP resign (case insensitive)
    if (isset($database_users[$pin]) && isset($database_users[$pin]['nip']) && 
        strtolower(trim($database_users[$pin]['nip'])) === 'resign') {
        continue;
    }
    
    // Skip jika user tidak ada di database (yang akan menghasilkan data "-")
    if (!isset($database_users[$pin])) {
        continue;
    }
    
    $combined_users[$pin] = [
        'pin' => $pin,
        'nama_mesin' => $name,
        'nama_db' => $database_users[$pin]['nama'],
        'nip' => $database_users[$pin]['nip'],
        'nik' => $database_users[$pin]['nik'],
        'jk' => $database_users[$pin]['jk'],
        'job_title' => $database_users[$pin]['job_title'],
        'job_level' => $database_users[$pin]['job_level'],
        'bagian' => $database_users[$pin]['bagian'],
        'departemen' => $database_users[$pin]['departemen'],
        'status' => 'Ada di Database',
        'in_machine' => true,
        'in_database' => true
    ];
}

// Tambahkan user yang ada di database tapi tidak di mesin
foreach ($database_users as $pin => $data) {
    // Skip user dengan NIP resign (sudah difilter di query, tapi double check)
    if (strtolower(trim($data['nip'])) === 'resign') {
        continue;
    }
    
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
uksort($combined_users, function($a, $b) {
    return (int)$a - (int)$b;
});

// Hitung statistik
$total_users = count($combined_users);
$users_both = count(array_filter($combined_users, function($user) { return $user['in_machine'] && $user['in_database']; }));
$users_machine_only = count(array_filter($combined_users, function($user) { return $user['in_machine'] && !$user['in_database']; }));
$users_database_only = count(array_filter($combined_users, function($user) { return !$user['in_machine'] && $user['in_database']; }));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data User Fingerprint & Database</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; font-size: 12px; }
        th { background: #f0f0f0; position: sticky; top: 0; z-index: 10; }
        tr:nth-child(even) { background: #f9f9f9; }
        button { padding: 5px 10px; cursor: pointer; margin-top: 10px; }
        .form-container { margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px; }
        .form-row { margin-bottom: 10px; }
        .form-row label { margin-right: 15px; }
        .btn-primary { background: #007bff; color: white; border: none; padding: 8px 15px; border-radius: 3px; }
        .btn-primary:hover { background: #0056b3; }
        .select-all { margin-bottom: 10px; }
        .search-container { margin-bottom: 15px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .search-input { padding: 8px; width: 300px; border: 1px solid #ddd; border-radius: 3px; }
        .filter-buttons { display: flex; gap: 5px; flex-wrap: wrap; }
        .filter-btn { padding: 6px 12px; border: 1px solid #ddd; background: white; border-radius: 3px; cursor: pointer; font-size: 11px; }
        .filter-btn.active { background: #007bff; color: white; }
        .button-container { margin: 15px 0; text-align: center; }
        .hidden { display: none !important; }
        .stats-container { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .stat-box { background: #fff; border: 1px solid #ddd; padding: 12px; border-radius: 5px; text-align: center; min-width: 120px; }
        .stat-number { font-size: 20px; font-weight: bold; color: #007bff; }
        .stat-label { font-size: 11px; color: #666; margin-top: 4px; }
        
        /* Status styling */
        .status-both { background: #d4edda; color: #155724; font-weight: bold; padding: 2px 6px; border-radius: 3px; font-size: 10px; }
        .status-machine { background: #fff3cd; color: #856404; font-weight: bold; padding: 2px 6px; border-radius: 3px; font-size: 10px; }
        .status-database { background: #f8d7da; color: #721c24; font-weight: bold; padding: 2px 6px; border-radius: 3px; font-size: 10px; }
        
        /* Table styling */
        .table-container { max-height: 600px; overflow-y: auto; border: 1px solid #ddd; }
        .pin-col { width: 60px; }
        .status-col { width: 120px; }
        .checkbox-col { width: 50px; text-align: center; }
    </style>
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
            
            return true;
        }
        
        function searchAndFilter() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const tableRows = document.querySelectorAll('tbody tr');
            let visibleCount = 0;
            
            tableRows.forEach(row => {
                const pin = row.cells[1].textContent.toLowerCase();
                const namaMesin = row.cells[2].textContent.toLowerCase();
                const namaDb = row.cells[3].textContent.toLowerCase();
                const nip = row.cells[4].textContent.toLowerCase();
                const bagian = row.cells[8].textContent.toLowerCase();
                const status = row.getAttribute('data-status');
                const checkbox = row.querySelector('input[type="checkbox"]');
                
                // Check search criteria
                const matchSearch = pin.includes(searchInput) || 
                                  namaMesin.includes(searchInput) || 
                                  namaDb.includes(searchInput) ||
                                  nip.includes(searchInput) ||
                                  bagian.includes(searchInput);
                
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
        document.addEventListener('DOMContentLoaded', function() {
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
    </script>
</head>
<body>
    <h2>👥 Data User Fingerprint & Database</h2>

    <div class="form-container">
        <form method="POST" action="detail-new.php" onsubmit="return validateForm()">
            <div class="form-row">
                <label>📅 Dari Tanggal: <input type="date" name="tanggal_dari" required></label>
                <label>📅 Sampai Tanggal: <input type="date" name="tanggal_sampai" required></label>
                <button type="submit" name="detailBtn" class="btn-primary">➡️ Detail Absensi</button>
            </div>
            
            <div class="search-container">
                <div>
                    <label>🔍 Cari: </label>
                    <input type="text" id="searchInput" class="search-input" placeholder="Ketik PIN, Nama, NIP, atau Bagian..." />
                </div>
                <div style="color: #666; font-size: 12px;">
                    <span id="userCount"><?= $total_users ?></span> user ditampilkan | 
                    <span id="selectedCount">0</span> dipilih
                </div>
            </div>
            
            <div class="select-all">
                <label>
                    <input type="checkbox" onchange="toggleAll(this)"> 
                    <strong>Pilih Semua User (yang terlihat)</strong>
                </label>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th class="checkbox-col">Pilih</th>
                            <th class="pin-col">PIN</th>
                            <th>Nama</th>
                            <th>NIP</th>
                            <th>NIK</th>
                            <th>L/P</th>
                            <th>Job Title</th>
                            <th>Bagian</th>
                            <th>Departemen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($combined_users as $pin => $user): ?>
                            <tr data-status="<?= $user['in_machine'] && $user['in_database'] ? 'both' : ($user['in_machine'] ? 'machine' : 'database') ?>">
                                <td class="checkbox-col">
                                    <?php if ($user['in_machine']): ?>
                                        <input type="checkbox" name="selected_users[]" value="<?= htmlspecialchars($pin) ?>">
                                    <?php else: ?>
                                        <span style="color: #ccc;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($pin) ?></td>
                                <td><?= htmlspecialchars($user['nama_db']) ?></td>
                                <td><?= htmlspecialchars($user['nip']) ?></td>
                                <td><?= htmlspecialchars($user['nik']) ?></td>
                                <td><?= htmlspecialchars($user['jk']) ?></td>
                                <td><?= htmlspecialchars($user['job_title']) ?></td>
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