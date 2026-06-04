<?php
declare(strict_types=1);

abstract class Controller {
    protected function render(string $template, array $data = []): void {
        $data['__flash'] = self::pullFlash();
        View::render($template, $data);
    }

    protected function redirect(string $path): void {
        header('Location: ' . CONFIG['base_url'] . $path);
        exit;
    }

    protected function flash(string $type, string $msg): void {
        $_SESSION['_flash'][] = ['type' => $type, 'msg' => $msg];
    }

    public static function pullFlash(): array {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }

    protected function notFound(): void {
        http_response_code(404);
        View::render('errors/404', []);
        exit;
    }
}
