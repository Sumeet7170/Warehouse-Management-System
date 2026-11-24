(function () {
    var cfg = window.WMS_CONFIG;

    // Elements
    var subtitle = document.getElementById('authSubtitle');
    var tabSignInBtn = document.getElementById('tabSignInBtn');
    var tabSignUpBtn = document.getElementById('tabSignUpBtn');
    var linkToSignUp = document.getElementById('linkToSignUp');
    var linkToSignIn = document.getElementById('linkToSignIn');

    var alertBox = document.getElementById('authAlert');
    var successBox = document.getElementById('authSuccess');

    var loginForm = document.getElementById('loginForm');
    var signupForm = document.getElementById('signupForm');

    var loginBtn = document.getElementById('loginBtn');
    var regBtn = document.getElementById('regBtn');

    function endpoint(action) {
        var url = new URL(cfg.api, window.location.href);
        url.searchParams.set('r', 'auth');
        url.searchParams.set('a', action);
        return url;
    }

    function clearMessages() {
        alertBox.textContent = '';
        alertBox.classList.add('d-none');
        successBox.textContent = '';
        successBox.classList.add('d-none');
    }

    function showError(message) {
        clearMessages();
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    }

    function showSuccess(message) {
        clearMessages();
        successBox.textContent = message;
        successBox.classList.remove('d-none');
    }

    function switchTab(mode) {
        clearMessages();
        if (mode === 'signup') {
            tabSignInBtn.classList.remove('active');
            tabSignUpBtn.classList.add('active');
            loginForm.classList.add('d-none');
            signupForm.classList.remove('d-none');
            subtitle.textContent = 'Create your new account';
            var firstInp = document.getElementById('regName');
            if (firstInp) { firstInp.focus(); }
        } else {
            tabSignUpBtn.classList.remove('active');
            tabSignInBtn.classList.add('active');
            signupForm.classList.add('d-none');
            loginForm.classList.remove('d-none');
            subtitle.textContent = 'Sign in to your account';
            var emailInp = document.getElementById('loginEmail');
            if (emailInp) { emailInp.focus(); }
        }
    }

    // Tab event listeners
    tabSignInBtn.addEventListener('click', function () { switchTab('signin'); });
    tabSignUpBtn.addEventListener('click', function () { switchTab('signup'); });
    if (linkToSignUp) { linkToSignUp.addEventListener('click', function () { switchTab('signup'); }); }
    if (linkToSignIn) { linkToSignIn.addEventListener('click', function () { switchTab('signin'); }); }

    // Check hash for #signup
    if (window.location.hash === '#signup') {
        switchTab('signup');
    }

    // Password visibility toggles
    function setupToggle(btnId, inputId) {
        var btn = document.getElementById(btnId);
        var input = document.getElementById(inputId);
        if (btn && input) {
            btn.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
            });
        }
    }
    setupToggle('toggleLoginPw', 'loginPassword');
    setupToggle('toggleRegPw', 'regPassword');
    setupToggle('toggleRegConfirmPw', 'regConfirmPassword');

    // Check existing session - show banner without force-redirecting away
    fetch(endpoint('me'), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (json) {
            if (json.success && json.data && json.data.user) {
                var u = json.data.user;
                var banner = document.createElement('div');
                banner.className = 'alert alert-info py-2 px-3 mb-3 d-flex align-items-center justify-content-between';
                banner.id = 'activeSessionBanner';
                banner.innerHTML = '<div class="small me-2"><i class="bi bi-person-check me-1"></i>Signed in as <strong>' +
                    (u.name || u.email) + '</strong> (' + u.email + ')</div>' +
                    '<div class="d-flex gap-1">' +
                    '<a href="app.html" class="btn btn-sm btn-primary py-0">Dashboard</a>' +
                    '<button type="button" class="btn btn-sm btn-outline-secondary py-0" id="btnSignOutNotice">Switch</button>' +
                    '</div>';

                var navTabs = document.querySelector('.auth-tabs');
                if (navTabs && navTabs.parentNode) {
                    navTabs.parentNode.insertBefore(banner, navTabs);
                }

                var signoutBtn = document.getElementById('btnSignOutNotice');
                if (signoutBtn) {
                    signoutBtn.addEventListener('click', function () {
                        fetch(endpoint('logout'), { method: 'POST', credentials: 'same-origin' })
                            .then(function () {
                                if (banner && banner.parentNode) { banner.parentNode.removeChild(banner); }
                                showSuccess('Signed out. You can now sign in or register a new account.');
                            });
                    });
                }
            }
        })
        .catch(function () {});

    // Sign In Submission
    loginForm.addEventListener('submit', function (event) {
        event.preventDefault();
        clearMessages();
        var email = document.getElementById('loginEmail').value.trim();
        var password = document.getElementById('loginPassword').value;
        if (!email || !password) {
            showError('Please enter your email and password.');
            return;
        }

        loginBtn.disabled = true;
        loginBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Signing in...';

        fetch(endpoint('login'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ email: email, password: password })
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (json.success) {
                    showSuccess('Sign in successful! Redirecting...');
                    setTimeout(function () {
                        window.location.replace('app.html');
                    }, 300);
                    return;
                }
                showError(json.message || 'Invalid email or password.');
            })
            .catch(function () {
                showError('Cannot reach the server. Please make sure the service is running.');
            })
            .finally(function () {
                loginBtn.disabled = false;
                loginBtn.textContent = 'Sign in';
            });
    });

    // Sign Up Submission
    signupForm.addEventListener('submit', function (event) {
        event.preventDefault();
        clearMessages();

        var name = document.getElementById('regName').value.trim();
        var email = document.getElementById('regEmail').value.trim();
        var role = document.getElementById('regRole').value;
        var password = document.getElementById('regPassword').value;
        var confirmPassword = document.getElementById('regConfirmPassword').value;

        if (!name) {
            showError('Please enter your full name.');
            return;
        }
        if (!email) {
            showError('Please enter your email address.');
            return;
        }
        if (!password) {
            showError('Please enter a password.');
            return;
        }
        if (password.length < 8 || !/[A-Za-z]/.test(password) || !/\d/.test(password)) {
            showError('Password must be at least 8 characters and contain both letters and numbers.');
            return;
        }
        if (password !== confirmPassword) {
            showError('Passwords do not match. Please re-enter.');
            return;
        }

        regBtn.disabled = true;
        regBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating account...';

        fetch(endpoint('register'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                name: name,
                email: email,
                role: role,
                password: password,
                confirm_password: confirmPassword
            })
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (json.success) {
                    showSuccess('Account created successfully! Your new workspace is ready from 0. Redirecting...');
                    setTimeout(function () {
                        window.location.replace('app.html');
                    }, 400);
                    return;
                }
                var errMsg = json.message || 'Registration failed.';
                if (json.errors && Object.keys(json.errors).length) {
                    var firstKey = Object.keys(json.errors)[0];
                    errMsg = json.errors[firstKey];
                }
                showError(errMsg);
            })
            .catch(function () {
                showError('Cannot reach the server. Please make sure the service is running.');
            })
            .finally(function () {
                regBtn.disabled = false;
                regBtn.textContent = 'Create Account';
            });
    });
}());
