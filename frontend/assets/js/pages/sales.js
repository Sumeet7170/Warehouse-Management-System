(function () {
    var W = window.W;

    async function showSale(id) {
        var s = (await W.get('sales', 'get', { id: id })).data;
        var rows = s.items.map(function (i) {
            return '<tr><td>' + W.esc(i.product_name) + '<div class="small text-muted">' + W.esc(i.sku) + '</div></td><td>' +
                i.batches.map(function (b) { return W.esc(b.batch_number) + ' × ' + W.num(b.quantity) + (b.expiry_date ? ' <span class="text-muted small">(exp ' + W.date(b.expiry_date) + ')</span>' : ''); }).join('<br>') +
                '</td><td class="text-end">' + W.num(i.quantity) + '</td><td class="text-end">' + W.money(i.unit_price) + '</td><td class="text-end">' + W.money(i.line_total) + '</td></tr>';
        });
        var m = W.modal({
            title: 'Sale ' + s.sale_no,
            size: 'xl',
            body: '<div class="row g-3 mb-3">' + W.kv('Customer', W.esc(s.customer_name || 'Walk-in customer')) + W.kv('Warehouse', W.esc(s.warehouse_name)) + W.kv('Sale date', W.date(s.sale_date)) + W.kv('Created by', W.esc(s.created_by_name)) + W.kv('Notes', W.esc(s.notes), 12) + '</div>' +
                W.simpleTable(['Product', 'Batches issued (FIFO)', { label: 'Qty', end: true }, { label: 'Unit price', end: true }, { label: 'Total', end: true }], rows) +
                '<div class="text-end fs-5 mt-3">Grand total: <strong>' + W.money(s.total_amount) + '</strong></div>',
            footer: '<button class="btn btn-light" data-bs-dismiss="modal">Close</button><button class="btn btn-primary" id="printSale"><i class="bi bi-printer me-1"></i>Print invoice</button>'
        });
        m.el.querySelector('#printSale').addEventListener('click', function () {
            var html = '<h3>Invoice ' + W.esc(s.sale_no) + '</h3><p class="mb-1"><strong>Date:</strong> ' + W.date(s.sale_date) + '<br><strong>Customer:</strong> ' + W.esc(s.customer_name || 'Walk-in customer') + '<br><strong>Warehouse:</strong> ' + W.esc(s.warehouse_name) + '</p>' +
                '<table class="table table-bordered mt-3"><thead><tr><th>Product</th><th>SKU</th><th class="text-end">Qty</th><th class="text-end">Unit price</th><th class="text-end">Total</th></tr></thead><tbody>' +
                s.items.map(function (i) { return '<tr><td>' + W.esc(i.product_name) + '</td><td>' + W.esc(i.sku) + '</td><td class="text-end">' + W.num(i.quantity) + '</td><td class="text-end">' + W.money(i.unit_price) + '</td><td class="text-end">' + W.money(i.line_total) + '</td></tr>'; }).join('') +
                '</tbody><tfoot><tr><th colspan="4" class="text-end">Grand total</th><th class="text-end">' + W.money(s.total_amount) + '</th></tr></tfoot></table>';
            W.print('Invoice ' + s.sale_no, html);
        });
    }

    function newSale(lv) {
        if (!W.lookups.warehouses || !W.lookups.warehouses.length) {
            W.confirm({
                title: 'Warehouse required',
                message: 'No warehouses found. Please create at least one warehouse before recording sales.',
                confirmText: 'Go to Warehouses',
                danger: false
            }).then(function (ok) {
                if (ok) { window.location.hash = '#/warehouses'; }
            });
            return;
        }
        var editor = null;
        var avail = {};
        var key = W.uid();
        var warehouseSelect = null;

        async function loadAvailability() {
            var wh = warehouseSelect.value;
            var ids = editor.productIds();
            if (!wh || !ids.length) { avail = {}; editor.render(); return; }
            try {
                avail = (await W.get('inventory', 'availability', { warehouse_id: wh, product_ids: ids.join(',') })).data;
                editor.render();
            } catch (e) { W.error(e); }
        }

        W.formModal({
            title: 'New sale',
            size: 'xl',
            submitText: 'Complete sale',
            values: { sale_date: W.today() },
            fields: [
                { name: 'warehouse_id', label: 'Sell from warehouse', type: 'select', options: W.whOptions(), required: true, col: 4 },
                { name: 'customer_id', label: 'Customer', type: 'select', options: W.customerOptions(), placeholder: 'Walk-in customer', col: 4 },
                { name: 'sale_date', label: 'Sale date', type: 'date', required: true, max: W.today(), col: 4 },
                { name: 'notes', label: 'Notes', maxlength: 500, col: 12 }
            ],
            extraHtml: '<div class="col-12"><div data-items></div></div><div class="col-12 d-flex justify-content-between align-items-center"><span class="text-muted small"><i class="bi bi-info-circle me-1"></i>Stock is issued automatically from the oldest non-expired batch first (FIFO). Expired stock cannot be sold.</span><span class="fs-5">Total: <strong data-total>' + W.money(0) + '</strong></span></div>',
            onReady: function (m, form) {
                warehouseSelect = form.elements.warehouse_id;
                var total = m.el.querySelector('[data-total]');
                editor = new W.ItemsEditor(m.el.querySelector('[data-items]'), {
                    columns: [
                        {
                            label: 'Available', align: 'end', display: function (l) {
                                var a = avail[l.product.id];
                                if (a === undefined) { return '<span class="text-muted">…</span>'; }
                                var over = (parseInt(l.quantity, 10) || 0) > a;
                                return '<span class="badge text-bg-' + (a === 0 ? 'danger' : (over ? 'warning' : 'success')) + '">' + W.num(a) + '</span>';
                            }
                        },
                        { key: 'quantity', label: 'Quantity', type: 'number', min: 1, step: 1, default: function () { return '1'; } },
                        { key: 'unit_price', label: 'Unit price', type: 'number', min: 0, step: '0.01', default: function (p) { return p.price; } },
                        { label: 'Line total', align: 'end', computed: function (l) { return W.money((parseFloat(l.quantity) || 0) * (parseFloat(l.unit_price) || 0)); } }
                    ],
                    onChange: function (ed, structural) {
                        total.textContent = W.money(ed.lines.reduce(function (s, l) { return s + (parseFloat(l.quantity) || 0) * (parseFloat(l.unit_price) || 0); }, 0));
                        if (structural) { loadAvailability(); }
                    }
                });
                warehouseSelect.addEventListener('change', loadAvailability);
            },
            onSubmit: async function (data) {
                var res = await W.post('sales', 'save', {
                    warehouse_id: data.warehouse_id, customer_id: data.customer_id, sale_date: data.sale_date, notes: data.notes,
                    idempotency_key: key, items: editor.items()
                });
                W.toast(res.message);
                lv.refresh();
                W.refreshAlerts();
                setTimeout(function () { showSale(res.data.id).catch(W.error); }, 400);
            }
        });
    }

    W.routes.sales = {
        title: 'Sales',
        render: async function (el) {
            el.innerHTML = W.pageHeader('Sales', 'Customer sales with automatic FIFO inventory deduction', '<button class="btn btn-primary" id="newSale"><i class="bi bi-plus-lg me-1"></i>New sale</button>') + '<div id="lv"></div>';
            var lv = new W.ListView(el.querySelector('#lv'), {
                resource: 'sales',
                empty: 'No sales recorded yet.',
                emptyAction: {
                    label: 'New sale',
                    icon: 'receipt',
                    onClick: function () { newSale(lv); }
                },
                filters: [
                    { name: 'search', type: 'search', placeholder: 'Search sale no. or customer', width: 3 },
                    { name: 'customer_id', type: 'select', options: function () { return W.customerOptions(false); }, all: 'All customers', width: 2 },
                    { name: 'warehouse_id', type: 'select', options: function () { return W.whOptions(false); }, all: 'All warehouses', width: 2 },
                    { name: 'from', type: 'date', label: 'From', width: 2 },
                    { name: 'to', type: 'date', label: 'To', width: 2 }
                ],
                columns: [
                    { label: 'Sale', render: function (r) { return '<code>' + W.esc(r.sale_no) + '</code><div class="small text-muted">' + W.date(r.sale_date) + '</div>'; } },
                    { label: 'Customer', render: function (r) { return W.esc(r.customer_name || 'Walk-in customer'); } },
                    { label: 'Warehouse', render: function (r) { return W.esc(r.warehouse_name); } },
                    { label: 'Qty sold', align: 'end', render: function (r) { return W.num(r.total_qty); } },
                    { label: 'Total', align: 'end', render: function (r) { return W.money(r.total_amount); } },
                    { label: 'Sold by', render: function (r) { return W.esc(r.created_by_name); } },
                    { label: 'Actions', align: 'end', actions: function () { return W.actionBtn('view', 'eye', 'View / print'); } }
                ],
                onAction: function (act, row) {
                    if (act === 'view') { showSale(row.id).catch(W.error); }
                }
            });
            el.querySelector('#newSale').addEventListener('click', function () { newSale(lv); });
        }
    };
}());
