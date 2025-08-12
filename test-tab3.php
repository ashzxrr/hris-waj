<?php
$ip = "192.168.0.102";
$port = 80;
$key = 0;

echo "<h2>🔍 DEBUG MODE - Analisis Koneksi Fingerprint</h2>";

// =========================
// 1. Test Koneksi Dasar
// =========================
echo "<h3>1. Test Koneksi Dasar</h3>";
$connect = fsockopen($ip, $port, $errno, $errstr, 5);
if ($connect) {
    echo "✅ Koneksi ke $ip:$port BERHASIL<br>";
    fclose($connect);
} else {
    echo "❌ Koneksi GAGAL: $errstr ($errno)<br>";
    exit;
}

// =========================
// 2. Coba Berbagai Method GetUser
// =========================
echo "<h3>2. Test Berbagai Method GetUser</h3>";

$soap_methods = [
    "GetAllUserInfo" => "<GetAllUserInfo><ArgComKey xsi:type=\"xsd:integer\">$key</ArgComKey></GetAllUserInfo>",
    "GetUserInfo_All" => "<GetUserInfo><ArgComKey xsi:type=\"xsd:integer\">$key</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">All</PIN></Arg></GetUserInfo>",
    "GetAllUserID" => "<GetAllUserID><ArgComKey xsi:type=\"xsd:integer\">$key</ArgComKey></GetAllUserID>",
    "GetUserTemplate" => "<GetUserTemplate><ArgComKey xsi:type=\"xsd:integer\">$key</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">All</PIN></Arg></GetUserTemplate>"
];

function testSoapMethod($ip, $port, $method_name, $soap_request) {
    $connect = fsockopen($ip, $port, $errno, $errstr, 3);
    if (!$connect) {
        return "❌ Koneksi gagal: $errstr";
    }
    
    $newLine = "\r\n";
    fputs($connect, "POST /iWsService HTTP/1.0" . $newLine);
    fputs($connect, "Content-Type: text/xml" . $newLine);
    fputs($connect, "Content-Length: " . strlen($soap_request) . $newLine . $newLine);
    fputs($connect, $soap_request . $newLine);
    
    $buffer = "";
    while ($line = fgets($connect, 1024)) {
        $buffer .= $line;
    }
    fclose($connect);
    
    return $buffer;
}

foreach ($soap_methods as $method => $soap_request) {
    echo "<h4>Method: $method</h4>";
    $response = testSoapMethod($ip, $port, $method, $soap_request);
    
    // Cek apakah ada data user
    if (strpos($response, '<Row>') !== false) {
        echo "✅ <strong>Method $method BERHASIL!</strong><br>";
        
        // Tampilkan 2 record pertama untuk analisis
        preg_match_all('/<Row>(.*?)<\/Row>/s', $response, $matches);
        $total_users = count($matches[1]);
        echo "📊 Total user ditemukan: $total_users<br>";
        
        if ($total_users > 0) {
            echo "<details><summary>👁️ Lihat sample data (2 record pertama)</summary>";
            echo "<pre style='background:#f5f5f5; padding:10px; font-size:12px;'>";
            for ($i = 0; $i < min(2, $total_users); $i++) {
                echo "Record " . ($i+1) . ":\n";
                echo htmlspecialchars($matches[1][$i]) . "\n\n";
            }
            echo "</pre></details>";
            
            // Analisis field yang tersedia
            if (!empty($matches[1][0])) {
                preg_match_all('/<(\w+)>(.*?)<\/\1>/', $matches[1][0], $field_matches, PREG_SET_ORDER);
                echo "<strong>📋 Field yang tersedia:</strong><br>";
                foreach ($field_matches as $field) {
                    echo "- {$field[1]}: " . htmlspecialchars($field[2]) . "<br>";
                }
            }
        }
        echo "<hr>";
    } else {
        echo "❌ Tidak ada data user atau error<br>";
        
        // Tampilkan response mentah untuk debug
        echo "<details><summary>🔍 Lihat response mentah</summary>";
        echo "<pre style='background:#ffe6e6; padding:10px; font-size:11px;'>";
        echo htmlspecialchars(substr($response, 0, 1000)) . "...";
        echo "</pre></details><hr>";
    }
}

// =========================
// 3. Test GetAttLog untuk Perbandingan
// =========================
echo "<h3>3. Test GetAttLog (untuk perbandingan)</h3>";
$soap_log = "<GetAttLog><ArgComKey xsi:type=\"xsd:integer\">$key</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">All</PIN></Arg></GetAttLog>";
$log_response = testSoapMethod($ip, $port, "GetAttLog", $soap_log);

if (strpos($log_response, '<Row>') !== false) {
    preg_match_all('/<Row>(.*?)<\/Row>/s', $log_response, $log_matches);
    $total_logs = count($log_matches[1]);
    echo "✅ GetAttLog BERHASIL - Total log: $total_logs<br>";
    
    if ($total_logs > 0) {
        echo "<details><summary>👁️ Lihat sample log (2 record pertama)</summary>";
        echo "<pre style='background:#f0f8ff; padding:10px; font-size:12px;'>";
        for ($i = 0; $i < min(2, $total_logs); $i++) {
            echo "Log " . ($i+1) . ":\n";
            echo htmlspecialchars($log_matches[1][$i]) . "\n\n";
        }
        echo "</pre></details>";
    }
} else {
    echo "❌ GetAttLog juga bermasalah<br>";
}

// =========================
// 4. Test Direct Web Interface
// =========================
echo "<h3>4. Test Akses Web Interface</h3>";
echo "🌐 Coba akses manual:<br>";
echo "- <a href='http://$ip/csl/check' target='_blank'>http://$ip/csl/check</a><br>";
echo "- <a href='http://$ip' target='_blank'>http://$ip</a> (halaman utama)<br>";

// Test HTTP GET request
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 5,
        'header' => "User-Agent: Mozilla/5.0\r\n"
    ]
]);

$web_response = @file_get_contents("http://$ip", false, $context);
if ($web_response !== false) {
    echo "✅ Web interface dapat diakses<br>";
    if (strlen($web_response) > 100) {
        echo "📄 Response size: " . strlen($web_response) . " bytes<br>";
    }
} else {
    echo "❌ Web interface tidak dapat diakses via PHP<br>";
}

echo "<hr>";
echo "<h3>💡 Rekomendasi Selanjutnya:</h3>";
echo "<ul>";
echo "<li>Lihat method mana yang berhasil mengambil data user</li>";
echo "<li>Perhatikan struktur field yang tersedia</li>";
echo "<li>Gunakan method yang berhasil untuk kode utama</li>";
echo "<li>Sesuaikan parsing berdasarkan field yang ada</li>";
echo "</ul>";
?>