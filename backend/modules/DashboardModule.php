<?php

final class DashboardModule
{
    public const READ = ['summary', 'alerts'];
    public const ADMIN = [];
    public const OPEN = [];

    public static function alerts(array $in): Reply
    {
        $exp = Inventory::expiryCounts();
        return new Reply([
            'low_stock' => count(Inventory::lowStock()),
            'expiring' => $exp['expiring'],
            'expired' => $exp['expired'],
        ]);
    }

    public static function summary(array $in): Reply
    {
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        $totals = Database::one(
            "SELECT
                (SELECT COUNT(*) FROM products WHERE is_active = 1) AS products,
                (SELECT COUNT(*) FROM warehouses WHERE is_active = 1) AS warehouses,
                (SELECT COALESCE(SUM(quantity), 0) FROM inventory_batches) AS total_units,
                (SELECT COALESCE(SUM(quantity * unit_cost), 0) FROM inventory_batches) AS stock_value,
                (SELECT COUNT(*) FROM stock_transfers WHERE status IN ('pending', 'in_transit')) AS open_transfers,
                (SELECT COUNT(*) FROM purchases WHERE status IN ('ordered', 'partial')) AS pending_purchases"
        );
        $low = Inventory::lowStock();
        $exp = Inventory::expiryCounts();
        $purchaseMonth = Database::one("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS amount FROM purchases WHERE status <> 'cancelled' AND purchase_date >= ?", [$monthStart]);
        $saleMonth = Database::one('SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS amount FROM sales WHERE sale_date >= ?', [$monthStart]);
        $saleToday = Database::one('SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS amount FROM sales WHERE sale_date = ?', [$today]);
        $recent = Database::all(TransactionsModule::baseSql() . ' ORDER BY t.id DESC LIMIT 10');
        $expiringItems = Database::all(
            'SELECT b.id, b.batch_number, b.expiry_date, b.quantity, p.sku, p.name AS product_name, w.name AS warehouse_name, DATEDIFF(b.expiry_date, CURDATE()) AS days_to_expiry
             FROM inventory_batches b JOIN products p ON p.id = b.product_id JOIN warehouses w ON w.id = b.warehouse_id
             WHERE b.quantity > 0 AND b.expiry_date IS NOT NULL AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ' . (int)$exp['days'] . ' DAY)
             ORDER BY b.expiry_date ASC LIMIT 6'
        );
        $start = date('Y-m-d', strtotime('-6 days'));
        $salesByDay = [];
        foreach (Database::all('SELECT sale_date AS d, SUM(total_amount) AS amount FROM sales WHERE sale_date >= ? GROUP BY sale_date', [$start]) as $r) {
            $salesByDay[$r['d']] = (float)$r['amount'];
        }
        $purchasesByDay = [];
        foreach (Database::all("SELECT purchase_date AS d, SUM(total_amount) AS amount FROM purchases WHERE status <> 'cancelled' AND purchase_date >= ? GROUP BY purchase_date", [$start]) as $r) {
            $purchasesByDay[$r['d']] = (float)$r['amount'];
        }
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime('-' . $i . ' days'));
            $trend[] = ['date' => $day, 'sales' => $salesByDay[$day] ?? 0, 'purchases' => $purchasesByDay[$day] ?? 0];
        }
        return new Reply([
            'totals' => $totals,
            'low_stock_count' => count($low),
            'low_stock_items' => array_slice($low, 0, 6),
            'expiring_count' => $exp['expiring'],
            'expired_count' => $exp['expired'],
            'expiry_days' => $exp['days'],
            'expiring_items' => $expiringItems,
            'purchases_month' => $purchaseMonth,
            'sales_month' => $saleMonth,
            'sales_today' => $saleToday,
            'recent_transactions' => $recent,
            'trend' => $trend,
        ]);
    }
}
