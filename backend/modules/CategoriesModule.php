<?php

final class CategoriesModule
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
            $where[] = '(c.name LIKE ? OR c.description LIKE ?)';
            $like = Paginator::like($search);
            array_push($params, $like, $like);
        }
        $sql = 'SELECT c.id, c.name, c.description, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
                FROM categories c WHERE ' . implode(' AND ', $where) . ' ORDER BY c.name ASC';
        return Paginator::reply($sql, $params, $in);
    }

    public static function save(array $in): Reply
    {
        $id = isset($in['id']) && $in['id'] !== '' ? V::id($in['id'], 'Category') : null;
        $d = V::clean($in, ['name' => 'required|string|max:100', 'description' => 'string|max:255']);
        $dup = Database::value('SELECT id FROM categories WHERE name = ?' . ($id ? ' AND id <> ?' : ''), $id ? [$d['name'], $id] : [$d['name']]);
        if ($dup) {
            throw new ValidationException(['name' => 'This category name already exists.']);
        }
        if ($id) {
            if (Database::value('SELECT id FROM categories WHERE id = ?', [$id]) === null) {
                throw new AppException('Category not found.', 404);
            }
            Database::exec('UPDATE categories SET name = ?, description = ? WHERE id = ?', [$d['name'], $d['description'], $id]);
            Activity::log('category_update', 'category', $id, $d['name']);
            return new Reply(['id' => $id], 'Category updated successfully.');
        }
        $id = Database::insert('INSERT INTO categories (name, description) VALUES (?, ?)', [$d['name'], $d['description']]);
        Activity::log('category_create', 'category', $id, $d['name']);
        return new Reply(['id' => $id], 'Category created successfully.');
    }

    public static function delete(array $in): Reply
    {
        $id = V::id($in['id'] ?? null, 'Category');
        $name = Database::value('SELECT name FROM categories WHERE id = ?', [$id]);
        if ($name === null) {
            throw new AppException('Category not found.', 404);
        }
        Database::exec('DELETE FROM categories WHERE id = ?', [$id]);
        Activity::log('category_delete', 'category', $id, (string)$name);
        return new Reply(null, 'Category deleted successfully. Its products are now uncategorised.');
    }
}
