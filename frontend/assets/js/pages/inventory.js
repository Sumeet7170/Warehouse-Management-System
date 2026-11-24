(function () {
    var W = window.W;

    function bindStockPicker(m, form, o) {
        var batchSel = form.elements.batch_id;
        var qtyInput = form.elements.quantity;
        var picked = m.el.querySelector('[data-picked]');
        var current = null;
        var loadSeq = 0;

        async function loadBatches(selectId) {
            var mySeq = ++loadSeq;
            var wh = form.elements.warehouse_id.value;
            var pid = form.elements.product_id.value;
            var html = '<option value="">' + (wh && pid ? 'Select batch...' : 'Choose warehouse and product first') + '</option>';
            batchSel.innerHTML = html;
            if (!wh || !pid) { return; }
            try {
                var rows = (await W.get('inventory', 'available', { product_id: pid, warehouse_id: wh })).data;
                if (mySeq !== loadSeq) { return; }
                var type = form.elements.adjustment_type ? form.elements.adjustment_type.value : 'decrease';
                html = '<option value="">' + (rows.length ? 'Select batch...' : 'No batches with stock') + '</option>';
                rows.forEach(function (b) {
                    html += '<option value="' + b.id + '" data-qty="' + b.quantity + '">' + W.esc(b.batch_number) + ' · ' + W.num(b.quantity) + ' units' + (b.expiry_date ? ' · exp ' + W.date(b.expiry_date) + (Number(b.is_expired) === 1 ? ' (EXPIRED)' : '') : '') + '</option>';
                });
                if (o.allowNew && type === 'increase') { html += '<option value="__new">+ Add to a new batch</option>'; }
                batchSel.innerHTML = html;
                if (selectId) { batchSel.value = String(selectId); batchSel.dispatchEvent(new Event('change')); }
            } catch (e) { W.error(e); }
        }

        function toggleNew() {
            if (!o.allowNew) { return; }
            var show = batchSel.value === '__new';
            ['batch_number', 'expiry_date', 'unit_cost'].forEach(function (n) {
                form.elements[n].closest('[class*="col-md-"]').classList.toggle('d-none', !show);
            });
        }

        function pick(p) {
            current = p;
            form.elements.product_id.value = p.id;
            picked.innerHTML = '<span class="badge text-bg-primary">' + W.esc(p.name) + '</span> <span class="text-muted small">' + W.esc(p.sku) + '</span>';
            if (form.elements.unit_cost && !form.elements.unit_cost.value && p.cost_price) { form.elements.unit_cost.value = p.cost_price; }
            loadBatches();
        }

        W.productSearch(m.el.querySelector('[data-product]'), { placeholder: 'Search product or scan barcode', onPick: pick });
        form.elements.warehouse_id.addEventListener('change', function () { loadBatches(); });
        if (form.elements.adjustment_type) { form.elements.adjustment_type.addEventListener('change', function () { loadBatches(); toggleNew(); }); }
        batchSel.addEventListener('change', function () {
            toggleNew();
            var opt = batchSel.options[batchSel.selectedIndex];
            if (opt && opt.dataset.qty) { qtyInput.max = opt.dataset.qty; qtyInput.placeholder = 'Max ' + opt.dataset.qty; } else { qtyInput.removeAttribute('max'); qtyInput.placeholder = ''; }
        });
        toggleNew();
        loadBatches();
        if (o.prefill) {
            pick(o.prefill.product);
            if (o.prefill.batch_id) { loadBatches(o.prefill.batch_id).then(function () { if (o.prefill.quantity) { qtyInput.value = o.prefill.quantity; } }); }
        }
        return { current: function () { return current; } };
    }

    var pickerHtml = '<label class="form-label">Product<span class="text-danger"> *</span></label><div data-product></div><div class="mt-1" data-picked><span class="text-muted small">No product selected</span></div><div class="invalid-feedback" data-err="product_id"></div>';

    function adjustmentForm(lv) {
        if (!W.lookups.warehouses || !W.lookups.warehouses.length) {
            W.confirm({
                title: 'Warehouse required',
                message: 'No warehouses found. Please create at least one warehouse before recording stock adjustments.',
                confirmText: 'Go to Warehouses',
                danger: false
            }).then(function (ok) {
                if (ok) { window.location.hash = '#/warehouses'; }
            });
            return;
        }
        var key = W.uid();
        W.formModal({
            title: 'New stock adjustment',
            size: 'lg',
            submitText: 'Record adjustment',
            values: { adjustment_type: 'decrease' },
            fields: [
                { name: 'warehouse_id', label: 'Warehouse', type: 'select', options: W.whOptions(), required: true, col: 6 },
                { type: 'html', html: pickerHtml, col: 6, name: 'product_html' },
                { name: 'product_id', type: 'hidden' },
                { name: 'adjustment_type', label: 'Adjustment type', type: 'select', options: [{ value: 'increase', label: 'Increase stock' }, { value: 'decrease', label: 'Decrease stock' }], required: true, placeholder: null, col: 4 },
                { name: 'batch_id', label: 'Batch', type: 'select', options: [], required: true, col: 5, placeholder: 'Choose warehouse and product first' },
                { name: 'quantity', label: 'Quantity', type: 'number', min: 1, step: 1, required: true, col: 3 },
                { name: 'batch_number', label: 'New batch number', maxlength: 60, col: 4 },
                { name: 'expiry_date', label: 'Expiry date', type: 'date', col: 4 },
                { name: 'unit_cost', label: 'Unit cost', type: 'number', min: 0, step: '0.01', col: 4 },
                { name: 'reason', label: 'Reason', required: true, maxlength: 255, col: 12, hint: 'For example: stock count variance, found stock, data entry correction.' }
            ],
            onReady: function (m, form) { bindStockPicker(m, form, { allowNew: true }); },
            onSubmit: async function (data) {
                var isNew = data.batch_id === '__new';
                var body = { warehouse_id: data.warehouse_id, product_id: data.product_id, adjustment_type: data.adjustment_type, quantity: data.quantity, reason: data.reason, idempotency_key: key };
                if (isNew) {
                    body.batch_number = data.batch_number;
                    body.expiry_date = data.expiry_date;
                    body.unit_cost = data.unit_cost;
                } else {
                    body.batch_id = data.batch_id;
                }
                var res = await W.post('adjustments', 'save', body);
                W.toast(res.message);
                lv.refresh();
                W.refreshAlerts();
            }
        });
    }

    function damageForm(lv, prefill) {
        if (!W.lookups.warehouses || !W.lookups.warehouses.length) {
            W.confirm({
                title: 'Warehouse required',
                message: 'No warehouses found. Please create at least one warehouse before recording damaged stock.',
                confirmText: 'Go to Warehouses',
                danger: false
            }).then(function (ok) {
                if (ok) { window.location.hash = '#/warehouses'; }
            });
            return;
        }
        var key = W.uid();
        W.formModal({
            title: 'Record damaged stock',
            size: 'lg',
            submitText: 'Record damage',
            values: { damage_date: W.today(), warehouse_id: prefill ? prefill.warehouse_id : '', reason: prefill ? prefill.reason : '' },
            fields: [
                { name: 'warehouse_id', label: 'Warehouse', type: 'select', options: W.whOptions(), required: true, col: 6 },
                { type: 'html', html: pickerHtml, col: 6, name: 'product_html' },
                { name: 'product_id', type: 'hidden' },
                { name: 'batch_id', label: 'Batch', type: 'select', options: [], required: true, col: 6, placeholder: 'Choose warehouse and product first' },
                { name: 'quantity', label: 'Damaged quantity', type: 'number', min: 1, step: 1, required: true, col: 3 },
                { name: 'damage_date', label: 'Damage date', type: 'date', required: true, max: W.today(), col: 3 },
                { name: 'reason', label: 'Reason', required: true, maxlength: 255, col: 12, hint: 'Damaged quantity is removed from sellable inventory immediately.' }
            ],
            onReady: function (m, form) { bindStockPicker(m, form, { allowNew: false, prefill: prefill }); },
            onSubmit: async function (data) {
                var res = await W.post('damaged', 'save', {
                    warehouse_id: data.warehouse_id, product_id: data.product_id, batch_id: data.batch_id, quantity: data.quantity,
                    reason: data.reason, damage_date: data.damage_date, idempotency_key: key
                });
                W.toast(res.message);
                if (lv) { lv.refresh(); }
                W.refreshAlerts();
            }
        });
    }

    async function batchesModal(row) {
        var rows = (await W.get('inventory', 'batches', { product_id: row.product_id, warehouse_id: row.warehouse_id, per_page: 100 })).data;
        W.modal({
            title: row.name + ' · ' + row.warehouse_name,
            size: 'lg',
            body: W.simpleTable(['Batch', 'Received', 'Expiry', { label: 'Qty', end: true }, { label: 'Unit cost', end: true }], rows.map(function (b) {
                return '<tr><td>' + W.esc(b.batch_number) + '</td><td>' + W.date(b.received_at) + '</td><td>' + W.expiryBadge(b.expiry_date, b.days_to_expiry) + '</td><td class="text-end">' + W.num(b.quantity) + '</td><td class="text-end">' + W.money(b.unit_cost) + '</td></tr>';
            }), 'No batches with stock.')
        });
    }

    W.routes.stock = {
        title: 'Stock Levels',
        render: async function (el) {
            el.innerHTML = W.pageHeader('Stock Levels', 'Separate quantities for every product in every warehouse') + '<div id="lv"></div>';
            new W.ListView(el.querySelector('#lv'), {
                resource: 'inventory',
                action: 'stock',
                empty: 'No stock recorded in any warehouse yet.',
                emptyAction: {
                    label: 'New purchase',
                    icon: 'cart-plus',
                    onClick: function () { window.location.hash = '#/purchases'; }
                },
                filters: [
                    { name: 'search', type: 'search', placeholder: 'Search product, SKU or barcode', width: 3 },
                    { name: 'warehouse_id', type: 'select', options: function () { return W.whOptions(false); }, all: 'All warehouses', width: 2 },
                    { name: 'category_id', type: 'select', options: W.categoryOptions, all: 'All categories', width: 2 },
                    { name: 'level', type: 'select', options: [{ value: 'low', label: 'Below minimum' }, { value: 'expired', label: 'Has expired stock' }], all: 'All stock', width: 2 }
                ],
                columns: [
                    { label: 'Product', render: function (r) { return '<span class="fw-semibold">' + W.esc(r.name) + '</span><div class="small text-muted">' + W.esc(r.sku) + ' · ' + W.esc(r.category_name || 'Uncategorised') + '</div>'; } },
                    { label: 'Warehouse', render: function (r) { return W.esc(r.warehouse_name); } },
                    { label: 'Total', align: 'end', render: function (r) { return W.num(r.total_qty); } },
                    {
                        label: 'Sellable', align: 'end', render: function (r) {
                            var low = Number(r.min_stock_level) > 0 && Number(r.sellable_qty) < Number(r.min_stock_level);
                            return '<span class="badge text-bg-' + (low ? 'danger' : 'success') + '">' + W.num(r.sellable_qty) + '</span>';
                        }
                    },
                    { label: 'Expired', align: 'end', render: function (r) { return Number(r.expired_qty) > 0 ? '<span class="badge text-bg-warning">' + W.num(r.expired_qty) + '</span>' : '<span class="text-muted">0</span>'; } },
                    { label: 'Min', align: 'end', render: function (r) { return W.num(r.min_stock_level); } },
                    { label: 'Batches', align: 'end', render: function (r) { return W.num(r.batch_count); } },
                    { label: 'Value', align: 'end', render: function (r) { return W.money(r.stock_value); } },
                    { label: '', align: 'end', actions: function () { return W.actionBtn('batches', 'collection', 'View batches'); } }
                ],
                onAction: function (act, row) { if (act === 'batches') { batchesModal(row).catch(W.error); } }
            });
        }
    };

    W.routes.batches = {
        title: 'Batches',
        render: async function (el) {
            el.innerHTML = W.pageHeader('Batches', 'Inventory tracked by batch number and warehouse, oldest first') + '<div id="lv"></div>';
            new W.ListView(el.querySelector('#lv'), {
                resource: 'inventory',
                action: 'batches',
                empty: 'No inventory batches exist yet.',
                emptyAction: {
                    label: 'New purchase',
                    icon: 'cart-plus',
                    onClick: function () { window.location.hash = '#/purchases'; }
                },
                filters: [
                    { name: 'search', type: 'search', placeholder: 'Search product, SKU or batch', width: 3 },
                    { name: 'warehouse_id', type: 'select', options: function () { return W.whOptions(false); }, all: 'All warehouses', width: 2 },
                    { name: 'expiry', type: 'select', options: [{ value: 'valid', label: 'Valid' }, { value: 'expiring', label: 'Expiring soon' }, { value: 'expired', label: 'Expired' }], all: 'Any expiry', width: 2 },
                    { name: 'include_empty', type: 'select', options: [{ value: '1', label: 'Include empty batches' }], all: 'Only batches with stock', width: 3 }
                ],
                columns: [
                    { label: 'Product', render: function (r) { return '<span class="fw-semibold">' + W.esc(r.product_name) + '</span><div class="small text-muted">' + W.esc(r.sku) + '</div>'; } },
                    { label: 'Warehouse', render: function (r) { return W.esc(r.warehouse_name); } },
                    { label: 'Batch', render: function (r) { return '<code>' + W.esc(r.batch_number) + '</code>'; } },
                    { label: 'Received', render: function (r) { return W.date(r.received_at); } },
                    { label: 'Expiry', render: function (r) { return W.expiryBadge(r.expiry_date, r.days_to_expiry); } },
                    { label: 'Qty / Initial', align: 'end', render: function (r) { return '<strong>' + W.num(r.quantity) + '</strong> / ' + W.num(r.initial_quantity); } },
                    { label: 'Unit cost', align: 'end', render: function (r) { return W.money(r.unit_cost); } }
                ]
            });
        }
    };

    W.routes.lowstock = {
        title: 'Low Stock',
        render: async function (el) {
            el.innerHTML = W.pageHeader('Low Stock Alerts', 'Products whose non-expired stock is below the minimum stock level') + '<div id="lv"></div>';
            new W.ListView(el.querySelector('#lv'), {
                resource: 'inventory',
                action: 'low_stock',
                empty: 'Great news — no products are below their minimum stock level.',
                emptyAction: {
                    label: 'View products',
                    icon: 'box-seam',
                    onClick: function () { window.location.hash = '#/products'; }
                },
                filters: [
                    { name: 'search', type: 'search', placeholder: 'Search product or SKU', width: 4 },
                    { name: 'warehouse_id', type: 'select', options: function () { return W.whOptions(false); }, all: 'All warehouses (combined)', width: 3 }
                ],
                columns: [
                    { label: 'Product', render: function (r) { return '<span class="fw-semibold">' + W.esc(r.name) + '</span><div class="small text-muted">' + W.esc(r.sku) + '</div>'; } },
                    { label: 'Category', render: function (r) { return W.esc(r.category_name || '—'); } },
                    { label: 'Current stock', align: 'end', render: function (r) { return W.num(r.stock) + ' ' + W.esc(r.unit); } },
                    { label: 'Minimum', align: 'end', render: function (r) { return W.num(r.min_stock_level); } },
                    { label: 'Shortfall', align: 'end', render: function (r) { return '<strong class="text-danger">' + W.num(Number(r.min_stock_level) - Number(r.stock)) + '</strong>'; } },
                    { label: 'Level', render: function (r) { return Number(r.stock) === 0 ? W.badge('Out of stock', 'danger') : W.badge('Low stock', 'warning'); } }
                ]
            });
        }
    };

    W.routes.expiring = {
        title: 'Expiry Alerts',
        render: async function (el) {
            el.innerHTML = W.pageHeader('Expiry Alerts', 'Batches that are expired or expiring within ' + ((W.appInfo && W.appInfo.expiry_alert_days) || 30) + ' days. Expired stock is blocked from sales.') + '<div id="lv"></div>';
            var lv = new W.ListView(el.querySelector('#lv'), {
                resource: 'inventory',
                action: 'expiring',
                empty: 'No batches are expired or close to expiry.',
                emptyAction: {
                    label: 'View stock levels',
                    icon: 'stack',
                    onClick: function () { window.location.hash = '#/stock'; }
                },
                filters: [
                    { name: 'mode', type: 'select', options: [{ value: 'expiring', label: 'Expiring soon' }, { value: 'expired', label: 'Already expired' }], all: 'Expired and expiring', width: 3 },
                    { name: 'warehouse_id', type: 'select', options: function () { return W.whOptions(false); }, all: 'All warehouses', width: 3 }
                ],
                columns: [
                    { label: 'Product', render: function (r) { return '<span class="fw-semibold">' + W.esc(r.product_name) + '</span><div class="small text-muted">' + W.esc(r.sku) + '</div>'; } },
                    { label: 'Warehouse', render: function (r) { return W.esc(r.warehouse_name); } },
                    { label: 'Batch', render: function (r) { return '<code>' + W.esc(r.batch_number) + '</code>'; } },
                    { label: 'Expiry', render: function (r) { return W.expiryBadge(r.expiry_date, r.days_to_expiry); } },
                    { label: 'Quantity', align: 'end', render: function (r) { return W.num(r.quantity); } },
                    { label: 'Value', align: 'end', render: function (r) { return W.money(Number(r.quantity) * Number(r.unit_cost)); } },
                    {
                        label: '', align: 'end', actions: function (r) {
                            return Number(r.days_to_expiry) < 0 ? '<button type="button" class="btn btn-sm btn-outline-danger" data-act="writeoff"><i class="bi bi-trash3 me-1"></i>Write off</button>' : '';
                        }
                    }
                ],
                onAction: function (act, row) {
                    if (act === 'writeoff') {
                        damageForm(lv, { warehouse_id: row.warehouse_id, reason: 'Expired stock write-off', product: { id: row.product_id, name: row.product_name, sku: row.sku }, batch_id: row.id, quantity: row.quantity });
                    }
                }
            });
        }
    };

    W.routes.adjustments = {
        title: 'Stock Adjustments',
        render: async function (el) {
            el.innerHTML = W.pageHeader('Stock Adjustments', 'Increase or decrease stock with a reason and full history', '<button class="btn btn-primary" id="newAdj"><i class="bi bi-plus-lg me-1"></i>New adjustment</button>') + '<div id="lv"></div>';
            var lv = new W.ListView(el.querySelector('#lv'), {
                resource: 'adjustments',
                empty: 'No stock adjustments recorded yet.',
                emptyAction: {
                    label: 'New adjustment',
                    icon: 'sliders',
                    onClick: function () { adjustmentForm(lv); }
                },
                filters: [
                    { name: 'search', type: 'search', placeholder: 'Search product, batch, reason', width: 3 },
                    { name: 'warehouse_id', type: 'select', options: function () { return W.whOptions(false); }, all: 'All warehouses', width: 2 },
                    { name: 'type', type: 'select', options: [{ value: 'increase', label: 'Increase' }, { value: 'decrease', label: 'Decrease' }], all: 'Any type', width: 2 },
                    { name: 'from', type: 'date', label: 'From', width: 2 },
                    { name: 'to', type: 'date', label: 'To', width: 2 }
                ],
                columns: [
                    { label: 'Adjustment', render: function (r) { return '<code>' + W.esc(r.adjustment_no) + '</code><div class="small text-muted">' + W.datetime(r.created_at) + '</div>'; } },
                    { label: 'Product', render: function (r) { return W.esc(r.product_name) + '<div class="small text-muted">' + W.esc(r.sku) + '</div>'; } },
                    { label: 'Warehouse', render: function (r) { return W.esc(r.warehouse_name); } },
                    { label: 'Batch', render: function (r) { return '<code>' + W.esc(r.batch_number) + '</code>'; } },
                    { label: 'Change', align: 'end', render: function (r) { return W.signed(r.adjustment_type === 'increase' ? r.quantity : -r.quantity); } },
                    { label: 'Reason', render: function (r) { return W.esc(r.reason); } },
                    { label: 'By', render: function (r) { return W.esc(r.user_name); } }
                ]
            });
            el.querySelector('#newAdj').addEventListener('click', function () { adjustmentForm(lv); });
        }
    };

    W.routes.damaged = {
        title: 'Damaged Stock',
        render: async function (el) {
            el.innerHTML = W.pageHeader('Damaged Stock', 'Damaged goods are removed from sellable inventory', '<button class="btn btn-danger" id="newDmg"><i class="bi bi-plus-lg me-1"></i>Record damage</button>') + '<div id="lv"></div>';
            var lv = new W.ListView(el.querySelector('#lv'), {
                resource: 'damaged',
                empty: 'No damaged stock records found.',
                emptyAction: {
                    label: 'Record damage',
                    icon: 'exclamation-octagon',
                    variant: 'danger',
                    onClick: function () { damageForm(lv, null); }
                },
                filters: [
                    { name: 'search', type: 'search', placeholder: 'Search product, batch, reason', width: 3 },
                    { name: 'warehouse_id', type: 'select', options: function () { return W.whOptions(false); }, all: 'All warehouses', width: 2 },
                    { name: 'from', type: 'date', label: 'From', width: 2 },
                    { name: 'to', type: 'date', label: 'To', width: 2 }
                ],
                columns: [
                    { label: 'Record', render: function (r) { return '<code>' + W.esc(r.damage_no) + '</code><div class="small text-muted">' + W.date(r.damage_date) + '</div>'; } },
                    { label: 'Product', render: function (r) { return W.esc(r.product_name) + '<div class="small text-muted">' + W.esc(r.sku) + '</div>'; } },
                    { label: 'Warehouse', render: function (r) { return W.esc(r.warehouse_name); } },
                    { label: 'Batch', render: function (r) { return '<code>' + W.esc(r.batch_number) + '</code>'; } },
                    { label: 'Qty', align: 'end', render: function (r) { return '<strong class="text-danger">' + W.num(r.quantity) + '</strong>'; } },
                    { label: 'Loss value', align: 'end', render: function (r) { return W.money(r.loss_value); } },
                    { label: 'Reason', render: function (r) { return W.esc(r.reason); } },
                    { label: 'By', render: function (r) { return W.esc(r.user_name); } }
                ]
            });
            el.querySelector('#newDmg').addEventListener('click', function () { damageForm(lv, null); });
        }
    };

    W.routes.transactions = {
        title: 'Transaction Log',
        render: async function (el) {
            el.innerHTML = W.pageHeader('Inventory Transaction Log', 'Every stock movement: user, date, warehouse, product, batch, quantity, type and reference') + '<div id="lv"></div>';
            new W.ListView(el.querySelector('#lv'), {
                resource: 'transactions',
                perPage: 20,
                empty: 'No stock transactions recorded yet.',
                emptyAction: {
                    label: 'New purchase',
                    icon: 'cart-plus',
                    onClick: function () { window.location.hash = '#/purchases'; }
                },
                filters: [
                    { name: 'search', type: 'search', placeholder: 'Search product, batch, reference', width: 3 },
                    { name: 'warehouse_id', type: 'select', options: function () { return W.whOptions(false); }, all: 'All warehouses', width: 2 },
                    {
                        name: 'type', type: 'select', all: 'All types', width: 2, options: Object.keys(W.txnTypes).map(function (k) { return { value: k, label: W.txnTypes[k][0] }; })
                    },
                    { name: 'from', type: 'date', label: 'From', width: 2 },
                    { name: 'to', type: 'date', label: 'To', width: 2 }
                ],
                columns: [
                    { label: 'Date', class: 'text-nowrap small', render: function (r) { return W.datetime(r.txn_date); } },
                    { label: 'Type', render: function (r) { return W.txnBadge(r.txn_type); } },
                    { label: 'Product', render: function (r) { return W.esc(r.product_name) + '<div class="small text-muted">' + W.esc(r.sku) + '</div>'; } },
                    { label: 'Warehouse', render: function (r) { return W.esc(r.warehouse_name); } },
                    { label: 'Batch', render: function (r) { return '<code>' + W.esc(r.batch_number) + '</code>'; } },
                    { label: 'Qty', align: 'end', render: function (r) { return W.signed(r.quantity_change); } },
                    { label: 'Balance', align: 'end', render: function (r) { return W.num(r.balance_after); } },
                    { label: 'Reference', render: function (r) { return '<code>' + W.esc(r.reference_no) + '</code>' + (r.notes ? '<div class="small text-muted">' + W.esc(r.notes) + '</div>' : ''); } },
                    { label: 'User', render: function (r) { return W.esc(r.user_name); } }
                ]
            });
        }
    };
}());
