<?php

final class DamagedModule
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
            $where[] = '(ds.damage_no LIKE ? OR p.name LIKE ? OR p.sku LIKE ? OR b.batch_number LIKE ? OR ds.reason LIKE ?)';
            $like = Paginator::like($search);
            array_push($params, $like, $like, $like, $like, $like);
        }
        if (!empty($in['warehouse_id'])) {
            $where[] = 'ds.warehouse_id = ?';
            $params[] = (int)$in['warehouse_id'];
        }
        if (!empty($in['from'])) {
            $where[] = 'ds.damage_date >= ?';
            $params[] = $in['from'];
        }
        if (!empty($in['to'])) {
            $where[] = 'ds.damage_date <= ?';
            $params[] = $in['to'];
        }
        $sql = 'SELECT ds.id, ds.damage_no, ds.damage_date, ds.quantity, ds.reason, p.sku, p.name AS product_name, w.name AS warehouse_name,
                b.batch_number, b.unit_cost, (ds.quantity * b.unit_cost) AS loss_value, u.name AS user_name
                FROM damaged_stock ds
                JOIN products p ON p.id = ds.product_id
                JOIN warehouses w ON w.id = ds.warehouse_id
                JOIN inventory_batches b ON b.id = ds.batch_id
                JOIN users u ON u.id = ds.created_by
                WHERE ' . implode(' AND ', $where) . ' ORDER BY ds.id DESC';
        return Paginator::reply($sql, $params, $in);
    }

    public static function save(array $in): Reply
    {
        $d = V::clean($in, [
            'warehouse_id' => 'required|int|min:1',
            'product_id' => 'required|int|min:1',
            'batch_id' => 'required|int|min:1',
            'quantity' => 'required|int|min:1|max:100000000',
            'reason' => 'required|string|min:3|max:255',
            'damage_date' => 'date',
            'idempotency_key' => 'string|max:64',
        ], ['warehouse_id' => 'Warehouse', 'product_id' => 'Product', 'batch_id' => 'Batch', 'damage_date' => 'Damage date']);
        $d['damage_date'] = $d['damage_date'] ?? date('Y-m-d');
        if ($d['damage_date'] > date('Y-m-d')) {
            throw new ValidationException(['damage_date' => 'Damage date cannot be in the future.']);
        }

        return Database::transaction(function () use ($d) {
            if ($d['idempotency_key'] !== null) {
                $dup = Database::one('SELECT id, damage_no FROM damaged_stock WHERE idempotency_key = ?', [$d['idempotency_key']]);
                if ($dup !== null) {
                    return new Reply(['id' => (int)$dup['id'], 'damage_no' => $dup['damage_no']], 'This damage record was already saved.');
                }
            }
            Lines::activeWarehouse($d['warehouse_id']);
            $product = Inventory::lockProduct($d['product_id']);
            $batch = Database::one(
                'SELECT id, batch_number FROM inventory_batches WHERE id = ? AND product_id = ? AND warehouse_id = ? FOR UPDATE',
                [$d['batch_id'], $d['product_id'], $d['warehouse_id']]
            );
            if ($batch === null) {
                throw new ValidationException(['batch_id' => 'The selected batch does not belong to this product and warehouse.']);
            }
            $no = Doc::next('DMG');
            $balance = Inventory::decrease($d['batch_id'], $d['quantity']);
            $id = Database::insert(
                'INSERT INTO damaged_stock (damage_no, warehouse_id, product_id, batch_id, quantity, reason, damage_date, idempotency_key, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$no, $d['warehouse_id'], $d['product_id'], $d['batch_id'], $d['quantity'], $d['reason'], $d['damage_date'], $d['idempotency_key'], Auth::id()]
            );
            Inventory::log('damage', $d['warehouse_id'], $d['product_id'], $d['batch_id'], $batch['batch_number'], -$d['quantity'], $balance, 'damage', $id, $no, $d['reason']);
            Activity::log('damaged_stock', 'damage', $id, $no . ' ' . $d['quantity'] . ' x ' . $product['sku']);
            return new Reply(['id' => $id, 'damage_no' => $no, 'balance' => $balance], 'Damaged stock ' . $no . ' recorded and removed from sellable inventory.');
        });
    }
}
