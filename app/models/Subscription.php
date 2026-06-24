<?php
declare(strict_types=1);

final class Subscription {
    public const TIER_STANDARD = 'standard';
    public const TIER_PREMIUM  = 'premium';

    // Legacy constants kept for callers that still reference them.
    public const PRICE_STANDARD = 25.00;
    public const PRICE_PREMIUM  = 50.00;
    public const PERIOD_DAYS    = 30;

    /**
     * Plan catalog. Each plan binds a tier + duration + price together so the
     * UI and checkout flow can offer multiple period choices per tier.
     */
    public const PLANS = [
        'standard_1' => [
            'tier'        => self::TIER_STANDARD,
            'period_days' => 1,
            'price'       => 5.00,
            'name'        => 'Standard (1 ditë)',
            'perks'       => [
                'Krijo oferta të pakufizuara',
                'Renditja e zakonshme në kërkim',
                'Vlerësime nga klientë',
                '1 ditë qasje',
            ],
        ],
        'standard_30' => [
            'tier'        => self::TIER_STANDARD,
            'period_days' => 30,
            'price'       => self::PRICE_STANDARD,
            'name'        => 'Standard (30 ditë)',
            'perks'       => [
                'Krijo oferta të pakufizuara',
                'Renditja e zakonshme në kërkim',
                'Vlerësime nga klientë',
                '30 ditë qasje',
            ],
        ],
        'standard_365' => [
            'tier'        => self::TIER_STANDARD,
            'period_days' => 365,
            'price'       => 150.00,
            'name'        => 'Standard (1 vit)',
            'perks'       => [
                'Krijo oferta të pakufizuara',
                'Renditja e zakonshme në kërkim',
                'Vlerësime nga klientë',
                '365 ditë qasje',
            ],
        ],
        'premium_30' => [
            'tier'        => self::TIER_PREMIUM,
            'period_days' => 30,
            'price'       => self::PRICE_PREMIUM,
            'name'        => 'Premium (30 ditë)',
            'perks'       => [
                'Çdo gjë në Standard',
                'Rendit në krye të rezultateve',
                'Shenjë "PREMIUM" e dukshme',
                '30 ditë qasje',
            ],
        ],
        'premium_365' => [
            'tier'        => self::TIER_PREMIUM,
            'period_days' => 365,
            'price'       => 250.00,
            'name'        => 'Premium (1 vit) — Pa kufi',
            'perks'       => [
                'Çdo gjë në Premium',
                'Renditje në krye gjatë gjithë vitit',
                'Shenjë "PREMIUM" e dukshme',
                'Pa kufizime në oferta dhe dukshmëri',
                '365 ditë qasje',
            ],
        ],
    ];

    public static function planFor(string $key): ?array {
        return self::PLANS[$key] ?? null;
    }

    public static function isValidPlan(string $key): bool {
        return isset(self::PLANS[$key]);
    }

    public static function priceForPlan(string $key): float {
        return (float)(self::PLANS[$key]['price'] ?? self::PRICE_STANDARD);
    }

    public static function periodForPlan(string $key): int {
        return (int)(self::PLANS[$key]['period_days'] ?? self::PERIOD_DAYS);
    }

    public static function tierForPlan(string $key): string {
        return (string)(self::PLANS[$key]['tier'] ?? self::TIER_STANDARD);
    }

    /** Active row for a provider, or null. "Active" = status=active AND expires_at > NOW. */
    public static function activeFor(int $providerId): ?array {
        $r = DB::q(
            "SELECT * FROM subscriptions
             WHERE provider_id = ? AND status = 'active' AND expires_at > NOW()
             ORDER BY expires_at DESC LIMIT 1",
            [$providerId]
        )->fetch();
        return $r ?: null;
    }

    /** True if the provider can create offer posts right now. */
    public static function isActive(int $providerId): bool {
        return self::activeFor($providerId) !== null;
    }

    /** Latest row (any status) for a provider — used for dashboard "last attempt". */
    public static function latestFor(int $providerId): ?array {
        $r = DB::q(
            "SELECT * FROM subscriptions WHERE provider_id = ? ORDER BY id DESC LIMIT 1",
            [$providerId]
        )->fetch();
        return $r ?: null;
    }

    /** Pending subscriptions awaiting admin action (bank-transfer flow). */
    public static function pendingForAdmin(int $limit = 100): array {
        $limit = max(1, min(500, $limit));
        return DB::q(
            "SELECT s.*, u.name AS provider_name, u.email AS provider_email
             FROM subscriptions s
             JOIN users u ON u.id = s.provider_id
             WHERE s.status = 'pending'
             ORDER BY s.created_at DESC LIMIT $limit"
        )->fetchAll();
    }

