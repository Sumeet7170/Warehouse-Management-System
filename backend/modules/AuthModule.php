<?php

final class AuthModule
{
    public const READ = ['me'];
    public const ADMIN = [];
    public const OPEN = ['login', 'logout', 'me', 'register'];

    public static function register(array $in): Reply
    {
        $d = V::clean($in, [
            'name' => 'required|string|min:2|max:120',
            'email' => 'required|email|max:150',
            'password' => 'required|string|min:8|max:200',
            'confirm_password' => 'required|string|max:200',
            'role' => 'string|in:admin,staff',
        ], ['name' => 'Full Name', 'email' => 'Email address', 'password' => 'Password', 'confirm_password' => 'Confirm Password']);

        if ($d['password'] !== $d['confirm_password']) {
            throw new ValidationException(['confirm_password' => 'Passwords do not match.']);
        }

        if (!preg_match('/[A-Za-z]/', $d['password']) || !preg_match('/\d/', $d['password'])) {
            throw new ValidationException(['password' => 'Password must contain at least one letter and one number.']);
        }

        $masterDb = App::config('db')['name'];
        $email = strtolower($d['email']);
        $existing = Database::value("SELECT id FROM `{$masterDb}`.users WHERE email = ?", [$email]);
        if ($existing !== null) {
            throw new ValidationException(['email' => 'An account with this email address already exists.']);
        }

        // Each newly registered business user gets admin access over their own workspace
        $role = 'admin';
        $hash = password_hash($d['password'], PASSWORD_DEFAULT);

        // 1. Insert into central master registry
        $newId = Database::insert(
            "INSERT INTO `{$masterDb}`.users (name, email, password_hash, role, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())",
            [$d['name'], $email, $hash, $role]
        );

        $accountDb = 'wms_account_' . $newId;
        Database::exec("UPDATE `{$masterDb}`.users SET account_db = ? WHERE id = ?", [$accountDb, $newId]);

        // 2. Destroy any existing session
        Auth::logout();
        Auth::start();

        // 3. Provision clean empty database from schema starting from ZERO
        $user = [
            'id' => (int)$newId,
            'name' => $d['name'],
            'email' => $email,
            'password_hash' => $hash,
            'role' => $role,
            'account_db' => $accountDb,
            'is_active' => 1
        ];
        Database::provisionAccount($accountDb, $user);

        // 4. Log in into the new isolated workspace
        Auth::login($user);
        Database::exec("UPDATE `{$masterDb}`.users SET last_login_at = NOW() WHERE id = ?", [$newId]);
        Activity::log('register', 'user', (int)$newId, 'New account registered: ' . $email);

        $app = App::config('app');
        return new Reply([
            'user' => ['id' => (int)$newId, 'name' => $d['name'], 'email' => $email, 'role' => $role],
            'csrf' => Auth::csrfToken(),
            'app' => ['name' => $app['name'], 'expiry_alert_days' => $app['expiry_alert_days']],
        ], 'Registration successful! Your new account is ready.');
    }

    public static function me(array $in): Reply
    {
        $user = Auth::user();
        $app = App::config('app');
        $data = ['user' => null, 'csrf' => null, 'app' => ['name' => $app['name'], 'expiry_alert_days' => $app['expiry_alert_days']]];
        if ($user !== null) {
            $data['user'] = [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ];
            $data['csrf'] = Auth::csrfToken();
        }
        return new Reply($data);
    }

    public static function login(array $in): Reply
    {
        $d = V::clean($in, ['email' => 'required|string|max:150', 'password' => 'required|string|max:200'], ['email' => 'Email']);
        $app = App::config('app');
        $masterDb = App::config('db')['name'];
        $email = strtolower($d['email']);
        $since = date('Y-m-d H:i:s', time() - (int)$app['lockout_minutes'] * 60);

        $fails = (int)Database::value("SELECT COUNT(*) FROM `{$masterDb}`.login_attempts WHERE email = ? AND attempted_at >= ?", [$email, $since]);
        if ($fails >= (int)$app['max_login_attempts']) {
            throw new AppException('Too many failed sign-in attempts. Please try again in ' . (int)$app['lockout_minutes'] . ' minutes.', 429);
        }

        $user = Database::one("SELECT id, name, email, role, password_hash, is_active, account_db FROM `{$masterDb}`.users WHERE email = ?", [$email]);
        $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringforsaltgGJb0H0cT8kqJ7p7Z6bq3m1Qz0JXe';
        $valid = password_verify($d['password'], $hash);
        if ($user === null || !$valid || (int)$user['is_active'] !== 1) {
            Database::exec("INSERT INTO `{$masterDb}`.login_attempts (email, ip_address, attempted_at) VALUES (?, ?, NOW())", [$email, $_SERVER['REMOTE_ADDR'] ?? '']);
            throw new AppException('Invalid email or password.', 401);
        }

        Database::exec("DELETE FROM `{$masterDb}`.login_attempts WHERE email = ?", [$email]);

        // Destroy previous session
        Auth::logout();
        Auth::start();

        // Switch to user account database
        $userDb = !empty($user['account_db']) ? $user['account_db'] : $masterDb;
        $user['account_db'] = $userDb;
        Auth::login($user);

        Database::exec("UPDATE `{$masterDb}`.users SET last_login_at = NOW() WHERE id = ?", [$user['id']]);
        if ($userDb !== $masterDb) {
            @Database::exec("UPDATE `{$userDb}`.users SET last_login_at = NOW() WHERE email = ?", [$email]);
        }
        Activity::log('login', 'user', (int)$user['id'], 'User signed in');

        return new Reply([
            'user' => ['id' => (int)$user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']],
            'csrf' => Auth::csrfToken(),
            'app' => ['name' => $app['name'], 'expiry_alert_days' => $app['expiry_alert_days']],
        ], 'Signed in successfully.');
    }

    public static function logout(array $in): Reply
    {
        if (Auth::check()) {
            Activity::log('logout', 'user', Auth::id(), 'User signed out');
        }
        Auth::logout();
        return new Reply(null, 'Signed out.');
    }

    public static function change_password(array $in): Reply
    {
        $d = V::clean($in, [
            'current_password' => 'required|string|max:200',
            'new_password' => 'required|string|min:8|max:200',
            'confirm_password' => 'required|string|max:200',
        ]);
        if ($d['new_password'] !== $d['confirm_password']) {
            throw new ValidationException(['confirm_password' => 'Password confirmation does not match.']);
        }
        if (!preg_match('/[A-Za-z]/', $d['new_password']) || !preg_match('/\d/', $d['new_password'])) {
            throw new ValidationException(['new_password' => 'Password must contain at least one letter and one number.']);
        }

        $masterDb = App::config('db')['name'];
        $user = Auth::user();
        if ($user === null || !password_verify($d['current_password'], $user['password_hash'])) {
            throw new ValidationException(['current_password' => 'Current password is incorrect.']);
        }

        $newHash = password_hash($d['new_password'], PASSWORD_DEFAULT);
        Database::exec("UPDATE `{$masterDb}`.users SET password_hash = ? WHERE id = ?", [$newHash, $user['id']]);
        if (!empty($user['account_db']) && $user['account_db'] !== $masterDb) {
            @Database::exec("UPDATE `{$user['account_db']}`.users SET password_hash = ? WHERE email = ?", [$newHash, $user['email']]);
        }
        Activity::log('password_change', 'user', (int)$user['id'], 'Password changed');
        return new Reply(null, 'Password updated successfully.');
    }
}
