<?php
declare(strict_types=1);

final class Category {
    public static function all(): array {
        return DB::q('SELECT id, name, slug, icon FROM categories ORDER BY name')->fetchAll();
    }
    public static function find(int $id): ?array {
        $r = DB::q('SELECT id, name, slug, icon FROM categories WHERE id=?', [$id])->fetch();
        return $r ?: null;
    }
    public static function findBySlug(string $slug): ?array {
        $r = DB::q('SELECT id, name, slug, icon FROM categories WHERE slug=?', [$slug])->fetch();
        return $r ?: null;
    }
    public static function create(string $name, string $slug, ?string $icon): int {
        DB::q('INSERT INTO categories (name, slug, icon) VALUES (?, ?, ?)', [$name, $slug, $icon]);
        return (int)DB::pdo()->lastInsertId();
    }
    public static function delete(int $id): void {
        DB::q('DELETE FROM categories WHERE id=?', [$id]);
    }
    public static function hasProviders(int $id): bool {
        $r = DB::q('SELECT 1 FROM provider_categories WHERE category_id=? LIMIT 1', [$id])->fetch();
        return (bool)$r;
    }
}
