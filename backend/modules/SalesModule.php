<?php

final class SalesModule
{
    public const READ = ['list', 'get'];
    public const ADMIN = [];
    public const OPEN = [];

    public static function list(array $in): Reply
    {
        $where = ['1=1'];
        $params = [];
        $search = trim((string)($in['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(s.sale_no LIKE ? OR c.name LIKE ?)';
            $like = Paginator::like($search);
            array_push($params, $like, $like);
        }
        if (!empty($in['customer_id'])) {
            $where[] = 's.customer_id = ?';
            $params[] = (int)$in['customer_id'];
        }
        if (!empty($in['warehouse_id'])) {
            $where[] = 's.warehouse_id = ?';
            $params[] = (int)$in['warehouse_id'];
        }
        if (!empty($in['from'])) {
            $where[] = 's.sale_date >= ?';
            $params[] = $in['from'];
        }
        if (!empty($in['to'])) {
            $where[] = 's.sale_date <= ?';
            $params[] = $in['to'];
        }
        $sql = 'SELECT s.id, s.sale_no, s.sale_date, s.total_amount, s.customer_id, c.name AS customer_name, s.warehouse_id, w.name AS warehouse_name, u.name AS created_by_name,
                (SELECT COALESCE(SUM(si.quantity), 0) FROM sale_items si WHERE si.sale_id = s.id) AS total_qty
                FROM sales s
                LEFT JOIN customers c ON c.id = s.customer_id
                JOIN warehouses w ON w.id = s.warehouse_id
                JOIN users u ON u.id = s.created_by
                WHERE ' . implode(' AND ', $where) . ' ORDER BY s.sale_date DESC, s.id DESC';
        return Paginator::reply($sql, $params, $in);
    }

    public static function get(array $in): Reply
    {
        $id = V::id($in['id'] ?? null, 'Sale');
        $sale = Database::one(
            'SELECT s.*, c.name AS customer_name, c.phone AS customer_phone, w.name AS warehouse_name, u.name AS created_by_name
             FROM sales s LEFT JOIN customers c ON c.id = s.customer_id JOIN warehouses w ON w.id = s.warehouse_id JOIN users u ON u.id = s.created_by
             WHERE s.id = ?',
            [$id]
        );
        if ($sale === null) {
            throw new AppException('Sale not found.', 404);
        }
        unset($sale['idempotency_key']);
        $items = Database::all(
            'SELECT si.id, si.product_id, p.sku, p.name AS product_name, p.unit, si.quantity, si.unit_price, si.line_total
             FROM sale_items si JOIN products p ON p.id = si.product_id WHERE si.sale_id = ? ORDER BY si.id ASC',
            [$id]
        );
        foreach ($items as &$item) {
            $item['batches'] = Database::all(
                'SELECT batch_number, expiry_date, quantity FROM sale_item_batches WHERE sale_item_id = ? ORDER BY id ASC',
                [$item['id']]
            );
        }
        unset($item);
        $sale['items'] = $items;
        return new Reply($sale);
    }

    public static function save(array $in): Reply
    {
        $d = V::clean($in, [
            'customer_id' => 'int|min:1',
            'warehouse_id' => 'required|int|min:1',
            'sale_date' => 'date',
            'notes' => 'string|max:500',
            'idempotency_key' => 'string|max:64',
        ], ['customer_id' => 'Customer', 'warehouse_id' => 'Warehouse', 'sale_date' => 'Sale date']);
        $d['sale_date'] = $d['sale_date'] ?? date('Y-m-d');
        if ($d['sale_date'] > date('Y-m-d')) {
            throw new ValidationException(['sale_date' => 'Sale date cannot be in the future.']);
        }
        $lines = Lines::clean($in['items'] ?? null, [
            'product_id' => 'required|int|min:1',
            'quantity' => 'required|int|min:1|max:100000000',
            'unit_price' => 'required|numeric|min:0|max:9999999999',
        ], ['product_id' => 'Product', 'unit_price' => 'Unit price']);
        $seen = [];
        foreach ($lines as $line) {
            if (isset($seen[$line['product_id']])) {
                throw new AppException('Line ' . $line['line_no'] . ': the same product is listed more than once. Combine the quantities into one line.', 422);
            }
            $seen[$line['product_id']] = true;
        }

        return Database::transaction(function () use ($d, $lines) {
            if ($d['idempotency_key'] !== null) {
                $dup = Database::one('SELECT id, sale_no FROM sales WHERE idempotency_key = ?', [$d['idempotency_key']]);
                if ($dup !== null) {
                    return new Reply(['id' => (int)$dup['id'], 'sale_no' => $dup['sale_no']], 'This sale was already saved.');
                }
            }
            Lines::activeWarehouse($d['warehouse_id']);
            if ($d['customer_id'] !== null) {
                $customer = Database::one('SELECT id, is_active FROM customers WHERE id = ?', [$d['customer_id']]);
                if ($customer === null || (int)$customer['is_active'] !== 1) {
                    throw new ValidationException(['customer_id' => 'Select an active customer.']);
                }
            }
            Lines::products(array_column($lines, 'product_id'));
            usort($lines, static fn($a, $b) => $a['product_id'] <=> $b['product_id']);
            $saleNo = Doc::next('SO');
            $saleId = Database::insert(
                'INSERT INTO sales (sale_no, customer_id, warehouse_id, sale_date, total_amount, notes, idempotency_key, created_by) VALUES (?, ?, ?, ?, 0, ?, ?, ?)',
                [$saleNo, $d['customer_id'], $d['warehouse_id'], $d['sale_date'], $d['notes'], $d['idempotency_key'], Auth::id()]
            );
            $total = 0.0;
            foreach ($lines as $line) {
                $lineTotal = round($line['quantity'] * $line['unit_price'], 2);
                $total += $lineTotal;
                $itemId = Database::insert(
                    'INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)',
                    [$saleId, $line['product_id'], $line['quantity'], $line['unit_price'], $lineTotal]
                );
                $allocations = Inventory::issueFifo($line['product_id'], $d['warehouse_id'], $line['quantity']);
                foreach ($allocations as $alloc) {
                    Database::exec(
                        'INSERT INTO sale_item_batches (sale_item_id, batch_id, batch_number, expiry_date, quantity, unit_cost) VALUES (?, ?, ?, ?, ?, ?)',
                        [$itemId, $alloc['batch_id'], $alloc['batch_number'], $alloc['expiry_date'], $alloc['quantity'], $alloc['unit_cost']]
                    );
                    Inventory::log('sale', $d['warehouse_id'], $line['product_id'], $alloc['batch_id'], $alloc['batch_number'], -$alloc['quantity'], $alloc['balance'], 'sale', $saleId, $saleNo);
                }
            }
            Database::exec('UPDATE sales SET total_amount = ? WHERE id = ?', [round($total, 2), $saleId]);
            Activity::log('sale_create', 'sale', $saleId, $saleNo . ' total ' . number_format($total, 2, '.', ''));
            return new Reply(['id' => $saleId, 'sale_no' => $saleNo, 'total_amount' => round($total, 2)], 'Sale ' . $saleNo . ' completed. Stock issued using FIFO.');
        });
    }
}
