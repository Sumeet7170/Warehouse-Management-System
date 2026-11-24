<?php

final class VendorsModule
{
    public const READ = ['list', 'get'];
    public const ADMIN = ['delete'];
    public const OPEN = [];

    public static function list(array $in): Reply
    {
        $where = ['1=1'];
        $params = [];
        $search = trim((string)($in['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(v.name LIKE ? OR v.contact_person LIKE ? OR v.email LIKE ? OR v.phone LIKE ?)';
            $like = Paginator::like($search);
            array_push($params, $like, $like, $like, $like);
        }
        if (($in['status'] ?? '') === 'active') {
            $where[] = 'v.is_active = 1';
        } elseif (($in['status'] ?? '') === 'inactive') {
            $where[] = 'v.is_active = 0';
        }
        $sql = "SELECT v.id, v.name, v.contact_person, v.email, v.phone, v.address, v.is_active,
                (SELECT COUNT(*) FROM purchases pu WHERE pu.vendor_id = v.id AND pu.status <> 'cancelled') AS purchase_count,
                COALESCE((SELECT SUM(pu.total_amount) FROM purchases pu WHERE pu.vendor_id = v.id AND pu.status <> 'cancelled'), 0) AS total_purchased
                FROM vendors v WHERE " . implode(' AND ', $where) . ' ORDER BY v.name ASC';
        return Paginator::reply($sql, $params, $in);
    }

    public static function get(array $in): Reply
    {
        $id = V::id($in['id'] ?? null, 'Vendor');
        $vendor = Database::one('SELECT id, name, contact_person, email, phone, address, is_active FROM vendors WHERE id = ?', [$id]);
        if ($vendor === null) {
            throw new AppException('Vendor not found.', 404);
        }
        $vendor['purchases'] = Database::all(
            'SELECT pu.id, pu.purchase_no, pu.purchase_date, pu.status, pu.total_amount, w.name AS warehouse_name,
                    (SELECT COALESCE(SUM(pi.quantity), 0) FROM purchase_items pi WHERE pi.purchase_id = pu.id) AS total_qty,
                    (SELECT COALESCE(SUM(pi.received_qty), 0) FROM purchase_items pi WHERE pi.purchase_id = pu.id) AS received_qty
             FROM purchases pu JOIN warehouses w ON w.id = pu.warehouse_id
             WHERE pu.vendor_id = ? ORDER BY pu.purchase_date DESC, pu.id DESC LIMIT 100',
            [$id]
        );
        return new Reply($vendor);
    }

    public static function save(array $in): Reply
    {
        $id = isset($in['id']) && $in['id'] !== '' ? V::id($in['id'], 'Vendor') : null;
        $d = V::clean($in, [
            'name' => 'required|string|max:150',
            'contact_person' => 'string|max:120',
            'email' => 'email',
            'phone' => 'string|max:30',
            'address' => 'string|max:255',
            'is_active' => 'bool',
        ]);
        if (!array_key_exists('is_active', $in)) {
            $d['is_active'] = 1;
        }
        if ($id) {
            if (Database::value('SELECT id FROM vendors WHERE id = ?', [$id]) === null) {
                throw new AppException('Vendor not found.', 404);
            }
            Database::exec(
                'UPDATE vendors SET name = ?, contact_person = ?, email = ?, phone = ?, address = ?, is_active = ? WHERE id = ?',
                [$d['name'], $d['contact_person'], $d['email'], $d['phone'], $d['address'], $d['is_active'], $id]
            );
            Activity::log('vendor_update', 'vendor', $id, $d['name']);
            return new Reply(['id' => $id], 'Vendor updated successfully.');
        }
        $id = Database::insert(
            'INSERT INTO vendors (name, contact_person, email, phone, address, is_active) VALUES (?, ?, ?, ?, ?, ?)',
            [$d['name'], $d['contact_person'], $d['email'], $d['phone'], $d['address'], $d['is_active']]
        );
        Activity::log('vendor_create', 'vendor', $id, $d['name']);
        return new Reply(['id' => $id], 'Vendor created successfully.');
    }

    public static function delete(array $in): Reply
    {
        $id = V::id($in['id'] ?? null, 'Vendor');
        $name = Database::value('SELECT name FROM vendors WHERE id = ?', [$id]);
        if ($name === null) {
            throw new AppException('Vendor not found.', 404);
        }
        try {
            Database::exec('DELETE FROM vendors WHERE id = ?', [$id]);
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1451) {
                throw new AppException('This vendor has purchase history and cannot be deleted. Mark it inactive instead.', 409);
            }
            throw $e;
        }
        Activity::log('vendor_delete', 'vendor', $id, (string)$name);
        return new Reply(null, 'Vendor deleted successfully.');
    }
}
