<?php
// Copy to config.php and fill in real values.
return [
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'helppy',
        'user'     => 'root',
        'pass'     => '',
        'charset'  => 'utf8mb4',
    ],
    'base_url'   => 'http://localhost/Helppy.com/public',
    'upload_dir' => __DIR__ . '/../public/uploads',
    'upload_url' => 'http://localhost/Helppy.com/public/uploads',
    'debug'      => true,
];
