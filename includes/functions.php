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

// Normalize PIN to canonical string form (remove whitespace, keep numeric value)
function normalize_pin($pin) {
    if ($pin === null) return '';
    // Remove non-digit characters and leading/trailing whitespace
    $p = trim((string)$pin);
    // If it's numeric-ish, normalize via intval to remove leading zeros
    if (preg_match('/^\d+$/', $p)) {
        return (string) intval($p);
    }
    // Fallback: return trimmed string
    return $p;
}

/**
 * MODIFIED: Auto combined dari SEMUA mesin aktif
 * Function signature tetap sama untuk backward compatibility
 */
function getUsers($ip = null, $port = null, $key = null) {
    global $machines;
    $allUsers = [];
    
    foreach ($machines as $machine) {
        if (!$machine['active']) continue;
        
        // Test koneksi dulu
        $connection = @fsockopen($machine['ip'], $machine['port'], $errno, $errstr, 5);
        if (!$connection) {
            error_log("Cannot connect to {$machine['name']}: {$errstr}");
            continue;
        }
        fclose($connection);
        
        // Get users dari mesin ini
        $soap_user = "<GetAllUserInfo>
            <ArgComKey xsi:type=\"xsd:integer\">{$machine['key']}</ArgComKey>
        </GetAllUserInfo>";

        $response_user = getSoapResponse($machine['ip'], $machine['port'], $soap_user);
        
        if ($response_user && strpos($response_user, '<Row>') !== false) {
            preg_match_all('/<Row>(.*?)<\/Row>/s', $response_user, $matches_user);
            foreach ($matches_user[1] as $row) {
                preg_match('/<PIN2>(.*?)<\/PIN2>/', $row, $pin2);
                preg_match('/<Name>(.*?)<\/Name>/', $row, $name);
                $pin = normalize_pin($pin2[1] ?? '');
                $nama = $name[1] ?? '';
                
                if ($pin && $nama) {
                    // Jika user sudah ada dari mesin lain, skip (hindari duplikat)
                    if ($pin !== '' && !isset($allUsers[$pin])) {
                        $allUsers[$pin] = $nama;
                    }
                }
            }
        }
    }
    
    return $allUsers;
}

/**
 * MODIFIED: Auto combined dari SEMUA mesin aktif  
 * Original function - tetap untuk backward compatibility
 */
function getAttendance($ip = null, $port = null, $key = null, $tanggal_filter = null, $users = null) {
    return getAttendanceRange(null, null, null, $tanggal_filter, $tanggal_filter, $users);
}

/**
 * MODIFIED: Auto combined dari SEMUA mesin aktif
 * New function untuk date range
 */
