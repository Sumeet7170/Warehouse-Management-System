<?php

final class Inventory
{
    public const SELLABLE = '(b.expiry_date IS NULL OR b.expiry_date >= CURDATE())';

    public static function lockProduct(int $productId): array
    {
        $product = Database::one('SELECT id, sku, name, has_expiry FROM products WHERE id = ? FOR UPDATE', [$productId]);
        if ($product === null) {
            throw new AppException('Product not found.', 404);
        }
        return $product;
    }

    public static function receive(
        int $productId,
        int $warehouseId,
        string $batchNumber,
        ?string $expiry,
        int $qty,
        float $cost,
        string $receivedAt,
        bool $allowExpired = false
    ): array {
        if ($qty <= 0) {
            throw new AppException('Quantity must be greater than zero.', 422);
        }
        $product = self::lockProduct($productId);
        if ((int)$product['has_expiry'] === 1) {
            if ($expiry === null) {
                throw new AppException('Expiry date is required for ' . $product['name'] . '.', 422);
            }
            if (!$allowExpired && $expiry < date('Y-m-d')) {
                throw new AppException('Cannot receive expired stock for ' . $product['name'] . ' (batch ' . $batchNumber . ').', 422);
            }
        } else {
            $expiry = null;
        }
        $batch = Database::one(
            'SELECT id, quantity, expiry_date, unit_cost, received_at FROM inventory_batches WHERE product_id = ? AND warehouse_id = ? AND batch_number = ? FOR UPDATE',
            [$productId, $warehouseId, $batchNumber]
        );
        if ($batch !== null) {
            if (($batch['expiry_date'] ?? null) !== $expiry) {
                throw new AppException('Batch ' . $batchNumber . ' already exists in this warehouse with a different expiry date.', 422);
            }
            $newQty = (int)$batch['quantity'] + $qty;
            $avgCost = round(((float)$batch['unit_cost'] * (int)$batch['quantity'] + $cost * $qty) / $newQty, 2);
            $firstReceived = $batch['received_at'] < $receivedAt ? $batch['received_at'] : $receivedAt;
            Database::exec(
                'UPDATE inventory_batches SET quantity = quantity + ?, initial_quantity = initial_quantity + ?, unit_cost = ?, received_at = ? WHERE id = ?',
                [$qty, $qty, $avgCost, $firstReceived, $batch['id']]
            );
            $batchId = (int)$batch['id'];
        } else {
            $batchId = Database::insert(
                'INSERT INTO inventory_batches (product_id, warehouse_id, batch_number, expiry_date, quantity, initial_quantity, unit_cost, received_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$productId, $warehouseId, $batchNumber, $expiry, $qty, $qty, $cost, $receivedAt]
            );
        }
        $balance = (int)Database::value('SELECT quantity FROM inventory_batches WHERE id = ?', [$batchId]);
        return ['batch_id' => $batchId, 'batch_number' => $batchNumber, 'balance' => $balance];
    }

    public static function decrease(int $batchId, int $qty): int
    {
        if ($qty <= 0) {
            throw new AppException('Quantity must be greater than zero.', 422);
        }
        $changed = Database::exec(
            'UPDATE inventory_batches SET quantity = quantity - ? WHERE id = ? AND quantity >= ?',
            [$qty, $batchId, $qty]
        );
        if ($changed !== 1) {
            throw new AppException('Insufficient batch stock. Negative inventory is not allowed.', 422);
        }
        return (int)Database::value('SELECT quantity FROM inventory_batches WHERE id = ?', [$batchId]);
    }

    public static function increase(int $batchId, int $qty): int
    {
        if ($qty <= 0) {
            throw new AppException('Quantity must be greater than zero.', 422);
        }
        Database::exec('UPDATE inventory_batches SET quantity = quantity + ? WHERE id = ?', [$qty, $batchId]);
        return (int)Database::value('SELECT quantity FROM inventory_batches WHERE id = ?', [$batchId]);
    }

