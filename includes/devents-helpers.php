<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * DEvents Helpers
 * Zawiera funkcje pomocnicze używane w całej wtyczce.
 */

/**
 * Funkcja do rekurencyjnego usuwania ukośników z pól tekstowych obiektu/tablicy.
 * Przeznaczone do użycia po pobraniu danych z bazy danych, jeśli zawierają niechciane ukośniki.
 * Działa zarówno na pojedynczych obiektach, jak i na tablicach obiektów.
 *
 * @param mixed $data Obiekt (np. z $wpdb->get_row) lub tablica obiektów (np. z $wpdb->get_results).
 * @return mixed Oczyszczone dane.
 */
function devents_unslash_event_data($data) {
    if (empty($data)) {
        return $data;
    }

    // Lista pól, które zidentyfikowaliśmy jako podatne na ukośniki
    // Dodałem wszystkie pola VARCHAR/TEXT z Twojej tabeli events_list
    $fields_to_unslash = [
        'title', 'category', 'ticket', 'delivery_mode', 'accessibility',
        'location', 'image_url', 'image_alt_text', 'organizator', 'description',
        'action_button_text', 'action_button_link', 'video_url',
        // Dodaj inne pola tekstowe, jeśli zauważysz w nich ukośniki, np. z event_settings czy events_users (org_name)
    ];

    if (is_array($data)) { // Jeśli $data to tablica obiektów (np. z get_results)
        foreach ($data as &$item) { // Użyj referencji, aby modyfikować oryginalne obiekty w tablicy
            if (is_object($item)) {
                foreach ($fields_to_unslash as $field) {
                    if (isset($item->$field) && is_string($item->$field)) {
                        $item->$field = stripslashes($item->$field);
                    }
                }
            }
        }
    } elseif (is_object($data)) { // Jeśli $data to pojedynczy obiekt (np. z get_row)
        foreach ($fields_to_unslash as $field) {
            if (isset($data->$field) && is_string($data->$field)) {
                $data->$field = stripslashes($data->$field);
            }
        }
    }
    
    return $data;
}