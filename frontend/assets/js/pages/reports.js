(function () {
    var W = window.W;

    var TYPES = [
        { id: 'stock', label: 'Stock levels', icon: 'stack' },
        { id: 'purchases', label: 'Purchases', icon: 'cart-plus' },
        { id: 'sales', label: 'Sales', icon: 'receipt' },
        { id: 'transfers', label: 'Transfers', icon: 'arrow-left-right' },
        { id: 'damaged', label: 'Damaged stock', icon: 'exclamation-octagon' },
        { id: 'movements', label: 'Inventory movements', icon: 'journal-text' }
    ];

    function filtersFor(type) {
        var search = { name: 'search', type: 'search', placeholder: 'Search product, SKU, reference', width: 3 };
        var wh = { name: 'warehouse_id', type: 'select', options: function () { return W.whOptions(false); }, all: 'All warehouses', width: 2 };
        var from = { name: 'from', type: 'date', label: 'From', width: 2 };
        var to = { name: 'to', type: 'date', label: 'To', width: 2 };
        if (type === 'stock') { return [search, wh, { name: 'category_id', type: 'select', options: W.categoryOptions, all: 'All categories', width: 2 }]; }
        if (type === 'purchases') { return [search, from, to, wh, { name: 'vendor_id', type: 'select', options: function () { return W.vendorOptions(false); }, all: 'All vendors', width: 2 }, { name: 'status', type: 'select', options: ['ordered', 'partial', 'received', 'cancelled'].map(function (s) { return { value: s, label: s }; }), all: 'Excluding cancelled', width: 2 }]; }
        if (type === 'sales') { return [search, from, to, wh, { name: 'customer_id', type: 'select', options: function () { return W.customerOptions(false); }, all: 'All customers', width: 2 }]; }
        if (type === 'transfers') { return [search, from, to, wh, { name: 'status', type: 'select', options: ['pending', 'in_transit', 'completed', 'cancelled'].map(function (s) { return { value: s, label: s.replace('_', ' ') }; }), all: 'All statuses', width: 2 }]; }
        if (type === 'damaged') { return [search, from, to, wh]; }
        return [search, from, to, wh, { name: 'txn_type', type: 'select', options: Object.keys(W.txnTypes).map(function (k) { return { value: k, label: W.txnTypes[k][0] }; }), all: 'All types', width: 2 }];
    }

    function cell(col, row) {
        var v = row[col[0]];
        switch (col[2]) {
            case 'int': return W.num(v);
            case 'money': return W.money(v);
            case 'date': return W.date(v);
            case 'datetime': return W.datetime(v);
            case 'signed': return W.signed(v);
            case 'type': return W.txnBadge(v);
            default:
                if (col[0] === 'doc_status') { return W.badge(String(v).replace('_', ' '), (W.statusColors.purchase[v] || W.statusColors.transfer[v] || 'secondary')); }
                if (col[0] === 'stock_status') { return W.badge(v, { 'Expired': 'danger', 'Expiring soon': 'warning', 'OK': 'success' }[v] || 'secondary'); }
                return W.esc(v);
        }
    }

    function summaryHtml(type, s) {
        var box = function (label, value) { return '<div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="text-muted small">' + label + '</div><div class="fs-5 fw-bold">' + value + '</div></div></div></div>'; };
        var html = box(type === 'movements' ? 'Transactions' : 'Rows', W.num(s.row_count));
        if (type === 'movements') { return html + box('Units in', W.num(s.total_in)) + box('Units out', W.num(s.total_out)) + box('Net change', W.signed(Number(s.total_in) - Number(s.total_out))); }
        html += box('Total units', W.num(s.total_qty));
        if (type === 'transfers') { return html; }
        var label = { stock: 'Stock value', purchases: 'Purchase amount', sales: 'Sales amount', damaged: 'Loss value' }[type];
        return html + box(label, W.money(s.total_amount));
    }

    W.routes.reports = {
        title: 'Reports',
        render: async function (el) {
            el.innerHTML = W.pageHeader('Reports', 'Stock levels, purchases, sales, transfers, damaged stock and inventory movements',
                '<button class="btn btn-outline-secondary" id="repPrint"><i class="bi bi-printer me-1"></i>Print</button><button class="btn btn-success" id="repExport"><i class="bi bi-filetype-csv me-1"></i>Export CSV</button>') +
                '<ul class="nav nav-pills mb-3 gap-1" id="repTabs">' + TYPES.map(function (t) { return '<li class="nav-item"><a class="nav-link" href="javascript:void(0)" data-type="' + t.id + '"><i class="bi bi-' + t.icon + ' me-1"></i>' + t.label + '</a></li>'; }).join('') + '</ul>' +
                '<div class="row g-2 mb-3" id="repSummary"></div><div id="repList"></div>';
            var current = null;
            var lv = null;
            var summary = el.querySelector('#repSummary');
            var tabs = el.querySelector('#repTabs');
            var listHost = el.querySelector('#repList');

            async function show(type) {
                current = type;
                tabs.querySelectorAll('a').forEach(function (a) { a.classList.toggle('active', a.dataset.type === type); });
                summary.innerHTML = '';
                var probe = (await W.get('reports', 'run', { type: type, per_page: 5 })).data;
                var columns = probe.columns.map(function (c) {
                    return { label: c[1], align: ['int', 'money', 'signed'].indexOf(c[2]) >= 0 ? 'end' : '', class: c[2] === 'date' || c[2] === 'datetime' ? 'text-nowrap' : '', render: function (row) { return cell(c, row); } };
                });
                lv = new W.ListView(listHost, {
                    resource: 'reports',
                    action: 'run',
                    perPage: 20,
                    empty: 'No records found for this report.',
                    emptyAction: {
                        label: 'View stock levels',
                        icon: 'stack',
                        onClick: function () { window.location.hash = '#/stock'; }
                    },
                    filters: filtersFor(type),
                    columns: columns,
                    params: function () { return { type: type }; },
                    rowsOf: function (res) { return res.data.rows; },
                    onLoad: function (res) { summary.innerHTML = summaryHtml(type, res.data.summary); }
                });
            }

            tabs.addEventListener('click', function (e) {
                var a = e.target.closest('[data-type]');
                if (a && a.dataset.type !== current) { show(a.dataset.type).catch(W.error); }
            });
            el.querySelector('#repExport').addEventListener('click', function () {
                var url = new URL(window.WMS_CONFIG.api, window.location.href);
                url.searchParams.set('r', 'reports');
                url.searchParams.set('a', 'export');
                url.searchParams.set('type', current);
                var params = lv.collect();
                Object.keys(params).forEach(function (k) { url.searchParams.set(k, params[k]); });
                window.location.href = url.toString();
            });
            el.querySelector('#repPrint').addEventListener('click', function () {
                var title = TYPES.filter(function (t) { return t.id === current; })[0].label + ' report';
                var table = lv.el.querySelector('table').cloneNode(true);
                table.className = 'table table-sm table-bordered';
                W.print(title, '<h4>' + W.esc(title) + '</h4><div class="text-muted mb-3">Generated ' + W.datetime(new Date().toISOString()) + ' by ' + W.esc(W.user.name) + '</div>' + table.outerHTML);
            });
            await show('stock');
        }
    };
}());
