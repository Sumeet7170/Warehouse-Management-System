<?php

final class UsersModule
{
    public const READ = ['list'];
    public const ADMIN = ['*'];
    public const OPEN = [];

    public static function list(array $in): Reply
    {
        $where = ['1=1'];
        $params = [];
        $search = trim((string)($in['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(u.name LIKE ? OR u.email LIKE ?)';
            $like = Paginator::like($search);
            array_push($params, $like, $like);
        }
        if (in_array($in['role'] ?? '', ['admin', 'staff'], true)) {
            $where[] = 'u.role = ?';
            $params[] = $in['role'];
        }
        if (($in['status'] ?? '') === 'active') {
            $where[] = 'u.is_active = 1';
        } elseif (($in['status'] ?? '') === 'inactive') {
            $where[] = 'u.is_active = 0';
        }
        $sql = 'SELECT u.id, u.name, u.email, u.role, u.is_active, u.last_login_at, u.created_at FROM users u WHERE ' . implode(' AND ', $where) . ' ORDER BY u.name ASC';
        return Paginator::reply($sql, $params, $in);
    }

    public static function save(array $in): Reply
    {
        $id = isset($in['id']) && $in['id'] !== '' ? V::id($in['id'], 'User') : null;
        $d = V::clean($in, [
            'name' => 'required|string|max:120',
            'email' => 'required|email',
            'role' => 'required|in:admin,staff',
            'password' => 'string|max:200',
            'is_active' => 'bool',
        ]);
        if (!array_key_exists('is_active', $in)) {
            $d['is_active'] = 1;
        }
        $d['email'] = strtolower($d['email']);
        if ($d['password'] !== null || $id === null) {
            if ($d['password'] === null || mb_strlen($d['password']) < 8 || !preg_match('/[A-Za-z]/', $d['password']) || !preg_match('/\d/', $d['password'])) {
                throw new ValidationException(['password' => 'Password must be at least 8 characters and contain a letter and a number.']);
            }
        }
        $dup = Database::value('SELECT id FROM users WHERE email = ?' . ($id ? ' AND id <> ?' : ''), $id ? [$d['email'], $id] : [$d['email']]);
        if ($dup) {
            throw new ValidationException(['email' => 'This email address is already in use.']);
        }
        if ($id) {
            if (Database::value('SELECT id FROM users WHERE id = ?', [$id]) === null) {
                throw new AppException('User not found.', 404);
            }
            if ($id === Auth::id() && ($d['role'] !== 'admin' || (int)$d['is_active'] === 0)) {
                throw new AppException('You cannot remove your own administrator access or deactivate your own account.', 409);
            }
            $adminsLeft = (int)Database::value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND id <> ?", [$id]);
            if ($adminsLeft === 0 && ($d['role'] !== 'admin' || (int)$d['is_active'] === 0)) {
                throw new AppException('At least one active administrator is required.', 409);
            }
            Database::exec('UPDATE users SET name = ?, email = ?, role = ?, is_active = ? WHERE id = ?', [$d['name'], $d['email'], $d['role'], $d['is_active'], $id]);
            if ($d['password'] !== null) {
                Database::exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($d['password'], PASSWORD_DEFAULT), $id]);
            }
            Activity::log('user_update', 'user', $id, $d['email']);
            return new Reply(['id' => $id], 'User updated successfully.');
        }
        $id = Database::insert(
            'INSERT INTO users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?)',
            [$d['name'], $d['email'], password_hash($d['password'], PASSWORD_DEFAULT), $d['role'], $d['is_active']]
        );
        Activity::log('user_create', 'user', $id, $d['email']);
        return new Reply(['id' => $id], 'User created successfully.');
    }

    public static function delete(array $in): Reply
    {
        $id = V::id($in['id'] ?? null, 'User');
        if ($id === Auth::id()) {
            throw new AppException('You cannot delete your own account.', 409);
        }
        $email = Database::value('SELECT email FROM users WHERE id = ?', [$id]);
        if ($email === null) {
            throw new AppException('User not found.', 404);
        }
        try {
            Database::exec('DELETE FROM users WHERE id = ?', [$id]);
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1451) {
                throw new AppException('This user has recorded transactions and cannot be deleted. Deactivate the account instead.', 409);
            }
            throw $e;
        }
        Activity::log('user_delete', 'user', $id, (string)$email);
        return new Reply(null, 'User deleted successfully.');
    }
}
