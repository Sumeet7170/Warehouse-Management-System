<?php

final class AdjustmentsModule
{
    public const READ = ['list'];
    public const ADMIN = [];
    public const OPEN = [];

    public static function list(array $in): Reply
    {
        $where = ['1=1'];
        $params = [];
        $search = trim((string)($in['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(a.adjustment_no LIKE ? OR p.name LIKE ? OR p.sku LIKE ? OR b.batch_number LIKE ? OR a.reason LIKE ?)';
            $like = Paginator::like($search);
            array_push($params, $like, $like, $like, $like, $like);
        }
        if (!empty($in['warehouse_id'])) {
            $where[] = 'a.warehouse_id = ?';
            $params[] = (int)$in['warehouse_id'];
        }
        if (in_array($in['type'] ?? '', ['increase', 'decrease'], true)) {
            $where[] = 'a.adjustment_type = ?';
            $params[] = $in['type'];
        }
        if (!empty($in['from'])) {
            $where[] = 'DATE(a.created_at) >= ?';
            $params[] = $in['from'];
        }
        if (!empty($in['to'])) {
            $where[] = 'DATE(a.created_at) <= ?';
            $params[] = $in['to'];
        }
        $sql = 'SELECT a.id, a.adjustment_no, a.created_at, a.adjustment_type, a.quantity, a.reason, p.sku, p.name AS product_name,
                w.name AS warehouse_name, b.batch_number, u.name AS user_name
                FROM stock_adjustments a
                JOIN products p ON p.id = a.product_id
                JOIN warehouses w ON w.id = a.warehouse_id
                JOIN inventory_batches b ON b.id = a.batch_id
                JOIN users u ON u.id = a.created_by
                WHERE ' . implode(' AND ', $where) . ' ORDER BY a.id DESC';
        return Paginator::reply($sql, $params, $in);
    }

    public static function save(array $in): Reply
    {
        $d = V::clean($in, [
            'warehouse_id' => 'required|int|min:1',
            'product_id' => 'required|int|min:1',
            'adjustment_type' => 'required|in:increase,decrease',
            'quantity' => 'required|int|min:1|max:100000000',
            'reason' => 'required|string|min:3|max:255',
            'batch_id' => 'int|min:1',
            'batch_number' => 'string|max:60',
            'expiry_date' => 'date',
            'unit_cost' => 'numeric|min:0|max:9999999999',
            'idempotency_key' => 'string|max:64',
        ], ['warehouse_id' => 'Warehouse', 'product_id' => 'Product', 'adjustment_type' => 'Adjustment type', 'batch_id' => 'Batch', 'batch_number' => 'Batch number', 'expiry_date' => 'Expiry date', 'unit_cost' => 'Unit cost']);

        return Database::transaction(function () use ($d) {
            if ($d['idempotency_key'] !== null) {
                $dup = Database::one('SELECT id, adjustment_no FROM stock_adjustments WHERE idempotency_key = ?', [$d['idempotency_key']]);
                if ($dup !== null) {
                    return new Reply(['id' => (int)$dup['id'], 'adjustment_no' => $dup['adjustment_no']], 'This adjustment was already saved.');
                }
            }
            Lines::activeWarehouse($d['warehouse_id']);
            $product = Inventory::lockProduct($d['product_id']);
            $no = Doc::next('ADJ');
            $type = $d['adjustment_type'];
            $batchId = $d['batch_id'];
            if ($batchId !== null) {
                $batch = Database::one(
                    'SELECT id, batch_number FROM inventory_batches WHERE id = ? AND product_id = ? AND warehouse_id = ? FOR UPDATE',
                    [$batchId, $d['product_id'], $d['warehouse_id']]
                );
                if ($batch === null) {
                    throw new ValidationException(['batch_id' => 'The selected batch does not belong to this product and warehouse.']);
                }
                $batchNo = $batch['batch_number'];
                $balance = $type === 'increase' ? Inventory::increase($batchId, $d['quantity']) : Inventory::decrease($batchId, $d['quantity']);
            } else {
                if ($type === 'decrease') {
                    throw new ValidationException(['batch_id' => 'Select the batch to decrease.']);
                }
                if ($d['batch_number'] === null) {
                    throw new ValidationException(['batch_number' => 'Select an existing batch or enter a new batch number.']);
                }
                $result = Inventory::receive(
                    $d['product_id'],
                    $d['warehouse_id'],
                    $d['batch_number'],
                    $d['expiry_date'],
                    $d['quantity'],
                    $d['unit_cost'] ?? 0.0,
                    date('Y-m-d H:i:s')
                );
                $batchId = $result['batch_id'];
                $batchNo = $result['batch_number'];
                $balance = $result['balance'];
            }
            $id = Database::insert(
                'INSERT INTO stock_adjustments (adjustment_no, warehouse_id, product_id, batch_id, adjustment_type, quantity, reason, idempotency_key, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$no, $d['warehouse_id'], $d['product_id'], $batchId, $type, $d['quantity'], $d['reason'], $d['idempotency_key'], Auth::id()]
            );
            Inventory::log(
                $type === 'increase' ? 'adjustment_in' : 'adjustment_out',
                $d['warehouse_id'],
                $d['product_id'],
                $batchId,
                $batchNo,
                $type === 'increase' ? $d['quantity'] : -$d['quantity'],
                $balance,
                'adjustment',
                $id,
                $no,
                $d['reason']
            );
            Activity::log('stock_adjustment', 'adjustment', $id, $no . ' ' . $type . ' ' . $d['quantity'] . ' x ' . $product['sku']);
            return new Reply(['id' => $id, 'adjustment_no' => $no, 'balance' => $balance], 'Stock adjustment ' . $no . ' recorded. New batch balance: ' . $balance . '.');
        });
    }
}
