<?php
// Test koneksi ke mesin fingerprint
$ip = "192.168.110.2"; // IP Router Tenda
$port = 8080;          // Port forwarding
$timeout = 5;

echo "=== TEST KONEKSI FINGERPRINT ===\n";
echo "Target: {$ip}:{$port}\n";
echo "Timeout: {$timeout} detik\n\n";

// Test 1: Socket Connection
$connection = @fsockopen($ip, $port, $errno, $errstr, $timeout);

if ($connection) {
    echo "✅ Socket Connection: BERHASIL\n";
    fclose($connection);
} else {
    echo "❌ Socket Connection: GAGAL\n";
    echo "Error: {$errno} - {$errstr}\n";
}

// Test 2: CURL Test
echo "\n=== TEST CURL ===\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://{$ip}:{$port}");
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($result !== false && $httpCode > 0) {
    echo "✅ HTTP Connection: BERHASIL\n";
    echo "HTTP Code: {$httpCode}\n";
} else {
    echo "❌ HTTP Connection: GAGAL\n";
    echo "Error: {$error}\n";
}

// Test 3: Ping Test (jika available)
echo "\n=== TEST PING ===\n";
$ping = exec("ping -c 1 -W 3 {$ip}", $output, $result);
if ($result == 0) {
    echo "✅ Ping: BERHASIL\n";
} else {
    echo "❌ Ping: GAGAL\n";
}

echo "\n=== KESIMPULAN ===\n";
if ($connection && $result !== false) {
    echo "✅ Mesin fingerprint DAPAT diakses\n";
    echo "Jika absensi masih jalan, cek:\n";
    echo "1. Apakah web HRIS butuh koneksi real-time ke mesin?\n";
    echo "2. Ada fallback ke input manual?\n";
    echo "3. Data diambil dengan cronjob?\n";
} else {
    echo "❌ Mesin fingerprint TIDAK dapat diakses\n";
    echo "Cek port forwarding dan koneksi jaringan\n";
}
?>


<?php
// Konfigurasi mesin fingerprint via Port Forwarding
$ip = "192.168.110.2"; // IP Router Tenda (gateway)
$port = 8080;          // Port forwarding yang sudah diset di router Tenda
$soap_port = $port;    // Port untuk SOAP communication
$key = 0;              // Communication key

// Database tetap sama (lokal)
$mysqli = new mysqli("localhost", "root", "", "absensi");

if ($mysqli->connect_errno) {
    die("Gagal konek MySQL: " . $mysqli->connect_error);
}

$dbHost = "localhost";
$dbUser = "root"; 
$dbPass = "";
$dbName = "absensi";

// Optional: Tambah timeout untuk koneksi yang lebih lama
ini_set('default_socket_timeout', 10);

echo "Koneksi fingerprint: {$ip}:{$port}\n";
echo "Target asli: 192.168.0.100:80 (via port forwarding)\n";
?>