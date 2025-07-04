<?php
/**
 * Obsługuje logikę dla publicznych profili organizatorów.
 * Wersja ostateczna.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class DEvents_Public_Profiles_Handler {

    public function __construct() {
        add_action( 'init', [$this, 'register_rewrite_rules'] );
        add_filter( 'query_vars', [$this, 'register_query_vars'] );
        add_filter( 'template_include', [$this, 'render_organizer_profile_template'], 99 );
    }

    public function register_rewrite_rules() {
        add_rewrite_rule('^organizator/([0-9]+)/?$', 'index.php?organizer_id=$matches[1]', 'top');
    }

    public function register_query_vars( $vars ) {
        $vars[] = 'organizer_id';
        return $vars;
    }

    public function render_organizer_profile_template( $template ) {
        $organizer_id = get_query_var( 'organizer_id' );

        // Jeśli to nie jest strona profilu, nic nie robimy
        if ( ! $organizer_id ) {
            return $template;
        }

        $user_data = get_userdata( $organizer_id );

        // Jeśli użytkownik nie istnieje lub nie jest organizatorem, wyświetl stronę 404
        if ( ! $user_data || ! in_array( 'organizer', (array) $user_data->roles ) ) {
            global $wp_query;
            $wp_query->set_404();
            status_header( 404 );
            return get_404_template();
        }

        // --- Pobieranie danych ---
        global $wpdb;
        $org_data_packet = get_user_meta($organizer_id, '_devents_organizer_data', true) ?: [];
        $organizer_profile_data = [
            'name'        => $org_data_packet['org_name'] ?? $user_data->display_name,
            'description' => wpautop($org_data_packet['org_description'] ?? ''),
            'logo_url'    => $org_data_packet['logo_url'] ?? '',
            'website'     => $org_data_packet['org_website'] ?? '',
            'phone'       => $org_data_packet['org_phone'] ?? '',
            'email'       => $org_data_packet['org_email'] ?? '',
            'coordinator' => $org_data_packet['coordinator'] ?? [],
        ];

        $table_events = $wpdb->prefix . 'events_list';
        $organizer_events = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table_events} WHERE user_id = %d AND verified = 1 ORDER BY start_datetime ASC",
            $organizer_id
        ));

        // --- Renderowanie i wyświetlanie ---
        
        // Ustawiamy tytuł strony
        add_filter('pre_get_document_title', function() use ($organizer_profile_data) {
            return $organizer_profile_data['name'] . ' - Profil Organizatora';
        });

        // 1. Ręcznie ładujemy nagłówek Twojego motywu
        get_header();
        
        // 2. Tworzymy standardowe kontenery WordPressa na treść
        echo '<div id="primary" class="content-area"><main id="main" class="site-main">';

        // 3. Renderujemy nasz szablon Twig
        $twig_helper = DEvents_Twig_Helper::get_instance();
        echo $twig_helper->render( 'user/organizer-profile', [
            'organizer'  => $organizer_profile_data,
            'events'     => $organizer_events,
            'plugin_url' => DEW_PLUGIN_URL,
        ]);

        // 4. Zamykamy kontenery
        echo '</main></div>';

        // 5. Ręcznie ładujemy stopkę Twojego motywu
        get_footer();

        // 6. Zatrzymujemy dalsze działanie WordPressa, bo już wygenerowaliśmy całą stronę
        exit();
    }
}

new DEvents_Public_Profiles_Handler();