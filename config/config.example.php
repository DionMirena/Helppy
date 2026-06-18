<?php
// Copy to config.php and fill in real values. config.php is gitignored.
return [
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'helppy',
        'user'     => 'root',
        'pass'     => '',
        'charset'  => 'utf8mb4',
    ],

    'base_url'   => 'https://helppy.com.loc',
    'upload_dir' => __DIR__ . '/../public/uploads',
    'upload_url' => 'https://helppy.com.loc/uploads',
    'debug'      => true,

    // ============================================================
    // PAYMENTS — every subscription payment from every provider lands
    // in the SINGLE admin account configured here.
    //
    // You fill in ONE Stripe account + ONE bank account (the admin account).
    // Every bank chip on the public /subscribe page automatically routes
    // to that admin IBAN, so providers can pay from Raiffeisen, NLB, TEB,
    // BKT, ProCredit — but all money ends up in your account.
    // ============================================================
    'payments' => [

        // -------- THE ADMIN ACCOUNT (where ALL transfers land) --------
        'admin' => [
            'beneficiary' => 'Helppy SH.P.K.',                // your registered name
            'iban'        => 'XK00 0000 0000 0000 0000',      // your IBAN
            'bank_name'   => '',                              // optional, e.g. "Raiffeisen Bank Kosovo"
            'swift'       => '',                              // optional, your bank's SWIFT/BIC
            'note'        => 'Përdor kodin e referencës si arsye e pagesës.',
        ],

        // -------- CARDS via Stripe (one admin Stripe account) --------
        // Stripe routes cards from Raiffeisen/NLB/TEB/BKT/ProCredit etc.
        'stripe' => [
            'enabled'         => false,            // flip true after pasting keys
            'secret_key'      => '',               // sk_test_... (or sk_live_... in production)
            'publishable_key' => '',               // pk_test_...
            'webhook_secret'  => '',               // whsec_...
        ],

        // -------- BANK CHIPS (display labels for the picker) --------
        // All share the admin IBAN above. Set 'enabled' => false to hide one.
        'banks' => [
            [ 'key' => 'raiffeisen', 'name' => 'Raiffeisen Bank Kosovo', 'short' => 'Raiffeisen', 'color' => '#FFEB00', 'gateway' => 'manual', 'enabled' => true ],
            [ 'key' => 'nlb',        'name' => 'NLB Banka',              'short' => 'NLB',        'color' => '#00803D', 'gateway' => 'manual', 'enabled' => true ],
            [ 'key' => 'teb',        'name' => 'TEB SH.A.',              'short' => 'TEB',        'color' => '#005DA8', 'gateway' => 'manual', 'enabled' => true ],
            [ 'key' => 'bkt',        'name' => 'BKT Kosova',             'short' => 'BKT',        'color' => '#003083', 'gateway' => 'manual', 'enabled' => true ],
            [ 'key' => 'procredit',  'name' => 'ProCredit Bank Kosovo',  'short' => 'ProCredit',  'color' => '#E2001A', 'gateway' => 'manual', 'enabled' => true ],
        ],
    ],

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
