<?php

final class ActivityModule
{
    public const READ = ['list'];
    public const ADMIN = ['*'];
    public const OPEN = [];

    public static function list(array $in): Reply
    {
        $where = ['1=1'];
        $params = [];
        $search = trim((string)($in['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(a.action LIKE ? OR a.details LIKE ? OR u.name LIKE ?)';
            $like = Paginator::like($search);
            array_push($params, $like, $like, $like);
        }
        if (!empty($in['user_id'])) {
            $where[] = 'a.user_id = ?';
            $params[] = (int)$in['user_id'];
        }
        if (!empty($in['from'])) {
            $where[] = 'a.created_at >= ?';
            $params[] = $in['from'] . ' 00:00:00';
        }
        if (!empty($in['to'])) {
            $where[] = 'a.created_at <= ?';
            $params[] = $in['to'] . ' 23:59:59';
        }
        $sql = 'SELECT a.id, a.created_at, a.action, a.entity, a.entity_id, a.details, a.ip_address, u.name AS user_name
                FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id
                WHERE ' . implode(' AND ', $where) . ' ORDER BY a.id DESC';
        return Paginator::reply($sql, $params, $in);
    }
}
