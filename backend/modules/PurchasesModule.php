<?php

final class PurchasesModule
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
            $where[] = '(pu.purchase_no LIKE ? OR v.name LIKE ?)';
            $like = Paginator::like($search);
            array_push($params, $like, $like);
        }
        if (in_array($in['status'] ?? '', ['ordered', 'partial', 'received', 'cancelled'], true)) {
            $where[] = 'pu.status = ?';
            $params[] = $in['status'];
        }
        if (!empty($in['vendor_id'])) {
            $where[] = 'pu.vendor_id = ?';
            $params[] = (int)$in['vendor_id'];
        }
        if (!empty($in['warehouse_id'])) {
            $where[] = 'pu.warehouse_id = ?';
            $params[] = (int)$in['warehouse_id'];
        }
        if (!empty($in['from'])) {
            $where[] = 'pu.purchase_date >= ?';
            $params[] = $in['from'];
        }
        if (!empty($in['to'])) {
            $where[] = 'pu.purchase_date <= ?';
            $params[] = $in['to'];
        }
        $sql = 'SELECT pu.id, pu.purchase_no, pu.purchase_date, pu.status, pu.total_amount, pu.vendor_id, v.name AS vendor_name,
                pu.warehouse_id, w.name AS warehouse_name, u.name AS created_by_name,
                (SELECT COALESCE(SUM(pi.quantity), 0) FROM purchase_items pi WHERE pi.purchase_id = pu.id) AS total_qty,
                (SELECT COALESCE(SUM(pi.received_qty), 0) FROM purchase_items pi WHERE pi.purchase_id = pu.id) AS received_qty
                FROM purchases pu
                JOIN vendors v ON v.id = pu.vendor_id
                JOIN warehouses w ON w.id = pu.warehouse_id
                JOIN users u ON u.id = pu.created_by
                WHERE ' . implode(' AND ', $where) . ' ORDER BY pu.purchase_date DESC, pu.id DESC';
        return Paginator::reply($sql, $params, $in);
    }

    public static function get(array $in): Reply
    {
        $id = V::id($in['id'] ?? null, 'Purchase');
        $purchase = Database::one(
            'SELECT pu.*, v.name AS vendor_name, w.name AS warehouse_name, u.name AS created_by_name
             FROM purchases pu JOIN vendors v ON v.id = pu.vendor_id JOIN warehouses w ON w.id = pu.warehouse_id JOIN users u ON u.id = pu.created_by
             WHERE pu.id = ?',
            [$id]
        );
        if ($purchase === null) {
            throw new AppException('Purchase not found.', 404);
        }
        unset($purchase['idempotency_key']);
        $purchase['items'] = Database::all(
            'SELECT pi.id, pi.product_id, p.sku, p.name AS product_name, p.unit, pi.quantity, pi.received_qty, pi.unit_cost, pi.batch_number, pi.expiry_date,
                    (pi.quantity * pi.unit_cost) AS line_total
             FROM purchase_items pi JOIN products p ON p.id = pi.product_id WHERE pi.purchase_id = ? ORDER BY pi.id ASC',
            [$id]
        );
        return new Reply($purchase);
    }

    public static function save(array $in): Reply
    {
        $d = V::clean($in, [
            'vendor_id' => 'required|int|min:1',
            'warehouse_id' => 'required|int|min:1',
            'purchase_date' => 'required|date',
            'notes' => 'string|max:500',
            'idempotency_key' => 'string|max:64',
        ], ['vendor_id' => 'Vendor', 'warehouse_id' => 'Warehouse', 'purchase_date' => 'Purchase date']);
        $lines = Lines::clean($in['items'] ?? null, [
            'product_id' => 'required|int|min:1',
            'quantity' => 'required|int|min:1|max:100000000',
            'unit_cost' => 'required|numeric|min:0|max:9999999999',
            'batch_number' => 'string|max:60',
            'expiry_date' => 'date',
        ], ['product_id' => 'Product', 'unit_cost' => 'Unit cost', 'batch_number' => 'Batch number', 'expiry_date' => 'Expiry date']);
        $receiveNow = filter_var($in['receive_now'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $seen = [];
        foreach ($lines as $line) {
            if ($line['batch_number'] !== null) {
                $key = $line['product_id'] . '|' . mb_strtolower($line['batch_number']);
                if (isset($seen[$key])) {
                    throw new AppException('Line ' . $line['line_no'] . ': the same product and batch number appear more than once.', 422);
                }
                $seen[$key] = true;
            }
        }

        return Database::transaction(function () use ($d, $lines, $receiveNow) {
            if ($d['idempotency_key'] !== null) {
                $dup = Database::one('SELECT id, purchase_no FROM purchases WHERE idempotency_key = ?', [$d['idempotency_key']]);
                if ($dup !== null) {
                    return new Reply(['id' => (int)$dup['id'], 'purchase_no' => $dup['purchase_no']], 'This purchase was already saved.');
                }
            }
            $vendor = Database::one('SELECT id, name, is_active FROM vendors WHERE id = ?', [$d['vendor_id']]);
            if ($vendor === null || (int)$vendor['is_active'] !== 1) {
                throw new ValidationException(['vendor_id' => 'Select an active vendor.']);
            }
            Lines::activeWarehouse($d['warehouse_id']);
            $products = Lines::products(array_column($lines, 'product_id'));
            usort($lines, static fn($a, $b) => [$a['product_id'], $a['line_no']] <=> [$b['product_id'], $b['line_no']]);
            $today = date('Y-m-d');
            foreach ($lines as $line) {
                $product = $products[$line['product_id']];
                if ((int)$product['has_expiry'] === 1) {
                    if ($line['expiry_date'] === null) {
                        throw new AppException('Line ' . $line['line_no'] . ': expiry date is required for ' . $product['name'] . '.', 422);
                    }
                    if ($line['expiry_date'] < $today) {
                        throw new AppException('Line ' . $line['line_no'] . ': expiry date is in the past for ' . $product['name'] . '.', 422);
                    }
                }
            }
            $purchaseNo = Doc::next('PO');
            $total = 0.0;
            foreach ($lines as $line) {
                $total += round($line['quantity'] * $line['unit_cost'], 2);
            }
            $purchaseId = Database::insert(
                "INSERT INTO purchases (purchase_no, vendor_id, warehouse_id, purchase_date, status, total_amount, notes, idempotency_key, created_by) VALUES (?, ?, ?, ?, 'ordered', ?, ?, ?, ?)",
                [$purchaseNo, $d['vendor_id'], $d['warehouse_id'], $d['purchase_date'], round($total, 2), $d['notes'], $d['idempotency_key'], Auth::id()]
            );
            foreach ($lines as $line) {
                $batch = $line['batch_number'] ?? ($purchaseNo . '-' . $line['line_no']);
                $expiry = (int)$products[$line['product_id']]['has_expiry'] === 1 ? $line['expiry_date'] : null;
                Database::exec(
                    'INSERT INTO purchase_items (purchase_id, product_id, quantity, received_qty, unit_cost, batch_number, expiry_date) VALUES (?, ?, ?, 0, ?, ?, ?)',
                    [$purchaseId, $line['product_id'], $line['quantity'], $line['unit_cost'], $batch, $expiry]
                );
            }
            Activity::log('purchase_create', 'purchase', $purchaseId, $purchaseNo . ' total ' . number_format($total, 2, '.', ''));
            $message = 'Purchase ' . $purchaseNo . ' created successfully.';
            if ($receiveNow) {
                self::receiveItems($purchaseId, null);
                $message = 'Purchase ' . $purchaseNo . ' created and stock received successfully.';
            }
            return new Reply(['id' => $purchaseId, 'purchase_no' => $purchaseNo], $message);
        });
    }

    public static function receive(array $in): Reply
    {
        $id = V::id($in['id'] ?? null, 'Purchase');
        $map = null;
        if (isset($in['items'])) {
            if (!is_array($in['items'])) {
                throw new AppException('Items must be a list.', 422);
            }
            $map = [];
            foreach ($in['items'] as $row) {
                $itemId = V::id($row['item_id'] ?? null, 'Purchase item');
                $qty = filter_var($row['quantity'] ?? 0, FILTER_VALIDATE_INT);
                if ($qty === false || $qty < 0) {
                    throw new AppException('Receive quantity must be a whole number of zero or more.', 422);
                }
                $map[$itemId] = $qty;
            }
        }
        return Database::transaction(function () use ($id, $map) {
            $info = self::receiveItems($id, $map);
            return new Reply(['id' => $id, 'status' => $info['status']], 'Stock received successfully for ' . $info['purchase_no'] . '.');
        });
    }

    private static function receiveItems(int $purchaseId, ?array $map): array
    {
        $purchase = Database::one('SELECT id, purchase_no, warehouse_id, status FROM purchases WHERE id = ? FOR UPDATE', [$purchaseId]);
        if ($purchase === null) {
            throw new AppException('Purchase not found.', 404);
        }
        if (!in_array($purchase['status'], ['ordered', 'partial'], true)) {
            throw new AppException('This purchase is ' . $purchase['status'] . ' and cannot receive more stock.', 409);
        }
        $items = Database::all(
            'SELECT id, product_id, quantity, received_qty, unit_cost, batch_number, expiry_date FROM purchase_items WHERE purchase_id = ? ORDER BY product_id ASC, id ASC FOR UPDATE',
            [$purchaseId]
        );
        if ($map !== null) {
            $valid = array_column($items, 'id');
            foreach (array_keys($map) as $itemId) {
                if (!in_array($itemId, $valid, true)) {
                    throw new AppException('A purchase item does not belong to this purchase.', 422);
                }
            }
        }
        $receivedAny = false;
        $now = date('Y-m-d H:i:s');
        foreach ($items as $item) {
            $remaining = (int)$item['quantity'] - (int)$item['received_qty'];
            $take = $map === null ? $remaining : ($map[(int)$item['id']] ?? 0);
            if ($take > $remaining) {
                throw new AppException('Cannot receive more than the outstanding quantity (' . $remaining . ') for batch ' . $item['batch_number'] . '.', 422);
            }
            if ($take <= 0) {
                continue;
            }
            $result = Inventory::receive(
                (int)$item['product_id'],
                (int)$purchase['warehouse_id'],
                $item['batch_number'],
                $item['expiry_date'],
                $take,
                (float)$item['unit_cost'],
                $now
            );
            Database::exec('UPDATE purchase_items SET received_qty = received_qty + ? WHERE id = ?', [$take, $item['id']]);
            Inventory::log('purchase', (int)$purchase['warehouse_id'], (int)$item['product_id'], $result['batch_id'], $item['batch_number'], $take, $result['balance'], 'purchase', $purchaseId, $purchase['purchase_no']);
            $receivedAny = true;
        }
        if (!$receivedAny) {
            throw new AppException('Enter a quantity to receive.', 422);
        }
        $outstanding = (int)Database::value('SELECT COALESCE(SUM(quantity - received_qty), 0) FROM purchase_items WHERE purchase_id = ?', [$purchaseId]);
        $status = $outstanding === 0 ? 'received' : 'partial';
        Database::exec('UPDATE purchases SET status = ?, received_at = NOW() WHERE id = ?', [$status, $purchaseId]);
        Activity::log('purchase_receive', 'purchase', $purchaseId, $purchase['purchase_no'] . ' -> ' . $status);
        return ['status' => $status, 'purchase_no' => $purchase['purchase_no']];
    }

    public static function cancel(array $in): Reply
    {
        $id = V::id($in['id'] ?? null, 'Purchase');
        return Database::transaction(function () use ($id) {
            $purchase = Database::one('SELECT id, purchase_no, status FROM purchases WHERE id = ? FOR UPDATE', [$id]);
            if ($purchase === null) {
                throw new AppException('Purchase not found.', 404);
            }
            if ($purchase['status'] !== 'ordered') {
                throw new AppException('Only purchases with no received stock can be cancelled.', 409);
            }
            Database::exec("UPDATE purchases SET status = 'cancelled' WHERE id = ?", [$id]);
            Activity::log('purchase_cancel', 'purchase', $id, $purchase['purchase_no']);
            return new Reply(null, 'Purchase ' . $purchase['purchase_no'] . ' cancelled.');
        });
    }
}
