<?php
declare(strict_types=1);

final class Category {
    /** Full list including children — used by admin and search filters. */
    public static function all(): array {
        return DB::q('SELECT id, parent_id, name, slug, icon FROM categories ORDER BY name')->fetchAll();
    }
    /** Newest first — used by the admin categories table so a fresh add lands on top. */
    public static function allNewestFirst(): array {
        return DB::q('SELECT id, parent_id, name, slug, icon FROM categories ORDER BY id DESC')->fetchAll();
    }
    /** Top-level only — used for the home/posts chip strip. */
    public static function topLevel(): array {
        return DB::q('SELECT id, parent_id, name, slug, icon FROM categories WHERE parent_id IS NULL ORDER BY name')->fetchAll();
    }

    /**
     * Top-level categories that actually have at least one child. Used on the
     * home page so the chip strip shows only "umbrella" families that drill
     * down — everything else is still findable via Kërko kategori.
     */
    public static function topLevelWithChildren(): array {
        return DB::q(
            'SELECT p.id, p.parent_id, p.name, p.slug, p.icon
             FROM categories p
             WHERE p.parent_id IS NULL
               AND EXISTS (SELECT 1 FROM categories c WHERE c.parent_id = p.id)
             ORDER BY p.name'
        )->fetchAll();
    }
    /** Children of a given parent id. */
    public static function children(int $parentId): array {
        return DB::q('SELECT id, parent_id, name, slug, icon FROM categories WHERE parent_id = ? ORDER BY name', [$parentId])->fetchAll();
    }

    /** Category id + all its direct child ids — used to widen "filter by umbrella". */
    public static function idsForFilter(int $categoryId): array {
        $ids = [$categoryId];
        foreach (DB::q('SELECT id FROM categories WHERE parent_id = ?', [$categoryId])->fetchAll() as $r) {
            $ids[] = (int)$r['id'];
        }
        return $ids;
    }
    public static function find(int $id): ?array {
        $r = DB::q('SELECT id, parent_id, name, slug, icon FROM categories WHERE id=?', [$id])->fetch();
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
