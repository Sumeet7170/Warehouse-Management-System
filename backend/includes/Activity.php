<?php

final class Activity
{
    public static function log(string $action, ?string $entity = null, ?int $entityId = null, ?string $details = null): void
    {
        $userId = Auth::check() ? Auth::id() : null;
        Database::exec(
            'INSERT INTO activity_logs (user_id, action, entity, entity_id, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [$userId, $action, $entity, $entityId, $details === null ? null : mb_substr($details, 0, 500), $_SERVER['REMOTE_ADDR'] ?? null]
        );
    }
}
