<?php
declare(strict_types=1);

final class Request {
    public static function method(): string {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }
    public static function isPost(): bool {
        return self::method() === 'POST';
    }
    public static function get(string $key, $default = null) {
        return $_GET[$key] ?? $default;
    }
    public static function post(string $key, $default = null) {
        return $_POST[$key] ?? $default;
    }
    public static function file(string $key): ?array {
        return isset($_FILES[$key]) && $_FILES[$key]['error'] !== UPLOAD_ERR_NO_FILE
            ? $_FILES[$key] : null;
    }

    /** Generate (once per session) and return the CSRF token. */
    public static function csrfToken(): string {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    /** Verify the _csrf POST field matches the session token. Dies on mismatch. */
    public static function verifyCsrf(): void {
        $supplied = $_POST['_csrf'] ?? '';
        $expected = $_SESSION['_csrf'] ?? '';
        if (!is_string($supplied) || !is_string($expected) || $expected === '' || !hash_equals($expected, $supplied)) {
            http_response_code(419);
            die('CSRF token mismatch');
        }
    }
}
