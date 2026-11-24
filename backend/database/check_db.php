<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$config = require dirname(__DIR__) . '/config/config.php';
$db = $config['db'];

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']),
        $db['user'],
        $db['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->fetch()) {
        exit(0); // Installed and ready
    }
    exit(2); // Tables missing
} catch (PDOException $e) {
    exit(1); // Database missing or not connected
}
