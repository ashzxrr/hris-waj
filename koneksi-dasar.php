<?php
require __DIR__ . '/vendor/autoload.php';

use OjiSatriani\Attendance\Solution;

$config = [
    'ip_address' => '192.168.0.102',
    'port'       => '80',
    'key'        => '0',
    'pin'        => 'All',
];

$client = Solution::init($config);

if ($client->connect()) {
    $logs = $client->response();

    echo str_pad("PIN", 10) . str_pad("Tanggal", 20) . "Waktu\n";
    echo str_repeat("-", 40) . "\n";

    foreach ($logs as $log) {
        $datetime = explode(" ", $log['datetime']);
        echo str_pad($log['pin'], 10) . str_pad($datetime[0], 20) . $datetime[1] . "\n";
    }
} else {
    echo "Gagal konek ke mesin.\n";
}
