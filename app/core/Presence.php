<?php
declare(strict_types=1);

/**
 * Online/offline presence tracking based on users.last_seen_at.
 *
 * Touch() is called once per authenticated request from public/index.php.
 * Anyone whose last_seen_at is within THRESHOLD_SECONDS is considered "online".
 */
final class Presence {

    /** A user is "online" if last_seen_at is within this many seconds of NOW. */
    public const THRESHOLD_SECONDS = 300; // 5 minutes

    private static bool $touchedThisRequest = false;

    /**
     * Update the user's last_seen_at to NOW. Throttled to once per request
     * so multiple Auth::user() calls don't repeatedly hit the DB.
     */
    public static function touch(int $uid): void {
        if (self::$touchedThisRequest || $uid <= 0) return;
        try {
            DB::q('UPDATE users SET last_seen_at = NOW() WHERE id = ?', [$uid]);
            self::$touchedThisRequest = true;
        } catch (Throwable $e) {
            error_log('[Presence::touch] ' . $e->getMessage());
        }
    }

    /** True if $lastSeenAt (DB datetime string) is within the threshold. */
    public static function isOnline(?string $lastSeenAt): bool {
        if (!$lastSeenAt) return false;
        $ts = strtotime((string)$lastSeenAt);
        if ($ts === false) return false;
        return (time() - $ts) <= self::THRESHOLD_SECONDS;
    }

    /**
     * Human-friendly relative time ("para 3 minutash", "para 2 orësh", "dje", "para 5 ditësh").
     * Returns null if $lastSeenAt is null.
     */
    public static function lastSeenLabel(?string $lastSeenAt): ?string {
        if (!$lastSeenAt) return null;
        $ts = strtotime((string)$lastSeenAt);
        if ($ts === false) return null;

        $diff = time() - $ts;
        if ($diff < 60)        return 'tani';
        if ($diff < 3600)      return 'para ' . (int)floor($diff / 60)   . ' minutash';
        if ($diff < 86400)     return 'para ' . (int)floor($diff / 3600) . ' orësh';
        if ($diff < 172800)    return 'dje';
        if ($diff < 30 * 86400) return 'para ' . (int)floor($diff / 86400) . ' ditësh';
        return date('d M Y', $ts);
    }
}
