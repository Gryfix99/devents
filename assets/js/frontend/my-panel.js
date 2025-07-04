document.addEventListener('DOMContentLoaded', function () {
    const panelContainer = document.querySelector('.panel-nav-container');
    const sidebar = document.querySelector('.panel-nav-sidebar');
    const toggleButton = document.getElementById('panel-nav-toggle'); // desktop toggle
    const mobileToggle = document.querySelector('.mobile-menu-toggle'); // hamburger na dole

    // Sprawdzenie elementów
    if (!panelContainer || !sidebar) {
        console.warn('Brak elementów panelu nawigacyjnego.');
        return;
    }

    // Funkcja wykrywająca mobilkę
    const isMobile = () => window.innerWidth <= 768;

    // Inicjalizacja stanu sidebar desktop
    if (!localStorage.getItem('sidebarState')) {
        localStorage.setItem('sidebarState', 'collapsed');
    }

    const setSidebarState = (state) => {
        if (state === 'collapsed') {
            panelContainer.classList.add('is-collapsed');
            document.body.classList.add('sidebar-collapsed');
            document.body.classList.remove('sidebar-expanded');
            localStorage.setItem('sidebarState', 'collapsed');
        } else {
            panelContainer.classList.remove('is-collapsed');
            document.body.classList.add('sidebar-expanded');
            document.body.classList.remove('sidebar-collapsed');
            localStorage.setItem('sidebarState', 'expanded');
        }
        updateDesktopToggleIcons();
    };

    const updateDesktopToggleIcons = () => {
        if (!toggleButton) return;
        const iconClose = toggleButton.querySelector('.icon-close');
        const iconMenu = toggleButton.querySelector('.icon-menu');
        const isCollapsed = panelContainer.classList.contains('is-collapsed');
        if (iconClose && iconMenu) {
            iconClose.style.display = isCollapsed ? 'none' : 'block';
            iconMenu.style.display = isCollapsed ? 'block' : 'none';
        }
    };

    // Toggle desktop sidebar
    const toggleSidebarDesktop = () => {
        const isCollapsed = panelContainer.classList.contains('is-collapsed');
        setSidebarState(isCollapsed ? 'expanded' : 'collapsed');
    };

    // Toggle mobile sidebar (nakładkowy)
    const toggleSidebarMobile = () => {
        const isVisible = sidebar.classList.contains('mobile-visible');
        if (isVisible) {
            sidebar.classList.remove('mobile-visible');
            mobileToggle.classList.remove('active');
            // Przywróć scroll body
            document.body.style.overflow = '';
        } else {
            sidebar.classList.add('mobile-visible');
            mobileToggle.classList.add('active');
            // Zablokuj scroll body, bo menu nakładkowe
            document.body.style.overflow = 'hidden';
        }
    };

    // Inicjalizacja po załadowaniu
    if (isMobile()) {
        // Mobilka: sidebar domyślnie ukryty
        sidebar.classList.remove('mobile-visible');
        if (mobileToggle) mobileToggle.classList.remove('active');
        document.body.classList.remove('sidebar-expanded', 'sidebar-collapsed');
        document.body.style.marginLeft = '0';
    } else {
        // Desktop: ustaw stan z localStorage
        setSidebarState(localStorage.getItem('sidebarState'));
        sidebar.classList.remove('mobile-visible');
        if (mobileToggle) mobileToggle.classList.remove('active');
        document.body.style.overflow = '';
    }

    // Obsługa kliknięcia toggle desktopowego
    if (toggleButton) {
        toggleButton.addEventListener('click', () => {
            if (!isMobile()) {
                toggleSidebarDesktop();
            }
        });
    }

    // Obsługa hamburgera mobilnego
    if (mobileToggle) {
        mobileToggle.addEventListener('click', () => {
            if (isMobile()) {
                toggleSidebarMobile();
            }
        });
    }

    // Ukryj menu mobilne po kliknięciu linku w sidebarze
    document.querySelectorAll('.panel-nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (isMobile() && sidebar.classList.contains('mobile-visible')) {
                toggleSidebarMobile();
            }
        });
    });

    // Reset przy zmianie rozmiaru okna
    window.addEventListener('resize', () => {
        if (isMobile()) {
            // Mobilka: ukryj sidebar i resetuj body
            sidebar.classList.remove('mobile-visible');
            if (mobileToggle) mobileToggle.classList.remove('active');
            document.body.style.overflow = '';
            document.body.style.marginLeft = '0';
            panelContainer.classList.remove('is-collapsed');
            document.body.classList.remove('sidebar-expanded', 'sidebar-collapsed');
        } else {
            // Desktop: przywróć sidebar według stanu
            setSidebarState(localStorage.getItem('sidebarState'));
            sidebar.classList.remove('mobile-visible');
            if (mobileToggle) mobileToggle.classList.remove('active');
            document.body.style.overflow = '';
        }
        updateDesktopToggleIcons();
    });
    
    // --- OSTATECZNA LOGIKA DLA POP-UPU (używa plików cookie) ---
    const $profileNagOverlay = $('#profile-nag-overlay');

    if ($profileNagOverlay.length) {
        const setNagSeenCookie = () => {
            // Ustawiamy plik cookie, który wygaśnie po zamknięciu przeglądarki
            document.cookie = "devents_profile_nag_seen=true; path=/";
        };

        // Zamykanie i ustawianie ciasteczka
        $profileNagOverlay.on('click', function (e) {
            // Jeśli kliknięto w tło LUB w przycisk "Później"
            if ($(e.target).is($profileNagOverlay) || $(e.target).is('#profile-nag-close')) {
                setNagSeenCookie();
                $profileNagOverlay.fadeOut(300);
            }
        });

        // Ustawianie ciasteczka po kliknięciu w główny przycisk
        $('#profile-nag-goto-settings').on('click', setNagSeenCookie);
    }
});