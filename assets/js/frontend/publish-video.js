/**
 * Kompletna obsługa formularza publikacji i edycji filmów.
 * Zawiera logikę UI oraz wysyłkę AJAX.
 */
jQuery(document).ready(function ($) {
    const form = $('#publish-video-form');
    if (form.length === 0) {
        return; // Zakończ, jeśli formularz nie istnieje na tej stronie
    }

    const messagesContainer = $('#form-messages');

    // Inicjalizacja bibliotek (Flatpickr, Choices, EasyMDE)
    if (typeof flatpickr !== 'undefined') {
        flatpickr("#publish_date", { enableTime: true, dateFormat: "Y-m-d H:i", locale: "pl" });
    }
    let easyMDE;
    if (typeof EasyMDE !== 'undefined' && $('#description').length) {
        easyMDE = new EasyMDE({
            element: document.getElementById("description"),
            spellChecker: false, status: false,
            placeholder: "Wpisz opis filmu...",
            toolbar: ["bold", "italic", "|", "unordered-list", "ordered-list", "|", "link", "guide"],
        });
    }

    // --- Logika wysyłania formularza ---
    form.on('submit', function (e) {
        e.preventDefault();

        if (easyMDE) {
            $('#description').val(easyMDE.value());
        }

        const submitButton = form.find('button[type="submit"]');
        const originalButtonText = submitButton.text();
        submitButton.prop('disabled', true).text('Wysyłanie...');
        messagesContainer.empty().removeClass('message-box--error message-box--success').hide();

        const formData = new FormData(this);
        formData.append('action', 'devents_save_video');
        formData.append('_wpnonce', devents_config.save_video_nonce);

        $.ajax({
            url: devents_config.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                let message = response.data.message || 'Wystąpił nieznany błąd.';
                let messageClass = response.success ? 'message-box--success' : 'message-box--error';

                messagesContainer.addClass('message-box ' + messageClass).html(message).show();

                if (response.success && form.find('input[name="video_id"]').val() == '0') {
                    form[0].reset();
                    if (easyMDE) easyMDE.value('');
                }
                $('html, body').animate({ scrollTop: messagesContainer.offset().top - 100 }, 'slow');
            },
            error: function () {
                messagesContainer.addClass('message-box message-box--error').html('Wystąpił krytyczny błąd serwera.').show();
            },
            complete: function () {
                submitButton.prop('disabled', false).text(originalButtonText);
            }
        });
    });
});