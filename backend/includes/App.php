<?php

final class App
{
    private static ?array $config = null;

    public static function config(string $key): array
    {
        if (self::$config === null) {
            self::$config = require WMS_ROOT . '/config/config.php';
        }
        return self::$config[$key];
    }

    public static function boot(): void
    {
        $app = self::config('app');
        date_default_timezone_set($app['timezone']);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);
        set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
            if ($no === E_DEPRECATED || $no === E_USER_DEPRECATED) {
                return true;
            }
            throw new ErrorException($str, 0, $no, $file, $line);
        });
        set_exception_handler([self::class, 'handleException']);
    }

    public static function log(string $message): void
    {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
        $dir = WMS_ROOT . '/logs';
        $wrote = false;
        if ((is_dir($dir) || @mkdir($dir, 0775, true)) && is_writable($dir)) {
            $wrote = @file_put_contents($dir . '/app.log', $line . PHP_EOL, FILE_APPEND) !== false;
        }
        if (!$wrote || getenv('RENDER') || getenv('VERCEL')) {
            @error_log('[WMS] ' . $line);
        }
    }

    public static function handleException(Throwable $e): void
    {
        if ($e instanceof ValidationException) {
            Response::error($e->getMessage(), 422, $e->errors());
        }
        if ($e instanceof AppException) {
            Response::error($e->getMessage(), (int)$e->getCode() ?: 400);
        }
        if ($e instanceof PDOException) {
            $code = (int)($e->errorInfo[1] ?? 0);
            if ($code === 1062) {
                Response::error(self::duplicateMessage($e->getMessage()), 409);
            }
            if ($code === 1451) {
                Response::error('This record is in use by other records and cannot be deleted.', 409);
            }
            if ($code === 1452) {
                Response::error('A related record does not exist.', 409);
            }
            if (in_array($code, [4025, 3819, 1264], true)) {
                Response::error('The operation would result in an invalid quantity or value.', 409);
            }
            if ($code === 1213 || $code === 1205) {
                Response::error('The system is busy. Please try again.', 503);
            }
        }
        self::log(get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        Response::error('An unexpected server error occurred. Please try again.', 500);
    }

    private static function duplicateMessage(string $text): string
    {
        $map = [
            'uq_products_sku' => 'This SKU already exists.',
            'uq_products_barcode' => 'This barcode is already assigned to another product.',
            'uq_users_email' => 'This email address is already in use.',
            'uq_warehouses_code' => 'This warehouse code already exists.',
            'uq_categories_name' => 'This category name already exists.',
            'uq_batch' => 'This batch number already exists for the product in this warehouse.',
        ];
        if (preg_match("/for key '([^']+)'/", $text, $m)) {
            $key = substr(strrchr('.' . $m[1], '.'), 1);
            if (isset($map[$key])) {
                return $map[$key];
            }
            if (str_ends_with($key, '_idem')) {
                return 'This request has already been submitted.';
            }
        }
        return 'A record with the same unique value already exists.';
    }
}
