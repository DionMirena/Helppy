<?php
declare(strict_types=1);

final class View {
    /** Render a view inside layout.php. $template like 'home/index'. */
    public static function render(string $template, array $data = [], string $layout = 'layout'): void {
        $viewFile = APP_ROOT . "/app/views/{$template}.php";
        if (!is_file($viewFile)) {
            throw new RuntimeException("View not found: $template");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        require APP_ROOT . "/app/views/{$layout}.php";
    }

    /** Render a partial directly with shared data (no layout). */
    public static function partial(string $name, array $data = []): void {
        extract($data, EXTR_SKIP);
        require APP_ROOT . "/app/views/partials/{$name}.php";
    }
}

/** Escape for HTML output. Used in every view. */
function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}
