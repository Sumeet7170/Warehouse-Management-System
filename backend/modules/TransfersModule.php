<?php

final class TransfersModule
{
    public const READ = ['list', 'get'];
    public const ADMIN = ['cancel'];
    public const OPEN = [];

    public static function list(array $in): Reply
    {
        $where = ['1=1'];
        $params = [];
        $search = trim((string)($in['search'] ?? ''));
        if ($search !== '') {
            $where[] = 't.transfer_no LIKE ?';
            $params[] = Paginator::like($search);
        }
        if (in_array($in['status'] ?? '', ['pending', 'in_transit', 'completed', 'cancelled'], true)) {
            $where[] = 't.status = ?';
            $params[] = $in['status'];
        }
        if (!empty($in['from_warehouse_id'])) {
            $where[] = 't.from_warehouse_id = ?';
            $params[] = (int)$in['from_warehouse_id'];
        }
        if (!empty($in['to_warehouse_id'])) {
            $where[] = 't.to_warehouse_id = ?';
            $params[] = (int)$in['to_warehouse_id'];
        }
        if (!empty($in['from'])) {
            $where[] = 't.transfer_date >= ?';
            $params[] = $in['from'];
        }
        if (!empty($in['to'])) {
            $where[] = 't.transfer_date <= ?';
            $params[] = $in['to'];
        }
        $sql = 'SELECT t.id, t.transfer_no, t.transfer_date, t.status, t.from_warehouse_id, wf.name AS from_warehouse_name,
                t.to_warehouse_id, wt.name AS to_warehouse_name, u.name AS created_by_name,
                (SELECT COALESCE(SUM(ti.quantity), 0) FROM stock_transfer_items ti WHERE ti.transfer_id = t.id) AS total_qty,
                (SELECT COUNT(*) FROM stock_transfer_items ti WHERE ti.transfer_id = t.id) AS item_count
                FROM stock_transfers t
                JOIN warehouses wf ON wf.id = t.from_warehouse_id
                JOIN warehouses wt ON wt.id = t.to_warehouse_id
                JOIN users u ON u.id = t.created_by
                WHERE ' . implode(' AND ', $where) . ' ORDER BY t.id DESC';
        return Paginator::reply($sql, $params, $in);
    }

    public static function get(array $in): Reply
    {
        $id = V::id($in['id'] ?? null, 'Transfer');
        $transfer = Database::one(
            'SELECT t.*, wf.name AS from_warehouse_name, wt.name AS to_warehouse_name, u.name AS created_by_name
             FROM stock_transfers t JOIN warehouses wf ON wf.id = t.from_warehouse_id JOIN warehouses wt ON wt.id = t.to_warehouse_id JOIN users u ON u.id = t.created_by
             WHERE t.id = ?',
            [$id]
        );
        if ($transfer === null) {
            throw new AppException('Transfer not found.', 404);
        }
        unset($transfer['idempotency_key']);
        $items = Database::all(
            'SELECT ti.id, ti.product_id, p.sku, p.name AS product_name, p.unit, ti.quantity
             FROM stock_transfer_items ti JOIN products p ON p.id = ti.product_id WHERE ti.transfer_id = ? ORDER BY ti.id ASC',
            [$id]
        );
        foreach ($items as &$item) {
            $item['batches'] = Database::all(
                'SELECT batch_number, expiry_date, quantity FROM stock_transfer_batches WHERE transfer_item_id = ? ORDER BY id ASC',
                [$item['id']]
            );
        }
        unset($item);
        $transfer['items'] = $items;
        return new Reply($transfer);
    }

