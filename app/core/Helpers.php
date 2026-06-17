<?php
declare(strict_types=1);

/**
 * Cross-cutting helpers that don't fit any single model/controller.
 * Static methods only; matches the codebase pattern.
 */
final class Helpers {
    /**
     * Send a transactional email but never throw on failure.
     * Used by booking/notification flows where a failed SMTP call
     * must NOT block the primary action (the in-app notification
     * is the source of truth).
     *
     * Returns true on success, false on failure. Logs failures via
     * PHP error_log; never propagates exceptions.
     */
    public static function sendEmailSafe(string $to, string $subject, string $body): bool {
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $cfg = CONFIG['mailer'] ?? [];
        if (empty($cfg['username']) || empty($cfg['password'])) {
            // No SMTP configured — silently skip in dev.
            error_log("[Helpers] Mailer not configured; skipped email to {$to}: {$subject}");
            return false;
        }
        try {
            return Mailer::send($to, $subject, $body);
        } catch (Throwable $e) {
            error_log("[Helpers] Email to {$to} failed: " . $e->getMessage());
            return false;
        }
    }
}
