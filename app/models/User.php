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
        DB::q('INSERT INTO users (name, email, password_hash, phone, role, city_id) VALUES (?, ?, ?, ?, ?, ?)',
              [$name, $email, $passwordHash, $phone, $role, $cityId]);
        return (int)DB::pdo()->lastInsertId();
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
}
