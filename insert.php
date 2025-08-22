<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/header.php';

// Proses insert ke database
if (isset($_POST['insertBtn'])) {
    $selectedUsers = $_POST['selected_users'] ?? [];
    
    if (empty($selectedUsers)) {
        $message = "⚠️ Pilih minimal satu user untuk disimpan!";
        $messageType = "error";
    } else {
        try {
            // Ambil data user dari fingerprint untuk mendapatkan nama
            $fingerprintUsers = getUsers($ip, $port, $key);
            
            $inserted = 0;
            $updated = 0;
            $errors = [];
            
            // Koneksi database (sesuaikan dengan config Anda)
            $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            foreach ($selectedUsers as $pin) {
                if (isset($fingerprintUsers[$pin])) {
                    $nama = $fingerprintUsers[$pin];
                    
                    // Cek apakah user sudah ada
                    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE pin = ?");
                    $checkStmt->execute([$pin]);
                    
                    if ($checkStmt->rowCount() > 0) {
                        // Update jika sudah ada
                        $updateStmt = $pdo->prepare("UPDATE users SET nama = ?, updated_at = CURRENT_TIMESTAMP WHERE pin = ?");
                        $updateStmt->execute([$nama, $pin]);
                        $updated++;
                    } else {
                        // Insert jika belum ada
                        $insertStmt = $pdo->prepare("INSERT INTO users (pin, nama) VALUES (?, ?)");
                        $insertStmt->execute([$pin, $nama]);
                        $inserted++;
                    }
                } else {
                    $errors[] = "PIN $pin tidak ditemukan di fingerprint";
                }
            }
            
            $message = "✅ Berhasil: $inserted user baru ditambahkan, $updated user diupdate";
            if (!empty($errors)) {
                $message .= "<br>❌ Error: " . implode(", ", $errors);
            }
            $messageType = "success";
            
        } catch (Exception $e) {
            $message = "❌ Error database: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

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
.btn-primary { background: #007bff; color: white; border: none; padding: 8px 15px; border-radius: 3px; margin-right: 10px; }
.btn-primary:hover { background: #0056b3; }
.btn-success { background: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 3px; margin-right: 10px; }
.btn-success:hover { background: #1e7e34; }
.select-all { margin-bottom: 10px; }
.search-container { margin-bottom: 15px; }
.search-input { padding: 8px; width: 300px; border: 1px solid #ddd; border-radius: 3px; }
.button-container { margin: 15px 0; text-align: center; }
.hidden { display: none; }
.message { padding: 10px; margin: 10px 0; border-radius: 5px; }
.message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.button-group { display: flex; gap: 10px; align-items: center; }
</style>
<script>
function toggleAll(source) {
    const checkboxes = document.querySelectorAll('input[name="selected_users[]"]:not(.hidden)');
    checkboxes.forEach(checkbox => checkbox.checked = source.checked);
}

function validateForm(formType) {
    const checkboxes = document.querySelectorAll('input[name="selected_users[]"]:checked:not(.hidden)');
    
    if (checkboxes.length === 0) {
        alert('⚠️ Pilih minimal satu user!');
        return false;
    }
    
    if (formType === 'detail') {
        const tanggalDari = document.querySelector('input[name="tanggal_dari"]').value;
        const tanggalSampai = document.querySelector('input[name="tanggal_sampai"]').value;
        
        if (!tanggalDari || !tanggalSampai) {
            alert('⚠️ Tanggal dari dan sampai harus diisi!');
            return false;
        }
        
        if (tanggalDari > tanggalSampai) {
            alert('⚠️ Tanggal dari tidak boleh lebih besar dari tanggal sampai!');
            return false;
        }
    }
    
    if (formType === 'insert') {
        return confirm('🔄 Apakah Anda yakin ingin menyimpan user yang dipilih ke database?\n\nJika user sudah ada, data akan diupdate.');
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

<?php if (isset($message)): ?>
    <div class="message <?= $messageType ?>">
        <?= $message ?>
    </div>
<?php endif; ?>

<div class="form-container">
    <div class="form-row">
        <label>📅 Dari Tanggal: <input type="date" name="tanggal_dari" id="tanggal_dari" required></label>
        <label>📅 Sampai Tanggal: <input type="date" name="tanggal_sampai" id="tanggal_sampai" required></label>
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
    
    <div class="button-container">
        <div class="button-group">
            <!-- Form untuk Detail Absensi -->
            <form method="POST" action="detail-new.php" style="display: inline;" onsubmit="return validateForm('detail')">
                <input type="hidden" name="tanggal_dari_hidden" id="tanggal_dari_hidden">
                <input type="hidden" name="tanggal_sampai_hidden" id="tanggal_sampai_hidden">
                <div id="selected_users_detail"></div>
                <button type="submit" name="detailBtn" class="btn-primary">➡️ Detail Absensi</button>
            </form>
            
            <!-- Form untuk Insert ke Database -->
            <form method="POST" style="display: inline;" onsubmit="return validateForm('insert')">
                <div id="selected_users_insert"></div>
                <button type="submit" name="insertBtn" class="btn-success">💾 Simpan ke Database</button>
            </form>
        </div>
    </div>
</div>

<script>
// Update hidden inputs sebelum submit
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // Update tanggal untuk form detail
            const tanggalDari = document.getElementById('tanggal_dari').value;
            const tanggalSampai = document.getElementById('tanggal_sampai').value;
            
            document.getElementById('tanggal_dari_hidden').value = tanggalDari;
            document.getElementById('tanggal_sampai_hidden').value = tanggalSampai;
            
            // Ambil checkbox yang dicentang dan tidak hidden
            const checkedBoxes = document.querySelectorAll('input[name="selected_users[]"]:checked:not(.hidden)');
            
            // Clear existing hidden inputs
            document.getElementById('selected_users_detail').innerHTML = '';
            document.getElementById('selected_users_insert').innerHTML = '';
            
            // Tambahkan hidden input untuk setiap user yang dipilih
            checkedBoxes.forEach(checkbox => {
                // Untuk form detail
                const hiddenDetail = document.createElement('input');
                hiddenDetail.type = 'hidden';
                hiddenDetail.name = 'selected_users[]';
                hiddenDetail.value = checkbox.value;
                document.getElementById('selected_users_detail').appendChild(hiddenDetail);
                
                // Untuk form insert
                const hiddenInsert = document.createElement('input');
                hiddenInsert.type = 'hidden';
                hiddenInsert.name = 'selected_users[]';
                hiddenInsert.value = checkbox.value;
                document.getElementById('selected_users_insert').appendChild(hiddenInsert);
            });
        });
    });
});
</script>

</body>
</html>

<?php

?>