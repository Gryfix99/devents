/**
 * Obsługuje interakcje na liście "Moje Filmy" (duplikowanie, usuwanie).
 */
jQuery(document).ready(function ($) {
    const listContainer = $('.my-content-list');
    if (listContainer.length === 0) {
        return;
    }

    // --- OBSŁUGA DUPLIKOWANIA FILMU ---
    listContainer.on('click', '.js-duplicate-video-btn', function (e) {
        e.preventDefault();
        const $button = $(this);
        const videoId = $button.data('video-id');

        if (!confirm('Czy na pewno chcesz zduplikować ten film?')) return;

        $button.prop('disabled', true).css('opacity', 0.5);
        $.post(devents_config.ajax_url, {
            action: 'devents_duplicate_video',
            video_id: videoId,
            _wpnonce: devents_config.duplicate_video_nonce
        }).done(response => {
            if (response.success) {
                alert(response.data.message);
                window.location.reload();
            } else {
                alert('Błąd: ' + (response.data.message || 'Nieznany błąd.'));
                $button.prop('disabled', false).css('opacity', 1);
            }
        }).fail(() => {
            alert('Błąd serwera.');
            $button.prop('disabled', false).css('opacity', 1);
        });
    });

    // --- OBSŁUGA USUWANIA FILMU ---
    listContainer.on('click', '.js-delete-video-btn', function (e) {
        e.preventDefault();
        const $button = $(this);
        const videoId = $button.data('video-id');

        if (!confirm('Czy na pewno chcesz trwale usunąć ten film?')) return;

        $button.prop('disabled', true).css('opacity', 0.5);
        $.post(devents_config.ajax_url, {
            action: 'devents_delete_video',
            video_id: videoId,
            _wpnonce: devents_config.delete_video_nonce
        }).done(response => {
            if (response.success) {
                $button.closest('tr').fadeOut(400, function () { $(this).remove(); });
            } else {
                alert('Błąd: ' + (response.data.message || 'Nieznany błąd.'));
                $button.prop('disabled', false).css('opacity', 1);
            }
        }).fail(() => {
            alert('Błąd serwera.');
            $button.prop('disabled', false).css('opacity', 1);
        });
    });
});