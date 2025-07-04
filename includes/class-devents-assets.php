<?php
/**
 * Klasa DEvents_Assets
 * Zarządza dołączaniem stylów i skryptów wtyczki.
 * Wersja ostateczna.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class DEvents_Assets {

    private $version = '5.2';

    public function __construct() {
        // Celowo puste, cała logika jest w hakach.
    }

    /**
     * Rejestruje i dołącza skrypty oraz style dla panelu administratora.
     */
    public function enqueue_admin_assets( $hook ) {
        $get_page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        
        $is_plugin_page = strpos($hook, 'devents-manager') !== false || in_array($get_page, [
            'events_list', 'event_add', 'event_edit', 'materials_list', 'material_add', 'material_edit',
            'users-list', 'user_edit', 'admin-settings', 'generate_event_image'
        ]);

        if ( !$is_plugin_page ) return;

        // Style
        wp_enqueue_style('devents-admin-style', DEW_PLUGIN_URL . 'assets/css/admin/admin.css', [], $this->version);
        wp_enqueue_style('material-symbols-outlined', 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined');

        // Skrypty
        wp_enqueue_script('jquery');
        wp_register_script('html2canvas', 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js', [], '1.4.1', true);

        // Centralny obiekt konfiguracyjny dla wszystkich skryptów admina
        $admin_config_data = [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('devents_admin_general_nonce'),
        ];
        
        // --- Ładowanie warunkowe ---
        
        // Strony z listami
        if ( in_array($get_page, ['events_list', 'materials_list', 'users-list']) ) {
            $script_handle = 'devents-admin-' . str_replace('_list', '', $get_page); // np. devents-admin-events
            wp_enqueue_script($script_handle, DEW_PLUGIN_URL . 'assets/js/admin/' . str_replace('_', '-', $get_page) . '.js', ['jquery'], $this->version, true);
            wp_localize_script($script_handle, 'devents_admin_config', $admin_config_data);
        }

        // Strony z formularzami
        if ( in_array($get_page, ['event_add', 'event_edit', 'material_add', 'material_edit']) ) {
            $this->enqueue_form_libraries();
            wp_enqueue_script('devents-admin-form-js', DEW_PLUGIN_URL . 'assets/js/admin/admin-form.js', ['jquery', 'choices-js', 'flatpickr-js', 'easymde-js'], $this->version, true);
            wp_localize_script('devents-admin-form-js', 'devents_admin_config', $admin_config_data);
        }

        // Generator grafik
        if ($get_page === 'generate_event_image') {
            wp_enqueue_script('devents-graphic-generator', DEW_PLUGIN_URL . 'assets/js/admin/graphic-generator.js', ['jquery', 'html2canvas'], $this->version, true);
        }
    }

    /**
     * Rejestruje i dołącza skrypty oraz style dla frontendu wtyczki.
     */
    public function enqueue_frontend_assets() {
        // --- Zasoby globalne ---
        wp_enqueue_script('jquery');
        wp_enqueue_style('devents-google-fonts-dosis', 'https://fonts.googleapis.com/css2?family=Dosis:wght@300;400;700&display=swap');
        wp_enqueue_style('material-symbols-outlined', 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined');
        wp_enqueue_style('devents-frontend-style', DEW_PLUGIN_URL . 'assets/css/frontend/frontend.css', [], $this->version);
        wp_enqueue_script('zxcvbn', 'https://cdnjs.cloudflare.com/ajax/libs/zxcvbn/4.4.2/zxcvbn.js', [], '4.4.2', true);

        // --- Centralny obiekt konfiguracyjny dla JS ---
        wp_localize_script('jquery', 'devents_config', [
            'ajax_url'              => admin_url('admin-ajax.php'),
            'home_url'              => home_url(),
            'register_nonce'        => wp_create_nonce('devents_register_nonce'),
            'duplicate_event_nonce' => wp_create_nonce('devents_duplicate_nonce'),
            'delete_event_nonce'    => wp_create_nonce('devents_delete_nonce'),
            'save_event_nonce'      => wp_create_nonce('devents_save_event_nonce'),
            'duplicate_video_nonce' => wp_create_nonce('devents_duplicate_video_nonce'),
            'delete_video_nonce'    => wp_create_nonce('devents_delete_video_nonce'),
            'save_video_nonce'      => wp_create_nonce('devents_save_video_nonce'),
        ]);

        // --- Zasoby ładowane warunkowo ---
        global $post;
        if (!is_a($post, 'WP_Post')) return;

        // Formularz rejestracji
        if ( has_shortcode($post->post_content, 'register_form') ) {
            wp_enqueue_script('devents-register-form', DEW_PLUGIN_URL . 'assets/js/frontend/register-form.js', ['jquery', 'zxcvbn'], $this->version, true);
        }

        // Wyszukiwarki
        if ( has_shortcode($post->post_content, 'event_search') ) {
            $this->enqueue_form_libraries();
            wp_enqueue_script('devents-event-search', DEW_PLUGIN_URL . 'assets/js/frontend/event-search.js', ['jquery', 'choices-js', 'flatpickr-js'], $this->version, true);
        }
        if ( has_shortcode($post->post_content, 'videos_search') || has_shortcode($post->post_content, 'video_search') ) {
            $this->enqueue_form_libraries();
            wp_enqueue_script('devents-video-search', DEW_PLUGIN_URL . 'assets/js/frontend/video-search.js', ['jquery', 'choices-js'], $this->version, true);
        }

        // Panel użytkownika i jego podstrony
        if ( has_shortcode( $post->post_content, 'devents_user_panel' ) ) {
            wp_enqueue_style('devents-panel-style', DEW_PLUGIN_URL . 'assets/css/frontend/users/my-panel.css', [], $this->version);
            wp_enqueue_script('devents-panel-nav', DEW_PLUGIN_URL . 'assets/js/frontend/my-panel.js', ['jquery'], $this->version, true);
            wp_enqueue_script('devents-my-account', DEW_PLUGIN_URL . 'assets/js/frontend/my-account.js', ['jquery', 'zxcvbn'], $this->version, true);
            wp_enqueue_script('devents-my-events', DEW_PLUGIN_URL . 'assets/js/frontend/my-events.js', ['jquery'], $this->version, true);
            wp_enqueue_script('devents-my-videos', DEW_PLUGIN_URL . 'assets/js/frontend/my-videos.js', ['jquery'], $this->version, true);
            
            $this->enqueue_form_libraries();
            wp_enqueue_script('devents-publish-event', DEW_PLUGIN_URL . 'assets/js/frontend/publish-event.js', ['jquery', 'flatpickr-js', 'choices-js', 'easymde-js'], $this->version, true);
            wp_enqueue_script('devents-publish-video', DEW_PLUGIN_URL . 'assets/js/frontend/publish-video.js', ['jquery', 'flatpickr-js', 'choices-js', 'easymde-js'], $this->version, true);
        }
    }

    /**
     * Funkcja pomocnicza do ładowania bibliotek dla złożonych formularzy.
     */
    private function enqueue_form_libraries() {
        wp_enqueue_style('choices-css', 'https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css');
        wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');
        wp_enqueue_style('easymde-css', 'https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css');
        
        wp_enqueue_script('choices-js', 'https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js', [], null, true);
        wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', [], null, true);
        wp_enqueue_script('flatpickr-pl', 'https://npmcdn.com/flatpickr/dist/l10n/pl.js', ['flatpickr-js'], null, true);
        wp_enqueue_script('easymde-js', 'https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js', [], null, true);
    }
}