<?php

final class CustomersModule
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
            $where[] = '(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)';
            $like = Paginator::like($search);
            array_push($params, $like, $like, $like);
        }
        if (($in['status'] ?? '') === 'active') {
            $where[] = 'c.is_active = 1';
        } elseif (($in['status'] ?? '') === 'inactive') {
            $where[] = 'c.is_active = 0';
        }
        $sql = 'SELECT c.id, c.name, c.email, c.phone, c.address, c.is_active,
                (SELECT COUNT(*) FROM sales s WHERE s.customer_id = c.id) AS sale_count,
                COALESCE((SELECT SUM(s.total_amount) FROM sales s WHERE s.customer_id = c.id), 0) AS total_sales
                FROM customers c WHERE ' . implode(' AND ', $where) . ' ORDER BY c.name ASC';
        return Paginator::reply($sql, $params, $in);
    }

    public static function get(array $in): Reply
    {
        $id = V::id($in['id'] ?? null, 'Customer');
        $customer = Database::one('SELECT id, name, email, phone, address, is_active FROM customers WHERE id = ?', [$id]);
        if ($customer === null) {
            throw new AppException('Customer not found.', 404);
        }
        $customer['sales'] = Database::all(
            'SELECT s.id, s.sale_no, s.sale_date, s.total_amount, w.name AS warehouse_name,
                    (SELECT COALESCE(SUM(si.quantity), 0) FROM sale_items si WHERE si.sale_id = s.id) AS total_qty
             FROM sales s JOIN warehouses w ON w.id = s.warehouse_id
             WHERE s.customer_id = ? ORDER BY s.sale_date DESC, s.id DESC LIMIT 100',
            [$id]
        );
        return new Reply($customer);
    }

    public static function save(array $in): Reply
    {
        $id = isset($in['id']) && $in['id'] !== '' ? V::id($in['id'], 'Customer') : null;
        $d = V::clean($in, [
            'name' => 'required|string|max:150',
            'email' => 'email',
            'phone' => 'string|max:30',
            'address' => 'string|max:255',
            'is_active' => 'bool',
        ]);
        if (!array_key_exists('is_active', $in)) {
            $d['is_active'] = 1;
        }
        if ($id) {
            if (Database::value('SELECT id FROM customers WHERE id = ?', [$id]) === null) {
                throw new AppException('Customer not found.', 404);
            }
            Database::exec(
                'UPDATE customers SET name = ?, email = ?, phone = ?, address = ?, is_active = ? WHERE id = ?',
                [$d['name'], $d['email'], $d['phone'], $d['address'], $d['is_active'], $id]
            );
            Activity::log('customer_update', 'customer', $id, $d['name']);
            return new Reply(['id' => $id], 'Customer updated successfully.');
        }
        $id = Database::insert(
            'INSERT INTO customers (name, email, phone, address, is_active) VALUES (?, ?, ?, ?, ?)',
            [$d['name'], $d['email'], $d['phone'], $d['address'], $d['is_active']]
        );
        Activity::log('customer_create', 'customer', $id, $d['name']);
        return new Reply(['id' => $id], 'Customer created successfully.');
    }

    public static function delete(array $in): Reply
    {
        $id = V::id($in['id'] ?? null, 'Customer');
        $name = Database::value('SELECT name FROM customers WHERE id = ?', [$id]);
        if ($name === null) {
            throw new AppException('Customer not found.', 404);
        }
        try {
            Database::exec('DELETE FROM customers WHERE id = ?', [$id]);
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1451) {
                throw new AppException('This customer has sales history and cannot be deleted. Mark it inactive instead.', 409);
            }
            throw $e;
        }
        Activity::log('customer_delete', 'customer', $id, (string)$name);
        return new Reply(null, 'Customer deleted successfully.');
    }
}
