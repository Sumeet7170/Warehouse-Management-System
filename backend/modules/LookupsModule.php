<?php

final class LookupsModule
{
    public const READ = ['all'];
    public const ADMIN = [];
    public const OPEN = [];

    public static function all(array $in): Reply
    {
        return new Reply([
            'warehouses' => Database::all('SELECT id, code, name, is_active FROM warehouses ORDER BY name'),
            'categories' => Database::all('SELECT id, name FROM categories ORDER BY name'),
            'vendors' => Database::all('SELECT id, name, is_active FROM vendors ORDER BY name'),
            'customers' => Database::all('SELECT id, name, is_active FROM customers ORDER BY name'),
        ]);
    }
}