    /** All subscriptions for admin overview. */
    public static function allForAdmin(int $limit = 200): array {
        $limit = max(1, min(500, $limit));
        return DB::q(
            "SELECT s.*, u.name AS provider_name, u.email AS provider_email
             FROM subscriptions s
             JOIN users u ON u.id = s.provider_id
             ORDER BY s.created_at DESC LIMIT $limit"
        )->fetchAll();
    }

    /** All subscriptions a given provider has ever had (history). */
    public static function historyFor(int $providerId, int $limit = 20): array {
        $limit = max(1, min(100, $limit));
        return DB::q(
            "SELECT * FROM subscriptions WHERE provider_id = ? ORDER BY id DESC LIMIT $limit",
            [$providerId]
        )->fetchAll();
    }

    public static function find(int $id): ?array {
        $r = DB::q("SELECT * FROM subscriptions WHERE id = ?", [$id])->fetch();
        return $r ?: null;
    }

    /** Legacy lookup — returns the 30-day price for the given tier. */
    public static function priceFor(string $tier): float {
        return $tier === self::TIER_PREMIUM ? self::PRICE_PREMIUM : self::PRICE_STANDARD;
    }

    /** Create a pending row for a given plan. Returns new id. */
    public static function createPending(int $providerId, string $planKey, string $method, ?string $bankRef = null, ?string $stripeSessionId = null): int {
        $plan   = self::planFor($planKey) ?? self::PLANS['standard_30'];
        $tier   = (string)$plan['tier'];
        $price  = (float)$plan['price'];
        $period = (int)$plan['period_days'];

        DB::q(
            "INSERT INTO subscriptions
               (provider_id, tier, plan, period_days, status, payment_method, amount_eur, bank_reference, stripe_session_id)
             VALUES (?,?,?,?,'pending',?,?,?,?)",
            [
                $providerId, $tier, $planKey, $period, $method, $price,
                $bankRef, $stripeSessionId,
            ]
        );
        return (int)DB::pdo()->lastInsertId();
    }

    /**
     * Mark active, set activated_at = NOW, expires_at = NOW + (period_days of the row).
     * Falls back to PERIOD_DAYS for legacy rows that pre-date the plan column.
     */
    public static function activate(int $id, ?string $stripePaymentIntent = null): void {
        $row    = self::find($id);
        $period = $row && (int)($row['period_days'] ?? 0) > 0
            ? (int)$row['period_days']
            : self::PERIOD_DAYS;

        $args = [];
        $set  = [
            "status = 'active'",
            "activated_at = NOW()",
            "expires_at = DATE_ADD(NOW(), INTERVAL $period DAY)",
        ];
        if ($stripePaymentIntent !== null) {
            $set[] = "stripe_payment_intent = ?";
            $args[] = $stripePaymentIntent;
        }
        $args[] = $id;
        DB::q("UPDATE subscriptions SET " . implode(', ', $set) . " WHERE id = ?", $args);

        // Mirror premium tier into providers.is_premium so search ranking picks it up.
        $row = self::find($id);
        if ($row && $row['tier'] === self::TIER_PREMIUM) {
            DB::q("UPDATE providers SET is_premium = 1 WHERE user_id = ?", [(int)$row['provider_id']]);
        }
    }

    public static function cancel(int $id): void {
        DB::q("UPDATE subscriptions SET status = 'cancelled' WHERE id = ?", [$id]);
    }

    public static function findByStripeSession(string $sessionId): ?array {
        $r = DB::q("SELECT * FROM subscriptions WHERE stripe_session_id = ? LIMIT 1", [$sessionId])->fetch();
        return $r ?: null;
    }

    public static function findByBankRef(string $ref): ?array {
        $r = DB::q("SELECT * FROM subscriptions WHERE bank_reference = ? LIMIT 1", [$ref])->fetch();
        return $r ?: null;
    }

    /** Generate a unique 10-char bank reference code (e.g. HLP-3A7Q9K). */
    public static function generateBankRef(int $providerId): string {
        for ($i = 0; $i < 5; $i++) {
            $ref = 'HLP-' . strtoupper(bin2hex(random_bytes(3)));
            if (!self::findByBankRef($ref)) return $ref;
        }
        // Fallback with provider id appended; extremely unlikely path
        return 'HLP-' . strtoupper(bin2hex(random_bytes(3))) . $providerId;
    }
}
