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
    'base_url2'   => 'http://localhost/Helppy.com/public',
    'upload_dir2' => __DIR__ . '/../public/uploads',
    'upload_url2' => 'http://localhost/Helppy.com/public/uploads',
    
    'base_url'   => 'http://localhost/Helppy.com/public',
    'upload_dir' => __DIR__ . '/../public/uploads',
    'upload_url' => 'http://localhost/Helppy.com/public/uploads',
    'debug'      => true,
    'mailer' => [
        'host'     => 'smtp.gmail.com',
        'port'     => 587,
        'username' => '',          // YOUR Gmail address
        'password' => '',          // App Password (16 chars, NOT your real password)
        'from'     => 'Helppy.com <noreply@helppy.com>',
        'reply_to' => '',
        'timeout'  => 10,
    ],
];
