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

// Helper function untuk export data ke CSV
function exportToCsv($data_absen, $filename = null, $pinOrder = null) {
    ob_clean(); // Bersihkan output buffer
    
    if (!$filename) {
        $filename = 'absensi_' . date('Y-m-d_H-i-s') . '.csv';
    }
    
    // Pastikan filename aman
    $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $filename);
    
    // Set headers untuk download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    try {
        $output = fopen('php://temp', 'r+');
        
        // Add BOM untuk proper UTF-8 encoding di Excel
        fwrite($output, "\xEF\xBB\xBF");
        
        // Header CSV — match the detail table columns (including Kategori Gaji)
        fputcsv($output, [
            'No',
            'PIN',
            'NIP',
            'Nama',
            'Kategori Gaji',
            'Jenis Kelamin',
            'Jabatan',
            'Tanggal',
            'In',
            'Out',
            'Overtime (menit)',
            'Keterangan'
        ]);

        // Aggregate data per PIN + date to match the table view
        $groups = [];
        foreach ($data_absen as $record) {
            $pin = $record['pin'] ?? '-';
            $dt = $record['datetime'] ?? null;
            $date = $dt ? date('Y-m-d', strtotime($dt)) : '-';

            if (!isset($groups[$pin][$date])) {
                $groups[$pin][$date] = [
                    'pin' => $pin,
                    'nip' => $record['nip'] ?? '-',
                    'nama' => $record['nama'] ?? '-',
                    'kategori_gaji' => $record['kategori_gaji'] ?? ($record['kategori_gaji'] ?? '-'),
                    'jk' => $record['jk'] ?? '-',
                    'job_title' => $record['job_title'] ?? '',
                    'job_level' => $record['job_level'] ?? '',
                    'in_times' => [],
                    'out_times' => [],
                    'keterangan' => $record['code'] ?? ''
                ];
            }

            if (!empty($dt)) {
                $ts = strtotime($dt);
                // Treat status 'IN' as in_times, others as out_times
                $status = strtoupper($record['status'] ?? '');
                if ($status === 'IN') $groups[$pin][$date]['in_times'][] = $ts;
                else $groups[$pin][$date]['out_times'][] = $ts;
            }
        }

        // If a specific pin order is provided, honor it: for each pin in pinOrder, write that pin's dates ascending
        $rowNo = 1;
        if (!empty($pinOrder) && is_array($pinOrder)) {
            foreach ($pinOrder as $pin) {
                if (!isset($groups[$pin])) continue;
                $dates = $groups[$pin];
                ksort($dates);
                foreach ($dates as $date => $g) {
                    $in_ts = !empty($g['in_times']) ? min($g['in_times']) : null;
                    $out_ts = !empty($g['out_times']) ? max($g['out_times']) : null;

                    $in_display = $in_ts ? date('H:i:s', $in_ts) : '-';
                    $out_display = $out_ts ? date('H:i:s', $out_ts) : '-';

                    // Overtime in minutes after 16:30
                    if ($out_ts) {
                        $threshold = strtotime($date . ' 16:30:00');
                        $overtime_minutes = $out_ts > $threshold ? floor(($out_ts - $threshold) / 60) : 0;
                    } else {
                        $overtime_minutes = '';
                    }

                    $jabatan = trim(($g['job_title'] ?? '') . ' ' . (!empty($g['job_level']) && $g['job_level'] !== '-' ? '(' . $g['job_level'] . ')' : '')) ?: '-';
                    $keterangan = $g['keterangan'] !== '' ? $g['keterangan'] : '-';

                    fputcsv($output, [
                        $rowNo++,
                        $g['pin'] ?? '-',
                        $g['nip'] ?? '-',
                        $g['nama'] ?? '-',
                        $g['kategori_gaji'] ?? '-',
                        $g['jk'] ?? '-',
                        $jabatan,
                        $date === '-' ? '-' : date('d/m/Y', strtotime($date)),
                        $in_display,
                        $out_display,
                        $overtime_minutes,
                        $keterangan
                    ]);
                }
            }
        } else {
            // Default behavior: iterate pins in groups and sort by date ascending per pin
            foreach ($groups as $pin => $dates) {
                ksort($dates);
                foreach ($dates as $date => $g) {
                    $in_ts = !empty($g['in_times']) ? min($g['in_times']) : null;
                    $out_ts = !empty($g['out_times']) ? max($g['out_times']) : null;

                    $in_display = $in_ts ? date('H:i:s', $in_ts) : '-';
                    $out_display = $out_ts ? date('H:i:s', $out_ts) : '-';

                    // Overtime in minutes after 16:30
                    if ($out_ts) {
                        $threshold = strtotime($date . ' 16:30:00');
                        $overtime_minutes = $out_ts > $threshold ? floor(($out_ts - $threshold) / 60) : 0;
                    } else {
                        $overtime_minutes = '';
                    }

                    $jabatan = trim(($g['job_title'] ?? '') . ' ' . (!empty($g['job_level']) && $g['job_level'] !== '-' ? '(' . $g['job_level'] . ')' : '')) ?: '-';
                    $keterangan = $g['keterangan'] !== '' ? $g['keterangan'] : '-';

                    fputcsv($output, [
                        $rowNo++,
                        $g['pin'] ?? '-',
                        $g['nip'] ?? '-',
                        $g['nama'] ?? '-',
                        $g['kategori_gaji'] ?? '-',
                        $g['jk'] ?? '-',
                        $jabatan,
                        $date === '-' ? '-' : date('d/m/Y', strtotime($date)),
                        $in_display,
                        $out_display,
                        $overtime_minutes,
                        $keterangan
                    ]);
                }
            }
        }
        
        // Reset pointer
        rewind($output);
        
        // Output file contents
        fpassthru($output);
        fclose($output);
        
    } catch (Exception $e) {
        // Log error jika perlu
        error_log("Error exporting CSV: " . $e->getMessage());
        header("HTTP/1.1 500 Internal Server Error");
        echo "Error generating CSV file";
    }
    
    exit();
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