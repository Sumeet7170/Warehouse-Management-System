<?php

final class Database
{
    private static ?PDO $pdo = null;
    private static int $depth = 0;
    private static ?string $activeDb = null;
    private static ?string $currentAccountDb = null;
    private static bool $bootstrapping = false;

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
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            if (!empty($c['ssl_ca'])) {
                if (str_contains($c['ssl_ca'], 'BEGIN CERTIFICATE')) {
                    $caFile = sys_get_temp_dir() . '/wms_db_ca.pem';
                    if (!is_file($caFile)) {
                        file_put_contents($caFile, $c['ssl_ca']);
                    }
                    $options[PDO::MYSQL_ATTR_SSL_CA] = $caFile;
                } else {
                    $options[PDO::MYSQL_ATTR_SSL_CA] = $c['ssl_ca'];
                }
            } elseif (!empty($c['ssl'])) {
                foreach (['/etc/ssl/certs/ca-certificates.crt', '/etc/pki/tls/certs/ca-bundle.crt'] as $bundle) {
                    if (is_file($bundle)) {
                        $options[PDO::MYSQL_ATTR_SSL_CA] = $bundle;
                        break;
                    }
                }
                if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                }
            }
            $pdo = new PDO($dsn, $c['user'], $c['pass'], $options);
            $pdo->exec("SET time_zone = '" . date('P') . "'");
            self::$pdo = $pdo;
            self::$activeDb = null;
        }

        $targetDb = self::resolveDb();
        if (self::$activeDb !== $targetDb) {
            try {
                self::$pdo->exec("USE `$targetDb`");
            } catch (PDOException $e) {
                $code = (int)($e->errorInfo[1] ?? 0);
                if ($code === 1049 && !self::$bootstrapping && self::autoSeedAllowed()) {
                    self::seedShowcase();
                    self::$pdo->exec("USE `$targetDb`");
                } else {
                    throw $e;
                }
            }
            self::$activeDb = $targetDb;
        }

        return self::$pdo;
    }

    private static function autoSeedAllowed(): bool
    {
        return (bool)(getenv('VERCEL') || getenv('RENDER') || getenv('WMS_AUTO_SEED'));
    }

    public static function seedShowcase(): void
    {
        if (self::$bootstrapping) {
            return;
        }
        $seedFile = WMS_ROOT . '/database/showcase_seed.sql';
        if (!is_file($seedFile)) {
            return;
        }
        self::$bootstrapping = true;
        try {
            $pdo = self::$pdo;
            if ($pdo === null) {
                return;
            }
            $masterDb = preg_replace('/[^a-zA-Z0-9_]/', '', App::config('db')['name']);
            try {
                $pdo->query("SELECT 1 FROM `{$masterDb}`.users LIMIT 1");
                return;
            } catch (PDOException $ignored) {
                // Users table not yet initialized; proceed with showcase seed.
            }

            $locked = false;
            try {
                $locked = (int)$pdo->query("SELECT GET_LOCK('wms_showcase_seed', 25)")->fetchColumn() === 1;
            } catch (PDOException $ignored) {
                $locked = false;
            }

            try {
                try {
                    $pdo->query("SELECT 1 FROM `{$masterDb}`.users LIMIT 1");
                    return;
                } catch (PDOException $ignored) {
                    // Still uninitialized; run seed.
                }

                $sql = (string)file_get_contents($seedFile);
                $sql = str_replace('`wms_db`', "`{$masterDb}`", $sql);

                $singleDbFallback = false;
                $parts = explode("CREATE DATABASE IF NOT EXISTS `wms_account_3`", $sql, 2);
                $masterSql = $parts[0];
                $tenantSql = isset($parts[1]) ? "CREATE DATABASE IF NOT EXISTS `wms_account_3`" . $parts[1] : '';

                self::execSqlBatch($pdo, $masterSql, $masterDb);

                if ($tenantSql !== '') {
                    try {
                        $pdo->exec("CREATE DATABASE IF NOT EXISTS `wms_account_3` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        self::execSqlBatch($pdo, $tenantSql);
                    } catch (PDOException $e) {
                        $code = (int)($e->errorInfo[1] ?? 0);
                        if (in_array($code, [1044, 1045, 1227], true)) {
                            $singleDbFallback = true;
                        } else {
                            throw $e;
                        }
                    }
                }

                if ($singleDbFallback && $tenantSql !== '') {
                    $tenantSql = preg_replace('/CREATE DATABASE IF NOT EXISTS `wms_account_3`[^;]*;\s*/', '', $tenantSql);
                    $tenantSql = str_replace('`wms_account_3`', "`{$masterDb}`", $tenantSql);
                    self::execSqlBatch($pdo, $tenantSql);
                    $stmt = $pdo->prepare("UPDATE `{$masterDb}`.users SET account_db = ? WHERE id = 3");
                    $stmt->execute([$masterDb]);
                    $pdo->exec(
                        "CREATE TABLE IF NOT EXISTS `{$masterDb}`.app_sessions (
                            id varchar(128) NOT NULL,
                            data mediumblob NOT NULL,
                            expires_at int UNSIGNED NOT NULL,
                            PRIMARY KEY (id),
                            KEY idx_app_sessions_expires (expires_at)
                        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci"
                    );
                }
                self::$activeDb = null;
            } finally {
                if ($locked) {
                    try {
                        $pdo->query("SELECT RELEASE_LOCK('wms_showcase_seed')");
                    } catch (PDOException $ignored) {
                    }
                }
            }
        } finally {
            self::$bootstrapping = false;
        }
    }

    private static function execSqlBatch(PDO $pdo, string $sql, ?string $ignoreCreateDb = null): void
    {
        foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $st) {
            $st = trim($st);
            if ($st === '' || str_starts_with($st, '--')) {
                continue;
            }
            try {
                $pdo->exec($st);
            } catch (PDOException $e) {
                $code = (int)($e->errorInfo[1] ?? 0);
                if (
                    $ignoreCreateDb !== null
                    && stripos($st, 'CREATE DATABASE') === 0
                    && in_array($code, [1044, 1045, 1227], true)
                ) {
                    $pdo->exec("USE `{$ignoreCreateDb}`");
                    continue;
                }
                throw $e;
            }
        }
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
        try {
            $statement = self::pdo()->prepare($sql);
            $statement->execute($params);
            return $statement;
        } catch (PDOException $e) {
            $code = (int)($e->errorInfo[1] ?? 0);
            if (($code === 1146 || $code === 1049) && !self::$bootstrapping && self::autoSeedAllowed()) {
                self::seedShowcase();
                $statement = self::pdo()->prepare($sql);
                $statement->execute($params);
                return $statement;
            }
            throw $e;
        }
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
