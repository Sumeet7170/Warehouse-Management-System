(function () {
    var W = window.W;

    W.whOptions = function (activeOnly) {
        return W.options(W.lookups.warehouses, { activeOnly: activeOnly !== false });
    };
    W.vendorOptions = function (activeOnly) {
        return W.options(W.lookups.vendors, { activeOnly: activeOnly !== false });
    };
    W.customerOptions = function (activeOnly) {
        return W.options(W.lookups.customers, { activeOnly: activeOnly !== false });
    };
    W.categoryOptions = function () {
        return W.options(W.lookups.categories);
    };

    W.kv = function (label, value, col) {
        return '<div class="col-6 col-md-' + (col || 3) + '"><div class="text-muted small">' + W.esc(label) + '</div><div class="fw-semibold">' + (value === undefined || value === null || value === '' ? '—' : value) + '</div></div>';
    };

    W.simpleTable = function (headers, rows, emptyText) {
        if (!rows.length) { return '<div class="text-muted py-3">' + W.esc(emptyText || 'No records.') + '</div>'; }
        return '<div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr>' +
            headers.map(function (h) { return '<th class="' + (h.end ? 'text-end' : '') + '">' + W.esc(h.label || h) + '</th>'; }).join('') +
            '</tr></thead><tbody>' + rows.join('') + '</tbody></table></div>';
    };

    W.actionBtn = function (act, icon, title, variant) {
        return '<button type="button" class="btn btn-sm btn-outline-' + (variant || 'secondary') + ' me-1" data-act="' + act + '" title="' + W.esc(title) + '"><i class="bi bi-' + icon + '"></i></button>';
    };

    W.crudPage = function (el, c) {
        var canAdd = c.canAdd === undefined ? true : c.canAdd;
        el.innerHTML = W.pageHeader(c.title, c.subtitle, canAdd ? '<button class="btn btn-primary" id="crudAdd"><i class="bi bi-plus-lg me-1"></i>' + W.esc(c.addLabel || 'Add') + '</button>' : '') + '<div id="crudList"></div>';
        var lv;

        async function openForm(row) {
            var values = row;
            if (row && c.loadRecord) {
                try { values = await c.loadRecord(row); } catch (e) { W.error(e); return; }
            }
            W.formModal({
                title: (row ? 'Edit ' : 'Add ') + c.noun,
                fields: c.fields(row),
                values: values || (c.defaults || {}),
                size: c.formSize || 'lg',
                onReady: c.onFormReady,
                onSubmit: async function (data) {
                    var body = Object.assign({}, data);
                    if (row) { body.id = row.id; }
                    var res = await W.post(c.resource, 'save', body);
                    W.toast(res.message);
                    if (c.afterSave) { await c.afterSave(res); }
                    lv.refresh();
                }
            });
        }

        var columns = c.columns.slice();
        columns.push({
            label: 'Actions',
            align: 'end',
            class: 'text-nowrap',
            actions: function (row) {
                var html = c.extraActions ? c.extraActions(row) : '';
                if (!c.canEdit || c.canEdit(row)) { html += W.actionBtn('edit', 'pencil', 'Edit', 'primary'); }
                if (c.canDelete && c.canDelete(row)) { html += W.actionBtn('delete', 'trash', 'Delete', 'danger'); }
                return html;
            }
        });
        lv = new W.ListView(el.querySelector('#crudList'), {
            resource: c.resource,
            columns: columns,
            filters: c.filters,
            empty: c.empty || ('No ' + (c.nounPlural || (c.noun + 's')) + ' found.'),
            emptyAction: canAdd ? {
                label: c.addLabel || ('Add ' + c.noun),
                icon: 'plus-lg',
                onClick: function () { openForm(null); }
            } : null,
            onAction: async function (act, row) {
                if (act === 'edit') { openForm(row); return; }
                if (act === 'delete') {
                    var ok = await W.confirm({ title: 'Delete ' + c.noun, message: 'Delete "' + (row.name || row.sku || row.email) + '"? This cannot be undone.', confirmText: 'Delete', danger: true });
                    if (!ok) { return; }
                    try {
                        var res = await W.post(c.resource, 'delete', { id: row.id });
                        W.toast(res.message);
                        if (c.afterSave) { await c.afterSave(res); }
                        lv.refresh();
                    } catch (e) { W.error(e); }
                    return;
                }
                if (c.onAction) { c.onAction(act, row, lv); }
            }
        });
        if (canAdd) { el.querySelector('#crudAdd').addEventListener('click', function () { openForm(null); }); }
        return lv;
    };
}());
