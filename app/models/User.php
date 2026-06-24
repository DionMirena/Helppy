<?php
declare(strict_types=1);

final class User {
    public static function find(int $id): ?array {
        $r = DB::q('SELECT * FROM users WHERE id=?', [$id])->fetch();
        return $r ?: null;
    }
    public static function findByEmail(string $email): ?array {
        $r = DB::q('SELECT * FROM users WHERE email=?', [$email])->fetch();
        return $r ?: null;
    }
    public static function emailExists(string $email): bool {
        return (bool)DB::q('SELECT 1 FROM users WHERE email=? LIMIT 1', [$email])->fetch();
    }
    public static function create(string $name, string $email, string $passwordHash, ?string $phone, string $role, ?int $cityId): int {
        $district = self::districtForCity($cityId);
        DB::q('INSERT INTO users (name, email, password_hash, phone, role, city_id, district) VALUES (?, ?, ?, ?, ?, ?, ?)',
              [$name, $email, $passwordHash, $phone, $role, $cityId, $district]);
        return (int)DB::pdo()->lastInsertId();
    }

    /** Look up the district name for a city id, or null. */
    public static function districtForCity(?int $cityId): ?string {
        if ($cityId === null) return null;
        $row = DB::q('SELECT name FROM cities WHERE id = ?', [$cityId])->fetch();
        if (!$row) return null;
        return Geography::districtOfCityName((string)$row['name']);
    }
    public static function toggleActive(int $id): void {
        DB::q('UPDATE users SET is_active = 1 - is_active WHERE id=?', [$id]);
    }
    public static function counts(): array {
        return [
            'users'     => (int)DB::q('SELECT COUNT(*) FROM users')->fetchColumn(),
            'providers' => (int)DB::q('SELECT COUNT(*) FROM users WHERE role="provider"')->fetchColumn(),
            'clients'   => (int)DB::q('SELECT COUNT(*) FROM users WHERE role="client"')->fetchColumn(),
            'reviews'   => (int)DB::q('SELECT COUNT(*) FROM reviews')->fetchColumn(),
        ];
    }

    /** All users with joined city + provider info for the admin users table. */
    public static function allForAdmin(int $limit = 500): array {
        $limit = max(1, min(2000, $limit));
        $sql = "SELECT u.id, u.name, u.email, u.phone, u.role, u.is_active, u.email_verified, u.created_at,
                       c.name AS city_name,
                       p.profession, p.is_company, p.company_name, p.photo
                FROM users u
                LEFT JOIN cities c     ON c.id     = u.city_id
                LEFT JOIN providers p  ON p.user_id = u.id
                ORDER BY u.created_at DESC
                LIMIT $limit";
        return DB::q($sql)->fetchAll();
    }

    public static function setRole(int $id, string $role): void {
        if (!in_array($role, ['client','provider','admin'], true)) return;
        DB::q('UPDATE users SET role = ? WHERE id = ?', [$role, $id]);
    }

    /**
     * Delete a user and all uploaded files they own (provider photo + post photos).
     * The DB tables already cascade — this method just removes the on-disk artifacts
     * BEFORE the cascade kicks in, so we don't orphan files in public/uploads/.
     */
    public static function deleteFully(int $id): void {
        // Provider profile photo
        $pp = DB::q('SELECT photo FROM providers WHERE user_id=?', [$id])->fetchColumn();
        if (is_string($pp) && $pp !== '') {
            $path = CONFIG['upload_dir'] . DIRECTORY_SEPARATOR . $pp;
            if (is_file($path)) @unlink($path);
        }
        // Post photos for all posts they authored
        $files = DB::q(
            'SELECT ph.filename FROM post_photos ph JOIN posts p ON p.id = ph.post_id WHERE p.user_id = ?',
            [$id]
        )->fetchAll();
        foreach ($files as $f) {
            $path = CONFIG['upload_dir'] . DIRECTORY_SEPARATOR . $f['filename'];
            if (is_file($path)) @unlink($path);
        }
        // Now the cascade DELETE will remove the DB rows.
        DB::q('DELETE FROM users WHERE id = ?', [$id]);
    }
}
