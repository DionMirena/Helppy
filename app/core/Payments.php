<?php
declare(strict_types=1);

/**
 * Reads CONFIG['payments'] and exposes a sane shape for the rest of the app.
 *
 * Every bank-transfer payment lands in the SAME admin account
 * (CONFIG['payments']['admin']). Per-bank entries are just display labels
 * the provider picks; the destination IBAN/beneficiary is always merged
 * from the admin block unless the bank entry explicitly overrides.
 */
final class Payments {

    /** The single admin destination — where every bank transfer lands. */
    public static function admin(): array {
        $a = (array)(CONFIG['payments']['admin'] ?? []);
        return [
            'beneficiary' => (string)($a['beneficiary'] ?? ''),
            'iban'        => (string)($a['iban'] ?? ''),
            'bank_name'   => (string)($a['bank_name'] ?? ''),
            'swift'       => (string)($a['swift'] ?? ''),
            'note'        => (string)($a['note'] ?? 'Përdor kodin e referencës si arsye e pagesës.'),
        ];
    }

    /** True when the admin IBAN looks plausibly filled in. */
    public static function adminConfigured(): bool {
        $iban = preg_replace('/\s+/', '', self::admin()['iban']);
        return is_string($iban) && strlen($iban) >= 15 && !str_starts_with($iban, 'XK0000');
    }

    /**
     * All enabled banks. Each entry has the admin destination merged in
     * (IBAN, beneficiary, swift, note) so the view doesn't need to know
     * about the admin block.
     */
    public static function banks(): array {
        $admin = self::admin();
        $raw   = (array)(CONFIG['payments']['banks'] ?? []);
        $out = [];
        foreach ($raw as $b) {
            if (empty($b['enabled'])) continue;
            $out[] = self::mergeAdmin($b, $admin);
        }
        return $out;
    }

    /** Find a bank entry by 'key' (e.g. 'raiffeisen'), or null. Admin merged. */
    public static function findBank(string $key): ?array {
        $admin = self::admin();
        foreach ((array)(CONFIG['payments']['banks'] ?? []) as $b) {
            if (empty($b['enabled'])) continue;
            if (($b['key'] ?? '') === $key) return self::mergeAdmin($b, $admin);
        }
        return null;
    }

    public static function defaultBankKey(): ?string {
        $banks = self::banks();
        return $banks ? (string)$banks[0]['key'] : null;
    }

    /** Per-bank overrides win; otherwise fall back to the admin destination. */
    private static function mergeAdmin(array $bank, array $admin): array {
        return $bank + [
            'beneficiary' => $admin['beneficiary'],
            'iban'        => $admin['iban'],
            'swift'       => $bank['swift'] ?? $admin['swift'],
            'note'        => $admin['note'],
            'api_key'     => '',
            'gateway'     => 'manual',
        ];
    }
}
