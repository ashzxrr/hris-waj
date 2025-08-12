<?php
date_default_timezone_set('Asia/Jakarta');

$ip   = '192.168.0.102';
$port = 80;

// ========================
// 1. Ambil data user
// ========================
$soap_users = "<GetAllUserInfo>
    <ArgComKey xsi:type=\"xsd:integer\">0</ArgComKey>
</GetAllUserInfo>";

$userList = [];
$response_users = sendSoapRequest($ip, $port, $soap_users);

// Ambil PIN dan Nama dari response user
preg_match_all("/<Row><PIN>(.*?)<\/PIN><Name>(.*?)<\/Name>/", $response_users, $matches_users, PREG_SET_ORDER);

foreach ($matches_users as $row) {
    $userList[$row[1]] = $row[2];
}

// ========================
// 2. Ambil data absensi
// ========================
$soap_logs = "<GetAttLog>
    <ArgComKey xsi:type=\"xsd:integer\">0</ArgComKey>
    <Arg><PIN xsi:type=\"xsd:integer\">All</PIN></Arg>
</GetAttLog>";

$response_logs = sendSoapRequest($ip, $port, $soap_logs);

// Ambil PIN dan DateTime dari log
preg_match_all("/<Row><PIN>(.*?)<\/PIN><DateTime>(.*?)<\/DateTime>/", $response_logs, $matches_logs, PREG_SET_ORDER);

// ========================
// 3. Filter log hari ini dan gabungkan nama
// ========================
$targetDate = date('Y-m-d');

echo "Data Absensi Tanggal: $targetDate\n";
echo "-----------------------------------------\n";
echo str_pad("PIN", 8) . str_pad("Nama", 20) . "Waktu\n";
echo "-----------------------------------------\n";

foreach ($matches_logs as $row) {
    $pin = $row[1];
    $datetime = $row[2];

    if (strpos($datetime, $targetDate) === 0) {
        $nama = isset($userList[$pin]) ? $userList[$pin] : "Tidak Diketahui";
        echo str_pad($pin, 8) . str_pad($nama, 20) . $datetime . "\n";
    }
}

// ========================
// Fungsi Kirim SOAP Request
// ========================
function sendSoapRequest($ip, $port, $soap_request)
{
    $connect = fsockopen($ip, $port, $errno, $errstr, 1);
    if (!$connect) {
        die("Tidak bisa konek ke mesin absensi: $errstr ($errno)\n");
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
