jQuery(document).ready(function ($) {
    // Sprawdź, czy AJAX URL jest dostępny
    if (typeof devents_users_object === 'undefined' || !devents_users_object.ajaxurl) {
        console.error('Brak devents_users_object lub ajaxurl.');
        return;
    }

    // Funkcja pomocnicza do AJAX
    function sendAjaxRequest(data, successCallback) {
        $.ajax({
            url: devents_users_object.ajaxurl,
            method: 'POST',
            data: data,
            success: function (response) {
                if (response && response.success) {
                    successCallback(response);
                } else {
                    var msg = response && response.data && response.data.message ? response.data.message : 'Nieoczekiwany błąd serwera.';
                    alert(msg);
                }
            },
            error: function () {
                alert('Wystąpił błąd przy przetwarzaniu żądania.');
            }
        });
    }

    // Select All checkbox
    $('#select-all-users, #select-all-users-footer').on('change', function () {
        var checked = $(this).prop('checked');
        $('.user-checkbox').prop('checked', checked);
        $('#select-all-users, #select-all-users-footer').prop('checked', checked);
    });
    $('#users-table').on('change', '.user-checkbox', function () {
        var total = $('.user-checkbox').length;
        var checked = $('.user-checkbox:checked').length;
        var allChecked = (total === checked);
        $('#select-all-users, #select-all-users-footer').prop('checked', allChecked);
    });

    // Obsługa pojedynczych akcji
    function handleSingleAction(selector, actionName, nonceDataAttr, confirmMessage) {
        $(document).on('click', selector, function (e) {
            e.preventDefault();
            var btn = $(this);
            var userID = btn.data('user-id');
            var nonceAttr = btn.attr('data-' + nonceDataAttr);
            if (!userID || !nonceAttr) {
                alert('Brak danych do wykonania akcji.');
                return;
            }
            if (confirmMessage) {
                if (!confirm(confirmMessage.replace('{userId}', userID))) {
                    return;
                }
            }
            var data = {
                action: actionName,
                user_id: userID
            };
            if (actionName === 'delete_user') {
                data.delete_user_nonce = nonceAttr;
            } else if (actionName === 'verify_user') {
                data.verify_user_nonce = nonceAttr;
            } else if (actionName === 'unverify_user') {
                data.unverify_user_nonce = nonceAttr;
            }
            sendAjaxRequest(data, function (response) {
                if (actionName === 'delete_user') {
                    $('#user-' + userID).fadeOut();
                } else {
                    location.reload();
                }
            });
        });
    }
    handleSingleAction('.button-delete-user', 'delete_user', 'delete-user-nonce', 'Czy na pewno chcesz usunąć użytkownika ID: {userId}?');
    handleSingleAction('.button-verify-user', 'verify_user', 'verify-user-nonce', 'Czy na pewno chcesz zweryfikować użytkownika ID: {userId}?');
    handleSingleAction('.button-unverify-user', 'unverify_user', 'unverify-user-nonce', 'Czy na pewno chcesz cofnąć weryfikację użytkownika ID: {userId}?');

    // Obsługa akcji zbiorczej
    $('#apply-bulk-user-action').on('click', function () {
        var action = $('#bulk-user-action-select').val();
        var selectedItems = $('.user-checkbox:checked').map(function () {
            return $(this).val();
        }).get();

        if (!action) {
            alert('Wybierz akcję.');
            return;
        }
        if (selectedItems.length === 0) {
            alert('Wybierz przynajmniej jednego użytkownika.');
            return;
        }
        var confirmMsg = 'Na pewno wykonać akcję "' + $('#bulk-user-action-select option:selected').text() + '" dla ' + selectedItems.length + ' użytkowników?';
        if (action === 'delete_bulk') {
            confirmMsg = 'Czy na pewno chcesz USUNĄĆ ' + selectedItems.length + ' użytkowników? Tej operacji nie można cofnąć.';
        }
        if (!confirm(confirmMsg)) {
            return;
        }
        var data = {
            action: 'bulk_user_action',
            bulk_action: action,
            user_ids: selectedItems,
            bulk_user_nonce: devents_users_object.bulk_user_nonce
        };
        sendAjaxRequest(data, function (response) {
            location.reload();
        });
    });

    // Sortowanie mobilne (opcjonalnie, jeśli masz filtr)
    $('#mobile_orderby_select').on('change', function() {
        var selectedValue = $(this).val();
        if (selectedValue) {
            var parts = selectedValue.split('|');
            var column = parts[0], orderDirection = parts[1];
            $('form.devents-filters input[name="orderby"]').val(column);
            $('form.devents-filters input[name="order"]').val(orderDirection);
            var currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('orderby', column);
            currentUrl.searchParams.set('order', orderDirection);
            currentUrl.searchParams.set('paged', '1');
            window.location.href = currentUrl.toString();
        }
    });
    function setInitialMobileSort() {
        var urlParams = new URLSearchParams(window.location.search);
        var currentOrderby = urlParams.get('orderby') || 'id';
        var currentOrder = (urlParams.get('order') || 'desc').toUpperCase();
        $('#mobile_orderby_select').val(currentOrderby + '|' + currentOrder);
        $('form.devents-filters input[name="orderby"]').val(currentOrderby);
        $('form.devents-filters input[name="order"]').val(currentOrder);
    }
    setInitialMobileSort();
});
