'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const passwordInput = document.getElementById('password');
    const toggleButton = document.getElementById('togglePassword');

    if (!(passwordInput instanceof HTMLInputElement) || !(toggleButton instanceof HTMLButtonElement)) {
        return;
    }

    toggleButton.addEventListener('click', () => {
        const showPassword = passwordInput.type === 'password';
        passwordInput.type = showPassword ? 'text' : 'password';
        toggleButton.textContent = showPassword ? 'Hide' : 'Show';
        toggleButton.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
        toggleButton.setAttribute('aria-pressed', String(showPassword));
    });
});
