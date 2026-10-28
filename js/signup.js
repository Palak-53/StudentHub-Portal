/* =========================================================
   STUDENT HUB — REGISTRATION FORM VALIDATION
   signup.js — JS DOM manipulation, Regex validation,
               Password strength, Error messages, Accessibility
   ========================================================= */

'use strict';

document.addEventListener('DOMContentLoaded', () => {

    // ── DOM References ──────────────────────────────────────
    const form            = document.getElementById('registrationForm');
    const regSuccess      = document.getElementById('regSuccess');

    const fldFullName     = document.getElementById('field-fullName');
    const fldEnrollment   = document.getElementById('field-enrollmentNumber');
    const fldEmail        = document.getElementById('field-email');
    const fldMobile       = document.getElementById('field-mobile');
    const fldCourse       = document.getElementById('field-course');
    const fldYear         = document.getElementById('field-year');
    const fldGender       = document.getElementById('field-gender');
    const fldPassword     = document.getElementById('field-password');
    const fldConfirm      = document.getElementById('field-confirmPassword');
    const fldTerms        = document.getElementById('field-terms');

    const inFullName      = document.getElementById('fullName');
    const inEnrollment    = document.getElementById('enrollmentNumber');
    const inEmail         = document.getElementById('email');
    const inMobile        = document.getElementById('mobile');
    const inCourse        = document.getElementById('course');
    const inYear          = document.getElementById('year');
    const inPassword      = document.getElementById('password');
    const inConfirm       = document.getElementById('confirmPassword');
    const inTerms         = document.getElementById('terms');

    const strengthFill    = document.getElementById('strengthFill');
    const strengthText    = document.getElementById('password-strength-text');

    const reqLength       = document.getElementById('req-length');
    const reqUpper        = document.getElementById('req-upper');
    const reqLower        = document.getElementById('req-lower');
    const reqNumber       = document.getElementById('req-number');
    const reqSpecial      = document.getElementById('req-special');

    const togglePwd       = document.getElementById('togglePassword');
    const toggleConfirm   = document.getElementById('toggleConfirmPassword');

    // ── Regular Expressions ─────────────────────────────────

    /** Full name: 2–60 chars, only letters (including accented) and spaces */
    const REGEX_NAME       = /^[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\s'-]{1,59}$/;

    /** Enrollment: 4–20 alphanumeric + optional hyphens */
    const REGEX_ENROLLMENT = /^[A-Za-z0-9\-]{4,20}$/;

    /** Email: standard RFC-5321 simplified */
    const REGEX_EMAIL      = /^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/;

    /** Mobile: exactly 10 digits, first digit 6-9 (Indian format) */
    const REGEX_MOBILE     = /^[6-9]\d{9}$/;

    /** Password: at least 8 chars, one uppercase, one lowercase, one digit, one special char */
    const REGEX_PASSWORD   = /^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?`~])[A-Za-z\d!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?`~]{8,64}$/;


    // ── Helper: show or clear error on a field ──────────────
    /**
     * @param {HTMLElement} fieldDiv - the .reg-field wrapper
     * @param {string}      errorId  - id of the <span class="reg-error">
     * @param {string}      message  - error text, or '' to mark valid
     */
    function setFieldState(fieldDiv, errorId, message) {
        const errSpan = document.getElementById(errorId);
        if (message) {
            fieldDiv.classList.remove('is-valid');
            fieldDiv.classList.add('is-invalid');
            if (errSpan) errSpan.textContent = message;
        } else {
            fieldDiv.classList.remove('is-invalid');
            fieldDiv.classList.add('is-valid');
            if (errSpan) errSpan.textContent = '';
        }
    }

    /** Clear all validation state from a field */
    function clearFieldState(fieldDiv, errorId) {
        const errSpan = document.getElementById(errorId);
        fieldDiv.classList.remove('is-valid', 'is-invalid');
        if (errSpan) errSpan.textContent = '';
    }


    // ── Individual Validators ────────────────────────────────

    function validateFullName() {
        const val = inFullName.value.trim();
        if (!val) {
            setFieldState(fldFullName, 'fullName-error', 'Full name is required.');
            return false;
        }
        if (!REGEX_NAME.test(val)) {
            setFieldState(fldFullName, 'fullName-error', 'Name must be 2–60 characters and contain only letters and spaces.');
            return false;
        }
        setFieldState(fldFullName, 'fullName-error', '');
        return true;
    }

    function validateEnrollment() {
        const val = inEnrollment.value.trim();
        if (!val) {
            setFieldState(fldEnrollment, 'enrollmentNumber-error', 'Enrollment number is required.');
            return false;
        }
        if (!REGEX_ENROLLMENT.test(val)) {
            setFieldState(fldEnrollment, 'enrollmentNumber-error', 'Enrollment must be 4–20 characters (letters, numbers, hyphens only).');
            return false;
        }
        setFieldState(fldEnrollment, 'enrollmentNumber-error', '');
        return true;
    }

    function validateEmail() {
        const val = inEmail.value.trim();
        if (!val) {
            setFieldState(fldEmail, 'email-error', 'Email address is required.');
            return false;
        }
        if (!REGEX_EMAIL.test(val)) {
            setFieldState(fldEmail, 'email-error', 'Enter a valid email address (e.g. student@college.edu).');
            return false;
        }
        setFieldState(fldEmail, 'email-error', '');
        return true;
    }

    function validateMobile() {
        const val = inMobile.value.trim();
        if (!val) {
            setFieldState(fldMobile, 'mobile-error', 'Mobile number is required.');
            return false;
        }
        if (!REGEX_MOBILE.test(val)) {
            setFieldState(fldMobile, 'mobile-error', 'Enter a valid 10-digit mobile number starting with 6–9.');
            return false;
        }
        setFieldState(fldMobile, 'mobile-error', '');
        return true;
    }

    function validateCourse() {
        if (!inCourse.value) {
            setFieldState(fldCourse, 'course-error', 'Please select your course.');
            return false;
        }
        setFieldState(fldCourse, 'course-error', '');
        return true;
    }

    function validateYear() {
        if (!inYear.value) {
            setFieldState(fldYear, 'year-error', 'Please select your year of study.');
            return false;
        }
        setFieldState(fldYear, 'year-error', '');
        return true;
    }

    function validateGender() {
        const selected = document.querySelector('input[name="gender"]:checked');
        if (!selected) {
            setFieldState(fldGender, 'gender-error', 'Please select your gender.');
            return false;
        }
        setFieldState(fldGender, 'gender-error', '');
        return true;
    }

    function validatePassword() {
        const val = inPassword.value;
        if (!val) {
            setFieldState(fldPassword, 'password-error', 'Password is required.');
            return false;
        }
        if (!REGEX_PASSWORD.test(val)) {
            setFieldState(fldPassword, 'password-error', 'Password must meet all the requirements listed below.');
            return false;
        }
        setFieldState(fldPassword, 'password-error', '');
        return true;
    }

    function validateConfirmPassword() {
        const pwd  = inPassword.value;
        const conf = inConfirm.value;
        if (!conf) {
            setFieldState(fldConfirm, 'confirmPassword-error', 'Please confirm your password.');
            return false;
        }
        if (pwd !== conf) {
            setFieldState(fldConfirm, 'confirmPassword-error', 'Passwords do not match. Please re-enter.');
            return false;
        }
        setFieldState(fldConfirm, 'confirmPassword-error', '');
        return true;
    }

    function validateTerms() {
        if (!inTerms.checked) {
            setFieldState(fldTerms, 'terms-error', 'You must accept the Terms & Conditions to register.');
            return false;
        }
        setFieldState(fldTerms, 'terms-error', '');
        return true;
    }


    // ── Password Strength ────────────────────────────────────

    /**
     * Returns a score 0–4 based on which criteria are met.
     * Also updates the requirement checklist items.
     */
    function getPasswordStrength(val) {
        const checks = {
            length : val.length >= 8,
            upper  : /[A-Z]/.test(val),
            lower  : /[a-z]/.test(val),
            number : /\d/.test(val),
            special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?`~]/.test(val),
        };

        // Update requirement checklist
        updateReqItem(reqLength,  checks.length,  'Minimum 8 characters');
        updateReqItem(reqUpper,   checks.upper,   'At least one uppercase letter');
        updateReqItem(reqLower,   checks.lower,   'At least one lowercase letter');
        updateReqItem(reqNumber,  checks.number,  'At least one number');
        updateReqItem(reqSpecial, checks.special, 'At least one special character (!@#$…)');

        return Object.values(checks).filter(Boolean).length;
    }

    function updateReqItem(el, isMet, text) {
        if (!el) return;
        if (isMet) {
            el.textContent = '✓ ' + text;
            el.classList.add('met');
        } else {
            el.textContent = '✗ ' + text;
            el.classList.remove('met');
        }
    }

    function updateStrengthUI(score) {
        // Remove all level classes
        strengthFill.className = 'strength-fill';
        strengthText.className = 'strength-label';

        if (score === 0) {
            strengthFill.style.width = '0%';
            strengthText.textContent = '';
            return;
        }

        const levels = ['', 'weak', 'fair', 'good', 'strong', 'strong'];
        const labels = ['', '🔴 Weak', '🟠 Fair', '🟡 Good', '🟢 Strong'];
        const level  = levels[score];
        const label  = labels[score] || labels[4];

        strengthFill.classList.add(level);
        strengthText.classList.add(level);
        strengthText.textContent = label;
    }


    // ── Show / Hide Password Toggle ──────────────────────────

    function setupEyeToggle(toggleBtn, inputEl) {
        if (!toggleBtn || !inputEl) return;
        toggleBtn.addEventListener('click', () => {
            const isPassword = inputEl.type === 'password';
            inputEl.type = isPassword ? 'text' : 'password';
            toggleBtn.textContent = isPassword ? '🙈' : '👁️';
            toggleBtn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            inputEl.focus();
        });
    }

    setupEyeToggle(togglePwd,     inPassword);
    setupEyeToggle(toggleConfirm, inConfirm);


    // ── Live (on-input / on-change) Validation ───────────────

    inFullName.addEventListener('input',  () => {
        if (fldFullName.classList.contains('is-invalid') || fldFullName.classList.contains('is-valid')) {
            validateFullName();
        }
    });
    inFullName.addEventListener('blur', validateFullName);

    inEnrollment.addEventListener('input', () => {
        if (fldEnrollment.classList.contains('is-invalid') || fldEnrollment.classList.contains('is-valid')) {
            validateEnrollment();
        }
    });
    inEnrollment.addEventListener('blur', validateEnrollment);

    inEmail.addEventListener('input', () => {
        if (fldEmail.classList.contains('is-invalid') || fldEmail.classList.contains('is-valid')) {
            validateEmail();
        }
    });
    inEmail.addEventListener('blur', validateEmail);

    inMobile.addEventListener('input', () => {
        // Allow only digits
        inMobile.value = inMobile.value.replace(/\D/g, '').slice(0, 10);
        if (fldMobile.classList.contains('is-invalid') || fldMobile.classList.contains('is-valid')) {
            validateMobile();
        }
    });
    inMobile.addEventListener('blur', validateMobile);

    inCourse.addEventListener('change', validateCourse);
    inYear.addEventListener('change',   validateYear);

    // Gender radio buttons
    document.querySelectorAll('input[name="gender"]').forEach(radio => {
        radio.addEventListener('change', validateGender);
    });

    // Password: live strength + requirement checklist
    inPassword.addEventListener('input', () => {
        const val   = inPassword.value;
        const score = getPasswordStrength(val);
        updateStrengthUI(score);

        // Re-validate if already touched
        if (fldPassword.classList.contains('is-invalid') || fldPassword.classList.contains('is-valid')) {
            validatePassword();
        }

        // Re-validate confirm if it already has a value
        if (inConfirm.value) validateConfirmPassword();
    });
    inPassword.addEventListener('blur', validatePassword);

    inConfirm.addEventListener('input', () => {
        if (fldConfirm.classList.contains('is-invalid') || fldConfirm.classList.contains('is-valid')) {
            validateConfirmPassword();
        }
    });
    inConfirm.addEventListener('blur', validateConfirmPassword);

    inTerms.addEventListener('change', validateTerms);


    // ── Form Submit ──────────────────────────────────────────

    form.addEventListener('submit', (e) => {
        // Run all validators and collect results
        const results = [
            validateFullName(),
            validateEnrollment(),
            validateEmail(),
            validateMobile(),
            validateCourse(),
            validateYear(),
            validateGender(),
            validatePassword(),
            validateConfirmPassword(),
            validateTerms(),
        ];

        const allValid = results.every(Boolean);

        if (!allValid) {
            // Prevent PHP submission — show inline JS errors
            e.preventDefault();

            // Scroll to first invalid field and focus it
            const firstInvalid = form.querySelector('.reg-field.is-invalid');
            if (firstInvalid) {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                const focusable = firstInvalid.querySelector('input, select');
                if (focusable) setTimeout(() => focusable.focus(), 300);
            }
            return;
        }

        // All fields valid — allow natural form POST to process.php
        // Do NOT call e.preventDefault() — form submits to PHP backend
    });

});
