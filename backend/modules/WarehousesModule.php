<?php

final class WarehousesModule
{
    public const READ = ['list'];
    public const ADMIN = ['save', 'delete'];
    public const OPEN = [];

    public static function list(array $in): Reply
    {
        $where = ['1=1'];
        $params = [];
        $search = trim((string)($in['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(w.name LIKE ? OR w.code LIKE ? OR w.address LIKE ?)';
            $like = Paginator::like($search);
            array_push($params, $like, $like, $like);
        }
        if (($in['status'] ?? '') === 'active') {
            $where[] = 'w.is_active = 1';
        } elseif (($in['status'] ?? '') === 'inactive') {
            $where[] = 'w.is_active = 0';
        }
        $sql = 'SELECT w.id, w.code, w.name, w.address, w.phone, w.is_active,
                COALESCE((SELECT SUM(b.quantity) FROM inventory_batches b WHERE b.warehouse_id = w.id), 0) AS total_units,
                COALESCE((SELECT COUNT(DISTINCT b.product_id) FROM inventory_batches b WHERE b.warehouse_id = w.id AND b.quantity > 0), 0) AS product_count
                FROM warehouses w WHERE ' . implode(' AND ', $where) . ' ORDER BY w.name ASC';
        return Paginator::reply($sql, $params, $in);
    }

    public static function save(array $in): Reply
    {
        $id = isset($in['id']) && $in['id'] !== '' ? V::id($in['id'], 'Warehouse') : null;
        $d = V::clean($in, [
            'code' => 'required|string|max:20',
            'name' => 'required|string|max:120',
            'address' => 'string|max:255',
            'phone' => 'string|max:30',
            'is_active' => 'bool',
        ]);
        if (!array_key_exists('is_active', $in)) {
            $d['is_active'] = 1;
        }
        $dup = Database::value('SELECT id FROM warehouses WHERE code = ?' . ($id ? ' AND id <> ?' : ''), $id ? [$d['code'], $id] : [$d['code']]);
        if ($dup) {
            throw new ValidationException(['code' => 'This warehouse code already exists.']);
        }
        if ($id) {
            if (Database::value('SELECT id FROM warehouses WHERE id = ?', [$id]) === null) {
                throw new AppException('Warehouse not found.', 404);
            }
            if ((int)$d['is_active'] === 0) {
                $units = (int)Database::value('SELECT COALESCE(SUM(quantity), 0) FROM inventory_batches WHERE warehouse_id = ?', [$id]);
                $open = (int)Database::value("SELECT COUNT(*) FROM stock_transfers WHERE status IN ('pending','in_transit') AND (from_warehouse_id = ? OR to_warehouse_id = ?)", [$id, $id]);
                if ($units > 0 || $open > 0) {
                    throw new AppException('A warehouse with stock or open transfers cannot be deactivated.', 409);
                }
            }
            Database::exec(
                'UPDATE warehouses SET code = ?, name = ?, address = ?, phone = ?, is_active = ? WHERE id = ?',
                [$d['code'], $d['name'], $d['address'], $d['phone'], $d['is_active'], $id]
            );
            Activity::log('warehouse_update', 'warehouse', $id, $d['name']);
            return new Reply(['id' => $id], 'Warehouse updated successfully.');
        }
        $id = Database::insert(
            'INSERT INTO warehouses (code, name, address, phone, is_active) VALUES (?, ?, ?, ?, ?)',
            [$d['code'], $d['name'], $d['address'], $d['phone'], $d['is_active']]
        );
        Activity::log('warehouse_create', 'warehouse', $id, $d['name']);
        return new Reply(['id' => $id], 'Warehouse created successfully.');
    }

    public static function delete(array $in): Reply
    {
        $id = V::id($in['id'] ?? null, 'Warehouse');
        $name = Database::value('SELECT name FROM warehouses WHERE id = ?', [$id]);
        if ($name === null) {
            throw new AppException('Warehouse not found.', 404);
        }
        try {
            Database::exec('DELETE FROM warehouses WHERE id = ?', [$id]);
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1451) {
                throw new AppException('This warehouse has stock or transaction history and cannot be deleted. Mark it inactive instead.', 409);
            }
            throw $e;
        }
        Activity::log('warehouse_delete', 'warehouse', $id, (string)$name);
        return new Reply(null, 'Warehouse deleted successfully.');
    }
}
