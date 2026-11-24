(function () {
    var W = window.W;

    function stockLine(rows) {
        return W.simpleTable(['Warehouse', { label: 'Total', end: true }, { label: 'Sellable', end: true }], rows.map(function (r) {
            return '<tr><td>' + W.esc(r.warehouse_name) + '</td><td class="text-end">' + W.num(r.total_qty) + '</td><td class="text-end fw-semibold">' + W.num(r.sellable_qty) + '</td></tr>';
        }), 'No stock in any warehouse.');
    }

    async function showProduct(row) {
        var p = (await W.get('products', 'get', { id: row.id })).data;
        var body = '<div class="row g-3 mb-3">' +
            W.kv('SKU', W.esc(p.sku)) + W.kv('Barcode', W.esc(p.barcode)) + W.kv('Category', W.esc(p.category_name)) + W.kv('Unit', W.esc(p.unit)) +
            W.kv('Selling price', W.money(p.price)) + W.kv('Cost price', W.money(p.cost_price)) + W.kv('Minimum stock', W.num(p.min_stock_level)) +
            W.kv('Expiry tracking', Number(p.has_expiry) === 1 ? 'Yes' : 'No') + W.kv('Status', W.activeBadge(p.is_active)) + W.kv('Description', W.esc(p.description), 9) + '</div>' +
            '<h6>Stock by warehouse</h6>' + stockLine(p.stock_by_warehouse) +
            '<h6 class="mt-3">Batches (FIFO order)</h6>' +
            W.simpleTable(['Warehouse', 'Batch', 'Received', 'Expiry', { label: 'Qty', end: true }], p.batches.map(function (b) {
                return '<tr><td>' + W.esc(b.warehouse_name) + '</td><td>' + W.esc(b.batch_number) + '</td><td>' + W.date(b.received_at) + '</td><td>' + W.expiryBadge(b.expiry_date) + '</td><td class="text-end">' + W.num(b.quantity) + '</td></tr>';
            }), 'No batches with stock.');
        W.modal({ title: p.name, body: body, size: 'lg' });
    }

    async function showBarcode(row, lv) {
        var p = (await W.get('products', 'get', { id: row.id })).data;
        var code = p.barcode;
        var m = W.modal({
            title: 'Barcode · ' + p.name,
            size: 'md',
            body: code ? '<div class="text-center"><div class="label-box mx-auto mb-3"><div class="lb-name">' + W.esc(p.name) + '</div><div class="lb-sku">' + W.esc(p.sku) + '</div>' + W.barcodeSvg(code, 70) + '</div>' +
                '<div class="row g-2 justify-content-center align-items-end"><div class="col-6"><label class="form-label small">Labels to print</label><input type="number" class="form-control" id="bcCopies" min="1" max="200" value="12"></div></div></div>' :
                '<div class="alert alert-warning mb-0">This product has no barcode yet. Generate one to enable scanning and label printing.</div>',
            footer: '<button class="btn btn-light" data-bs-dismiss="modal">Close</button>' + (code ?
                '<button class="btn btn-primary" id="bcPrint"><i class="bi bi-printer me-1"></i>Print labels</button>' :
                '<button class="btn btn-primary" id="bcGen"><i class="bi bi-magic me-1"></i>Generate barcode</button>')
        });
        var print = m.el.querySelector('#bcPrint');
        if (print) {
            print.addEventListener('click', function () {
                var copies = Math.max(1, Math.min(200, parseInt(m.el.querySelector('#bcCopies').value, 10) || 1));
                var one = '<div class="label-box"><div class="lb-name">' + W.esc(p.name) + '</div><div class="lb-sku">' + W.esc(p.sku) + '</div>' + W.barcodeSvg(code, 50) + '</div>';
                W.print('Labels - ' + p.name, '<div class="label-sheet">' + new Array(copies + 1).join(one) + '</div>');
            });
        }
        var gen = m.el.querySelector('#bcGen');
        if (gen) {
            gen.addEventListener('click', async function () {
                gen.disabled = true;
                try {
                    var res = await W.post('products', 'save', {
                        id: p.id, sku: p.sku, name: p.name, category_id: p.category_id, unit: p.unit, price: p.price, cost_price: p.cost_price,
                        min_stock_level: p.min_stock_level, has_expiry: Number(p.has_expiry) === 1, description: p.description, is_active: Number(p.is_active) === 1, auto_barcode: true
                    });
                    W.toast('Barcode generated.');
                    m.close();
                    lv.refresh();
                    showBarcode(row, lv);
                } catch (e) {
                    W.error(e);
                    gen.disabled = false;
                }
            });
        }
    }

    W.routes.products = {
        title: 'Products',
        render: async function (el) {
            W.crudPage(el, {
                title: 'Products',
                subtitle: 'Manage product details, SKUs, pricing, categories and barcodes',
                noun: 'product',
                nounPlural: 'products',
                empty: 'No products in catalog yet. Add your first product to get started.',
                addLabel: 'Add product',
                resource: 'products',
                canDelete: function () { return W.isAdmin(); },
                filters: [
                    { name: 'search', type: 'search', placeholder: 'Search name, SKU or barcode', width: 4 },
                    { name: 'category_id', type: 'select', options: W.categoryOptions, all: 'All categories', width: 3 },
                    { name: 'status', type: 'select', options: [{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }], all: 'All statuses', width: 2 }
                ],
                columns: [
                    { label: 'SKU', render: function (r) { return '<code>' + W.esc(r.sku) + '</code>'; } },
                    { label: 'Product', render: function (r) { return '<div class="fw-semibold">' + W.esc(r.name) + (Number(r.has_expiry) === 1 ? ' <i class="bi bi-hourglass-split text-warning" title="Tracks expiry"></i>' : '') + '</div><small class="text-muted">' + W.esc(r.barcode || 'No barcode') + '</small>'; } },
                    { label: 'Category', render: function (r) { return W.esc(r.category_name || '—'); } },
                    { label: 'Price', align: 'end', render: function (r) { return W.money(r.price); } },
                    { label: 'Cost', align: 'end', render: function (r) { return W.money(r.cost_price); } },
                    {
                        label: 'Stock', align: 'end', render: function (r) {
                            var low = Number(r.min_stock_level) > 0 && Number(r.sellable_qty) < Number(r.min_stock_level);
                            return '<span class="badge text-bg-' + (low ? 'danger' : 'success') + '">' + W.num(r.sellable_qty) + ' ' + W.esc(r.unit) + '</span>';
                        }
                    },
                    { label: 'Min', align: 'end', render: function (r) { return W.num(r.min_stock_level); } },
                    { label: 'Status', render: function (r) { return W.activeBadge(r.is_active); } }
                ],
                extraActions: function () { return W.actionBtn('view', 'eye', 'View details') + W.actionBtn('barcode', 'upc', 'Barcode'); },
                onAction: function (act, row, lv) {
                    if (act === 'view') { showProduct(row).catch(W.error); }
                    if (act === 'barcode') { showBarcode(row, lv).catch(W.error); }
                },
                loadRecord: async function (row) { return (await W.get('products', 'get', { id: row.id })).data; },
                defaults: { unit: 'pcs', price: '0', cost_price: '0', min_stock_level: '0', is_active: 1 },
                fields: function () {
                    return [
                        { name: 'sku', label: 'SKU', required: true, maxlength: 60 },
                        { name: 'name', label: 'Product name', required: true, maxlength: 180 },
                        {
                            name: 'barcode', label: 'Barcode', maxlength: 64, hint: 'Scan with a barcode scanner, type it, or generate one.',
                            append: '<button type="button" class="btn btn-outline-secondary" id="genBarcode" title="Generate"><i class="bi bi-magic"></i></button><button type="button" class="btn btn-outline-secondary" id="scanBarcode" title="Scan with camera"><i class="bi bi-camera"></i></button>'
                        },
                        { name: 'category_id', label: 'Category', type: 'select', options: W.categoryOptions(), placeholder: 'Uncategorised' },
                        { name: 'unit', label: 'Unit', maxlength: 20, col: 3 },
                        { name: 'price', label: 'Selling price', type: 'number', min: 0, step: '0.01', required: true, col: 3 },
                        { name: 'cost_price', label: 'Cost price', type: 'number', min: 0, step: '0.01', col: 3 },
                        { name: 'min_stock_level', label: 'Minimum stock level', type: 'number', min: 0, step: '1', col: 3 },
                        { name: 'description', label: 'Description', type: 'textarea', col: 12, maxlength: 2000, rows: 2 },
                        { name: 'has_expiry', label: 'Product has an expiry date', type: 'checkbox', col: 6 },
                        { name: 'is_active', label: 'Active', type: 'checkbox', col: 6 }
                    ];
                },
                onFormReady: function (m, form) {
                    var host = document.createElement('div');
                    host.className = 'mt-2';
                    form.querySelector('[data-err="barcode"]').insertAdjacentElement('afterend', host);
                    m.el.querySelector('#genBarcode').addEventListener('click', async function () {
                        try { form.elements.barcode.value = (await W.get('products', 'generate_barcode')).data.barcode; } catch (e) { W.error(e); }
                    });
                    m.el.querySelector('#scanBarcode').addEventListener('click', function () {
                        if (host.innerHTML) { host.innerHTML = ''; return; }
                        var s = W.scanner(host, function (code) { form.elements.barcode.value = code; s.stop(); }, { continuous: false });
                    });
                }
            });
        }
    };

    W.routes.barcode = {
        title: 'Barcode Tools',
        render: async function (el) {
            el.innerHTML = W.pageHeader('Barcode Tools', 'Scan products for instant identification and print barcode labels') +
                '<div class="row g-3"><div class="col-lg-6"><div class="card h-100"><div class="card-header"><i class="bi bi-upc-scan me-2"></i>Scan / identify product</div><div class="card-body">' +
                '<div id="scanBox"></div><div id="scanResult" class="mt-3"><div class="text-muted">Scan a barcode with a USB scanner or the camera, or type a barcode / SKU and press Enter.</div></div></div></div></div>' +
                '<div class="col-lg-6"><div class="card h-100"><div class="card-header"><i class="bi bi-printer me-2"></i>Print barcode labels</div><div class="card-body">' +
                '<div id="labelSearch"></div><div class="table-responsive mt-3"><table class="table table-sm align-middle"><thead><tr><th>Product</th><th>Code</th><th style="width:110px">Copies</th><th></th></tr></thead><tbody id="labelRows"></tbody></table></div>' +
                '<div class="d-flex gap-2 justify-content-end"><button class="btn btn-outline-secondary" id="labelClear">Clear</button><button class="btn btn-primary" id="labelPrint"><i class="bi bi-printer me-1"></i>Print labels</button></div></div></div></div></div>';
            var result = el.querySelector('#scanResult');
            W.productSearch(el.querySelector('#scanBox'), {
                placeholder: 'Scan barcode or type barcode / SKU / name',
                onPick: async function (p) {
                    try {
                        var info = (await W.get('products', 'lookup', { code: p.barcode || p.sku })).data;
                        result.innerHTML = '<div class="row g-3"><div class="col-12"><h5 class="mb-0">' + W.esc(info.name) + '</h5><div class="text-muted">' + W.esc(info.sku) + ' · ' + W.esc(info.category_name || 'Uncategorised') + '</div></div>' +
                            W.kv('Price', W.money(info.price), 4) + W.kv('Min stock', W.num(info.min_stock_level), 4) + W.kv('Status', W.activeBadge(info.is_active), 4) +
                            '<div class="col-12 text-center">' + (info.barcode ? W.barcodeSvg(info.barcode, 60) : '<span class="text-muted">No barcode assigned</span>') + '</div>' +
                            '<div class="col-12"><h6>Stock by warehouse</h6>' + stockLine(info.stock_by_warehouse) + '</div></div>';
                    } catch (e) {
                        result.innerHTML = '<div class="alert alert-danger mb-0">' + W.esc(e.message) + '</div>';
                    }
                }
            });
            var labels = [];
            var rows = el.querySelector('#labelRows');
            function drawLabels() {
                rows.innerHTML = labels.length ? labels.map(function (l, i) {
                    return '<tr><td>' + W.esc(l.name) + '<div class="small text-muted">' + W.esc(l.sku) + '</div></td><td><code>' + W.esc(l.code) + '</code></td><td><input type="number" min="1" max="200" class="form-control form-control-sm" data-copies="' + i + '" value="' + l.copies + '"></td>' +
                        '<td><button class="btn btn-sm btn-outline-danger" data-del="' + i + '"><i class="bi bi-x"></i></button></td></tr>';
                }).join('') : '<tr><td colspan="4" class="text-center text-muted py-3">Search for products to add labels, or <a href="#/products" class="btn btn-sm btn-outline-primary ms-1"><i class="bi bi-plus-lg me-1"></i>Add product</a></td></tr>';
            }
            drawLabels();
            W.productSearch(el.querySelector('#labelSearch'), {
                placeholder: 'Add product to label sheet',
                onPick: function (p) {
                    var existing = labels.filter(function (l) { return l.id === p.id; })[0];
                    if (existing) { existing.copies += 1; } else { labels.push({ id: p.id, name: p.name, sku: p.sku, code: p.barcode || p.sku, copies: 1 }); }
                    drawLabels();
                }
            });
            rows.addEventListener('input', function (e) {
                if (e.target.dataset.copies !== undefined) { labels[Number(e.target.dataset.copies)].copies = Math.max(1, Math.min(200, parseInt(e.target.value, 10) || 1)); }
            });
            rows.addEventListener('click', function (e) {
                var b = e.target.closest('[data-del]');
                if (b) { labels.splice(Number(b.dataset.del), 1); drawLabels(); }
            });
            el.querySelector('#labelClear').addEventListener('click', function () { labels = []; drawLabels(); });
            el.querySelector('#labelPrint').addEventListener('click', function () {
                if (!labels.length) { W.toast('Add at least one product.', 'warning'); return; }
                var html = '';
                labels.forEach(function (l) {
                    var one = '<div class="label-box"><div class="lb-name">' + W.esc(l.name) + '</div><div class="lb-sku">' + W.esc(l.sku) + '</div>' + W.barcodeSvg(l.code, 50) + '</div>';
                    html += new Array(l.copies + 1).join(one);
                });
                W.print('Barcode labels', '<div class="label-sheet">' + html + '</div>');
            });
        }
    };

    W.routes.categories = {
        title: 'Categories',
        render: async function (el) {
            W.crudPage(el, {
                title: 'Categories',
                subtitle: 'Organise products into categories',
                noun: 'category',
                nounPlural: 'categories',
                empty: 'No categories created yet. Organize your products by adding categories.',
                addLabel: 'Add category',
                resource: 'categories',
                canAdd: W.isAdmin(),
                canEdit: function () { return W.isAdmin(); },
                canDelete: function () { return W.isAdmin(); },
                afterSave: W.loadLookups,
                filters: [{ name: 'search', type: 'search', placeholder: 'Search categories', width: 4 }],
                columns: [
                    { label: 'Name', render: function (r) { return '<span class="fw-semibold">' + W.esc(r.name) + '</span>'; } },
                    { label: 'Description', render: function (r) { return W.esc(r.description || '—'); } },
                    { label: 'Products', align: 'end', render: function (r) { return W.num(r.product_count); } }
                ],
                fields: function () {
                    return [
                        { name: 'name', label: 'Category name', required: true, maxlength: 100, col: 12 },
                        { name: 'description', label: 'Description', type: 'textarea', maxlength: 255, rows: 2, col: 12 }
                    ];
                }
            });
        }
    };

    W.routes.warehouses = {
        title: 'Warehouses',
        render: async function (el) {
            W.crudPage(el, {
                title: 'Warehouses',
                subtitle: 'Manage warehouse locations and see stock held in each',
                noun: 'warehouse',
                nounPlural: 'warehouses',
                empty: 'No warehouses created yet. Add your first warehouse location to begin managing inventory.',
                addLabel: 'Add warehouse',
                resource: 'warehouses',
                canAdd: W.isAdmin(),
                canEdit: function () { return W.isAdmin(); },
                canDelete: function () { return W.isAdmin(); },
                afterSave: W.loadLookups,
                defaults: { is_active: 1 },
                filters: [
                    { name: 'search', type: 'search', placeholder: 'Search warehouses', width: 4 },
                    { name: 'status', type: 'select', options: [{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }], all: 'All statuses', width: 2 }
                ],
                columns: [
                    { label: 'Code', render: function (r) { return '<code>' + W.esc(r.code) + '</code>'; } },
                    { label: 'Name', render: function (r) { return '<span class="fw-semibold">' + W.esc(r.name) + '</span><div class="small text-muted">' + W.esc(r.address || '') + '</div>'; } },
                    { label: 'Phone', render: function (r) { return W.esc(r.phone || '—'); } },
                    { label: 'Products', align: 'end', render: function (r) { return W.num(r.product_count); } },
                    { label: 'Units in stock', align: 'end', render: function (r) { return W.num(r.total_units); } },
                    { label: 'Status', render: function (r) { return W.activeBadge(r.is_active); } }
                ],
                fields: function () {
                    return [
                        { name: 'code', label: 'Code', required: true, maxlength: 20 },
                        { name: 'name', label: 'Warehouse name', required: true, maxlength: 120 },
                        { name: 'address', label: 'Address', maxlength: 255, col: 8 },
                        { name: 'phone', label: 'Phone', maxlength: 30, col: 4 },
                        { name: 'is_active', label: 'Active', type: 'checkbox', col: 12 }
                    ];
                }
            });
        }
    };
}());
