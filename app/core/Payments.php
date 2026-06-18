<?php
declare(strict_types=1);

/**
 * Reads CONFIG['payments'] and exposes a sane shape for the rest of the app.
 *
 * - Banks() returns only enabled entries.
 * - findBank($key) lets the controller look one up from a POSTed key.
 * - Card flow is gated by Stripe::isConfigured().
 *
 * Add or remove banks by editing CONFIG['payments']['banks']. The UI updates
 * automatically; no controller change required.
 */
final class Payments {

    /** All banks where 'enabled' is true. Preserves order from config. */
    public static function banks(): array {
        $all = (array)(CONFIG['payments']['banks'] ?? []);
        return array_values(array_filter($all, fn ($b) => !empty($b['enabled'])));
    }

    /** Find a bank entry by 'key' (e.g. 'raiffeisen'), or null. */
    public static function findBank(string $key): ?array {
        foreach (self::banks() as $b) {
            if (($b['key'] ?? '') === $key) return $b;
        }
        return null;
    }

    public static function defaultBankKey(): ?string {
        $banks = self::banks();
        return $banks ? (string)$banks[0]['key'] : null;
    }
}
