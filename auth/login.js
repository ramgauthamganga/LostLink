/**
 * LostLink Authentication Page
 * Handles form switching, validation, password toggles, loading states
 * Production-ready vanilla JavaScript
 */

(function() {
    'use strict';

    // ========== DOM Elements ==========
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const showRegisterBtn = document.getElementById('show-register');
    const showLoginBtn = document.getElementById('show-login');

    // ========== URL Parameter Detection ==========
    function getUrlParam(param) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param);
    }

    // ========== Form Switching ==========
    function switchForm(showForm, hideForm) {
        // Animate out
        hideForm.style.opacity = '0';
        hideForm.style.transform = 'translateX(-20px)';

        setTimeout(() => {
            hideForm.style.display = 'none';
            hideForm.classList.remove('showing');
            hideForm.classList.add('hidden');

            // Reset and show new form
            showForm.style.display = 'flex';
            showForm.classList.remove('hidden');
            showForm.classList.add('showing');

            // Small delay for transition
            requestAnimationFrame(() => {
                showForm.style.opacity = '1';
                showForm.style.transform = 'translateX(0)';
            });

            // Reset validation states
            resetFormValidation(showForm);
            resetFormValidation(hideForm);
        }, 250);
    }

    function resetFormValidation(form) {
        const inputs = form.querySelectorAll('input');
        const msgs = form.querySelectorAll('.validation-msg');

        inputs.forEach(input => {
            input.classList.remove('error', 'success');
            input.value = '';
        });

        msgs.forEach(msg => {
            msg.textContent = '';
            msg.className = 'validation-msg';
        });

        // Reset checkboxes
        const checkboxes = form.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(cb => cb.checked = false);

        // Reset password toggles
        const passwordInputs = form.querySelectorAll('input[type="password"], input[type="text"]');
        passwordInputs.forEach(input => {
            if (input.id.includes('password') || input.id.includes('confirm')) {
                input.type = 'password';
            }
        });

        // Reset toggle button icons
        const toggles = form.querySelectorAll('.toggle-password');
        toggles.forEach(toggle => {
            toggle.querySelector('.eye-icon').style.display = 'block';
            toggle.querySelector('.eye-off-icon').style.display = 'none';
            toggle.setAttribute('aria-label', 'Show password');
        });

        // Reset submit button
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.querySelector('.btn-text').style.display = 'inline';
            submitBtn.querySelector('.btn-spinner').style.display = 'none';
        }
    }

    // ========== Event Listeners for Form Switching ==========
    if (showRegisterBtn) {
        showRegisterBtn.addEventListener('click', () => {
            switchForm(registerForm, loginForm);
            // Update URL without reload
            const newUrl = new URL(window.location);
            newUrl.searchParams.set('mode', 'register');
            window.history.pushState({}, '', newUrl);
        });
    }

    if (showLoginBtn) {
        showLoginBtn.addEventListener('click', () => {
            switchForm(loginForm, registerForm);
            // Update URL without reload
            const newUrl = new URL(window.location);
            newUrl.searchParams.set('mode', 'login');
            window.history.pushState({}, '', newUrl);
        });
    }

    // ========== Password Toggle ==========
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const eyeIcon = this.querySelector('.eye-icon');
            const eyeOffIcon = this.querySelector('.eye-off-icon');

            if (input.type === 'password') {
                input.type = 'text';
                eyeIcon.style.display = 'none';
                eyeOffIcon.style.display = 'block';
                this.setAttribute('aria-label', 'Hide password');
            } else {
                input.type = 'password';
                eyeIcon.style.display = 'block';
                eyeOffIcon.style.display = 'none';
                this.setAttribute('aria-label', 'Show password');
            }
        });
    });

    // ========== Validation Helpers ==========
    function showError(input, msgId, message) {
        input.classList.remove('success');
        input.classList.add('error');
        const msg = document.getElementById(msgId);
        if (msg) {
            msg.textContent = message;
            msg.className = 'validation-msg error';
        }
    }

    function showSuccess(input, msgId, message) {
        input.classList.remove('error');
        input.classList.add('success');
        const msg = document.getElementById(msgId);
        if (msg) {
            msg.textContent = message || '';
            msg.className = 'validation-msg success';
        }
    }

    function clearValidation(input, msgId) {
        input.classList.remove('error', 'success');
        const msg = document.getElementById(msgId);
        if (msg) {
            msg.textContent = '';
            msg.className = 'validation-msg';
        }
    }

    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    function isValidPassword(password) {
        // Minimum 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special char
        const re = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
        return re.test(password);
    }

    function isValidPhone(phone) {
        const re = /^\d{8,20}$/;
        return re.test(phone);
    }

    // ========== Real-time Validation ==========
    // Login Email
    const loginEmail = document.getElementById('login-email');
    if (loginEmail) {
        loginEmail.addEventListener('blur', function() {
            if (!this.value) {
                showError(this, 'login-email-error', 'Email is required');
            } else if (!isValidEmail(this.value)) {
                showError(this, 'login-email-error', 'Please enter a valid email address');
            } else if (this.value.length > 150) {
                showError(this, 'login-email-error', 'Email cannot exceed 150 characters');
            } else {
                showSuccess(this, 'login-email-error', '');
            }
        });
        loginEmail.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                clearValidation(this, 'login-email-error');
            }
        });
    }

    // Login Password
    const loginPassword = document.getElementById('login-password');
    if (loginPassword) {
        loginPassword.addEventListener('blur', function() {
            if (!this.value) {
                showError(this, 'login-password-error', 'Password is required');
            } else if (this.value.length < 8) {
                showError(this, 'login-password-error', 'Password must be at least 8 characters');
            } else {
                showSuccess(this, 'login-password-error', '');
            }
        });
        loginPassword.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                clearValidation(this, 'login-password-error');
            }
        });
    }

    // Register Name
    const regName = document.getElementById('reg-name');
    if (regName) {
        regName.addEventListener('blur', function() {
            if (!this.value.trim()) {
                showError(this, 'reg-name-error', 'Full name is required');
            } else if (this.value.trim().length < 2) {
                showError(this, 'reg-name-error', 'Name must be at least 2 characters');
            } else if (this.value.trim().length > 150) {
                showError(this, 'reg-name-error', 'Name cannot exceed 150 characters');
            } else {
                showSuccess(this, 'reg-name-error', '');
            }
        });
        regName.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                clearValidation(this, 'reg-name-error');
            }
        });
    }

    // Register Email
    const regEmail = document.getElementById('reg-email');
    if (regEmail) {
        regEmail.addEventListener('blur', function() {
            if (!this.value) {
                showError(this, 'reg-email-error', 'Email is required');
            } else if (!isValidEmail(this.value)) {
                showError(this, 'reg-email-error', 'Please enter a valid email address');
            } else if (this.value.length > 150) {
                showError(this, 'reg-email-error', 'Email cannot exceed 150 characters');
            } else {
                showSuccess(this, 'reg-email-error', '');
            }
        });
        regEmail.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                clearValidation(this, 'reg-email-error');
            }
        });
    }

    // Register Department
    const regDept = document.getElementById('reg-dept');
    if (regDept) {
        regDept.addEventListener('blur', function() {
            if (!this.value.trim()) {
                showError(this, 'reg-dept-error', 'Department is required');
            } else if (this.value.trim().length > 100) {
                showError(this, 'reg-dept-error', 'Department cannot exceed 100 characters');
            } else {
                showSuccess(this, 'reg-dept-error', '');
            }
        });
        regDept.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                clearValidation(this, 'reg-dept-error');
            }
        });
    }

    // Register Student ID
    const regStudentId = document.getElementById('reg-studentid');
    if (regStudentId) {
        regStudentId.addEventListener('blur', function() {
            if (!this.value.trim()) {
                showError(this, 'reg-studentid-error', 'Student ID is required');
            } else if (this.value.trim().length > 20) {
                showError(this, 'reg-studentid-error', 'Student ID cannot exceed 20 characters');
            } else {
                showSuccess(this, 'reg-studentid-error', '');
            }
        });
        regStudentId.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                clearValidation(this, 'reg-studentid-error');
            }
        });
    }

    // Register Phone Number
    const regPhone = document.getElementById('reg-phone');
    if (regPhone) {
        regPhone.addEventListener('blur', function() {
            if (!this.value.trim()) {
                showError(this, 'reg-phone-error', 'Phone number is required');
            } else if (!isValidPhone(this.value.trim())) {
                showError(this, 'reg-phone-error', 'Please enter a valid phone number (digits only, 8-20 characters)');
            } else {
                showSuccess(this, 'reg-phone-error', '');
            }
        });
        regPhone.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                clearValidation(this, 'reg-phone-error');
            }
        });
    }

    // Register Password
    const regPassword = document.getElementById('reg-password');
    if (regPassword) {
        regPassword.addEventListener('blur', function() {
            if (!this.value) {
                showError(this, 'reg-password-error', 'Password is required');
            } else if (!isValidPassword(this.value)) {
                showError(this, 'reg-password-error', 'Min 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special char');
            } else {
                showSuccess(this, 'reg-password-error', 'Strong password');
            }
        });
        regPassword.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                clearValidation(this, 'reg-password-error');
            }
            // Also re-validate confirm password if it has a value
            const confirm = document.getElementById('reg-confirm');
            if (confirm && confirm.value) {
                if (confirm.value !== this.value) {
                    showError(confirm, 'reg-confirm-error', 'Passwords do not match');
                } else {
                    showSuccess(confirm, 'reg-confirm-error', 'Passwords match');
                }
            }
        });
    }

    // Register Confirm Password
    const regConfirm = document.getElementById('reg-confirm');
    if (regConfirm) {
        regConfirm.addEventListener('blur', function() {
            const password = document.getElementById('reg-password');
            if (!this.value) {
                showError(this, 'reg-confirm-error', 'Please confirm your password');
            } else if (password && this.value !== password.value) {
                showError(this, 'reg-confirm-error', 'Passwords do not match');
            } else {
                showSuccess(this, 'reg-confirm-error', 'Passwords match');
            }
        });
        regConfirm.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                clearValidation(this, 'reg-confirm-error');
            }
        });
    }

    // ========== Form Submission ==========
    function setLoading(button, isLoading) {
        const text = button.querySelector('.btn-text');
        const spinner = button.querySelector('.btn-spinner');

        if (isLoading) {
            button.disabled = true;
            text.style.display = 'none';
            spinner.style.display = 'inline-flex';
        } else {
            button.disabled = false;
            text.style.display = 'inline';
            spinner.style.display = 'none';
        }
    }

    // Login Submit
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            let isValid = true;

            // Validate email
            if (!loginEmail.value) {
                showError(loginEmail, 'login-email-error', 'Email is required');
                isValid = false;
            } else if (!isValidEmail(loginEmail.value)) {
                showError(loginEmail, 'login-email-error', 'Please enter a valid email address');
                isValid = false;
            }

            // Validate password
            if (!loginPassword.value) {
                showError(loginPassword, 'login-password-error', 'Password is required');
                isValid = false;
            } else if (loginPassword.value.length < 8) {
                showError(loginPassword, 'login-password-error', 'Password must be at least 8 characters');
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
                return;
            }

            const submitBtn = document.getElementById('login-submit');
            setLoading(submitBtn, true);
        });
    }

    // Register Submit
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            let isValid = true;

            // Validate name
            if (!regName.value.trim()) {
                showError(regName, 'reg-name-error', 'Full name is required');
                isValid = false;
            } else if (regName.value.trim().length < 2) {
                showError(regName, 'reg-name-error', 'Name must be at least 2 characters');
                isValid = false;
            }

            // Validate email
            if (!regEmail.value) {
                showError(regEmail, 'reg-email-error', 'Email is required');
                isValid = false;
            } else if (!isValidEmail(regEmail.value)) {
                showError(regEmail, 'reg-email-error', 'Please enter a valid email address');
                isValid = false;
            }

            // Validate department
            if (!regDept.value.trim()) {
                showError(regDept, 'reg-dept-error', 'Department is required');
                isValid = false;
            } else if (regDept.value.trim().length > 100) {
                showError(regDept, 'reg-dept-error', 'Department cannot exceed 100 characters');
                isValid = false;
            }

            // Validate student ID
            if (!regStudentId.value.trim()) {
                showError(regStudentId, 'reg-studentid-error', 'Student ID is required');
                isValid = false;
            } else if (regStudentId.value.trim().length > 20) {
                showError(regStudentId, 'reg-studentid-error', 'Student ID cannot exceed 20 characters');
                isValid = false;
            }

            // Validate phone
            if (!regPhone.value.trim()) {
                showError(regPhone, 'reg-phone-error', 'Phone number is required');
                isValid = false;
            } else if (!isValidPhone(regPhone.value.trim())) {
                showError(regPhone, 'reg-phone-error', 'Please enter a valid phone number (digits only, 8-20 characters)');
                isValid = false;
            }

            // Validate password
            if (!regPassword.value) {
                showError(regPassword, 'reg-password-error', 'Password is required');
                isValid = false;
            } else if (!isValidPassword(regPassword.value)) {
                showError(regPassword, 'reg-password-error', 'Min 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special char');
                isValid = false;
            }

            // Validate confirm
            if (!regConfirm.value) {
                showError(regConfirm, 'reg-confirm-error', 'Please confirm your password');
                isValid = false;
            } else if (regConfirm.value !== regPassword.value) {
                showError(regConfirm, 'reg-confirm-error', 'Passwords do not match');
                isValid = false;
            }

            // Validate terms
            const terms = document.getElementById('reg-terms');
            if (!terms.checked) {
                const termsError = document.getElementById('reg-terms-error');
                if (termsError) {
                    termsError.textContent = 'You must agree to the Terms and Privacy Policy';
                    termsError.className = 'validation-msg error';
                }
                isValid = false;
            } else {
                const termsError = document.getElementById('reg-terms-error');
                if (termsError) {
                    termsError.textContent = '';
                    termsError.className = 'validation-msg';
                }
            }

            if (!isValid) {
                e.preventDefault();
                return;
            }

            const submitBtn = document.getElementById('register-submit');
            setLoading(submitBtn, true);
        });
    }

    // ========== Terms Checkbox Real-time Clear ==========
    const regTerms = document.getElementById('reg-terms');
    if (regTerms) {
        regTerms.addEventListener('change', function() {
            const termsError = document.getElementById('reg-terms-error');
            if (this.checked && termsError) {
                termsError.textContent = '';
                termsError.className = 'validation-msg';
            }
        });
    }

    // ========== Initialize Page State ==========
    function initPageState() {
        const mode = getUrlParam('mode');

        if (mode === 'register') {
            loginForm.style.display = 'none';
            loginForm.classList.remove('showing');
            loginForm.classList.add('hidden');
            registerForm.style.display = 'flex';
            registerForm.classList.remove('hidden');
            registerForm.classList.add('showing');
        } else {
            // Default: login
            loginForm.style.display = 'flex';
            loginForm.classList.remove('hidden');
            loginForm.classList.add('showing');
            registerForm.style.display = 'none';
            registerForm.classList.remove('showing');
            registerForm.classList.add('hidden');
        }
    }

    // Handle browser back/forward buttons
    window.addEventListener('popstate', initPageState);

})();