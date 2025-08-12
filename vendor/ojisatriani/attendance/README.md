## Attendance API PHP
Solution Attendance Management Web Service SDK

## Solution

Compatible Device:
```sh
X100-C, X304, X302-S, X401, X601, C1, X606-S
```

How To Use:
```php
use OjiSatriani\Attendance\Solution;
$config =   [
                'ip_address'    =>'127.0.0.1',
                'port'          => '80',
                'key'           => '0',
                'pin'           => 'All',
            ];
$connect    = Solution::init($config)->connect(); // checking connection
$response   = Solution::init($config)->response(); // get AttLog Data
```
