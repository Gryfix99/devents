<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

final class DEvents_Core {

    private static $instance = null;

    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->register_hooks();
    }

    /**
     * Ładuje wszystkie pliki i klasy wymagane przez wtyczkę.
     */
    private function load_dependencies() {        
        // Główne moduły wtyczki
        require_once DEW_PLUGIN_PATH . 'includes/class-devents-assets.php';
        require_once DEW_PLUGIN_PATH . 'includes/class-devents-shortcodes.php';
        require_once DEW_PLUGIN_PATH . 'admin/class-devents-admin-menu.php';
        require_once DEW_PLUGIN_PATH . 'includes/post-types.php';
        require_once DEW_PLUGIN_PATH . 'includes/ajax-handlers.php';
        require_once DEW_PLUGIN_PATH . 'includes/devents-helpers.php';
        require_once DEW_PLUGIN_PATH . 'emails/email-manager.php';
        require_once DEW_PLUGIN_PATH . 'includes/handlers/auth-handler.php';
        require_once DEW_PLUGIN_PATH . 'includes/handlers/public-profiles.php';
        require_once DEW_PLUGIN_PATH . 'includes/handlers/file-handler.php';
    }

    /**
     * Rejestruje wszystkie haki (actions i filters).
     */
    private function register_hooks() {
        // Haki ogólne
        add_action( 'init', [ $this, 'init_session' ] );
        add_action( 'init', 'devents_register_post_types' );
        add_action( 'plugins_loaded', [ $this, 'check_db_version' ] );

        // Zasoby (Skrypty i Style)
        $assets = new DEvents_Assets();
        add_action( 'wp_enqueue_scripts', [ $assets, 'enqueue_frontend_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $assets, 'enqueue_admin_assets' ] );

        // Menu i strony w panelu admina
        $admin_menu = new DEvents_Admin_Menu();
        add_action( 'admin_menu', [ $admin_menu, 'register_menu' ] );
        
        // Funkcje AJAX
        devents_register_ajax_handlers();

        // Używamy haka wp_footer, aby wstrzyknąć pozycje menu
        add_action( 'wp_footer', [ $this, 'inject_menu_items_with_script' ] );

        // Filtr do ukrywania belki administracyjnej
        add_filter('show_admin_bar', [$this, 'devents_hide_admin_bar_for_roles']);
    }

    /**
     * Callback dla filtra 'show_admin_bar'.
     * Ukrywa belkę administracyjną dla użytkowników o określonych rolach.
     *
     * @param bool $show_admin_bar Domyślny status widoczności belki (true/false).
     * @return bool Zmodyfikowany status widoczności.
     */
    public function devents_hide_admin_bar_for_roles($show_admin_bar) {
        if (!is_user_logged_in()) {
            return $show_admin_bar;
        }
        $current_user = wp_get_current_user();
        $roles_to_hide_bar = array('organizer', 'subscriber');
        $user_roles = (array) $current_user->roles;
        
        foreach ($user_roles as $role) {
            if (in_array($role, $roles_to_hide_bar)) {
                return false;
            }
        }
        return $show_admin_bar;
    }

    /**
     * Wstrzykuje pozycje menu do stopki strony wraz ze skryptem,
     * który przeniesie je do właściwego kontenera menu Elementora.
     */
    public function inject_menu_items_with_script() {
        $li_classes = 'menu-item menu-item-type-custom menu-item-object-custom menu-item-devents hfe-creative-menu';
        $a_classes = 'hfe-menu-item';
        $items_html = '';

        if ( is_user_logged_in() ) {
            $items_html .= '<li class="' . $li_classes . '"><a class="' . $a_classes . '" href="' . home_url('/panel-uzytkownika/') . '">Panel użytkownika</a></li>';
            $items_html .= '<li class="' . $li_classes . '"><a class="' . $a_classes . '" href="' . wp_logout_url(home_url()) . '">Wyloguj się</a></li>';
        } else {
            $items_html .= '<li class="' . $li_classes . '"><a class="' . $a_classes . '" href="' . home_url('/zaloguj-sie/') . '">Zaloguj się</a></li>';
            $items_html .= '<li class="' . $li_classes . '"><a class="' . $a_classes . '" href="' . home_url('/zarejestruj-sie/') . '">Zarejestruj się</a></li>';
        }

        echo '<div id="devents-menu-items-source" style="display: none;">' . $items_html . '</div>';
        
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const menuSelectors = ['#menu-1-58e3445', '.elementor-nav-menu > ul'];
                const sourceContainer = document.getElementById('devents-menu-items-source');
                if (sourceContainer) {
                    const itemsToInject = sourceContainer.innerHTML;
                    menuSelectors.forEach(function(selector) {
                        const targetMenus = document.querySelectorAll(selector);
                        targetMenus.forEach(function(menu) {
                            if (menu) {
                                menu.insertAdjacentHTML('beforeend', itemsToInject);
                            }
                        });
                    });
                }
            });
        </script>
        <?php
    }

    public function init_session() {
        if ( session_status() === PHP_SESSION_NONE && ! headers_sent() ) {
            session_start();
        }
    }

    public function check_db_version() {
        if (class_exists('RegisterTable')) {
            $register_table = new RegisterTable();
            $register_table->check_db_version();
        }
    }
}