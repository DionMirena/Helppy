<?php
declare(strict_types=1);

final class Notification {
    /** Recent notifications for a user. */
    public static function forUser(int $userId, int $limit = 50): array {
        $limit = max(1, min(200, $limit));
        return DB::q(
            "SELECT id, type, title, body, link, read_at, created_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY created_at DESC LIMIT $limit",
            [$userId]
        )->fetchAll();
    }

    public static function unreadCount(int $userId): int {
        return (int)DB::q(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL',
            [$userId]
        )->fetchColumn();
    }

    public static function create(int $userId, string $type, string $title, ?string $body = null, ?string $link = null): int {
        DB::q(
            'INSERT INTO notifications (user_id, type, title, body, link) VALUES (?,?,?,?,?)',
            [$userId, $type, $title, $body, $link]
        );
        return (int)DB::pdo()->lastInsertId();
    }

    public static function markRead(int $id, int $userId): void {
        DB::q(
            'UPDATE notifications SET read_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ? AND read_at IS NULL',
            [$id, $userId]
        );
    }

    public static function markAllRead(int $userId): void {
        DB::q(
            'UPDATE notifications SET read_at = CURRENT_TIMESTAMP WHERE user_id = ? AND read_at IS NULL',
            [$userId]
        );
    }
}
