<?php
declare(strict_types=1);

final class ProviderPhoto {
    /** All work photos for a provider, oldest first. */
    public static function forProvider(int $providerId, int $limit = 60): array {
        $limit = max(1, min(200, $limit));
        return DB::q(
            "SELECT id, provider_id, filename, caption, sort_order, created_at
             FROM provider_photos
             WHERE provider_id = ?
             ORDER BY sort_order ASC, id ASC
             LIMIT $limit",
            [$providerId]
        )->fetchAll();
    }

    public static function count(int $providerId): int {
        return (int)DB::q(
            "SELECT COUNT(*) FROM provider_photos WHERE provider_id = ?",
            [$providerId]
        )->fetchColumn();
    }

    /** Insert one photo. Returns new id. */
    public static function add(int $providerId, string $filename, ?string $caption = null): int {
        $next = (int)DB::q(
            "SELECT COALESCE(MAX(sort_order), 0) + 1 FROM provider_photos WHERE provider_id = ?",
            [$providerId]
        )->fetchColumn();
        DB::q(
            "INSERT INTO provider_photos (provider_id, filename, caption, sort_order) VALUES (?, ?, ?, ?)",
            [$providerId, $filename, $caption, $next]
        );
        return (int)DB::pdo()->lastInsertId();
    }

    /** Delete one photo if it belongs to $providerId. Returns the filename to unlink. */
    public static function deleteOne(int $id, int $providerId): ?string {
        $r = DB::q(
            "SELECT filename FROM provider_photos WHERE id = ? AND provider_id = ?",
            [$id, $providerId]
        )->fetch();
        if (!$r) return null;
        DB::q("DELETE FROM provider_photos WHERE id = ?", [$id]);
        return (string)$r['filename'];
    }

    /** Admin: delete one photo regardless of owner. */
    public static function adminDelete(int $id): ?string {
        $r = DB::q("SELECT filename FROM provider_photos WHERE id = ?", [$id])->fetch();
        if (!$r) return null;
        DB::q("DELETE FROM provider_photos WHERE id = ?", [$id]);
        return (string)$r['filename'];
    }
}
