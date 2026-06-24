<?php
declare(strict_types=1);

final class Provider {
    /** Find a provider with joined user + city info. */
    public static function find(int $userId): ?array {
        $sql = "SELECT u.id, u.name, u.email, u.phone, u.is_active, u.email_verified, u.created_at,
                       c.name AS city, u.district,
                       p.profession, p.bio, p.skills_services, p.hourly_rate, p.photo, p.is_company, p.company_name,
                       p.latitude, p.longitude,
                       p.is_premium, p.views
                FROM providers p
                JOIN users u ON u.id = p.user_id
                LEFT JOIN cities c ON c.id = u.city_id
                WHERE p.user_id = ?";
        $r = DB::q($sql, [$userId])->fetch();
        if (!$r) return null;
        $r['categories']   = self::categories($userId);
        $r['avg_rating']   = self::avgRating($userId);
        $r['review_count'] = self::reviewCount($userId);
        return $r;
    }

    /** Search by city_id, category_id, and optional name/company query. */
    public static function search(?int $cityId, ?int $categoryId, ?string $query = null): array {
        $cityIds = $cityId === null ? [] : [$cityId];
        return self::searchInCities($cityIds, $categoryId, $query);
    }

    /**
     * Search restricted to a set of city IDs (used by the district fallback).
     * Empty $cityIds means "any city". $query matches against the user's name,
     * the company name, and the profession (case-insensitive LIKE).
     */
    public static function searchInCities(array $cityIds, ?int $categoryId, ?string $query = null): array {
        $sql = "SELECT u.id, u.name, u.phone, u.city_id, c.name AS city, u.district,
                       p.profession, p.photo, p.is_company, p.company_name,
                       p.is_premium, p.hourly_rate,
                       (SELECT AVG(rating) FROM reviews WHERE provider_id = p.user_id) AS avg_rating,
                       (SELECT COUNT(*)   FROM reviews WHERE provider_id = p.user_id) AS review_count
                FROM providers p
                JOIN users u ON u.id = p.user_id AND u.is_active = 1 AND u.email_verified = 1
                LEFT JOIN cities c ON c.id = u.city_id
                WHERE 1=1 ";
        $args = [];
        if (!empty($cityIds)) {
            $placeholders = implode(',', array_fill(0, count($cityIds), '?'));
            $sql .= " AND u.city_id IN ($placeholders)";
            foreach ($cityIds as $id) $args[] = (int)$id;
        }
        if ($categoryId !== null) {
            $sql .= " AND EXISTS (SELECT 1 FROM provider_categories pc WHERE pc.provider_id = p.user_id AND pc.category_id = ?)";
            $args[] = $categoryId;
        }
        $q = $query !== null ? trim($query) : '';
        if ($q !== '') {
            $like = '%' . $q . '%';
            $sql .= " AND (u.name LIKE ? OR p.company_name LIKE ? OR p.profession LIKE ?)";
            $args[] = $like; $args[] = $like; $args[] = $like;
        }
        $sql .= " ORDER BY p.is_premium DESC, u.created_at DESC";
        return DB::q($sql, $args)->fetchAll();
    }

    /** Featured strip for home page. */
    public static function featured(int $limit = 8): array {
        $limit = max(1, min(50, $limit));
        $sql = "SELECT u.id, u.name, u.phone, c.name AS city,
                       p.profession, p.photo, p.is_company, p.is_premium, p.hourly_rate,
                       (SELECT AVG(rating) FROM reviews WHERE provider_id = p.user_id) AS avg_rating
                FROM providers p
                JOIN users u ON u.id = p.user_id AND u.is_active = 1 AND u.email_verified = 1
                LEFT JOIN cities c ON c.id = u.city_id
                ORDER BY p.is_premium DESC, RAND()
                LIMIT $limit";
        return DB::q($sql)->fetchAll();
    }