    public static function save(array $in): Reply
    {
        $d = V::clean($in, [
            'from_warehouse_id' => 'required|int|min:1',
            'to_warehouse_id' => 'required|int|min:1',
            'transfer_date' => 'date',
            'notes' => 'string|max:500',
            'idempotency_key' => 'string|max:64',
        ], ['from_warehouse_id' => 'Source warehouse', 'to_warehouse_id' => 'Destination warehouse', 'transfer_date' => 'Transfer date']);
        if ($d['from_warehouse_id'] === $d['to_warehouse_id']) {
            throw new ValidationException(['to_warehouse_id' => 'Source and destination warehouses must be different.']);
        }
        $d['transfer_date'] = $d['transfer_date'] ?? date('Y-m-d');
        $lines = Lines::clean($in['items'] ?? null, [
            'product_id' => 'required|int|min:1',
            'quantity' => 'required|int|min:1|max:100000000',
        ], ['product_id' => 'Product']);
        $seen = [];
        foreach ($lines as $line) {
            if (isset($seen[$line['product_id']])) {
                throw new AppException('Line ' . $line['line_no'] . ': the same product is listed more than once. Combine the quantities into one line.', 422);
            }
            $seen[$line['product_id']] = true;
        }

        return Database::transaction(function () use ($d, $lines) {
            if ($d['idempotency_key'] !== null) {
                $dup = Database::one('SELECT id, transfer_no FROM stock_transfers WHERE idempotency_key = ?', [$d['idempotency_key']]);
                if ($dup !== null) {
                    return new Reply(['id' => (int)$dup['id'], 'transfer_no' => $dup['transfer_no']], 'This transfer was already saved.');
                }
            }
            Lines::activeWarehouse($d['from_warehouse_id'], 'Source warehouse');
            Lines::activeWarehouse($d['to_warehouse_id'], 'Destination warehouse');
            $products = Lines::products(array_column($lines, 'product_id'));
            foreach ($lines as $line) {
                $available = (int)Database::value(
                    'SELECT COALESCE(SUM(b.quantity), 0) FROM inventory_batches b WHERE b.product_id = ? AND b.warehouse_id = ? AND ' . Inventory::SELLABLE,
                    [$line['product_id'], $d['from_warehouse_id']]
                );
                if ($available < $line['quantity']) {
                    $p = $products[$line['product_id']];
                    throw new AppException(sprintf('Insufficient stock for %s (%s) in the source warehouse. Available non-expired stock: %d, requested: %d.', $p['name'], $p['sku'], $available, $line['quantity']), 422);
                }
            }
            usort($lines, static fn($a, $b) => $a['product_id'] <=> $b['product_id']);
            $no = Doc::next('TR');
            $id = Database::insert(
                "INSERT INTO stock_transfers (transfer_no, from_warehouse_id, to_warehouse_id, status, transfer_date, notes, idempotency_key, created_by) VALUES (?, ?, ?, 'pending', ?, ?, ?, ?)",
                [$no, $d['from_warehouse_id'], $d['to_warehouse_id'], $d['transfer_date'], $d['notes'], $d['idempotency_key'], Auth::id()]
            );
            foreach ($lines as $line) {
                Database::exec('INSERT INTO stock_transfer_items (transfer_id, product_id, quantity) VALUES (?, ?, ?)', [$id, $line['product_id'], $line['quantity']]);
            }
            Activity::log('transfer_create', 'transfer', $id, $no);
            return new Reply(['id' => $id, 'transfer_no' => $no], 'Transfer ' . $no . ' created. Dispatch it to move stock out of the source warehouse.');
        });
    }

    public static function dispatch(array $in): Reply
    {
        $id = V::id($in['id'] ?? null, 'Transfer');
        return Database::transaction(function () use ($id) {
            $t = Database::one('SELECT id, transfer_no, from_warehouse_id, status FROM stock_transfers WHERE id = ? FOR UPDATE', [$id]);
            if ($t === null) {
                throw new AppException('Transfer not found.', 404);
            }
            if ($t['status'] !== 'pending') {
                throw new AppException('Only pending transfers can be dispatched. This transfer is ' . str_replace('_', ' ', $t['status']) . '.', 409);
            }
            $items = Database::all('SELECT id, product_id, quantity FROM stock_transfer_items WHERE transfer_id = ? ORDER BY product_id ASC, id ASC', [$id]);
            foreach ($items as $item) {
                $allocations = Inventory::issueFifo((int)$item['product_id'], (int)$t['from_warehouse_id'], (int)$item['quantity']);
                foreach ($allocations as $a) {
                    Database::exec(
                        'INSERT INTO stock_transfer_batches (transfer_item_id, source_batch_id, batch_number, expiry_date, quantity, unit_cost, original_received_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                        [$item['id'], $a['batch_id'], $a['batch_number'], $a['expiry_date'], $a['quantity'], $a['unit_cost'], $a['received_at']]
                    );
                    Inventory::log('transfer_out', (int)$t['from_warehouse_id'], (int)$item['product_id'], $a['batch_id'], $a['batch_number'], -$a['quantity'], $a['balance'], 'transfer', $id, $t['transfer_no']);
                }
            }
            Database::exec("UPDATE stock_transfers SET status = 'in_transit', dispatched_at = NOW() WHERE id = ?", [$id]);
            Activity::log('transfer_dispatch', 'transfer', $id, $t['transfer_no']);
            return new Reply(['id' => $id, 'status' => 'in_transit'], 'Transfer ' . $t['transfer_no'] . ' dispatched. Stock deducted from the source warehouse (FIFO).');
        });
    }

