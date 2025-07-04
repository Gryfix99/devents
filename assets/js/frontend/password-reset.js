jQuery(function ($) {
    const container = $('.auth-container');
    if (!container.length || typeof devents_reset_object === 'undefined') return;

    const steps = {
        1: container.find('#reset-step-1'),
        2: container.find('#reset-step-2'),
        3: container.find('#reset-step-3')
    };

    const messageBox = $('#form-message');
    let currentEmail = '';

    function showStep(num) {
        $.each(steps, (i, el) => (i == num ? el.show() : el.hide()));
        messageBox.hide();
    }

    function showMessage(text, success = true) {
        messageBox.text(text).removeClass().addClass(success ? 'auth-message success' : 'auth-message error').show();
    }

    // ETAP 1: Wyślij kod
    container.on('submit', '#send-code-form', function (e) {
        e.preventDefault();
        const form = $(this);
        currentEmail = $('#user_email').val();
        form.find('button').prop('disabled', true);

        $.post(devents_reset_object.ajaxurl, {
            action: 'devents_password_reset',
            nonce: devents_reset_object.nonce,
            step: 'send_code',
            email: currentEmail
        }).done(res => {
            $('#user-email-display').text(currentEmail);
            showStep(2);
        }).fail(() => {
            showMessage('Błąd połączenia. Spróbuj ponownie.', false);
        }).always(() => form.find('button').prop('disabled', false));
    });

    // Obsługa pola kodu: automatyczne przejście do następnego i backspace
    container.on('input', '.code-digit', function () {
        const $this = $(this);
        const val = $this.val();
        if (val.length > 1) {
            $this.val(val.slice(0, 1));
        }
        if (val.match(/[0-9]/)) {
            $this.next('.code-digit').focus();
        } else {
            $this.val('');
        }
    });

    container.on('keydown', '.code-digit', function (e) {
        const $this = $(this);
        if (e.key === 'Backspace' && !$this.val()) {
            $this.prev('.code-digit').focus();
        }
    });

    // ETAP 2: Zweryfikuj kod
    container.on('submit', '#validate-code-form', function (e) {
        e.preventDefault();
        let code = '';
        $('.code-digit').each(function () {
            code += $(this).val();
        });
        if (code.length !== 6 || !code.match(/^\d{6}$/)) {
            return showMessage('Proszę wpisać 6-cyfrowy kod.', false);
        }
        const form = $(this);
        form.find('button').prop('disabled', true);

        $.post(devents_reset_object.ajaxurl, {
            action: 'devents_password_reset',
            nonce: devents_reset_object.nonce,
            step: 'validate_code',
            email: currentEmail,
            code
        }).done(res => {
            if (res.success) {
                $('#set_pass_reset_code').val(code);
                $('#username-field').val(currentEmail);
                showStep(3);
            } else {
                showMessage(res.data.message, false);
            }
        }).fail(() => {
            showMessage('Błąd połączenia. Spróbuj ponownie.', false);
        }).always(() => form.find('button').prop('disabled', false));
    });

    // ETAP 3: Ustaw nowe hasło
    container.on('submit', '#set-password-form', function (e) {
        e.preventDefault();
        const pass1 = $('#pass1').val();
        const pass2 = $('#pass2').val();

        if (pass1 !== pass2) return showMessage('Hasła nie są identyczne.', false);

        // Walidacja wymagań hasła
        const lengthReq = pass1.length >= 8;
        const uppercaseReq = /[A-Z]/.test(pass1);
        const lowercaseReq = /[a-z]/.test(pass1);
        const digitReq = /\d/.test(pass1);

        if (!lengthReq || !uppercaseReq || !lowercaseReq || !digitReq) {
            return showMessage('Hasło nie spełnia wymagań.', false);
        }

        const form = $(this);
        form.find('button').prop('disabled', true);

        $.post(devents_reset_object.ajaxurl, {
            action: 'devents_password_reset',
            nonce: devents_reset_object.nonce,
            step: 'set_password',
            email: currentEmail,
            code: $('#set_pass_reset_code').val(),
            pass1
        }).done(res => {
            if (res.success) {
                $.each(steps, (i, el) => el.hide());
                showMessage(res.data.message, true);
                $('<div class="auth-secondary-action" style="margin-top:1rem;"><a href="/zaloguj-sie" class="btn btn--secondary btn--full-width">Przejdź do logowania</a></div>').insertAfter(messageBox);
            } else {
                showMessage(res.data.message, false);
            }
        }).fail(() => {
            showMessage('Błąd połączenia. Spróbuj ponownie.', false);
        }).always(() => form.find('button').prop('disabled', false));
    });

    // Walidacja i wskaźnik siły hasła na bieżąco
    $('#pass1').on('input', function () {
        const val = $(this).val();
        const lengthReq = val.length >= 8;
        const uppercaseReq = /[A-Z]/.test(val);
        const lowercaseReq = /[a-z]/.test(val);
        const digitReq = /\d/.test(val);

        $('#length').toggleClass('valid', lengthReq).text((lengthReq ? '✔' : '✖') + ' Min. 8 znaków');
        $('#uppercase').toggleClass('valid', uppercaseReq).text((uppercaseReq ? '✔' : '✖') + ' Wielka litera');
        $('#lowercase').toggleClass('valid', lowercaseReq).text((lowercaseReq ? '✔' : '✖') + ' Mała litera');
        $('#digit').toggleClass('valid', digitReq).text((digitReq ? '✔' : '✖') + ' Cyfra');

        let strength = 0;
        if (lengthReq) strength++;
        if (uppercaseReq) strength++;
        if (lowercaseReq) strength++;
        if (digitReq) strength++;

        $('#password-strength-meter').val(strength);
    });
});
