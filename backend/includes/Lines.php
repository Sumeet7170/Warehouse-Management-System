<?php

final class Lines
{
    public static function clean(mixed $items, array $rules, array $labels = []): array
    {
        if (!is_array($items) || count($items) === 0) {
            throw new ValidationException(['items' => 'Add at least one product line.'], 'Add at least one product line.');
        }
        if (count($items) > 200) {
            throw new AppException('A document can contain at most 200 lines.', 422);
        }
        $clean = [];
        $n = 0;
        foreach (array_values($items) as $line) {
            $n++;
            if (!is_array($line)) {
                throw new AppException('Line ' . $n . ' is invalid.', 422);
            }
            try {
                $clean[] = V::clean($line, $rules, $labels) + ['line_no' => $n];
            } catch (ValidationException $e) {
                $messages = array_values($e->errors());
                throw new AppException('Line ' . $n . ': ' . ($messages[0] ?? 'Invalid data.'), 422);
            }
        }
        return $clean;
    }

    public static function products(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::all('SELECT id, sku, name, has_expiry, is_active, cost_price FROM products WHERE id IN (' . $marks . ')', $ids);
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['id']] = $row;
        }
        foreach ($ids as $id) {
            if (!isset($map[$id])) {
                throw new AppException('One of the selected products no longer exists.', 422);
            }
            if ((int)$map[$id]['is_active'] !== 1) {
                throw new AppException('Product "' . $map[$id]['name'] . '" is inactive and cannot be used.', 422);
            }
        }
        return $map;
    }

    public static function activeWarehouse(int $id, string $label = 'Warehouse'): array
    {
        $row = Database::one('SELECT id, name, is_active FROM warehouses WHERE id = ?', [$id]);
        if ($row === null) {
            throw new AppException($label . ' not found.', 422);
        }
        if ((int)$row['is_active'] !== 1) {
            throw new AppException($label . ' "' . $row['name'] . '" is inactive.', 422);
        }
        return $row;
    }
}
