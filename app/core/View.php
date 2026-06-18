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

/** Bootstrap-icon glyph for a notification type. Falls back to a bell. */
function notificationIcon(string $type): string {
    if (str_starts_with($type, 'booking.')) return 'bi-calendar-event';
    if (str_starts_with($type, 'message.')) return 'bi-chat-dots';
    return 'bi-bell';
}

/** Albanian relative time helper: "tani", "para X minutash/orësh/ditësh". */
function timeAgoSq(string $dt): string {
    $t = strtotime($dt);
    if ($t === false) return '';
    $diff = time() - $t;
    if ($diff < 60)         return 'tani';
    if ($diff < 3600)       { $n = (int)floor($diff / 60);    return "para $n " . ($n === 1 ? 'minute' : 'minutash'); }
    if ($diff < 86400)      { $n = (int)floor($diff / 3600);  return "para $n " . ($n === 1 ? 'ore'    : 'orësh'); }
    if ($diff < 30 * 86400) { $n = (int)floor($diff / 86400); return "para $n " . ($n === 1 ? 'dite'   : 'ditësh'); }
    return date('d M Y', $t);
}
