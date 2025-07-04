<?php
/**
 * Plik: material-edit.php
 * Lokalizacja: /devents-manager/admin-panel/
 *
 * Funkcja odpowiedzialna za edycję danych materiału.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Brak uprawnień.' );
}

$material_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
if ( ! $material_id ) {
    echo '<div class="error"><p>Nieprawidłowy identyfikator materiału.</p></div>';
    return;
}

global $wpdb;
$material_table_name = $wpdb->prefix . 'sign_language_materials';
$material = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $material_table_name WHERE id = %d", $material_id ) );

if ( ! $material ) {
    echo '<div class="error"><p>Nie znaleziono materiału.</p></div>';
    return;
}

// Przygotowanie predefiniowanych wartości dla pola "accessibility"
$accessibility_selected = [];
if ( ! empty( $material->accessibility ) ) {
    $accessibility_selected = array_map( 'trim', explode( ',', $material->accessibility ) );
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['edit_material_nonce'] ) && wp_verify_nonce( $_POST['edit_material_nonce'], 'edit_material_action' ) ) {
    // Pobieramy i sanitizujemy dane z formularza.
    $title         = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
    $category      = isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : '';
    $author        = isset( $_POST['author'] ) ? sanitize_text_field( $_POST['author'] ) : '';
    $video_url     = isset( $_POST['video_url'] ) ? esc_url_raw( $_POST['video_url'] ) : '';
    $accessibility_arr = isset( $_POST['accessibility'] ) ? array_map( 'sanitize_text_field', $_POST['accessibility'] ) : [];
    $accessibility = implode( ',', $accessibility_arr );
    $description   = isset( $_POST['description'] ) ? sanitize_textarea_field( $_POST['description'] ) : '';
    $publish_date  = isset( $_POST['publish_date'] ) ? sanitize_text_field( $_POST['publish_date'] ) : '';
    $translator    = isset( $_POST['translator'] ) ? sanitize_text_field( $_POST['translator'] ) : '';
    $series_name   = isset( $_POST['series_name'] ) ? sanitize_text_field( $_POST['series_name'] ) : '';
    $verified      = isset( $_POST['verified'] ) ? 1 : 0;

    // Aktualizacja danych w bazie.
    $updated = $wpdb->update(
        $material_table_name,
        [
            'title'         => $title,
            'category'      => $category,
            'author'        => $author,
            'accessibility' => $accessibility,
            'description'   => $description,
            'publish_date'  => $publish_date,
            'translator'    => $translator,
            'series_name'   => $series_name,
            // Jeśli pole video_url jest częścią tabeli – dodajemy je poniżej:
            'video_url'     => $video_url,
            'verified'      => $verified,
        ],
        [ 'id' => $material_id ],
        [
            '%s', // title
            '%s', // category
            '%s', // author
            '%s', // accessibility
            '%s', // description
            '%s', // publish_date
            '%s', // translator
            '%s', // series_name
            '%s', // video_url
            '%d', // verified
        ],
        [ '%d' ]
    );

    if ( false !== $updated ) {
        echo '<div class="updated"><p>Dane materiału zostały zaktualizowane.</p></div>';
    } else {
        echo '<div class="error"><p>Wystąpił błąd podczas aktualizacji danych.</p></div>';
    }

    // Ponowne pobranie danych po aktualizacji.
    $material = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $material_table_name WHERE id = %d", $material_id ) );
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Edytuj Materiał</title>
    <link rel="stylesheet" href="admin-styles.css">
    <style>
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-label {
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
        }
        .form-input, .form-select {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
        }
        .add-form-input-info {
            font-size: 0.9em;
            color: #555;
        }
        .button-submit {
            padding: 10px 20px;
            background-color: #0073aa;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: block;
            margin: 20px auto 0;
        }
        .button-submit:hover {
            background-color: #006799;
        }
        .updated, .error {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
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
    <!-- Wczytanie Select2 CSS dla ulepszonego wyglądu wielokrotnego wyboru -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
</head>
<body>
    <div class="container">
        <h1>Edytuj Materiał</h1>
        <form action="" method="POST">
            <?php wp_nonce_field( 'edit_material_action', 'edit_material_nonce' ); ?>
            
            <!-- Kategoria -->
            <div class="form-group">
                <label for="category" class="form-label">Kategoria <span>*</span></label>
                <select id="category" name="category" class="form-select" required>
                    <option value="Film" <?php selected( $material->category, 'Film' ); ?>>Film</option>
                    <option value="Inne" <?php selected( $material->category, 'Inne' ); ?>>Inne</option>
                    <option value="Konferencja" <?php selected( $material->category, 'Konferencja' ); ?>>Konferencja</option>
                    <option value="Podcast" <?php selected( $material->category, 'Podcast' ); ?>>Podcast</option>
                    <option value="Spacer" <?php selected( $material->category, 'Spacer' ); ?>>Spacer</option>
                    <option value="Spektakl" <?php selected( $material->category, 'Spektakl' ); ?>>Spektakl</option>
                    <option value="Spotkanie autorskie" <?php selected( $material->category, 'Spotkanie autorskie' ); ?>>Spotkanie autorskie</option>
                    <option value="Warsztaty" <?php selected( $material->category, 'Warsztaty' ); ?>>Warsztaty</option>
                    <option value="Wykład" <?php selected( $material->category, 'Wykład' ); ?>>Wykład</option>
                    <option value="Zaproszenie" <?php selected( $material->category, 'Zaproszenie' ); ?>>Zaproszenie</option>
                </select>
                <p class="add-form-input-info">Wybierz kategorię publikowanego materiału.</p>
            </div>
            
            <!-- Nazwa serii -->
            <div class="form-group">
                <label for="series_name" class="form-label">Nazwa serii</label>
                <input type="text" id="series_name" name="series_name" placeholder="Podaj nazwę serii (jeśli dotyczy)" class="form-input" value="<?php echo esc_attr( $material->series_name ); ?>">
                <p class="add-form-input-info">Podaj nazwę serii, jeśli jest to kilka odcinków - pole nie jest wymagane.</p>
            </div>
            
            <!-- Tytuł -->
            <div class="form-group">
                <label for="title" class="form-label">Tytuł <span>*</span></label>
                <input type="text" id="title" name="title" placeholder="Podaj tytuł materiału" class="form-input" required value="<?php echo esc_attr( $material->title ); ?>">
                <p class="add-form-input-info">Podaj tytuł materiału, a w przypadku serii materiałów - tytuł odcinka.</p>
            </div>
            
            <!-- Autor -->
            <div class="form-group">
                <label for="author" class="form-label">Autor <span>*</span></label>
                <input type="text" id="author" name="author" placeholder="Imię i nazwisko autora" class="form-input" required value="<?php echo esc_attr( $material->author ); ?>">
                <p class="add-form-input-info">Podaj nazwę autora materiału.</p>
            </div>
            
            <!-- Link do filmu -->
            <div class="form-group">
                <label for="video_url" class="form-label">Link do filmu <span>*</span></label>
                <input type="url" id="video_url" name="video_url" placeholder="https://www.youtube.com/watch?v=..." class="form-input" required value="<?php echo isset( $material->video_url ) ? esc_attr( $material->video_url ) : ''; ?>">
                <p class="add-form-input-info">Podaj link do materiału.</p>
            </div>
            
            <!-- Data publikacji -->
            <div class="form-group">
                <label for="publish_date" class="form-label">Data publikacji <span>*</span></label>
                <input type="date" id="publish_date" name="publish_date" class="form-input" required value="<?php echo esc_attr( $material->publish_date ); ?>">
                <p class="add-form-input-info">Data opublikowania materiału w internecie.</p>
            </div>
            
            <!-- Opis -->
            <div class="form-group">
                <label for="description" class="form-label">Opis</label>
                <textarea id="description" name="description" rows="5" placeholder="Dodaj krótki opis materiału" class="form-input"><?php echo esc_textarea( $material->description ); ?></textarea>
                <p class="add-form-input-info">Opis powinien mieć od 300 do 6000 znaków.</p>
                <p class="add-form-input-info">
                    Pole obsługuje formatowanie w Markdown – możesz używać nagłówków, list, pogrubienia, kursywy, linków oraz innych elementów. 
                    <a href="/wp-content/plugins/wydarzenia/Instrukcja - jak korzystac z Markdown.pdf">Spójrz na listę przykładów!</a>
                </p>
            </div>
            
            <!-- Dostępność -->
            <div class="form-group">
                <label for="accessibility" class="form-label">Dostępność <span>*</span></label>
                <select id="accessibility" name="accessibility[]" multiple class="form-select" required>
                    <option value="Napisy po polsku" <?php echo in_array( "Napisy po polsku", $accessibility_selected ) ? 'selected' : ''; ?>>Napisy po polsku</option>
                    <option value="Napisy po angielsku" <?php echo in_array( "Napisy po angielsku", $accessibility_selected ) ? 'selected' : ''; ?>>Napisy po angielsku</option>
                    <option value="W języku migowym" <?php echo in_array( "W języku migowym", $accessibility_selected ) ? 'selected' : ''; ?>>W języku migowym</option>
                    <option value="Tłumaczenie na język migowy" <?php echo in_array( "Tłumaczenie na język migowy", $accessibility_selected ) ? 'selected' : ''; ?>>Tłumaczenie na język migowy</option>
                </select>
                <p class="add-form-input-info">Wybierz dostępność zamieszczoną w filmie - tłumaczenie, napisy lub materiał jest w języku migowym.</p>
            </div>
            
            <!-- Tłumacz -->
            <div class="form-group">
                <label for="translator" class="form-label">Tłumacz</label>
                <input type="text" id="translator" name="translator" placeholder="Podaj imię i nazwisko tłumacza (jeśli dotyczy)" class="form-input" value="<?php echo esc_attr( $material->translator ); ?>">
                <p class="add-form-input-info">Pole nie jest wymagane, jeśli materiał nie posiada tłumacza.</p>
            </div>
            
            <!-- Zweryfikowany -->
            <div class="form-group">
                <label for="verified" class="form-label">Zweryfikowany</label>
                <input type="checkbox" id="verified" name="verified" <?php checked( $material->verified, 1 ); ?>>
            </div>
            
            <button type="submit" class="button-submit">Zaktualizuj materiał</button>
        </form>
    </div>
    
    <!-- Wczytanie skryptów Select2 -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicjalizacja Select2 dla pola accessibility
            const accessibilitySelect = document.getElementById('accessibility');
            if (accessibilitySelect) {
                $(accessibilitySelect).select2({
                    placeholder: "Wybierz dostępność",
                    allowClear: true,
                    width: '100%'
                });
            }
        });
    </script>
</body>
</html>