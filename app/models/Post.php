<?php
declare(strict_types=1);

final class Post {
    /** Find one post with joined user, category, city. Returns null if not found. */
    public static function find(int $id): ?array {
        $sql = "SELECT p.*, u.name AS author_name, u.email AS author_email, u.phone AS author_phone, u.role AS author_role,
                       cat.name AS category_name, cat.icon AS category_icon,
                       ct.name AS city_name
                FROM posts p
                JOIN users u        ON u.id = p.user_id
                JOIN categories cat ON cat.id = p.category_id
                JOIN cities ct      ON ct.id = p.city_id
                WHERE p.id = ?";
        $r = DB::q($sql, [$id])->fetch();
        return $r ?: null;
    }

    /**
     * Feed query with optional filters.
     * $filters keys: type (offer|request), category_id (int), city_id (int), include_hidden (bool, admin only).
     * Returns rows with first-photo filename joined as `photo` (nullable).
     */
    public static function feed(array $filters = [], int $limit = 60): array {
        $limit = max(1, min(200, $limit));

        $sql = "SELECT p.id, p.type, p.title, p.category_id, p.city_id, p.status, p.created_at,
                       p.price_from, p.price_to, p.budget_from, p.budget_to, p.urgency, p.deadline,
                       u.name AS author_name, u.role AS author_role,
                       cat.name AS category_name, cat.icon AS category_icon,
                       ct.name AS city_name,
                       (SELECT filename FROM post_photos ph WHERE ph.post_id = p.id ORDER BY ph.sort_order, ph.id LIMIT 1) AS photo
                FROM posts p
                JOIN users u        ON u.id = p.user_id
                JOIN categories cat ON cat.id = p.category_id
                JOIN cities ct      ON ct.id = p.city_id
                WHERE 1=1 ";
        $args = [];

        if (empty($filters['include_hidden'])) {
            $sql .= " AND p.status = 'active' ";
        }
        if (!empty($filters['type']) && in_array($filters['type'], ['offer','request'], true)) {
            $sql .= " AND p.type = ? ";
            $args[] = $filters['type'];
        }
        if (!empty($filters['category_id'])) {
            $sql .= " AND p.category_id = ? ";
            $args[] = (int)$filters['category_id'];
        }
        if (!empty($filters['city_id'])) {
            $sql .= " AND p.city_id = ? ";
            $args[] = (int)$filters['city_id'];
        } elseif (!empty($filters['city_ids']) && is_array($filters['city_ids'])) {
            $ids = array_values(array_filter(array_map('intval', $filters['city_ids'])));
            if ($ids) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $sql .= " AND p.city_id IN ($placeholders) ";
                foreach ($ids as $id) $args[] = $id;
            }
        }

        $sql .= " ORDER BY p.created_at DESC LIMIT $limit";

        return DB::q($sql, $args)->fetchAll();
    }

    /** All posts for the admin table (active + closed + hidden). */
    public static function allForAdmin(int $limit = 200): array {
        $limit = max(1, min(500, $limit));
        $sql = "SELECT p.id, p.type, p.title, p.status, p.created_at,
                       u.name AS author_name, u.role AS author_role,
                       cat.name AS category_name, ct.name AS city_name
                FROM posts p
                JOIN users u        ON u.id = p.user_id
                JOIN categories cat ON cat.id = p.category_id
                JOIN cities ct      ON ct.id = p.city_id
                ORDER BY p.created_at DESC
                LIMIT $limit";
        return DB::q($sql)->fetchAll();
    }

    /** All active posts authored by one user. */
    public static function forAuthor(int $userId): array {
        $sql = "SELECT id, type, title, status, created_at FROM posts WHERE user_id = ? ORDER BY created_at DESC";
        return DB::q($sql, [$userId])->fetchAll();
    }

    /** Insert a post. $data is pre-validated. Returns the new id. */
    public static function create(int $userId, string $type, array $data): int {
        $sql = "INSERT INTO posts
                  (user_id, type, title, description, category_id, city_id,
                   price_from, price_to, working_hours, contact_preferences,
                   budget_from, budget_to, deadline, urgency)
                VALUES (?,?,?,?,?,?, ?,?,?,?, ?,?,?,?)";
        DB::q($sql, [
            $userId,
            $type,
            $data['title'],
            $data['description'],
            $data['category_id'],
            $data['city_id'],
            $data['price_from']          ?? null,
            $data['price_to']            ?? null,
            $data['working_hours']       ?? null,
            $data['contact_preferences'] ?? null,
            $data['budget_from']         ?? null,
            $data['budget_to']           ?? null,
            $data['deadline']            ?? null,
            $data['urgency']             ?? null,
        ]);
        return (int)DB::pdo()->lastInsertId();
    }

    /** Update an existing post. Only fields present in $data are touched. */
    public static function update(int $id, array $data): void {
        $allowed = ['title','description','category_id','city_id',
                    'price_from','price_to','working_hours','contact_preferences',
                    'budget_from','budget_to','deadline','urgency'];
        $sets = []; $args = [];
        foreach ($data as $k => $v) {
            if (in_array($k, $allowed, true)) { $sets[] = "$k = ?"; $args[] = $v; }
        }
        if (!$sets) return;
        $args[] = $id;
        DB::q('UPDATE posts SET ' . implode(', ', $sets) . ' WHERE id = ?', $args);
    }

    public static function close(int $id): void {
        DB::q("UPDATE posts SET status = 'closed' WHERE id = ?", [$id]);
    }

    public static function hide(int $id): void {
        DB::q("UPDATE posts SET status = 'hidden' WHERE id = ?", [$id]);
    }

    public static function delete(int $id): void {
        DB::q('DELETE FROM posts WHERE id = ?', [$id]);
    }

    public static function incrementViews(int $id): void {
        DB::q('UPDATE posts SET views = views + 1 WHERE id = ?', [$id]);
    }

    /** True if the post exists and belongs to $userId. */
    public static function ownedBy(int $id, int $userId): bool {
        $r = DB::q('SELECT 1 FROM posts WHERE id = ? AND user_id = ?', [$id, $userId])->fetch();
        return (bool)$r;
    }
}
