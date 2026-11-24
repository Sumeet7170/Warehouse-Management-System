<?php

final class Response
{
    private static function send(array $payload, int $status): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            header('X-Content-Type-Options: nosniff');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        exit;
    }

    public static function success(mixed $data = null, ?string $message = null, ?array $meta = null, int $status = 200): never
    {
        $payload = ['success' => true, 'data' => $data, 'message' => $message];
        if ($meta !== null) {
            $payload['meta'] = $meta;
        }
        self::send($payload, $status);
    }

    public static function error(string $message, int $status = 400, array $errors = []): never
    {
        if ($status < 400 || $status > 599) {
            $status = 400;
        }
        self::send(['success' => false, 'message' => $message, 'errors' => (object)$errors], $status);
    }
}
