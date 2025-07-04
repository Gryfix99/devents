<?php
/**
 * Logika strony edycji wydarzenia w panelu admina.
 * Przygotowuje dane i renderuje szablon Twig.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Brak uprawnień do zarządzania tą stroną.' );
}

global $wpdb;
// --- KLUCZOWA ZMIANA: Pobieramy jedną, poprawnie skonfigurowaną instancję Twig ---
$twig = DEvents_Twig_Helper::get_instance();

// Pobranie ID wydarzenia
$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$event_id) {
    echo '<div class="wrap"><div class="notice notice-error"><p>Nieprawidłowy ID wydarzenia.</p></div></div>';
    return;
}

// Pobranie danych wydarzenia z bazy
$event = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}events_list WHERE id = %d", $event_id));
if (!$event) {
    echo '<div class="wrap"><div class="notice notice-error"><p>Nie znaleziono wydarzenia.</p></div></div>';
    return;
}

// Pobieranie opcji dla selectów
$table_settings = $wpdb->prefix . 'event_settings';
$categories = $wpdb->get_col("SELECT DISTINCT event_category FROM {$table_settings} WHERE event_category IS NOT NULL AND event_category != ''");
$methods = $wpdb->get_col("SELECT DISTINCT event_method FROM {$table_settings} WHERE event_method IS NOT NULL AND event_method != ''");
$accessibility_options = $wpdb->get_col("SELECT DISTINCT event_accessibility FROM {$table_settings} WHERE event_accessibility IS NOT NULL AND event_accessibility != ''");

// Przygotowanie danych do przekazania do szablonu
global $devents_form_errors;
$data_for_twig = [
    'event'                 => $event,
    'event_categories'      => $categories,
    'event_methods'         => $methods,
    'accessibility_options' => $accessibility_options,
    'accessibility_selected' => !empty($event->accessibility) ? array_map('trim', explode(',', $event->accessibility)) : [],
    'nonce_field'           => wp_nonce_field('devents_edit_event_action_' . $event_id, '_wpnonce', true, false),
    'success_message'       => isset($_GET['updated']) && $_GET['updated'] === 'true' ? 'Wydarzenie zostało pomyślnie zaktualizowane.' : '',
    'error_messages'        => $devents_form_errors['edit_event'] ?? [],
];

// Renderowanie szablonu
echo $twig->render('admin/edit-event', $data_for_twig);
