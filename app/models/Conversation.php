<?php
declare(strict_types=1);

final class Conversation {
    /** Normalize a user pair so (a,b) with a < b is the canonical form. */
    private static function pair(int $u1, int $u2): array {
        return $u1 < $u2 ? [$u1, $u2] : [$u2, $u1];
    }

    /** Find or create the conversation for the unordered pair (u1, u2). */
    public static function findOrCreate(int $u1, int $u2): int {
        [$a, $b] = self::pair($u1, $u2);
        $r = DB::q(
            'SELECT id FROM conversations WHERE user_a_id = ? AND user_b_id = ?',
            [$a, $b]
        )->fetch();
        if ($r) return (int)$r['id'];
        DB::q('INSERT INTO conversations (user_a_id, user_b_id) VALUES (?, ?)', [$a, $b]);
        return (int)DB::pdo()->lastInsertId();
    }

    /** Find by id, with both participants' names. */
    public static function find(int $id): ?array {
        $sql = "SELECT c.id, c.user_a_id, c.user_b_id, c.last_message_at, c.created_at,
                       ua.name AS user_a_name, ub.name AS user_b_name
                FROM conversations c
                JOIN users ua ON ua.id = c.user_a_id
                JOIN users ub ON ub.id = c.user_b_id
                WHERE c.id = ?";
        $r = DB::q($sql, [$id])->fetch();
        return $r ?: null;
    }

    /** All conversations a user is part of, newest message first, with the OTHER party's info. */
    public static function forUser(int $userId, int $limit = 50): array {
        $limit = max(1, min(200, $limit));
        $sql = "SELECT c.id, c.last_message_at,
                       CASE WHEN c.user_a_id = ? THEN c.user_b_id ELSE c.user_a_id END AS other_id,
                       CASE WHEN c.user_a_id = ? THEN ub.name ELSE ua.name END AS other_name,
                       CASE WHEN c.user_a_id = ? THEN ub.role ELSE ua.role END AS other_role,
                       (SELECT body FROM messages WHERE conversation_id = c.id ORDER BY id DESC LIMIT 1) AS last_body,
                       (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_id <> ? AND read_at IS NULL) AS unread_count
                FROM conversations c
                JOIN users ua ON ua.id = c.user_a_id
                JOIN users ub ON ub.id = c.user_b_id
                WHERE c.user_a_id = ? OR c.user_b_id = ?
                ORDER BY COALESCE(c.last_message_at, c.created_at) DESC
                LIMIT $limit";
        return DB::q($sql, [$userId, $userId, $userId, $userId, $userId, $userId])->fetchAll();
    }

    /** True if $userId is one of the participants in conversation $id. */
    public static function userIn(int $convId, int $userId): bool {
        $r = DB::q(
            'SELECT 1 FROM conversations WHERE id = ? AND (user_a_id = ? OR user_b_id = ?)',
            [$convId, $userId, $userId]
        )->fetch();
        return (bool)$r;
    }

    /** The other participant's user id for a given conversation. */
    public static function otherUserId(array $conv, int $viewerId): int {
        return (int)$conv['user_a_id'] === $viewerId ? (int)$conv['user_b_id'] : (int)$conv['user_a_id'];
    }

    public static function touch(int $id): void {
        DB::q('UPDATE conversations SET last_message_at = CURRENT_TIMESTAMP WHERE id = ?', [$id]);
    }

    public static function totalUnreadForUser(int $userId): int {
        return (int)DB::q(
            "SELECT COUNT(*) FROM messages m
             JOIN conversations c ON c.id = m.conversation_id
             WHERE m.sender_id <> ? AND m.read_at IS NULL
               AND (c.user_a_id = ? OR c.user_b_id = ?)",
            [$userId, $userId, $userId]
        )->fetchColumn();
    }
}
