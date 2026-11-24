<?php

final class ReportsModule
{
    public const READ = ['run', 'export'];
    public const ADMIN = [];
    public const OPEN = [];

    private const TYPES = ['stock', 'purchases', 'sales', 'transfers', 'damaged', 'movements'];

    public static function run(array $in): Reply
    {
        [$title, $columns, $sql, $params] = self::build($in);
        $page = Paginator::reply($sql, $params, $in);
        $summary = Database::one(
            'SELECT COUNT(*) AS row_count,
                    COALESCE(SUM(quantity), 0) AS total_qty,
                    COALESCE(SUM(amount), 0) AS total_amount,
                    COALESCE(SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END), 0) AS total_in,
                    COALESCE(SUM(CASE WHEN quantity < 0 THEN -quantity ELSE 0 END), 0) AS total_out
             FROM (' . $sql . ') AS report_rows',
            $params
        );
        return new Reply(['type' => $in['type'], 'title' => $title, 'columns' => $columns, 'rows' => $page->data, 'summary' => $summary], null, $page->meta);
    }

    public static function export(array $in): Reply
    {
        [$title, $columns, $sql, $params] = self::build($in);
        $rows = Database::all($sql . ' LIMIT 100000', $params);
        $filename = 'report_' . $in['type'] . '_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_map(static fn($c) => $c[1], $columns));
        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $c) {
                $value = $row[$c[0]] ?? '';
                if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
                    $value = "'" . $value;
                }
                $line[] = $value;
            }
            fputcsv($out, $line);
        }
        fclose($out);
        exit;
    }

    private static function date(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $d = DateTime::createFromFormat('!Y-m-d', $value);
        return $d && $d->format('Y-m-d') === $value ? $value : null;
    }

    private static function build(array $in): array
    {
        $type = (string)($in['type'] ?? '');
        if (!in_array($type, self::TYPES, true)) {
            throw new AppException('Unknown report type.', 422);
        }
        $from = self::date($in['from'] ?? null);
        $to = self::date($in['to'] ?? null);
        $search = trim((string)($in['search'] ?? ''));
        $warehouse = !empty($in['warehouse_id']) ? (int)$in['warehouse_id'] : null;
        $where = ['1=1'];
        $params = [];
        $like = $search !== '' ? Paginator::like($search) : null;
        $days = (int)App::config('app')['expiry_alert_days'];

        switch ($type) {
            case 'stock':
                $where[] = 'b.quantity > 0';
                if ($warehouse) {
                    $where[] = 'b.warehouse_id = ?';
                    $params[] = $warehouse;
                }
                if (!empty($in['category_id'])) {
                    $where[] = 'p.category_id = ?';
                    $params[] = (int)$in['category_id'];
                }
                if ($like) {
                    $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR b.batch_number LIKE ?)';
                    array_push($params, $like, $like, $like);
                }
                $sql = "SELECT p.sku, p.name AS product_name, c.name AS category_name, w.name AS warehouse_name, b.batch_number, b.expiry_date,
                        b.quantity AS quantity, b.unit_cost, (b.quantity * b.unit_cost) AS amount,
                        CASE WHEN b.expiry_date IS NULL THEN 'No expiry' WHEN b.expiry_date < CURDATE() THEN 'Expired'
                             WHEN b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL {$days} DAY) THEN 'Expiring soon' ELSE 'OK' END AS stock_status
                        FROM inventory_batches b JOIN products p ON p.id = b.product_id JOIN warehouses w ON w.id = b.warehouse_id
                        LEFT JOIN categories c ON c.id = p.category_id
                        WHERE " . implode(' AND ', $where) . ' ORDER BY p.name ASC, w.name ASC, b.received_at ASC, b.id ASC';
                $columns = [['sku', 'SKU', 'text'], ['product_name', 'Product', 'text'], ['category_name', 'Category', 'text'], ['warehouse_name', 'Warehouse', 'text'],
                    ['batch_number', 'Batch', 'text'], ['expiry_date', 'Expiry', 'date'], ['quantity', 'Quantity', 'int'], ['unit_cost', 'Unit cost', 'money'],
                    ['amount', 'Stock value', 'money'], ['stock_status', 'Status', 'text']];
                return ['Stock Levels Report', $columns, $sql, $params];

            case 'purchases':
                if ($from) {
                    $where[] = 'pu.purchase_date >= ?';
                    $params[] = $from;
                }
                if ($to) {
                    $where[] = 'pu.purchase_date <= ?';
                    $params[] = $to;
                }
                if ($warehouse) {
                    $where[] = 'pu.warehouse_id = ?';
                    $params[] = $warehouse;
                }
                if (!empty($in['vendor_id'])) {
                    $where[] = 'pu.vendor_id = ?';
                    $params[] = (int)$in['vendor_id'];
                }
                if (in_array($in['status'] ?? '', ['ordered', 'partial', 'received', 'cancelled'], true)) {
                    $where[] = 'pu.status = ?';
                    $params[] = $in['status'];
                } else {
                    $where[] = "pu.status <> 'cancelled'";
                }
                if ($like) {
                    $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR pu.purchase_no LIKE ? OR v.name LIKE ?)';
                    array_push($params, $like, $like, $like, $like);
                }
                $sql = 'SELECT pu.purchase_date AS txn_date, pu.purchase_no, v.name AS vendor_name, w.name AS warehouse_name, p.sku, p.name AS product_name,
                        pi.batch_number, pi.quantity AS quantity, pi.received_qty, pi.unit_cost, (pi.quantity * pi.unit_cost) AS amount, pu.status AS doc_status
                        FROM purchase_items pi JOIN purchases pu ON pu.id = pi.purchase_id JOIN vendors v ON v.id = pu.vendor_id
                        JOIN warehouses w ON w.id = pu.warehouse_id JOIN products p ON p.id = pi.product_id
                        WHERE ' . implode(' AND ', $where) . ' ORDER BY pu.purchase_date DESC, pu.id DESC, pi.id ASC';
                $columns = [['txn_date', 'Date', 'date'], ['purchase_no', 'Purchase No', 'text'], ['vendor_name', 'Vendor', 'text'], ['warehouse_name', 'Warehouse', 'text'],
                    ['sku', 'SKU', 'text'], ['product_name', 'Product', 'text'], ['batch_number', 'Batch', 'text'], ['quantity', 'Ordered', 'int'],
                    ['received_qty', 'Received', 'int'], ['unit_cost', 'Unit cost', 'money'], ['amount', 'Amount', 'money'], ['doc_status', 'Status', 'text']];
                return ['Purchase Report', $columns, $sql, $params];

            case 'sales':
                if ($from) {
                    $where[] = 's.sale_date >= ?';
                    $params[] = $from;
                }
                if ($to) {
                    $where[] = 's.sale_date <= ?';
                    $params[] = $to;
                }
                if ($warehouse) {
                    $where[] = 's.warehouse_id = ?';
                    $params[] = $warehouse;
                }
                if (!empty($in['customer_id'])) {
                    $where[] = 's.customer_id = ?';
                    $params[] = (int)$in['customer_id'];
                }
                if ($like) {
                    $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR s.sale_no LIKE ? OR c.name LIKE ?)';
                    array_push($params, $like, $like, $like, $like);
                }
                $sql = "SELECT s.sale_date AS txn_date, s.sale_no, COALESCE(c.name, 'Walk-in customer') AS customer_name, w.name AS warehouse_name, p.sku, p.name AS product_name,
                        si.quantity AS quantity, si.unit_price, si.line_total AS amount
                        FROM sale_items si JOIN sales s ON s.id = si.sale_id LEFT JOIN customers c ON c.id = s.customer_id
                        JOIN warehouses w ON w.id = s.warehouse_id JOIN products p ON p.id = si.product_id
                        WHERE " . implode(' AND ', $where) . ' ORDER BY s.sale_date DESC, s.id DESC, si.id ASC';
                $columns = [['txn_date', 'Date', 'date'], ['sale_no', 'Sale No', 'text'], ['customer_name', 'Customer', 'text'], ['warehouse_name', 'Warehouse', 'text'],
                    ['sku', 'SKU', 'text'], ['product_name', 'Product', 'text'], ['quantity', 'Quantity', 'int'], ['unit_price', 'Unit price', 'money'], ['amount', 'Amount', 'money']];
                return ['Sales Report', $columns, $sql, $params];

            case 'transfers':
                if ($from) {
                    $where[] = 't.transfer_date >= ?';
                    $params[] = $from;
                }
                if ($to) {
                    $where[] = 't.transfer_date <= ?';
                    $params[] = $to;
                }
                if ($warehouse) {
                    $where[] = '(t.from_warehouse_id = ? OR t.to_warehouse_id = ?)';
                    array_push($params, $warehouse, $warehouse);
                }
                if (in_array($in['status'] ?? '', ['pending', 'in_transit', 'completed', 'cancelled'], true)) {
                    $where[] = 't.status = ?';
                    $params[] = $in['status'];
                }
                if ($like) {
                    $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR t.transfer_no LIKE ?)';
                    array_push($params, $like, $like, $like);
                }
                $sql = 'SELECT t.transfer_date AS txn_date, t.transfer_no, wf.name AS from_warehouse, wt.name AS to_warehouse, p.sku, p.name AS product_name,
                        ti.quantity AS quantity, 0 AS amount, t.status AS doc_status
                        FROM stock_transfer_items ti JOIN stock_transfers t ON t.id = ti.transfer_id JOIN warehouses wf ON wf.id = t.from_warehouse_id
                        JOIN warehouses wt ON wt.id = t.to_warehouse_id JOIN products p ON p.id = ti.product_id
                        WHERE ' . implode(' AND ', $where) . ' ORDER BY t.transfer_date DESC, t.id DESC, ti.id ASC';
                $columns = [['txn_date', 'Date', 'date'], ['transfer_no', 'Transfer No', 'text'], ['from_warehouse', 'From', 'text'], ['to_warehouse', 'To', 'text'],
                    ['sku', 'SKU', 'text'], ['product_name', 'Product', 'text'], ['quantity', 'Quantity', 'int'], ['doc_status', 'Status', 'text']];
                return ['Stock Transfer Report', $columns, $sql, $params];

            case 'damaged':
                if ($from) {
                    $where[] = 'ds.damage_date >= ?';
                    $params[] = $from;
                }
                if ($to) {
                    $where[] = 'ds.damage_date <= ?';
                    $params[] = $to;
                }
                if ($warehouse) {
                    $where[] = 'ds.warehouse_id = ?';
                    $params[] = $warehouse;
                }
                if ($like) {
                    $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR ds.damage_no LIKE ? OR b.batch_number LIKE ? OR ds.reason LIKE ?)';
                    array_push($params, $like, $like, $like, $like, $like);
                }
                $sql = 'SELECT ds.damage_date AS txn_date, ds.damage_no, w.name AS warehouse_name, p.sku, p.name AS product_name, b.batch_number,
                        ds.quantity AS quantity, (ds.quantity * b.unit_cost) AS amount, ds.reason, u.name AS user_name
                        FROM damaged_stock ds JOIN products p ON p.id = ds.product_id JOIN warehouses w ON w.id = ds.warehouse_id
                        JOIN inventory_batches b ON b.id = ds.batch_id JOIN users u ON u.id = ds.created_by
                        WHERE ' . implode(' AND ', $where) . ' ORDER BY ds.damage_date DESC, ds.id DESC';
                $columns = [['txn_date', 'Date', 'date'], ['damage_no', 'Damage No', 'text'], ['warehouse_name', 'Warehouse', 'text'], ['sku', 'SKU', 'text'],
                    ['product_name', 'Product', 'text'], ['batch_number', 'Batch', 'text'], ['quantity', 'Quantity', 'int'], ['amount', 'Loss value', 'money'],
                    ['reason', 'Reason', 'text'], ['user_name', 'Recorded by', 'text']];
                return ['Damaged Stock Report', $columns, $sql, $params];

            default:
                if ($from) {
                    $where[] = 't.txn_date >= ?';
                    $params[] = $from . ' 00:00:00';
                }
                if ($to) {
                    $where[] = 't.txn_date <= ?';
                    $params[] = $to . ' 23:59:59';
                }
                if ($warehouse) {
                    $where[] = 't.warehouse_id = ?';
                    $params[] = $warehouse;
                }
                if (in_array($in['txn_type'] ?? '', ['purchase', 'sale', 'transfer_out', 'transfer_in', 'transfer_return', 'adjustment_in', 'adjustment_out', 'damage'], true)) {
                    $where[] = 't.txn_type = ?';
                    $params[] = $in['txn_type'];
                }
                if ($like) {
                    $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR t.batch_number LIKE ? OR t.reference_no LIKE ?)';
                    array_push($params, $like, $like, $like, $like);
                }
                $sql = 'SELECT t.txn_date, t.txn_type, p.sku, p.name AS product_name, w.name AS warehouse_name, t.batch_number, t.reference_no,
                        t.quantity_change AS quantity, t.balance_after, ABS(t.quantity_change) * b.unit_cost AS amount, u.name AS user_name
                        FROM stock_transactions t JOIN products p ON p.id = t.product_id JOIN warehouses w ON w.id = t.warehouse_id
                        JOIN inventory_batches b ON b.id = t.batch_id JOIN users u ON u.id = t.user_id
                        WHERE ' . implode(' AND ', $where) . ' ORDER BY t.id DESC';
                $columns = [['txn_date', 'Date', 'datetime'], ['txn_type', 'Type', 'type'], ['sku', 'SKU', 'text'], ['product_name', 'Product', 'text'],
                    ['warehouse_name', 'Warehouse', 'text'], ['batch_number', 'Batch', 'text'], ['reference_no', 'Reference', 'text'],
                    ['quantity', 'Qty change', 'signed'], ['balance_after', 'Balance', 'int'], ['user_name', 'User', 'text']];
                return ['Inventory Movement Report', $columns, $sql, $params];
        }
    }
}
