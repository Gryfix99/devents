<?php
/**
 * Plik: user-edit.php
 * Lokalizacja: /devents-manager/admin-panel/
 *
 * Funkcja odpowiedzialna za edycję danych użytkownika.
 * Poprawiony kod, uwzględniający nazwy kolumn i sanitizację.
 */

// Zapobiegamy bezpośredniemu dostępowi
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Sprawdzamy uprawnienia
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Brak uprawnień.' );
}

// Pobieramy ID użytkownika z GET
$user_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
if ( ! $user_id ) {
    echo '<div class="error"><p>Nieprawidłowy identyfikator użytkownika.</p></div>';
    return;
}

global $wpdb;
$user_table_name = $wpdb->prefix . 'events_users';

// Pobieramy dane użytkownika
$user = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $user_table_name WHERE id = %d", $user_id ) );

if ( ! $user ) {
    echo '<div class="error"><p>Nie znaleziono użytkownika.</p></div>';
    return;
}

// Obsługa formularza
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['edit_user_nonce'] ) && wp_verify_nonce( $_POST['edit_user_nonce'], 'edit_user_action' ) ) {

    // Pobieramy i sanityzujemy pola
    $org_name = sanitize_text_field( $_POST['org_name'] );
    $email    = sanitize_email( $_POST['email'] );
    $nip      = sanitize_text_field( $_POST['nip'] ); // korekta nazwy pola
    $name     = sanitize_text_field( $_POST['name'] );
    $surname  = sanitize_text_field( $_POST['surname'] );
    $verified = isset( $_POST['verified'] ) ? 1 : 0;
    $role     = sanitize_text_field( $_POST['role'] );

    // Walidacja obowiązkowych pól
    if ( empty($org_name) || empty($email) || empty($name) || empty($surname) ) {
        echo '<div class="error"><p>Wypełnij wszystkie wymagane pola.</p></div>';
    } else {
        // Aktualizacja w bazie
        $updated = $wpdb->update(
            $user_table_name,
            [
                'org_name' => $org_name,
                'email'    => $email,
                'nip'      => $nip,
                'name'     => $name,
                'surname'  => $surname,
                'verified' => $verified,
                'role'     => $role,
            ],
            [ 'id' => $user_id ],
            [
                '%s', '%s', '%s', '%s', '%s', '%d', '%s'
            ],
            [ '%d' ]
        );

        if ( $updated !== false ) {
            echo '<div class="updated"><p>Dane użytkownika zostały zaktualizowane.</p></div>';
            // Odświeżamy dane użytkownika
            $user = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $user_table_name WHERE id = %d", $user_id ) );
        } else {
            echo '<div class="error"><p>Wystąpił błąd podczas aktualizacji danych.</p></div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8" />
    <title>Edytuj Użytkownika</title>
    <style>
        body {
            font-family: 'Dosis', sans-serif;
            background: #f5f7fa;
            margin: 0;
        }
        .container {
            max-width: 800px;
            margin: 30px auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            padding: 20px;
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }
        form {
            display: grid;
            gap: 20px;
        }
        label {
            font-weight: bold;
            color: #555;
            display: block;
            margin-bottom: 6px;
        }
        input[type="text"],
        input[type="email"],
        input[type="checkbox"],
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
        }
        .checkbox-label {
            font-weight: normal;
            display: flex;
            align-items: center;
            color: #555;
        }
        .button-submit {
            background-color: #0073aa;
            color: #fff;
            padding: 12px 25px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            justify-self: center;
            transition: background-color 0.3s ease;
        }
        .button-submit:hover {
            background-color: #006799;
        }
        .updated, .error {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .updated {
            background-color: #dff0d8;
            color: #3c763d;
        }
        .error {
            background-color: #f2dede;
            color: #a94442;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Edytuj Użytkownika</h1>
    <form method="POST" action="">
        <?php wp_nonce_field( 'edit_user_action', 'edit_user_nonce' ); ?>

        <div>
            <label for="org_name">Nazwa użytkownika <span style="color:red">*</span></label>
            <input type="text" id="org_name" name="org_name" value="<?= esc_attr( $user->org_name ) ?>" required placeholder="Wprowadź nazwę użytkownika">
        </div>

        <div>
            <label for="email">E-mail <span style="color:red">*</span></label>
            <input type="email" id="email" name="email" value="<?= esc_attr( $user->email ) ?>" required placeholder="Wprowadź adres e-mail">
        </div>

        <div>
            <label for="nip">NIP</label>
            <input type="text" id="nip" name="nip" value="<?= esc_attr( $user->nip ) ?>" placeholder="Wprowadź NIP">
        </div>

        <div>
            <label for="name">Imię <span style="color:red">*</span></label>
            <input type="text" id="name" name="name" value="<?= esc_attr( $user->name ) ?>" required placeholder="Wprowadź imię">
        </div>

        <div>
            <label for="surname">Nazwisko <span style="color:red">*</span></label>
            <input type="text" id="surname" name="surname" value="<?= esc_attr( $user->surname ) ?>" required placeholder="Wprowadź nazwisko">
        </div>

        <div class="checkbox-label">
            <input type="checkbox" id="verified" name="verified" value="1" <?= checked( $user->verified, 1, false ) ?>>
            <label for="verified" style="margin:0;">Zweryfikowany</label>
        </div>

        <div>
            <label for="role">Rola</label>
            <select id="role" name="role">
                <option value="event_organizer" <?= selected( $user->role, 'event_organizer', false ) ?>>Organizator</option>
                <option value="member" <?= selected( $user->role, 'member', false ) ?>>Uczestnik</option>
                <option value="admin" <?= selected( $user->role, 'admin', false ) ?>>Administrator</option>
            </select>
        </div>

        <div>
            <button type="submit" class="button-submit">Zaktualizuj użytkownika</button>
        </div>
    </form>
</div>
</body>
</html>
