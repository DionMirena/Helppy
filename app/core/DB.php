<?php
declare(strict_types=1);

final class DB {
    private static ?PDO $pdo = null;

    public static function pdo(): PDO {
        if (self::$pdo === null) {
            $c = CONFIG['db'];
            $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}";
            self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }

    public static function q(string $sql, array $params = []): PDOStatement {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }
}
