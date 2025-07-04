<?php
/**
 * Plik obsługujący procesy uwierzytelniania:
 * Logowanie (synchroniczne), Weryfikację konta po kliknięciu w link, CRON do czyszczenia kont
 * oraz zabezpieczenia.
 *
 * UWAGA: Obsługa REJESTRACJI została przeniesiona do pliku ajax-handlers.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}


/**
 * =========================================================================
 * GŁÓWNA FUNKCJA OBSŁUGUJĄCA FORMULARZE SYNCHRONICZNE
 * =========================================================================
 * Podpięta do haka 'init', obecnie obsługuje tylko logowanie.
 */
function devents_handle_auth_actions() {
	global $devents_form_errors;
	$devents_form_errors = []; // Resetujemy błędy na starcie

	// --- OBSŁUGA LOGOWANIA ---
	if ( isset( $_POST['devents_action'] ) && $_POST['devents_action'] === 'login' && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'devents-login' ) ) {

		// Sprawdzamy, czy kluczowe pola istnieją, aby uniknąć błędów
		if ( ! isset( $_POST['devents_user_login'] ) || ! isset( $_POST['devents_user_pass'] ) ) {
			$devents_form_errors['login'] = 'Wystąpił błąd formularza. Spróbuj ponownie.';
			return;
		}
		
		$creds = [
			'user_login'    => sanitize_text_field( $_POST['devents_user_login'] ),
			'user_password' => $_POST['devents_user_pass'], // Hasła nie sanitujemy, wp_signon robi to bezpiecznie
			'remember'      => isset( $_POST['devents_rememberme'] ),
		];
		
		$user = wp_signon( $creds, is_ssl() );

		if ( is_wp_error( $user ) ) {
			error_log( 'Błąd logowania DEvents: ' . $user->get_error_message() );
			// Przekazujemy komunikat błędu do formularza
			$devents_form_errors['login'] = 'Dane logowania są błędne.';
		} else {
			// Logowanie udane, przekierowujemy do panelu użytkownika
			wp_safe_redirect( home_url( '/panel-uzytkownika/' ) );
			exit;
		}
		return; // Kończymy, aby nie przetwarzać dalej
	}

    // Blok rejestracji został celowo usunięty, ponieważ jest teraz obsługiwany przez AJAX.
}
add_action( 'init', 'devents_handle_auth_actions' );


/**
 * =========================================================================
 * FUNKCJE POMOCNICZE I ZABEZPIECZAJĄCE
 * =========================================================================
 */

/**
 * Obsługuje kliknięcie w link aktywacyjny w e-mailu.
 */
function devents_handle_account_verification() {
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'devents_verify' && isset( $_GET['token'] ) && isset( $_GET['user_id'] ) ) {
        $user_id = absint( $_GET['user_id'] );
        $token   = sanitize_text_field( $_GET['token'] );
        $user    = get_userdata( $user_id );

        if ( ! $user || get_user_meta( $user_id, 'activation_token', true ) !== $token || time() > get_user_meta( $user_id, 'activation_token_expiry', true ) ) {
            wp_die( 'Błąd: Link aktywacyjny jest nieprawidłowy lub wygasł. Prosimy o ponowną rejestrację.', 'Błąd aktywacji' );
        }

        update_user_meta( $user_id, 'is_verified', 1 );
        delete_user_meta( $user_id, 'activation_token' );
        delete_user_meta( $user_id, 'activation_token_expiry' );

        wp_set_current_user( $user_id, $user->user_login );
        wp_set_auth_cookie( $user_id, true );

        wp_safe_redirect( home_url( '/panel-uzytkownika/?rejestracja=sukces' ) );
        exit;
    }
}
add_action( 'wp_loaded', 'devents_handle_account_verification' );

/**
 * ZABEZPIECZENIE: Uniemożliwia logowanie użytkownikom, którzy nie zweryfikowali adresu e-mail.
 */
