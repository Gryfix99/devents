<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class DEvents_Admin_Menu {
    
    private $twig;

    /**
     * Inicjalizuje Twig i rejestruje menu.
     */
    public function __construct() {
        // Inicjalizujemy Twig raz, aby był dostępny dla wszystkich metod tej klasy
        if ( class_exists('\Twig\Loader\FilesystemLoader') ) {
            try {
                $loader = new \Twig\Loader\FilesystemLoader( DEW_PLUGIN_PATH . 'templates' );
                $this->twig = new \Twig\Environment($loader, ['cache' => false]);

                // Dodajemy kluczowe funkcje WordPressa do Twig
                $this->add_wordpress_functions_to_twig();

            } catch(Exception $e) {
                wp_die('Błąd inicjalizacji Twig: ' . esc_html($e->getMessage()));
            }
        } else {
             wp_die('Klasa Twig nie została znaleziona. Uruchom `composer install`.');
        }
    }
    
    /**
     * Dodaje popularne funkcje WordPressa do środowiska Twig.
     */
    private function add_wordpress_functions_to_twig() {
        if ( ! $this->twig ) return;
        
        $this->twig->addFunction( new \Twig\TwigFunction('admin_url', function($path = '') {
            return admin_url($path);
        }) );

        $this->twig->addFunction( new \Twig\TwigFunction('wp_nonce_field', function($action = -1, $name = '_wpnonce', $referer = true, $echo = true) {
            return wp_nonce_field($action, $name, $referer, false);
        }, ['is_safe' => ['html']]) );
    }

    /**
     * Rejestruje wszystkie strony w menu administratora.
     */
    public function register_menu() {
        add_menu_page( 'DEvents Manager', 'DEvents Manager', 'manage_options', 'devents-manager', [$this, 'render_events_list_page'], 'dashicons-calendar-alt', 6 );
        add_submenu_page( 'devents-manager', 'Wydarzenia', 'Wydarzenia', 'manage_options', 'events_list', [$this, 'render_events_list_page'] );
        add_submenu_page( 'devents-manager', 'Materiały', 'Materiały', 'manage_options', 'materials_list', [$this, 'render_materials_list_page'] );
        add_submenu_page( 'devents-manager', 'Użytkownicy', 'Użytkownicy', 'manage_options', 'users-list', [$this, 'render_users_list_page'] );
        add_submenu_page( 'devents-manager', 'Ustawienia', 'Ustawienia', 'manage_options', 'admin-settings', [$this, 'render_settings_page'] );
        
        // Ukryte strony
        add_submenu_page( null, 'Edytuj wydarzenie', 'Edytuj wydarzenie', 'manage_options', 'event_edit', [$this, 'render_event_edit_page'] );
        add_submenu_page( null, 'Edytuj materiał', 'Edytuj materiał', 'manage_options', 'material_edit', [$this, 'render_material_edit_page'] );
        add_submenu_page( null, 'Edytuj użytkownika', 'Edytuj użytkownika', 'manage_options', 'user_edit', [$this, 'render_user_edit_page'] );
    }

    /**
     * Renderuje stronę z listą wydarzeń.
     */
    public function render_events_list_page() {
        global $twig;
        $twig = $this->twig;
        include DEW_PLUGIN_PATH . 'admin/list-events.php';
    }

    /**
     * Renderuje stronę edycji wydarzenia.
     */
    public function render_event_edit_page() {
        global $twig;
        $twig = $this->twig;
        include DEW_PLUGIN_PATH . 'admin/event-edit.php';
    }
    
    /**
     * Renderuje stronę ustawień.
     */
    public function render_settings_page() {
        require_once DEW_PLUGIN_PATH . 'admin/settings.php';
        if (function_exists('devents_settings_page_callback')) {
            devents_settings_page_callback();
        }
    }
    
    // Pozostałe metody renderujące
    public function render_materials_list_page() { include DEW_PLUGIN_PATH . 'admin/materials-list.php'; }
    public function render_users_list_page() { include DEW_PLUGIN_PATH . 'admin/users-list.php'; }
    public function render_material_edit_page() { include DEW_PLUGIN_PATH . 'admin/material-edit.php'; }
    public function render_user_edit_page() { include DEW_PLUGIN_PATH . 'admin/user-edit.php'; }
}
