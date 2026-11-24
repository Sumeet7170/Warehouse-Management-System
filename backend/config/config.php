<?php

return [
    'db' => [
        'host' => getenv('WMS_DB_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('WMS_DB_PORT') ?: 3306),
        'name' => getenv('WMS_DB_NAME') ?: 'wms_db',
        'user' => getenv('WMS_DB_USER') ?: 'root',
        'pass' => getenv('WMS_DB_PASS') !== false ? getenv('WMS_DB_PASS') : '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => 'Warehouse Management System',
        'timezone' => 'Asia/Kolkata',
        'session_name' => 'WMSSESSID',
        'session_lifetime' => 28800,
        'expiry_alert_days' => 30,
        'default_per_page' => 15,
        'max_login_attempts' => 5,
        'lockout_minutes' => 15,
    ],
];
