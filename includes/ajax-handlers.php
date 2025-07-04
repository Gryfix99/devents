<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rejestruje wszystkie hooki dla funkcji AJAX wtyczki.
 */
function devents_register_ajax_handlers() {
    // Frontend (niezalogowani)
    add_action('wp_ajax_nopriv_devents_ajax_login', 'devents_ajax_login_callback');
    add_action('wp_ajax_nopriv_devents_register', 'devents_ajax_register_user_callback');
    add_action('wp_ajax_devents_password_reset', 'devents_ajax_password_reset_callback');
    add_action('wp_ajax_nopriv_devents_password_reset', 'devents_ajax_password_reset_callback');

    // Backend (zalogowani z uprawnieniami admina)
    add_action('wp_ajax_event_single_action', 'devents_event_single_action_callback');
    add_action('wp_ajax_bulk_event_action', 'devents_bulk_event_action_callback');
    add_action('wp_ajax_devents_get_graphic', 'devents_get_graphic_callback');
    add_action('wp_ajax_verify_material', 'devents_ajax_verify_material_callback');
    add_action('wp_ajax_publish_material', 'devents_ajax_publish_material_callback');
    add_action('wp_ajax_delete_material', 'devents_ajax_delete_material_callback');
    add_action('wp_ajax_delete_user', 'devents_ajax_delete_user');
    add_action('wp_ajax_verify_user', 'devents_ajax_verify_user');
    add_action('wp_ajax_bulk_user_action', 'devents_ajax_bulk_user_action');
    
    // Ustawienia konta (frontend)
    add_action('wp_ajax_devents_update_account', 'devents_ajax_update_account_callback');
    add_action('wp_ajax_devents_change_password', 'devents_ajax_change_password_callback');
    add_action('wp_ajax_devents_delete_account', 'devents_ajax_delete_account_callback');

    // Dla zalogowanych (frontend)
    // Wydarzenia
    add_action('wp_ajax_devents_duplicate_event', 'devents_ajax_duplicate_event_callback');
    add_action('wp_ajax_devents_delete_event', 'devents_ajax_delete_event_callback');
    add_action('wp_ajax_devents_save_event', 'devents_ajax_save_event_callback');
    // Materiały filmowe
    add_action('wp_ajax_devents_save_video', 'devents_ajax_save_video_callback');
    add_action('wp_ajax_devents_duplicate_video', 'devents_ajax_duplicate_video_callback');
    add_action('wp_ajax_devents_delete_video', 'devents_ajax_delete_video_callback');
}

// ============================================================
// CALLBACKI
// ============================================================

function devents_ajax_login_callback() {
    check_ajax_referer('devents_login_nonce', 'nonce');
    $user = wp_authenticate(sanitize_text_field($_POST['user_login']), $_POST['user_pass']);

    if (is_wp_error($user)) {
        wp_send_json_error(['message' => $user->get_error_message()]);
    }
    
    $creds = ['user_login' => sanitize_text_field($_POST['user_login']), 'user_password' => $_POST['user_pass'], 'remember' => isset($_POST['rememberme']) && $_POST['rememberme'] === 'true'];
    $user_signon = wp_signon($creds, is_ssl());

    if (is_wp_error($user_signon)) {
        wp_send_json_error(['message' => 'Błąd logowania. Spróbuj ponownie.']);
    }

    wp_send_json_success();
}

/**
 * Rejestruje nowego użytkownika przez AJAX i wysyła e-mail weryfikacyjny.
 * Wywoływana przez żądanie z register-form.js.
 */
