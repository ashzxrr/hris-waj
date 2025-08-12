<?php
date_default_timezone_set('Asia/Jakarta');

$ip   = '192.168.0.102';
$port = 80;

echo "=== SISTEM ABSENSI FINGERPRINT X100C ===\n\n";

// ========================
// 1. Ambil data user dengan mapping yang benar
// ========================
echo "📥 Mengambil data user dari mesin...\n";

$soap_users = "<GetAllUserInfo>
    <ArgComKey xsi:type=\"xsd:integer\">0</ArgComKey>
</GetAllUserInfo>";

$userMapping = []; // Format: [PIN_internal => [id_number, nama]]
$response_users = sendSoapRequest($ip, $port, $soap_users);

// Coba beberapa pattern untuk mendapatkan ID Number yang benar
$patterns = [
    'password' => "/<Row>.*?<PIN>(.*?)<\/PIN>.*?<Name>(.*?)<\/Name>.*?<Password>(.*?)<\/Password>.*?<\/Row>/s",
    'passwd'   => "/<Row>.*?<PIN>(.*?)<\/PIN>.*?<Name>(.*?)<\/Name>.*?<Passwd>(.*?)<\/Passwd>.*?<\/Row>/s",
    'cardno'   => "/<Row>.*?<PIN>(.*?)<\/PIN>.*?<Name>(.*?)<\/Name>.*?<CardNo>(.*?)<\/CardNo>.*?<\/Row>/s"
];

$found_pattern = null;
$field_name = '';

foreach ($patterns as $name => $pattern) {
    preg_match_all($pattern, $response_users, $matches, PREG_SET_ORDER);
    if (!empty($matches)) {
        $found_pattern = $matches;
        $field_name = $name;
        echo "✅ Data user berhasil diambil menggunakan field: " . strtoupper($name) . "\n";
        break;
    }
}

if ($found_pattern) {
    foreach ($found_pattern as $row) {
        $pin_internal = $row[1];          // PIN internal mesin
        $nama = $row[2];                  // Nama user
        $id_number = $row[3];             // ID Number sebenarnya
        
        $userMapping[$pin_internal] = [
            'id_number' => $id_number,
            'nama' => $nama
        ];
    }
    echo "📊 Total user ditemukan: " . count($userMapping) . " orang\n\n";
} else {
    // Fallback: gunakan PIN sebagai ID Number
    echo "⚠️  Menggunakan PIN sebagai ID Number (fallback)\n";
    preg_match_all("/<Row><PIN>(.*?)<\/PIN><Name>(.*?)<\/Name>/", $response_users, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $row) {
        $userMapping[$row[1]] = [
            'id_number' => $row[1],
            'nama' => $row[2]
        ];
    }
}

// Tampilkan mapping user untuk verifikasi
echo "📋 DAFTAR USER TERDAFTAR:\n";
echo str_repeat("-", 60) . "\n";
echo str_pad("PIN Internal", 15) . str_pad("ID Number", 15) . "Nama\n";
echo str_repeat("-", 60) . "\n";

foreach ($userMapping as $pin => $data) {
    echo str_pad($pin, 15) . str_pad($data['id_number'], 15) . $data['nama'] . "\n";
}
echo "\n";

// ========================
// 2. Ambil data absensi
// ========================
echo "📥 Mengambil data absensi dari mesin...\n";

$soap_logs = "<GetAttLog>
    <ArgComKey xsi:type=\"xsd:integer\">0</ArgComKey>
    <Arg><PIN xsi:type=\"xsd:integer\">All</PIN></Arg>
</GetAttLog>";

$response_logs = sendSoapRequest($ip, $port, $soap_logs);

// Parse log absensi
preg_match_all("/<Row><PIN>(.*?)<\/PIN><DateTime>(.*?)<\/DateTime>/", $response_logs, $matches_logs, PREG_SET_ORDER);

echo "✅ Data absensi berhasil diambil: " . count($matches_logs) . " record\n\n";

// ========================
// 3. Filter dan tampilkan absensi hari ini
// ========================
$targetDate = date('Y-m-d');

