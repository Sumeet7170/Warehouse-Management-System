<?php

final class InventoryModule
{
    public const READ = ['stock', 'batches', 'available', 'availability', 'low_stock', 'expiring'];
    public const ADMIN = [];
    public const OPEN = [];

    public static function stock(array $in): Reply
    {
        $where = ['1=1'];
        $params = [];
        $search = trim((string)($in['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)';
            $like = Paginator::like($search);
            array_push($params, $like, $like, $like);
        }
        if (!empty($in['warehouse_id'])) {
            $where[] = 'b.warehouse_id = ?';
            $params[] = (int)$in['warehouse_id'];
        }
        if (!empty($in['category_id'])) {
            $where[] = 'p.category_id = ?';
            $params[] = (int)$in['category_id'];
        }
        $having = 'HAVING SUM(b.quantity) > 0';
        $level = $in['level'] ?? '';
        if ($level === 'low') {
            $having .= ' AND p.min_stock_level > 0 AND sellable_qty < p.min_stock_level';
        } elseif ($level === 'expired') {
            $having .= ' AND expired_qty > 0';
        }
        $sql = 'SELECT p.id AS product_id, p.sku, p.name, p.unit, p.min_stock_level, c.name AS category_name,
                w.id AS warehouse_id, w.name AS warehouse_name,
                SUM(b.quantity) AS total_qty,
                SUM(CASE WHEN ' . Inventory::SELLABLE . ' THEN b.quantity ELSE 0 END) AS sellable_qty,
                SUM(CASE WHEN ' . Inventory::SELLABLE . ' THEN 0 ELSE b.quantity END) AS expired_qty,
                SUM(b.quantity * b.unit_cost) AS stock_value,
                COUNT(CASE WHEN b.quantity > 0 THEN 1 END) AS batch_count
                FROM inventory_batches b
                JOIN products p ON p.id = b.product_id
                JOIN warehouses w ON w.id = b.warehouse_id
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY p.id, p.sku, p.name, p.unit, p.min_stock_level, c.name, w.id, w.name ' . $having . '
                ORDER BY p.name ASC, w.name ASC';
        return Paginator::reply($sql, $params, $in);
    }

    public static function batches(array $in): Reply
    {
        $where = ['1=1'];
        $params = [];
        $search = trim((string)($in['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR b.batch_number LIKE ?)';
            $like = Paginator::like($search);
            array_push($params, $like, $like, $like);
        }
        if (!empty($in['warehouse_id'])) {
            $where[] = 'b.warehouse_id = ?';
            $params[] = (int)$in['warehouse_id'];
        }
        if (!empty($in['product_id'])) {
            $where[] = 'b.product_id = ?';
            $params[] = (int)$in['product_id'];
        }
        if (($in['include_empty'] ?? '') !== '1') {
            $where[] = 'b.quantity > 0';
        }
        $days = (int)App::config('app')['expiry_alert_days'];
        $state = $in['expiry'] ?? '';
        if ($state === 'expired') {
            $where[] = 'b.expiry_date < CURDATE()';
        } elseif ($state === 'expiring') {
            $where[] = 'b.expiry_date >= CURDATE() AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ' . $days . ' DAY)';
        } elseif ($state === 'valid') {
            $where[] = '(b.expiry_date IS NULL OR b.expiry_date > DATE_ADD(CURDATE(), INTERVAL ' . $days . ' DAY))';
        }
        $sql = 'SELECT b.id, b.batch_number, b.expiry_date, b.quantity, b.initial_quantity, b.unit_cost, b.received_at,
                p.id AS product_id, p.sku, p.name AS product_name, w.id AS warehouse_id, w.name AS warehouse_name,
                CASE WHEN b.expiry_date IS NULL THEN NULL ELSE DATEDIFF(b.expiry_date, CURDATE()) END AS days_to_expiry
                FROM inventory_batches b
                JOIN products p ON p.id = b.product_id
                JOIN warehouses w ON w.id = b.warehouse_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY p.name ASC, w.name ASC, b.received_at ASC, b.id ASC';
        return Paginator::reply($sql, $params, $in);
    }

    public static function available(array $in): Reply
    {
        $productId = V::id($in['product_id'] ?? null, 'Product');
        $warehouseId = V::id($in['warehouse_id'] ?? null, 'Warehouse');
        $rows = Database::all(
            'SELECT b.id, b.batch_number, b.expiry_date, b.quantity, b.unit_cost, b.received_at,
                    CASE WHEN b.expiry_date IS NOT NULL AND b.expiry_date < CURDATE() THEN 1 ELSE 0 END AS is_expired
             FROM inventory_batches b WHERE b.product_id = ? AND b.warehouse_id = ? AND b.quantity > 0
             ORDER BY b.received_at ASC, b.id ASC',
            [$productId, $warehouseId]
        );
        return new Reply($rows);
    }

    public static function availability(array $in): Reply
    {
        $warehouseId = V::id($in['warehouse_id'] ?? null, 'Warehouse');
        $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)($in['product_ids'] ?? ''))), static fn($v) => $v > 0)));
        $result = [];
        if ($ids) {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $rows = Database::all(
                'SELECT b.product_id, SUM(b.quantity) AS qty FROM inventory_batches b
                 WHERE b.warehouse_id = ? AND b.product_id IN (' . $marks . ') AND ' . Inventory::SELLABLE . ' GROUP BY b.product_id',
                array_merge([$warehouseId], $ids)
            );
            foreach ($ids as $id) {
                $result[(string)$id] = 0;
            }
            foreach ($rows as $row) {
                $result[(string)$row['product_id']] = (int)$row['qty'];
            }
        }
        return new Reply((object)$result);
    }

    public static function low_stock(array $in): Reply
    {
        $warehouseId = !empty($in['warehouse_id']) ? (int)$in['warehouse_id'] : null;
        $rows = Inventory::lowStock($warehouseId);
        $search = mb_strtolower(trim((string)($in['search'] ?? '')));
        if ($search !== '') {
            $rows = array_values(array_filter($rows, static fn($r) => str_contains(mb_strtolower($r['name'] . ' ' . $r['sku']), $search)));
        }
        $per = max(5, min(100, (int)($in['per_page'] ?? App::config('app')['default_per_page'])));
        $total = count($rows);
        $pages = max(1, (int)ceil($total / $per));
        $page = min($pages, max(1, (int)($in['page'] ?? 1)));
        return new Reply(array_slice($rows, ($page - 1) * $per, $per), null, ['page' => $page, 'per_page' => $per, 'total' => $total, 'pages' => $pages]);
    }

    public static function expiring(array $in): Reply
    {
        $days = max(1, min(365, (int)($in['days'] ?? App::config('app')['expiry_alert_days'])));
        $where = ['b.quantity > 0', 'b.expiry_date IS NOT NULL'];
        $params = [];
        $mode = $in['mode'] ?? 'all';
        if ($mode === 'expired') {
            $where[] = 'b.expiry_date < CURDATE()';
        } elseif ($mode === 'expiring') {
            $where[] = 'b.expiry_date >= CURDATE() AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ' . $days . ' DAY)';
        } else {
            $where[] = 'b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ' . $days . ' DAY)';
        }
        if (!empty($in['warehouse_id'])) {
            $where[] = 'b.warehouse_id = ?';
            $params[] = (int)$in['warehouse_id'];
        }
        $sql = 'SELECT b.id, b.batch_number, b.expiry_date, b.quantity, b.unit_cost, p.id AS product_id, p.sku, p.name AS product_name,
                w.id AS warehouse_id, w.name AS warehouse_name, DATEDIFF(b.expiry_date, CURDATE()) AS days_to_expiry
                FROM inventory_batches b JOIN products p ON p.id = b.product_id JOIN warehouses w ON w.id = b.warehouse_id
                WHERE ' . implode(' AND ', $where) . ' ORDER BY b.expiry_date ASC, p.name ASC';
        return Paginator::reply($sql, $params, $in);
    }
}
