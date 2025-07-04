/**
 * videos-search.js
 * Logika JavaScript dla formularza wyszukiwania materiałów wideo.
 */
jQuery(document).ready(function ($) {
    const filterForm = $('#filter-form');
    if (filterForm.length === 0) {
        return; // Zakończ, jeśli formularz nie istnieje
    }

    // --- Inicjalizacja biblioteki Choices.js ---
    if (typeof Choices !== 'undefined') {
        const accessibilitySelect = document.getElementById('accessibility');
        if (accessibilitySelect) {
            new Choices(accessibilitySelect, {
                removeItemButton: true,
                placeholderValue: 'Wybierz opcje dostępności',
                searchPlaceholderValue: 'Dostępność'
            });
        }
    }

    // --- Logika przełączania widoczności zaawansowanej wyszukiwarki ---
    const toggleButton = $('#toggle-advanced-search');
    const advancedFields = $('#advanced-search-fields');

    // Sprawdź, czy filtry zaawansowane są aktywne przy ładowaniu strony
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('author') || urlParams.has('translator') || urlParams.get('accessibility')) {
        advancedFields.removeClass('hidden');
        toggleButton.text('Zwiń wyszukiwarkę');
    }

    toggleButton.on('click', function () {
        advancedFields.toggleClass('hidden');
        const isHidden = advancedFields.hasClass('hidden');
        $(this).text(isHidden ? 'Wyszukiwarka zaawansowana' : 'Zwiń wyszukiwarkę');
    });

});