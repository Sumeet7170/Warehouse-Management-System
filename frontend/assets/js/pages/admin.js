(function () {
    var W = window.W;

    W.routes.users = {
        title: 'Users',
        admin: true,
        render: async function (el) {
            W.crudPage(el, {
                title: 'Users',
                subtitle: 'Administrators have full access; staff can run daily operations',
                noun: 'user',
                addLabel: 'Add user',
                resource: 'users',
                canDelete: function (row) { return Number(row.id) !== Number(W.user.id); },
                defaults: { role: 'staff', is_active: 1 },
                filters: [
                    { name: 'search', type: 'search', placeholder: 'Search name or email', width: 4 },
                    { name: 'role', type: 'select', options: [{ value: 'admin', label: 'Administrator' }, { value: 'staff', label: 'Staff' }], all: 'All roles', width: 2 },
                    { name: 'status', type: 'select', options: [{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }], all: 'All statuses', width: 2 }
                ],
                columns: [
                    { label: 'Name', render: function (r) { return '<span class="fw-semibold">' + W.esc(r.name) + '</span>'; } },
                    { label: 'Email', render: function (r) { return W.esc(r.email); } },
                    { label: 'Role', render: function (r) { return r.role === 'admin' ? W.badge('Administrator', 'primary') : W.badge('Staff', 'secondary'); } },
                    { label: 'Last sign-in', render: function (r) { return r.last_login_at ? W.datetime(r.last_login_at) : 'Never'; } },
                    { label: 'Status', render: function (r) { return W.activeBadge(r.is_active); } }
                ],
                fields: function (row) {
                    return [
                        { name: 'name', label: 'Full name', required: true, maxlength: 120 },
                        { name: 'email', label: 'Email', type: 'email', required: true, maxlength: 150 },
                        { name: 'role', label: 'Role', type: 'select', required: true, options: [{ value: 'admin', label: 'Administrator' }, { value: 'staff', label: 'Staff' }], placeholder: null },
                        { name: 'password', label: row ? 'New password' : 'Password', type: 'password', required: !row, autocomplete: 'new-password', hint: row ? 'Leave blank to keep the current password.' : 'At least 8 characters with a letter and a number.' },
                        { name: 'is_active', label: 'Active', type: 'checkbox', col: 12 }
                    ];
                }
            });
        }
    };

    W.routes.activity = {
        title: 'Activity Logs',
        admin: true,
        render: async function (el) {
            el.innerHTML = W.pageHeader('Activity Logs', 'Audit trail of who did what in the system') + '<div id="lv"></div>';
            new W.ListView(el.querySelector('#lv'), {
                resource: 'activity',
                perPage: 20,
                empty: 'No activity logged yet.',
                filters: [
                    { name: 'search', type: 'search', placeholder: 'Search action, details or user', width: 4 },
                    { name: 'from', type: 'date', label: 'From', width: 2 },
                    { name: 'to', type: 'date', label: 'To', width: 2 }
                ],
                columns: [
                    { label: 'When', class: 'text-nowrap small', render: function (r) { return W.datetime(r.created_at); } },
                    { label: 'User', render: function (r) { return W.esc(r.user_name || 'System'); } },
                    { label: 'Action', render: function (r) { return '<code>' + W.esc(r.action) + '</code>'; } },
                    { label: 'Details', render: function (r) { return W.esc(r.details || ''); } },
                    { label: 'IP address', render: function (r) { return W.esc(r.ip_address || ''); } }
                ]
            });
        }
    };
}());
