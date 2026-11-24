<?php

final class TransactionsModule
{
    public const READ = ['list', 'types'];
    public const ADMIN = [];
    public const OPEN = [];

    public static function types(array $in): Reply
    {
        return new Reply(['purchase', 'sale', 'transfer_out', 'transfer_in', 'transfer_return', 'adjustment_in', 'adjustment_out', 'damage']);
    }

    public static function list(array $in): Reply
    {
        [$where, $params] = self::filters($in);
        $sql = self::baseSql() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY t.id DESC';
        return Paginator::reply($sql, $params, $in);
    }

    public static function baseSql(): string
    {
        return 'SELECT t.id, t.txn_date, t.txn_type, t.quantity_change, t.balance_after, t.batch_number, t.reference_type, t.reference_id, t.reference_no, t.notes,
                p.sku, p.name AS product_name, w.name AS warehouse_name, u.name AS user_name
                FROM stock_transactions t
                JOIN products p ON p.id = t.product_id
                JOIN warehouses w ON w.id = t.warehouse_id
                JOIN users u ON u.id = t.user_id';
    }

    public static function filters(array $in): array
    {
        $where = ['1=1'];
        $params = [];
        $search = trim((string)($in['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR t.batch_number LIKE ? OR t.reference_no LIKE ?)';
            $like = Paginator::like($search);
            array_push($params, $like, $like, $like, $like);
        }
        if (!empty($in['warehouse_id'])) {
            $where[] = 't.warehouse_id = ?';
            $params[] = (int)$in['warehouse_id'];
        }
        if (!empty($in['product_id'])) {
            $where[] = 't.product_id = ?';
            $params[] = (int)$in['product_id'];
        }
        if (!empty($in['user_id'])) {
            $where[] = 't.user_id = ?';
            $params[] = (int)$in['user_id'];
        }
        if (in_array($in['type'] ?? '', ['purchase', 'sale', 'transfer_out', 'transfer_in', 'transfer_return', 'adjustment_in', 'adjustment_out', 'damage'], true)) {
            $where[] = 't.txn_type = ?';
            $params[] = $in['type'];
        }
        if (!empty($in['from'])) {
            $where[] = 't.txn_date >= ?';
            $params[] = $in['from'] . ' 00:00:00';
        }
        if (!empty($in['to'])) {
            $where[] = 't.txn_date <= ?';
            $params[] = $in['to'] . ' 23:59:59';
        }
        return [$where, $params];
    }
}
