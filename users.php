<?php
$ip = "192.168.0.102";
$port = 80;
$key = 0;

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

    // Parsing data user
    preg_match_all('/<Row>(.*?)<\/Row>/s', $buffer, $matches);
    foreach ($matches[1] as $row) {
        preg_match('/<PIN>(.*?)<\/PIN>/', $row, $pin);
        preg_match('/<Name>(.*?)<\/Name>/', $row, $name);
        preg_match('/<Privilege>(.*?)<\/Privilege>/', $row, $privilege);

        echo "PIN: {$pin[1]} | Nama: {$name[1]} | Privilege: {$privilege[1]}\n";
    }
} else {
    echo "Gagal konek ke mesin: $errstr ($errno)\n";
}
