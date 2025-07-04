<?php
/**
 * Narzędzie do jednorazowej naprawy powiązań (user_id) w tabelach
 * 'events_list' i 'sign_language_materials'.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die('Brak uprawnień.');
?>
<div class="wrap">
    <h1><span class="dashicons dashicons-admin-links"></span> Narzędzie do Naprawy Powiązań Danych</h1>

    <div class="notice notice-warning notice-alt">
        <p><strong>WAŻNE:</strong> To narzędzie należy uruchomić **tylko raz** po migracji użytkowników. Zaktualizuje ono ID autorów we wszystkich wydarzeniach i materiałach, aby pasowały do nowego systemu użytkowników WordPress.</p>
        <p>Przed uruchomieniem, upewnij się, że masz **kopię zapasową bazy danych**.</p>
    </div>

    <form method="post">
        <?php wp_nonce_field( 'devents_data_fix_nonce', 'devents_data_fix_nonce_field' ); ?>
        <p>
            <button type="submit" name="start_data_fix" class="button button-primary button-hero">
                <span class="dashicons dashicons-hammer"></span> Uruchom naprawę danych
            </button>
        </p>
    </form>
    <hr>
    <?php
    if ( isset( $_POST['start_data_fix'] ) && check_admin_referer( 'devents_data_fix_nonce', 'devents_data_fix_nonce_field' ) ) {
        global $wpdb;

        echo '<h2>Logi procesu naprawy:</h2><div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; max-height: 400px; overflow-y: auto;">';

        // 1. Utwórz mapowanie: stary_id => nowy_id na podstawie adresu e-mail
        $old_users = $wpdb->get_results("SELECT id, email FROM {$wpdb->prefix}events_users WHERE email IS NOT NULL AND email != ''");
        $new_users = get_users(['fields' => ['ID', 'user_email']]);
        
        $email_to_new_id = [];
        foreach ($new_users as $user) {
            $email_to_new_id[strtolower($user->user_email)] = $user->ID;
        }

        $old_id_to_new_id = [];
        foreach ($old_users as $old_user) {
            $email_key = strtolower($old_user->email);
            if (isset($email_to_new_id[$email_key])) {
                $old_id_to_new_id[$old_user->id] = $email_to_new_id[$email_key];
            }
        }
        echo '<p>Znaleziono ' . count($old_id_to_new_id) . ' pasujących użytkowników do zmapowania.</p>';

        // 2. Zaktualizuj tabelę `events_list`
        $events_table = $wpdb->prefix . 'events_list';
        $events_to_update = $wpdb->get_results("SELECT id, user_id FROM {$events_table}");
        $events_updated_count = 0;
        foreach ($events_to_update as $event) {
            if (isset($old_id_to_new_id[$event->user_id])) {
                $new_user_id = $old_id_to_new_id[$event->user_id];
                if ($wpdb->update($events_table, ['user_id' => $new_user_id], ['id' => $event->id])) {
                    $events_updated_count++;
                }
            }
        }
        echo "<p>Zaktualizowano powiązania dla <strong>{$events_updated_count}</strong> wydarzeń.</p>";

        // 3. Zaktualizuj tabelę `sign_language_materials`
        $materials_table = $wpdb->prefix . 'sign_language_materials';
        $materials_to_update = $wpdb->get_results("SELECT id, user_id FROM {$materials_table}");
        $materials_updated_count = 0;
        foreach ($materials_to_update as $material) {
            if (isset($old_id_to_new_id[$material->user_id])) {
                $new_user_id = $old_id_to_new_id[$material->user_id];
                if ($wpdb->update($materials_table, ['user_id' => $new_user_id], ['id' => $material->id])) {
                    $materials_updated_count++;
                }
            }
        }
        echo "<p>Zaktualizowano powiązania dla <strong>{$materials_updated_count}</strong> materiałów.</p>";
        echo '<p style="color: green; font-weight: bold;">Proces naprawy zakończony!</p>';
        echo '</div>';
    }
    ?>
</div>
