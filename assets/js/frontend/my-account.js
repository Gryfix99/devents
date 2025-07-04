/**
 * Obsługuje interakcje na stronie "Moje Konto", w tym zakładki i wysyłkę formularzy AJAX.
 * Wersja ostateczna z poprawionymi nazwami zmiennych.
 */
jQuery(document).ready(function ($) {
    const container = $('.account-settings-container');
    if (container.length === 0) {
        return;
    }

    const messagesContainer = $('#settings-messages');

    // --- LOGIKA DLA ZAKŁADEK ---
    const tabs = container.find('.nav-tab');
    const tabContents = container.find('.tab-content');

    tabs.on('click', function (e) {
        e.preventDefault();
        const targetTab = $(this).data('tab');
        tabs.removeClass('active');
        $(this).addClass('active');
        tabContents.removeClass('active');
        $('#tab-' + targetTab).addClass('active');
        messagesContainer.hide().empty();
    });


    // --- LOGIKA DLA FORMULARZY AJAX ---

    // 1. FORMULARZ DANYCH KONTA
    $('#account-details-form').on('submit', function (e) {
        e.preventDefault();
        const $form = $(this);
        const $button = $form.find('button[type="submit"]'); // Zmienna nazywa się $button
        const originalButtonText = $button.text();

        $button.prop('disabled', true).text('Zapisywanie...');
        messagesContainer.hide().empty();

        const formData = new FormData(this);
        formData.append('action', 'devents_update_account');

        $.ajax({
            url: devents_config.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                const message = response.data.message || 'Wystąpił błąd.';
                const messageClass = response.success ? 'message-box--success' : 'message-box--error';
                messagesContainer.attr('class', 'message-box ' + messageClass).html(message).show();
                if (response.success && response.data.new_logo_url !== undefined) {
                    // Odśwież stronę, aby poprawnie zobaczyć nowe logo
                    window.location.reload();
                }
            },
            error: function () {
                messagesContainer.attr('class', 'message-box message-box--error').html('Błąd serwera. Spróbuj ponownie.').show();
            },
            complete: function () {
                // Używamy tej samej, poprawnej nazwy zmiennej: $button
                $button.prop('disabled', false).text(originalButtonText);
            }
        });
    });

    // 2. FORMULARZ ZMIANY HASŁA
    $('#change-password-form').on('submit', function (e) {
        e.preventDefault();
        const $form = $(this);
        const $button = $form.find('button[type="submit"]'); // Zmienna nazywa się $button
        const originalButtonText = $button.text();

        $button.prop('disabled', true).text('Zmienianie...');
        messagesContainer.hide().empty();

        $.post(devents_config.ajax_url, $form.serialize() + '&action=devents_change_password')
            .done(response => {
                const message = response.data.message || 'Błąd';
                const messageClass = response.success ? 'message-box--success' : 'message-box--error';
                messagesContainer.attr('class', 'message-box ' + messageClass).html(message).show();
                if (response.success) {
                    $form.trigger('reset');
                }
            })
            .fail(() => messagesContainer.attr('class', 'message-box message-box--error').html('Błąd serwera.').show())
            .always(() => $button.prop('disabled', false).text(originalButtonText)); // Używamy $button
    });

    // 3. FORMULARZ USUWANIA KONTA
    $('#delete-account-form').on('submit', function (e) {
        e.preventDefault();
        if (!confirm('Czy na pewno chcesz trwale usunąć swoje konto? Tej operacji nie można cofnąć!')) {
            return;
        }

        const $form = $(this);
        const $button = $form.find('button[type="submit"]'); // Zmienna nazywa się $button
        const originalButtonText = $button.text();

        $button.prop('disabled', true).text('Usuwanie...');
        messagesContainer.hide().empty();

        $.post(devents_config.ajax_url, $form.serialize() + '&action=devents_delete_account')
            .done(response => {
                if (response.success) {
                    messagesContainer.attr('class', 'message-box message-box--success').html(response.data.message).show();
                    $button.text('Konto usunięte...');
                    setTimeout(() => window.location.href = devents_config.home_url, 3000);
                } else {
                    messagesContainer.attr('class', 'message-box message-box--error').html(response.data.message).show();
                    $button.prop('disabled', false).text(originalButtonText);
                }
            })
            .fail(() => {
                messagesContainer.attr('class', 'message-box message-box--error').html('Błąd serwera.').show();
                $button.prop('disabled', false).text(originalButtonText);
            });
    });

});