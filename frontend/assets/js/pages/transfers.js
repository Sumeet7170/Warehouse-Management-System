(function () {
    var W = window.W;

    async function showTransfer(id) {
        var t = (await W.get('transfers', 'get', { id: id })).data;
        var rows = t.items.map(function (i) {
            return '<tr><td>' + W.esc(i.product_name) + '<div class="small text-muted">' + W.esc(i.sku) + '</div></td><td class="text-end">' + W.num(i.quantity) + '</td><td>' +
                (i.batches.length ? i.batches.map(function (b) { return W.esc(b.batch_number) + ' × ' + W.num(b.quantity) + (b.expiry_date ? ' <span class="text-muted small">(exp ' + W.date(b.expiry_date) + ')</span>' : ''); }).join('<br>') : '<span class="text-muted">Allocated on dispatch</span>') + '</td></tr>';
        });
        W.modal({
            title: 'Transfer ' + t.transfer_no,
            size: 'lg',
            body: '<div class="row g-3 mb-3">' + W.kv('From', W.esc(t.from_warehouse_name)) + W.kv('To', W.esc(t.to_warehouse_name)) + W.kv('Status', W.statusBadge('transfer', t.status)) + W.kv('Transfer date', W.date(t.transfer_date)) +
                W.kv('Created by', W.esc(t.created_by_name)) + W.kv('Dispatched', t.dispatched_at ? W.datetime(t.dispatched_at) : '—') + W.kv('Received', t.received_at ? W.datetime(t.received_at) : '—') + W.kv('Cancelled', t.cancelled_at ? W.datetime(t.cancelled_at) : '—') +
                W.kv('Notes', W.esc(t.notes), 12) + '</div>' + W.simpleTable(['Product', { label: 'Quantity', end: true }, 'Batches moved'], rows)
        });
    }

    function newTransfer(lv) {
        if (!W.lookups.warehouses || W.lookups.warehouses.length < 2) {
            W.confirm({
                title: 'At least two warehouses required',
                message: 'Stock transfers require at least two warehouses (a source and a destination). Please create warehouses first.',
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
        var fromSelect = null;

        async function loadAvailability() {
            var wh = fromSelect.value;
            var ids = editor.productIds();
            if (!wh || !ids.length) { avail = {}; editor.render(); return; }
            try {
                avail = (await W.get('inventory', 'availability', { warehouse_id: wh, product_ids: ids.join(',') })).data;
                editor.render();
            } catch (e) { W.error(e); }
        }

        W.formModal({
            title: 'New stock transfer',
            size: 'xl',
            submitText: 'Create transfer',
            values: { transfer_date: W.today() },
            fields: [
                { name: 'from_warehouse_id', label: 'From warehouse', type: 'select', options: W.whOptions(), required: true, col: 4 },
                { name: 'to_warehouse_id', label: 'To warehouse', type: 'select', options: W.whOptions(), required: true, col: 4 },
                { name: 'transfer_date', label: 'Transfer date', type: 'date', required: true, col: 4 },
                { name: 'notes', label: 'Notes', maxlength: 500, col: 12 }
            ],
            extraHtml: '<div class="col-12"><div data-items></div></div><div class="col-12 text-muted small"><i class="bi bi-info-circle me-1"></i>Creating a transfer does not move stock. Dispatch it to deduct stock from the source (FIFO, non-expired batches), then receive it at the destination.</div>',
            onReady: function (m, form) {
                fromSelect = form.elements.from_warehouse_id;
                editor = new W.ItemsEditor(m.el.querySelector('[data-items]'), {
                    columns: [
                        {
                            label: 'Available at source', align: 'end', display: function (l) {
                                var a = avail[l.product.id];
                                if (a === undefined) { return '<span class="text-muted">…</span>'; }
                                return '<span class="badge text-bg-' + (a === 0 ? 'danger' : ((parseInt(l.quantity, 10) || 0) > a ? 'warning' : 'success')) + '">' + W.num(a) + '</span>';
                            }
                        },
                        { key: 'quantity', label: 'Quantity', type: 'number', min: 1, step: 1, default: function () { return '1'; } }
                    ],
                    onChange: function (ed, structural) { if (structural) { loadAvailability(); } }
                });
                fromSelect.addEventListener('change', loadAvailability);
            },
            onSubmit: async function (data) {
                var res = await W.post('transfers', 'save', {
                    from_warehouse_id: data.from_warehouse_id, to_warehouse_id: data.to_warehouse_id, transfer_date: data.transfer_date, notes: data.notes,
                    idempotency_key: key, items: editor.items()
                });
                W.toast(res.message);
                lv.refresh();
            }
        });
    }

    W.routes.transfers = {
        title: 'Stock Transfers',
        render: async function (el) {
            el.innerHTML = W.pageHeader('Stock Transfers', 'Move stock between warehouses and track transfer status', '<button class="btn btn-primary" id="newTransfer"><i class="bi bi-plus-lg me-1"></i>New transfer</button>') + '<div id="lv"></div>';
            var lv = new W.ListView(el.querySelector('#lv'), {
                resource: 'transfers',
                empty: 'No stock transfers recorded yet.',
                emptyAction: {
                    label: 'New transfer',
                    icon: 'arrow-left-right',
                    onClick: function () { newTransfer(lv); }
                },
                filters: [
                    { name: 'search', type: 'search', placeholder: 'Search transfer no.', width: 2 },
                    { name: 'status', type: 'select', options: [{ value: 'pending', label: 'Pending' }, { value: 'in_transit', label: 'In transit' }, { value: 'completed', label: 'Completed' }, { value: 'cancelled', label: 'Cancelled' }], all: 'All statuses', width: 2 },
                    { name: 'from_warehouse_id', type: 'select', options: function () { return W.whOptions(false); }, all: 'Any source', width: 2 },
                    { name: 'to_warehouse_id', type: 'select', options: function () { return W.whOptions(false); }, all: 'Any destination', width: 2 },
                    { name: 'from', type: 'date', label: 'From', width: 2 },
                    { name: 'to', type: 'date', label: 'To', width: 2 }
                ],
                columns: [
                    { label: 'Transfer', render: function (r) { return '<code>' + W.esc(r.transfer_no) + '</code><div class="small text-muted">' + W.date(r.transfer_date) + '</div>'; } },
                    { label: 'Route', render: function (r) { return W.esc(r.from_warehouse_name) + ' <i class="bi bi-arrow-right mx-1"></i> ' + W.esc(r.to_warehouse_name); } },
                    { label: 'Products', align: 'end', render: function (r) { return W.num(r.item_count); } },
                    { label: 'Units', align: 'end', render: function (r) { return W.num(r.total_qty); } },
                    { label: 'Status', render: function (r) { return W.statusBadge('transfer', r.status); } },
                    { label: 'Created by', render: function (r) { return W.esc(r.created_by_name); } },
                    {
                        label: 'Actions', align: 'end', class: 'text-nowrap', actions: function (r) {
                            var html = W.actionBtn('view', 'eye', 'View');
                            if (r.status === 'pending') { html += W.actionBtn('dispatch', 'truck', 'Dispatch', 'primary'); }
                            if (r.status === 'in_transit') { html += W.actionBtn('receive', 'box-arrow-in-down', 'Receive at destination', 'success'); }
                            if ((r.status === 'pending' || r.status === 'in_transit') && W.isAdmin()) { html += W.actionBtn('cancel', 'x-circle', 'Cancel transfer', 'danger'); }
                            return html;
                        }
                    }
                ],
                onAction: async function (act, row) {
                    try {
                        if (act === 'view') { await showTransfer(row.id); return; }
                        var cfg = {
                            dispatch: ['Dispatch transfer', 'Dispatch ' + row.transfer_no + '? Stock will be deducted from ' + row.from_warehouse_name + ' using FIFO.', 'Dispatch', false],
                            receive: ['Receive transfer', 'Confirm receipt of ' + row.transfer_no + ' at ' + row.to_warehouse_name + '?', 'Receive', false],
                            cancel: ['Cancel transfer', 'Cancel ' + row.transfer_no + '?' + (row.status === 'in_transit' ? ' Stock will be returned to ' + row.from_warehouse_name + '.' : ''), 'Cancel transfer', true]
                        }[act];
                        var ok = await W.confirm({ title: cfg[0], message: cfg[1], confirmText: cfg[2], danger: cfg[3] });
                        if (!ok) { return; }
                        var res = await W.post('transfers', act, { id: row.id });
                        W.toast(res.message);
                        lv.refresh();
                        W.refreshAlerts();
                    } catch (e) { W.error(e); }
                }
            });
            el.querySelector('#newTransfer').addEventListener('click', function () { newTransfer(lv); });
        }
    };
}());
