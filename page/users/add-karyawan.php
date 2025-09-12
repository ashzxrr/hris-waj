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
            // Validasi data required (PIN, Nama, Jenis Kelamin wajib)
            if (empty($user_data['pin']) || empty($user_data['nama']) || empty($user_data['jk'])) {
                $error_messages[] = "PIN, Nama, dan Jenis Kelamin harus diisi untuk user PIN: " . ($user_data['pin'] ?? 'Unknown');
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
            // Normalize gender value to single-character code expected by DB (L or P)
            $jk_raw = isset($user_data['jk']) ? trim($user_data['jk']) : '';
            $jk = '';
            if ($jk_raw !== '') {
                $jk_up = strtoupper(substr($jk_raw, 0, 1));
                if ($jk_up === 'L' || $jk_up === 'P') {
                    $jk = $jk_up;
                } else {
                    // try to map common full strings
                    $lk = strtolower($jk_raw);
                    if (strpos($lk, 'laki') === 0) $jk = 'L';
                    elseif (strpos($lk, 'perempuan') === 0 || strpos($lk, 'perem') === 0) $jk = 'P';
                    else $jk = '';
                }
            }
            $job_title = !empty($user_data['job_title']) ? $user_data['job_title'] : '';
            $job_level = !empty($user_data['job_level']) ? $user_data['job_level'] : '';
            $bagian = !empty($user_data['bagian']) ? $user_data['bagian'] : '';
            $departemen = !empty($user_data['departemen']) ? $user_data['departemen'] : '';
            $tl_id = !empty($user_data['tl_id']) ? $user_data['tl_id'] : '';
            // Insert user baru (tl_id disimpan NULL bila kosong). Gunakan NULLIF untuk memungkinkan tl_id kosong.
            // Use NULLIF for jk and tl_id so empty strings become NULL (avoids enum/truncation errors)
            $insert_query = "INSERT INTO users (pin, nip, nama, nik, jk, job_title, job_level, bagian, departemen, tl_id) VALUES (?, ?, ?, ?, NULLIF(? ,''), ?, ?, ?, ?, NULLIF(?,''))";
            $insert_stmt = mysqli_prepare($mysqli, $insert_query);

            if ($insert_stmt) {
                // Bind satu kali dengan 10 parameter (termasuk tl_id)
                mysqli_stmt_bind_param($insert_stmt, "ssssssssss", $pin, $nip, $nama, $nik, $jk, $job_title, $job_level, $bagian, $departemen, $tl_id);

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
    }.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

/* Card Styles */
.card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.card-header {
    padding: 15px 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #1f2937;
    margin: 0;
}

.card-body {
    padding: 20px;
}

/* Info Alert Styles */
.alert {
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 20px;
    font-size: 0.9rem;
}

.alert-info {
    background-color: #e1f5fe;
    border: 1px solid #b3e5fc;
    color: #0277bd;
}

/* Form Styles */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.form-group {
    margin-bottom: 15px;
}

.form-label {
    display: block;
    margin-bottom: 5px;
    font-size: 0.9rem;
    color: #4b5563;
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.9rem;
    transition: border-color 0.15s ease-in-out;
}

.form-control:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
}

.form-control:disabled {
    background-color: #f3f4f6;
}

/* Button Styles */
.btn-toolbar {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}

.btn-primary {
    background-color: #3b82f6;
    color: white;
}

.btn-primary:hover {
    background-color: #2563eb;
}

.btn-secondary {
    background-color: #6b7280;
    color: white;
}

.btn-secondary:hover {
    background-color: #4b5563;
}

/* Required Field Indicator */
.required::after {
    content: " *";
    color: #ef4444;
}

/* Card Grid */
.user-cards {
    display: grid;
    gap: 20px;
    margin-bottom: 20px;
}

.user-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
}

.user-card-header {
    background: #f8fafc;
    padding: 12px 16px;
    border-bottom: 1px solid #e5e7eb;
    font-weight: 500;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 8px;
}

