<?php

/**
 * MySQL-backed PHP session handler so sessions persist across stateless serverless functions
 * and multi-worker container requests.
 */
final class DbSessionHandler implements SessionHandlerInterface
{
    private string $masterDb;
    private int $lifetime;
    private bool $tableEnsured = false;

    public function __construct(string $masterDb, int $lifetime)
    {
        $this->masterDb = preg_replace('/[^a-zA-Z0-9_]/', '', $masterDb);
        $this->lifetime = max(60, $lifetime);
    }

    private function table(): string
    {
        return "`{$this->masterDb}`.app_sessions";
    }

    private function ensureTable(): void
    {
        if ($this->tableEnsured) {
            return;
        }
        Database::pdo()->exec(
            "CREATE TABLE IF NOT EXISTS {$this->table()} (
                id varchar(128) NOT NULL,
                data mediumblob NOT NULL,
                expires_at int UNSIGNED NOT NULL,
                PRIMARY KEY (id),
                KEY idx_app_sessions_expires (expires_at)
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci"
        );
        $this->tableEnsured = true;
    }

    public function open(string $path, string $name): bool
    {
        $this->ensureTable();
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $this->ensureTable();
        $stmt = Database::pdo()->prepare("SELECT data FROM {$this->table()} WHERE id = ? AND expires_at >= ?");
        $stmt->execute([$id, time()]);
        $data = $stmt->fetchColumn();
        return $data === false ? '' : (string)$data;
    }

    public function write(string $id, string $data): bool
    {
        $this->ensureTable();
        $expires = time() + $this->lifetime;
        $stmt = Database::pdo()->prepare(
            "INSERT INTO {$this->table()} (id, data, expires_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE data = VALUES(data), expires_at = VALUES(expires_at)"
        );
        return $stmt->execute([$id, $data, $expires]);
    }

    public function destroy(string $id): bool
    {
        $this->ensureTable();
        $stmt = Database::pdo()->prepare("DELETE FROM {$this->table()} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $this->ensureTable();
        $stmt = Database::pdo()->prepare("DELETE FROM {$this->table()} WHERE expires_at < ?");
        $stmt->execute([time()]);
        return $stmt->rowCount();
    }
}
