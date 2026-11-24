(function () {
    var W = window.W;

    W.ListView = function (el, o) {
        this.el = el;
        this.o = o;
        this.page = 1;
        this.rows = [];
        this.meta = null;
        this.seq = 0;
        this.build();
        this.load(1);
    };

    W.ListView.prototype.build = function () {
        var self = this;
        var o = this.o;
        var filters = (o.filters || []).map(function (f) {
            var width = f.width || 3;
            var label = f.label ? '<label class="form-label small text-muted mb-0">' + W.esc(f.label) + '</label>' : '';
            var control;
            if (f.type === 'select') {
                var opts = typeof f.options === 'function' ? f.options() : (f.options || []);
                control = '<select class="form-select form-select-sm" name="' + f.name + '">' + W.optionsHtml(opts, f.value || '', f.all || 'All') + '</select>';
            } else if (f.type === 'date') {
                control = '<input type="date" class="form-control form-control-sm" name="' + f.name + '" value="' + W.esc(f.value || '') + '">';
            } else {
                control = '<input type="search" class="form-control form-control-sm" name="' + f.name + '" placeholder="' + W.esc(f.placeholder || 'Search...') + '" autocomplete="off">';
            }
            return '<div class="col-6 col-md-' + width + '">' + label + control + '</div>';
        }).join('');
        var head = o.columns.map(function (c) {
            return '<th class="' + (c.align === 'end' ? 'text-end ' : '') + (c.headClass || '') + '">' + W.esc(c.label) + '</th>';
        }).join('');
        this.el.innerHTML = '<div class="card"><div class="card-body pb-2"><form class="row g-2 align-items-end lv-filters" novalidate>' + filters +
            '<div class="col-auto"><button type="button" class="btn btn-sm btn-outline-secondary lv-reset"><i class="bi bi-x-circle me-1"></i>Reset</button></div></form></div>' +
            '<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr>' + head + '</tr></thead><tbody></tbody></table></div>' +
            '<div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 lv-foot"></div></div>';
        this.form = this.el.querySelector('.lv-filters');
        this.tbody = this.el.querySelector('tbody');
        this.foot = this.el.querySelector('.lv-foot');
        this.form.addEventListener('submit', function (e) { e.preventDefault(); self.load(1); });
        this.form.addEventListener('input', W.debounce(function (e) {
            if (e.target.type === 'search') { self.load(1); }
        }, 350));
        this.form.addEventListener('change', function (e) {
            if (e.target.type !== 'search') { self.load(1); }
        });
        this.el.querySelector('.lv-reset').addEventListener('click', function () {
            (o.filters || []).forEach(function (f) {
                var input = self.form.elements[f.name];
                if (input) { input.value = f.value || ''; }
            });
            self.load(1);
        });
        this.tbody.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-act]');
            if (!btn || !o.onAction) { return; }
            var row = self.rows[Number(btn.dataset.idx)];
            if (row) { o.onAction(btn.dataset.act, row, self); }
        });
        this.foot.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-page]');
            if (btn && !btn.parentElement.classList.contains('disabled')) { self.load(Number(btn.dataset.page)); }
        });
    };

    W.ListView.prototype.collect = function () {
        var params = {};
        (this.o.filters || []).forEach(function (f) {
            var input = this.form.elements[f.name];
            if (input && input.value !== '') { params[f.name] = input.value; }
        }, this);
        return params;
    };

    W.ListView.prototype.refresh = function () { return this.load(this.page); };

    W.ListView.prototype.load = async function (page) {
        var o = this.o;
        var seq = ++this.seq;
        this.page = page || 1;
        var span = o.columns.length;
        this.tbody.innerHTML = '<tr><td colspan="' + span + '" class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading...</td></tr>';
        try {
            var extra = o.params ? o.params() : {};
            var params = Object.assign({}, extra, this.collect(), { page: this.page, per_page: o.perPage || 15 });
            var res = await W.api(o.resource, o.action || 'list', { params: params });
            if (seq !== this.seq) { return; }
            this.rows = o.rowsOf ? o.rowsOf(res) : res.data;
            this.meta = res.meta;
            this.page = res.meta.page;
            this.renderRows();
            this.renderFoot();
            if (o.onLoad) { o.onLoad(res, this); }
        } catch (e) {
            if (seq !== this.seq) { return; }
            this.tbody.innerHTML = '<tr><td colspan="' + span + '" class="text-center py-4 text-danger">' + W.esc(e.message) + '</td></tr>';
            this.foot.innerHTML = '';
        }
    };

    W.ListView.prototype.renderRows = function () {
        var self = this;
        var o = this.o;
        if (!this.rows.length) {
            var params = this.collect();
            var hasFilters = Object.keys(params).some(function (k) {
                var v = params[k];
                return v !== undefined && v !== null && String(v).trim() !== '';
            });
            var emptyText;
            var btnHtml = '';
            var onBtnClick = null;
            if (hasFilters) {
                emptyText = 'No records match your search or filter criteria.';
                btnHtml = '<div class="mt-3"><button type="button" class="btn btn-sm btn-outline-secondary lv-empty-action"><i class="bi bi-x-circle me-1"></i>Reset filters</button></div>';
                onBtnClick = function () {
                    (o.filters || []).forEach(function (f) {
                        var input = self.form.elements[f.name];
                        if (input) { input.value = f.value || ''; }
                    });
                    self.load(1);
                };
            } else {
                emptyText = o.empty || 'No records found.';
                var act = typeof o.emptyAction === 'function' ? o.emptyAction(self) : o.emptyAction;
                if (act && act.label) {
                    var variant = act.variant || 'primary';
                    var icon = act.icon || 'plus-lg';
                    btnHtml = '<div class="mt-3"><button type="button" class="btn btn-sm btn-' + variant + ' lv-empty-action"><i class="bi bi-' + icon + ' me-1"></i>' + W.esc(act.label) + '</button></div>';
                    onBtnClick = function () {
                        if (act.onClick) { act.onClick(self); }
                    };
                }
            }
            this.tbody.innerHTML = '<tr><td colspan="' + o.columns.length + '"><div class="empty-state py-5 text-center"><i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i><div class="fs-6 text-muted">' + W.esc(emptyText) + '</div>' + btnHtml + '</div></td></tr>';
            if (onBtnClick) {
                var actionBtn = this.tbody.querySelector('.lv-empty-action');
                if (actionBtn) {
                    actionBtn.addEventListener('click', onBtnClick);
                }
            }
            return;
        }
        var html = '';
        this.rows.forEach(function (row, idx) {
            html += '<tr>';
            o.columns.forEach(function (c) {
                var content;
                if (c.actions) {
                    content = c.actions(row).replace(/data-act=/g, 'data-idx="' + idx + '" data-act=');
                } else if (c.render) {
                    content = c.render(row);
                } else {
                    content = W.esc(row[c.key]);
                }
                html += '<td class="' + (c.align === 'end' ? 'text-end ' : '') + (c.class || '') + '">' + content + '</td>';
            });
            html += '</tr>';
        });
        this.tbody.innerHTML = html;
    };

    W.ListView.prototype.renderFoot = function () {
        var m = this.meta;
        var from = m.total === 0 ? 0 : (m.page - 1) * m.per_page + 1;
        var to = Math.min(m.total, m.page * m.per_page);
        var pages = [];
        var start = Math.max(1, m.page - 2);
        var end = Math.min(m.pages, m.page + 2);
        for (var i = start; i <= end; i++) { pages.push(i); }
        var html = '<div class="text-muted small">Showing ' + W.num(from) + '–' + W.num(to) + ' of ' + W.num(m.total) + '</div>';
        if (m.pages > 1) {
            html += '<ul class="pagination pagination-sm mb-0">' +
                '<li class="page-item' + (m.page <= 1 ? ' disabled' : '') + '"><a class="page-link" href="javascript:void(0)" data-page="' + (m.page - 1) + '">Prev</a></li>' +
                (start > 1 ? '<li class="page-item"><a class="page-link" href="javascript:void(0)" data-page="1">1</a></li>' + (start > 2 ? '<li class="page-item disabled"><span class="page-link">…</span></li>' : '') : '') +
                pages.map(function (p) { return '<li class="page-item' + (p === m.page ? ' active' : '') + '"><a class="page-link" href="javascript:void(0)" data-page="' + p + '">' + p + '</a></li>'; }).join('') +
                (end < m.pages ? (end < m.pages - 1 ? '<li class="page-item disabled"><span class="page-link">…</span></li>' : '') + '<li class="page-item"><a class="page-link" href="javascript:void(0)" data-page="' + m.pages + '">' + m.pages + '</a></li>' : '') +
                '<li class="page-item' + (m.page >= m.pages ? ' disabled' : '') + '"><a class="page-link" href="javascript:void(0)" data-page="' + (m.page + 1) + '">Next</a></li></ul>';
        }
        this.foot.innerHTML = html;
    };

    W.scanner = function (host, onCode, o) {
        o = o || {};
        var id = 'scn_' + Math.random().toString(36).slice(2);
        host.innerHTML = '<div class="scan-panel border rounded p-2 bg-light"><div class="d-flex justify-content-between align-items-center mb-2">' +
            '<small class="text-muted"><i class="bi bi-camera-video me-1"></i>Point the camera at a barcode</small><button type="button" class="btn-close scan-close" aria-label="Close scanner"></button></div>' +
            '<div id="' + id + '" style="width:100%;max-width:420px"></div><div class="small text-danger mt-1 scan-msg"></div></div>';
        var msg = host.querySelector('.scan-msg');
        var qr = null;
        var running = false;
        var closed = false;
        var last = '';
        var lastAt = 0;
        var stop = async function () {
            closed = true;
            if (qr && running) {
                running = false;
                try { await qr.stop(); } catch (e) { }
                try { qr.clear(); } catch (e) { }
            }
            host.innerHTML = '';
        };
        host.querySelector('.scan-close').addEventListener('click', stop);
        (async function () {
            if (typeof Html5Qrcode === 'undefined' || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                msg.textContent = 'Camera scanning needs a supported browser on localhost or HTTPS. You can still use a USB barcode scanner or type the code.';
                return;
            }
            try {
                var formats = typeof Html5QrcodeSupportedFormats !== 'undefined' ? [
                    Html5QrcodeSupportedFormats.EAN_13, Html5QrcodeSupportedFormats.EAN_8, Html5QrcodeSupportedFormats.CODE_128,
                    Html5QrcodeSupportedFormats.CODE_39, Html5QrcodeSupportedFormats.UPC_A, Html5QrcodeSupportedFormats.UPC_E, Html5QrcodeSupportedFormats.QR_CODE
                ] : undefined;
                qr = new Html5Qrcode(id, { formatsToSupport: formats, verbose: false });
                await qr.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 260, height: 140 } }, function (text) {
                    var now = Date.now();
                    if (text === last && now - lastAt < 2500) { return; }
                    last = text;
                    lastAt = now;
                    onCode(text);
                    if (o.continuous === false) { stop(); }
                }, function () { });
                running = true;
                if (closed) { running = false; try { await qr.stop(); } catch (e) { } }
            } catch (err) {
                msg.textContent = 'Could not start the camera: ' + ((err && err.message) || err);
            }
        }());
        return { stop: stop };
    };

    W.productSearch = function (host, o) {
        o = o || {};
        host.innerHTML = '<div class="position-relative"><div class="input-group"><span class="input-group-text"><i class="bi bi-upc-scan"></i></span>' +
            '<input type="text" class="form-control ps-input" placeholder="' + W.esc(o.placeholder || 'Scan barcode or search product by name / SKU') + '" autocomplete="off">' +
            '<button type="button" class="btn btn-outline-secondary ps-scan" title="Scan with camera"><i class="bi bi-camera"></i></button></div>' +
            '<div class="list-group ps-list shadow d-none"></div></div><div class="ps-scanner mt-2"></div>';
        var input = host.querySelector('.ps-input');
        var list = host.querySelector('.ps-list');
        var scanHost = host.querySelector('.ps-scanner');
        var scanner = null;
        var results = [];
        var seq = 0;

        var hide = function () { list.classList.add('d-none'); };
        var pick = function (product) {
            hide();
            if (o.clearOnPick !== false) { input.value = ''; }
            o.onPick(product);
            input.focus();
        };
        var lookup = async function (code) {
            try {
                var res = await W.get('products', 'lookup', { code: code });
                if (Number(res.data.is_active) !== 1) {
                    W.toast('Product "' + res.data.name + '" is inactive.', 'warning');
                    return;
                }
                pick(res.data);
            } catch (e) {
                if (e.status === 404 && results.length) { pick(results[0]); } else { W.error(e); }
            }
        };
        var search = W.debounce(async function () {
            var q = input.value.trim();
            var mySeq = ++seq;
            if (q.length < 1) { hide(); return; }
            try {
                var res = await W.get('products', 'search', { q: q });
                if (mySeq !== seq) { return; }
                results = res.data;
                list.innerHTML = results.length ? results.map(function (p, i) {
                    return '<button type="button" class="list-group-item list-group-item-action" data-i="' + i + '"><div class="fw-semibold">' + W.esc(p.name) + '</div><small class="text-muted">' +
                        W.esc(p.sku) + (p.barcode ? ' · ' + W.esc(p.barcode) : '') + '</small></button>';
                }).join('') : '<div class="list-group-item text-muted">No matching products</div>';
                list.classList.remove('d-none');
            } catch (e) { hide(); }
        }, 250);

        input.addEventListener('input', search);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var code = input.value.trim();
                if (code) { lookup(code); }
            } else if (e.key === 'Escape') { hide(); }
        });
        list.addEventListener('mousedown', function (e) {
            var btn = e.target.closest('[data-i]');
            if (btn) { e.preventDefault(); pick(results[Number(btn.dataset.i)]); }
        });
        input.addEventListener('blur', function () { setTimeout(hide, 150); });
        host.querySelector('.ps-scan').addEventListener('click', function () {
            if (scanHost.innerHTML) {
                if (scanner) { scanner.stop(); }
                scanHost.innerHTML = '';
                return;
            }
            scanner = W.scanner(scanHost, lookup, { continuous: true });
        });
        return {
            focus: function () { input.focus(); },
            clear: function () { input.value = ''; hide(); }
        };
    };

    W.ItemsEditor = function (el, o) {
        var self = this;
        this.el = el;
        this.o = o;
        this.lines = [];
        el.innerHTML = '<div class="ie-search mb-3"></div><div class="table-responsive"><table class="table table-sm table-bordered items-table align-middle mb-0"><thead class="table-light"><tr></tr></thead><tbody></tbody></table></div>';
        this.tbody = el.querySelector('tbody');
        var head = '<th>Product</th>';
        o.columns.forEach(function (c) { head += '<th class="' + (c.align === 'end' ? 'text-end' : '') + '">' + W.esc(c.label) + '</th>'; });
        head += '<th style="width:44px"></th>';
        el.querySelector('thead tr').innerHTML = head;
        W.productSearch(el.querySelector('.ie-search'), { onPick: function (p) { self.add(p); } });
        this.tbody.addEventListener('input', function (e) {
            var inp = e.target.closest('[data-key]');
            if (!inp) { return; }
            var line = self.lines[Number(inp.dataset.idx)];
            line[inp.dataset.key] = inp.value;
            self.updateComputed();
            if (o.onChange) { o.onChange(self); }
        });
        this.tbody.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-remove]');
            if (!btn) { return; }
            self.lines.splice(Number(btn.dataset.remove), 1);
            self.render();
            if (o.onChange) { o.onChange(self, true); }
        });
        this.render();
    };

    W.ItemsEditor.prototype.add = function (product) {
        var existing = this.lines.filter(function (l) { return Number(l.product.id) === Number(product.id); })[0];
        var qtyCol = this.o.columns.filter(function (c) { return c.key === 'quantity'; })[0];
        if (existing && this.o.mergeSame !== false && qtyCol) {
            existing.quantity = String((parseInt(existing.quantity, 10) || 0) + 1);
        } else {
            var line = { product: product };
            this.o.columns.forEach(function (c) {
                if (c.key && c.default) { line[c.key] = c.default(product); }
            });
            this.lines.push(line);
        }
        this.render();
        if (this.o.onChange) { this.o.onChange(this, true); }
    };

    W.ItemsEditor.prototype.productIds = function () {
        return this.lines.map(function (l) { return l.product.id; });
    };

    W.ItemsEditor.prototype.render = function () {
        var self = this;
        if (!this.lines.length) {
            this.tbody.innerHTML = '<tr><td colspan="' + (this.o.columns.length + 2) + '" class="text-center text-muted py-4">Scan a barcode or search to add products</td></tr>';
            return;
        }
        var html = '';
        this.lines.forEach(function (line, idx) {
            html += '<tr><td><div class="fw-semibold">' + W.esc(line.product.name) + '</div><small class="text-muted">' + W.esc(line.product.sku) + '</small></td>';
            self.o.columns.forEach(function (c) {
                var cell;
                if (c.display) {
                    cell = c.display(line, self);
                } else if (c.computed) {
                    cell = '<span data-comp="' + idx + '" data-ckey="' + c.label + '">' + c.computed(line) + '</span>';
                } else if (c.enabled && !c.enabled(line.product)) {
                    cell = '<span class="text-muted">—</span>';
                } else {
                    var attrs = '';
                    ['min', 'step', 'placeholder', 'maxlength'].forEach(function (a) { if (c[a] !== undefined) { attrs += ' ' + a + '="' + W.esc(c[a]) + '"'; } });
                    if (c.requiredFor && c.requiredFor(line.product)) { attrs += ' required'; }
                    cell = '<input class="form-control form-control-sm" type="' + (c.type || 'text') + '" data-idx="' + idx + '" data-key="' + c.key + '" value="' + W.esc(line[c.key] === undefined ? '' : line[c.key]) + '"' + attrs + '>';
                }
                html += '<td class="' + (c.align === 'end' ? 'text-end' : '') + '">' + cell + '</td>';
            });
            html += '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-remove="' + idx + '" title="Remove"><i class="bi bi-trash"></i></button></td></tr>';
        });
        this.tbody.innerHTML = html;
    };

    W.ItemsEditor.prototype.updateComputed = function () {
        var self = this;
        this.tbody.querySelectorAll('[data-comp]').forEach(function (span) {
            var col = self.o.columns.filter(function (c) { return c.label === span.dataset.ckey; })[0];
            span.innerHTML = col.computed(self.lines[Number(span.dataset.comp)]);
        });
    };

    W.ItemsEditor.prototype.items = function () {
        return this.lines.map(function (line) {
            var out = { product_id: line.product.id };
            Object.keys(line).forEach(function (k) { if (k !== 'product') { out[k] = line[k]; } });
            return out;
        });
    };
}());
