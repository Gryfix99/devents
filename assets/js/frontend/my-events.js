/**
 * Obsługuje interakcje na liście "Moje Wydarzenia".
 * Wersja ostateczna, z poprawnym i bezpiecznym wrapperem jQuery.
 */
jQuery(document).ready(function ($) {

    // Upewniamy się, że kontener listy istnieje na stronie, zanim cokolwiek zrobimy
    const listContainer = $('.my-content-list');
    if (listContainer.length === 0) {
        // Jeśli nie ma listy na stronie, nic więcej nie robimy.
        return;
    }

    // --- OBSŁUGA PRZYCISKU "DUPLIKUJ" ---
    listContainer.on('click', '.js-duplicate-event-btn', function (e) {
        e.preventDefault();

        const $button = $(this);
        const eventId = $button.data('event-id');

        if (!confirm('Czy na pewno chcesz zduplikować to wydarzenie? Zostanie ono zapisane jako wersja robocza.')) {
            return;
        }

        $button.prop('disabled', true).css('opacity', 0.5);

        $.post(devents_config.ajax_url, {
            action: 'devents_duplicate_event',
            event_id: eventId,
            _wpnonce: devents_config.duplicate_event_nonce
        }).done(function (response) {
            if (response.success) {
                alert(response.data.message);
                window.location.reload();
            } else {
                alert('Błąd: ' + (response.data.message || 'Nieznany błąd.'));
                $button.prop('disabled', false).css('opacity', 1);
            }
        }).fail(function () {
            alert('Wystąpił błąd serwera. Spróbuj ponownie.');
            $button.prop('disabled', false).css('opacity', 1);
        });
    });

    // --- OBSŁUGA PRZYCISKU "USUŃ" ---
    listContainer.on('click', '.js-delete-event-btn', function (e) {
        e.preventDefault();

        const $button = $(this);
        const eventId = $button.data('event-id');

        if (!confirm('Czy na pewno chcesz trwale usunąć to wydarzenie? Tej operacji nie można cofnąć.')) {
            return;
        }

        $button.prop('disabled', true).css('opacity', 0.5);

        $.post(devents_config.ajax_url, {
            action: 'devents_delete_event',
            event_id: eventId,
            _wpnonce: devents_config.delete_event_nonce
        }).done(function (response) {
            if (response.success) {
                $button.closest('tr').fadeOut(400, function () {
                    $(this).remove();
                });
            } else {
                alert('Błąd: ' + (response.data.message || 'Nieznany błąd.'));
                $button.prop('disabled', false).css('opacity', 1);
            }
        }).fail(function () {
            alert('Wystąpił błąd serwera. Spróbuj ponownie.');
            $button.prop('disabled', false).css('opacity', 1);
        });
    });

});