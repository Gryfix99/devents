<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Pomocnicza klasa do obsługi środowiska Twig.
 */
class DEvents_Twig_Helper {
    private static $instance = null;
    public $twig;

    private function __construct() {
        $autoload_path = DEW_PLUGIN_PATH . 'vendor/autoload.php';
        if ( file_exists( $autoload_path ) ) {
            require_once $autoload_path;
        }

        if (!class_exists('\Twig\Loader\FilesystemLoader')) {
            error_log('DEvents Fatal Error: Twig library not found. Please run "composer install".');
            return;
        }

        $loader = new \Twig\Loader\FilesystemLoader( DEW_PLUGIN_PATH . 'templates' );
        $this->twig = new \Twig\Environment( $loader, [
            'cache' => false, // Ustaw na ścieżkę do folderu cache na produkcji, np. DEW_PLUGIN_PATH . 'cache/twig'
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'auto_reload' => defined('WP_DEBUG') && WP_DEBUG,
        ]);

        if (class_exists('\Twig\Extension\CoreExtension')) {
            $this->twig->getExtension(\Twig\Extension\CoreExtension::class)->setTimezone( get_option('timezone_string') ?: 'Europe/Warsaw' );
        }
        
        $this->register_wp_functions();
    }

    /**
     * Rejestruje kluczowe funkcje WordPressa, aby były dostępne w szablonach Twig.
     */
    private function register_wp_functions() {
        $this->twig->addFunction(new \Twig\TwigFunction('home_url', 'home_url'));
        $this->twig->addFunction(new \Twig\TwigFunction('do_shortcode', 'do_shortcode'));
        $this->twig->addFunction(new \Twig\TwigFunction('wp_logout_url', 'wp_logout_url'));
        $this->twig->addFunction(new \Twig\TwigFunction('wp_create_nonce', 'wp_create_nonce'));
        $this->twig->addFunction(new \Twig\TwigFunction('wp_nonce_url', 'wp_nonce_url'));
        $this->twig->addFunction(new \Twig\TwigFunction('wp_nonce_field', function($action = -1, $name = "_wpnonce", $referer = true, $echo = false) {
            return wp_nonce_field($action, $name, $referer, $echo);
        }, ['is_safe' => ['html']]));
    }

    /**
     * Zwraca jedyną instancję klasy.
     */
    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Renderuje szablon Twig.
     */
    public function render( $template_name, $data = [] ) {
        if (!$this->twig) {
            return 'Błąd: Środowisko Twig nie jest dostępne.';
        }
        try {
            return $this->twig->render( $template_name . '.twig', $data );
        } catch ( \Twig\Error\Error $e ) {
            error_log( "Twig Template Error in '" . $template_name . "': " . $e->getMessage() );
            return (defined('WP_DEBUG') && WP_DEBUG) ? 'Błąd szablonu: ' . esc_html($e->getMessage()) : '';
        }
    }
}

/**
 * Główna klasa rejestrująca i obsługująca wszystkie shortcode'y wtyczki.
 */
class DEvents_Shortcodes {
    private $twig;
    private $twig_helper;

    public function __construct() {
        $this->twig = DEvents_Twig_Helper::get_instance();
        $this->twig_helper = DEvents_Twig_Helper::get_instance();
        $this->register_shortcodes();
    }

    public function handle_forms_post_requests() {
        if ('POST' !== $_SERVER['REQUEST_METHOD']) return;

        if (isset($_POST['devents_action'])) {
            require_once DEW_PLUGIN_PATH . 'includes/forms/form-handler.php';
            devents_handle_auth_forms();
        }
    }

    private function register_shortcodes() {
        $shortcodes = [
            'register_form'                 => 'render_form',
            'login_form'                    => 'render_form',
            'devents_password_reset'        => 'render_password_reset_form',
            'my_account'                    => 'render_my_account',
            'publish_event'                 => 'render_publish_event',
            'publish_video'                 => 'render_publish_video_form',
            'wydarzenie'                    => 'render_single_event',
            'video'                         => 'render_single_video',
            'homepage'                      => 'render_homepage',
            'my_events_list'                => 'render_my_events_list',
            'my_videos_list'                => 'render_my_videos_list',
            'event_search'                  => 'render_event_search',
            'video_search'                  => 'render_video_search',
            'devents_user_panel'            => 'render_user_panel',
        ];

        foreach ( $shortcodes as $tag => $method ) {
            add_shortcode( $tag, [ $this, $method ] );
        }
    }
    
