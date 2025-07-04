    jQuery(document).ready(function ($) {
        const publishEventForm = $('.devents-form'); // Użycie jQuery do znalezienia formularza
        if (publishEventForm.length === 0) return; // Sprawdzenie czy formularz istnieje

        // 1. Flatpickr
        if (typeof flatpickr !== 'undefined') {
            flatpickr.localize(flatpickr.l10ns.pl);
            flatpickr('.flatpickr-datetime', {
                enableTime: true,
                dateFormat: 'Y-m-d H:i',
                time_24hr: true,
                locale: 'pl',
                altInput: true,
                altFormat: 'd-m-Y H:i',
                allowInput: false
            });
        }

        // 2. Choices.js dla dostępności
        if (typeof Choices !== 'undefined') {
            const accessibilitySelect = document.getElementById('accessibility');
            if (accessibilitySelect) {
                new Choices(accessibilitySelect, {
                    removeItemButton: true,
                    placeholderValue: 'Wybierz jedną lub więcej opcji',
                    searchPlaceholderValue: 'Szukaj dostępności...',
                    shouldSort: false
                });
            }
        }

        // 3. EasyMDE dla opisu
        if (typeof EasyMDE !== 'undefined') {
            const textarea = document.getElementById("description");
            const charCountDisplay = document.getElementById("description-count");

            if (textarea && charCountDisplay) {
                const easyMDE = new EasyMDE({
                    element: textarea,
                    autoDownloadFontAwesome: false,
                    spellChecker: false,
                    status: false,
                    placeholder: "Wpisz opis wydarzenia w formacie Markdown...",
                    toolbar: ["bold", "italic", "heading", "|", "unordered-list", "ordered-list", "|", "preview", "guide"],
                    minHeight: "200px",
                });

                function updateDescriptionCharCount() {
                    const content = easyMDE.value();
                    textarea.value = content;
                    charCountDisplay.textContent = `${content.length} / 6000 znaków`;
                    charCountDisplay.style.color = (content.length < 300 || content.length > 6000) ? 'red' : '';
                }

                easyMDE.codemirror.on("change", updateDescriptionCharCount);
                updateDescriptionCharCount();
            }
        }

        // 4. Liczniki znaków
        function setupCharCounter(inputId, counterId, max) {
            const el = document.getElementById(inputId);
            const cnt = document.getElementById(counterId);
            if (!el || !cnt) return;

            const update = () => {
                const len = el.value.length;
                cnt.textContent = `${len} / ${max} znaków`;
                cnt.classList.toggle('over', len > max);
            };

            el.addEventListener('input', update);
            update();
        }
        setupCharCounter('title', 'title-count', 60);
        setupCharCounter('image_alt_text', 'image_alt_text-count', 100);

        // 5. Powtarzanie wydarzenia
        const recurrenceSelect = document.getElementById('recurrence');
        const recurrenceEndContainer = document.getElementById('recurrence_end_date_container');
        if (recurrenceSelect && recurrenceEndContainer) {
            recurrenceSelect.addEventListener('change', () => {
                recurrenceEndContainer.classList.toggle('hidden', recurrenceSelect.value === 'none');
            });
            recurrenceEndContainer.classList.toggle('hidden', recurrenceSelect.value === 'none');
        }

        // 6. Niestandardowy przycisk akcji
        const actionButtonEnabledCheckbox = document.getElementById('action_button_enabled');
        const actionButtonTextGroup = document.getElementById('action_button_text_group');
        const actionButtonLinkGroup = document.getElementById('action_button_link_group');

        if (actionButtonEnabledCheckbox && actionButtonTextGroup && actionButtonLinkGroup) {
            function toggleActionButtonFields() {
                const isChecked = actionButtonEnabledCheckbox.checked;
                actionButtonTextGroup.classList.toggle('hidden', !isChecked);
                actionButtonLinkGroup.classList.toggle('hidden', !isChecked);
            }
            actionButtonEnabledCheckbox.addEventListener('change', toggleActionButtonFields);
            toggleActionButtonFields();
        }

        // 7. Inny organizator
        const isOtherOrganizerCheckbox = document.getElementById('is_other_organizer');
        const otherOrganizerWrapper = document.getElementById('other_organizer_wrapper');
        const other_organizerInput = document.getElementById('other_organizer');

        if (isOtherOrganizerCheckbox && otherOrganizerWrapper && other_organizerInput) {
            function toggleOtherOrganizerField() {
                const checked = isOtherOrganizerCheckbox.checked;
                otherOrganizerWrapper.classList.toggle('hidden', !checked);
                other_organizerInput.required = checked;
                if (!checked) other_organizerInput.value = '';
            }
            isOtherOrganizerCheckbox.addEventListener('change', toggleOtherOrganizerField);
            toggleOtherOrganizerField();
        }

        // 8. Lokalizacja – autouzupełnianie (Nominatim)
        const locationInput = document.getElementById('location');
        const autocompleteResults = document.getElementById('autocomplete-results');
        const loadingSpinner = document.getElementById('location-loading-spinner');
        let activeItem = -1;

        if (locationInput && autocompleteResults && loadingSpinner) {
            const formatAddress = (address) => {
                const parts = [
                    address.building || address.amenity || address.leisure || address.office || address.shop || address.tourism,
                    address.road && address.house_number ? `${address.road} ${address.house_number}` : address.road,
                    address.city || address.town || address.village || address.hamlet
                ];
                return parts.filter(Boolean).join(', ');
            };

            const debounce = (func, delay) => {
                let timeout;
                return function (...args) {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(this, args), delay);
                };
            };

            locationInput.addEventListener('input', debounce(async () => {
                const input = locationInput.value.trim();
                autocompleteResults.innerHTML = '';
                if (input.length < 3) return;

                loadingSpinner.style.display = 'block';
                try {
                    const res = await fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(input)}&format=jsonv2&addressdetails=1&limit=10&accept-language=pl`);
                    const data = await res.json();
                    loadingSpinner.style.display = 'none';

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
                } catch (err) {
                    console.error('Nominatim error:', err);
                    loadingSpinner.style.display = 'none';
                }
            }, 300));

            locationInput.addEventListener('keydown', (e) => {
                const items = autocompleteResults.querySelectorAll('.autocomplete-item');
                if (!items.length) return;

                if (e.key === 'ArrowDown') {
                    activeItem = (activeItem + 1) % items.length;
                } else if (e.key === 'ArrowUp') {
                    activeItem = (activeItem - 1 + items.length) % items.length;
                } else if (e.key === 'Enter') {
                    if (activeItem > -1) items[activeItem].click();
                    autocompleteResults.innerHTML = '';
                } else if (e.key === 'Escape') {
                    autocompleteResults.innerHTML = '';
                }

                items.forEach((item, idx) => {
                    item.classList.toggle('active', idx === activeItem);
                });
            });

            document.addEventListener('click', (e) => {
                if (!autocompleteResults.contains(e.target) && e.target !== locationInput) {
                    autocompleteResults.innerHTML = '';
                }
            });
        }

        // === KLUCZOWA ZMIANA: OBSŁUGA WYSYŁANIA FORMULARZA PRZEZ JQUERY AJAX ===
        $(document).on('submit', '#publish-event-form', function (e) {
            e.preventDefault();

            // 'this' w tym kontekście to element DOM formularza, który został wysłany
            const form = $(this);
            const messagesContainer = $('#form-messages');

            // Sprawdzamy, czy edytor EasyMDE istnieje i aktualizujemy pole textarea
            if (typeof easyMDE !== 'undefined' && easyMDE) {
                $('#description').val(easyMDE.value());
            }

            const submitButton = form.find('button[type="submit"]');
            const originalButtonText = submitButton.text();
            submitButton.prop('disabled', true).text('Wysyłanie...');
            messagesContainer.empty().removeClass('message-box--error message-box--success').hide();

            const formData = new FormData(this);
            formData.append('action', 'devents_save_event');
            formData.append('_wpnonce', devents_config.save_event_nonce);

            $.ajax({
                url: devents_config.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    let message = 'Wystąpił nieznany błąd.';
                    let messageClass = 'message-box--error';

                    if (response.success) {
                        message = response.data.message;
                        messageClass = 'message-box--success';

                        if (form.find('input[name="event_id"]').val() == '0') {
                            form[0].reset();
                            if (easyMDE) easyMDE.value('');
                        }
                    } else if (response.data && response.data.message) {
                        message = response.data.message;
                    }

                    messagesContainer.addClass('message-box ' + messageClass).html(message).show();
                    $('html, body').animate({ scrollTop: messagesContainer.offset().top - 100 }, 'slow');
                },
                error: function () {
                    messagesContainer.addClass('message-box message-box--error').html('Wystąpił krytyczny błąd serwera. Spróbuj ponownie.').show();
                },
                complete: function () {
                    submitButton.prop('disabled', false).text(originalButtonText);
                }
            });
        });
    });