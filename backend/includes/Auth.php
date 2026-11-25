<?php

final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $app = App::config('app');
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
            || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);

        $driver = $app['session_driver'] ?? 'auto';
        $useDbSessions = $driver === 'db'
            || ($driver === 'auto' && (getenv('VERCEL') || getenv('RENDER')));

        if (!headers_sent()) {
            if ($useDbSessions) {
                $handler = new DbSessionHandler(App::config('db')['name'], (int)$app['session_lifetime']);
                session_set_save_handler($handler, true);
            }
            session_name($app['session_name']);
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => $https,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            ini_set('session.gc_maxlifetime', (string)$app['session_lifetime']);
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (isset($_SESSION['last_seen']) && time() - (int)$_SESSION['last_seen'] > (int)$app['session_lifetime']) {
            $_SESSION = [];
        }
        $_SESSION['last_seen'] = time();

        if (!empty($_SESSION['account_db'])) {
            Database::setAccountDb($_SESSION['account_db']);
        }
    }

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $uid = (int)($_SESSION['uid'] ?? 0);
            if ($uid > 0) {
                $masterDb = App::config('db')['name'];
                self::$user = Database::one("SELECT id, name, email, role, account_db FROM `{$masterDb}`.users WHERE id = ? AND is_active = 1", [$uid]);
                if (self::$user === null) {
                    unset($_SESSION['uid'], $_SESSION['account_db']);
                } else {
                    $_SESSION['account_db'] = self::$user['account_db'] ?? $masterDb;
                    Database::setAccountDb($_SESSION['account_db']);
                }
            }
        }
        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): int
    {
        $user = self::user();
        if ($user === null) {
            throw new AppException('Authentication required.', 401);
        }
        return (int)$user['id'];
    }

    public static function isAdmin(): bool
    {
        $user = self::user();
        return $user !== null && $user['role'] === 'admin';
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            throw new AppException('Your session has expired. Please sign in again.', 401);
        }
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            throw new AppException('You do not have permission to perform this action.', 403);
        }
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function verifyCsrf(): void
    {
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $token = $_SESSION['csrf'] ?? '';
        if ($token === '' || !is_string($sent) || !hash_equals($token, $sent)) {
            throw new AppException('Security token is invalid or expired. Please reload the page.', 419);
        }
    }

    public static function login(array $user): void
    {
        self::start();
        if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }
        $db = $user['account_db'] ?? App::config('db')['name'];
        $_SESSION['uid'] = (int)$user['id'];
        $_SESSION['account_db'] = $db;
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $_SESSION['last_seen'] = time();
        self::$user = $user;
        self::$loaded = true;
        Database::setAccountDb($db);
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }
        self::$user = null;
        self::$loaded = false;
        Database::setAccountDb(null);
    }
}
