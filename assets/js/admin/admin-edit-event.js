/**
 * admin-edit-event.js
 * Logika JavaScript dla formularza edycji wydarzenia w panelu administracyjnym.
 * Inicjalizuje biblioteki i obsługuje interakcje użytkownika.
 * Zaktualizowano o Choices.js.
 * DODANO LOGIKĘ AUTOUZUPEŁNIANIA ADRESÓW (NOMINATIM).
 */
(function ($) { // Użycie IIFE z przekazanym jQuery
    'use strict';

    // Sprawdź, czy jesteśmy na odpowiedniej stronie, szukając głównego kontenera formularza.
    if (!document.querySelector('.admin-edit-container')) {
        return;
    }

    // --- Elementy DOM dla Nominatim ---
    const locationInput = document.getElementById('location');
    const autocompleteResults = document.getElementById('autocomplete-results'); // Upewnij się, że ten element jest w TWIG
    const loadingSpinner = document.getElementById('location-loading-spinner'); // Upewnij się, że ten element jest w TWIG

    let activeItem = -1; // Index aktywnego elementu dla nawigacji klawiaturą (Nominatim)

    // Funkcja do inicjalizacji Flatpickr
    function initFlatpickr() {
        if (typeof flatpickr !== 'undefined') {
            flatpickr.localize(flatpickr.l10ns.pl);
            flatpickr(".flatpickr-datetime", {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                time_24hr: true,
                locale: "pl",
                altInput: true, // Włącz alternatywny input dla lepszej czytelności
                altFormat: "d-m-Y H:i", // Format wyświetlania
                allowInput: false // Zapobiega ręcznemu wpisywaniu w alternatywny input
            });
        } else {
            console.warn('Flatpickr is not loaded.');
        }
    }

    // Funkcja do inicjalizacji Choices.js
    function initChoicesJs(elementId) {
        const selectElement = document.getElementById(elementId);
        if (typeof Choices !== 'undefined' && selectElement) {
            new Choices(selectElement, {
                removeItemButton: true,
                placeholder: true,
                placeholderValue: 'Wybierz', // Domyślny placeholder
                searchPlaceholderValue: 'Szukaj...', // Domyślny placeholder dla wyszukiwania
                shouldSort: false
            });
        } else if (!selectElement) {
            console.warn(`Element #${elementId} not found for Choices.js initialization.`);
        } else {
            console.warn('Choices.js is not loaded.');
        }
    }

    // Funkcja do inicjalizacji EasyMDE
    function initEasyMDE() {
        const descriptionTextarea = document.getElementById("description");
        if (typeof EasyMDE !== 'undefined' && descriptionTextarea) {
            const easymde = new EasyMDE({
                element: descriptionTextarea,
                autoDownloadFontAwesome: false,
                spellChecker: false,
                status: false, // Wyłącz domyślny pasek statusu EasyMDE
                placeholder: "Wprowadź szczegółowe informacje o wydarzeniu w formacie Markdown...",
                toolbar: ["bold", "italic", "heading", "|", "unordered-list", "ordered-list", "|", "preview", "guide"],
                minHeight: "200px",
            });

            const descriptionCountElement = $('#description-count');
            if (descriptionCountElement.length) {
                function updateEasyMDECharCount() {
                    const currentLength = easymde.value().length;
                    const maxLength = 6000;
                    descriptionCountElement.text(`${currentLength}/${maxLength}`);
                    if (currentLength > maxLength) {
                        descriptionCountElement.addClass('over');
                    } else {
                        descriptionCountElement.removeClass('over');
                    }
                }
                easymde.codemirror.on("change", updateEasyMDECharCount);
                updateEasyMDECharCount();
            }
            return easymde;
        } else {
            console.warn('EasyMDE is not loaded or description textarea not found.');
            return null;
        }
    }

    // Funkcja do inicjalizacji liczników znaków dla zwykłych inputów
    function setupCharCounter(inputId, countId, maxLength) {
        const inputElement = $(inputId);
        const countElement = $(countId);
        if (inputElement.length && countElement.length) {
            function update() {
                const currentLength = inputElement.val().length;
                countElement.text(`${currentLength}/${maxLength}`);
                if (currentLength > maxLength) {
                    countElement.addClass('over');
                } else {
                    countElement.removeClass('over');
                }
            }
            inputElement.on('input', update);
            update();
        }
    }

    // --- Funkcja do formatowania adresu z odpowiedzi Nominatim ---
    function formatAddress(addressComponents) {
        let parts = [];
        if (addressComponents.building) {
            parts.push(addressComponents.building);
        } else if (addressComponents.amenity) {
            parts.push(addressComponents.amenity);
        } else if (addressComponents.leisure) {
            parts.push(addressComponents.leisure);
        } else if (addressComponents.office) {
            parts.push(addressComponents.office);
        } else if (addressComponents.shop) {
            parts.push(addressComponents.shop);
        } else if (addressComponents.tourism) {
            parts.push(addressComponents.tourism);
        }

        let streetPart = [];
        if (addressComponents.road) {
            streetPart.push(addressComponents.road);
        }
        if (addressComponents.house_number) {
            streetPart.push(addressComponents.house_number);
        }
        if (streetPart.length > 0) {
            parts.push(streetPart.join(' '));
        }

        if (addressComponents.city) {
            parts.push(addressComponents.city);
        } else if (addressComponents.town) {
            parts.push(addressComponents.town);
        } else if (addressComponents.village) {
            parts.push(addressComponents.village);
        } else if (addressComponents.hamlet) {
            parts.push(addressComponents.hamlet);
        }

        const finalParts = [...new Set(parts.filter(p => p && p.trim() !== ''))];
        return finalParts.join(', ');
    }

    // --- Funkcja debounce do ograniczania liczby wywołań API ---
    function debounce(func, delay) {
        let timeout;
        return function (...args) {
            const context = this;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), delay);
        };
    }

    // Główna logika uruchamiana po załadowaniu DOM
    $(document).ready(function () {
        initFlatpickr();
        initChoicesJs('accessibility'); // Inicjalizuj Choices.js dla accessibility
        initChoicesJs('category'); // Inicjalizuj Choices.js dla category (jeśli potrzebne)
        initChoicesJs('method'); // Inicjalizuj Choices.js dla method (jeśli potrzebne)
        initEasyMDE();

        setupCharCounter('#title', '#title-count', 60);
        setupCharCounter('#image_alt_text', '#image_alt_text-count', 100);

        // Logika pokazywania/ukrywania pola 'Organizator'
        $('#is_other_organizer').on('change', function () {
            const otherOrganizerWrapper = $('#other_organizer_wrapper');
            const organizatorInput = $('#organizator');
            if ($(this).is(':checked')) {
                otherOrganizerWrapper.removeClass('hidden');
                organizatorInput.prop('required', true);
            } else {
                otherOrganizerWrapper.addClass('hidden');
                organizatorInput.prop('required', false).val('');
            }
        }).trigger('change');

        // Logika pokazywania/ukrywania pola 'Zakończenie powtarzania'
        $('#repeat').on('change', function () {
            const repeatEndWrapper = $('#repeat_end_wrapper');
            const repeatEndDatetimeInput = $('#repeat_end_datetime');
            if ($(this).val() !== '') {
                repeatEndWrapper.removeClass('hidden');
            } else {
                repeatEndWrapper.addClass('hidden');
                repeatEndDatetimeInput.val('');
            }
        }).trigger('change');

        // Logika pokazywania/ukrywania pól przycisku akcji
        const actionButtonEnabledCheckbox = $('#action_button_enabled');
        const actionButtonFields = $('#action_button_fields');

        if (actionButtonEnabledCheckbox.length && actionButtonFields.length) {
            function toggleActionButtonFields() {
                if (actionButtonEnabledCheckbox.is(':checked')) {
                    actionButtonFields.removeClass('hidden');
                } else {
                    actionButtonFields.addClass('hidden');
                }
            }
            actionButtonEnabledCheckbox.on('change', toggleActionButtonFields);
            toggleActionButtonFields();
        }

        // --- Logika Nominatim (autouzupełnianie adresów) dla lokalizacji w adminie ---
        if (locationInput && autocompleteResults && loadingSpinner) {
            locationInput.addEventListener('input', debounce(async () => {
                const input = locationInput.value.trim();
                autocompleteResults.innerHTML = '';
                loadingSpinner.style.display = 'none';

                if (input.length < 3) {
                    return;
                }

                loadingSpinner.style.display = 'block';

                try {
                    const response = await fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(input)}&format=jsonv2&addressdetails=1&limit=10&accept-language=pl`);
                    const data = await response.json();

                    loadingSpinner.style.display = 'none';

                    if (data && data.length > 0) {
                        data.forEach(result => {
                            const item = document.createElement('div');
                            item.classList.add('autocomplete-item');
                            item.textContent = result.display_name;

                            item.addEventListener('click', () => {
                                locationInput.value = formatAddress(result.address);
                                autocompleteResults.innerHTML = '';
                            });
                            autocompleteResults.appendChild(item);
                        });
                        activeItem = -1;
                    } else {
                        // console.log('Brak wyników dla: ' + input);
                    }
                } catch (error) {
                    console.error('Błąd podczas pobierania danych z Nominatim:', error);
                    loadingSpinner.style.display = 'none';
                }
            }, 300));

            // Obsługa nawigacji klawiaturą (strzałki góra/dół, Enter)
            locationInput.addEventListener('keydown', (e) => {
                const items = autocompleteResults.querySelectorAll('.autocomplete-item');
                if (items.length === 0) return;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    activeItem = (activeItem + 1) % items.length;
                    updateActiveItem(items);
                    items[activeItem].scrollIntoView({ block: 'nearest' });
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    activeItem = (activeItem - 1 + items.length) % items.length;
                    updateActiveItem(items);
                    items[activeItem].scrollIntoView({ block: 'nearest' });
                } else if (e.key === 'Enter') {
                    if (activeItem > -1) {
                        items[activeItem].click();
                    }
                    autocompleteResults.innerHTML = '';
                } else if (e.key === 'Escape') {
                    autocompleteResults.innerHTML = '';
                }
            });

            function updateActiveItem(items) {
                items.forEach((item, index) => {
                    if (index === activeItem) {
                        item.classList.add('active');
                    } else {
                        item.classList.remove('active');
                    }
                });
            }

            // Zamknięcie listy wyników po kliknięciu poza nią
            document.addEventListener('click', (e) => {
                if (!autocompleteResults.contains(e.target) && e.target !== locationInput) {
                    autocompleteResults.innerHTML = '';
                }
            });
        }
    });
})(jQuery);