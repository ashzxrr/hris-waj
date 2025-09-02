<?php
require __DIR__ . '/../../includes/config.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/header.php';

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
            window.location.href = '?page=users';
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
        window.location.href = '?page=users.php';
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
            window.location.href = '?page=users';
        </script>";
        exit;
    }
}

if (empty($selected_users)) {
    echo "<script>
        alert('⚠️ Tidak ada data user yang valid!');
        window.location.href = '?page=users';
    </script>";
    exit;
}

// Data referensi untuk dropdown
$job_titles = [
    'Operator',
    'TL Cuci',
    'TL Cabut',
    'TL Kedatangan',
    'SPV Moulding',
    'TL Moulding',
    'GTL Moulding',
    'GTL Cabut',
    'Driver',
    'Manager Produksi',
    'SPV Kedatangan',
    'Checker Cabut',
    'Admin Produktivitas',
    'Checker Moulding',
    'TL Pengiriman',
    'Admin',
    'TL Packing',
    'Superintenden',
    'Ass. Superintenden',
    'TL Cutter & Flek',
    'SPV Packing',
    'Security',
    'Sanitasi',
    'Purchasing/ Logistic',
    'Maintenance',
    'Finance Accounting'
];

$job_levels = [
    'Operator',
    'Team Leader',
    'Supervisor',
    'Group Team Leader',
    'Manager',
    'Checker',
    'Administrasi',
    'Driver',
    'Superintenden',
    'General Manager',
    'Security',
    'Sanitasi',
    'Maintenance',
    'Finance Accounting'
];

$bagian_list = [
    '-',
    'Manager Produksi',
    'Bahan Baku',
    'Cabut',
    'Dry A',
    'Moulding',
    'Cuci Bersih',
    'Cuci Kotor',
    'Admin',
    'Rambang',
    'Cutter & Flek',
    'Dry B & HCR',
    'HCR Moulding',
    'Admin Cabut & Bahan Baku',
    'Packing',
    'Admin Drying & Moulding',
    'SPV',
    'TL Pre Cleaning',
    'Checker Moulding',
    'Timbang Indomie',
    'Administrasi',
    'Grading',
    'Final Grading',
    'Titil HCR',
    'Moulding Indomie',
    'CCP 1',
    'Prewash',
    'Driver',
    'Admin Packing',
    'Admin Cabut',
    'Security',
    'Sanitasi',
    'Kasir Perusahaan',
    'Maintenance IT',
    'Finance Accounting',
    'Maintenance'
];

$departemen_list = ['Produksi', 'Support', 'Operation'];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <title>Tambah User ke Database</title>
<style>
     .content {
        margin: 20px;
    }

    .info-box {
        background-color: #fff3cd;
        border: 1px solid #ffeeba;
        color: #856404;
        padding: 12px 15px;
        border-radius: 4px;
        margin-bottom: 20px;
        font-size: 13px;
    }

    .user-cards {
        display: grid;
        gap: 20px;
        margin-bottom: 20px;
    }

    .user-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 5px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .user-card-header {
        background: #f8fafc;
        padding: 10px 15px;
        border-bottom: 1px solid #e2e8f0;
        font-weight: 600;
        color: #475569;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .user-card-body {
        padding: 15px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }

    .form-group {
        margin-bottom: 10px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-size: 13px;
        color: #475569;
        font-weight: 500;
    }

    .form-control {
        width: 100%;
        padding: 8px;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        font-size: 13px;
        transition: all 0.3s ease;
    }

    .form-control:focus {
        outline: none;
        border-color: #28a745;
        box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.1);
    }

    .form-control:disabled {
        background-color: #f8f9fa;
        cursor: not-allowed;
    }

    .button-container {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
    padding: 0 10px;
}

.btn {
    min-width: 160px;
    padding: 10px 24px;
    border: none;
    border-radius: 25px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
}

    /*.actions {
        background: #f8fafc;
        padding: 15px;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: center;
        gap: 10px;
    }*/

    .btn {
        padding: 8px 20px;
        border: none;
        border-radius: 25px;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #218838, #1ea67a);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2);
    }

.btn-secondary {
    background: linear-gradient(135deg, #0d6efd, #0dcaf0);
    color: white;
}

.btn-secondary:hover {
    background: linear-gradient(135deg, #0b5ed7, #0aa1c0);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.2);
}
    .required::after {
        content: " *";
        color: #dc3545;
    }

    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
</head>

<body>
    <h2>👤 Tambah User ke Database</h2>

    <div class="content">
        <div class="info-box">
            <strong>📋 Informasi:</strong> 
            Lengkapi data untuk <?= count($selected_users) ?> user yang akan ditambahkan ke database.
            Field bertanda <span style="color: #dc3545;">*</span> wajib diisi.
        </div>

            <form method="POST" id="userForm">
                <div class="button-container">
                    <a href="?page=users" class="btn btn-secondary">
                        <span>⬅️</span> Kembali
                    </a>
                    <button class="btn btn-secondary" type="button" onclick="location.reload();">
                        <span>🔄</span> Muat Ulang Halaman

                    </button>
                    <button type="submit" name="save_users" class="btn btn-primary" id="saveBtn">
                        <span>💾</span> Simpan <?= count($selected_users) ?> User
                    </button>
                </div>
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
                                        <input type="text" name="users[<?= $index ?>][pin]"
                                            value="<?= htmlspecialchars($user['pin']) ?>" class="form-control" disabled>
                                        <input type="hidden" name="users[<?= $index ?>][pin]"
                                            value="<?= htmlspecialchars($user['pin']) ?>">
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label required">Nama Lengkap</label>
                                        <input type="text" name="users[<?= $index ?>][nama]"
                                            value="<?= htmlspecialchars($user['nama']) ?>" class="form-control" required>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">NIP</label>
                                        <input type="text" name="users[<?= $index ?>][nip]" class="form-control"
                                            placeholder="Masukkan NIP">
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">NIK</label>
                                        <input type="text" name="users[<?= $index ?>][nik]" class="form-control"
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
                                        <label for="job_title">Jabatan</label>
                                        <select name="users[<?= $index ?>][job_title]" id="job_title" class="form-control"
                                            required>
                                            <?php foreach ($job_titles as $title): ?>
                                                <option value="<?= $title ?>" <?= ($title === 'Operator') ? 'selected' : '' ?>>
                                                    <?= $title ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="job_level">Level Jabatan</label>
                                        <select name="users[<?= $index ?>][job_level]" id="job_level" class="form-control"
                                            required>
                                            <?php foreach ($job_levels as $level): ?>
                                                <option value="<?= $level ?>" <?= ($level === 'Operator') ? 'selected' : '' ?>>
                                                    <?= $level ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="bagian">Bagian</label>
                                        <select name="users[<?= $index ?>][bagian]" id="bagian" class="form-control"
                                            required>
                                            <?php foreach ($bagian_list as $bagian): ?>
                                                <option value="<?= $bagian ?>" <?= ($bagian === '-') ? 'selected' : '' ?>>
                                                    <?= $bagian ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="bagian">Bagian</label>
                                        <select name="users[<?= $index ?>][departemen]" id="departemen" class="form-control"
                                            required>
                                            <?php foreach ($departemen_list as $departemen): ?>
                                                <option value="<?= $departemen ?>" <?= ($departemen === '-') ? 'selected' : '' ?>>
                                                    <?= $departemen ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>


                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('userForm').addEventListener('submit', function (e) {
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