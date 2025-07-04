<?php
/**
 * Wyświetla listę użytkowników WordPress, korzystając z natywnych funkcji.
 * Zastępuje logikę opartą o niestandardową tabelę.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// --- 1) Przygotowanie parametrów ---
$search    = isset( $_GET['s'] )       ? sanitize_text_field( $_GET['s'] ) : '';
$orderby   = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'registered';
$order     = isset( $_GET['order'] )   ? strtoupper( sanitize_key( $_GET['order'] ) ) : 'DESC';
$paged     = isset( $_GET['paged'] )   ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page  = 20;

// --- 2) Budowa argumentów dla WP_User_Query ---
$args = [
    'number' => $per_page,
    'paged'  => $paged,
    'order'  => $order,
];

// --- Logika sortowania ---
$allowed_sort_columns = ['ID', 'login', 'email', 'registered', 'display_name', 'org_name'];
if ( ! in_array( $orderby, $allowed_sort_columns ) ) {
    $orderby = 'registered';
}

if ( 'org_name' === $orderby ) {
    $args['meta_key'] = 'org_name';
    $args['orderby'] = 'meta_value';
} else {
    $args['orderby'] = $orderby;
}

// --- Logika wyszukiwania ---
if ( ! empty( $search ) ) {
    $args['search'] = '*' . esc_attr( $search ) . '*';
    $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
    // Wyszukiwanie również w meta danych
    $args['meta_query'] = [
        'relation' => 'OR',
        ['key' => 'first_name', 'value' => $search, 'compare' => 'LIKE'],
        ['key' => 'last_name', 'value' => $search, 'compare' => 'LIKE'],
        ['key' => 'org_name', 'value' => $search, 'compare' => 'LIKE'],
        ['key' => 'nip', 'value' => $search, 'compare' => 'LIKE'],
    ];
}

// --- 3) Wykonanie zapytania ---
$user_query = new WP_User_Query( $args );
$users = $user_query->get_results();
$total_users = $user_query->get_total();
$total_pages = ceil( $total_users / $per_page );

// --- 4) Funkcja do generowania linków sortujących ---
function sort_link_users( $column, $label ) {
    global $orderby, $order;
    $new_order = ( $orderby === $column && $order === 'ASC' ) ? 'DESC' : 'ASC';
    $arrow = ( $orderby === $column ) ? ( $order === 'ASC' ? ' &uarr;' : ' &darr;' ) : '';
    $url = add_query_arg([
        'page'    => $_GET['page'] ?? '', 's' => $_GET['s'] ?? '',
        'orderby' => $column, 'order' => $new_order,
    ], admin_url( 'admin.php' ));
    return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . $arrow . '</a>';
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Lista użytkowników</h1>

    <details class="filter-toggle">
        <summary>🔍 Filtry</summary>
        <form method="get" class="devents-filters">
            <input type="hidden" name="page" value="<?php echo esc_attr( $_GET['page'] ?? '' ); ?>">
            <input type="text" name="s" placeholder="Szukaj użytkownika..." value="<?php echo esc_attr( $search ); ?>">
            <button type="submit" class="button button-primary">Filtruj</button>
            <a href="<?php echo admin_url( 'admin.php?page=' . esc_attr( $_GET['page'] ?? '' ) ); ?>" class="button">Wyczyść</a>
        </form>
    </details>

    <div class="tablenav top">
        <div class="alignleft actions bulkactions">
            <label for="bulk-user-action-select-top" class="screen-reader-text">Wybierz akcję zbiorczą</label>
            <select name="bulk_action" id="bulk-user-action-select-top">
                <option value="">Akcje zbiorcze</option>
                <option value="delete_bulk">Usuń</option>
            </select>
            <input type="button" id="apply-bulk-user-action-top" class="button action" value="Zastosuj">
        </div>
        <div class="tablenav-pages">
            <span class="displaying-num"><?php echo $total_users; ?> użytkowników</span>
            <span class="pagination-links">
                <?php echo paginate_links(['base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'prev_text' => '&laquo;', 'next_text' => '&raquo;', 'total' => $total_pages, 'current' => $paged ]); ?>
            </span>
        </div>
        <br class="clear">
    </div>

    <form id="bulk-form-users" method="post">
        <?php wp_nonce_field( 'bulk_user_action_nonce', 'bulk_user_nonce_field' ); ?>
        <table class="wp-list-table widefat striped devents-responsive-table" id="users-table">
            <thead>
                <tr>
                    <th class="manage-column column-cb check-column"><input type="checkbox" id="select-all-users-top"></th>
                    <th><?php echo sort_link_users( 'ID', 'ID' ); ?></th>
                    <th><?php echo sort_link_users( 'login', 'Login (Email)' ); ?></th>
                    <th><?php echo sort_link_users( 'display_name', 'Imię i Nazwisko' ); ?></th>
                    <th><?php echo sort_link_users( 'org_name', 'Organizacja' ); ?></th>
                    <th class="desktop-only">Rola</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( !empty($users) ): foreach ( $users as $user ): ?>
                    <?php
                        $org_name = get_user_meta( $user->ID, 'org_name', true );
                        $verified_status = get_user_meta( $user->ID, 'devents_verified_status', true );
                    ?>
                    <tr id="user-<?php echo $user->ID; ?>">
                        <th scope="row" class="check-column">
                            <input type="checkbox" class="user-checkbox" name="user_ids[]" value="<?php echo $user->ID; ?>">
                        </th>
                        <td data-label="ID:" class="desktop-only"><?php echo $user->ID; ?></td>
                        <td data-label="Login:" class="desktop-only"><a href="mailto:<?php echo esc_attr($user->user_email); ?>"><?php echo esc_html($user->user_login); ?></a></td>
                        <td class="responsive-main-info" data-label="Użytkownik:">
                            <strong><?php echo esc_html( $user->display_name ?: '(brak)' ); ?></strong>
                            <div class="responsive-meta">
                                <span>ID: <?php echo $user->ID; ?></span><br>
                                <span>Login: <a href="mailto:<?php echo esc_attr($user->user_email); ?>"><?php echo esc_html($user->user_login); ?></a></span><br>
                                <?php if ( $org_name ): ?>
                                    <span>Organizacja: <?php echo esc_html( $org_name ); ?></span><br>
                                <?php endif; ?>
                                <span>Rola: <?php echo esc_html( implode(', ', $user->roles) ); ?></span>
                            </div>
                            <div class="row-actions">
                                <span class="edit"><a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>" class="button button-small">Edytuj</a></span>
                                <span class="delete"><a href="#" class="button button-small button-delete-user" data-user-id="<?php echo $user->ID; ?>" style="color: #a00;">Usuń</a></span>
                            </div>
                        </td>
                        <td data-label="Organizacja:" class="desktop-only"><?php echo esc_html( $org_name ?: '---' ); ?></td>
                        <td data-label="Rola:" class="desktop-only"><?php echo esc_html( implode(', ', $user->roles) ); ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="6">Nie znaleziono użytkowników.</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th class="manage-column column-cb check-column"><input type="checkbox" id="select-all-users-bottom"></th>
                    <th><?php echo sort_link_users( 'ID', 'ID' ); ?></th>
                    <th><?php echo sort_link_users( 'login', 'Login (Email)' ); ?></th>
                    <th><?php echo sort_link_users( 'display_name', 'Imię i Nazwisko' ); ?></th>
                    <th><?php echo sort_link_users( 'org_name', 'Organizacja' ); ?></th>
                    <th class="desktop-only">Rola</th>
                </tr>
            </tfoot>
        </table>
    </form>
</div>
