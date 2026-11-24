<?php

define('WMS_ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    foreach (['/includes/', '/modules/'] as $dir) {
        $file = WMS_ROOT . $dir . $class . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

App::boot();
