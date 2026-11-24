(function () {
    var W = window.W;

    async function showPurchase(id) {
        var p = (await W.get('purchases', 'get', { id: id })).data;
        var rows = p.items.map(function (i) {
            return '<tr><td>' + W.esc(i.product_name) + '<div class="small text-muted">' + W.esc(i.sku) + '</div></td><td>' + W.esc(i.batch_number) + '</td><td>' + (i.expiry_date ? W.date(i.expiry_date) : '—') + '</td><td class="text-end">' + W.num(i.quantity) + '</td><td class="text-end">' + W.num(i.received_qty) + '</td><td class="text-end">' + W.money(i.unit_cost) + '</td><td class="text-end">' + W.money(i.line_total) + '</td></tr>';
        });
        W.modal({
            title: 'Purchase ' + p.purchase_no,
            size: 'xl',
            body: '<div class="row g-3 mb-3">' + W.kv('Vendor', W.esc(p.vendor_name)) + W.kv('Warehouse', W.esc(p.warehouse_name)) + W.kv('Purchase date', W.date(p.purchase_date)) + W.kv('Status', W.statusBadge('purchase', p.status)) +
                W.kv('Created by', W.esc(p.created_by_name)) + W.kv('Received at', p.received_at ? W.datetime(p.received_at) : '—') + W.kv('Total', W.money(p.total_amount)) + W.kv('Notes', W.esc(p.notes)) + '</div>' +
                W.simpleTable(['Product', 'Batch', 'Expiry', { label: 'Ordered', end: true }, { label: 'Received', end: true }, { label: 'Unit cost', end: true }, { label: 'Total', end: true }], rows)
        });
    }

    async function receiveModal(id, lv) {
        var p = (await W.get('purchases', 'get', { id: id })).data;
        var open = p.items.filter(function (i) { return Number(i.quantity) > Number(i.received_qty); });
        var rows = open.map(function (i) {
            var left = Number(i.quantity) - Number(i.received_qty);
            return '<tr><td>' + W.esc(i.product_name) + '<div class="small text-muted">' + W.esc(i.sku) + '</div></td><td>' + W.esc(i.batch_number) + '</td><td>' + (i.expiry_date ? W.date(i.expiry_date) : '—') +
                '</td><td class="text-end">' + W.num(left) + '</td><td style="width:140px"><input type="number" class="form-control form-control-sm" min="0" max="' + left + '" step="1" value="' + left + '" data-item="' + i.id + '"></td></tr>';
        });
        var m = W.modal({
            title: 'Receive stock · ' + p.purchase_no,
            size: 'lg',
            body: '<div class="alert alert-danger d-none" data-form-alert></div><p class="text-muted">Receiving into <strong>' + W.esc(p.warehouse_name) + '</strong>. Enter the quantity actually received for each line; leave the rest for a later delivery.</p>' +
                W.simpleTable(['Product', 'Batch', 'Expiry', { label: 'Outstanding', end: true }, { label: 'Receive now', end: true }], rows),
            footer: '<button class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success" id="doReceive"><i class="bi bi-box-arrow-in-down me-1"></i>Receive stock</button>'
        });
        var btn = m.el.querySelector('#doReceive');
        btn.addEventListener('click', async function () {
            var items = [];
            m.el.querySelectorAll('[data-item]').forEach(function (inp) {
                items.push({ item_id: Number(inp.dataset.item), quantity: parseInt(inp.value, 10) || 0 });
            });
            btn.disabled = true;
            try {
                var res = await W.post('purchases', 'receive', { id: id, items: items });
                W.toast(res.message);
                m.close();
                lv.refresh();
            } catch (e) {
                W.showFormErrors(m.el, {}, e.message);
                btn.disabled = false;
            }
        });
    }

    function newPurchase(lv) {
        if (!W.lookups.warehouses || !W.lookups.warehouses.length) {
            W.confirm({
                title: 'Warehouse required',
                message: 'No warehouses found. Please create at least one warehouse before recording a purchase.',
                confirmText: 'Go to Warehouses',
                danger: false
            }).then(function (ok) {
                if (ok) { window.location.hash = '#/warehouses'; }
            });
            return;
        }
        if (!W.lookups.vendors || !W.lookups.vendors.length) {
            W.confirm({
                title: 'Vendor required',
                message: 'No vendors found. Please add at least one vendor before recording a purchase.',
                confirmText: 'Go to Vendors',
                danger: false
            }).then(function (ok) {
                if (ok) { window.location.hash = '#/vendors'; }
            });
            return;
        }
        var editor = null;
        var key = W.uid();
        W.formModal({
            title: 'New purchase',
            size: 'xl',
            submitText: 'Save purchase',
            values: { purchase_date: W.today() },
            fields: [
                { name: 'vendor_id', label: 'Vendor', type: 'select', options: W.vendorOptions(), required: true, col: 4 },
                { name: 'warehouse_id', label: 'Receiving warehouse', type: 'select', options: W.whOptions(), required: true, col: 4 },
                { name: 'purchase_date', label: 'Purchase date', type: 'date', required: true, col: 4 },
                { name: 'notes', label: 'Notes', maxlength: 500, col: 8 },
                { name: 'receive_now', label: 'Receive stock immediately', type: 'checkbox', col: 4 }
            ],
            extraHtml: '<div class="col-12"><div data-items></div></div><div class="col-12 text-end fs-5">Total: <strong data-total>' + W.money(0) + '</strong></div>',
            onReady: function (m) {
                var total = m.el.querySelector('[data-total]');
                editor = new W.ItemsEditor(m.el.querySelector('[data-items]'), {
                    columns: [
                        { key: 'quantity', label: 'Quantity', type: 'number', min: 1, step: 1, default: function () { return '1'; } },
                        { key: 'unit_cost', label: 'Unit cost', type: 'number', min: 0, step: '0.01', default: function (p) { return p.cost_price; } },
                        { key: 'batch_number', label: 'Batch number', type: 'text', maxlength: 60, placeholder: 'Auto-generated' },
                        { key: 'expiry_date', label: 'Expiry date', type: 'date', enabled: function (p) { return Number(p.has_expiry) === 1; }, requiredFor: function (p) { return Number(p.has_expiry) === 1; } },
                        { label: 'Line total', align: 'end', computed: function (l) { return W.money((parseFloat(l.quantity) || 0) * (parseFloat(l.unit_cost) || 0)); } }
                    ],
                    onChange: function (ed) {
                        total.textContent = W.money(ed.lines.reduce(function (s, l) { return s + (parseFloat(l.quantity) || 0) * (parseFloat(l.unit_cost) || 0); }, 0));
                    }
                });
            },
            onSubmit: async function (data) {
                var res = await W.post('purchases', 'save', {
                    vendor_id: data.vendor_id, warehouse_id: data.warehouse_id, purchase_date: data.purchase_date, notes: data.notes,
                    receive_now: data.receive_now, idempotency_key: key, items: editor.items()
                });
                W.toast(res.message);
                lv.refresh();
            }
        });
    }

    W.routes.purchases = {
        title: 'Purchases',
        render: async function (el) {
            el.innerHTML = W.pageHeader('Purchases', 'Record purchase orders, vendors, quantities and received stock', '<button class="btn btn-primary" id="newPurchase"><i class="bi bi-plus-lg me-1"></i>New purchase</button>') + '<div id="lv"></div>';
            var lv = new W.ListView(el.querySelector('#lv'), {
                resource: 'purchases',
                empty: 'No purchases recorded yet.',
                emptyAction: {
                    label: 'New purchase',
                    icon: 'cart-plus',
                    onClick: function () { newPurchase(lv); }
                },
                filters: [
                    { name: 'search', type: 'search', placeholder: 'Search purchase no. or vendor', width: 3 },
                    { name: 'status', type: 'select', options: ['ordered', 'partial', 'received', 'cancelled'].map(function (s) { return { value: s, label: s.charAt(0).toUpperCase() + s.slice(1) }; }), all: 'All statuses', width: 2 },
                    { name: 'vendor_id', type: 'select', options: function () { return W.vendorOptions(false); }, all: 'All vendors', width: 2 },
                    { name: 'warehouse_id', type: 'select', options: function () { return W.whOptions(false); }, all: 'All warehouses', width: 2 },
                    { name: 'from', type: 'date', label: 'From', width: 2 },
                    { name: 'to', type: 'date', label: 'To', width: 2 }
                ],
                columns: [
                    { label: 'Purchase', render: function (r) { return '<code>' + W.esc(r.purchase_no) + '</code><div class="small text-muted">' + W.date(r.purchase_date) + '</div>'; } },
                    { label: 'Vendor', render: function (r) { return W.esc(r.vendor_name); } },
                    { label: 'Warehouse', render: function (r) { return W.esc(r.warehouse_name); } },
                    {
                        label: 'Received', align: 'end', render: function (r) {
                            var pct = Number(r.total_qty) ? Math.round(Number(r.received_qty) / Number(r.total_qty) * 100) : 0;
                            return W.num(r.received_qty) + ' / ' + W.num(r.total_qty) + '<div class="progress mt-1" style="height:4px"><div class="progress-bar bg-success" style="width:' + pct + '%"></div></div>';
                        }
                    },
                    { label: 'Total', align: 'end', render: function (r) { return W.money(r.total_amount); } },
                    { label: 'Status', render: function (r) { return W.statusBadge('purchase', r.status); } },
                    {
                        label: 'Actions', align: 'end', class: 'text-nowrap', actions: function (r) {
                            var html = W.actionBtn('view', 'eye', 'View');
                            if (r.status === 'ordered' || r.status === 'partial') { html += W.actionBtn('receive', 'box-arrow-in-down', 'Receive stock', 'success'); }
                            if (r.status === 'ordered' && W.isAdmin()) { html += W.actionBtn('cancel', 'x-circle', 'Cancel purchase', 'danger'); }
                            return html;
                        }
                    }
                ],
                onAction: async function (act, row) {
                    try {
                        if (act === 'view') { await showPurchase(row.id); }
                        if (act === 'receive') { await receiveModal(row.id, lv); }
                        if (act === 'cancel') {
                            var ok = await W.confirm({ title: 'Cancel purchase', message: 'Cancel purchase ' + row.purchase_no + '?', confirmText: 'Cancel purchase', danger: true });
                            if (!ok) { return; }
                            var res = await W.post('purchases', 'cancel', { id: row.id });
                            W.toast(res.message);
                            lv.refresh();
                        }
                    } catch (e) { W.error(e); }
                }
            });
            el.querySelector('#newPurchase').addEventListener('click', function () { newPurchase(lv); });
        }
    };
}());