function devents_ajax_register_user_callback() {
    // 1. Bezpieczeństwo i walidacja
    check_ajax_referer( 'devents_register_nonce', '_wpnonce' );

    $email = sanitize_email( $_POST['email'] ?? '' );
    if ( ! is_email( $email ) ) {
        wp_send_json_error( [ 'message' => 'Proszę podać poprawny adres e-mail.' ] );
    }
    if ( email_exists( $email ) ) {
        wp_send_json_error( [ 'message' => 'Ten adres e-mail jest już zarejestrowany. <a href="/zaloguj-sie">Zaloguj się</a>.' ] );
    }
    if ( empty( $_POST['password'] ) || $_POST['password'] !== $_POST['confirm_password'] ) {
        wp_send_json_error( [ 'message' => 'Hasła nie są identyczne lub są puste.' ] );
    }
    if ( ! isset( $_POST['accept_policy'] ) ) {
        wp_send_json_error( [ 'message' => 'Akceptacja regulaminu jest obowiązkowa.' ] );
    }

    // 2. Tworzenie użytkownika (bez zmian)
    $email = sanitize_email( $_POST['email'] ?? '' );
    $user_id = wp_create_user( $email, $_POST['password'], $email );
    if ( is_wp_error( $user_id ) ) {
        wp_send_json_error( [ 'message' => $user_id->get_error_message() ] );
    }

    // 3. Ustawienie roli i przygotowanie danych
    $role = sanitize_key( $_POST['role'] ?? 'subscriber' );
    $wp_user = new WP_User( $user_id );
    $wp_user->set_role( $role );

    // Inicjalizujemy "paczkę" danych, która przechowa wszystkie niestandardowe informacje
    $data_packet = [];

    if (isset($_FILES['logo_upload']) && $_FILES['logo_upload']['error'] === UPLOAD_ERR_OK) {
        $uploader = new DEvents_File_Uploader($user_id);
        $upload_result = $uploader->handle_upload('logo_upload');
        
        if (!is_wp_error($upload_result)) {
            $data_packet['logo_url'] = $upload_result;
        }
    }

    // --- ZAPIS DANYCH W ZALEŻNOŚCI OD ROLI ---
    if ( $role === 'organizer' ) {
        $org_name = sanitize_text_field( $_POST['org_name'] ?? '' );
        
        // Uzupełniamy naszą paczkę o resztę danych organizatora
        $data_packet['org_name']    = $org_name;
        $data_packet['org_nip']     = sanitize_text_field( $_POST['org_nip'] ?? '' );
        $data_packet['org_address'] = sanitize_text_field( $_POST['org_address'] ?? '' );
        $data_packet['org_phone']   = sanitize_text_field( $_POST['org_phone'] ?? '' );
        $data_packet['org_website'] = esc_url_raw( $_POST['org_website'] ?? '' );
        $data_packet['org_email']   = sanitize_email( $_POST['org_email'] ?? '' );
        $data_packet['coordinator'] = [
            'first_name' => sanitize_text_field( $_POST['coordinator_first_name'] ?? '' ),
            'last_name'  => sanitize_text_field( $_POST['coordinator_last_name'] ?? '' ),
            'email'      => sanitize_email( $_POST['coordinator_email'] ?? '' ),
            'phone'      => sanitize_text_field( $_POST['coordinator_phone'] ?? '' ),
        ];

        // Ustawiamy nazwę wyświetlaną na nazwę organizacji
        wp_update_user( [ 'ID' => $user_id, 'display_name' => $org_name ] );

    } else { // Dla subscribera
        $first_name = sanitize_text_field( $_POST['name'] ?? '' );
        $last_name = sanitize_text_field( $_POST['surname'] ?? '' );

        // Zapisujemy imię i nazwisko do standardowych pól WordPressa
        update_user_meta( $user_id, 'first_name', $first_name );
        update_user_meta( $user_id, 'last_name', $last_name );
        
        if (!empty($first_name) || !empty($last_name)) {
            wp_update_user( [ 'ID' => $user_id, 'display_name' => trim( "$first_name $last_name" ) ] );
        }
    }
    
    // Zapisujemy całą paczkę danych (z logo i danymi org, lub tylko z logo dla subscribera)
    update_user_meta( $user_id, '_devents_organizer_data', $data_packet );
    
    // 4. Przygotowanie do weryfikacji e-mail
    update_user_meta( $user_id, 'is_verified', 0 );
    $token = wp_generate_password( 32, false );
    update_user_meta( $user_id, 'activation_token', $token );
    update_user_meta( $user_id, 'activation_token_expiry', time() + 4 * HOUR_IN_SECONDS );

    $verification_link = add_query_arg( [ 'action' => 'devents_verify', 'token' => $token, 'user_id' => $user_id ], home_url() );
    $display_name_for_email = ($role === 'organizer') ? ($organizer_data['org_name'] ?: 'Organizatorze') : ($first_name ?: 'Użytkowniku');
    $email_data = [ 'first_name' => $display_name_for_email, 'verification_link' => $verification_link ];
    
    devents_send_email( $email, 'user_verification', $email_data );

    // 5. Zwrócenie odpowiedzi o sukcesie
    $success_message = '<strong>Dziękujemy za rejestrację!</strong> Na podany przez Ciebie adres e-mail wysłaliśmy link aktywacyjny. Sprawdź swoją skrzynkę odbiorczą (oraz folder SPAM/Oferty). Link będzie aktywny przez 4 godziny.';
    wp_send_json_success( [ 'message' => $success_message ] );
}

// Funkcja do weryfikacji
function devents_ajax_verify_material_callback() {
    check_ajax_referer('devents_admin_general_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Brak uprawnień.');
    
    $material_id = isset($_POST['material_id']) ? intval($_POST['material_id']) : 0;
    if (!$material_id) wp_send_json_error('Brak ID materiału.');

    global $wpdb;
    $wpdb->update($wpdb->prefix . 'events_materials', ['verified' => 1], ['id' => $material_id]);
    
    wp_send_json_success(['message' => 'Materiał zweryfikowany.']);
}

// Funkcja do publikacji (poprawiona wersja Twojej)
function devents_ajax_publish_material_callback() {
    check_ajax_referer('devents_admin_general_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Brak uprawnień.');

    $material_id = isset($_POST['material_id']) ? intval($_POST['material_id']) : 0;
    if (!$material_id) wp_send_json_error(['message' => 'Brak ID materiału.']);
    
    // Wywołujemy Twoją istniejącą, globalną funkcję
    $result = devents_publish_material_to_post($material_id);

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }
    
    wp_send_json_success(['message' => 'Materiał opublikowany jako wpis.']);
}

function devents_publish_material_to_post($material_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'events_materials';
    $material = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $material_id));
    
    if (!$material) {
        return new WP_Error('not_found', 'Materiał o podanym ID nie został znaleziony.');
    }

    // Tworzymy unikalny slug w formacie 'tytul-id'
    $slug = sanitize_title($material->title) . '-' . $material->id;
    $post_content = '[video id="' . $material->id . '"]';
    
    $post_data = [
        'post_title'   => $material->title,
        'post_content' => $post_content,
        'post_name'    => $slug,
        'post_status'  => 'publish',
        'post_type'    => 'materials',
        'post_author'  => $material->user_id,
        'meta_input'   => ['_material_id' => $material_id],
    ];

    // Sprawdzamy, czy wpis już istnieje
    $existing_post_query = new WP_Query([
        'post_type'      => 'materials',
        'meta_key'       => '_material_id',
        'meta_value'     => $material_id,
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'post_status'    => 'any', // Sprawdzaj wszystkie statusy
    ]);

    if ( $existing_post_query->have_posts() ) {
        // Jeśli wpis istnieje, aktualizujemy go
        $post_data['ID'] = $existing_post_query->posts[0];
        $result = wp_update_post($post_data, true);
    } else {
        // Jeśli nie, tworzymy nowy
        $result = wp_insert_post($post_data, true);
    }

    return $result;
}

