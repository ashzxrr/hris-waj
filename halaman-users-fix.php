<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/header.php';

// Ambil data user dari mesin fingerprint
$users = getUsers($ip, $port, $key);

// Urutkan berdasarkan PIN (numerik)
uksort($users, function($a, $b) {
    return (int)$a - (int)$b;
});
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Data User Fingerprint</title>
<style>
table { border-collapse: collapse; width: 100%; margin-top: 10px; }
th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
th { background: #f0f0f0; }
tr:nth-child(even) { background: #f9f9f9; }
button { padding: 5px 10px; cursor: pointer; margin-top: 10px; }
.form-container { margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px; }
.form-row { margin-bottom: 10px; }
.form-row label { margin-right: 15px; }
.btn-primary { background: #007bff; color: white; border: none; padding: 8px 15px; border-radius: 3px; }
.btn-primary:hover { background: #0056b3; }
.select-all { margin-bottom: 10px; }
.search-container { margin-bottom: 15px; }
.search-input { padding: 8px; width: 300px; border: 1px solid #ddd; border-radius: 3px; }
.button-container { margin: 15px 0; text-align: center; }
.hidden { display: none; }
</style>
<script>
function toggleAll(source) {
    const checkboxes = document.querySelectorAll('input[name="selected_users[]"]:not(.hidden)');
    checkboxes.forEach(checkbox => checkbox.checked = source.checked);
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
            checkbox.classList.remove('hidden');
            visibleCount++;
        } else {
            row.style.display = 'none';
            row.classList.add('hidden');
            checkbox.classList.add('hidden');
            checkbox.checked = false; // Uncheck hidden items
        }
    });
    
    // Update counter
    document.getElementById('userCount').textContent = visibleCount;
    
    // Reset "Pilih Semua" checkbox
    document.querySelector('input[onchange="toggleAll(this)"]').checked = false;
}

// Auto search saat mengetik
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('searchInput').addEventListener('input', searchUsers);
});
</script>
</head>
<body>

<h2>📋 Data User Fingerprint</h2>

<div class="form-container">
    <form method="POST" action="detail-noabs.php" onsubmit="return validateForm()">
        <div class="form-row">
            <label>📅 Dari Tanggal: <input type="date" name="tanggal_dari" required></label>
            <label>📅 Sampai Tanggal: <input type="date" name="tanggal_sampai" required></label>
     
            <button type="submit" name="detailBtn" class="btn-primary">➡️ Detail Absensi</button>
        </div>
        
        <div class="search-container">
            <label>🔍 Cari User: </label>
            <input type="text" id="searchInput" class="search-input" placeholder="Ketik PIN atau Nama..." />
            <span style="margin-left: 10px; color: #666;">
                (<span id="userCount"><?= count($users) ?></span> user ditemukan)
            </span>
        </div>
        
        <div class="select-all">
            <label>
                <input type="checkbox" onchange="toggleAll(this)"> 
                <strong>Pilih Semua User (yang terlihat)</strong>
            </label>
        </div>

        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>PIN</th>
                    <th>Nama</th>
                    <th>Pilih</th>
                </tr>
            </thead>
            <tbody>
                <?php $no=1; foreach($users as $pin=>$name): ?>
                    <tr>
                        <td><?= $no ?></td>
                        <td><?= htmlspecialchars($pin) ?></td>
                        <td><?= htmlspecialchars($name) ?></td>
                        <td><input type="checkbox" name="selected_users[]" value="<?= htmlspecialchars($pin) ?>"></td>
                    </tr>
                <?php $no++; endforeach; ?>
            </tbody>
        </table>
    </form>
</div>

</body>
</html>