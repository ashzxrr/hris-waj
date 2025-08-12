<?php
$ip = "192.168.0.102";
$port = 80;
$key = 0;

// Ambil data user
$users = [];
$connect = fsockopen($ip, $port, $errno, $errstr, 1);
if ($connect) {
    $soap_request = "<GetUserInfo>
                        <ArgComKey xsi:type=\"xsd:integer\">{$key}</ArgComKey>
                        <Arg>
                            <PIN xsi:type=\"xsd:integer\">All</PIN>
                        </Arg>
                     </GetUserInfo>";

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
        $users[$pin[1]] = $name[1] ?: "Tidak Diketahui";
    }
}

// Ambil data absensi tanggal tertentu
$tanggal_filter = "2025-08-12"; // filter tanggal
$logs = [];
$connect = fsockopen($ip, $port, $errno, $errstr, 1);
if ($connect) {
    $soap_request = "<GetAttLog>
                        <ArgComKey xsi:type=\"xsd:integer\">{$key}</ArgComKey>
                        <Arg>
                            <PIN xsi:type=\"xsd:integer\">All</PIN>
                        </Arg>
                     </GetAttLog>";

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
        preg_match('/<DateTime>(.*?)<\/DateTime>/', $row, $datetime);

        if (strpos($datetime[1], $tanggal_filter) === 0) {
            $logs[] = [
                'pin' => $pin[1],
                'nama' => $users[$pin[1]] ?? "Tidak Diketahui",
                'datetime' => $datetime[1]
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Data Absensi</title>
    <style>
        table {
            border-collapse: collapse;
            width: 60%;
            margin: 20px auto;
        }
        table, th, td {
            border: 1px solid #333;
        }
        th, td {
            padding: 8px 12px;
            text-align: center;
        }
        th {
            background: #eee;
        }
    </style>
</head>
<body>
    <h2 style="text-align:center;">Data Absensi Tanggal: <?= $tanggal_filter ?></h2>
    <table>
        <tr>
            <th>PIN</th>
            <th>Nama</th>
            <th>Waktu</th>
        </tr>
        <?php foreach ($logs as $log): ?>
        <tr>
            <td><?= htmlspecialchars($log['pin']) ?></td>
            <td><?= htmlspecialchars($log['nama']) ?></td>
            <td><?= htmlspecialchars($log['datetime']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
