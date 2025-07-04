<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Zbiór funkcji callback dla akcji AJAX związanych z wydarzeniami.
 */

// Rejestracja hooków AJAX
add_action( 'wp_ajax_handle_event_action', 'devents_handle_event_action_callback' );
add_action( 'wp_ajax_bulk_event_action', 'devents_bulk_event_action_callback' );
add_action( 'wp_ajax_load_event_graphic', 'devents_load_event_graphic_callback' );


/**
 * Obsługuje pojedyncze akcje na wydarzeniach (weryfikuj, usuń, etc.).
 * To jest bardziej ogólna funkcja, która może zastąpić wiele mniejszych.
 */
function devents_handle_event_action_callback() {
    // Podstawowe sprawdzenie bezpieczeństwa
    if ( ! isset( $_POST['action_type'], $_POST['event_id'], $_POST['nonce'] ) ) {
        wp_send_json_error( 'Brak wymaganych danych.' );
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Brak uprawnień.' );
    }

    $action = sanitize_key( $_POST['action_type'] );
    $event_id = intval( $_POST['event_id'] );
    $nonce = sanitize_text_field( $_POST['nonce'] );

    // Weryfikacja nonce
    if ( ! wp_verify_nonce( $nonce, $action . '_event_action' ) ) {
        wp_send_json_error( 'Błąd weryfikacji (nonce). Spróbuj odświeżyć stronę.' );
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'events_list';
    
    switch ( $action ) {
        case 'delete':
            $deleted = $wpdb->delete( $table_name, ['id' => $event_id], ['%d'] );
            if ( $deleted ) {
                wp_send_json_success( ['message' => 'Wydarzenie usunięte.'] );
            } else {
                wp_send_json_error( 'Nie udało się usunąć wydarzenia.' );
            }
            break;

        case 'verify':
            $updated = $wpdb->update( $table_name, ['verified' => 1], ['id' => $event_id], ['%d'], ['%d'] );
             if ( $updated !== false ) {
                wp_send_json_success( ['message' => 'Wydarzenie zweryfikowane.'] );
            } else {
                wp_send_json_error( 'Nie udało się zweryfikować wydarzenia.' );
            }
            break;

        case 'unverify':
            $updated = $wpdb->update( $table_name, ['verified' => 0], ['id' => $event_id], ['%d'], ['%d'] );
            if ( $updated !== false ) {
                wp_send_json_success( ['message' => 'Cofnięto weryfikację.'] );
            } else {
                wp_send_json_error( 'Nie udało się cofnąć weryfikacji.' );
            }
            break;

        case 'publish':
            // Logika publikowania (tworzenie/aktualizacja posta CPT)
            // ... (tutaj powinna znaleźć się logika tworzenia posta, podobna do tej z materiałów)
            wp_send_json_success( ['message' => 'Wydarzenie opublikowane (logika do implementacji).'] );
            break;

        default:
            wp_send_json_error( 'Nieznana akcja.' );
    }
}


/**
 * Obsługuje akcje zbiorcze na wydarzeniach.
 */
function devents_bulk_event_action_callback() {
    if ( ! isset( $_POST['bulk_action'], $_POST['event_ids'], $_POST['nonce'] ) ) {
        wp_send_json_error( 'Brak wymaganych danych.' );
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Brak uprawnień.' );
    }
    if ( ! wp_verify_nonce( sanitize_text_field($_POST['nonce']), 'bulk_event_action' ) ) {
        wp_send_json_error( 'Błąd weryfikacji (nonce).' );
    }

    $action = sanitize_key( $_POST['bulk_action'] );
    $event_ids = array_map( 'intval', (array) $_POST['event_ids'] );

    if ( empty( $event_ids ) ) {
        wp_send_json_error( 'Nie wybrano żadnych wydarzeń.' );
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'events_list';
    $results = ['success_count' => 0, 'error_count' => 0];

    foreach( $event_ids as $id ) {
        $result = false;
        if ( $action === 'delete_bulk' ) {
            $result = $wpdb->delete( $table_name, ['id' => $id], ['%d'] );
        } elseif ( $action === 'verify_bulk' ) {
            $result = $wpdb->update( $table_name, ['verified' => 1], ['id' => $id], ['%d'], ['%d'] );
        } elseif ( $action === 'unverify_bulk' ) {
            $result = $wpdb->update( $table_name, ['verified' => 0], ['id' => $id], ['%d'], ['%d'] );
        }

        if ( $result !== false ) {
            $results['success_count']++;
        } else {
            $results['error_count']++;
        }
    }

    wp_send_json_success( [
        'message' => 'Akcja zbiorcza zakończona.',
        'results' => $results
    ] );
}

/**
 * Ładuje dane do wygenerowania grafiki i zwraca HTML.
 */
function devents_load_event_graphic_callback() {
    if ( ! isset( $_POST['event_id'], $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'load_event_graphic_action' ) ) {
        wp_send_json_error( 'Błąd bezpieczeństwa.' );
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Brak uprawnień.' );
    }
    
    $event_id = intval($_POST['event_id']);
    
    global $wpdb;
    $table_e = $wpdb->prefix . 'events_list';
    $table_u = $wpdb->prefix . 'events_users';

    $event = $wpdb->get_row( $wpdb->prepare(
        "SELECT e.*, u.org_name FROM {$table_e} e LEFT JOIN {$table_u} u ON e.user_id = u.id WHERE e.id = %d",
        $event_id
    ) );

    if ( ! $event ) {
        wp_send_json_error( 'Nie znaleziono wydarzenia.' );
    }

    // Prosty przykład zwracanego HTML
    // W rzeczywistości tutaj mogłaby być bardziej złożona logika generowania grafiki (np. z użyciem biblioteki GD lub ImageMagick)
    // lub przygotowanie danych dla biblioteki JS
    $html = '<h3>' . esc_html($event->title) . '</h3>';
    $html .= '<p><strong>Organizator:</strong> ' . esc_html($event->org_name ?? 'Brak') . '</p>';
    $html .= '<p><strong>Data:</strong> ' . date_i18n( 'd F Y, H:i', strtotime($event->start_datetime) ) . '</p>';
    $html .= '<p><strong>Lokalizacja:</strong> ' . esc_html($event->location) . '</p>';
    $html .= '<hr><p><em>To jest podgląd danych do grafiki.</em></p>';

    wp_send_json_success( ['html' => $html] );
}
