<?php
require __DIR__ . '/../../includes/config.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/header.php';

// Ensure POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ?page=users');
    exit;
}

// If this is a save submission, handle updates immediately and exit/redirect
if (isset($_POST['save_users'])) {
    $success = 0;
    $errors = [];
    if (isset($_POST['users']) && is_array($_POST['users'])) {
        foreach ($_POST['users'] as $uid => $ud) {
            $id = isset($ud['id']) ? intval($ud['id']) : 0;
            $nama = $ud['nama'] ?? '';
            if ($id <= 0 || trim($nama) === '') {
                $errors[] = "ID & Nama wajib diisi untuk item #$uid";
                continue;
            }

            $nip = $ud['nip'] ?? '';
            $nik = $ud['nik'] ?? '';
            $jk = $ud['jk'] ?? '';
            $job_title = $ud['job_title'] ?? '';
            $job_level = $ud['job_level'] ?? '';
            $tl_id = $ud['tl_id'] ?? '';
            // normalize TL input: some UI may send 'none' or empty string to indicate no TL
            if ($tl_id === 'none') {
                $tl_id = '';
            }
            $bagian = $ud['bagian'] ?? '';
            $departemen = $ud['departemen'] ?? '';
            // Use NULLIF on tl_id so empty string becomes SQL NULL (avoids incorrect integer value)
            $update_sql = "UPDATE users SET nip=?, nik=?, nama=?, jk=NULLIF(?, ''), job_title=?, job_level=?, tl_id=NULLIF(?, ''), bagian=?, departemen=? WHERE id=?";
            $up_stmt = mysqli_prepare($mysqli, $update_sql);
            if (!$up_stmt) {
                $errors[] = "Gagal prepare update untuk ID {$id}: " . mysqli_error($mysqli);
                continue;
            }
            mysqli_stmt_bind_param($up_stmt, 'sssssssssi', $nip, $nik, $nama, $jk, $job_title, $job_level, $tl_id, $bagian, $departemen, $id);
            if (mysqli_stmt_execute($up_stmt)) {
                $success++;
            } else {
                $errors[] = "Gagal menyimpan ID {$id}: " . mysqli_error($mysqli);
            }
            mysqli_stmt_close($up_stmt);
        }
    }

    if ($success > 0) {
        echo "<script>alert('✅ Berhasil menyimpan {$success} user.'); window.location.href='?page=users';</script>";
        exit;
    } else {
        $msg = !empty($errors) ? implode('\n', array_slice($errors, 0, 5)) : 'Tidak ada perubahan disimpan.';
        echo "<script>alert('⚠️ {$msg}');</script>";
        // fallthrough to re-render form using POSTed users if needed
    }
}

// Collect pins and nips from POST (support multiple input shapes)
$pins = [];
$nips = [];
if (isset($_POST['user_data']) && is_array($_POST['user_data'])) {
    foreach ($_POST['user_data'] as $ud) {
        if (!empty($ud['pin']))
            $pins[] = $ud['pin'];
        if (!empty($ud['nip']))
            $nips[] = $ud['nip'];
    }
}
if (isset($_POST['selected_users']) && is_array($_POST['selected_users'])) {
    foreach ($_POST['selected_users'] as $pin) {
        if (!empty($pin))
            $pins[] = $pin;
    }
}
if (isset($_POST['selected_nips']) && is_array($_POST['selected_nips'])) {
    foreach ($_POST['selected_nips'] as $nip) {
        if (!empty($nip))
            $nips[] = $nip;
    }
}

// Deduplicate
$pins = array_values(array_unique($pins));
$nips = array_values(array_unique($nips));

if (empty($pins) && empty($nips)) {
    echo "<script>alert('⚠️ Tidak ada user yang dipilih untuk diedit.'); window.location.href='?page=users';</script>";
    exit;
}

// Build query to fetch users by pin OR nip
$conditions = [];
$params = [];
$types = '';
if (!empty($pins)) {
    $ph = implode(',', array_fill(0, count($pins), '?'));
    $conditions[] = "pin IN ($ph)";
    $types .= str_repeat('s', count($pins));
    $params = array_merge($params, $pins);
}
if (!empty($nips)) {
    $ph2 = implode(',', array_fill(0, count($nips), '?'));
    $conditions[] = "nip IN ($ph2)";
    $types .= str_repeat('s', count($nips));
    $params = array_merge($params, $nips);
}

$sql = "SELECT id, pin, nip, nik, nama, jk, job_title, job_level, tl_id, bagian, departemen FROM users WHERE " . implode(' OR ', $conditions);
$stmt = mysqli_prepare($mysqli, $sql);
if ($stmt) {
    // bind params
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    echo "<script>alert('⚠️ Query error: " . addslashes(mysqli_error($mysqli)) . "'); window.location.href='?page=users';</script>";
    exit;
}