.user-card-body {
    padding: 16px;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .btn-toolbar {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
}
</style>
</head>

<body>
    <h2>👤 Tambah User ke Database</h2>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">👤 Tambah User ke Database</h2>
        </div>
        
        <div class="card-body">
            <div class="alert alert-info">
                <strong>📋 Informasi:</strong> 
                Lengkapi data untuk <?= count($selected_users) ?> user yang akan ditambahkan ke database.
                Field bertanda <span style="color: #ef4444;">*</span> wajib diisi.
            </div>

            <form method="POST" id="userForm">
                <div class="btn-toolbar">
                    <a href="?page=users" class="btn btn-secondary">
                        <span>⬅️</span> Kembali
                    </a>
                    <button type="button" class="btn btn-secondary" onclick="location.reload();">
                        <span>🔄</span> Muat Ulang
                    </button>
                    <button type="submit" name="save_users" class="btn btn-primary">
                        <span>💾</span> Simpan <?= count($selected_users) ?> User
                    </button>
                </div>

                <div class="user-cards">
                    <?php foreach ($selected_users as $index => $user): ?>
                        <div class="user-card">
                            <div class="user-card-header">
                                <span>👤</span>
                                <strong>
                                User <?= $index + 1 ?> - PIN: <?= htmlspecialchars($user['pin']) ?><?= $index + 1 ?> - Nama: <?= htmlspecialchars($user['nama']) ?>
                                </strong>
                            </div>

                            <div class="user-card-body">
                                <div class="form-grid">
                                    <div class="form-group" style="display:none;">
                                        <label class="form-label required">PIN</label>
                                        <input type="hidden" name="users[<?= $index ?>][pin]"
                                            value="<?= htmlspecialchars($user['pin']) ?>">
                                        <input type="text" value="<?= htmlspecialchars($user['pin']) ?>" class="form-control" disabled>
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
                                        <select name="users[<?= $index ?>][jk]" class="form-control" required>
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

                                    <div class="form-group">
                                        <?php $sel_tl = isset($user['tl_id']) ? (string)$user['tl_id'] : ''; ?>
                                        <label class="form-label">TL (Team Leader)</label>
                                        <select name="users[<?= $index ?>][tl_id]" class="form-control">
                                            <option value="" <?= $sel_tl === '' ? 'selected' : '' ?>>- Tidak Ada -</option>
                                            <optgroup label="CABUT">
                                                <option value="8" <?= $sel_tl === '8' ? 'selected' : '' ?>>Karyawati</option>
                                                <option value="3" <?= $sel_tl === '3' ? 'selected' : '' ?>>Sri Utami</option>
                                                <option value="2" <?= $sel_tl === '2' ? 'selected' : '' ?>>ST Nur Farokah</option>
                                                <option value="25" <?= $sel_tl === '25' ? 'selected' : '' ?>>Fhilis Sulestari</option>
                                                <option value="22" <?= $sel_tl === '22' ? 'selected' : '' ?>>Muhammad Regatana Hidayatulloh</option>
                                                <option value="119" <?= $sel_tl === '119' ? 'selected' : '' ?>>Zusita Arsdhia Indrayani</option>
                                                <option value="34" <?= $sel_tl === '34' ? 'selected' : '' ?>>Wahyu Surodo</option>
                                                <option value="60" <?= $sel_tl === '60' ? 'selected' : '' ?>>Lutfi Dwi Firmansyah</option>
                                                <option value="109" <?= $sel_tl === '109' ? 'selected' : '' ?>>Ruliatul Fidiah</option>
                                            </optgroup>

                                            <optgroup label="Cetak">
                                                <option value="57" <?= $sel_tl === '57' ? 'selected' : '' ?>>Muhammad Tamamur Ridlwan</option>
                                                <option value="53" <?= $sel_tl === '53' ? 'selected' : '' ?>>Abdul Rouf Khoiri</option>
                                                <option value="7" <?= $sel_tl === '7' ? 'selected' : '' ?>>Anita</option>
                                                <option value="24" <?= $sel_tl === '24' ? 'selected' : '' ?>>Patur Albertino</option>
                                                <option value="27" <?= $sel_tl === '27' ? 'selected' : '' ?>>Anas Ja'far</option>
                                                <option value="48" <?= $sel_tl === '48' ? 'selected' : '' ?>>M.Jamaludin</option>
                                                <option value="99" <?= $sel_tl === '99' ? 'selected' : '' ?>>Nila Widya Sari</option>
                                                <option value="113" <?= $sel_tl === '113' ? 'selected' : '' ?>>Nurul Izzuddin</option>
                                            </optgroup>

                                            <optgroup label="Dan Lain lain">
                                                <option value="1" <?= $sel_tl === '1' ? 'selected' : '' ?>>Anik</option>
                                                <option value="98" <?= $sel_tl === '98' ? 'selected' : '' ?>>M Gaung Sidiq</option>
                                                <option value="40" <?= $sel_tl === '40' ? 'selected' : '' ?>>Cankiswan</option>
                                                <option value="118" <?= $sel_tl === '118' ? 'selected' : '' ?>>Kerinna</option>
                                            </optgroup>
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
    </div>  

    <script>
        document.getElementById('userForm').addEventListener('submit', function (e) {
            const requiredFields = document.querySelectorAll('input[required], select[required]');
            let isValid = true;
            let firstInvalid = null;

            // Reset error styling
            document.querySelectorAll('.form-control.error').forEach(field => {
                field.classList.remove('error');
            });

            // Validate required fields (inputs and selects)
            requiredFields.forEach(field => {
                const val = field.value === null ? '' : String(field.value).trim();
                if (!val) {
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