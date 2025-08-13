<?php
function getSoapResponse($ip, $port, $soap_request) {
    $connect = @fsockopen($ip, $port, $errno, $errstr, 10);
    if (!$connect) return false;

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

function getUsers($ip, $port, $key) {
    $soap_user = "<GetAllUserInfo>
        <ArgComKey xsi:type=\"xsd:integer\">$key</ArgComKey>
    </GetAllUserInfo>";

    $response_user = getSoapResponse($ip, $port, $soap_user);
    $users = [];

    if ($response_user && strpos($response_user, '<Row>') !== false) {
        preg_match_all('/<Row>(.*?)<\/Row>/s', $response_user, $matches_user);
        foreach ($matches_user[1] as $row) {
            preg_match('/<PIN2>(.*?)<\/PIN2>/', $row, $pin2);
            preg_match('/<Name>(.*?)<\/Name>/', $row, $name);
            $users[$pin2[1] ?? ''] = $name[1] ?? '';
        }
    }
    return $users;
}

function getAttendance($ip, $port, $key, $tanggal_filter, $users) {
    $soap_attlog = "<GetAttLog>
        <ArgComKey xsi:type=\"xsd:integer\">$key</ArgComKey>
        <Arg><PIN xsi:type=\"xsd:integer\">All</PIN></Arg>
    </GetAttLog>";

    $response_attlog = getSoapResponse($ip, $port, $soap_attlog);
    $data_absen = [];

    if ($response_attlog && strpos($response_attlog, '<Row>') !== false) {
        preg_match_all('/<Row>(.*?)<\/Row>/s', $response_attlog, $matches_log);

        foreach ($matches_log[1] as $row) {
            preg_match('/<PIN>(.*?)<\/PIN>/', $row, $pin);
            preg_match('/<DateTime>(.*?)<\/DateTime>/', $row, $datetime);
            preg_match('/<Verified>(.*?)<\/Verified>/', $row, $verified);
            preg_match('/<Status>(.*?)<\/Status>/', $row, $status);

            $waktu = $datetime[1] ?? '';
            $tanggal = substr($waktu, 0, 10);

            if ($tanggal === $tanggal_filter) {
                $pin_val = $pin[1] ?? '';
                $nama_val = $users[$pin_val] ?? '(Tidak Diketahui)';
                $status_text = ($status[1] ?? '') == "0" ? "IN" : "OUT";

                $data_absen[] = [
                    'nama' => $nama_val,
                    'pin' => $pin_val,
                    'datetime' => $waktu,
                    'verified' => $verified[1] ?? '-',
                    'status' => $status_text
                ];
            }
        }
    }
    return $data_absen;
}
