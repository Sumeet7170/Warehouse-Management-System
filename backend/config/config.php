<?php

$envOr = static function (array $keys, string $default = ''): string {
    foreach ($keys as $k) {
        $v = getenv($k);
        if ($v !== false && $v !== '') {
            return $v;
        }
    }
    return $default;
};

$dbUrl = getenv('DATABASE_URL') ?: '';
$parsedUrl = $dbUrl !== '' ? parse_url($dbUrl) : false;

$dbHost = $envOr(['WMS_DB_HOST', 'TIDB_HOST', 'MYSQL_HOST', 'MYSQLHOST'], is_array($parsedUrl) && !empty($parsedUrl['host']) ? $parsedUrl['host'] : '127.0.0.1');
$dbPort = (int)$envOr(['WMS_DB_PORT', 'TIDB_PORT', 'MYSQL_PORT', 'MYSQLPORT'], (string)(is_array($parsedUrl) && !empty($parsedUrl['port']) ? $parsedUrl['port'] : 3306));
$dbName = $envOr(['WMS_DB_NAME', 'TIDB_DATABASE', 'MYSQL_DATABASE', 'MYSQLDATABASE'], is_array($parsedUrl) && !empty($parsedUrl['path']) ? ltrim($parsedUrl['path'], '/') : 'wms_db');
$dbUser = $envOr(['WMS_DB_USER', 'TIDB_USER', 'MYSQL_USER', 'MYSQLUSER'], is_array($parsedUrl) && isset($parsedUrl['user']) ? urldecode($parsedUrl['user']) : 'root');

$dbPassEnv = getenv('WMS_DB_PASS');
if ($dbPassEnv === false) {
    $dbPassEnv = getenv('TIDB_PASSWORD');
}
if ($dbPassEnv === false) {
    $dbPassEnv = getenv('MYSQL_PASSWORD');
}
if ($dbPassEnv === false) {
    $dbPassEnv = getenv('MYSQLPASSWORD');
}
if ($dbPassEnv !== false) {
    $dbPass = $dbPassEnv;
} elseif (is_array($parsedUrl) && isset($parsedUrl['pass'])) {
    $dbPass = urldecode($parsedUrl['pass']);
} else {
    $dbPass = '';
}

$sslEnv = getenv('WMS_DB_SSL');
$useSsl = $sslEnv !== false
    ? filter_var($sslEnv, FILTER_VALIDATE_BOOLEAN)
    : (getenv('TIDB_HOST') !== false || ($dbUrl !== '' && stripos($dbUrl, 'ssl') !== false));

return [
    'db' => [
        'host' => $dbHost,
        'port' => $dbPort,
        'name' => $dbName,
        'user' => $dbUser,
        'pass' => $dbPass,
        'charset' => 'utf8mb4',
        'ssl' => $useSsl,
        'ssl_ca' => getenv('WMS_DB_SSL_CA') ?: '',
    ],
    'app' => [
        'name' => 'Warehouse Management System',
        'timezone' => getenv('WMS_TIMEZONE') ?: 'Asia/Kolkata',
        'session_name' => 'WMSSESSID',
        'session_lifetime' => 28800,
        'session_driver' => getenv('WMS_SESSION_DRIVER') ?: (getenv('VERCEL') ? 'db' : 'auto'),
        'expiry_alert_days' => 30,
        'default_per_page' => 15,
        'max_login_attempts' => 5,
        'lockout_minutes' => 15,
    ],
];
