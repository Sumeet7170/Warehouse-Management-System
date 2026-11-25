<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

define('WMS_ROOT', dirname(__DIR__));
$config = require WMS_ROOT . '/config/config.php';
$db = $config['db'];

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], $db['port']),
        $db['user'],
        $db['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "Cannot connect to MySQL: " . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, "Start MySQL in the XAMPP Control Panel and check backend/config/config.php." . PHP_EOL);
    exit(1);
}

$seedFile = __DIR__ . '/showcase_seed.sql';
$files = ($db['name'] === 'wms_db' && is_file($seedFile)) ? ['showcase_seed.sql'] : ['schema.sql'];

foreach ($files as $file) {
    $sql = file_get_contents(__DIR__ . '/' . $file);
    if ($file === 'schema.sql') {
        $sql = str_replace('wms_db', $db['name'], $sql);
    }
    $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);
    $count = 0;
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement === '' || str_starts_with($statement, '--')) {
            continue;
        }
        try {
            $pdo->exec($statement);
            $count++;
        } catch (PDOException $e) {
            fwrite(STDERR, 'Error in ' . $file . ': ' . $e->getMessage() . PHP_EOL . substr($statement, 0, 200) . PHP_EOL);
            exit(1);
        }
    }
    echo 'Imported ' . $file . ' (' . $count . ' statements)' . PHP_EOL;
}

// Ensure showcase administrator account exists
$pdo->exec("USE `{$db['name']}`");
$sumeetHash = password_hash('$M@rty22', PASSWORD_BCRYPT);
$stmt = $pdo->prepare("INSERT INTO users (id, name, email, password_hash, role, account_db, is_active) VALUES (3, 'Sumeet Ajay Tiwari', 'sumeettiwari22@gmail.com', ?, 'admin', 'wms_account_3', 1) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = VALUES(role), account_db = VALUES(account_db)");
$stmt->execute([$sumeetHash]);
echo 'Configured showcase administrator account.' . PHP_EOL;

echo PHP_EOL . 'Database "' . $db['name'] . '" is ready.' . PHP_EOL;
echo 'Administrator: sumeettiwari22@gmail.com / $M@rty22' . PHP_EOL;