function getAttendanceRange($ip = null, $port = null, $key = null, $tanggal_dari = null, $tanggal_sampai = null, $users = null) {
    global $machines;
    $allAttendance = [];
    
    foreach ($machines as $machine) {
        if (!$machine['active']) continue;
        
        // Test koneksi dulu  
        $connection = @fsockopen($machine['ip'], $machine['port'], $errno, $errstr, 5);
        if (!$connection) {
            error_log("Cannot connect to {$machine['name']}: {$errstr}");
            continue;
        }
        fclose($connection);
        
        // Get attendance dari mesin ini
        $soap_attlog = "<GetAttLog>
            <ArgComKey xsi:type=\"xsd:integer\">{$machine['key']}</ArgComKey>
            <Arg><PIN xsi:type=\"xsd:integer\">All</PIN></Arg>
        </GetAttLog>";

        $response_attlog = getSoapResponse($machine['ip'], $machine['port'], $soap_attlog);
        
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
                    $pin_val = normalize_pin($pin[1] ?? '');
                        $nama_val = $users[$pin_val] ?? '(Tidak Diketahui)';
                    $status_text = ($status[1] ?? '') == "0" ? "IN" : "OUT";

                    $allAttendance[] = [
                        'nama' => $nama_val,
                        'pin' => $pin_val,
                        'datetime' => $waktu,
                        'verified' => $verified[1] ?? '-',
                        'status' => $status_text,
                        'tanggal' => $tanggal, // Tambahan field untuk kemudahan sorting/grouping
                        'machine_name' => $machine['name'] // Info mesin (opsional)
                    ];
                }
            }
        }
    }

    // Sort berdasarkan datetime (terbaru dulu)
    usort($allAttendance, function($a, $b) {
        return strtotime($b['datetime']) - strtotime($a['datetime']);
    });

    return $allAttendance;
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
        
        // Header CSV sesuai dengan tabel
        fputcsv($output, [
            'No',
            'NIP', 
            'Nama',
            'L/P',
            'Jabatan',
            'Kategori Gaji',
            'Tanggal',
            'In',
            'Out', 
            'Overtime',
            'Keterangan',
            'Ringkasan'
        ]);

        // Get selected users and date range from POST data
        global $selected_users, $tanggal_dari, $tanggal_sampai, $nip_data, $absence_notes;
        
        // Calculate summary data per user (sama seperti di modal)
        // Normalize selected users to match normalized PINs in attendance data
        $norm_selected = array_map('normalize_pin', $selected_users);
        $ordered_norm = array_values($norm_selected); // preserve original order

        $per_user_summary = [];
        // Initialize summary for each normalized user
        foreach ($ordered_norm as $pin) {
            $per_user_summary[$pin] = [
                'present' => 0,
                'no_absen' => 0, 
                'A' => 0,
                'S' => 0,
                'I' => 0
            ];
        }
        
        // Build attendance index for quick lookup
        $attendance_index = [];
        foreach ($data_absen as $rec) {
            $d = date('Y-m-d', strtotime($rec['datetime']));
            $p = normalize_pin($rec['pin']);
            $attendance_index[$p][$d] = true;
        }
        
        // Calculate summary over the period
        $periode = new DatePeriod(
            new DateTime($tanggal_dari), 
            new DateInterval('P1D'), 
            (new DateTime($tanggal_sampai))->modify('+1 day')
        );
        
        foreach ($ordered_norm as $pin) {
            foreach ($periode as $d) {
                $ds = $d->format('Y-m-d');
                $is_sunday = (date('N', strtotime($ds)) == 7);
                $has_attendance = isset($attendance_index[$pin]) && !empty($attendance_index[$pin][$ds]);
                
                if ($has_attendance) {
                    $per_user_summary[$pin]['present']++;
                } else {
                    // don't count Sundays as no-absen
                    if (!$is_sunday) {
                        $per_user_summary[$pin]['no_absen']++;
                        $code = $absence_notes[$pin][$ds] ?? '';
                        if ($code && isset($per_user_summary[$pin][$code])) {
                            $per_user_summary[$pin][$code]++;
                        }
                    }
                }
            }
        }
        
        // Generate rows sesuai dengan urutan di tabel
        $rowNo = 1;
        
        foreach ($selected_users as $pin) {
            foreach ($periode as $tgl) {
                $tanggal_str = $tgl->format('Y-m-d');
                $records_on_date = array_filter($data_absen, function ($item) use ($pin, $tanggal_str) {
                    return $item['pin'] == $pin && date('Y-m-d', strtotime($item['datetime'])) == $tanggal_str;
                });

                // Get user info
                $nip = $nip_data[$pin]['nip'] ?? '-';
                $nama = $nip_data[$pin]['nama'] ?? '-';
                $jk = $nip_data[$pin]['jk'] ?? '-';
                $job_title = $nip_data[$pin]['job_title'] ?? '-';
                $job_level = $nip_data[$pin]['job_level'] ?? '-';
                $kategori_gaji = $nip_data[$pin]['kategori_gaji'] ?? '-';
                
                // Format jabatan
                $jabatan = trim($job_title . ' ' . ($job_level && $job_level !== '-' ? '(' . $job_level . ')' : ''));
                if ($jabatan === '') $jabatan = '-';
                
                // Format tanggal
                $tanggal_display = date('d/m/Y', strtotime($tanggal_str));
                
                // Generate summary text (hanya untuk baris pertama user ini)
                $ringkasan = '';
                static $processed_users = [];
                if (!in_array($pin, $processed_users)) {
                    $summary = $per_user_summary[$pin];
                    $ringkasan = sprintf(
                        "Total Hadir: %d | Tidak Absen: %d | Alpha (A): %d | Sakit (S): %d | Ijin (I): %d",
                        $summary['present'],
                        $summary['no_absen'], 
                        $summary['A'],
                        $summary['S'],
                        $summary['I']
                    );
                    $processed_users[] = $pin;
                }
                
                if (!empty($records_on_date)) {
                    // Ada record absensi
                    // Collect IN and OUT times
                    $in_times = [];
                    $out_times = [];
                    foreach ($records_on_date as $record) {
                        $ts = strtotime($record['datetime']);
                        if (strtoupper($record['status']) === 'IN') {
                            $in_times[] = $ts;
                        } else {
                            $out_times[] = $ts;
                        }
                    }

                    $in_ts = !empty($in_times) ? min($in_times) : null;
                    $out_ts = !empty($out_times) ? max($out_times) : null;

                    $in_display = $in_ts ? date('H:i', $in_ts) : '-';
                    $out_display = $out_ts ? date('H:i', $out_ts) : '-';

                    // Calculate overtime
                    if ($out_ts) {
                        $threshold = strtotime($tanggal_str . ' 16:30:00');
                        $overtime_minutes = $out_ts > $threshold ? floor(($out_ts - $threshold) / 60) : 0;
                        $overtime_display = $overtime_minutes > 0 ? $overtime_minutes . ' menit' : '';
                    } else {
                        $overtime_display = '';
                    }
                    
                    $keterangan = '----';
                    
                } else {
                    // Tidak ada record
                    $in_display = '-';
                    $out_display = '-';
                    $overtime_display = '';
                    
                    // Check if Sunday
                    $hari = getNamaHari($tanggal_str);
                    if ($hari === 'Minggu') {
                        $keterangan = 'Minggu';
                    } else {
                        // Check absence code
                        $existing_code = $absence_notes[$pin][$tanggal_str] ?? '';
                        $code_labels = [
                            'S' => 'S (Sakit)',
                            'A' => 'A (Alpha)', 
                            'I' => 'I (Ijin)'
                        ];
                        $keterangan = !empty($existing_code) ? ($code_labels[$existing_code] ?? $existing_code) : '-';
                    }
                }

                // Write CSV row
                fputcsv($output, [
                    $rowNo++,
                    $nip,
                    $nama, 
                    $jk,
                    $jabatan,
                    $kategori_gaji,
                    $tanggal_display,
                    $in_display,
                    $out_display,
                    $overtime_display,
                    $keterangan,
                    $ringkasan
                ]);
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

/**
 * DEBUG FUNCTION - Test koneksi semua mesin (opsional)
 */
function testAllMachinesConnection() {
    global $machines;
    
    echo "=== TEST KONEKSI SEMUA MESIN ===\n";
    foreach ($machines as $machine) {
        if (!$machine['active']) {
            echo "- {$machine['name']}: TIDAK AKTIF\n";
            continue;
        }
        
        $connection = @fsockopen($machine['ip'], $machine['port'], $errno, $errstr, 5);
        if ($connection) {
            fclose($connection);
            echo "✓ {$machine['name']}: ONLINE\n";
        } else {
            echo "✗ {$machine['name']}: OFFLINE - {$errstr}\n";
        }
    }
    echo "\n";
}

/**
 * Hitung jumlah karyawan unik yang hadir berdasarkan record IN
 * @param array $data_absen daftar record attendance (mengandung field 'pin' dan 'status')
 * @return int jumlah karyawan unik yang punya setidaknya satu status 'IN'
 */
function getUniquePresentCount($data_absen) {
    $unique = [];
    foreach ($data_absen as $rec) {
        $status = strtoupper($rec['status'] ?? '');
        if ($status === 'IN') {
            $p = normalize_pin($rec['pin'] ?? '');
            if ($p !== '') $unique[$p] = true;
        }
    }
    return count($unique);
}
?>