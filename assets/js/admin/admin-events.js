/**
 * Skrypty dla strony listy wydarzeń w panelu administratora.
 */
jQuery(function ($) {
    if (typeof devents_events_object === 'undefined') {
        console.error('Błąd: Obiekt devents_events_object jest niedostępny.');
        return;
    }

    const ajaxurl = devents_events_object.ajaxurl;
    const pluginUrl = devents_events_object.plugin_url;
    const table = $('#events-table');
    const modal = $('#graphic-modal');
    const modalBody = $('#graphic-modal-body'); // dodane
    const iframe = $('#graphic-frame');

    // Ukryj modal na starcie
    modal.hide();

    function showAdminMessage(msg, type = 'info') {
        const messageDiv = $(`<div class="notice notice-${type} is-dismissible"><p>${msg}</p></div>`);
        $('.wrap h1').first().after(messageDiv);
        setTimeout(() => messageDiv.slideUp(200, () => messageDiv.remove()), 5000);
    }

    function handleEventAction(selector, ajax_action_type, confirm_msg = '') {
        table.on('click', selector, function (e) {
            e.preventDefault();
            const btn = $(this);
            const eventId = btn.data('event-id');
            const nonce = btn.data('nonce');

            if (confirm_msg && !confirm(confirm_msg)) return;
            if (!eventId || !nonce) {
                showAdminMessage('Błąd: Brak wymaganych danych (ID lub nonce).', 'error');
                return;
            }

            btn.prop('disabled', true);
            $.post(ajaxurl, {
                action: 'event_single_action',
                action_type: ajax_action_type,
                event_id: eventId,
                nonce: nonce
            })
                .done(response => {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        showAdminMessage(response.data.message || 'Błąd.', 'error');
                        btn.prop('disabled', false);
                    }
                })
                .fail(() => {
                    showAdminMessage('Błąd serwera.', 'error');
                    btn.prop('disabled', false);
                });
        });
    }

    handleEventAction('.delete-event-btn', 'delete', devents_events_object.confirm_delete_msg);
    handleEventAction('.verify-event-btn', 'verify');
    handleEventAction('.unverify-event-btn', 'unverify');
    handleEventAction('.publish-event-btn', 'publish');

    // Akcje zbiorcze
    $('#doaction_top, #doaction_bottom').on('click', function (e) {
        e.preventDefault();
        const actionType = $(this).siblings('select').val();
        if (!actionType) { showAdminMessage('Proszę wybrać akcję zbiorczą.', 'info'); return; }
        const selectedEventIds = $('tbody .check-column input:checked').map((i, el) => $(el).val()).get();
        if (selectedEventIds.length === 0) { showAdminMessage('Proszę wybrać przynajmniej jedno wydarzenie.', 'info'); return; }
        if (devents_events_object.confirm_bulk_msg && !confirm(devents_events_object.confirm_bulk_msg)) return;
        $.post(ajaxurl, {
            action: 'bulk_event_action',
            bulk_action: actionType,
            event_ids: selectedEventIds,
            nonce: devents_events_object.bulk_nonce
        }).done(response => {
            showAdminMessage(response.data.message || 'Akcja zbiorcza zakończona.', response.success ? 'success' : 'error');
            if (response.success) setTimeout(() => window.location.reload(), 2000);
        });
    });

    // Checkbox "Zaznacz wszystkie"
    $('#cb-select-all-1, #cb-select-all-2').on('change', function () {
        $('tbody .check-column input[type="checkbox"]').prop('checked', $(this).is(':checked'));
    });

    // Handler dla modala "Grafika"
    table.on('click', '.view-graphic-btn', function (e) {
        e.preventDefault();

        const eventId = $(this).data('event-id');
        // Ta linia jest poprawna, jeśli wykonałeś Krok 2
        const nonce = devents_events_object.graphic_nonce;

        if (!eventId || !nonce) {
            showAdminMessage('Brak wymaganych danych do załadowania grafiki.', 'error');
            return;
        }

        modalBody.html('<p>Ładowanie…</p>');
        modal.fadeIn(200);

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'devents_get_graphic', // Ta akcja musi być zarejestrowana w PHP
                event_id: eventId,
                nonce: nonce
            },
            success: function (response) {

                if (response.success && response.data.html) {
                    modalBody.html(response.data.html);
                    initializeGraphicGenerator(eventId);
                } else {
                    const errorMsg = response.data.message || 'Nie udało się załadować grafiki.';
                    modalBody.html('<p>Błąd: ' + errorMsg + '</p>');
                }
            },
            error: function () {
                modalBody.html('<p>Wystąpił błąd sieciowy.</p>');
            }
        });
    });

    // Zamknięcie modala i czyszczenie zawartości
    modal.on('click', function (e) {
        if ($(e.target).is(modal) || $(e.target).hasClass('close-modal')) {
            modal.fadeOut(200, () => {
                modalBody.html('');
                // iframe.attr('src', ''); // jeśli korzystasz z iframe, odkomentuj
            });
        }
    });

    // Zapobiegaj propagacji kliknięć wewnątrz modal-content (żeby kliknięcie nie zamykało modala)
    modal.on('click', '.modal-content', e => e.stopPropagation());
});