// Funkcja do usuwania
function devents_ajax_delete_material_callback() {
    check_ajax_referer('devents_admin_general_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Brak uprawnień.');
    
    $material_id = isset($_POST['material_id']) ? intval($_POST['material_id']) : 0;
    if (!$material_id) wp_send_json_error('Brak ID materiału.');

    global $wpdb;
    $wpdb->delete($wpdb->prefix . 'events_materials', ['id' => $material_id], ['%d']);

    wp_send_json_success(['message' => 'Materiał usunięty.']);
}

/**
 * Poprawiony handler dla pojedynczych akcji na wydarzeniach.
 */
function devents_event_single_action_callback() {
    $event_id    = intval($_POST['event_id'] ?? 0);
    $action_type = sanitize_key($_POST['action_type'] ?? '');
    $nonce       = sanitize_text_field($_POST['nonce'] ?? '');

    // Poprawna weryfikacja unikalnego nonce dla każdej akcji
    if ( ! wp_verify_nonce( $nonce, $action_type . '_event_action_' . $event_id ) ) {
        wp_send_json_error(['message' => 'Błąd weryfikacji bezpieczeństwa (nonce).']);
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Brak uprawnień.']);
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'events_list';
    
    switch ( $action_type ) {
        case 'delete':
            if ($wpdb->delete($table_name, ['id' => $event_id], ['%d'])) {
                wp_send_json_success(['message' => 'Wydarzenie usunięte.']);
            }
            break;
        case 'verify':
            if ($wpdb->update($table_name, ['verified' => 1], ['id' => $event_id], ['%d'], ['%d']) !== false) {
                devents_publish_event_to_post($event_id); // Publikujemy posta
                wp_send_json_success(['message' => 'Wydarzenie zweryfikowane i opublikowane.']);
            }
            break;
        case 'unverify':
            if ($wpdb->update($table_name, ['verified' => 0], ['id' => $event_id], ['%d'], ['%d']) !== false) {
                wp_send_json_success(['message' => 'Cofnięto weryfikację.']);
            }
            break;
        case 'publish':
            devents_publish_event_to_post($event_id);
            wp_send_json_success(['message' => 'Wydarzenie opublikowane.']);
            break;
    }
    wp_send_json_error(['message' => 'Nieznana lub nieudana akcja.']);
}

function devents_bulk_event_action_callback() {
    check_ajax_referer('devents_bulk_action_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Brak uprawnień.']);
    
    $action = sanitize_key($_POST['bulk_action']);
    $event_ids = array_map('intval', (array)$_POST['event_ids']);
    if (empty($event_ids)) wp_send_json_error(['message' => 'Nie wybrano wydarzeń.']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'events_list';
    $results = ['success_count' => 0, 'error_count' => 0];

    foreach ($event_ids as $id) {
        $result = false;
        if ($action === 'delete_bulk') $result = $wpdb->delete($table_name, ['id' => $id], ['%d']);
        elseif ($action === 'verify_bulk') $result = $wpdb->update($table_name, ['verified' => 1], ['id' => $id], ['%d'], ['%d']);
        elseif ($action === 'unverify_bulk') $result = $wpdb->update($table_name, ['verified' => 0], ['id' => $id], ['%d'], ['%d']);
        $result ? $results['success_count']++ : $results['error_count']++;
    }
    wp_send_json_success(['message' => 'Akcja zbiorcza zakończona.', 'results' => $results]);
}

function devents_get_graphic_callback() {
    // Sprawdzenie nonce jest kluczowe dla bezpieczeństwa
    check_ajax_referer('devents_graphic_view_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Brak uprawnień.']);
        // wp_die() jest zbędne po wp_send_json_*
    }

    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    if (!$event_id) {
        wp_send_json_error(['message' => 'Brak ID wydarzenia.']);
    }

    global $wpdb, $twig; // Upewnij się, że masz dostęp do globalnej instancji Twig

    $table_e = $wpdb->prefix . 'events_list';
    $table_u = $wpdb->prefix . 'users';

    // Pobieramy display_name, tak jak w liście wydarzeń
    $event = $wpdb->get_row($wpdb->prepare("
        SELECT e.*, u.display_name
        FROM {$table_e} e
        LEFT JOIN {$table_u} u ON e.user_id = u.ID
        WHERE e.id = %d
    ", $event_id));

    if (!$event) {
        wp_send_json_error(['message' => 'Nie znaleziono wydarzenia.']);
    }

    // --- KLUCZOWA ZMIANA: Zbieranie danych do kontekstu Twig ---
    $context = [];
    $context['event'] = $event; // Przekaż cały obiekt, jest przydatny
    $context['category'] = sanitize_text_field($event->category);
    $context['title'] = sanitize_text_field($event->title);
    $context['date'] = date_i18n('d.m.Y', strtotime($event->start_datetime));
    $context['time'] = date_i18n('H:i', strtotime($event->start_datetime));
    $context['is_online'] = in_array(strtolower($event->delivery_mode), ['on-line', 'hybrydowo'], true);
    $context['logo_url'] = DEW_PLUGIN_URL . 'assets/images/logo-white.png'; // Upewnij się, że stała jest zdefiniowana
    $context['image_url'] = $event->image_url ?? '';

    // Logika ceny
    if (isset($event->price) && is_numeric($event->price) && $event->price > 0) {
        $context['price_label'] = $event->price . ' zł';
        $context['price_class'] = 'paid';
    } else {
        $context['price_label'] = 'Bezpłatnie';
        $context['price_class'] = 'free';
    }

    // Logika organizatora - ujednolicona z listą
    $organizer = '';
    if (!empty($event->other_organizer)) { // Kolumna 'organizator' z tabeli events_list
        $organizer = sanitize_text_field($event->other_organizer);
    } elseif (!empty($event->display_name)) { // display_name z wp_users
        $organizer = sanitize_text_field($event->display_name);
    }
    $context['organizer'] = $organizer;

    $raw_accessibility = $event->accessibility ?? '';
    $accessibility_array = [];

    $json_decode = json_decode($raw_accessibility, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($json_decode)) {
        $accessibility_array = array_map('sanitize_text_field', $json_decode);
    } else {
        $parts = array_map('trim', explode(',', $raw_accessibility));
        foreach ($parts as $part) {
            if ($part !== '') {
                $accessibility_array[] = sanitize_text_field($part);
            }
        }
    }
    $context['access'] = $accessibility_array;

    try {
        $html = DEvents_Twig_Helper::get_instance()->render('admin/generate-graphic', $context);

        // Wyślij wyrenderowany HTML w ustrukturyzowanej odpowiedzi JSON
        wp_send_json_success(['html' => $html]);

    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Błąd renderowania szablonu grafiki: ' . $e->getMessage()]);
    }
}


function devents_ajax_delete_user() {
    check_ajax_referer('devents_ajax_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Brak uprawnień.']);
    if ( empty( $_POST['user_id'] ) ) wp_send_json_error(['message' => 'Brak ID użytkownika.']);
    
    require_once(ABSPATH.'wp-admin/includes/user.php');
    $user_id = intval($_POST['user_id']);
    
    if ( get_current_user_id() == $user_id ) wp_send_json_error(['message' => "Nie możesz usunąć własnego konta."]);

    if ( wp_delete_user( $user_id ) ) {
        wp_send_json_success( ['message' => 'Użytkownik usunięty.'] );
    } else {
        wp_send_json_error( ['message' => 'Błąd przy usuwaniu użytkownika.'] );
    }
}