$users = [];
while ($row = mysqli_fetch_assoc($result)) {
    $users[] = $row;
}

// Compute diagnostics: which requested pins/nips were actually found
$foundPins = array_map(function ($u) {
    return (string) $u['pin'];
}, $users);
$foundNips = array_map(function ($u) {
    return (string) $u['nip'];
}, $users);

$requestedPins = array_map('strval', $pins);
$requestedNips = array_map('strval', $nips);

$missingPins = array_values(array_diff($requestedPins, $foundPins));
$missingNips = array_values(array_diff($requestedNips, $foundNips));

if (empty($users)) {
    $messages = [];
    if (!empty($missingPins))
        $messages[] = "PIN tidak ditemukan: " . implode(', ', $missingPins);
    if (!empty($missingNips))
        $messages[] = "NIP tidak ditemukan: " . implode(', ', $missingNips);
    $msg = !empty($messages) ? implode(' | ', $messages) : 'Tidak ada user di database yang sesuai dengan pilihan Anda.';
    echo "<script>alert('⚠️ " . addslashes($msg) . "'); window.location.href='?page=users';</script>";
    exit;
}

// (duplicate save handler removed; save is handled earlier)

// Data for selects
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
$job_levels = ['Operator', 'Team Leader', 'Supervisor', 'Group Team Leader', 'Manager', 'Checker', 'Administrasi', 'Driver', 'Superintenden', 'General Manager', 'Security', 'Sanitasi', 'Maintenance', 'Finance Accounting'];
$bagian_list = ['-', 'Manager Produksi', 'Bahan Baku', 'Cabut', 'Dry A', 'Moulding', 'Cuci Bersih', 'Cuci Kotor', 'Admin', 'Rambang', 'Cutter & Flek', 'Dry B & HCR', 'HCR Moulding', 'Admin Cabut & Bahan Baku', 'Packing', 'Admin Drying & Moulding', 'SPV', 'TL Pre Cleaning', 'Checker Moulding', 'Timbang Indomie', 'Administrasi', 'Grading', 'Final Grading', 'Titil HCR', 'Moulding Indomie', 'CCP 1', 'Prewash', 'Driver', 'Admin Packing', 'Admin Cabut', 'Security', 'Sanitasi', 'Kasir Perusahaan', 'Maintenance IT', 'Finance Accounting', 'Maintenance'];
$departemen_list = ['Produksi', 'Support', 'Operation'];

?>

<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="assets/css/style-user-2.css">
    <title>Edit Karyawan</title>
