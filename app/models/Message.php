<?php
declare(strict_types=1);

final class Message {
    /** All messages in a conversation, oldest first. */
    public static function forConversation(int $convId, int $limit = 200): array {
        $limit = max(1, min(500, $limit));
        return DB::q(
            "SELECT id, conversation_id, sender_id, body, read_at, created_at
             FROM messages WHERE conversation_id = ?
             ORDER BY id ASC LIMIT $limit",
            [$convId]
        )->fetchAll();
    }

    /** Messages after $afterId (for AJAX polling). */
    public static function afterId(int $convId, int $afterId, int $limit = 100): array {
        $limit = max(1, min(200, $limit));
        return DB::q(
            "SELECT id, conversation_id, sender_id, body, read_at, created_at
             FROM messages WHERE conversation_id = ? AND id > ?
             ORDER BY id ASC LIMIT $limit",
            [$convId, $afterId]
        )->fetchAll();
    }

    public static function send(int $convId, int $senderId, string $body): int {
        DB::q(
            'INSERT INTO messages (conversation_id, sender_id, body) VALUES (?,?,?)',
            [$convId, $senderId, $body]
        );
        $id = (int)DB::pdo()->lastInsertId();
        Conversation::touch($convId);
        return $id;
    }

    /** Mark all messages in $convId not sent by $viewerId as read. */
    public static function markReadFor(int $convId, int $viewerId): void {
        DB::q(
            'UPDATE messages SET read_at = CURRENT_TIMESTAMP
             WHERE conversation_id = ? AND sender_id <> ? AND read_at IS NULL',
            [$convId, $viewerId]
        );
    }
}