function devents_prevent_unverified_login( $user ) {
    // Jeśli już jest błąd (np. złe hasło), nic nie rób
    if ( is_wp_error( $user ) ) {
        return $user;
    }

    // Sprawdzamy nasz status weryfikacji
    $is_verified = get_user_meta( $user->ID, 'is_verified', true );
    
    // Jeśli status nie jest '1', blokujemy i zwracamy własny błąd
    if ( $is_verified !== '1' ) {
        return new WP_Error(
            'account_not_verified',
            '<strong>Błąd:</strong> Twoje konto nie zostało jeszcze aktywowane. Sprawdź swoją skrzynkę e-mail i kliknij w link weryfikacyjny.'
        );
    }

    return $user;
}
add_filter( 'wp_authenticate_user', 'devents_prevent_unverified_login', 10, 1 );


/**
 * =========================================================================
 * FUNKCJE ZADANIA CRON
 * =========================================================================
 */

/**
 * Usuwa z bazy danych konta, które nie zostały zweryfikowane w wyznaczonym czasie.
 */
function devents_cleanup_unverified_users_callback() {
    $args = [
        'meta_query' => [
            'relation' => 'AND',
            [ 'key' => 'is_verified', 'value' => '0', 'compare' => '=' ],
            [ 'key' => 'activation_token_expiry', 'value' => time(), 'compare' => '<', 'type' => 'NUMERIC' ],
        ],
        'fields'     => 'ID',
    ];
    $unverified_user_ids = get_users( $args );
    if ( ! empty( $unverified_user_ids ) ) {
        require_once( ABSPATH . 'wp-admin/includes/user.php' );
        foreach ( $unverified_user_ids as $user_id ) {
            wp_delete_user( $user_id );
        }
    }
}
add_action( 'devents_cleanup_unverified_users', 'devents_cleanup_unverified_users_callback' );

/**
 * Rejestruje cykliczne zadanie CRON.
 */
function devents_schedule_cleanup_cron() {
    if ( ! wp_next_scheduled( 'devents_cleanup_unverified_users' ) ) {
        wp_schedule_event( time(), 'hourly', 'devents_cleanup_unverified_users' );
    }
}
add_action( 'init', 'devents_schedule_cleanup_cron' );

/**
 * Usuwa wydarzenie przez AJAX na podstawie podanego ID.
 */
function devents_ajax_delete_event_callback() {
    // 1. Bezpieczeństwo: Sprawdzamy token nonce i uprawnienia użytkownika.
    check_ajax_referer('devents_delete_nonce', '_wpnonce');

    if ( ! is_user_logged_in() ) {
        wp_send_json_error(['message' => 'Musisz być zalogowany, aby wykonać tę akcję.']);
    }

    $event_id_to_delete = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    if ( empty($event_id_to_delete) ) {
        wp_send_json_error(['message' => 'Nieprawidłowe ID wydarzenia.']);
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'events_list';
    $current_user_id = get_current_user_id();

    // 2. Pobieramy ID właściciela wydarzenia, aby zweryfikować uprawnienia.
    $event_owner_id = $wpdb->get_var( $wpdb->prepare("SELECT user_id FROM {$table_name} WHERE id = %d", $event_id_to_delete) );

    // 3. Sprawdzamy, czy użytkownik jest właścicielem wydarzenia lub administratorem.
    if ( ! $event_owner_id || ($event_owner_id != $current_user_id && !current_user_can('manage_options')) ) {
        wp_send_json_error(['message' => 'Nie masz uprawnień do usunięcia tego wydarzenia.']);
    }

    // 4. Usuwamy wydarzenie z bazy danych.
    $result = $wpdb->delete($table_name, ['id' => $event_id_to_delete], ['%d']);

    if ($result === false) {
        wp_send_json_error(['message' => 'Wystąpił błąd bazy danych podczas usuwania wydarzenia.']);
    }

    // 5. Wysyłamy odpowiedź o sukcesie.
    wp_send_json_success(['message' => 'Wydarzenie zostało pomyślnie usunięte.']);
}