function devents_ajax_verify_user() {
    check_ajax_referer('devents_ajax_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Brak uprawnień.');
    if ( empty( $_POST['user_id'] ) ) wp_send_json_error( 'Brak ID użytkownika.' );
    
    $user_id = intval($_POST['user_id']);
    if (update_user_meta($user_id, 'devents_verified_status', '1')) {
        $user = get_userdata($user_id);
        if ($user) {
            wp_mail($user->user_email, 'Twoje konto zostało zweryfikowane – DEvents', 'Witaj, Twoje konto na portalu DEvents zostało pomyślnie zweryfikowane.', ['Content-Type: text/html; charset=UTF-8']);
        }
        wp_send_json_success(['message' => 'Użytkownik zweryfikowany.']);
    }
    wp_send_json_error(['message' => 'Błąd weryfikacji.']);
}

function devents_ajax_bulk_user_action() {
    check_ajax_referer('devents_ajax_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Brak uprawnień.');
    if ( empty( $_POST['bulk_action'] ) || empty( $_POST['user_ids'] ) ) wp_send_json_error( 'Brak danych.' );
    
    $action = sanitize_key( $_POST['bulk_action'] );
    $ids = array_map( 'intval', (array) $_POST['user_ids'] );
    $results = [];
    foreach ($ids as $id) {
        if ($action === 'delete_bulk' && get_current_user_id() != $id) {
            require_once(ABSPATH.'wp-admin/includes/user.php');
            $results[$id] = wp_delete_user($id);
        }
    }
    wp_send_json_success($results);
}

/**
 * Aktualizuje dane konta użytkownika przez AJAX.
 * Obsługuje dane tekstowe oraz przesyłanie i usuwanie logo dla obu ról.
 * Wersja ostateczna.
 */
