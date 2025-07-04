/**
 * event-search.js
 * Logika JavaScript dla formularza wyszukiwania wydarzeń na frontendzie.
 * Inicjalizuje biblioteki i obsługuje interakcje użytkownika.
 * Zaktualizowano o Choices.js i Flatpickr.
 */
(function ($) {
    'use strict';

    // Sprawdź, czy jesteśmy na stronie z formularzem wyszukiwania wydarzeń
    if (!document.getElementById('filter-form')) {
        return;
    }

    // --- Inicjalizacja Choices.js  ---
    if (typeof Choices !== 'undefined') {
        const accessibilitySelect = document.getElementById('accessibility');
        if (accessibilitySelect) {
            new Choices(accessibilitySelect, {
                removeItemButton: true,
                placeholderValue: 'Wybierz opcje dostępności',
                searchPlaceholderValue: 'Szukaj...'
            });
        }
    }

    // --- Inicjalizacja Flatpickr dla pól daty ---
    function initFlatpickr() {
        if (typeof flatpickr !== 'undefined') {
            flatpickr.localize(flatpickr.l10ns.pl); // Ustawienie języka polskiego
            flatpickr(".flatpickr-date-input", {
                enableTime: false, // Tylko data
                dateFormat: "d-m-Y", // Format wyświetlania
                altInput: true,
                altFormat: "d-m-Y",
                allowInput: false, // Zapobiega ręcznemu wpisywaniu
                locale: "pl"
            });
        } else {
            console.warn('Flatpickr is not loaded.');
        }
    }

    $(document).ready(function () {
        // Inicjalizacja Choices.js dla odpowiednich selectów
        initChoicesJs('category');
        initChoicesJs('delivery_mode');
        initChoicesJs('accessibility');

        // Inicjalizacja Flatpickr
        initFlatpickr();

        // Logika przełączania widoczności zaawansowanej wyszukiwarki
        const toggleButton = $('#toggle-advanced-search');
        const advancedSearchFields = $('#advanced-search-fields');

        // Sprawdź, czy są jakieś filtry ustawione, aby pokazać zaawansowane pola
        const urlParams = new URLSearchParams(window.location.search);
        let hasAdvancedFilters = false;
        const advancedFilterNames = ['category', 'delivery_mode', 'accessibility', 'start_date', 'end_date', 'min_price', 'max_price', 'only_current'];

        for (const name of advancedFilterNames) {
            if (urlParams.has(name) && urlParams.get(name) !== '' && urlParams.get(name) !== '0') {
                hasAdvancedFilters = true;
                break;
            }
        }

        if (hasAdvancedFilters) {
            advancedSearchFields.removeClass('hidden');
            toggleButton.text('Zwiń wyszukiwarkę zaawansowaną');
        }

        toggleButton.on('click', function () {
            advancedSearchFields.toggleClass('hidden');
            if (advancedSearchFields.hasClass('hidden')) {
                $(this).text('Wyszukiwarka zaawansowana');
            } else {
                $(this).text('Zwiń wyszukiwarkę zaawansowaną');
            }
        });

        // Logika dla przycisku czyszczenia pola wyszukiwania
        const searchInput = $('#search_query');
        const clearSearchIcon = $('#clear-search-query');

        function toggleClearIcon() {
            if (searchInput.val().length > 0) {
                clearSearchIcon.removeClass('hidden');
            } else {
                clearSearchIcon.addClass('hidden');
            }
        }

        searchInput.on('input', toggleClearIcon);
        clearSearchIcon.on('click', function () {
            searchInput.val('');
            toggleClearIcon();
            searchInput.focus();
        });

        // Inicjalne sprawdzenie stanu ikony po załadowaniu strony
        toggleClearIcon();

        // Obsługa resetowania formularza (opcjonalnie, jeśli dodasz przycisk reset)
        // Jeśli dodasz przycisk reset, możesz dodać listener:
        // $('#reset-button').on('click', function() {
        //     $('#filter-form')[0].reset();
        //     // Tutaj trzeba by zresetować też Choices.js i Flatpickr
        //     // np. choicesInstance.setChoiceByValue('');
        //     // flatpickrInstance.clear();
        //     // I ukryć advancedSearchFields
        //     advancedSearchFields.addClass('hidden');
        //     toggleButton.text('Wyszukiwarka zaawansowana');
        //     toggleClearIcon();
        // });
    });
})(jQuery);