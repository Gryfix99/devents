<?php
/**
 * Logika strony z listą wydarzeń w panelu administratora.
 *
 * Pobiera dane, obsługuje filtry i przekazuje je do szablonu Twig.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Brak uprawnień do zarządzania tą stroną.' );
}

global $wpdb, $twig;

// --- 1) Pobieranie parametrów (Filtry, sortowanie, paginacja) ---
$search    = isset( $_GET['s'] )         ? sanitize_text_field( $_GET['s'] ) : '';
$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
$date_to   = isset( $_GET['date_to'] )   ? sanitize_text_field( $_GET['date_to'] ) : '';
$orderby   = isset( $_GET['orderby'] )   ? sanitize_key( $_GET['orderby'] ) : 'id';
$order     = isset( $_GET['order'] )     ? strtoupper( sanitize_key( $_GET['order'] ) ) : 'asc';
$paged     = isset( $_GET['paged'] )     ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page  = 20; // Liczba elementów na stronę
$offset    = ( $paged - 1 ) * $per_page;

// --- 2) Budowa klauzuli WHERE ---
$where_clauses = [];
if ( $search ) {
    $like = '%' . $wpdb->esc_like( $search ) . '%';
    // Wyszukiwanie po nazwie organizatora z WP_users (display_name)
    $where_clauses[] = $wpdb->prepare(
        "(e.title LIKE %s OR e.category LIKE %s OR e.location LIKE %s OR u.display_name LIKE %s)",
        $like, $like, $like, $like
    );
}
if ( $date_from && ( $d = DateTime::createFromFormat( 'Y-m-d', $date_from ) ) ) {
    $where_clauses[] = $wpdb->prepare( "e.start_datetime >= %s", $d->format('Y-m-d').' 00:00:00' );
}
if ( $date_to && ( $d = DateTime::createFromFormat( 'Y-m-d', $date_to ) ) ) {
    $where_clauses[] = $wpdb->prepare( "e.start_datetime <= %s", $d->format('Y-m-d').' 23:59:59' );
}
$where_sql = $where_clauses ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';

// --- 3) Budowa klauzuli ORDER BY ---
$allowed_sort_columns = [
    'title'          => 'e.title',
    'start_datetime' => 'e.start_datetime',
    'id'             => 'e.id',
    'verified'       => 'e.verified'
];
$orderby_col = array_key_exists( $orderby, $allowed_sort_columns ) ? $allowed_sort_columns[$orderby] : 'e.created_at';
$order_dir = in_array( $order, ['ASC', 'DESC'] ) ? $order : 'DESC';
$order_sql = "
    ORDER BY
        CASE
            WHEN e.verified = 1 THEN 1
            ELSE 0
        END ASC,
        {$orderby_col} {$order_dir}
";


// --- 4) Nazwy tabel ---
$table_events = $wpdb->prefix . 'events_list';
$table_users  = $wpdb->prefix . 'users'; // Główna tabela WP użytkowników

// --- 5) Całkowita liczba rekordów do paginacji ---
// U.ID to standardowa kolumna ID w tabeli wp_users
$total_events_query = "SELECT COUNT(e.id) FROM {$table_events} e LEFT JOIN {$table_users} u ON e.user_id = u.ID {$where_sql}"; // Usunięto komentarz z końca linii
$total_events = (int) $wpdb->get_var( $total_events_query );
// Dodane logowanie dla debugowania
error_log("DEvents Debug: Total events query: " . $total_events_query);
error_log("DEvents Debug: Total events count: " . $total_events);

// --- 6) Pobranie danych wydarzeń ---
// Pobieramy display_name i user_login z tabeli wp_users
$events_query = $wpdb->prepare("
    SELECT e.*, u.display_name, u.user_login
    FROM {$table_events} e
    LEFT JOIN {$table_users} u ON e.user_id = u.ID
    {$where_sql} {$order_sql} LIMIT %d OFFSET %d
", $per_page, $offset );
// Dodane logowanie dla debugowania
error_log("DEvents Debug: Events data query: " . $events_query);
$events_data = $wpdb->get_results( $events_query );
error_log("DEvents Debug: Fetched events data raw: " . print_r($events_data, true)); // Pokaż surowe dane

// --- KLUCZOWA ZMIANA: ZASTOSOWANIE GLOBALNEGO UNLASHINGU PRZEZ HELPERA ---
// Zastosuj stripslashes do danych po pobraniu, używając funkcji pomocniczej
$events_data = devents_unslash_event_data($events_data);
error_log("DEvents Debug: Fetched events data unslashed: " . print_r($events_data, true)); // Pokaż dane po unslash


// --- 7) Przetwarzanie danych dla szablonu ---
$events = array_map(function($event) {
    global $wpdb; 
    
    $args = [
    'post_type'      => 'wydarzenia', // POPRAWKA: Szukamy we właściwym typie wpisu
    'post_status'    => 'publish',    // POPRAWKA: Interesują nas tylko opublikowane wpisy
    'posts_per_page' => 1,
    'fields'         => 'ids',        // Potrzebujemy tylko ID, to jest najszybsze
    'meta_query'     => [             // Ulepszona i bardziej elastyczna składnia
        [
            'key'     => '_event_id', // Szukamy po naszym niestandardowym polu...
            'value'   => $event->id,  // ...o wartości równej ID wydarzenia z Twojej tabeli.
            'compare' => '=',
        ]
        ]
    ];

    // Wykonujemy zapytanie
    $post_query = new WP_Query($args);

    // Sprawdzamy, czy znaleziono jakikolwiek pasujący wpis
    $is_published = $post_query->have_posts();

    // Pobieramy ID znalezionego wpisu (lub 0, jeśli nic nie znaleziono)
    $post_id = $is_published ? $post_query->posts[0] : 0;

    // Dodajemy nowe właściwości do naszego obiektu $event
    $event->is_published = $is_published;
    $event->post_url = $is_published ? get_permalink($post_id) : '#';
    $event->formatted_date = date_i18n( 'd.m.Y H:i', strtotime( $event->start_datetime ) );

    $event->org_name = $event->other_organizer;
    if ( empty($event->org_name) ) {
        $event->org_name = $event->display_name ?? $event->user_login ?? '<i>Brak</i>';
    }

    $status_info = [];
    switch ($event->verified) {
        case 1:
            $status_info['text'] = 'Zweryfikowane';
            $status_info['class'] = 'status-badge--published'; // Zielony
            break;
        case 2:
            $status_info['text'] = 'Wersja Robocza';
            $status_info['class'] = 'status-badge--draft'; // Niebieski
            break;
        case 3:
            $status_info['text'] = 'Do Ponownej Weryfikacji';
            $status_info['class'] = 'status-badge--updated'; // Żółty/Pomarańczowy
            break;
        case 0:
        default:
            $status_info['text'] = 'Oczekuje';
            $status_info['class'] = 'status-badge--pending'; // Czerwony
            break;
    }

    $event->status_info = $status_info;

    // Generowanie nonces - UNIKALNE DLA KAŻDEJ AKCJI I WYDARZENIA
    $event->nonces = [
        'delete'   => wp_create_nonce( 'delete_event_action_' . $event->id ),
        'verify'   => wp_create_nonce( 'verify_event_action_' . $event->id ),
        'unverify' => wp_create_nonce('unverify_event_action_' . $event->id),
        'publish'  => wp_create_nonce('publish_event_action_' . $event->id)
    ];
    return $event;
}, $events_data);


// --- 8) Przygotowanie kontekstu dla Twig ---
$context = [
    'add_new_url' => admin_url( 'admin.php?page=event_add' ),
    'filters' => [
        'action_url' => admin_url('admin.php'),
        'page'       => $_REQUEST['page'] ?? 'devents-manager',
        'search'     => $search,
        'date_from'  => $date_from,
        'date_to'    => $date_to,
        'orderby'    => $orderby,
        'order'      => $order,
        'clear_url'  => admin_url('admin.php?page=' . ($_REQUEST['page'] ?? 'devents-manager')),
    ],
    'pagination' => [
        'total_items' => $total_events,
        'links' => paginate_links([
            'base'      => add_query_arg( 'paged', '%#%' ),
            'format'    => '',
            'prev_text' => '«',
            'next_text' => '»',
            'current'   => $paged,
        ]),
    ],
    'events' => $events,
];

// --- 9) Renderowanie szablonu ---
try {
    echo $twig->render( 'admin/list-events.twig', $context );
} catch ( Exception $e ) {
    echo '<div class="error"><p>Błąd renderowania szablonu: ' . esc_html( $e->getMessage() ) . '</p></div>';
}
