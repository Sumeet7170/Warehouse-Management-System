<?php

final class Database
{
    private static ?PDO $pdo = null;
    private static int $depth = 0;
    private static ?string $activeDb = null;
    private static ?string $currentAccountDb = null;

    public static function resolveDb(): string
    {
        if (self::$currentAccountDb !== null) {
            return self::$currentAccountDb;
        }
        if (!empty($_SESSION['account_db'])) {
            return preg_replace('/[^a-zA-Z0-9_]/', '', $_SESSION['account_db']);
        }
        return App::config('db')['name'];
    }

    public static function setAccountDb(?string $dbName): void
    {
        $db = $dbName ? preg_replace('/[^a-zA-Z0-9_]/', '', $dbName) : App::config('db')['name'];
        self::$currentAccountDb = $db;
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['account_db'] = $db;
        }
        if (self::$pdo !== null && self::$activeDb !== $db) {
            self::$pdo->exec("USE `$db`");
            self::$activeDb = $db;
        }
    }

    public static function activeDb(): string
    {
        return self::resolveDb();
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = App::config('db');
            $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $c['host'], $c['port'], $c['charset']);
            $pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec("SET time_zone = '" . date('P') . "'");
            self::$pdo = $pdo;
            self::$activeDb = null;
        }

        $targetDb = self::resolveDb();
        if (self::$activeDb !== $targetDb) {
            self::$pdo->exec("USE `$targetDb`");
            self::$activeDb = $targetDb;
        }

        return self::$pdo;
    }

    public static function provisionAccount(string $dbName, array $userData): void
    {
        $pdo = self::pdo();
        $db = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$db`");
        self::$activeDb = $db;
        self::$currentAccountDb = $db;

        $schemaFile = WMS_ROOT . '/database/schema.sql';
        if (is_file($schemaFile)) {
            $sql = file_get_contents($schemaFile);
            $sql = str_replace('wms_db', $db, $sql);
            foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $st) {
                $st = trim($st);
                if ($st !== '') {
                    $pdo->exec($st);
                }
            }
        }

        $stmt = $pdo->prepare('INSERT INTO users (id, name, email, password_hash, role, is_active, account_db) VALUES (?, ?, ?, ?, ?, 1, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), role = VALUES(role)');
        $stmt->execute([
            $userData['id'],
            $userData['name'],
            $userData['email'],
            $userData['password_hash'],
            $userData['role'] ?? 'admin',
            $db
        ]);
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $statement = self::pdo()->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $value = self::run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    public static function exec(string $sql, array $params = []): int
    {
        return self::run($sql, $params)->rowCount();
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);
        return (int)self::pdo()->lastInsertId();
    }

    public static function transaction(callable $callback): mixed
    {
        if (self::$depth > 0) {
            return $callback();
        }
        $attempt = 0;
        while (true) {
            $attempt++;
            $pdo = self::pdo();
            self::$depth = 1;
            $pdo->beginTransaction();
            try {
                $result = $callback();
                $pdo->commit();
                self::$depth = 0;
                return $result;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                self::$depth = 0;
                $code = $e instanceof PDOException ? (int)($e->errorInfo[1] ?? 0) : 0;
                if (($code === 1213 || $code === 1205) && $attempt < 3) {
                    usleep(120000 * $attempt);
                    continue;
                }
                throw $e;
            }
        }
    }
}
