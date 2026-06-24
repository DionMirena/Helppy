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

    /**
     * Return the id of a category matching $name (case-insensitive), creating
     * it if it doesn't exist yet. Returns 0 if $name is too short to bother.
     */
    public static function findOrCreateByName(string $name): int {
        $name = trim($name);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) return 0;

        // Case-insensitive lookup first.
        $r = DB::q('SELECT id FROM categories WHERE LOWER(name) = LOWER(?) LIMIT 1', [$name])->fetch();
        if ($r) return (int)$r['id'];

        // Generate a unique slug, falling back to -2, -3 etc. on collision.
        $base = self::slugify($name);
        if ($base === '') $base = 'kategori';
        $slug = $base;
        $i = 2;
        while (DB::q('SELECT 1 FROM categories WHERE slug = ? LIMIT 1', [$slug])->fetch()) {
            $slug = $base . '-' . $i++;
            if ($i > 100) break;
        }
        return self::create($name, $slug, 'bi-tag');
    }

    /** Albanian-aware slugifier: strips diacritics, lowercases, dashes for spaces. */
    public static function slugify(string $s): string {
        $map = [
            'ç'=>'c','Ç'=>'c','ë'=>'e','Ë'=>'e',
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u',
            'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u',
            'ş'=>'s','Ş'=>'s','ğ'=>'g','Ğ'=>'g',
        ];
        $s = strtr($s, $map);
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('/[^a-z0-9]+/u', '-', $s);
        $s = trim($s, '-');
        return $s;
    }

    public static function delete(int $id): void {
        DB::q('DELETE FROM categories WHERE id=?', [$id]);
    }
    public static function hasProviders(int $id): bool {
        $r = DB::q('SELECT 1 FROM provider_categories WHERE category_id=? LIMIT 1', [$id])->fetch();
        return (bool)$r;
    }
}
