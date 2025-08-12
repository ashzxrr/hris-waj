<?php return array(
    'root' => array(
        'name' => 'user/absensi-solution',
        'pretty_version' => '1.0.0+no-version-set',
        'version' => '1.0.0.0',
        'reference' => null,
        'type' => 'library',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        'ojisatriani/attendance' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => 'ced0b40626d31f538635a7ec293f5f063eb4f9d4',
            'type' => 'library',
            'install_path' => __DIR__ . '/../ojisatriani/attendance',
            'aliases' => array(
                0 => '9999999-dev',
            ),
            'dev_requirement' => false,
        ),
        'user/absensi-solution' => array(
            'pretty_version' => '1.0.0+no-version-set',
            'version' => '1.0.0.0',
            'reference' => null,
            'type' => 'library',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
