<?php
declare(strict_types=1);

/**
 * Thin Stripe REST client — no Composer SDK.
 *
 * Only implements the two endpoints we actually use:
 *   - POST /v1/checkout/sessions  (create a Checkout session)
 *   - GET  /v1/checkout/sessions/{id}  (verify a session on return)
 *
 * Webhook signature verification is also here (HMAC-SHA256 of the raw body).
 *
 * All methods throw RuntimeException on error so the caller can surface it.
 */
final class Stripe {
    private const API_BASE = 'https://api.stripe.com';

    public static function isConfigured(): bool {
        $s = CONFIG['payments']['stripe'] ?? [];
        if (empty($s['enabled'])) return false;
        $k = $s['secret_key'] ?? '';
        return is_string($k) && str_starts_with($k, 'sk_');
    }

    public static function secretKey(): string {
        return (string)(CONFIG['payments']['stripe']['secret_key'] ?? '');
    }

    public static function webhookSecret(): string {
        return (string)(CONFIG['payments']['stripe']['webhook_secret'] ?? '');
    }

    /**
     * Create a one-time Checkout session for a subscription activation.
     * Returns ['id' => ..., 'url' => ...] (url is the hosted page to redirect to).
     */
    public static function createCheckoutSession(string $tier, float $amountEur, string $customerEmail, string $successUrl, string $cancelUrl, array $metadata = []): array {
        if (!self::isConfigured()) {
            throw new RuntimeException('Stripe not configured (config/config.php → stripe.secret_key).');
        }

        $params = [
            'mode'                       => 'payment',
            'payment_method_types[]'     => 'card',
            'customer_email'             => $customerEmail,
            'success_url'                => $successUrl,
            'cancel_url'                 => $cancelUrl,
            'line_items[0][quantity]'    => 1,
            'line_items[0][price_data][currency]'     => 'eur',
            'line_items[0][price_data][unit_amount]'  => (int)round($amountEur * 100),
            'line_items[0][price_data][product_data][name]' => 'Helppy — ' . ucfirst($tier) . ' (30 dite)',
        ];
        foreach ($metadata as $k => $v) {
            $params['metadata[' . $k . ']'] = (string)$v;
        }

        return self::call('POST', '/v1/checkout/sessions', $params);
    }

    /** Retrieve a Checkout session by id. */
    public static function retrieveCheckoutSession(string $sessionId): array {
        return self::call('GET', '/v1/checkout/sessions/' . urlencode($sessionId), []);
    }

    /**
     * Verify a Stripe webhook signature.
     * $rawBody is the raw POST body.
     * $sigHeader is the Stripe-Signature header.
     * $secret is the webhook signing secret (whsec_...).
     * Throws on mismatch.
     */
    public static function verifyWebhook(string $rawBody, string $sigHeader, string $secret): void {
        if ($secret === '') throw new RuntimeException('No webhook secret configured.');
        $parts = [];
        foreach (explode(',', $sigHeader) as $segment) {
            [$k, $v] = array_pad(explode('=', trim($segment), 2), 2, null);
            if ($k === null || $v === null) continue;
            $parts[$k][] = $v;
        }
        $t  = $parts['t'][0] ?? null;
        $sigs = $parts['v1'] ?? [];
        if ($t === null || !$sigs) throw new RuntimeException('Malformed Stripe-Signature header.');
        // Reject events older than 5 minutes (replay window).
        if (abs(time() - (int)$t) > 300) throw new RuntimeException('Stripe signature timestamp out of tolerance.');

        $expected = hash_hmac('sha256', $t . '.' . $rawBody, $secret);
        foreach ($sigs as $sig) {
            if (hash_equals($expected, $sig)) return;
        }
        throw new RuntimeException('Stripe signature mismatch.');
    }

    /** Internal: make an HTTP call to Stripe with the secret key. */
    private static function call(string $method, string $path, array $params): array {
        $secret = self::secretKey();
        $url = self::API_BASE . $path;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_USERPWD        => $secret . ':',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Stripe-Version: 2024-04-10',
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 20,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false) {
            throw new RuntimeException("Stripe API call failed: $err");
        }
        $json = json_decode((string)$resp, true);
        if (!is_array($json)) {
            throw new RuntimeException("Stripe returned non-JSON (HTTP $code): " . substr((string)$resp, 0, 200));
        }
        if ($code >= 400) {
            $msg = $json['error']['message'] ?? 'unknown error';
            throw new RuntimeException("Stripe HTTP $code: $msg");
        }
        return $json;
    }
}
