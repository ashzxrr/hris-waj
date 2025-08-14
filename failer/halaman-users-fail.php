<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

// Ambil data user dari mesin fingerprint
$users_raw = getUsers($ip, $port, $key);

// Ubah ke array dengan PIN2 sebagai key agar bisa diurutkan
$users_data = [];
foreach($users_raw as $pin2 => $name) {
    $users_data[] = [
        'pin2' => (int)$pin2,
        'pin' => $pin2,
        'name' => $name
    ];
}

// Urutkan berdasarkan PIN2
usort($users_data, function($a, $b) {
    return $a['pin2'] <=> $b['pin2'];
});
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Data User Fingerprint</title>
<style>
table { border-collapse: collapse; width: 100%; margin-top: 20px; }
th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
th { background: #f0f0f0; }
tr:nth-child(even) { background: #f9f9f9; }
button { padding: 5px 10px; cursor: pointer; margin-top: 10px; }
</style>
</head>
<body>

<h2>📋 Data User Fingerprint</h2>

<button id="insertDbBtn">💾 Insert Database</button>
<div id="status" style="margin:10px 0; color:green;"></div>
<div class="mb-3">
            <input type="text" id="searchInput" class="form-control" placeholder="Cari nama atau PIN...">
        </div>

<table id="userTable">
    <thead>
        <tr>
            <th>No</th>
            <th>PIN</th>
            <th>Nama</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $no = 1;
        foreach($users_data as $user) {
            echo "<tr>";
            echo "<td>$no</td>";
            echo "<td>{$user['pin']}</td>";
            echo "<td>{$user['name']}</td>";
            echo "</tr>";
            $no++;
        }
        ?>
    </tbody>
</table>

<script>
document.getElementById('insertDbBtn').addEventListener('click', function(){
    let btn = this;
    btn.disabled = true;
    document.getElementById('status').innerText = '🔄 Menyimpan data ke database...';

    fetch('insert-users.php', { method: 'POST' })
    .then(res => res.text())
    .then(msg => {
        document.getElementById('status').innerText = msg;
        btn.disabled = false;
    })
    .catch(err => {
        document.getElementById('status').innerText = '❌ Gagal menyimpan data!';
        console.error(err);
        btn.disabled = false;
    });
});
</script>

</body>
</html>