function devents_ajax_update_account_callback() {
    if (!is_user_logged_in()) { 
        wp_send_json_error(['message' => 'Brak uprawnień.']); 
    }
    
    $user_id = get_current_user_id();
    check_ajax_referer('devents-update-account-' . $user_id, '_wpnonce');

    // Pobieramy istniejącą "paczkę" danych, aby ją zaktualizować.
    $data_packet = get_user_meta($user_id, '_devents_organizer_data', true);
    if (!is_array($data_packet)) { 
        $data_packet = []; 
    }

    // --- LOGIKA OBSŁUGI LOGO ---
    if (isset($_POST['delete_logo']) && $_POST['delete_logo'] === 'true') {
        $data_packet['logo_url'] = ''; 
    }
    if (isset($_FILES['logo_upload']) && $_FILES['logo_upload']['error'] === UPLOAD_ERR_OK) {
        $uploader = new DEvents_File_Uploader($user_id);
        $upload_result = $uploader->handle_upload('logo_upload');
        if (is_wp_error($upload_result)) {
            wp_send_json_error(['message' => $upload_result->get_error_message()]);
        }
        $data_packet['logo_url'] = $upload_result;
    }

    // --- AKTUALIZACJA DANYCH ---
    $user_core_data = ['ID' => $user_id];
    if (user_can($user_id, 'organizer')) {
        // Zapisujemy wszystkie pola tekstowe organizacji do paczki
        $data_packet['org_name']            = sanitize_text_field($_POST['org_name'] ?? '');
        $data_packet['org_description']     = sanitize_textarea_field($_POST['org_description'] ?? ''); // POPRAWIONY PRZECINEK
        $data_packet['org_nip']             = sanitize_text_field($_POST['org_nip'] ?? '');
        $data_packet['org_address']         = sanitize_text_field($_POST['org_address'] ?? '');
        $data_packet['org_phone']           = sanitize_text_field($_POST['org_phone'] ?? '');
        $data_packet['org_website']         = esc_url_raw($_POST['org_website'] ?? '');
        $data_packet['org_email']           = sanitize_email($_POST['org_email'] ?? '');
        $data_packet['coordinator']         = [
            'first_name' => sanitize_text_field($_POST['coordinator_first_name'] ?? ''),
            'last_name'  => sanitize_text_field($_POST['coordinator_last_name'] ?? ''),
            'email'      => sanitize_email($_POST['coordinator_email'] ?? ''),
            'phone'      => sanitize_text_field($_POST['coordinator_phone'] ?? ''),
        ];
        
        $user_core_data['first_name'] = $data_packet['coordinator']['first_name'];
        $user_core_data['last_name'] = $data_packet['coordinator']['last_name'];
        if (!empty($data_packet['org_name'])) {
            $user_core_data['display_name'] = $data_packet['org_name'];
        }
    } else {
        $user_core_data['first_name'] = sanitize_text_field($_POST['first_name'] ?? '');
        $user_core_data['last_name']  = sanitize_text_field($_POST['last_name'] ?? '');
        $display_name = trim($user_core_data['first_name'] . ' ' . $user_core_data['last_name']);
        if (!empty($display_name)) {
            $user_core_data['display_name'] = $display_name;
        }
    }
    
    update_user_meta($user_id, '_devents_organizer_data', $data_packet);
    if (count($user_core_data) > 1) {
        wp_update_user($user_core_data);
    }

    wp_send_json_success([
        'message' => 'Dane zaktualizowane pomyślnie.',
        'new_logo_url' => $data_packet['logo_url'] ?? ''
    ]);
}

function devents_ajax_change_password_callback() {
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Brak uprawnień.']);
    $user_id = get_current_user_id();
    check_ajax_referer('devents-change-password-' . $user_id, '_wpnonce_pass');
    
    $user = get_user_by('id', $user_id);
    if (!wp_check_password($_POST['current_pass'], $user->user_pass, $user_id)) {
        wp_send_json_error(['message' => 'Bieżące hasło jest nieprawidłowe.']);
    }
    if (empty($_POST['pass1']) || $_POST['pass1'] !== $_POST['pass2']) {
        wp_send_json_error(['message' => 'Nowe hasła są puste lub nie są identyczne.']);
    }
    wp_set_password($_POST['pass1'], $user_id);
    wp_clear_auth_cookie();
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id);
    wp_send_json_success(['message' => 'Hasło zmienione.']);
}

function devents_ajax_delete_account_callback() {
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Brak uprawnień.']);
    $user_id = get_current_user_id();
    check_ajax_referer('devents-delete-account-' . $user_id, '_wpnonce_delete');
    
    $user = get_user_by('id', $user_id);
    if (!wp_check_password($_POST['delete_pass'], $user->user_pass, $user_id)) {
        wp_send_json_error(['message' => 'Podane hasło jest nieprawidłowe. Operacja anulowana.']);
    }
    
    require_once(ABSPATH.'wp-admin/includes/user.php');
    if (wp_delete_user($user_id)) {
        wp_send_json_success(['message' => 'Konto zostało usunięte.']);
    } else {
        wp_send_json_error(['message' => 'Nie udało się usunąć konta. Skontaktuj się z administratorem.']);
    }
}

function devents_publish_event_to_post($event_id) {
    global $wpdb;
    $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}events_list WHERE id = %d", $event_id));
    if (!$event) return new WP_Error('not_found', 'Event not found.');

    $post_content = '[wydarzenie id="' . intval($event->id) . '"]';

    $post_data = [
        'post_title'   => $event->title,
        'post_content' => $post_content,
        'post_status'  => 'publish',
        'post_type'    => 'wydarzenia', // musi być post type wydarzenia!
        'post_author'  => $event->user_id,
        'meta_input'   => ['_event_id' => $event_id],
    ];

    $existing_post = get_posts([
        'post_type'  => 'wydarzenia',
        'meta_key'   => '_event_id',
        'meta_value' => $event_id,
        'posts_per_page' => 1,
        'fields'     => 'ids',
    ]);

    if ($existing_post) {
        $post_data['ID'] = $existing_post[0];
        $updated_post_id = wp_update_post($post_data, true);
        if (is_wp_error($updated_post_id)) {
            return $updated_post_id;
        }
        // Ustaw slug przy aktualizacji
        $slug = sanitize_title($event->title) . '-' . $event_id;
        wp_update_post(['ID' => $updated_post_id, 'post_name' => $slug]);
        return $updated_post_id;

    } else {
        $new_post_id = wp_insert_post($post_data, true);
        if (is_wp_error($new_post_id)) {
            return $new_post_id;
        }
        // Ustaw slug przy tworzeniu
        $slug = sanitize_title($event->title) . '-' . $event_id;
        wp_update_post(['ID' => $new_post_id, 'post_name' => $slug]);
        return $new_post_id;
    }
}

