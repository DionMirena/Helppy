<?php
declare(strict_types=1);

final class Verification {
    /** Generates a fresh 6-digit code, resets attempts=0, expires_at=NOW+15min, last_sent_at=NOW. */
    public static function generateCodeFor(int $userId): string {
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::q(
            'UPDATE users SET verification_code=?, verification_expires_at=DATE_ADD(NOW(), INTERVAL 15 MINUTE), verification_attempts=0, verification_last_sent_at=NOW() WHERE id=?',
            [$code, $userId]
        );
        return $code;
    }

    /**
     * Sends the current verification code via Mailer.
     *
     * Behavior on failure:
     *   - production (debug=false): rethrows so the caller can log/redirect.
     *   - dev (debug=true): swallows the failure, logs the attempt to
     *     storage/mail.log, and stashes the code in $_SESSION['_dev_code']
     *     so the verify-email view can surface it. This lets you develop
     *     without configured SMTP credentials.
     */
    public static function send(int $userId): void {
        $row = DB::q('SELECT name, email, verification_code FROM users WHERE id=?', [$userId])->fetch();
        if (!$row || $row['verification_code'] === null) {
            throw new RuntimeException("No active code for user $userId");
        }
        $subject = 'Helppy.com — kodi i verifikimit';
        $body = strtr(
            "Pershendetje {NAME},\n\n" .
            "Kodi juaj i verifikimit per Helppy.com eshte:\n\n" .
            "  {CODE}\n\n" .
            "Ky kod vlen 15 minuta. Nese nuk e keni kerkuar ju, injorojeni kete email.\n\n" .
            "Faleminderit,\n" .
            "Ekipi Helppy.com\n",
            ['{NAME}' => $row['name'], '{CODE}' => $row['verification_code']]
        );

        try {
            Mailer::send($row['email'], $subject, $body);
            // Production / successful send: do NOT leak the code into session.
            unset($_SESSION['_dev_code']);
            return;
        } catch (Throwable $e) {
            // Re-throw in prod so the caller can show a real error.
            if (empty(CONFIG['debug'])) {
                throw $e;
            }

            // DEV fallback: code didn't go out but we know it. Make it usable.
            $stamp = date('c');
            $logLine = "{$stamp} [DEV-FALLBACK] to={$row['email']} code={$row['verification_code']} reason=" . $e->getMessage() . "\n";
            $logDir  = APP_ROOT . '/storage';
            if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
            @file_put_contents($logDir . '/mail.log', $logLine, FILE_APPEND);

            // Stash the code so verify.php can surface it as a dev banner.
            $_SESSION['_dev_code'] = [
                'code'  => (string)$row['verification_code'],
                'email' => (string)$row['email'],
                'when'  => time(),
            ];
        }
    }

    /** TRUE on match (clears columns), FALSE otherwise. 5 wrong attempts nulls the code. */
    public static function verify(int $userId, string $code): bool {
        $row = DB::q(
            'SELECT verification_code, verification_attempts, (verification_expires_at < NOW()) AS expired FROM users WHERE id=?',
            [$userId]
        )->fetch();
        if (!$row || $row['verification_code'] === null) return false;
        if ((int)$row['expired'] === 1) return false;

        if (hash_equals((string)$row['verification_code'], $code)) {
            DB::q(
                'UPDATE users SET verification_code=NULL, verification_expires_at=NULL, verification_attempts=0, verification_last_sent_at=NULL WHERE id=?',
                [$userId]
            );
            return true;
        }

        $newAttempts = (int)$row['verification_attempts'] + 1;
        if ($newAttempts >= 5) {
            DB::q('UPDATE users SET verification_code=NULL, verification_attempts=? WHERE id=?', [$newAttempts, $userId]);
        } else {
            DB::q('UPDATE users SET verification_attempts=? WHERE id=?', [$newAttempts, $userId]);
        }
        return false;
    }

    public static function canResend(int $userId): bool {
        return self::secondsUntilResend($userId) === 0;
    }

    public static function secondsUntilResend(int $userId): int {
        $elapsed = DB::q(
            'SELECT TIMESTAMPDIFF(SECOND, verification_last_sent_at, NOW()) FROM users WHERE id=? AND verification_last_sent_at IS NOT NULL',
            [$userId]
        )->fetchColumn();
        if ($elapsed === false || $elapsed === null) return 0;
        $elapsed = (int)$elapsed;
        return $elapsed >= 60 ? 0 : 60 - $elapsed;
    }

    public static function isEmailVerified(int $userId): bool {
        return (int)DB::q('SELECT email_verified FROM users WHERE id=?', [$userId])->fetchColumn() === 1;
    }
}
