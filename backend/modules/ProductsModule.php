<?php

final class ProductsModule
{
    public const READ = ['list', 'get', 'search', 'lookup', 'generate_barcode'];
    public const ADMIN = ['delete'];
    public const OPEN = [];

    public static function list(array $in): Reply
    {
        $where = ['1=1'];
        $params = [];
        $search = trim((string)($in['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)';
            $like = Paginator::like($search);
            array_push($params, $like, $like, $like);
        }
        if (!empty($in['category_id'])) {
            $where[] = 'p.category_id = ?';
            $params[] = (int)$in['category_id'];
        }
        if (($in['status'] ?? '') === 'active') {
            $where[] = 'p.is_active = 1';
        } elseif (($in['status'] ?? '') === 'inactive') {
            $where[] = 'p.is_active = 0';
        }
        $sql = 'SELECT p.id, p.sku, p.barcode, p.name, p.category_id, c.name AS category_name, p.unit, p.price, p.cost_price,
                p.min_stock_level, p.has_expiry, p.is_active,
                COALESCE((SELECT SUM(b.quantity) FROM inventory_batches b WHERE b.product_id = p.id AND ' . Inventory::SELLABLE . '), 0) AS sellable_qty
                FROM products p LEFT JOIN categories c ON c.id = p.category_id
                WHERE ' . implode(' AND ', $where) . ' ORDER BY p.name ASC, p.id ASC';
        return Paginator::reply($sql, $params, $in);
    }

    public static function get(array $in): Reply
    {
        $id = V::id($in['id'] ?? null, 'Product');
        $product = Database::one(
            'SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ?',
            [$id]
        );
        if ($product === null) {
            throw new AppException('Product not found.', 404);
        }
        $product['stock_by_warehouse'] = self::stockByWarehouse($id);
        $product['batches'] = Database::all(
            'SELECT b.id, b.batch_number, b.expiry_date, b.quantity, b.unit_cost, b.received_at, w.name AS warehouse_name
             FROM inventory_batches b JOIN warehouses w ON w.id = b.warehouse_id
             WHERE b.product_id = ? AND b.quantity > 0 ORDER BY w.name ASC, b.received_at ASC, b.id ASC',
            [$id]
        );
        return new Reply($product);
    }

    private static function stockByWarehouse(int $productId): array
    {
        return Database::all(
            'SELECT w.id AS warehouse_id, w.name AS warehouse_name, SUM(b.quantity) AS total_qty,
                    SUM(CASE WHEN ' . Inventory::SELLABLE . ' THEN b.quantity ELSE 0 END) AS sellable_qty
             FROM inventory_batches b JOIN warehouses w ON w.id = b.warehouse_id
             WHERE b.product_id = ? GROUP BY w.id, w.name HAVING SUM(b.quantity) > 0 ORDER BY w.name ASC',
            [$productId]
        );
    }

    public static function search(array $in): Reply
    {
        $q = trim((string)($in['q'] ?? ''));
        $params = [];
        $where = 'p.is_active = 1';
        if ($q !== '') {
            $like = Paginator::like($q);
            $where .= ' AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        $rows = Database::all(
            'SELECT p.id, p.sku, p.barcode, p.name, p.unit, p.price, p.cost_price, p.has_expiry FROM products p WHERE ' . $where . ' ORDER BY p.name ASC LIMIT 15',
            $params
        );
        return new Reply($rows);
    }

    public static function lookup(array $in): Reply
    {
        $code = trim((string)($in['code'] ?? ''));
        if ($code === '') {
            throw new AppException('Enter or scan a barcode or SKU.', 422);
        }
        $product = Database::one(
            'SELECT p.id, p.sku, p.barcode, p.name, p.unit, p.price, p.cost_price, p.has_expiry, p.is_active, p.min_stock_level, c.name AS category_name
             FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.barcode = ? OR p.sku = ? LIMIT 1',
            [$code, $code]
        );
        if ($product === null) {
            throw new AppException('No product found for code "' . $code . '".', 404);
        }
        $product['stock_by_warehouse'] = self::stockByWarehouse((int)$product['id']);
        return new Reply($product);
    }

    public static function generate_barcode(array $in): Reply
    {
        for ($i = 0; $i < 20; $i++) {
            $base = '20' . str_pad((string)random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
            $sum = 0;
            for ($j = 0; $j < 12; $j++) {
                $sum += (int)$base[$j] * ($j % 2 === 0 ? 1 : 3);
            }
            $code = $base . ((10 - $sum % 10) % 10);
            if (Database::value('SELECT id FROM products WHERE barcode = ?', [$code]) === null) {
                return new Reply(['barcode' => $code]);
            }
        }
        throw new AppException('Could not generate a unique barcode. Please try again.', 500);
    }

    public static function save(array $in): Reply
    {
        $id = isset($in['id']) && $in['id'] !== '' ? V::id($in['id'], 'Product') : null;
        $d = V::clean($in, [
            'sku' => 'required|string|max:60',
            'barcode' => 'string|max:64',
            'name' => 'required|string|max:180',
            'category_id' => 'int|min:1',
            'unit' => 'string|max:20',
            'price' => 'required|numeric|min:0|max:9999999999',
            'cost_price' => 'numeric|min:0|max:9999999999',
            'min_stock_level' => 'int|min:0|max:100000000',
            'has_expiry' => 'bool',
            'description' => 'string|max:2000',
            'is_active' => 'bool',
        ], ['sku' => 'SKU', 'category_id' => 'Category', 'min_stock_level' => 'Minimum stock level', 'cost_price' => 'Cost price']);
        $d['unit'] = $d['unit'] ?? 'pcs';
        $d['cost_price'] = $d['cost_price'] ?? 0;
        $d['min_stock_level'] = $d['min_stock_level'] ?? 0;
        $existing = null;
        if ($id) {
            $existing = Database::one('SELECT id, is_active FROM products WHERE id = ?', [$id]);
            if ($existing === null) {
                throw new AppException('Product not found.', 404);
            }
        }
        if (!array_key_exists('is_active', $in)) {
            $d['is_active'] = $existing ? (int)$existing['is_active'] : 1;
        }
        if ($d['category_id'] !== null && Database::value('SELECT id FROM categories WHERE id = ?', [$d['category_id']]) === null) {
            throw new ValidationException(['category_id' => 'Selected category does not exist.']);
        }
        if ($d['barcode'] === null && filter_var($in['auto_barcode'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $d['barcode'] = self::generate_barcode([])->data['barcode'];
        }
        $dupSku = Database::value('SELECT id FROM products WHERE sku = ?' . ($id ? ' AND id <> ?' : ''), $id ? [$d['sku'], $id] : [$d['sku']]);
        if ($dupSku) {
            throw new ValidationException(['sku' => 'This SKU already exists.']);
        }
        if ($d['barcode'] !== null) {
            $dupBarcode = Database::value('SELECT id FROM products WHERE barcode = ?' . ($id ? ' AND id <> ?' : ''), $id ? [$d['barcode'], $id] : [$d['barcode']]);
            if ($dupBarcode) {
                throw new ValidationException(['barcode' => 'This barcode is already assigned to another product.']);
            }
        }
        if ($id) {
            Database::exec(
                'UPDATE products SET sku = ?, barcode = ?, name = ?, category_id = ?, unit = ?, price = ?, cost_price = ?, min_stock_level = ?, has_expiry = ?, description = ?, is_active = ? WHERE id = ?',
                [$d['sku'], $d['barcode'], $d['name'], $d['category_id'], $d['unit'], $d['price'], $d['cost_price'], $d['min_stock_level'], $d['has_expiry'], $d['description'], $d['is_active'], $id]
            );
            Activity::log('product_update', 'product', $id, $d['sku'] . ' - ' . $d['name']);
            return new Reply(['id' => $id], 'Product updated successfully.');
        }
        $id = Database::insert(
            'INSERT INTO products (sku, barcode, name, category_id, unit, price, cost_price, min_stock_level, has_expiry, description, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$d['sku'], $d['barcode'], $d['name'], $d['category_id'], $d['unit'], $d['price'], $d['cost_price'], $d['min_stock_level'], $d['has_expiry'], $d['description'], $d['is_active']]
        );
        Activity::log('product_create', 'product', $id, $d['sku'] . ' - ' . $d['name']);
        return new Reply(['id' => $id], 'Product created successfully.');
    }

    public static function delete(array $in): Reply
    {
        $id = V::id($in['id'] ?? null, 'Product');
        $product = Database::one('SELECT sku, name FROM products WHERE id = ?', [$id]);
        if ($product === null) {
            throw new AppException('Product not found.', 404);
        }
        try {
            Database::exec('DELETE FROM products WHERE id = ?', [$id]);
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1451) {
                throw new AppException('This product has stock or transaction history and cannot be deleted. Mark it inactive instead.', 409);
            }
            throw $e;
        }
        Activity::log('product_delete', 'product', $id, $product['sku'] . ' - ' . $product['name']);
        return new Reply(null, 'Product deleted successfully.');
    }
}
