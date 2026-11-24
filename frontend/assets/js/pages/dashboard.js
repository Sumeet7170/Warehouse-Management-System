(function () {
    var W = window.W;

    function card(id, icon, color, label, value, sub, route) {
        return '<div class="col-6 col-lg-3"><a href="#/' + route + '" class="text-decoration-none text-reset"><div class="card stat-card h-100"><div class="card-body d-flex align-items-center gap-3">' +
            '<div class="stat-icon bg-' + color + '-subtle text-' + color + '"><i class="bi bi-' + icon + '"></i></div><div class="min-w-0"><div class="text-muted small">' + W.esc(label) + '</div>' +
            '<div class="stat-value" id="' + id + '">' + value + '</div><div class="small text-muted">' + sub + '</div></div></div></div></a></div>';
    }

    W.routes.dashboard = {
        title: 'Dashboard',
        render: async function (el) {
            var d = (await W.get('dashboard', 'summary')).data;
            var t = d.totals;
            var emptyBanner = '';
            if (Number(t.products) === 0 && Number(t.warehouses) === 0) {
                emptyBanner = '<div class="card border-0 bg-primary-subtle text-primary-emphasis mb-4 shadow-sm">' +
                    '<div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">' +
                    '<div><h5 class="fw-bold mb-1"><i class="bi bi-box-seam me-2"></i>Welcome to your Warehouse Management System</h5>' +
                    '<div class="text-secondary small">Your system is initialized with an empty database state. Start by creating a warehouse location and your first product to begin tracking live inventory.</div></div>' +
                    '<div class="d-flex gap-2 flex-wrap">' +
                    '<a href="#/warehouses" class="btn btn-primary"><i class="bi bi-building me-1"></i>Create First Warehouse</a>' +
                    '<a href="#/products" class="btn btn-outline-primary"><i class="bi bi-plus-lg me-1"></i>Create First Product</a>' +
                    '</div></div></div>';
            }
            el.innerHTML = W.pageHeader('Dashboard', 'Live overview of inventory across all warehouses') +
                emptyBanner +
                '<div class="row g-3 mb-3">' +
                card('s1', 'box-seam', 'primary', 'Total products', W.num(t.products), 'Active catalog items', 'products') +
                card('s2', 'building', 'info', 'Warehouses', W.num(t.warehouses), W.num(t.total_units) + ' units in stock', 'warehouses') +
                card('s3', 'arrow-down-circle', 'danger', 'Low-stock items', W.num(d.low_stock_count), 'Below minimum level', 'lowstock') +
                card('s4', 'hourglass-split', 'warning', 'Expiring batches', W.num(d.expiring_count), W.num(d.expired_count) + ' already expired · next ' + d.expiry_days + ' days', 'expiring') +
                card('s5', 'cash-stack', 'success', 'Stock value', W.money(t.stock_value), 'At batch cost', 'stock') +
                card('s6', 'receipt', 'primary', 'Sales this month', W.money(d.sales_month.amount), W.num(d.sales_month.cnt) + ' orders · today ' + W.money(d.sales_today.amount), 'sales') +
                card('s7', 'cart-plus', 'info', 'Purchases this month', W.money(d.purchases_month.amount), W.num(d.purchases_month.cnt) + ' orders · ' + W.num(t.pending_purchases) + ' awaiting receipt', 'purchases') +
                card('s8', 'arrow-left-right', 'warning', 'Open transfers', W.num(t.open_transfers), 'Pending or in transit', 'transfers') +
                '</div>' +
                '<div class="row g-3 mb-3"><div class="col-lg-8"><div class="card h-100"><div class="card-header">Sales vs purchases (last 7 days)</div><div class="card-body"><div class="chart-box"><canvas id="trendChart"></canvas></div></div></div></div>' +
                '<div class="col-lg-4"><div class="card h-100"><div class="card-header d-flex justify-content-between"><span>Low-stock alerts</span><a href="#/lowstock" class="small">View all</a></div><div class="card-body p-0">' +
                W.simpleTable(['Product', { label: 'Stock', end: true }], d.low_stock_items.map(function (r) {
                    return '<tr><td>' + W.esc(r.name) + '<div class="small text-muted">' + W.esc(r.sku) + '</div></td><td class="text-end"><span class="badge text-bg-danger">' + W.num(r.stock) + '</span> <span class="text-muted small">/ ' + W.num(r.min_stock_level) + '</span></td></tr>';
                }), 'No low-stock products.') + '</div></div></div></div>' +
                '<div class="row g-3"><div class="col-lg-5"><div class="card h-100"><div class="card-header d-flex justify-content-between"><span>Expiring &amp; expired batches</span><a href="#/expiring" class="small">View all</a></div><div class="card-body p-0">' +
                W.simpleTable(['Product', 'Batch', 'Expiry', { label: 'Qty', end: true }], d.expiring_items.map(function (r) {
                    return '<tr><td>' + W.esc(r.product_name) + '<div class="small text-muted">' + W.esc(r.warehouse_name) + '</div></td><td>' + W.esc(r.batch_number) + '</td><td>' + W.expiryBadge(r.expiry_date, r.days_to_expiry) + '</td><td class="text-end">' + W.num(r.quantity) + '</td></tr>';
                }), 'No batches near expiry.') + '</div></div></div>' +
                '<div class="col-lg-7"><div class="card h-100"><div class="card-header d-flex justify-content-between"><span>Recent transactions</span><a href="#/transactions" class="small">View all</a></div><div class="card-body p-0">' +
                W.simpleTable(['When', 'Type', 'Product', 'Warehouse', { label: 'Qty', end: true }], d.recent_transactions.map(function (r) {
                    return '<tr><td class="text-nowrap small">' + W.datetime(r.txn_date) + '</td><td>' + W.txnBadge(r.txn_type) + '</td><td>' + W.esc(r.product_name) + '<div class="small text-muted">' + W.esc(r.batch_number) + ' · ' + W.esc(r.reference_no) + '</div></td><td>' + W.esc(r.warehouse_name) + '</td><td class="text-end">' + W.signed(r.quantity_change) + '</td></tr>';
                }), 'No transactions yet.') + '</div></div></div></div>';
            W.routes.dashboard.trend = d.trend;
        },
        after: function (view) {
            var canvas = view.querySelector('#trendChart');
            var trend = W.routes.dashboard.trend;
            if (!canvas || !trend || typeof Chart === 'undefined') { return; }
            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: trend.map(function (r) { return W.date(r.date); }),
                    datasets: [
                        { label: 'Sales', data: trend.map(function (r) { return r.sales; }), backgroundColor: '#2f6fed' },
                        { label: 'Purchases', data: trend.map(function (r) { return r.purchases; }), backgroundColor: '#20c997' }
                    ]
                },
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
            });
        }
    };
}());
