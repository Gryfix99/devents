/**
 * Obsługa AJAX i interakcji dla listy materiałów w panelu admina.
 * Wersja ostateczna.
 */
jQuery(document).ready(function ($) {

    const table = $('#materials-table');
    if (table.length === 0) {
        return; // Zakończ, jeśli tabela nie istnieje na stronie
    }

    // --- Zaznaczanie / Odznaczanie wszystkich checkboxów ---
    $('#select-all-top, #select-all-bottom').on('change', function () {
        const isChecked = $(this).prop('checked');
        table.find('input[type="checkbox"][name="material_ids[]"]').prop('checked', isChecked);
    });


    /**
     * Funkcja pomocnicza do wysyłania żądań AJAX dla akcji.
     * @param {string} actionName - Nazwa akcji WordPressa (np. 'delete_material').
     * @param {object} data - Dodatkowe dane do wysłania (np. { material_id: 123 }).
     * @param {string} confirmationMessage - Wiadomość do wyświetlenia w oknie confirm().
     */
    function handleAjaxAction(actionName, data, confirmationMessage) {
        if (confirmationMessage && !confirm(confirmationMessage)) {
            return;
        }

        // Dołączamy ogólny nonce dla bezpieczeństwa
        data.action = actionName;
        data.nonce = devents_admin_config.nonce; // Zakładając, że przekazujesz go pod tą nazwą

        $.ajax({
            url: ajaxurl, // ajaxurl jest globalną zmienną w panelu admina WP
            type: 'POST',
            data: data,
            beforeSend: function () {
                $('body').css('cursor', 'wait');
            },
            success: function (response) {
                if (response.success) {
                    alert(response.data.message || 'Akcja wykonana pomyślnie!');
                    window.location.reload(); // Odśwież stronę, aby zobaczyć zmiany
                } else {
                    alert('Błąd: ' + (response.data.message || 'Wystąpił nieznany błąd.'));
                }
            },
            error: function () {
                alert('Wystąpił błąd komunikacji z serwerem.');
            },
            complete: function () {
                $('body').css('cursor', 'default');
            }
        });
    }

    // --- Podpięcie zdarzeń dla przycisków akcji ---

    // Akcje pojedyncze
    table.on('click', '.button-verify-material', function (e) {
        e.preventDefault();
        const materialId = $(this).data('id');
        handleAjaxAction('verify_material', { material_id: materialId }, 'Czy na pewno chcesz zweryfikować ten materiał?');
    });

    table.on('click', '.button-publish-material', function (e) {
        e.preventDefault();
        const materialId = $(this).data('id');
        handleAjaxAction('publish_material', { material_id: materialId }, 'Czy na pewno chcesz opublikować ten materiał jako wpis?');
    });

    table.on('click', '.button-delete-material', function (e) {
        e.preventDefault();
        const materialId = $(this).data('id');
        handleAjaxAction('delete_material', { material_id: materialId }, 'Czy na pewno chcesz trwale usunąć ten materiał?');
    });

    // Możesz tu dodać kolejne handlery dla np. 'unverify', 'unpublish' etc.


    // Akcje zbiorcze
    $('#doaction_top, #doaction_bottom').on('click', function () {
        const selector = $(this).attr('id') === 'doaction_top' ? '#bulk-action-selector-top' : '#bulk-action-selector-bottom';
        const action = $(selector).val();
        if (!action) {
            alert('Wybierz akcję zbiorczą.');
            return;
        }

        const ids = $('input[name="material_ids[]"]:checked').map(function () {
            return $(this).val();
        }).get();

        if (ids.length === 0) {
            alert('Wybierz przynajmniej jeden materiał.');
            return;
        }

        handleAjaxAction(action + '_bulk', { ids: ids }, 'Czy na pewno chcesz wykonać wybraną akcję dla ' + ids.length + ' elementów?');
    });

});