</head>
<style>
    :root {
        --bg: #f6f8fa;
        --card: #ffffff;
        --muted: #6b7280;
        --accent: #2563eb;
        --accent-2: #3b82f6;
        --success: #16a34a;
        --border: #e6e9ee;
        --radius: 10px;
        --gap: 16px;
        font-size: 16px;
    }

    * {
        box-sizing: border-box
    }

    html,
    body {
        height: 100%;
        margin: 0;
        font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
        background: var(--bg);
        color: #0f172a
    }

    h2 {
        margin: 18px 0 12px 0;
        font-size: 1.25rem;
        color: #0b2545;
        font-weight: 600;
        padding: 0 20px
    }

    .container {
        max-width: 1100px;
        margin: 12px auto;
        padding: 18px
    }

    /* Info box */
    .info-box {
        background: #fffef8;
        border: 1px solid #fff0c2;
        color: #5a4b00;
        padding: 12px 14px;
        border-radius: 8px;
        margin-bottom: 14px;
        font-size: 14px;
    }

    /* Cards grid */
    .user-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: var(--gap);
        margin: 12px 0 20px;
    }

    /* Card */
    .user-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        min-height: 120px;
    }

    .user-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 12px 14px;
        background: linear-gradient(90deg, rgba(59, 130, 246, 0.04), rgba(14, 165, 233, 0.02));
        border-bottom: 1px solid var(--border);
        font-weight: 600;
        color: #063970;
        font-size: 14px;
    }

    .user-card-body {
        padding: 14px;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        align-items: start;
    }

    /* Single-column in narrow screens */
    @media (max-width:720px) {
        .user-card-body {
            grid-template-columns: 1fr
        }
    }

    /* Form controls */
    label {
        display: block;
        font-size: 13px;
        color: var(--muted);
        margin-bottom: 6px;
        font-weight: 600
    }

    input[type="text"],
    select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: #fff;
        font-size: 14px;
        color: #0f172a;
        transition: box-shadow .15s, border-color .15s;
    }

    input[type="text"]:focus,
    select:focus {
        outline: none;
        border-color: var(--accent-2);
        box-shadow: 0 6px 18px rgba(59, 130, 246, 0.08);
    }

    /* Make hidden inputs visually hidden but accessible */
    input[type="hidden"] {
        display: none
    }

    /* Footer actions */
    .form-actions {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        align-items: center;
        padding: 8px 4px 24px;
    }

    /* Buttons */
    button[name="save_users"],
    .btn-save {
        background: linear-gradient(180deg, var(--accent-2), var(--accent));
        color: #ffffff;
        border: none;
        padding: 10px 18px;
        border-radius: 999px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 8px 24px rgba(59, 130, 246, 0.12);
        transition: transform .12s ease, box-shadow .12s ease, opacity .12s;
    }

    button[name="save_users"]:hover {
        transform: translateY(-2px)
    }

    a.cancel-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        border-radius: 999px;
        text-decoration: none;
        color: var(--muted);
        background: #fff;
        border: 1px solid var(--border);
    }

    /* Labels for required */
    .required::after {
        content: " *";
        color: #ef4444
    }

    /* Small helper layout for each control: use consistent spacing */
    .user-card-body>div {
        display: flex;
        flex-direction: column
    }

    /* Improve select width / text size */
    select {
        appearance: none
    }

    /* Responsive adjustments */
    @media (max-width:480px) {
        h2 {
            font-size: 1.05rem;
            padding: 12px
        }

        .container {
            padding: 12px
        }
    }

    /* Accessibility: high-contrast focus */
    input[type="text"]:focus,
    select:focus {
        outline-offset: 2px
    }

    /* Minor niceties */
    .user-card-header small {
        color: var(--muted);
        font-weight: 500;
        font-size: 12px
    }

    /* Button styles to match site primary/secondary */
    .btn-primary {
        background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
        color: #fff;
        border: none;
        padding: 10px 18px;
        border-radius: 999px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 8px 24px rgba(59, 130, 246, 0.12);
        transition: transform .12s ease, box-shadow .12s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 30px rgba(59, 130, 246, 0.16);
    }

    .cancel-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        border-radius: 999px;
        text-decoration: none;
        color: var(--muted);
        background: #fff;
        border: 1px solid var(--border);
    }

    /* ensure save button sizing consistent */
    .btn-save {
        padding: 10px 18px;
    }
</style>