    public static function receive(array $in): Reply
    {
        $id = V::id($in['id'] ?? null, 'Transfer');
        return Database::transaction(function () use ($id) {
            $t = Database::one('SELECT id, transfer_no, to_warehouse_id, status FROM stock_transfers WHERE id = ? FOR UPDATE', [$id]);
            if ($t === null) {
                throw new AppException('Transfer not found.', 404);
            }
            if ($t['status'] !== 'in_transit') {
                throw new AppException('Only in-transit transfers can be received. This transfer is ' . str_replace('_', ' ', $t['status']) . '.', 409);
            }
            $rows = Database::all(
                'SELECT ti.product_id, tb.batch_number, tb.expiry_date, tb.quantity, tb.unit_cost, tb.original_received_at
                 FROM stock_transfer_batches tb JOIN stock_transfer_items ti ON ti.id = tb.transfer_item_id
                 WHERE ti.transfer_id = ? ORDER BY ti.product_id ASC, tb.id ASC',
                [$id]
            );
            foreach ($rows as $r) {
                $res = Inventory::receive((int)$r['product_id'], (int)$t['to_warehouse_id'], $r['batch_number'], $r['expiry_date'], (int)$r['quantity'], (float)$r['unit_cost'], $r['original_received_at'], true);
                Inventory::log('transfer_in', (int)$t['to_warehouse_id'], (int)$r['product_id'], $res['batch_id'], $r['batch_number'], (int)$r['quantity'], $res['balance'], 'transfer', $id, $t['transfer_no']);
            }
            Database::exec("UPDATE stock_transfers SET status = 'completed', received_at = NOW() WHERE id = ?", [$id]);
            Activity::log('transfer_receive', 'transfer', $id, $t['transfer_no']);
            return new Reply(['id' => $id, 'status' => 'completed'], 'Transfer ' . $t['transfer_no'] . ' received. Stock added to the destination warehouse.');
        });
    }

    public static function cancel(array $in): Reply
    {
        $id = V::id($in['id'] ?? null, 'Transfer');
        return Database::transaction(function () use ($id) {
            $t = Database::one('SELECT id, transfer_no, from_warehouse_id, status FROM stock_transfers WHERE id = ? FOR UPDATE', [$id]);
            if ($t === null) {
                throw new AppException('Transfer not found.', 404);
            }
            if (!in_array($t['status'], ['pending', 'in_transit'], true)) {
                throw new AppException('Only pending or in-transit transfers can be cancelled.', 409);
            }
            if ($t['status'] === 'in_transit') {
                $rows = Database::all(
                    'SELECT ti.product_id, tb.batch_number, tb.expiry_date, tb.quantity, tb.unit_cost, tb.original_received_at
                     FROM stock_transfer_batches tb JOIN stock_transfer_items ti ON ti.id = tb.transfer_item_id
                     WHERE ti.transfer_id = ? ORDER BY ti.product_id ASC, tb.id ASC',
                    [$id]
                );
                foreach ($rows as $r) {
                    $res = Inventory::receive((int)$r['product_id'], (int)$t['from_warehouse_id'], $r['batch_number'], $r['expiry_date'], (int)$r['quantity'], (float)$r['unit_cost'], $r['original_received_at'], true);
                    Inventory::log('transfer_return', (int)$t['from_warehouse_id'], (int)$r['product_id'], $res['batch_id'], $r['batch_number'], (int)$r['quantity'], $res['balance'], 'transfer', $id, $t['transfer_no'], 'Transfer cancelled, stock returned');
                }
            }
            Database::exec("UPDATE stock_transfers SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?", [$id]);
            Activity::log('transfer_cancel', 'transfer', $id, $t['transfer_no']);
            return new Reply(['id' => $id, 'status' => 'cancelled'], 'Transfer ' . $t['transfer_no'] . ' cancelled.' . ($t['status'] === 'in_transit' ? ' Stock returned to the source warehouse.' : ''));
        });
    }
}
