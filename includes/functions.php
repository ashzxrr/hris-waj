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

// Original function - masih dipertahankan untuk backward compatibility
function getAttendance($ip, $port, $key, $tanggal_filter, $users) {
    return getAttendanceRange($ip, $port, $key, $tanggal_filter, $tanggal_filter, $users);
}

// New function untuk date range
function getAttendanceRange($ip, $port, $key, $tanggal_dari, $tanggal_sampai, $users) {
    $soap_attlog = "<GetAttLog>
        <ArgComKey xsi:type=\"xsd:integer\">$key</ArgComKey>
        <Arg><PIN xsi:type=\"xsd:integer\">All</PIN></Arg>
    </GetAttLog>";

    $response_attlog = getSoapResponse($ip, $port, $soap_attlog);
    $data_absen = [];

    if ($response_attlog && strpos($response_attlog, '<Row>') !== false) {
        preg_match_all('/<Row>(.*?)<\/Row>/s', $response_attlog, $matches_log);

        // Convert tanggal ke timestamp untuk comparison yang lebih efisien
        $timestamp_dari = strtotime($tanggal_dari);
        $timestamp_sampai = strtotime($tanggal_sampai . ' 23:59:59'); // Include sampai akhir hari

        foreach ($matches_log[1] as $row) {
            preg_match('/<PIN>(.*?)<\/PIN>/', $row, $pin);
            preg_match('/<DateTime>(.*?)<\/DateTime>/', $row, $datetime);
            preg_match('/<Verified>(.*?)<\/Verified>/', $row, $verified);
            preg_match('/<Status>(.*?)<\/Status>/', $row, $status);

            $waktu = $datetime[1] ?? '';
            $tanggal = substr($waktu, 0, 10); // Format: YYYY-MM-DD
            $timestamp_record = strtotime($waktu);

            // Check apakah tanggal dalam range
            if ($timestamp_record >= $timestamp_dari && $timestamp_record <= $timestamp_sampai) {
                $pin_val = $pin[1] ?? '';
                $nama_val = $users[$pin_val] ?? '(Tidak Diketahui)';
                $status_text = ($status[1] ?? '') == "0" ? "IN" : "OUT";

                $data_absen[] = [
                    'nama' => $nama_val,
                    'pin' => $pin_val,
                    'datetime' => $waktu,
                    'verified' => $verified[1] ?? '-',
                    'status' => $status_text,
                    'tanggal' => $tanggal // Tambahan field untuk kemudahan sorting/grouping
                ];
            }
        }
    }

    // Sort berdasarkan datetime (terbaru dulu)
    usort($data_absen, function($a, $b) {
        return strtotime($b['datetime']) - strtotime($a['datetime']);
    });

    return $data_absen;
}

// Helper function untuk mendapatkan statistik absensi
function getAttendanceStats($data_absen) {
    $stats = [
        'total' => count($data_absen),
        'total_in' => 0,
        'total_out' => 0,
        'by_date' => [],
        'by_user' => []
    ];

    foreach ($data_absen as $record) {
        // Count IN/OUT
        if ($record['status'] == 'IN') {
            $stats['total_in']++;
        } else {
            $stats['total_out']++;
        }

        // Group by date
        $date = substr($record['datetime'], 0, 10);
        if (!isset($stats['by_date'][$date])) {
            $stats['by_date'][$date] = ['total' => 0, 'in' => 0, 'out' => 0];
        }
        $stats['by_date'][$date]['total']++;
        $stats['by_date'][$date][$record['status'] == 'IN' ? 'in' : 'out']++;

        // Group by user
        $user_key = $record['pin'] . ' - ' . $record['nama'];
        if (!isset($stats['by_user'][$user_key])) {
            $stats['by_user'][$user_key] = ['total' => 0, 'in' => 0, 'out' => 0];
        }
        $stats['by_user'][$user_key]['total']++;
        $stats['by_user'][$user_key][$record['status'] == 'IN' ? 'in' : 'out']++;
    }

    return $stats;
}

// Helper function untuk export data ke CSV - Updated dengan field dari database
function exportToCsv($data_absen, $filename = null) {
    if (!$filename) {
        $filename = 'absensi_' . date('Y-m-d_H-i-s') . '.csv';
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM untuk proper UTF-8 encoding di Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header CSV dengan field database
    fputcsv($output, [
        'No', 
        'PIN', 
        'NIP', 
        'Nama', 
        'NIK', 
        'Jenis Kelamin', 
        'Job Title', 
        'Job Level', 
        'Bagian', 
        'Departemen', 
        'Tanggal', 
        'Waktu', 
        'Status', 
        'Verified'
    ]);
    
    // Data
    foreach ($data_absen as $i => $record) {
        fputcsv($output, [
            $i + 1,
            $record['pin'] ?? '-',
            $record['nip'] ?? '-',
            $record['nama'] ?? '-',
            $record['nik'] ?? '-',
            $record['jk'] ?? '-',
            $record['job_title'] ?? '-',
            $record['job_level'] ?? '-',
            $record['bagian'] ?? '-',
            $record['departemen'] ?? '-',
            isset($record['datetime']) ? date('d/m/Y', strtotime($record['datetime'])) : '-',
            isset($record['datetime']) ? date('H:i:s', strtotime($record['datetime'])) : '-',
            $record['status'] ?? '-',
            $record['verified'] ?? '-'
        ]);
    }
    
    fclose($output);
    exit;
}

// Helper function untuk format tanggal Indonesia
function formatTanggalIndonesia($tanggal) {
    $bulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $timestamp = strtotime($tanggal);
    $hari = date('j', $timestamp);
    $bulan_idx = (int)date('n', $timestamp);
    $tahun = date('Y', $timestamp);
    
    return $hari . ' ' . $bulan[$bulan_idx] . ' ' . $tahun;
}
?>