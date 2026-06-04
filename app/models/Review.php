<?php
declare(strict_types=1);

final class Review {
    public static function find(int $id): ?array {
        $r = DB::q('SELECT * FROM reviews WHERE id=?', [$id])->fetch();
        return $r ?: null;
    }

    public static function forProvider(int $providerId): array {
        return DB::q(
            "SELECT r.id, r.rating, r.comment, r.created_at, r.client_id,
                    u.name AS client_name
             FROM reviews r
             JOIN users u ON u.id = r.client_id
             WHERE r.provider_id = ?
             ORDER BY r.created_at DESC",
            [$providerId]
        )->fetchAll();
    }

    public static function byClient(int $clientId): array {
        return DB::q(
            "SELECT r.id, r.rating, r.comment, r.created_at, r.provider_id,
                    u.name AS provider_name
             FROM reviews r
             JOIN users u ON u.id = r.provider_id
             WHERE r.client_id = ?
             ORDER BY r.created_at DESC",
            [$clientId]
        )->fetchAll();
    }

    public static function existsFor(int $providerId, int $clientId): bool {
        return (bool)DB::q('SELECT 1 FROM reviews WHERE provider_id=? AND client_id=? LIMIT 1',
                           [$providerId, $clientId])->fetch();
    }

    public static function create(int $providerId, int $clientId, int $rating, ?string $comment): void {
        DB::q('INSERT INTO reviews (provider_id, client_id, rating, comment) VALUES (?, ?, ?, ?)',
              [$providerId, $clientId, $rating, $comment]);
    }

    public static function delete(int $id): void {
        DB::q('DELETE FROM reviews WHERE id=?', [$id]);
    }
}
