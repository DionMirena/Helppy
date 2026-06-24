<?php
declare(strict_types=1);

/**
 * Per-bank 3D Secure card gateway (Nestpay/Asseco-compatible by default).
 *
 * Every bank under CONFIG['payments']['banks'] carries its own gateway block
 * with merchant_id, store_key and api_url. The customer picks a bank on the
 * /subscribe page and is redirected to THAT bank's hosted 3DS page.
 *
 * Flow per bank:
 *   1. We POST a signed form to the bank's hosted page (api_url).
 *   2. The customer enters their card on the BANK'S page (we never see it).
 *   3. The bank POSTs the result back to our callback URL, with HMAC signature.
 *   4. We verify the signature using the bank's store_key and activate the sub.
 *
 * If a bank uses a non-Nestpay gateway, branch on $cfg['provider'] inside
 * buildAuthRequest()/verifyCallback() — controller, routes, and DB flow stay.
 */
final class BankGateway {

    /** Return the merged config for a bank key, or null. */
    public static function forBank(string $bankKey): ?array {
        foreach ((array)(CONFIG['payments']['banks'] ?? []) as $b) {
            if (($b['key'] ?? '') !== $bankKey) continue;
            if (empty($b['enabled'])) return null;
            return $b;
        }
        return null;
    }

    /** True iff this specific bank has a working gateway block. */
    public static function isConfigured(string $bankKey): bool {
        $b = self::forBank($bankKey);
        if (!$b) return false;
        $g = (array)($b['gateway'] ?? []);
        return !empty($g['enabled'])
            && !empty($g['api_url'])
            && !empty($g['merchant_id'])
            && !empty($g['store_key']);
    }

    /** True iff at least one bank gateway is enabled — used to show the picker. */
    public static function anyEnabled(): bool {
        foreach ((array)(CONFIG['payments']['banks'] ?? []) as $b) {
            if (empty($b['enabled'])) continue;
            $g = (array)($b['gateway'] ?? []);
            if (!empty($g['enabled']) && !empty($g['api_url']) && !empty($g['merchant_id']) && !empty($g['store_key'])) {
                return true;
            }
        }
        return false;
    }

    /** Banks whose gateway is fully configured — for the per-plan picker. */
    public static function enabledBanks(): array {
        $out = [];
        foreach ((array)(CONFIG['payments']['banks'] ?? []) as $b) {
            if (empty($b['enabled'])) continue;
            $g = (array)($b['gateway'] ?? []);
            if (empty($g['enabled']) || empty($g['api_url']) || empty($g['merchant_id']) || empty($g['store_key'])) continue;
            $out[] = $b;
        }
        return $out;
    }

    public static function apiUrl(string $bankKey): string {
        $b = self::forBank($bankKey);
        return (string)($b['gateway']['api_url'] ?? '');
    }

    /**
     * Build the form fields that get POSTed to the picked bank's 3DS page.
     * Returns an associative array of <name> => <value> the view auto-submits.
     */
    public static function buildAuthRequest(string $bankKey, string $orderId, float $amount, string $successUrl, string $failUrl, string $customerEmail = ''): array {
        $b = self::forBank($bankKey);
        if (!$b) throw new RuntimeException("Bank not enabled: $bankKey");
        $g = (array)$b['gateway'];

        $shared   = (array)(CONFIG['payments']['bank_gateway'] ?? []);
        $currency = self::currencyCode((string)($shared['currency'] ?? 'EUR'));

        $provider = (string)($g['provider'] ?? 'nestpay');
        if ($provider !== 'nestpay') {
            // Branch here when adding non-Nestpay gateways (e.g. Halcom, IPG, etc.).
            throw new RuntimeException("Unsupported gateway provider for $bankKey: $provider");
        }

        // Nestpay v3 fields. Names are case-sensitive on the bank's side.
        $fields = [
            'clientid'      => (string)$g['merchant_id'],
            'storetype'     => '3D_PAY_HOSTING',
            'TranType'      => (string)($shared['tran_type'] ?? 'Auth'),
            'amount'        => number_format($amount, 2, '.', ''),
            'currency'      => $currency,
            'oid'           => $orderId,
            'okUrl'         => $successUrl,
            'failUrl'       => $failUrl,
            'lang'          => (string)($shared['lang'] ?? 'sq'),
            'rnd'           => bin2hex(random_bytes(8)),
            'encoding'      => 'utf-8',
            'hashAlgorithm' => 'ver3',
        ];
        if ($customerEmail !== '') $fields['email'] = $customerEmail;

        $fields['HASH'] = self::computeHash($fields, (string)$g['store_key']);
        return $fields;
    }

    /**
     * Verify the bank's callback. Caller passes the bank key found from the
     * subscription row (sub.bank_chosen) so we use the right store_key.
     * Returns ['ok' => bool, 'oid' => '...', 'reason' => '...'].
     */
    public static function verifyCallback(string $bankKey, array $post): array {
        $b = self::forBank($bankKey);
        if (!$b) return ['ok' => false, 'oid' => (string)($post['oid'] ?? ''), 'reason' => 'unknown bank'];
        $g = (array)$b['gateway'];

        $expected = self::computeHash($post, (string)$g['store_key']);
        $sent     = (string)($post['HASH'] ?? '');

        if (!hash_equals($expected, $sent)) {
            return ['ok' => false, 'oid' => (string)($post['oid'] ?? ''), 'reason' => 'hash mismatch'];
        }

        $mdStatus = (string)($post['mdStatus']        ?? '');
        $resp     = (string)($post['Response']        ?? '');
        $procCode = (string)($post['ProcReturnCode']  ?? '');

        $authenticated = in_array($mdStatus, ['1', '2', '3', '4'], true);
        $approved      = ($resp === 'Approved') || ($procCode === '00');

        return [
            'ok'     => $authenticated && $approved,
            'oid'    => (string)($post['oid'] ?? ''),
            'reason' => $authenticated ? ($approved ? 'approved' : "declined ($procCode)") : "auth failed ($mdStatus)",
        ];
    }

    /**
     * Nestpay v3 hashing: take ALL incoming params except HASH, sort by key
     * (case-insensitive), pipe-join the values (with '|' escaped as '\|'),
     * append the store key, SHA-512, then base64.
     */
    private static function computeHash(array $params, string $storeKey): string {
        $copy = $params;
        unset($copy['HASH']);
        uksort($copy, 'strcasecmp');

        $escape = static function ($v): string {
            $s = (string)$v;
            $s = str_replace('\\', '\\\\', $s);
            $s = str_replace('|',  '\\|',  $s);
            return $s;
        };

        $plain = '';
        foreach ($copy as $v) {
            $plain .= $escape($v) . '|';
        }
        $plain .= $escape($storeKey);

        return base64_encode(hash('sha512', $plain, true));
    }

    /** Map an ISO 4217 alpha code to the numeric code the bank expects. */
    private static function currencyCode(string $alpha): string {
        $map = ['EUR' => '978', 'USD' => '840', 'ALL' => '008', 'GBP' => '826'];
        return $map[strtoupper($alpha)] ?? '978';
    }
}
