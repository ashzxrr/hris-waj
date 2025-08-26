<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/functions.php';

// Ambil user dari mesin absensi
$users = getUsers($ip, $port, $key);
?>

<div class="container mt-5">
    <h3>Data User</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>PIN</th>
                <th>Nama</th>
                <th>NIP</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $pin => $nama): ?>
                <?php
                // Ambil NIP dari tabel karyawan
                $stmt = $mysqli->prepare("SELECT nip FROM karyawan_test WHERE pin = ?");
                $stmt->bind_param("s", $pin);
                $stmt->execute();
                $stmt->bind_result($nip);
                $stmt->fetch();
                $stmt->close();
                ?>
                <tr>
                    <td><?= htmlspecialchars($pin) ?></td>
                    <td><?= htmlspecialchars($nama) ?></td>
                    <td><?= htmlspecialchars($nip ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
