<?php
declare(strict_types=1);

final class Booking {
    public const STATUS_PENDING   = 'pending';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    /** Find a booking with joined client + provider info. */
    public static function find(int $id): ?array {
        $sql = "SELECT b.*,
                       c.name AS client_name, c.email AS client_email, c.phone AS client_phone,
                       pu.name AS provider_name, pu.email AS provider_email, pu.phone AS provider_phone,
                       pr.profession, pr.hourly_rate, pr.is_company, pr.company_name
                FROM bookings b
                JOIN users c   ON c.id  = b.client_id
                JOIN users pu  ON pu.id = b.provider_id
                LEFT JOIN providers pr ON pr.user_id = b.provider_id
                WHERE b.id = ?";
        $r = DB::q($sql, [$id])->fetch();
        return $r ?: null;
    }

    /** All bookings for a client (their own requests). */
    public static function forClient(int $clientId, int $limit = 50): array {
        $limit = max(1, min(200, $limit));
        $sql = "SELECT b.id, b.scheduled_at, b.duration_hours, b.status, b.created_at,
                       pu.name AS provider_name, pr.profession,
                       (SELECT photo FROM providers WHERE user_id = b.provider_id) AS provider_photo
                FROM bookings b
                JOIN users pu     ON pu.id = b.provider_id
                LEFT JOIN providers pr ON pr.user_id = b.provider_id
                WHERE b.client_id = ?
                ORDER BY b.created_at DESC LIMIT $limit";
        return DB::q($sql, [$clientId])->fetchAll();
    }

    /** All bookings addressed to a provider. */
    public static function forProvider(int $providerId, int $limit = 50): array {
        $limit = max(1, min(200, $limit));
        $sql = "SELECT b.id, b.scheduled_at, b.duration_hours, b.status, b.created_at,
                       c.name AS client_name, c.phone AS client_phone
                FROM bookings b
                JOIN users c ON c.id = b.client_id
                WHERE b.provider_id = ?
                ORDER BY b.created_at DESC LIMIT $limit";
        return DB::q($sql, [$providerId])->fetchAll();
    }

    public static function pendingCountForProvider(int $providerId): int {
        return (int)DB::q(
            "SELECT COUNT(*) FROM bookings WHERE provider_id = ? AND status = 'pending'",
            [$providerId]
        )->fetchColumn();
    }

    public static function create(int $clientId, int $providerId, string $scheduledAt, ?float $duration, ?string $notes): int {
        DB::q(
            "INSERT INTO bookings (client_id, provider_id, scheduled_at, duration_hours, notes, status)
             VALUES (?,?,?,?,?, 'pending')",
            [$clientId, $providerId, $scheduledAt, $duration, $notes]
        );
        return (int)DB::pdo()->lastInsertId();
    }

    public static function setStatus(int $id, string $status): void {
        $allowed = ['pending','accepted','rejected','completed','cancelled'];
        if (!in_array($status, $allowed, true)) return;
        DB::q('UPDATE bookings SET status = ? WHERE id = ?', [$status, $id]);
    }
}