    /**
     * Stable, paginated provider list for the home page (premium first, then
     * newest). Used by the "load more on scroll" behaviour.
     */
    /**
     * $type is 'person', 'company', or '' (any). Filters by providers.is_company.
     */
    public static function listPaged(int $offset, int $limit, string $type = ''): array {
        $offset = max(0, $offset);
        $limit  = max(1, min(50, $limit));
        $where  = self::typeWhere($type);
        $sql = "SELECT u.id, u.name, u.phone, c.name AS city, u.district,
                       p.profession, p.photo, p.is_company, p.company_name,
                       p.is_premium, p.hourly_rate,
                       (SELECT AVG(rating) FROM reviews WHERE provider_id = p.user_id) AS avg_rating
                FROM providers p
                JOIN users u ON u.id = p.user_id AND u.is_active = 1 AND u.email_verified = 1
                LEFT JOIN cities c ON c.id = u.city_id
                WHERE 1=1 $where
                ORDER BY p.is_premium DESC, u.id DESC
                LIMIT $limit OFFSET $offset";
        return DB::q($sql)->fetchAll();
    }

    public static function listCount(string $type = ''): int {
        $where = self::typeWhere($type);
        return (int)DB::q(
            "SELECT COUNT(*) FROM providers p
             JOIN users u ON u.id = p.user_id AND u.is_active = 1 AND u.email_verified = 1
             WHERE 1=1 $where"
        )->fetchColumn();
    }

    /** Inline SQL fragment for the is_company filter — safe (hardcoded values). */
    private static function typeWhere(string $type): string {
        if ($type === 'person')  return ' AND p.is_company = 0 ';
        if ($type === 'company') return ' AND p.is_company = 1 ';
        return '';
    }

    public static function categories(int $userId): array {
        return DB::q("SELECT c.id, c.name, c.slug FROM provider_categories pc
                      JOIN categories c ON c.id = pc.category_id
                      WHERE pc.provider_id = ?", [$userId])->fetchAll();
    }

    public static function setCategories(int $userId, array $categoryIds): void {
        DB::q('DELETE FROM provider_categories WHERE provider_id = ?', [$userId]);
        $st = DB::pdo()->prepare('INSERT INTO provider_categories (provider_id, category_id) VALUES (?, ?)');
        foreach ($categoryIds as $cid) {
            $st->execute([$userId, (int)$cid]);
        }
    }

    public static function avgRating(int $userId): ?float {
        $r = DB::q('SELECT AVG(rating) AS a FROM reviews WHERE provider_id=?', [$userId])->fetch();
        return $r && $r['a'] !== null ? (float)$r['a'] : null;
    }

    public static function reviewCount(int $userId): int {
        return (int)DB::q('SELECT COUNT(*) FROM reviews WHERE provider_id=?', [$userId])->fetchColumn();
    }

    public static function create(int $userId, string $profession, bool $isCompany, ?string $companyName): void {
        DB::q('INSERT INTO providers (user_id, profession, is_company, company_name) VALUES (?, ?, ?, ?)',
              [$userId, $profession, $isCompany ? 1 : 0, $companyName]);
    }

    public static function update(int $userId, array $fields): void {
        $allowed = ['profession','bio','company_name','skills_services','hourly_rate','latitude','longitude'];
        $sets = []; $args = [];
        foreach ($fields as $k => $v) {
            if (in_array($k, $allowed, true)) { $sets[] = "$k = ?"; $args[] = $v; }
        }
        if (!$sets) return;
        $args[] = $userId;
        DB::q('UPDATE providers SET ' . implode(', ', $sets) . ' WHERE user_id = ?', $args);
    }

    public static function setPhoto(int $userId, string $filename): void {
        DB::q('UPDATE providers SET photo=? WHERE user_id=?', [$filename, $userId]);
    }

    public static function incrementViews(int $userId): void {
        DB::q('UPDATE providers SET views = views + 1 WHERE user_id = ?', [$userId]);
    }

    public static function togglePremium(int $userId): void {
        DB::q('UPDATE providers SET is_premium = 1 - is_premium WHERE user_id = ?', [$userId]);
    }

    public static function allWithStatus(): array {
        return DB::q("SELECT u.id, u.name, u.email, u.is_active, u.email_verified, u.created_at,
                             p.profession, p.is_premium
                      FROM providers p
                      JOIN users u ON u.id = p.user_id
                      ORDER BY u.created_at DESC")->fetchAll();
    }
}