function devents_ajax_password_reset_callback() {
    check_ajax_referer('devents_reset_nonce', 'nonce');
    $step = isset($_POST['step']) ? sanitize_key($_POST['step']) : '';
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
    $user = get_user_by('email', $email);

    switch ($step) {
        case 'send_code':
            if ($user) {
                $reset_code = str_pad(wp_rand(0, 999999), 6, '0', STR_PAD_LEFT);
                update_user_meta($user->ID, '_password_reset_code', password_hash($reset_code, PASSWORD_DEFAULT));
                update_user_meta($user->ID, '_password_reset_expiry', time() + 15 * MINUTE_IN_SECONDS);
                devents_send_email($email, 'password_reset_code', ['reset_code' => $reset_code]);
            }
            wp_send_json_success(['message' => 'Jeśli konto o podanym adresie e-mail istnieje, wysłaliśmy na nie kod do resetu hasła.']);
            break;

        case 'validate_code':
            $is_valid = $user && get_user_meta($user->ID, '_password_reset_expiry', true) > time() && password_verify($code, get_user_meta($user->ID, '_password_reset_code', true));
            if (!$is_valid) {
                wp_send_json_error(['message' => 'Nieprawidłowy lub wygasły kod.']);
            }
            wp_send_json_success(['message' => 'Kod poprawny.']);
            break;

        case 'set_password':
            $pass1 = isset($_POST['pass1']) ? $_POST['pass1'] : '';
            $pass2 = isset($_POST['pass2']) ? $_POST['pass2'] : '';
            $is_valid = $user && password_verify($code, get_user_meta($user->ID, '_password_reset_code', true));

            if (!$is_valid) {
                wp_send_json_error(['message' => 'Błąd weryfikacji. Spróbuj od nowa.']);
            }
            if (empty($pass1) || $pass1 !== $pass2) {
                wp_send_json_error(['message' => 'Hasła nie są identyczne lub są puste.']);
            }

            reset_password($user, $pass1);
            delete_user_meta($user->ID, '_password_reset_code');
            delete_user_meta($user->ID, '_password_reset_expiry');
            wp_send_json_success(['message' => 'Hasło zostało pomyślnie zmienione.']);
            break;

        default:
            wp_send_json_error(['message' => 'Nieznana akcja.']);
    }
}

function devents_generate_unique_digit_code() {
    $attempts = 0;
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= rand(0, 9);
        }
        $digits = str_split($code);
        $counts = array_count_values($digits);
        $duplicates = array_filter($counts, fn($v) => $v > 1);
        $maxDuplicates = max($duplicates ?? [0]);
        $attempts++;
    } while ($maxDuplicates > 2 && $attempts < 20);

    return $code;
}

function devents_ajax_duplicate_event_callback() {
    try {
        global $wpdb;

        // 1. Bezpieczeństwo i walidacja
        check_ajax_referer('devents_duplicate_nonce', '_wpnonce');
        if ( ! is_user_logged_in() ) {
            wp_send_json_error(['message' => 'Musisz być zalogowany.']);
        }

        $event_id_to_duplicate = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        if ( empty($event_id_to_duplicate) ) {
            wp_send_json_error(['message' => 'Brak ID wydarzenia do skopiowania.']);
        }

        $table_name = $wpdb->prefix . 'events_list';
        $current_user_id = get_current_user_id();

        // 2. Pobieramy oryginalne wydarzenie
        $original_event = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $event_id_to_duplicate), ARRAY_A );

        // 3. Weryfikacja uprawnień
        if ( ! $original_event ) {
            wp_send_json_error(['message' => 'Wydarzenie nie istnieje.']);
        }
        if ( $original_event['user_id'] != $current_user_id && !current_user_can('manage_options') ) {
            wp_send_json_error(['message' => 'Brak uprawnień do skopiowania tego wydarzenia.']);
        }

        // 4. Przygotowujemy dane dla kopii
        $new_event_data = $original_event;
        
        unset($new_event_data['id']);
        if (isset($new_event_data['created_at'])) {
            unset($new_event_data['created_at']);
        }

        $new_event_data['title'] = $original_event['title'] . ' (Kopia)';
        $new_event_data['verified'] = 2; // Status: "Wersja robocza"

        // ====================================================================
        // KLUCZOWA ZMIANA: Tworzymy tablicę formatów dla każdej kolumny
        // %s - string (tekst), %d - integer (liczba całkowita), %f - float (liczba zmiennoprzecinkowa)
        // ====================================================================
        $data_formats = [
            'user_id'                 => '%d',
            'title'                   => '%s',
            'organizator'             => '%s',
            'category'                => '%s',
            'delivery_mode'           => '%s',
            'accessibility'           => '%s',
            'location'                => '%s',
            'image_url'               => '%s',
            'image_alt_text'          => '%s',
            'description'             => '%s',
            'video_url'               => '%s',
            'start_datetime'          => '%s', // Daty traktujemy jako string
            'end_datetime'            => '%s',
            'price'                   => '%f', // Cena jako float
            'ticket'                  => '%s',
            'action_button_enabled'   => '%d',
            'action_button_text'      => '%s',
            'action_button_link'      => '%s',
            'verified'                => '%d',
        ];

        // 5. Wstawiamy nowy wiersz do bazy, przekazując tablicę z formatami
        $result = $wpdb->insert($table_name, $new_event_data, $data_formats);

        if ($result === false) {
            wp_send_json_error(['message' => 'Błąd bazy danych podczas tworzenia kopii: ' . $wpdb->last_error]);
        }

        // 6. Wysyłamy odpowiedź o sukcesie
        wp_send_json_success(['message' => 'Wydarzenie zostało pomyślnie skopiowane! Strona zostanie odświeżona.']);

    } catch (Throwable $e) {
        // Blok przechwytujący każdy inny możliwy błąd krytyczny
        wp_send_json_error([
            'message' => 'Wystąpił błąd krytyczny PHP: ' . $e->getMessage()
        ]);
    }
}

