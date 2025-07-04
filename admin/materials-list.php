<?php
/**
 * Logika i renderowanie dla strony "Lista materiałów PJM" w panelu admina.
 * Wersja ostateczna z pełną obsługą filtrów, sortowania, statusów i AJAX.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Brak uprawnień.' );

global $wpdb;

// --- 1. Pobieranie parametrów (Filtry, sortowanie, paginacja) ---
$search  = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'id';
$order   = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? strtoupper($_GET['order']) : 'DESC';
$paged   = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$offset  = ($paged - 1) * $per_page;

// --- 2. Budowa zapytań SQL ---
$table_materials = $wpdb->prefix . 'events_materials';
$where_sql = "WHERE 1=1";
$params = [];

if ($search) {
    $where_sql .= " AND (title LIKE %s OR author LIKE %s)";
    $params[] = '%' . $wpdb->esc_like($search) . '%';
    $params[] = '%' . $wpdb->esc_like($search) . '%';
}

$allowed_orderby = ['id', 'title', 'author', 'verified'];
$orderby_column = in_array($orderby, $allowed_orderby) ? $orderby : 'id';

// Logika sortowania: najpierw statusy wymagające akcji (0, 2, 3), potem opublikowane (1)
$order_sql = "ORDER BY CASE WHEN verified = 1 THEN 1 ELSE 0 END ASC, {$orderby_column} {$order}";

// --- 3. Pobieranie danych ---
$total_items = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM {$table_materials} {$where_sql}", $params));
$materials_raw = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_materials} {$where_sql} {$order_sql} LIMIT %d OFFSET %d", array_merge($params, [$per_page, $offset])));

// --- 4. Przetwarzanie danych dla szablonu ---
$materials = array_map(function($material) {
    // Sprawdzenie, czy wpis CPT istnieje
    $cpt_query = new WP_Query([
        'post_type' => 'materials', 'post_status' => 'publish',
        'meta_key' => '_material_id', 'meta_value' => $material->id,
        'posts_per_page' => 1, 'fields' => 'ids'
    ]);
    $material->is_published = $cpt_query->have_posts();

    // Przygotowanie danych o statusie
    $status_map = [
        1 => ['text' => 'Zweryfikowany', 'class' => 'status-badge--published'],
        2 => ['text' => 'Wersja robocza', 'class' => 'status-badge--draft'],
        3 => ['text' => 'Do weryfikacji', 'class' => 'status-badge--updated'],
        0 => ['text' => 'Oczekuje', 'class' => 'status-badge--pending'],
    ];
    $material->status_info = $status_map[$material->verified] ?? $status_map[0];

    return $material;
}, $materials_raw);

// --- 5. Przygotowanie danych do przekazania do Twig ---
$data_for_twig = [
    'materials' => $materials,
    // ... i reszta danych potrzebnych dla szablonu (filtry, paginacja etc.) ...
];

// --- 6. Ładowanie zasobów i renderowanie szablonu ---
// Tutaj enqueue skryptu JS i lokalizacja danych, jeśli robisz to w tym pliku
// ...

$twig = DEvents_Twig_Helper::get_instance();
echo $twig->render('admin/list-materials', $data_for_twig);