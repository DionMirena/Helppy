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
    // PAYMENTS — fill in only the parts you have, leave the rest blank.
    // The system enables/disables flows automatically based on what is set.
    // ============================================================
    'payments' => [

        // -------- CARDS (any bank) via Stripe --------
        // Stripe routes cards from RAIFFEISEN/NLB/TEB/BKT/ProCredit etc.,
        // so a single Stripe account handles every Kosovo bank's cards.
        // Sign up free at https://dashboard.stripe.com, then paste keys
        // from https://dashboard.stripe.com/test/apikeys.
        'stripe' => [
            'enabled'         => false,            // flip true after pasting keys
            'secret_key'      => '',               // sk_test_... (or sk_live_... in production)
            'publishable_key' => '',               // pk_test_...
            'webhook_secret'  => '',               // whsec_... from https://dashboard.stripe.com/test/webhooks
        ],

        // -------- DIRECT BANK TRANSFER (per bank) --------
        // Each entry is a bank you accept transfers to. Providers will see
        // a card-grid and can pick any one. Add or remove entries freely.
        //
        // 'gateway' field:
        //   'manual' — provider transfers from their app; admin manually
        //              activates at /admin/subscriptions after the money
        //              lands. THIS IS THE ONLY OPTION KOSOVO BANKS SUPPORT
        //              today as a self-serve API does not exist for them.
        //   'auto'   — reserved for the future: if Raiffeisen/NLB/TEB ever
        //              publishes a direct payment API, set gateway='auto'
        //              + paste the api_key, and we'll add a controller for
        //              that specific bank.
        //
        // Set 'enabled' => false to hide a bank without deleting its entry.
        'banks' => [
            [
                'key'         => 'raiffeisen',
                'name'        => 'Raiffeisen Bank Kosovo',
                'short'       => 'Raiffeisen',
                'color'       => '#FFEB00',
                'beneficiary' => 'Helppy SH.P.K.',
                'iban'        => 'XK00 0000 0000 0000 0000',  // paste real IBAN
                'swift'       => 'RBKORS22',
                'note'        => 'Përdor kodin e referencës si arsye e pagesës.',
                'gateway'     => 'manual',
                'api_key'     => '',
                'enabled'     => true,
            ],
            [
                'key'         => 'nlb',
                'name'        => 'NLB Banka',
                'short'       => 'NLB',
                'color'       => '#00803D',
                'beneficiary' => 'Helppy SH.P.K.',
                'iban'        => 'XK00 0000 0000 0000 0000',
                'swift'       => 'NLPBXKPR',
                'note'        => 'Përdor kodin e referencës si arsye e pagesës.',
                'gateway'     => 'manual',
                'api_key'     => '',
                'enabled'     => true,
            ],
            [
                'key'         => 'teb',
                'name'        => 'TEB SH.A.',
                'short'       => 'TEB',
                'color'       => '#005DA8',
                'beneficiary' => 'Helppy SH.P.K.',
                'iban'        => 'XK00 0000 0000 0000 0000',
                'swift'       => 'TEBKXKPR',
                'note'        => 'Përdor kodin e referencës si arsye e pagesës.',
                'gateway'     => 'manual',
                'api_key'     => '',
                'enabled'     => true,
            ],
            [
                'key'         => 'bkt',
                'name'        => 'BKT Kosova',
                'short'       => 'BKT',
                'color'       => '#003083',
                'beneficiary' => 'Helppy SH.P.K.',
                'iban'        => 'XK00 0000 0000 0000 0000',
                'swift'       => 'NCBAALTR',
                'note'        => 'Përdor kodin e referencës si arsye e pagesës.',
                'gateway'     => 'manual',
                'api_key'     => '',
                'enabled'     => true,
            ],
            [
                'key'         => 'procredit',
                'name'        => 'ProCredit Bank Kosovo',
                'short'       => 'ProCredit',
                'color'       => '#E2001A',
                'beneficiary' => 'Helppy SH.P.K.',
                'iban'        => 'XK00 0000 0000 0000 0000',
                'swift'       => 'MBKOXKPR',
                'note'        => 'Përdor kodin e referencës si arsye e pagesës.',
                'gateway'     => 'manual',
                'api_key'     => '',
                'enabled'     => true,
            ],
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
