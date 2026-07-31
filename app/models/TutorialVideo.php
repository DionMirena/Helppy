<?php
declare(strict_types=1);

final class TutorialVideo {

    public static function all(): array {
        return DB::q('SELECT * FROM tutorial_videos ORDER BY id')->fetchAll();
    }

    public static function bySlot(string $slot): ?array {
        $r = DB::q('SELECT * FROM tutorial_videos WHERE slot = ?', [$slot])->fetch();
        return $r ?: null;
    }

    public static function allIndexed(): array {
        $rows = self::all();
        $out  = [];
        foreach ($rows as $r) { $out[$r['slot']] = $r; }
        return $out;
    }

    public static function setUrl(string $slot, string $url): void {
        DB::q('UPDATE tutorial_videos SET video_url = ? WHERE slot = ?', [trim($url), $slot]);
    }

    /** Convert any YouTube / Vimeo watch URL to an embed URL. Returns as-is if already embed or unknown. */
    public static function toEmbed(string $url): string {
        $url = trim($url);
        if ($url === '') return '';

        // YouTube: watch?v=ID  or  youtu.be/ID  or  already /embed/
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_\-]{11})/', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1] . '?rel=0&modestbranding=1';
        }
        if (str_contains($url, 'youtube.com/embed/')) return $url;

        // Vimeo: vimeo.com/ID
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }

        return $url; // mp4 or other direct link
    }

    /** True if the URL is a direct video file (not an embed iframe). */
    public static function isDirect(string $url): bool {
        return (bool) preg_match('/\.(mp4|webm|ogg)(\?.*)?$/i', $url);
    }
}
