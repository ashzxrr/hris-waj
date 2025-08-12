<?php
$ip = "192.168.0.102";
$port = 80;
$key = 0;

// =========================
// 1. Ambil Data User
// =========================
function getUserList($ip, $port, $key) {
    $soap_request = "<GetUserInfo>
                        <ArgComKey xsi:type=\"xsd:integer\">{$key}</ArgComKey>
                        <Arg><PIN xsi:type=\"xsd:integer\">All</PIN></Arg>
                     </GetUserInfo>";

    $connect = fsockopen($ip, $port, $errno, $errstr, 1);
    $userList = [];

    if ($connect) {
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

        preg_match_all('/<Row>(.*?)<\/Row>/s', $buffer, $matches);
        foreach ($matches[1] as $row) {
            preg_match('/<PIN>(.*?)<\/PIN>/', $row, $pin);
            preg_match('/<Name>(.*?)<\/Name>/', $row, $name);
            $userList[$pin[1]] = $name[1] ?? "Tidak Diketahui";
        }
    }
    return $userList;
}

// =========================
// 2. Ambil Data Absensi
// =========================
function getAttLog($ip, $port, $key, $filterDate) {
    $soap_request = "<GetAttLog>
                        <ArgComKey xsi:type=\"xsd:integer\">{$key}</ArgComKey>
                        <Arg><PIN xsi:type=\"xsd:integer\">All</PIN></Arg>
                     </GetAttLog>";

    $connect = fsockopen($ip, $port, $errno, $errstr, 1);
    $logList = [];

    if ($connect) {
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

        preg_match_all('/<Row>(.*?)<\/Row>/s', $buffer, $matches);
        foreach ($matches[1] as $row) {
            preg_match('/<PIN>(.*?)<\/PIN>/', $row, $pin);
            preg_match('/<DateTime>(.*?)<\/DateTime>/', $row, $dt);

            if (!empty($dt[1]) && strpos($dt[1], $filterDate) === 0) {
                $logList[] = [
                    'pin' => $pin[1],
                    'datetime' => $dt[1]
                ];
            }
        }
    }
    return $logList;
}

// =========================
// 3. Gabungkan dan Tampilkan HTML
// =========================
$userList = getUserList($ip, $port, $key);
$logs = getAttLog($ip, $port, $key, "2025-08-12");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Data Absensi</title>
    <style>
        table { border-collapse: collapse; width: 50%; }
        th, td { border: 1px solid black; padding: 5px; text-align: left; }
    </style>
</head>
<body>
<h2>Data Absensi Tanggal: 2025-08-12</h2>
<table>
    <tr>
        <th>PIN</th>
        <th>Nama</th>
        <th>Waktu</th>
    </tr>
    <?php foreach ($logs as $log): ?>
    <tr>
        <td><?= htmlspecialchars($log['pin']) ?></td>
        <td><?= htmlspecialchars($userList[$log['pin']] ?? 'Tidak Diketahui') ?></td>
        <td><?= htmlspecialchars($log['datetime']) ?></td>
    </tr>
    <?php endforeach; ?>
</table>
</body>
</html>
