<?php
/**
 * @author  Jcode | ObrempongK
 */
/* ============================================================
   VOXU — Database Layer (PDO Singleton)
   ============================================================ */
if (!defined('APP_NAME')) require_once __DIR__ . '/../config.php';

class DB {
    private static ?PDO $instance = null;

    public static function conn(): PDO {
        if (self::$instance === null) {
            if (
                DB_NAME === 'your_database_name' ||
                DB_USER === 'your_database_user' ||
                DB_PASS === 'your_database_password'
            ) {
                http_response_code(500);
                die('Database credentials are still default placeholders. Update config.php with your actual DB_USER and DB_PASS.');
            }
            try {
                $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT
                     . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                die('Database connection failed. Please verify your database settings in config.php.');
            }
        }
        return self::$instance;
    }

    /** Run a query and return all rows */
    public static function query(string $sql, array $params = []): array {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Run a query and return the first row */
    public static function first(string $sql, array $params = []): ?array {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Execute a query and return affected rows */
    public static function exec(string $sql, array $params = []): int {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** Insert a row and return the last insert ID */
    private static function quoteIdentifier(string $identifier): string {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    public static function insert(string $table, array $data): string|false {
        $cols = implode(',', array_map([self::class, 'quoteIdentifier'], array_keys($data)));
        $phs  = implode(',', array_fill(0, count($data), '?'));
        $stmt = self::conn()->prepare('INSERT INTO ' . self::quoteIdentifier($table) . " ({$cols}) VALUES ({$phs})");
        $stmt->execute(array_values($data));
        return self::conn()->lastInsertId();
    }

    /** Update rows matching $where */
    public static function update(string $table, array $data, array $where): int {
        $set  = implode(',', array_map(fn($k) => self::quoteIdentifier($k) . '=?', array_keys($data)));
        $cond = implode(' AND ', array_map(fn($k) => self::quoteIdentifier($k) . '=?', array_keys($where)));
        $params = array_merge(array_values($data), array_values($where));
        $stmt = self::conn()->prepare('UPDATE ' . self::quoteIdentifier($table) . " SET {$set} WHERE {$cond}");
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** Count rows */
    public static function count(string $table, string $where = '1', array $params = []): int {
        $row = self::first("SELECT COUNT(*) AS n FROM `{$table}` WHERE {$where}", $params);
        return (int)($row['n'] ?? 0);
    }

    public static function lastId(): string { return self::conn()->lastInsertId(); }
    public static function beginTransaction(): void { self::conn()->beginTransaction(); }
    public static function commit(): void { self::conn()->commit(); }
    public static function rollback(): void { self::conn()->rollBack(); }
}