    public function render_form($atts, $content = null, $tag = '') {
        if ( is_user_logged_in() ) { 
            wp_safe_redirect( home_url('/panel-uzytkownika/') ); // Lepiej przekierować do panelu
            exit; 
        }

        global $devents_form_errors;
        
        $template_map = [
            'login_form'    => 'forms/login-form', 
            'register_form' => 'forms/register-form'
        ];
        
        if (!isset($template_map[$tag])) return '';

        $form_type = ($tag === 'login_form' ? 'login' : 'register');
        
        $data = [
            'nonce_field'   => wp_nonce_field('devents-' . $form_type, '_wpnonce', true, false),
            'error_message' => $devents_form_errors[$form_type] ?? '',
            'flash_message' => null // Domyślnie brak wiadomości flash
        ];

        if (isset($_SESSION['devents_flash_message'])) {
            $data['flash_message'] = $_SESSION['devents_flash_message'];
            
            unset($_SESSION['devents_flash_message']);
        }

        return $this->twig->render($template_map[$tag], $data);
    }

    public function render_php_template($path_segment) {
        $path = DEW_PLUGIN_PATH . 'includes/' . $path_segment . '.php';
        ob_start();
        if ( file_exists($path) ) include $path;
        return ob_get_clean();
    }
    
    public function render_password_reset_form() {
        return $this->twig->render('forms/password-reset');
    }

