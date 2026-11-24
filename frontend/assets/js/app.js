(function () {
    var W = window.W;
    var MENU = [
        { id: 'dashboard', label: 'Dashboard', icon: 'speedometer2' },
        { group: 'Catalog' },
        { id: 'products', label: 'Products', icon: 'box-seam' },
        { id: 'barcode', label: 'Barcode', icon: 'upc-scan' },
        { id: 'categories', label: 'Categories', icon: 'tags' },
        { id: 'warehouses', label: 'Warehouses', icon: 'building' },
        { group: 'Operations' },
        { id: 'purchases', label: 'Purchases', icon: 'cart-plus' },
        { id: 'sales', label: 'Sales', icon: 'receipt' },
        { id: 'transfers', label: 'Stock Transfers', icon: 'arrow-left-right' },
        { id: 'adjustments', label: 'Stock Adjustments', icon: 'sliders' },
        { id: 'damaged', label: 'Damaged Stock', icon: 'exclamation-octagon' },
        { group: 'Inventory' },
        { id: 'stock', label: 'Stock Levels', icon: 'stack' },
        { id: 'batches', label: 'Batches', icon: 'collection' },
        { id: 'lowstock', label: 'Low Stock', icon: 'arrow-down-circle' },
        { id: 'expiring', label: 'Expiry Alerts', icon: 'hourglass-split' },
        { id: 'transactions', label: 'Transaction Log', icon: 'journal-text' },
        { group: 'Partners' },
        { id: 'vendors', label: 'Vendors', icon: 'truck' },
        { id: 'customers', label: 'Customers', icon: 'people' },
        { group: 'Insights' },
        { id: 'reports', label: 'Reports', icon: 'bar-chart-line' },
        { group: 'Administration', admin: true },
        { id: 'users', label: 'Users', icon: 'person-gear' },
        { id: 'activity', label: 'Activity Logs', icon: 'clock-history' }
    ];
    var navToken = 0;

    function buildMenu() {
        var html = '';
        MENU.forEach(function (item) {
            if (item.group) {
                if (item.admin && !W.isAdmin()) { return; }
                html += '<div class="nav-group">' + W.esc(item.group) + '</div>';
                return;
            }
            var route = W.routes[item.id];
            if (route && route.admin && !W.isAdmin()) { return; }
            html += '<a class="nav-link" href="#/' + item.id + '" data-route="' + item.id + '"><i class="bi bi-' + item.icon + '"></i><span>' + W.esc(item.label) + '</span></a>';
        });
        document.getElementById('menu').innerHTML = html;
    }

    W.refreshAlerts = async function () {
        try {
            var res = await W.get('dashboard', 'alerts');
            var a = res.data;
            var total = a.low_stock + a.expiring + a.expired;
            var badge = document.getElementById('alertCount');
            badge.textContent = total;
            badge.classList.toggle('d-none', total === 0);
            var items = [
                ['lowstock', 'bi-arrow-down-circle text-danger', a.low_stock + ' low-stock product' + (a.low_stock === 1 ? '' : 's')],
                ['expiring', 'bi-hourglass-split text-warning', a.expiring + ' batch' + (a.expiring === 1 ? '' : 'es') + ' expiring soon'],
                ['expiring', 'bi-x-octagon text-danger', a.expired + ' expired batch' + (a.expired === 1 ? '' : 'es') + ' with stock']
            ];
            document.getElementById('alertMenu').innerHTML = '<li class="dropdown-header">Inventory alerts</li>' + items.map(function (i) {
                return '<li><a class="dropdown-item" href="#/' + i[0] + '"><i class="bi ' + i[1] + ' me-2"></i>' + W.esc(i[2]) + '</a></li>';
            }).join('');
        } catch (e) { }
    };

    async function navigate() {
        var token = ++navToken;
        var id = (window.location.hash.replace(/^#\/?/, '') || 'dashboard').split('?')[0];
        var view = document.getElementById('view');
        var route = W.routes[id];
        document.body.classList.remove('sidebar-open');
        if (!route) {
            view.innerHTML = '<div class="alert alert-warning">Page not found.</div>';
            return;
        }
        if (route.admin && !W.isAdmin()) {
            view.innerHTML = '<div class="alert alert-danger">You do not have permission to view this page.</div>';
            return;
        }
        document.querySelectorAll('#menu a').forEach(function (a) { a.classList.toggle('active', a.dataset.route === id); });
        document.getElementById('topTitle').textContent = route.title;
        document.title = route.title + ' - WMS';
        view.innerHTML = '<div class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2"></span>Loading...</div>';
        try {
            var holder = document.createElement('div');
            await route.render(holder);
            if (token !== navToken) { return; }
            view.innerHTML = '';
            while (holder.firstChild) { view.appendChild(holder.firstChild); }
            if (route.after) { route.after(view); }
        } catch (e) {
            if (token === navToken) { view.innerHTML = '<div class="alert alert-danger">' + W.esc(e.message) + '</div>'; }
        }
        W.refreshAlerts();
    }

    async function boot() {
        try {
            var me = await W.api('auth', 'me', { silent: true });
            if (!me.data.user) {
                window.location.replace('index.html');
                return;
            }
            W.user = me.data.user;
            W.csrf = me.data.csrf;
            W.appInfo = me.data.app;
            await W.loadLookups();
        } catch (e) {
            document.getElementById('view').innerHTML = '<div class="alert alert-danger">' + W.esc(e.message) + '</div>';
            return;
        }
        document.getElementById('userName').textContent = W.user.name;
        document.getElementById('userRole').textContent = W.user.role;
        buildMenu();
        document.getElementById('sidebarToggle').addEventListener('click', function () { document.body.classList.toggle('sidebar-open'); });
        document.getElementById('sidebarBackdrop').addEventListener('click', function () { document.body.classList.remove('sidebar-open'); });
        document.getElementById('logoutBtn').addEventListener('click', async function () {
            try { await W.post('auth', 'logout', {}); } catch (e) { }
            window.location.replace('index.html');
        });
        document.getElementById('changePwBtn').addEventListener('click', function () {
            W.formModal({
                title: 'Change password',
                size: 'md',
                fields: [
                    { name: 'current_password', label: 'Current password', type: 'password', required: true, col: 12, autocomplete: 'current-password' },
                    { name: 'new_password', label: 'New password', type: 'password', required: true, col: 12, hint: 'At least 8 characters with a letter and a number.', autocomplete: 'new-password' },
                    { name: 'confirm_password', label: 'Confirm new password', type: 'password', required: true, col: 12, autocomplete: 'new-password' }
                ],
                onSubmit: async function (data) {
                    var res = await W.post('auth', 'change_password', data);
                    W.toast(res.message);
                }
            });
        });
        window.addEventListener('hashchange', navigate);
        navigate();
    }

    window.addEventListener('DOMContentLoaded', boot);
}());
