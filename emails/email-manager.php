<?php
// /emails/email-manager.php

if ( ! defined( 'ABSPATH' ) ) exit;

function devents_send_email(string $to, string $template_key, array $data = []): bool {
    // WAŻNA ZMIANA: Poprawiona ścieżka do pliku z szablonami
    $templates = include( DEW_PLUGIN_PATH . 'emails/email-templates.php' );

    if ( ! isset($templates[$template_key]) ) {
        error_log('DEvents Mailer: Nie znaleziono szablonu e-maila o kluczu: ' . $template_key);
        return false;
    }

    $template = $templates[$template_key];
    $subject = $template['subject'];
    $body = $template['body'];

    $default_data = [
        'site_name' => get_bloginfo('name'),
        'site_url'  => home_url(),
    ];
    $data = array_merge($default_data, $data);

    foreach ($data as $key => $value) {
        $subject = str_replace('{{' . $key . '}}', $value, $subject);
        $body = str_replace('{{' . $key . '}}', $value, $body);
    }
    
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    return wp_mail($to, $subject, $body, $headers);
}