<?php

final class Router
{
    public static function dispatch(): void
    {
        $resource = preg_replace('/[^a-z_]/', '', strtolower((string)($_GET['r'] ?? '')));
        $action = preg_replace('/[^a-z_]/', '', strtolower((string)($_GET['a'] ?? '')));
        if ($resource === '' || $action === '') {
            throw new AppException('Invalid request.', 400);
        }
        $class = implode('', array_map('ucfirst', explode('_', $resource))) . 'Module';
        $file = WMS_ROOT . '/modules/' . $class . '.php';
        if (!is_file($file)) {
            throw new AppException('Endpoint not found.', 404);
        }
        require_once $file;
        if (!class_exists($class, false) || !method_exists($class, $action)) {
            throw new AppException('Endpoint not found.', 404);
        }
        $reflection = new ReflectionMethod($class, $action);
        if (!$reflection->isPublic() || !$reflection->isStatic()) {
            throw new AppException('Endpoint not found.', 404);
        }

        Auth::start();

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $isRead = in_array($action, $class::READ, true);
        $isOpen = in_array($action, $class::OPEN, true);
        if ($isRead && !in_array($method, ['GET', 'HEAD'], true)) {
            throw new AppException('Method not allowed.', 405);
        }
        if (!$isRead && $method !== 'POST') {
            throw new AppException('Method not allowed.', 405);
        }
        if (!$isOpen) {
            if (in_array($action, $class::ADMIN, true) || in_array('*', $class::ADMIN, true)) {
                Auth::requireAdmin();
            } else {
                Auth::requireLogin();
            }
            if (!$isRead) {
                Auth::verifyCsrf();
            }
        }

        $input = $_GET;
        if ($method === 'POST') {
            $raw = file_get_contents('php://input');
            if ($raw !== false && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) {
                    throw new AppException('Request body must be valid JSON.', 400);
                }
                $input = array_merge($input, $decoded);
            } else {
                $input = array_merge($input, $_POST);
            }
        }
        unset($input['r'], $input['a']);

        $result = $class::$action($input);
        if ($result instanceof Reply) {
            Response::success($result->data, $result->message, $result->meta);
        }
        Response::success($result);
    }
}
