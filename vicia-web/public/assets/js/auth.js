/**
 * assets/js/auth.js
 *
 * Comportements des écrans d'authentification : affichage/masquage
 * du mot de passe et validation JavaScript de confort (la validation
 * de sécurité réelle est systématiquement effectuée côté serveur,
 * voir app/core/Validator.php).
 */

document.addEventListener('DOMContentLoaded', () => {
    // Affichage / masquage du mot de passe
    document.querySelectorAll('.toggle-visibility').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const input = toggle.parentElement.querySelector('input');
            if (!input) return;
            const isPassword = input.getAttribute('type') === 'password';
            input.setAttribute('type', isPassword ? 'text' : 'password');
            toggle.classList.toggle('fa-eye');
            toggle.classList.toggle('fa-eye-slash');
        });
    });

    // Validation de confort du formulaire de connexion
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', (e) => {
            const email = loginForm.querySelector('[name="email"]');
            const password = loginForm.querySelector('[name="password"]');
            let valid = true;

            if (!email.value || !email.value.includes('@')) {
                setFieldError(email, 'Adresse e-mail invalide.');
                valid = false;
            } else {
                clearFieldError(email);
            }

            if (!password.value || password.value.length < 4) {
                setFieldError(password, 'Le mot de passe est requis.');
                valid = false;
            } else {
                clearFieldError(password);
            }

            if (!valid) {
                e.preventDefault();
            } else {
                const btn = loginForm.querySelector('button[type="submit"]');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner"></span> Connexion en cours...';
                }
            }
        });
    }

    // Validation de confort du formulaire de réinitialisation
    const resetForm = document.getElementById('reset-password-form');
    if (resetForm) {
        resetForm.addEventListener('submit', (e) => {
            const password = resetForm.querySelector('[name="password"]');
            const confirmation = resetForm.querySelector('[name="password_confirmation"]');

            if (password.value.length < 8) {
                setFieldError(password, 'Le mot de passe doit contenir au moins 8 caractères.');
                e.preventDefault();
                return;
            }
            clearFieldError(password);

            if (password.value !== confirmation.value) {
                setFieldError(confirmation, 'La confirmation ne correspond pas au mot de passe.');
                e.preventDefault();
                return;
            }
            clearFieldError(confirmation);
        });
    }

    function setFieldError(input, message) {
        const group = input.closest('.form-group');
        if (!group) return;
        group.classList.add('has-error');
        const errorEl = group.querySelector('.form-error');
        if (errorEl) errorEl.textContent = message;
    }

    function clearFieldError(input) {
        const group = input.closest('.form-group');
        if (group) group.classList.remove('has-error');
    }
});