    public function render_user_panel() {
        if ( ! is_user_logged_in() ) {
            return $this->render_form([], null, 'login_form');
        }

        // Pobranie kluczowych danych
        $current_user_id = get_current_user_id();
        $user_data = get_userdata($current_user_id);
        $is_organizer = in_array('organizer', (array) $user_data->roles);
        global $wpdb;

        // Definicja pozycji w menu
        $menu_items = [
            'pulpit'            => ['title' => 'Pulpit', 'icon' => 'dashboard', 'visible' => true],
            'moje-wydarzenia'   => ['title' => 'Moje Wydarzenia', 'icon' => 'event_list', 'visible' => true],
            'dodaj-wydarzenie'  => ['title' => 'Opublikuj Wydarzenie', 'icon' => 'add_circle', 'visible' => true],
            'moje-filmy'        => ['title' => 'Moje Filmy', 'icon' => 'video_library', 'visible' => true],
            'dodaj-film'        => ['title' => 'Opublikuj Film', 'icon' => 'video_call', 'visible' => true],
            'ustawienia'        => ['title' => 'Ustawienia Konta', 'icon' => 'settings', 'visible' => true],
            'edytuj-wydarzenie' => ['title' => 'Edytuj Wydarzenie', 'icon' => 'edit_note', 'visible' => false],
            'edytuj-film'       => ['title' => 'Edytuj Film', 'icon' => 'edit_note', 'visible' => false],
        ];

        // Określenie aktywnego widoku
        $current_view = isset($_GET['view']) ? sanitize_key($_GET['view']) : 'pulpit';
        if (!array_key_exists($current_view, $menu_items)) {
            $current_view = 'pulpit';
        }

        // Przygotowanie danych dla karty profilu i pop-upu
        $data_packet = get_user_meta($current_user_id, '_devents_organizer_data', true) ?: [];
        
        $profile_card_data = [
            'email'          => $user_data->user_email,
            'logo_url'       => $data_packet['logo_url'] ?? '',
            'is_institution' => $is_organizer,
        ];

        if ($is_organizer) {
            $profile_card_data['org_name'] = $data_packet['org_name'] ?? '';
            $profile_card_data['display_name'] = trim(($data_packet['coordinator']['first_name'] ?? '') . ' ' . ($data_packet['coordinator']['last_name'] ?? ''));
            $profile_card_data['phone'] = $data_packet['org_phone'] ?? '';
        } else {
            $profile_card_data['display_name'] = $user_data->display_name;
        }

        // Ostateczna, odporna na błędy logika dla pop-upu
        $show_profile_nag = false;
        if ($is_organizer) {
            // Sprawdzamy, czy profil jest niekompletny
            $is_profile_incomplete = (
                !isset($data_packet['logo_url']) || empty($data_packet['logo_url']) || 
                !isset($data_packet['org_description']) || empty($data_packet['org_description'])
            );

            $has_seen_nag = isset($_COOKIE['devents_profile_nag_seen']) && $_COOKIE['devents_profile_nag_seen'] === 'true';

            if ($is_profile_incomplete && !$has_seen_nag) {
                $show_profile_nag = true;
            }
        }

        // Przygotowanie danych dla pulpitu (tylko, gdy jest potrzebny)
        $dashboard_data = [];
        if ($current_view === 'pulpit') {
            $table_events = $wpdb->prefix . 'events_list';
            $table_videos = $wpdb->prefix . 'events_materials';
            
            $dashboard_data = [
                'events_count' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_events} WHERE user_id = %d", $current_user_id)),
                'videos_count' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_videos} WHERE user_id = %d", $current_user_id)),
                'total_views'  => 0, // Placeholder
            ];
        }
        
        // Finalna paczka danych dla szablonu
        $data = [
            'user'                    => $user_data,
            'menu_items'              => $menu_items,
            'current_view'            => $current_view,
            'panel_url'               => home_url('/panel-uzytkownika/'),
            'page_content'            => $this->get_panel_page_content($current_view, $current_user_id, $profile_card_data),
            'profile_card'            => $profile_card_data,
            'dashboard_data'          => $dashboard_data,
            'show_profile_nag'        => $show_profile_nag,
            'is_registration_success' => isset($_GET['rejestracja']) && $_GET['rejestracja'] === 'sukces',
        ];

        return $this->twig->render('user/panel', $data);
    }
    
    private function get_panel_page_content($view, $user_id, $profile_card_data = []) {
        if ($view === 'pulpit') {
            global $wpdb;
            $dashboard_data = [
                'events_count' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}events_list WHERE user_id = %d", $user_id)),
                'videos_count' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}events_materials WHERE user_id = %d", $user_id)),
                'total_views'  => 0,
            ];
            // Przekazujemy dane profilu i pulpitu do szablonu my-pulpit.twig
            return $this->twig->render('user/my-pulpit', [
                'dashboard_data' => $dashboard_data,
                'profile_card'   => $profile_card_data, // <-- KLUCZOWA ZMIANA
                'panel_url'      => home_url('/panel-uzytkownika/'),
                'is_registration_success' => isset($_GET['rejestracja']) && $_GET['rejestracja'] === 'sukces',
            ]);
        }
        $shortcode_map = [
            'ustawienia' => '[my_account]',
            'dodaj-wydarzenie' => '[publish_event]',
            'edytuj-wydarzenie' => '[publish_event]',
            'moje-wydarzenia' => '[my_events_list]',
            'dodaj-film' => '[publish_video]',
            'edytuj-film' => '[publish_video]',
            'moje-filmy' => '[my_videos_list]',
        ];
        return isset($shortcode_map[$view]) ? do_shortcode($shortcode_map[$view]) : '';
    }
       
    
    public function render_my_account() {
        if ( ! is_user_logged_in() ) {
            return '';
        }

        $user_id = get_current_user_id();
        $user_data = get_userdata($user_id);
        $is_organizer = in_array('organizer', (array) $user_data->roles);

        // Pobieramy dane w zależności od roli
        $template_data = [
            'first_name' => $user_data->first_name,
            'last_name'  => $user_data->last_name,
        ];
        
        $org_data = [];
        if ($is_organizer) {
            // Pobieramy całą "paczkę" danych jednym zapytaniem
            $org_data = get_user_meta($user_id, '_devents_organizer_data', true);
            // Ustawiamy domyślne wartości, jeśli paczka jest pusta, aby uniknąć błędów
            $org_data = wp_parse_args($org_data, [
                'org_name'    => '', 'org_nip'     => '', 'org_address' => '',
                'org_phone'   => '', 'org_website' => '', 'org_email'   => '',
                'coordinator' => ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '']
            ]);
        }

        $data_for_twig = [
            'user'           => $user_data,
            'is_organizer'   => $is_organizer,
            'org_data'       => $org_data, // Przekazujemy całą paczkę danych organizacji
            'details_nonce'  => wp_create_nonce('devents-update-account-' . $user_id),
            'password_nonce' => wp_create_nonce('devents-change-password-' . $user_id),
            'delete_nonce'   => wp_create_nonce('devents-delete-account-' . $user_id),
        ];

        return $this->twig->render('user/my-account', $data_for_twig);
    }


    public function render_publish_event_form() {
        if ( ! is_user_logged_in() ) {
            return 'Musisz być zalogowany, aby opublikować wydarzenie.';
        }

        $user_id = get_current_user_id();
        $is_verified = get_user_meta($user_id, 'is_verified', true);

        if ( $is_verified !== '1' && ! current_user_can('manage_options') ) {
            return '<div class="message-box message-box--error">Twoje konto musi zostać zweryfikowane przez administratora.</div>';
        }

        global $wpdb;
        $table_events = $wpdb->prefix . 'events_list';
        $table_settings = $wpdb->prefix . 'events_settings';
        
        $event_data = null;
        $event_id_to_edit = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

        // Jeśli edytujemy, pobieramy dane z bazy
        if ( $event_id_to_edit > 0 ) {
            $event_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_events} WHERE id = %d", $event_id_to_edit));

            // Zabezpieczenie: sprawdź, czy użytkownik jest właścicielem wydarzenia
            if ( !$event_data || ($event_data->user_id != $user_id && !current_user_can('manage_options')) ) {
                return '<div class="message-box message-box--error">Nie masz uprawnień do edycji tego wydarzenia.</div>';
            }
            // Konwertujemy zapisane w JSON opcje dostępności z powrotem na tablicę
            if (!empty($event_data->accessibility)) {
                $event_data->accessibility = json_decode($event_data->accessibility, true);
            }
        }

        // Przygotowujemy dane do przekazania do szablonu Twig
        $data_for_twig = [
            'event'                 => $event_data,
            'event_categories'      => $wpdb->get_col( $wpdb->prepare("SELECT setting_value FROM {$table_settings} WHERE setting_type = %s", 'event_category') ),
            'event_methods'         => $wpdb->get_col( $wpdb->prepare("SELECT setting_value FROM {$table_settings} WHERE setting_type = %s", 'event_method') ),
            'accessibility_options' => $wpdb->get_col( $wpdb->prepare("SELECT setting_value FROM {$table_settings} WHERE setting_type = %s", 'event_accessibility') ),
        ];
        
        return $this->twig->render('pages/publish-event', $data_for_twig);


        $data_for_twig = [
            'event'                 => $event_data, // Przekazujemy dane wydarzenia (lub null, jeśli nowe)
            'event_categories'      => $wpdb->get_col( $wpdb->prepare("SELECT setting_value FROM {$table_settings} WHERE setting_type = %s", 'event_category') ),
            'event_methods'         => $wpdb->get_col( $wpdb->prepare("SELECT setting_value FROM {$table_settings} WHERE setting_type = %s", 'event_method') ),
            'event_accessibility' => $wpdb->get_col( $wpdb->prepare("SELECT setting_value FROM {$table_settings} WHERE setting_type = %s", 'event_accessibility') ),
            'nonce_field'           => wp_nonce_field('devents_save_event_action', '_wpnonce', true, false),
        ];
        
        return $this->twig->render('pages/publish-event', $data_for_twig);
    }

    public function render_single_event($atts) {
        $atts = shortcode_atts(['id' => 0], $atts);
        $event_id = intval($atts['id']);

        if ( !$event_id ) {
            return '<div class="message-box message-box--error">Nieprawidłowy ID wydarzenia.</div>';
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'events_list';
        $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $event_id));

        if (!$event) {
            return '<div class="message-box message-box--error">Nie znaleziono wydarzenia.</div>';
        }

        $event = stripslashes_deep($event);
        $converter = new \League\CommonMark\CommonMarkConverter(['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $event->description = $converter->convert($event->description);

        // Logika pobierania danych autora i organizatora
        $organizer_name = 'Nie podano organizatora';
        $organizer_is_linkable = false;
        $organizer_id = 0; // Zamiast sluga, będziemy przekazywać ID
        $author_role = 'none';
        
        $author_user = get_userdata($event->user_id);

        if ($author_user) {
            $author_role = $author_user->roles[0] ?? 'subscriber';

            if (in_array('organizer', (array) $author_user->roles)) {
                $org_name_meta = get_user_meta($author_user->ID, 'org_name', true);
                $organizer_name = $org_name_meta ?: $author_user->display_name;
                $organizer_is_linkable = true;
                $organizer_id = $author_user->ID;
            } 
            else {
                if (!empty($event->other_organizer)) {
                    $organizer_name = $event->other_organizer;
                }
            }
        }
        
        return $this->twig->render('single/event', [
            'event'                 => $event, 
            'author_role'           => $author_role,
            'organizer_name'        => $organizer_name,
            'organizer_is_linkable' => $organizer_is_linkable,
            'organizer_id'          => $organizer_id,
            'home_url'              => home_url(),
        ]);
    }

    public function render_homepage() {
        global $wpdb;
        $table = "{$wpdb->prefix}events_list";
        $now = current_time('mysql');
        
        // 1. Pobieramy wydarzenie "Hero"
        $next_event_raw = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE start_datetime >= %s AND verified = 1 ORDER BY start_datetime ASC LIMIT 1",
            $now
        ));
        $next_event_id = $next_event_raw ? $next_event_raw->id : 0;

        // 2. Pobieramy do 9 wydarzeń na siatkę, pomijając to z "Hero"
        $events_raw = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$table}
            WHERE 
                verified = 1 
                AND id != %d
                AND (
                    (start_datetime <= %s AND end_datetime >= %s) 
                    OR 
                    (start_datetime > %s)
                )
            ORDER BY start_datetime ASC
            LIMIT 9
        ", $next_event_id, $now, $now, $now));
        
        // 3. Funkcja pomocnicza do przetwarzania danych
        $process_events = function($events) use ($now) {
            if (empty($events)) return [];
            
            foreach ($events as &$event) {
                $event->permalink = home_url('/wydarzenia/' . sanitize_title($event->title) . '-' . $event->id);
                $event->is_current = (strtotime($event->start_datetime) <= strtotime($now) && !empty($event->end_datetime) && strtotime($event->end_datetime) >= strtotime($now));
            }
            return stripslashes_deep($events);
        };
        
        // 4. Przekazujemy przetworzone dane do szablonu Twig
        return $this->twig_helper->render('pages/homepage', [
            'next_event'      => $next_event_raw ? $process_events([$next_event_raw])[0] : null,
            'all_events'      => $process_events($events_raw),
        ]);
    }
    
    /**
     * Renderuje listę wydarzeń użytkownika z filtrowaniem, sortowaniem i wyszukiwaniem.
     */
    public function render_my_events_list() {
        if ( ! is_user_logged_in() ) {
            return '';
        }

        $user_id = get_current_user_id();
        global $wpdb;
        $table_name = $wpdb->prefix . 'events_list';

        // 1. Odczytanie i walidacja parametrów z URL
        $search_query = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
        $status_filter = isset($_GET['status']) ? sanitize_key($_GET['status']) : 'all';
        
        $allowed_orderby = ['id', 'title', 'start_datetime', 'verified'];
        $orderby = isset($_GET['orderby']) && in_array($_GET['orderby'], $allowed_orderby) ? $_GET['orderby'] : 'created_at';
        $order = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? strtoupper($_GET['order']) : 'DESC';

        // 2. Dynamiczne i bezpieczne budowanie zapytania SQL
        $sql = "SELECT * FROM {$table_name} WHERE user_id = %d";
        $params = [$user_id];

        if (!empty($search_query)) {
            $sql .= " AND title LIKE %s";
            $params[] = '%' . $wpdb->esc_like($search_query) . '%';
        }

        if ($status_filter !== 'all') {
            if ($status_filter === 'pending') {
                $sql .= " AND verified IN (0, 3)";
            } else {
                $status_map = ['published' => 1, 'draft' => 2];
                if (array_key_exists($status_filter, $status_map)) {
                    $sql .= " AND verified = %d";
                    $params[] = $status_map[$status_filter];
                }
            }
        }
        
        $sql .= " ORDER BY CASE WHEN verified = 1 THEN 1 ELSE 0 END ASC, {$orderby} {$order}";

        // 3. Wykonanie zapytania
        $user_events_raw = $wpdb->get_results($wpdb->prepare($sql, $params));
        
        $user_events = [];
        if ($user_events_raw) {
            foreach ($user_events_raw as $event) {
                // Definiujemy mapowanie statusów
                $status_map = [
                    1 => ['text' => 'Zweryfikowane', 'class' => 'status-badge--published'],
                    2 => ['text' => 'Wersja robocza', 'class' => 'status-badge--draft'],
                    3 => ['text' => 'Do weryfikacji', 'class' => 'status-badge--updated'],
                    0 => ['text' => 'Oczekuje', 'class' => 'status-badge--pending'],
                ];
                // Dodajemy do obiektu wydarzenia nową właściwość `status_info`
                $event->status_info = $status_map[$event->verified] ?? $status_map[0];
                
                $user_events[] = $event;
            }
        }

        // 4. Przekazanie danych do szablonu Twig
        return $this->twig->render('user/my-events', [
            'events' => $user_events,
            'current_filters' => [
                'search' => $search_query,
                'status' => $status_filter,
                'orderby' => $orderby,
                'order' => $order,
            ],
            'panel_url' => home_url('/panel-uzytkownika/'),
        ]);
    }


    public function render_event_search() {
        global $wpdb;
        $event_table_name = $wpdb->prefix . 'events_list';
        $table_settings = $wpdb->prefix . 'events_settings';
        $all_categories = $wpdb->get_col( $wpdb->prepare(
            "SELECT setting_value FROM {$table_settings} WHERE setting_type = %s ORDER BY setting_value ASC", 
            'event_category'
        ));
        $all_delivery_modes = $wpdb->get_col( $wpdb->prepare(
            "SELECT setting_value FROM {$table_settings} WHERE setting_type = %s ORDER BY setting_value ASC", 
            'event_method'
        ));
        $all_accessibility = $wpdb->get_col( $wpdb->prepare(
            "SELECT setting_value FROM {$table_settings} WHERE setting_type = %s ORDER BY setting_value ASC", 
            'event_accessibility'
        ));
        $filters = ['search_query'  => isset($_GET['search_query']) ? sanitize_text_field($_GET['search_query']) : '', 'category' => isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '', 'delivery_mode' => isset($_GET['delivery_mode']) ? sanitize_text_field($_GET['delivery_mode']) : '', 'accessibility' => isset($_GET['accessibility']) && is_array($_GET['accessibility']) ? array_map('sanitize_text_field', $_GET['accessibility']) : [], 'start_date' => isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '', 'end_date' => isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '', 'free' => isset($_GET['free']), 'location' => isset($_GET['location']) ? sanitize_text_field($_GET['location']) : '', 'only_current'  => isset($_GET['only_current']),];
        $base_conditions = ["verified = 1"];
        if (!empty($filters['search_query'])) { $base_conditions[] = $wpdb->prepare("(title LIKE %s OR description LIKE %s)", '%' . $wpdb->esc_like($filters['search_query']) . '%', '%' . $wpdb->esc_like($filters['search_query']) . '%'); }
        if (!empty($filters['category'])) { $base_conditions[] = $wpdb->prepare("category = %s", $filters['category']); }
        if (!empty($filters['delivery_mode'])) { $base_conditions[] = $wpdb->prepare("delivery_mode = %s", $filters['delivery_mode']); }
        if (!empty($filters['location'])) { $base_conditions[] = $wpdb->prepare("location LIKE %s", '%' . $wpdb->esc_like($filters['location']) . '%'); }
        if ($filters['free']) { $base_conditions[] = "(price <= 0 OR price IS NULL)"; }
        if (!empty($filters['accessibility'])) {
            $acc_conds = [];
            foreach ($filters['accessibility'] as $acc) { $acc_conds[] = $wpdb->prepare("accessibility LIKE %s", '%' . $wpdb->esc_like($acc) . '%'); }
            if (!empty($acc_conds)) { $base_conditions[] = "(" . implode(" OR ", $acc_conds) . ")"; }
        }
        $base_where_clause = implode(" AND ", $base_conditions);
        $date_conditions = [];
        if (!empty($filters['start_date'])) { $date_conditions[] = $wpdb->prepare("DATE(start_datetime) >= %s", $filters['start_date']); }
        if (!empty($filters['end_date'])) { $date_conditions[] = $wpdb->prepare("DATE(start_datetime) <= %s", $filters['end_date']); }
        $current_events = []; $upcoming_events = [];
        $process_events = function($events, $is_current = false) {
            if (empty($events)) return [];
            $events = stripslashes_deep($events);
            foreach ($events as &$event) {
                $slug = sanitize_title($event->title);
                $event->permalink = home_url('/wydarzenia/' . $slug . '-' . $event->id);
                $event->is_current = $is_current;
            }
            return $events;
        };
        if ($filters['only_current']) {
            $where_parts = array_merge([$base_where_clause, "NOW() BETWEEN start_datetime AND end_datetime"], $date_conditions);
            $current_events = $wpdb->get_results("SELECT * FROM {$event_table_name} WHERE " . implode(" AND ", $where_parts) . " ORDER BY start_datetime ASC");
        } else {
            $where_parts_cur = array_merge([$base_where_clause, "NOW() BETWEEN start_datetime AND end_datetime"], $date_conditions);
            $current_events = $wpdb->get_results("SELECT * FROM {$event_table_name} WHERE " . implode(" AND ", $where_parts_cur) . " ORDER BY start_datetime ASC");
            $where_parts_up = array_merge([$base_where_clause, "start_datetime > NOW()"], $date_conditions);
            $upcoming_events = $wpdb->get_results("SELECT * FROM {$event_table_name} WHERE " . implode(" AND ", $where_parts_up) . " ORDER BY start_datetime ASC");
        }
        return $this->twig->render('pages/event-search', ['filters' => $filters, 'current_events' => $process_events($current_events, true), 'upcoming_events' => $process_events($upcoming_events), 'all_categories' => $all_categories, 'all_delivery_modes' => $all_delivery_modes, 'all_accessibility' => $all_accessibility,]);
    }

    public function render_video_search() {
        global $wpdb;
        $table_materials = $wpdb->prefix . 'events_materials';
        $table_settings = $wpdb->prefix . 'events_settings';

        // Pobieramy wszystkie opcje potrzebne do filtrów
        $all_categories = $wpdb->get_col($wpdb->prepare("SELECT setting_value FROM {$table_settings} WHERE setting_type = %s ORDER BY setting_value ASC", 'video_category'));
        $all_accessibility = $wpdb->get_col($wpdb->prepare("SELECT setting_value FROM {$table_settings} WHERE setting_type = %s ORDER BY setting_value ASC", 'video_accessibility'));

        // Pobieranie i sanityzacja filtrów z adresu URL
        $filters = [
            'search'        => isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '',
            'category'      => isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '',
            'author'        => isset($_GET['author']) ? sanitize_text_field(wp_unslash($_GET['author'])) : '',
            'translator'    => isset($_GET['translator']) ? sanitize_text_field(wp_unslash($_GET['translator'])) : '',
            'accessibility' => isset($_GET['accessibility']) && is_array($_GET['accessibility']) ? array_map('sanitize_text_field', $_GET['accessibility']) : [],
        ];

        // Budowanie zapytania SQL
        $sql = "SELECT * FROM {$table_materials} WHERE verified = 1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (title LIKE %s OR description LIKE %s)";
            $params[] = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = '%' . $wpdb->esc_like($filters['search']) . '%';
        }
        if (!empty($filters['category'])) {
            $sql .= " AND category = %s";
            $params[] = $filters['category'];
        }
        if (!empty($filters['author'])) {
            $sql .= " AND author LIKE %s";
            $params[] = '%' . $wpdb->esc_like($filters['author']) . '%';
        }
        if (!empty($filters['translator'])) {
            $sql .= " AND translator LIKE %s";
            $params[] = '%' . $wpdb->esc_like($filters['translator']) . '%';
        }
        if (!empty($filters['accessibility'])) {
            $acc_conds = [];
            foreach ($filters['accessibility'] as $acc) {
                $acc_conds[] = $wpdb->prepare("accessibility LIKE %s", '%' . $wpdb->esc_like($acc) . '%');
            }
            $sql .= " AND (" . implode(' OR ', $acc_conds) . ")";
        }
        
        $sql .= " ORDER BY publish_date DESC";

        $videos_raw = $wpdb->get_results($wpdb->prepare($sql, $params));
        
        $videos = array_map([$this, 'prepare_video_card_data'], $videos_raw);

        return $this->twig_helper->render('pages/video-search', [
            'videos'            => $videos,
            'filters'           => $filters,
            'all_categories'    => $all_categories,
            'all_accessibility' => $all_accessibility,
            'current_base_url'  => get_permalink(),
        ]);
    }

    public function render_my_videos_list() {
        if (!is_user_logged_in()) {
            return '';
        }

        $user_id = get_current_user_id();
        global $wpdb;
        $table_name = $wpdb->prefix . 'events_materials';

        // 1. Odczytanie i walidacja parametrów z URL
        $search_query = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
        $status_filter = isset($_GET['status']) ? sanitize_key($_GET['status']) : 'all';
        
        $allowed_orderby = ['id', 'title', 'publish_date', 'verified'];
        $orderby = isset($_GET['orderby']) && in_array($_GET['orderby'], $allowed_orderby) ? $_GET['orderby'] : 'id';
        $order = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? strtoupper($_GET['order']) : 'DESC';

        // 2. Dynamiczne i bezpieczne budowanie zapytania SQL
        $sql = "SELECT * FROM {$table_name} WHERE user_id = %d";
        $params = [$user_id];

        if (!empty($search_query)) {
            $sql .= " AND title LIKE %s";
            $params[] = '%' . $wpdb->esc_like($search_query) . '%';
        }

        if ($status_filter !== 'all') {
            if ($status_filter === 'pending') {
                $sql .= " AND verified IN (0, 3)";
            } else {
                $status_map = ['published' => 1, 'draft' => 2];
                if (array_key_exists($status_filter, $status_map)) {
                    $sql .= " AND verified = %d";
                    $params[] = $status_map[$status_filter];
                }
            }
        }
        
        $order_sql = " ORDER BY CASE WHEN verified = 1 THEN 1 ELSE 0 END ASC, {$orderby} {$order}";
        $sql .= $order_sql;

        // 3. Wykonanie zapytania
        $user_videos_raw = $wpdb->get_results($wpdb->prepare($sql, $params));
        
        // 4. Przetwarzanie wyników
        $user_videos = array_map([$this, 'prepare_video_card_data'], $user_videos_raw);

        // 5. Przekazanie danych do szablonu Twig
        return $this->twig->render('user/my-videos', [
            'videos' => $user_videos,
            'current_filters' => [
                'search' => $search_query,
                'status' => $status_filter,
                'orderby' => $orderby,
                'order' => $order,
            ],
            'panel_url' => home_url('/panel-uzytkownika/'),
        ]);
    }

    private function prepare_video_card_data($video_object) {
        if (empty($video_object)) {
            return null;
        }
        
        // 1. Tworzymy poprawny slug dla linku
        $video_object->slug = sanitize_title($video_object->title) . '-' . $video_object->id;
        
        // 2. Tworzymy pełny URL do wpisu CPT 'materials' o slugu 'filmy'
        $video_object->permalink = home_url('/filmy/' . $video_object->slug);

        // 3. Pobieramy miniaturkę
        if (empty($video_object->thumbnail_url)) {
            $video_object->thumbnail = $this->get_video_thumbnail_from_url($video_object->video_url);
        } else {
            $video_object->thumbnail = $video_object->thumbnail_url;
        }

        return $video_object;
    }

    private function get_video_thumbnail_from_url($url) {
        // Ustawiamy domyślny obrazek, na wypadek gdyby nic nie znaleziono
        $default_thumbnail = DEW_PLUGIN_URL . 'assets/images/default-thumbnail.png';

        if (empty($url)) {
            return $default_thumbnail;
        }

        $video_id = '';
        // Sprawdzamy, czy to standardowy link YouTube
        if (preg_match('/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            $video_id = $matches[1];
        } 
        // Sprawdzamy, czy to skrócony link youtu.be
        elseif (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            $video_id = $matches[1];
        }

        // Jeśli znaleźliśmy ID YouTube, zwracamy link do miniaturki w średniej jakości
        if ($video_id) {
            return "https://i.ytimg.com/vi/{$video_id}/mqdefault.jpg";
        }

        // Dla Vimeo i innych na razie zwracamy domyślny obrazek
        // W przyszłości można to rozbudować o zapytania do API Vimeo.
        
        return $default_thumbnail;
    }

    /**
     * Renderuje stronę pojedynczego materiału wideo.
     */
    public function render_single_video($atts) {
        $atts = shortcode_atts(['id' => 0], $atts, 'video');
        $video_id = intval($atts['id']);

        if (!$video_id) {
            return '<div class="message-box message-box--error">Nieprawidłowy ID materiału.</div>';
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'events_materials';
        $video = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d AND verified = 1", $video_id));

        if (!$video) {
            return '<div class="message-box message-box--error">Nie znaleziono materiału wideo lub nie został on jeszcze zweryfikowany.</div>';
        }

        // Przetwarzanie opisu z Markdown na HTML
        $converter = new \League\CommonMark\CommonMarkConverter(['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $video->description = $converter->convert($video->description);

        // Pobieranie danych organizatora, który dodał film
        $organizer_name = 'Nie podano';
        $organizer_id = $video->user_id;
        $user_data = get_userdata($organizer_id);
        
        if ($user_data && in_array('organizer', (array) $user_data->roles)) {
             $org_data_packet = get_user_meta($organizer_id, '_devents_organizer_data', true) ?: [];
             $organizer_name = $org_data_packet['org_name'] ?? $user_data->display_name;
        } elseif ($user_data) {
            $organizer_name = $user_data->display_name; // Jeśli dodał to subscriber
        }

        $twig_helper = DEvents_Twig_Helper::get_instance();
        
        return $twig_helper->render('single/video', [
            'video'          => $video,
            'organizer_name' => $organizer_name,
            'organizer_id'   => $video->user_id,
        ]);
    }

    /**
     * Renderuje formularz do dodawania i edycji filmów.
     */
    public function render_publish_video_form() {
        if (!is_user_logged_in()) {
            return 'Musisz być zalogowany, aby opublikować film.';
        }

        global $wpdb;
        $table_settings = $wpdb->prefix . 'events_settings';
        
        $video_data = null;
        $video_id_to_edit = isset($_GET['video_id']) ? intval($_GET['video_id']) : 0;

        if ($video_id_to_edit > 0) {
            $table_videos = $wpdb->prefix . 'events_materials';
            $video_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_videos} WHERE id = %d", $video_id_to_edit));

            if (!$video_data || ($video_data->user_id != get_current_user_id() && !current_user_can('manage_options'))) {
                return '<div class="message-box message-box--error">Nie masz uprawnień do edycji tego materiału.</div>';
            }

            if (!empty($video_data->accessibility)) {
                $video_data->accessibility = json_decode($video_data->accessibility, true);
            }
        }

        $data_for_twig = [
            'video_categories'      => $wpdb->get_col( $wpdb->prepare("SELECT setting_value FROM {$table_settings} WHERE setting_type = %s", 'video_category') ),
            'video_accessibility' => $wpdb->get_col( $wpdb->prepare("SELECT setting_value FROM {$table_settings} WHERE setting_type = %s", 'video_accessibility') ),
            'nonce_field'           => wp_nonce_field('devents_save_video_action', '_wpnonce', true, false),
            'video'                 => $video_data,
        ];
        
        return $this->twig->render('pages/publish-video', $data_for_twig);
    }

}

new DEvents_Shortcodes();