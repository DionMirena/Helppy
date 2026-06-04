<?php
declare(strict_types=1);

final class Auth {
    public static function login(array $user): void {
        session_regenerate_id(true);
        $_SESSION['uid']  = (int)$user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
    }

    public static function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool {
        return !empty($_SESSION['uid']);
    }

    public static function user(): ?array {
        if (!self::check()) return null;
        static $cached = null;
        if ($cached === null) {
            $st = DB::q('SELECT id,name,email,phone,role,city_id,is_active FROM users WHERE id=?',
                       [$_SESSION['uid']]);
            $cached = $st->fetch() ?: null;
        }
        return $cached;
    }

    public static function role(): ?string {
        return $_SESSION['role'] ?? null;
    }

    public static function pendingUid(): ?int {
        return isset($_SESSION['pending_2fa_uid']) ? (int)$_SESSION['pending_2fa_uid'] : null;
    }

    public static function setPending(int $uid): void {
        session_regenerate_id(true);
        $_SESSION['pending_2fa_uid'] = $uid;
    }

    public static function clearPending(): void {
        unset($_SESSION['pending_2fa_uid']);
    }

    /** Require login + optional role. Sends 403/redirect and exits if not allowed. */
    public static function require(?string $role = null): void {
        if (!self::check()) {
            header('Location: ' . CONFIG['base_url'] . '/login');
            exit;
        }
        if ($role !== null && self::role() !== $role) {
            http_response_code(403);
            View::render('errors/403', []);
            exit;
        }
    }
}