    public static function issueFifo(int $productId, int $warehouseId, int $qty): array
    {
        if ($qty <= 0) {
            throw new AppException('Quantity must be greater than zero.', 422);
        }
        $product = self::lockProduct($productId);
        $batches = Database::all(
            'SELECT b.id, b.batch_number, b.expiry_date, b.quantity, b.unit_cost, b.received_at FROM inventory_batches b
             WHERE b.product_id = ? AND b.warehouse_id = ? AND b.quantity > 0 AND ' . self::SELLABLE . '
             ORDER BY b.received_at ASC, b.id ASC FOR UPDATE',
            [$productId, $warehouseId]
        );
        $available = 0;
        foreach ($batches as $b) {
            $available += (int)$b['quantity'];
        }
        if ($available < $qty) {
            throw new AppException(
                sprintf('Insufficient stock for %s (%s). Available non-expired stock: %d, requested: %d.', $product['name'], $product['sku'], $available, $qty),
                422
            );
        }
        $remaining = $qty;
        $allocations = [];
        foreach ($batches as $b) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, (int)$b['quantity']);
            $balance = self::decrease((int)$b['id'], $take);
            $allocations[] = [
                'batch_id' => (int)$b['id'],
                'batch_number' => $b['batch_number'],
                'expiry_date' => $b['expiry_date'],
                'unit_cost' => $b['unit_cost'],
                'received_at' => $b['received_at'],
                'quantity' => $take,
                'balance' => $balance,
            ];
            $remaining -= $take;
        }
        return $allocations;
    }

    public static function log(
        string $type,
        int $warehouseId,
        int $productId,
        int $batchId,
        string $batchNumber,
        int $change,
        int $balance,
        string $refType,
        int $refId,
        string $refNo,
        ?string $notes = null
    ): void {
        Database::exec(
            'INSERT INTO stock_transactions (txn_date, user_id, warehouse_id, product_id, batch_id, batch_number, txn_type, quantity_change, balance_after, reference_type, reference_id, reference_no, notes)
             VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [Auth::id(), $warehouseId, $productId, $batchId, $batchNumber, $type, $change, $balance, $refType, $refId, $refNo, $notes === null ? null : mb_substr($notes, 0, 255)]
        );
    }

    public static function lowStock(?int $warehouseId = null): array
    {
        $params = [];
        $sub = 'SELECT b.product_id, SUM(b.quantity) AS qty FROM inventory_batches b WHERE ' . self::SELLABLE;
        if ($warehouseId !== null) {
            $sub .= ' AND b.warehouse_id = ?';
            $params[] = $warehouseId;
        }
        $sub .= ' GROUP BY b.product_id';
        $sql = 'SELECT p.id AS product_id, p.sku, p.name, p.unit, p.min_stock_level, c.name AS category_name, COALESCE(s.qty, 0) AS stock
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN (' . $sub . ') s ON s.product_id = p.id
                WHERE p.is_active = 1 AND p.min_stock_level > 0 AND COALESCE(s.qty, 0) < p.min_stock_level';
        if ($warehouseId !== null) {
            $sql .= ' AND EXISTS (SELECT 1 FROM inventory_batches x WHERE x.product_id = p.id AND x.warehouse_id = ?)';
            $params[] = $warehouseId;
        }
        $sql .= ' ORDER BY (p.min_stock_level - COALESCE(s.qty, 0)) DESC, p.name ASC';
        return Database::all($sql, $params);
    }

    public static function expiryCounts(): array
    {
        $days = (int)App::config('app')['expiry_alert_days'];
        $row = Database::one(
            'SELECT
                COALESCE(SUM(CASE WHEN expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL ' . $days . ' DAY) THEN 1 ELSE 0 END), 0) AS expiring,
                COALESCE(SUM(CASE WHEN expiry_date < CURDATE() THEN 1 ELSE 0 END), 0) AS expired
             FROM inventory_batches WHERE quantity > 0 AND expiry_date IS NOT NULL'
        );
        return ['expiring' => (int)$row['expiring'], 'expired' => (int)$row['expired'], 'days' => $days];
    }
}
