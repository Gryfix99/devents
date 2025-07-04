function initializeGraphicGenerator(eventId) {
    if (typeof html2canvas === 'undefined') {
        console.error('Błąd: Biblioteka html2canvas nie jest załadowana.');
        return;
    }

    const modalContent = document.getElementById('graphic-modal-body');
    if (!modalContent) return;

    const lightBtn = modalContent.querySelector('#light-btn');
    const darkBtn = modalContent.querySelector('#dark-btn');
    const previewContainer = modalContent.querySelector('#graphicPreview');
    const fullGraphicContainer = document.querySelector('#graphicFull .img-event-graphic'); // poza modalContent, bo zwykle tak jest
    const downloadBtn = modalContent.querySelector('#download-btn');

    if (!lightBtn || !darkBtn || !previewContainer || !fullGraphicContainer || !downloadBtn) {
        console.error('Brak wymaganych elementów w DOM.');
        return;
    }

    // Kopiuj zawartość FULL do PREVIEW tylko raz przy inicjalizacji
    previewContainer.innerHTML = fullGraphicContainer.innerHTML;

    function setTheme(theme) {
        [previewContainer, fullGraphicContainer].forEach(el => {
            if (el) {
                el.classList.remove('light', 'dark');
                el.classList.add(theme);
            }
        });
        lightBtn.classList.toggle('active', theme === 'light');
        darkBtn.classList.toggle('active', theme === 'dark');
    }

    lightBtn.addEventListener('click', () => setTheme('light'));
    darkBtn.addEventListener('click', () => setTheme('dark'));

    downloadBtn.addEventListener('click', async () => {
        downloadBtn.innerText = 'Generowanie...';
        downloadBtn.disabled = true;
        try {
            const canvas = await html2canvas(fullGraphicContainer, { scale: 2, useCORS: true, allowTaint: true, backgroundColor: null });
            if (!canvas) throw new Error('html2canvas zwrócił null');

            const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png', 1.0));
            if (!blob) throw new Error('toBlob zwrócił null');

            const file = new File([blob], `wydarzenie-devents-${eventId}.png`, { type: 'image/png' });

            if (navigator.canShare && navigator.canShare({ files: [file] })) {
                try {
                    await navigator.share({ files: [file], title: file.name });
                } catch (e) {
                    console.warn('Share API odrzucone, przechodzenie do pobierania.', e);
                    downloadFile(blob, file.name);
                }
            } else {
                downloadFile(blob, file.name);
            }

        } catch (err) {
            console.error('Błąd generowania grafiki:', err);
            alert('Wystąpił błąd podczas generowania grafiki.');
        } finally {
            downloadBtn.innerText = 'Pobierz grafikę';
            downloadBtn.disabled = false;
        }
    });

    function downloadFile(blob, filename) {
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);
    }

    setTheme('dark');

    document.querySelectorAll('.img-break-word').forEach(el => {
        const words = el.textContent.split(' ');
        let newHtml = '';
        let charCount = 0; // licznik znaków w bieżącej linii

        words.forEach((word, index) => {
            // długość słowa + 1 na spację (poza pierwszym słowem)
            const wordLen = word.length + (index === 0 ? 0 : 1);

            if (charCount + wordLen > 36) {
                // jeśli dodanie słowa przekroczy limit - dodaj <br> przed słowem i resetuj licznik
                newHtml += '<br>' + word;
                charCount = word.length; // nowa linia zaczyna się od długości tego słowa
            } else {
                // normalnie dopisujemy słowo (ze spacją, jeśli nie jest pierwsze)
                newHtml += (index === 0 ? '' : ' ') + word;
                charCount += wordLen;
            }
        });

        el.innerHTML = newHtml;
    });


}