/**
 * Zapisuje lub aktualizuje wydarzenie przez AJAX.
 */
function devents_ajax_save_event_callback() {
    // 1. Bezpieczeństwo i walidacja uprawnień
    check_ajax_referer('devents_save_event_nonce', '_wpnonce');
    if (!is_user_logged_in() || get_user_meta(get_current_user_id(), 'is_verified', true) !== '1' && !current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Nie masz wystarczających uprawnień, aby zapisać to wydarzenie.']);
    }

    // 2. Pobranie i odkażenie danych z formularza
    $event_data = [];
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    
    // Lista pól do przetworzenia
    $fields = ['title', 'category', 'delivery_mode', 'accessibility', 'location', 'image_url', 'image_alt_text', 'organizator', 'description', 'start_datetime', 'end_datetime', 'price', 'ticket', 'action_button_enabled', 'action_button_text', 'action_button_link'];
    
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            // accessibility jest tablicą, reszta to tekst
            if ($field === 'accessibility' && is_array($_POST[$field])) {
                $event_data[$field] = implode(', ', array_map('sanitize_text_field', $_POST[$field]));
            } else {
                $event_data[$field] = sanitize_textarea_field(wp_unslash($_POST[$field]));
            }
        }
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'events_list';
    $current_user_id = get_current_user_id();

    // 3. Logika: Aktualizacja czy Tworzenie nowego?
    if ($event_id > 0) {
        // --- AKTUALIZACJA ISTNIEJĄCEGO WYDARZENIA ---
        // Sprawdzamy, czy użytkownik jest właścicielem wydarzenia
        $owner_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$table_name} WHERE id = %d", $event_id));
        if ($owner_id != $current_user_id && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Błąd: Nie możesz edytować wydarzenia, którego nie jesteś autorem.']);
        }
        $event_data['verified'] = 3;
        $result = $wpdb->update($table_name, $event_data, ['id' => $event_id]);
        
        if ($result === false) {
            wp_send_json_error(['message' => 'Błąd bazy danych podczas aktualizacji wydarzenia.']);
        }
        wp_send_json_success(['message' => 'Wydarzenie zostało pomyślnie zaktualizowane!']);

    } else {
        // --- TWORZENIE NOWEGO WYDARZENIA ---
        $event_data['user_id'] = $current_user_id;
        $event_data['verified'] = 0; // Nowe wydarzenia wymagają weryfikacji

        $result = $wpdb->insert($table_name, $event_data);
        
        if ($result === false) {
            wp_send_json_error(['message' => 'Błąd bazy danych podczas tworzenia wydarzenia.']);
        }
        wp_send_json_success(['message' => 'Wydarzenie zostało dodane i czeka na weryfikację administratora.']);
    }
}

/**
 * Zapisuje lub aktualizuje film przez AJAX, włącznie z obsługą uploadu miniaturki.
 */
function devents_ajax_save_video_callback() {
    // 1. Bezpieczeństwo: Sprawdź token nonce i uprawnienia użytkownika.
    check_ajax_referer('devents_save_video_nonce', '_wpnonce');

    if ( !is_user_logged_in() ) {
        wp_send_json_error(['message' => 'Musisz być zalogowany, aby wykonać tę akcję.']);
    }
    // Dodatkowe sprawdzenie, czy konto jest zweryfikowane
    if ( get_user_meta(get_current_user_id(), 'is_verified', true) !== '1' && !current_user_can('manage_options') ) {
        wp_send_json_error(['message' => 'Twoje konto musi być zweryfikowane, aby dodawać treści.']);
    }

    // 2. Pobranie i odkażenie danych z formularza
    $video_id = isset($_POST['video_id']) ? intval($_POST['video_id']) : 0;
    
    // Walidacja pól wymaganych
    if (empty($_POST['title'])) {
        wp_send_json_error(['message' => 'Tytuł jest polem wymaganym.']);
    }
    if (empty($_POST['video_url'])) {
        wp_send_json_error(['message' => 'Link do wideo jest polem wymaganym.']);
    }
    if (empty($_POST['description'])) {
        wp_send_json_error(['message' => 'Opis jest polem wymaganym.']);
    }

    // Przygotowanie tablicy z danymi do zapisu
    $data = [
        'title'           => sanitize_text_field($_POST['title']),
        'description'     => sanitize_textarea_field($_POST['description']),
        'video_url'       => esc_url_raw($_POST['video_url']),
        'category'        => sanitize_text_field($_POST['category'] ?? ''),
        'duration'        => isset($_POST['duration']) && is_numeric($_POST['duration']) ? intval($_POST['duration']) : null,
        'series_name'     => sanitize_text_field($_POST['series_name'] ?? ''),
        'author'          => sanitize_text_field($_POST['author'] ?? ''),
        'translator'      => sanitize_text_field($_POST['translator'] ?? ''),
        'publish_date'    => !empty($_POST['publish_date']) ? sanitize_text_field($_POST['publish_date']) : null,
        'accessibility'   => isset($_POST['accessibility']) ? json_encode(array_map('sanitize_text_field', $_POST['accessibility'])) : '',
    ];

    // 3. Logika obsługi przesyłania pliku (miniaturki) i czasu
    if (isset($_FILES['thumbnail_upload']) && $_FILES['thumbnail_upload']['error'] === UPLOAD_ERR_OK) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        // Bezpieczna obsługa uploadu przez WordPress
        $attachment_id = media_handle_upload('thumbnail_upload', 0);
        
        if (is_wp_error($attachment_id)) {
            wp_send_json_error(['message' => 'Błąd podczas przesyłania pliku: ' . $attachment_id->get_error_message()]);
        } else {
            // Zapisujemy URL do wgranego obrazka
            $data['thumbnail_url'] = wp_get_attachment_url($attachment_id);
        }
    }

    if (empty($duration) && !empty($video_url)) {
        $auto_duration = devents_get_video_duration_from_url($video_url);
        if ($auto_duration) {
            $data['duration'] = $auto_duration;
        }
    } else {
        $data['duration'] = $duration;
    }
    
    $data['video_url'] = $video_url;

    global $wpdb;
    $table_name = $wpdb->prefix . 'events_materials';
    $current_user_id = get_current_user_id();

    // 4. Decyzja: Aktualizacja istniejącego wpisu CZY tworzenie nowego
    if ($video_id > 0) {
        $owner_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$table_name} WHERE id = %d", $video_id));
        if ($owner_id != $current_user_id && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Brak uprawnień do edycji tego materiału.']);
        }
        $data['verified'] = 3; 
        $result = $wpdb->update($table_name, $data, ['id' => $video_id]);
        
        if ($result === false) {
            wp_send_json_error(['message' => 'Błąd bazy danych podczas aktualizacji filmu.']);
        }
        wp_send_json_success(['message' => 'Film został pomyślnie zaktualizowany.']);

    } else {
        // --- TWORZENIE NOWEGO ---
        $data['user_id'] = $current_user_id;
        $data['verified'] = 0; // Nowe filmy zawsze trafiają do weryfikacji

        $result = $wpdb->insert($table_name, $data);
        
        if ($result === false) {
            wp_send_json_error(['message' => 'Błąd bazy danych podczas tworzenia filmu.']);
        }
        wp_send_json_success(['message' => 'Film został pomyślnie dodany i czeka na weryfikację administratora.']);
    }
}

