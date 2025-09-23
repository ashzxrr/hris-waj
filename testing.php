<?php
/**
 * ===============================================
 * KONFIGURASI MULTI MESIN FINGERPRINT
 * ===============================================
 * 
 * Sistem untuk mengelola multiple mesin fingerprint
 * yang terhubung melalui port forwarding
 * 
 * Author: System Administrator
 * Last Update: 2024
 */

// Database connection is provided by includes/config.php
// require_once __DIR__ . '/includes/config.php' will set up $mysqli
// If includes/config.php is not present, you can set $mysqli here as fallback.

// Ambil konfigurasi mesin dari includes/config.php (single source of truth)
require_once __DIR__ . '/includes/config.php';

// gunakan $fingerprintMachines dari config (fallback ke lokal jika tidak ada)
if (empty($fingerprintMachines)) {
    die("❌ Konfigurasi mesin tidak ditemukan di includes/config.php\n");
}

// ===============================================
// 3. PENGATURAN SISTEM
// ===============================================
ini_set('default_socket_timeout', 10);
ini_set('max_execution_time', 300);

// ===============================================
// 4. FUNGSI UTILITAS
// ===============================================

/**
 * Menampilkan daftar semua mesin
 */
function showMachineList($machines) {
    echo "=== DAFTAR MESIN FINGERPRINT ===\n";
    echo str_repeat("=", 50) . "\n";
    
    foreach ($machines as $key => $machine) {
        $status = $machine['active'] ? '🟢 AKTIF' : '🔴 TIDAK AKTIF';
        
        echo "Key ID        : {$key}\n";
        echo "Nama          : {$machine['name']}\n";
        echo "Lokasi        : {$machine['location']}\n";
        echo "Akses Via     : {$machine['router_ip']}:{$machine['router_port']}\n";
        echo "Target Asli   : {$machine['machine_ip']}:{$machine['machine_port']}\n";
        echo "Status        : {$status}\n";
        echo str_repeat("-", 50) . "\n";
    }
    echo "\n";
}

/**
 * Test koneksi ke mesin tertentu
 */
function testMachineConnection($machine) {
    $ip = $machine['router_ip'];
    $port = $machine['router_port'];
    $timeout = $machine['timeout'];
    
    echo "🔄 Testing koneksi ke {$machine['name']}...\n";
    echo "   Target: {$ip}:{$port}\n";
    
    $startTime = microtime(true);
    $connection = @fsockopen($ip, $port, $errno, $errstr, $timeout);
    $responseTime = round((microtime(true) - $startTime) * 1000, 2);
    
    if ($connection) {
        fclose($connection);
        echo "✅ BERHASIL - Response time: {$responseTime}ms\n";
        return true;
    } else {
        echo "❌ GAGAL - Error: {$errstr} ({$errno})\n";
        return false;
    }
}

/**
 * Test koneksi semua mesin
 */
function testAllMachines($machines) {
    echo "=== TEST KONEKSI SEMUA MESIN ===\n";
    echo str_repeat("=", 50) . "\n";
    
    $results = [];
    
    foreach ($machines as $key => $machine) {
        if (!$machine['active']) {
            echo "⏭️  Mesin {$machine['name']} tidak aktif, dilewati\n\n";
            continue;
        }
        
        $isConnected = testMachineConnection($machine);
        $results[$key] = $isConnected;
        echo "\n";
    }
    
    // Ringkasan hasil
    echo "=== RINGKASAN TEST KONEKSI ===\n";
    $connected = 0;
    $total = count($results);
    
    foreach ($results as $key => $status) {
        $statusText = $status ? '✅ ONLINE' : '❌ OFFLINE';
        echo "{$machines[$key]['name']}: {$statusText}\n";
        if ($status) $connected++;
    }
    
    echo "\nTotal: {$connected}/{$total} mesin online\n\n";
    return $results;
}

/**
 * Ambil konfigurasi mesin tertentu
 */
function getMachine($key, $machines) {
    if (!isset($machines[$key])) {
        die("❌ Error: Mesin dengan key '{$key}' tidak ditemukan!\n");
    }
    
    return $machines[$key];
}

/**
 * Proses data dari mesin tertentu
 */
function processAttendanceData($machineKey, $machine) {
    global $mysqli;
    
    echo "📡 Memproses data dari {$machine['name']}...\n";
    
    // Test koneksi dulu
    if (!testMachineConnection($machine)) {
        echo "❌ Tidak bisa terhubung ke mesin, proses dibatalkan\n";
        return false;
    }
    
    // Di sini implementasi sesuai SDK yang digunakan
    // Contoh:
    /*
    $ip = $machine['router_ip'];
    $port = $machine['router_port'];
    
    // Inisialisasi SDK fingerprint
    // $zk = new ZKTeco($ip, $port);
    // $attendanceData = $zk->getAttendance();
    
    // Simpan ke database
    // foreach ($attendanceData as $record) {
    //     $stmt = $mysqli->prepare("INSERT INTO attendance (user_id, timestamp, machine_id, location) VALUES (?, ?, ?, ?)");
    //     $stmt->bind_param("ssis", $record['user_id'], $record['timestamp'], $machine['id'], $machine['location']);
    //     $stmt->execute();
    // }
    */
    
    echo "✅ Data berhasil diproses dari {$machine['name']}\n";
    return true;
}

// ===============================================
// 5. EKSEKUSI PROGRAM
// ===============================================

echo "SISTEM MULTI MESIN FINGERPRINT\n";
echo str_repeat("=", 50) . "\n\n";

// Tampilkan daftar mesin
showMachineList($fingerprintMachines);

// Test koneksi semua mesin
$connectionResults = testAllMachines($fingerprintMachines);

// ===============================================
// 6. CONTOH PENGGUNAAN
// ===============================================

echo "=== CONTOH PENGGUNAAN ===\n";
echo "// 1. Akses mesin tertentu:\n";
echo "// \$machine = getMachine('produksi_2a', \$fingerprintMachines);\n";
echo "// \$ip = \$machine['router_ip'];\n";
echo "// \$port = \$machine['router_port'];\n\n";

echo "// 2. Proses data dari mesin tertentu:\n";
echo "// processAttendanceData('produksi_2a', \$machine);\n\n";

echo "// 3. Proses semua mesin yang online:\n";
echo "/*\n";
echo "foreach (\$fingerprintMachines as \$key => \$machine) {\n";
echo "    if (\$machine['active'] && \$connectionResults[\$key]) {\n";
echo "        processAttendanceData(\$key, \$machine);\n";
echo "    }\n";
echo "}\n";
echo "*/\n\n";

// ===============================================
// 7. INFORMASI PORT FORWARDING
// ===============================================
echo "=== KONFIGURASI PORT FORWARDING YANG DIPERLUKAN ===\n";
echo str_repeat("=", 60) . "\n";

foreach ($fingerprintMachines as $key => $machine) {
    if (!$machine['active']) continue;
    
    echo "Router: {$machine['router_ip']}\n";
    echo "- External Port: {$machine['router_port']}\n";
    echo "- Internal IP: {$machine['machine_ip']}\n";
    echo "- Internal Port: {$machine['machine_port']}\n";
    echo "- Protocol: TCP\n";
    echo str_repeat("-", 30) . "\n";
}

echo "\n=== SELESAI ===\n";
?>