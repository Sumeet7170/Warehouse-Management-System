(function () {
    var W = window.W;

    function contactCell(r) {
        return '<div>' + W.esc(r.email || '—') + '</div><small class="text-muted">' + W.esc(r.phone || '') + '</small>';
    }

    W.routes.vendors = {
        title: 'Vendors',
        render: async function (el) {
            W.crudPage(el, {
                title: 'Vendors',
                subtitle: 'Supplier information and purchase history',
                noun: 'vendor',
                nounPlural: 'vendors',
                empty: 'No vendors added yet. Add suppliers you purchase inventory from.',
                addLabel: 'Add vendor',
                resource: 'vendors',
                canDelete: function () { return W.isAdmin(); },
                afterSave: W.loadLookups,
                defaults: { is_active: 1 },
                filters: [
                    { name: 'search', type: 'search', placeholder: 'Search name, contact, email or phone', width: 4 },
                    { name: 'status', type: 'select', options: [{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }], all: 'All statuses', width: 2 }
                ],
                columns: [
                    { label: 'Vendor', render: function (r) { return '<span class="fw-semibold">' + W.esc(r.name) + '</span><div class="small text-muted">' + W.esc(r.contact_person || '') + '</div>'; } },
                    { label: 'Contact', render: contactCell },
                    { label: 'Address', render: function (r) { return W.esc(r.address || '—'); } },
                    { label: 'Purchases', align: 'end', render: function (r) { return W.num(r.purchase_count); } },
                    { label: 'Total purchased', align: 'end', render: function (r) { return W.money(r.total_purchased); } },
                    { label: 'Status', render: function (r) { return W.activeBadge(r.is_active); } }
                ],
                extraActions: function () { return W.actionBtn('history', 'clock-history', 'Purchase history'); },
                onAction: async function (act, row) {
                    if (act !== 'history') { return; }
                    try {
                        var v = (await W.get('vendors', 'get', { id: row.id })).data;
                        W.modal({
                            title: v.name + ' · Purchase history',
                            size: 'lg',
                            body: '<div class="row g-3 mb-3">' + W.kv('Contact person', W.esc(v.contact_person)) + W.kv('Email', W.esc(v.email)) + W.kv('Phone', W.esc(v.phone)) + W.kv('Status', W.activeBadge(v.is_active)) + '</div>' +
                                W.simpleTable(['Purchase', 'Date', 'Warehouse', { label: 'Received / Ordered', end: true }, { label: 'Amount', end: true }, 'Status'], v.purchases.map(function (p) {
                                    return '<tr><td><code>' + W.esc(p.purchase_no) + '</code></td><td>' + W.date(p.purchase_date) + '</td><td>' + W.esc(p.warehouse_name) + '</td><td class="text-end">' + W.num(p.received_qty) + ' / ' + W.num(p.total_qty) + '</td><td class="text-end">' + W.money(p.total_amount) + '</td><td>' + W.statusBadge('purchase', p.status) + '</td></tr>';
                                }), 'No purchases from this vendor yet.')
                        });
                    } catch (e) { W.error(e); }
                },
                fields: function () {
                    return [
                        { name: 'name', label: 'Vendor name', required: true, maxlength: 150 },
                        { name: 'contact_person', label: 'Contact person', maxlength: 120 },
                        { name: 'email', label: 'Email', type: 'email', maxlength: 150 },
                        { name: 'phone', label: 'Phone', maxlength: 30 },
                        { name: 'address', label: 'Address', maxlength: 255, col: 12 },
                        { name: 'is_active', label: 'Active', type: 'checkbox', col: 12 }
                    ];
                }
            });
        }
    };

    W.routes.customers = {
        title: 'Customers',
        render: async function (el) {
            W.crudPage(el, {
                title: 'Customers',
                subtitle: 'Customer information and sales history',
                noun: 'customer',
                nounPlural: 'customers',
                empty: 'No customers added yet. Add customer profiles for sales and invoicing.',
                addLabel: 'Add customer',
                resource: 'customers',
                canDelete: function () { return W.isAdmin(); },
                afterSave: W.loadLookups,
                defaults: { is_active: 1 },
                filters: [
                    { name: 'search', type: 'search', placeholder: 'Search name, email or phone', width: 4 },
                    { name: 'status', type: 'select', options: [{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }], all: 'All statuses', width: 2 }
                ],
                columns: [
                    { label: 'Customer', render: function (r) { return '<span class="fw-semibold">' + W.esc(r.name) + '</span>'; } },
                    { label: 'Contact', render: contactCell },
                    { label: 'Address', render: function (r) { return W.esc(r.address || '—'); } },
                    { label: 'Sales', align: 'end', render: function (r) { return W.num(r.sale_count); } },
                    { label: 'Total sales', align: 'end', render: function (r) { return W.money(r.total_sales); } },
                    { label: 'Status', render: function (r) { return W.activeBadge(r.is_active); } }
                ],
                extraActions: function () { return W.actionBtn('history', 'clock-history', 'Sales history'); },
                onAction: async function (act, row) {
                    if (act !== 'history') { return; }
                    try {
                        var c = (await W.get('customers', 'get', { id: row.id })).data;
                        W.modal({
                            title: c.name + ' · Sales history',
                            size: 'lg',
                            body: '<div class="row g-3 mb-3">' + W.kv('Email', W.esc(c.email)) + W.kv('Phone', W.esc(c.phone)) + W.kv('Address', W.esc(c.address), 4) + W.kv('Status', W.activeBadge(c.is_active)) + '</div>' +
                                W.simpleTable(['Sale', 'Date', 'Warehouse', { label: 'Qty', end: true }, { label: 'Amount', end: true }], c.sales.map(function (s) {
                                    return '<tr><td><code>' + W.esc(s.sale_no) + '</code></td><td>' + W.date(s.sale_date) + '</td><td>' + W.esc(s.warehouse_name) + '</td><td class="text-end">' + W.num(s.total_qty) + '</td><td class="text-end">' + W.money(s.total_amount) + '</td></tr>';
                                }), 'No sales to this customer yet.')
                        });
                    } catch (e) { W.error(e); }
                },
                fields: function () {
                    return [
                        { name: 'name', label: 'Customer name', required: true, maxlength: 150 },
                        { name: 'email', label: 'Email', type: 'email', maxlength: 150 },
                        { name: 'phone', label: 'Phone', maxlength: 30 },
                        { name: 'address', label: 'Address', maxlength: 255 },
                        { name: 'is_active', label: 'Active', type: 'checkbox', col: 12 }
                    ];
                }
            });
        }
    };
}());