/**
 * Duplikuje materiał wideo przez AJAX.
 */
function devents_ajax_duplicate_video_callback() {
    check_ajax_referer('devents_duplicate_video_nonce', '_wpnonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message' => 'Brak uprawnień.']); }

    $video_id = isset($_POST['video_id']) ? intval($_POST['video_id']) : 0;
    if (empty($video_id)) { wp_send_json_error(['message' => 'Nieprawidłowe ID materiału.']); }

    global $wpdb;
    $table_name = $wpdb->prefix . 'events_materials';
    $current_user_id = get_current_user_id();

    $original_video = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $video_id), ARRAY_A);

    if (!$original_video || $original_video['user_id'] != $current_user_id) {
        wp_send_json_error(['message' => 'Nie masz uprawnień do skopiowania tego materiału.']);
    }

    $new_video_data = $original_video;
    unset($new_video_data['id']);
    $new_video_data['title'] = $original_video['title'] . ' (Kopia)';
    $new_video_data['verified'] = 2; // Status: "Wersja robocza"

    if ($wpdb->insert($table_name, $new_video_data)) {
        wp_send_json_success(['message' => 'Film został pomyślnie skopiowany.']);
    } else {
        wp_send_json_error(['message' => 'Błąd bazy danych podczas tworzenia kopii.']);
    }
}

/**
 * Usuwa materiał wideo przez AJAX.
 */
function devents_ajax_delete_video_callback() {
    check_ajax_referer('devents_delete_video_nonce', '_wpnonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message' => 'Brak uprawnień.']); }

    $video_id = isset($_POST['video_id']) ? intval($_POST['video_id']) : 0;
    if (empty($video_id)) { wp_send_json_error(['message' => 'Nieprawidłowe ID materiału.']); }

    global $wpdb;
    $table_name = $wpdb->prefix . 'events_materials';
    $current_user_id = get_current_user_id();

    $owner_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$table_name} WHERE id = %d", $video_id));

    if (!$owner_id || ($owner_id != $current_user_id && !current_user_can('manage_options'))) {
        wp_send_json_error(['message' => 'Nie masz uprawnień do usunięcia tego materiału.']);
    }

    if ($wpdb->delete($table_name, ['id' => $video_id], ['%d'])) {
        wp_send_json_success(['message' => 'Film został pomyślnie usunięty.']);
    } else {
        wp_send_json_error(['message' => 'Błąd bazy danych podczas usuwania filmu.']);
    }
}

function devents_get_video_duration_from_url($url) {
    if (empty($url)) {
        return null;
    }

    $oembed_url = '';

    // Sprawdzamy, czy to link do YouTube
    if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
        $oembed_url = 'https://www.youtube.com/oembed?url=' . esc_url($url) . '&format=json';
    } 
    // Sprawdzamy, czy to link do Vimeo
    elseif (strpos($url, 'vimeo.com') !== false) {
        $oembed_url = 'https://vimeo.com/api/oembed.json?url=' . esc_url($url);
    }

    if (empty($oembed_url)) {
        return null;
    }

    $response = wp_remote_get($oembed_url);

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return null;
    }

    $data = json_decode(wp_remote_retrieve_body($response));

    if ($data && isset($data->duration) && is_numeric($data->duration)) {
        // Konwertujemy sekundy na format H:i:s
        return gmdate("H:i:s", $data->duration);
    }

    return null;
}