echo "📅 DATA ABSENSI TANGGAL: $targetDate\n";
echo str_repeat("=", 80) . "\n";
echo str_pad("PIN", 8) . str_pad("ID Number", 12) . str_pad("Nama", 25) . str_pad("Waktu", 20) . "Status\n";
echo str_repeat("-", 80) . "\n";

$absensi_hari_ini = [];
$tidak_dikenali = [];

foreach ($matches_logs as $row) {
    $pin_log = $row[1];      // PIN yang tercatat di log
    $datetime = $row[2];     // Waktu absensi
    
    // Filter hanya hari ini
    if (strpos($datetime, $targetDate) === 0) {
        
        if (isset($userMapping[$pin_log])) {
            // User ditemukan dalam mapping
            $user_data = $userMapping[$pin_log];
            $id_number = $user_data['id_number'];
            $nama = $user_data['nama'];
            $status = "✅ OK";
            
            echo str_pad($pin_log, 8) . 
                 str_pad($id_number, 12) . 
                 str_pad($nama, 25) . 
                 str_pad($datetime, 20) . 
                 $status . "\n";
                 
            $absensi_hari_ini[] = [
                'pin' => $pin_log,
                'id_number' => $id_number,
                'nama' => $nama,
                'waktu' => $datetime
            ];
            
        } else {
            // PIN tidak ditemukan dalam mapping user
            echo str_pad($pin_log, 8) . 
                 str_pad("???", 12) . 
                 str_pad("TIDAK DIKENALI", 25) . 
                 str_pad($datetime, 20) . 
                 "❌ ERROR\n";
                 
            $tidak_dikenali[] = $pin_log;
        }
    }
}

// ========================
// 4. Ringkasan dan troubleshooting
// ========================
echo "\n" . str_repeat("=", 80) . "\n";
echo "📊 RINGKASAN ABSENSI HARI INI\n";
echo str_repeat("-", 40) . "\n";
echo "Total absensi hari ini: " . count($absensi_hari_ini) . " record\n";
echo "User tidak dikenali: " . count(array_unique($tidak_dikenali)) . " PIN\n";

if (!empty($tidak_dikenali)) {
    echo "\n⚠️  PIN YANG TIDAK DIKENALI:\n";
    foreach (array_unique($tidak_dikenali) as $unknown_pin) {
        echo "- PIN: $unknown_pin\n";
    }
    echo "\n💡 Solusi: Periksa apakah user dengan PIN tersebut sudah terdaftar di mesin\n";
}

echo "\n✅ Field yang digunakan untuk ID Number: " . strtoupper($field_name ?: 'PIN (fallback)') . "\n";
echo "✅ Koneksi ke mesin: BERHASIL\n";
echo "✅ Parsing data: BERHASIL\n";

// ========================
// 5. Export ke array untuk penggunaan lebih lanjut
// ========================
function getAbsensiArray() {
    global $absensi_hari_ini;
    return $absensi_hari_ini;
}

// ========================
// Fungsi Kirim SOAP Request
// ========================
function sendSoapRequest($ip, $port, $soap_request)
{
    $connect = fsockopen($ip, $port, $errno, $errstr, 3);
    if (!$connect) {
        die("❌ GAGAL: Tidak bisa konek ke mesin $ip:$port - $errstr ($errno)\n");
    }

    $http_req  = "POST /iWsService HTTP/1.0\r\n";
    $http_req .= "Content-Type: text/xml\r\n";
    $http_req .= "Content-Length: " . strlen($soap_request) . "\r\n\r\n";
    $http_req .= $soap_request;

    fwrite($connect, $http_req);

    $response = '';
    while ($res = fgets($connect, 1024)) {
        $response .= $res;
    }
    fclose($connect);

    return $response;
}

echo "\n🎉 PROSES SELESAI!\n";

// Uncomment baris di bawah jika ingin melihat raw response untuk debugging
// echo "\n=== DEBUG INFO ===\n";
// echo "Raw User Response: " . substr($response_users, 0, 500) . "...\n";
// echo "Raw Log Response: " . substr($response_logs, 0, 500) . "...\n";
?>