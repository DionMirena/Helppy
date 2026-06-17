<?php
declare(strict_types=1);

final class PostPhoto {
    /** All photos for a post, ordered. */
    public static function forPost(int $postId): array {
        return DB::q(
            'SELECT id, post_id, filename, sort_order FROM post_photos WHERE post_id = ? ORDER BY sort_order ASC, id ASC',
            [$postId]
        )->fetchAll();
    }

    /** First photo for a post, or null. Used for card thumbnails. */
    public static function firstForPost(int $postId): ?array {
        $r = DB::q(
            'SELECT id, filename FROM post_photos WHERE post_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1',
            [$postId]
        )->fetch();
        return $r ?: null;
    }

    /** Insert one photo row. Returns the new id. */
    public static function add(int $postId, string $filename, int $sortOrder): int {
        DB::q(
            'INSERT INTO post_photos (post_id, filename, sort_order) VALUES (?, ?, ?)',
            [$postId, $filename, $sortOrder]
        );
        return (int)DB::pdo()->lastInsertId();
    }

    /** Delete one photo row by id; returns the filename so the caller can unlink. */
    public static function removeOne(int $photoId, int $postId): ?string {
        $r = DB::q('SELECT filename FROM post_photos WHERE id = ? AND post_id = ?', [$photoId, $postId])->fetch();
        if (!$r) return null;
        DB::q('DELETE FROM post_photos WHERE id = ?', [$photoId]);
        return $r['filename'];
    }

    /** Delete all photo rows for a post; returns the list of filenames so the caller can unlink. */
    public static function removeAllForPost(int $postId): array {
        $names = array_column(
            DB::q('SELECT filename FROM post_photos WHERE post_id = ?', [$postId])->fetchAll(),
            'filename'
        );
        DB::q('DELETE FROM post_photos WHERE post_id = ?', [$postId]);
        return $names;
    }
}
