/**
 * Obsługuje całą logikę po stronie klienta dla formularza rejestracji,
 * w tym przełączanie widoków, walidację siły hasła ORAZ wysyłkę AJAX.
 */
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('register-form');
    if (!form) {
        return; // Zakończ, jeśli formularz nie istnieje na tej stronie
    }

    // ====================================================================
    // SEKCJA 1: Logika interfejsu (Twoja istniejąca, poprawna logika)
    // ====================================================================

    // --- Przełączanie ról: Uczestnik / Organizacja ---
    const sectionOrganizer = document.getElementById('section-organizer');
    const sectionMember = document.getElementById('section-member');
    const roleRadios = form.querySelectorAll('input[name="role"]');

    function toggleRoleSections(selectedRole) {
        const isOrganizer = (selectedRole === 'organizer');
        if (sectionOrganizer) sectionOrganizer.style.display = isOrganizer ? 'block' : 'none';
        if (sectionMember) sectionMember.style.display = isOrganizer ? 'none' : 'block';
    }

    roleRadios.forEach(radio => {
        radio.addEventListener('change', function () { toggleRoleSections(this.value); });
    });

    const initialRoleElement = form.querySelector('input[name="role"]:checked');
    if (initialRoleElement) toggleRoleSections(initialRoleElement.value);

    // --- Sprawdzanie siły hasła ---
    const passwordInput = document.getElementById('password');
    const meter = document.getElementById('password-strength-meter');
    const requirements = {
        length: document.getElementById('length'),
        uppercase: document.getElementById('uppercase'),
        lowercase: document.getElementById('lowercase'),
        digit: document.getElementById('digit')
    };

    function checkPasswordStrength() {
        if (typeof zxcvbn === 'undefined' || !passwordInput) return;
        const pass = passwordInput.value;
        const result = zxcvbn(pass);
        if (meter) meter.value = result.score;

        const checks = {
            length: pass.length >= 8,
            uppercase: /[A-Z]/.test(pass),
            lowercase: /[a-z]/.test(pass),
            digit: /\d/.test(pass)
        };
        for (const key in requirements) {
            if (requirements[key]) {
                const isValid = checks[key];
                const item = requirements[key];
                const icon = isValid ? '✔' : '✖';
                item.classList.toggle("valid", isValid);
                item.textContent = `${icon} ${item.textContent.slice(2)}`;
            }
        }
    }

    if (passwordInput) passwordInput.addEventListener('input', checkPasswordStrength);

    // ====================================================================
    // SEKCJA 2: Logika wysyłki formularza AJAX (brakujący element)
    // ====================================================================
    const messagesDiv = document.getElementById('form-messages');

    form.addEventListener('submit', async function (event) {
        event.preventDefault(); // Zablokuj domyślne przeładowanie strony
        if (messagesDiv) messagesDiv.innerHTML = '';

        const submitButton = form.querySelector('button[type="submit"]');
        const originalButtonText = submitButton.innerHTML;
        submitButton.disabled = true;
        submitButton.innerHTML = 'Przetwarzanie...';

        const formData = new FormData(form);
        formData.append('action', 'devents_register');
        formData.append('_wpnonce', devents_ajax_object.nonce); // Używamy nonce przekazanego z PHP

        try {
            const response = await fetch(devents_ajax_object.ajax_url, { // Używamy URL przekazanego z PHP
                method: 'POST',
                body: formData,
            });
            const result = await response.json();

            if (result.success) {
                if (messagesDiv) {
                    messagesDiv.className = 'message-box message-box--success auth-message';
                    messagesDiv.innerHTML = result.data.message;
                }
                form.style.display = 'none'; // Ukryj formularz po sukcesie
                const separator = document.querySelector('.auth-separator');
                const secondaryAction = document.querySelector('.auth-secondary-action');
                if (separator) separator.style.display = 'none';
                if (secondaryAction) secondaryAction.style.display = 'none';
            } else {
                if (messagesDiv) {
                    messagesDiv.className = 'message-box message-box--error auth-message';
                    messagesDiv.innerHTML = result.data.message;
                }
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            }
        } catch (error) {
            console.error('Błąd AJAX:', error);
            if (messagesDiv) {
                messagesDiv.className = 'message-box message-box--error auth-message';
                messagesDiv.innerHTML = 'Wystąpił nieoczekiwany błąd. Spróbuj ponownie później.';
            }
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonText;
        }
    });
});