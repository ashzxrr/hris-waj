<?php
date_default_timezone_set('Asia/Jakarta');

// IP dan port mesin absensi
$ip = '192.168.0.102';
$port = 80;

// Kirim SOAP request untuk ambil data absen
$soap_request = "<GetAttLog>
    <ArgComKey xsi:type=\"xsd:integer\">0</ArgComKey>
    <Arg><PIN xsi:type=\"xsd:integer\">All</PIN></Arg>
</GetAttLog>";

$connect = fsockopen($ip, $port, $errno, $errstr, 1);
if (!$connect) {
    die("Tidak bisa konek ke mesin absensi\n");
}

$soap_action = "POST /iWsService HTTP/1.0\r\n";
$soap_action .= "Content-Type: text/xml\r\n";
$soap_action .= "Content-Length: " . strlen($soap_request) . "\r\n\r\n";
$soap_action .= $soap_request;

fwrite($connect, $soap_action);
$response = '';

while ($response_part = fgets($connect, 1024)) {
    $response .= $response_part;
}
fclose($connect);

// Ambil data dari XML response
preg_match_all("/<Row><PIN>(.*?)<\/PIN><DateTime>(.*?)<\/DateTime>/", $response, $matches, PREG_SET_ORDER);

// Filter tanggal (hari ini)
$targetDate = date('Y-m-d');
echo "Data Absensi Tanggal $targetDate\n";
echo "--------------------------------\n";

foreach ($matches as $match) {
    $pin = $match[1];
    $datetime = $match[2];

    if (strpos($datetime, $targetDate) === 0) {
        echo "PIN: $pin - Waktu: $datetime\n";
    }
}