<body>
    </br>
    </br>
    <h2>✏️ Edit Karyawan (Hanya yang ada di Database)</h2>
    <form method="POST" id="editForm">
        <div class="form-actions" style="margin-top:12px;">
            <a href="?page=users" class="cancel-link">⬅️ Batal</a>
            <button type="submit" name="save_users" class="btn-primary btn-save">💾 Simpan Perubahan</button>
        </div>
        <div class="user-cards">
            <?php foreach ($users as $i => $u): ?>
                <div class="user-card">
                    <div class="user-card-header">User <?= $i + 1 ?> - PIN: <?= htmlspecialchars($u['pin']) ?></div>
                    <div class="user-card-body">
                        <input type="hidden" name="users[<?= $i ?>][id]" value="<?= htmlspecialchars($u['id']) ?>">
                        <input type="hidden" name="users[<?= $i ?>][pin]" value="<?= htmlspecialchars($u['pin']) ?>">
                        <div>
                            <label>Nama Lengkap</label>
                            <input type="text" name="users[<?= $i ?>][nama]" value="<?= htmlspecialchars($u['nama']) ?>"
                                required>
                        </div>
                        <div>
                            <label>NIP</label>
                            <input type="text" name="users[<?= $i ?>][nip]" value="<?= htmlspecialchars($u['nip']) ?>">
                        </div>
                        <div>
                            <label>NIK</label>
                            <input type="text" name="users[<?= $i ?>][nik]" value="<?= htmlspecialchars($u['nik']) ?>">
                        </div>
                        <div>
                            <label>Jenis Kelamin</label>
                            <select name="users[<?= $i ?>][jk]">
                                <option value="">- Pilih -</option>
                                <option value="L" <?= $u['jk'] === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="P" <?= $u['jk'] === 'P' ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>
                        <div>
                            <label>Jabatan</label>
                            <select name="users[<?= $i ?>][job_title]">
                                <?php foreach ($job_titles as $jt): ?>
                                    <option value="<?= $jt ?>" <?= $jt === $u['job_title'] ? 'selected' : '' ?>><?= $jt ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Level</label>
                            <select name="users[<?= $i ?>][job_level]">
                                <?php foreach ($job_levels as $jl): ?>
                                    <option value="<?= $jl ?>" <?= $jl === $u['job_level'] ? 'selected' : '' ?>><?= $jl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Bagian</label>
                            <select name="users[<?= $i ?>][bagian]">
                                <?php foreach ($bagian_list as $b): ?>
                                    <option value="<?= $b ?>" <?= $b === $u['bagian'] ? 'selected' : '' ?>><?= $b ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Departemen</label>
                            <select name="users[<?= $i ?>][departemen]">
                                <?php foreach ($departemen_list as $d): ?>
                                    <option value="<?= $d ?>" <?= $d === $u['departemen'] ? 'selected' : '' ?>><?= $d ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- TL select: this field is saved as users[...][tl_id] -->
                        <div style="margin:12px 0;">
                            <label style="display:block;margin-bottom:6px;font-weight:600;color:#6b7280">Team Leader (TL)</label>
                            <select name="users[<?= $i ?>][tl_id]">
                                <option value="">- Tanpa TL -</option>
                                <optgroup label="CABUT">
                                    <option value="8" <?= (string)($u['tl_id'] ?? '') === '8' ? 'selected' : '' ?>>Karyawati</option>
                                    <option value="3" <?= (string)($u['tl_id'] ?? '') === '3' ? 'selected' : '' ?>>Sri Utami</option>
                                    <option value="2" <?= (string)($u['tl_id'] ?? '') === '2' ? 'selected' : '' ?>>ST Nur Farokah</option>
                                    <option value="25" <?= (string)($u['tl_id'] ?? '') === '25' ? 'selected' : '' ?>>Fhilis Sulestari</option>
                                    <option value="22" <?= (string)($u['tl_id'] ?? '') === '22' ? 'selected' : '' ?>>Muhammad Regatana Hidayatulloh</option>
                                    <option value="119" <?= (string)($u['tl_id'] ?? '') === '119' ? 'selected' : '' ?>>Zusita Arsdhia Indrayani</option>
                                    <option value="34" <?= (string)($u['tl_id'] ?? '') === '34' ? 'selected' : '' ?>>Wahyu Surodo</option>
                                    <option value="60" <?= (string)($u['tl_id'] ?? '') === '60' ? 'selected' : '' ?>>Lutfi Dwi Firmansyah</option>
                                    <option value="109" <?= (string)($u['tl_id'] ?? '') === '109' ? 'selected' : '' ?>>Ruliatul Fidiah</option>
                                </optgroup>
                                <optgroup label="CETAK">
                                    <option value="57" <?= (string)($u['tl_id'] ?? '') === '57' ? 'selected' : '' ?>>Muhammad Tamamur Ridlwan</option>
                                    <option value="53" <?= (string)($u['tl_id'] ?? '') === '53' ? 'selected' : '' ?>>Abdul Rouf Khoiri</option>
                                    <option value="7" <?= (string)($u['tl_id'] ?? '') === '7' ? 'selected' : '' ?>>Anita</option>
                                    <option value="24" <?= (string)($u['tl_id'] ?? '') === '24' ? 'selected' : '' ?>>Patur Albertino</option>
                                    <option value="27" <?= (string)($u['tl_id'] ?? '') === '27' ? 'selected' : '' ?>>Anas Ja'far</option>
                                    <option value="48" <?= (string)($u['tl_id'] ?? '') === '48' ? 'selected' : '' ?>>M. Jamaludin</option>
                                    <option value="99" <?= (string)($u['tl_id'] ?? '') === '99' ? 'selected' : '' ?>>Nila Widya Sari</option>
                                    <option value="113" <?= (string)($u['tl_id'] ?? '') === '113' ? 'selected' : '' ?>>Nurul Izzuddin</option>
                                </optgroup>
                                <optgroup label="LAINNYA">
                                    <option value="1" <?= (string)($u['tl_id'] ?? '') === '1' ? 'selected' : '' ?>>Anik</option>
                                    <option value="98" <?= (string)($u['tl_id'] ?? '') === '98' ? 'selected' : '' ?>>M Gaung Sidiq</option>
                                    <option value="40" <?= (string)($u['tl_id'] ?? '') === '40' ? 'selected' : '' ?>>Cankiswan</option>
                                    <option value="118" <?= (string)($u['tl_id'] ?? '') === '118' ? 'selected' : '' ?>>Kerinna</option>
                                    <option value="63" <?= (string)($u['tl_id'] ?? '') === '63' ? 'selected' : '' ?>>Puput Indarwati</option>
                                </optgroup>
                            </select>
                        </div>


                    </div>
                </div>
            <?php endforeach; ?>
        </div>


    </form>
</body>

</html>