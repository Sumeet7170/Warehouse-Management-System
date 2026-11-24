<?php

final class Paginator
{
    public static function like(string $term): string
    {
        return '%' . addcslashes($term, '%_\\') . '%';
    }

    public static function reply(string $sql, array $params, array $input, ?callable $map = null): Reply
    {
        $default = (int)App::config('app')['default_per_page'];
        $perPage = (int)($input['per_page'] ?? $default);
        if ($perPage < 5 || $perPage > 100) {
            $perPage = $default;
        }
        $page = max(1, (int)($input['page'] ?? 1));
        $total = (int)Database::value('SELECT COUNT(*) FROM (' . $sql . ') AS paginated_count', $params);
        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }
        $offset = ($page - 1) * $perPage;
        $rows = Database::all($sql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset, $params);
        if ($map !== null) {
            $rows = array_map($map, $rows);
        }
        return new Reply($rows, null, ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => $pages]);
    }
}
