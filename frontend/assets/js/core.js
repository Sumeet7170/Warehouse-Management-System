(function () {
    var cfg = window.WMS_CONFIG;
    var W = window.W = { user: null, csrf: null, lookups: null, appInfo: null, routes: {} };

    W.esc = function (value) {
        return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };

    W.money = function (n) {
        return cfg.currency + ' ' + Number(n || 0).toLocaleString(cfg.locale, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    W.num = function (n) {
        return Number(n || 0).toLocaleString(cfg.locale);
    };

    W.date = function (d) {
        if (!d) { return '—'; }
        var dt = new Date(String(d).substring(0, 10) + 'T00:00:00');
        if (isNaN(dt.getTime())) { return W.esc(d); }
        return dt.toLocaleDateString(cfg.locale, { day: '2-digit', month: 'short', year: 'numeric' });
    };

    W.datetime = function (d) {
        if (!d) { return '—'; }
        var dt = new Date(String(d).replace(' ', 'T'));
        if (isNaN(dt.getTime())) { return W.esc(d); }
        return dt.toLocaleDateString(cfg.locale, { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' +
            dt.toLocaleTimeString(cfg.locale, { hour: '2-digit', minute: '2-digit' });
    };

    W.today = function () {
        var d = new Date();
        var pad = function (n) { return String(n).padStart(2, '0'); };
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    };

    W.uid = function () {
        if (window.crypto && window.crypto.randomUUID) { return window.crypto.randomUUID(); }
        return 'k' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12);
    };

    W.debounce = function (fn, wait) {
        var timer = null;
        return function () {
            var args = arguments;
            var self = this;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(self, args); }, wait);
        };
    };

    W.ApiError = function (message, status, errors) {
        this.name = 'ApiError';
        this.message = message;
        this.status = status || 0;
        this.errors = errors || {};
    };
    W.ApiError.prototype = Object.create(Error.prototype);

    W.api = async function (resource, action, opts) {
        opts = opts || {};
        var url = new URL(cfg.api, window.location.href);
        url.searchParams.set('r', resource);
        url.searchParams.set('a', action);
        var params = opts.params || {};
        Object.keys(params).forEach(function (k) {
            var v = params[k];
            if (v !== undefined && v !== null && v !== '') { url.searchParams.set(k, v); }
        });
        var init = { credentials: 'same-origin', headers: { 'Accept': 'application/json' } };
        if (opts.body !== undefined) {
            init.method = 'POST';
            init.headers['Content-Type'] = 'application/json';
            init.headers['X-CSRF-Token'] = W.csrf || '';
            init.body = JSON.stringify(opts.body);
        }
        var res;
        try {
            res = await fetch(url.toString(), init);
        } catch (e) {
            throw new W.ApiError('Cannot reach the server. Make sure Apache and MySQL are running.', 0);
        }
        var json = null;
        try { json = await res.json(); } catch (e) { json = null; }
        if (!json) { throw new W.ApiError('The server returned an unexpected response.', res.status); }
        if (res.status === 401 && !opts.silent) {
            window.location.href = 'index.html';
            throw new W.ApiError(json.message || 'Session expired.', 401);
        }
        if (!json.success) { throw new W.ApiError(json.message || 'Request failed.', res.status, json.errors || {}); }
        return json;
    };

    W.get = function (resource, action, params) { return W.api(resource, action, { params: params }); };
    W.post = function (resource, action, body) { return W.api(resource, action, { body: body || {} }); };

    W.toast = function (message, type) {
        type = type || 'success';
        var host = document.getElementById('toasts');
        var el = document.createElement('div');
        el.className = 'toast align-items-center border-0 text-bg-' + type;
        el.setAttribute('role', 'alert');
        el.innerHTML = '<div class="d-flex"><div class="toast-body">' + W.esc(message) + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
        host.appendChild(el);
        var toast = new bootstrap.Toast(el, { delay: type === 'danger' ? 7000 : 4000 });
        el.addEventListener('hidden.bs.toast', function () { el.remove(); });
        toast.show();
    };

    W.error = function (e) {
        W.toast(e && e.message ? e.message : String(e), 'danger');
    };

    W.modal = function (o) {
        var el = document.createElement('div');
        el.className = 'modal fade';
        el.tabIndex = -1;
        var size = o.size === 'sm' ? ' modal-sm' : (o.size === 'xl' ? ' modal-xl' : (o.size === 'md' ? '' : ' modal-lg'));
        el.innerHTML = '<div class="modal-dialog' + size + ' modal-dialog-scrollable"><div class="modal-content">' +
            '<div class="modal-header"><h5 class="modal-title">' + W.esc(o.title || '') + '</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>' +
            '<div class="modal-body"></div><div class="modal-footer"></div></div></div>';
        var body = el.querySelector('.modal-body');
        var footer = el.querySelector('.modal-footer');
        if (typeof o.body === 'string') { body.innerHTML = o.body; } else if (o.body) { body.appendChild(o.body); }
        if (o.footer) { footer.innerHTML = o.footer; } else { footer.remove(); footer = null; }
        document.body.appendChild(el);
        var instance = new bootstrap.Modal(el, { backdrop: 'static' });
        el.addEventListener('hidden.bs.modal', function () {
            if (o.onHidden) { o.onHidden(); }
            instance.dispose();
            el.remove();
        });
        instance.show();
        return { el: el, body: body, footer: footer, close: function () { instance.hide(); } };
    };

    W.confirm = function (o) {
        return new Promise(function (resolve) {
            var decided = false;
            var m = W.modal({
                title: o.title || 'Please confirm',
                size: 'sm',
                body: '<p class="mb-0">' + W.esc(o.message) + '</p>',
                footer: '<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>' +
                    '<button type="button" class="btn btn-' + (o.danger ? 'danger' : 'primary') + '" data-ok>' + W.esc(o.confirmText || 'Confirm') + '</button>',
                onHidden: function () { if (!decided) { resolve(false); } }
            });
            m.el.querySelector('[data-ok]').addEventListener('click', function () {
                decided = true;
                resolve(true);
                m.close();
            });
        });
    };

    W.badge = function (text, color) {
        return '<span class="badge text-bg-' + (color || 'secondary') + '">' + W.esc(text) + '</span>';
    };

    W.statusColors = {
        purchase: { ordered: 'warning', partial: 'info', received: 'success', cancelled: 'secondary' },
        transfer: { pending: 'warning', in_transit: 'info', completed: 'success', cancelled: 'secondary' }
    };

    W.statusBadge = function (kind, status) {
        return W.badge(String(status).replace('_', ' ').replace(/^./, function (c) { return c.toUpperCase(); }), W.statusColors[kind][status] || 'secondary');
    };

    W.txnTypes = {
        purchase: ['Purchase', 'success'],
        sale: ['Sale', 'primary'],
        transfer_out: ['Transfer out', 'warning'],
        transfer_in: ['Transfer in', 'info'],
        transfer_return: ['Transfer return', 'info'],
        adjustment_in: ['Adjustment +', 'success'],
        adjustment_out: ['Adjustment -', 'danger'],
        damage: ['Damaged', 'danger']
    };

    W.txnBadge = function (type) {
        var t = W.txnTypes[type] || [type, 'secondary'];
        return W.badge(t[0], t[1]);
    };

    W.signed = function (n) {
        n = Number(n);
        return '<span class="fw-semibold ' + (n < 0 ? 'text-danger' : 'text-success') + '">' + (n > 0 ? '+' : '') + W.num(n) + '</span>';
    };

    W.expiryBadge = function (date, days) {
        if (!date) { return '<span class="text-muted">—</span>'; }
        var d = days === undefined || days === null ? Math.round((new Date(date + 'T00:00:00') - new Date(W.today() + 'T00:00:00')) / 86400000) : Number(days);
        var limit = (W.appInfo && W.appInfo.expiry_alert_days) || 30;
        var cls = d < 0 ? 'danger' : (d <= limit ? 'warning' : 'success');
        var label = d < 0 ? 'Expired ' + Math.abs(d) + 'd ago' : (d === 0 ? 'Expires today' : d + 'd left');
        return W.date(date) + ' ' + W.badge(label, cls);
    };

    W.activeBadge = function (v) {
        return Number(v) === 1 ? W.badge('Active', 'success') : W.badge('Inactive', 'secondary');
    };

    W.loadLookups = async function () {
        W.lookups = (await W.get('lookups', 'all')).data;
        return W.lookups;
    };

    W.options = function (list, o) {
        o = o || {};
        var items = (list || []).filter(function (x) { return !o.activeOnly || Number(x.is_active) === 1; });
        return items.map(function (x) {
            return { value: x.id, label: o.label ? o.label(x) : x.name };
        });
    };

    W.optionsHtml = function (options, selected, placeholder) {
        var html = placeholder !== undefined && placeholder !== null ? '<option value="">' + W.esc(placeholder) + '</option>' : '';
        options.forEach(function (op) {
            var value = typeof op === 'object' ? op.value : op;
            var label = typeof op === 'object' ? op.label : op;
            html += '<option value="' + W.esc(value) + '"' + (String(value) === String(selected === undefined || selected === null ? '' : selected) ? ' selected' : '') + '>' + W.esc(label) + '</option>';
        });
        return html;
    };

    W.fieldHtml = function (f, v) {
        var id = 'f_' + f.name;
        var value = v === undefined || v === null ? (f.value === undefined ? '' : f.value) : v;
        var req = f.required ? ' required' : '';
        var label = W.esc(f.label) + (f.required ? '<span class="text-danger"> *</span>' : '');
        var col = f.col || 6;
        var input;
        if (f.type === 'hidden') {
            return '<input type="hidden" id="' + id + '" name="' + f.name + '" value="' + W.esc(value) + '">';
        }
        if (f.type === 'html') {
            return '<div class="col-md-' + col + '">' + f.html + '</div>';
        }
        if (f.type === 'checkbox') {
            return '<div class="col-md-' + col + '"><div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" id="' + id + '" name="' + f.name + '"' + (Number(value) === 1 || value === true ? ' checked' : '') + '>' +
                '<label class="form-check-label" for="' + id + '">' + label + '</label></div></div>';
        }
        if (f.type === 'select') {
            input = '<select class="form-select" id="' + id + '" name="' + f.name + '"' + req + '>' + W.optionsHtml(f.options || [], value, f.placeholder === undefined ? 'Select...' : f.placeholder) + '</select>';
        } else if (f.type === 'textarea') {
            input = '<textarea class="form-control" id="' + id + '" name="' + f.name + '" rows="' + (f.rows || 3) + '"' + req + (f.maxlength ? ' maxlength="' + f.maxlength + '"' : '') + '>' + W.esc(value) + '</textarea>';
        } else {
            var attrs = '';
            ['min', 'max', 'step', 'maxlength', 'placeholder', 'autocomplete'].forEach(function (a) {
                if (f[a] !== undefined) { attrs += ' ' + a + '="' + W.esc(f[a]) + '"'; }
            });
            input = '<input class="form-control" type="' + (f.type || 'text') + '" id="' + id + '" name="' + f.name + '" value="' + W.esc(value) + '"' + attrs + req + '>';
            if (f.append) { input = '<div class="input-group">' + input + f.append + '</div>'; }
        }
        return '<div class="col-md-' + col + '"><label class="form-label" for="' + id + '">' + label + '</label>' + input +
            '<div class="invalid-feedback" data-err="' + f.name + '"></div>' + (f.hint ? '<div class="form-text">' + W.esc(f.hint) + '</div>' : '') + '</div>';
    };

    W.collectForm = function (form, fields) {
        var data = {};
        fields.forEach(function (f) {
            var el = form.elements[f.name];
            if (!el) { return; }
            data[f.name] = f.type === 'checkbox' ? el.checked : (typeof el.value === 'string' ? el.value.trim() : el.value);
        });
        return data;
    };

    W.showFormErrors = function (root, errors, message) {
        root.querySelectorAll('.is-invalid').forEach(function (e) { e.classList.remove('is-invalid'); });
        root.querySelectorAll('[data-err]').forEach(function (e) { e.textContent = ''; e.style.display = ''; });
        var alertBox = root.querySelector('[data-form-alert]');
        var unmatched = false;
        Object.keys(errors || {}).forEach(function (name) {
            var input = root.querySelector('[name="' + name + '"]');
            var fb = root.querySelector('[data-err="' + name + '"]');
            if (input && fb) {
                input.classList.add('is-invalid');
                fb.textContent = errors[name];
                fb.style.display = 'block';
            } else {
                unmatched = true;
            }
        });
        if (alertBox) {
            if (message && (unmatched || !errors || !Object.keys(errors).length)) {
                alertBox.textContent = message;
                alertBox.classList.remove('d-none');
            } else {
                alertBox.classList.add('d-none');
            }
        }
    };

    W.formModal = function (o) {
        var formId = 'form_' + Math.random().toString(36).slice(2);
        var fields = o.fields || [];
        var values = o.values || {};
        var m = W.modal({
            title: o.title,
            size: o.size || 'lg',
            body: '<div class="alert alert-danger d-none" data-form-alert></div><form id="' + formId + '" class="row g-3" novalidate>' +
                fields.map(function (f) { return W.fieldHtml(f, values[f.name]); }).join('') + (o.extraHtml || '') + '</form>',
            footer: '<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>' +
                '<button type="submit" class="btn btn-primary" form="' + formId + '">' + W.esc(o.submitText || 'Save') + '</button>'
        });
        var form = m.el.querySelector('form');
        var submit = m.el.querySelector('button[type="submit"]');
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            W.showFormErrors(m.el, {}, '');
            if (!form.reportValidity()) { return; }
            submit.disabled = true;
            var original = submit.innerHTML;
            submit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving';
            try {
                await o.onSubmit(W.collectForm(form, fields), m);
                m.close();
            } catch (e) {
                W.showFormErrors(m.el, e.errors || {}, e.message);
            } finally {
                submit.disabled = false;
                submit.innerHTML = original;
            }
        });
        if (o.onReady) { o.onReady(m, form); }
        return m;
    };

    W.pageHeader = function (title, subtitle, actions) {
        return '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3"><div><h4 class="page-title">' + W.esc(title) + '</h4>' +
            (subtitle ? '<div class="text-muted">' + W.esc(subtitle) + '</div>' : '') + '</div><div class="d-flex gap-2 flex-wrap no-print">' + (actions || '') + '</div></div>';
    };

    W.isAdmin = function () { return W.user && W.user.role === 'admin'; };

    W.barcodeSvg = function (value, height) {
        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        var opts = { width: 2, height: height || 55, displayValue: true, fontSize: 14, margin: 4 };
        try {
            JsBarcode(svg, value, Object.assign({ format: /^\d{13}$/.test(value) ? 'EAN13' : 'CODE128' }, opts));
        } catch (e) {
            try {
                svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                JsBarcode(svg, value, Object.assign({ format: 'CODE128' }, opts));
            } catch (e2) {
                return '<div class="text-danger small">Barcode cannot be rendered</div>';
            }
        }
        return svg.outerHTML;
    };

    W.print = function (title, bodyHtml, css) {
        var w = window.open('', '_blank', 'width=980,height=720');
        if (!w) { W.toast('Allow pop-ups for this site to print.', 'warning'); return; }
        var base = new URL('assets/vendor/bootstrap/bootstrap.min.css', window.location.href).href;
        w.document.write('<!doctype html><html><head><meta charset="utf-8"><title>' + W.esc(title) + '</title><link rel="stylesheet" href="' + base + '">' +
            '<style>body{padding:20px;background:#fff}.label-sheet{display:flex;flex-wrap:wrap;gap:8px}.label-box{border:1px dashed #999;padding:6px 8px;width:220px;text-align:center;page-break-inside:avoid}' +
            '.lb-name{font-size:12px;font-weight:600;line-height:1.2}.lb-sku{font-size:10px;color:#555}.label-box svg{max-width:100%;height:auto}' + (css || '') + '</style></head><body>' + bodyHtml + '</body></html>');
        w.document.close();
        setTimeout(function () { w.focus(); w.print(); }, 450);
    };
}());
