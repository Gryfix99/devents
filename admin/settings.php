<?php
/**
 * Plik zawierający logikę i widok dla strony "Ustawienia DEvents".
 * Wersja z dwukolumnowym layoutem i poprawionym usuwaniem.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Obsługuje zapis i usuwanie danych z formularza na stronie ustawień.
 */
function devents_handle_settings_form() {
    if ( ! current_user_can('manage_options') ) return;

    if ( isset($_POST['devents_add_setting']) && check_admin_referer('devents_add_setting_nonce') ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'events_settings';
        $type = isset($_POST['setting_type']) ? sanitize_key($_POST['setting_type']) : '';
        $value = isset($_POST['setting_value']) ? sanitize_text_field($_POST['setting_value']) : '';

        if ( !empty($type) && !empty($value) ) {
            $wpdb->insert($table_name, ['setting_type' => $type, 'setting_value' => $value]);
            wp_safe_redirect(admin_url('admin.php?page=admin-settings&message=1'));
            exit;
        }
    }


    if ( isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['setting_id']) ) {
        // Poprawiona, bardziej niezawodna weryfikacja nonce dla akcji GET
        if ( !isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'devents_delete_setting_' . $_GET['setting_id']) ) {
            wp_die('Błąd zabezpieczeń. Spróbuj ponownie.');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'events_settings';
        $id_to_delete = intval($_GET['setting_id']);
        $wpdb->delete($table_name, ['id' => $id_to_delete], ['%d']);

        wp_safe_redirect(admin_url('admin.php?page=admin-settings&message=2'));
        exit;
    }
}
add_action('admin_init', 'devents_handle_settings_form');

/**
 * Główna funkcja renderująca zawartość strony ustawień.
 */
function devents_settings_page_callback() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Nie masz uprawnień.' );

    global $wpdb;
    $table_name = $wpdb->prefix . 'events_settings';
    
    // Pobieramy wszystkie ustawienia
    $all_settings_raw = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY setting_value ASC");
    
    // Dzielimy je na dwie główne grupy: dla wydarzeń i dla filmów
    $event_settings = [];
    $video_settings = [];

    if ($all_settings_raw) {
        foreach ($all_settings_raw as $setting) {
            if (strpos($setting->setting_type, 'event_') === 0) {
                $event_settings[$setting->setting_type][] = $setting;
            } elseif (strpos($setting->setting_type, 'video_') === 0) {
                $video_settings[$setting->setting_type][] = $setting;
            }
        }
    }

    // Przygotowanie komunikatu o sukcesie
    $message_code = isset($_GET['message']) ? intval($_GET['message']) : 0;
    $success_message = '';
    if ($message_code === 1) $success_message = 'Nowe ustawienie zostało dodane.';
    if ($message_code === 2) $success_message = 'Ustawienie zostało usunięte.';
    
    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'events';

    // Renderowanie szablonu z nowymi danymi
    $twig_helper = DEvents_Twig_Helper::get_instance();
    echo $twig_helper->render( 'admin/settings-page', [
        'event_settings' => $event_settings,
        'video_settings' => $video_settings,
        'success_message' => $success_message,
        'page_url' => admin_url('admin.php?page=admin-settings'),
        'active_tab' => $active_tab, // Przekazujemy informację o aktywnej zakładce
    ]);
}