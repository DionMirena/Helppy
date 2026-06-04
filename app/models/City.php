<?php
declare(strict_types=1);

final class City {
    public static function all(): array {
        return DB::q('SELECT id, name FROM cities ORDER BY name')->fetchAll();
    }
    public static function find(int $id): ?array {
        $r = DB::q('SELECT id, name FROM cities WHERE id=?', [$id])->fetch();
        return $r ?: null;
    }
}
