<?php
/**
 * Tablica z szablonami e-maili używanymi w wtyczce.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

return [
    'user_verification' => [
        'subject' => 'Potwierdź swoją rejestrację na {{site_name}}',
        'body'    => '
            <p>Witaj {{first_name}},</p>
            <p>Dziękujemy za rejestrację w serwisie {{site_name}}. Aby dokończyć proces i aktywować swoje konto, prosimy o kliknięcie w poniższy link:</p>
            <p><a href="{{verification_link}}" style="padding: 10px 15px; background-color: #0073aa; color: #ffffff; text-decoration: none; border-radius: 5px;">Aktywuj moje konto</a></p>
            <p>Jeśli przycisk nie działa, skopiuj i wklej ten adres URL do swojej przeglądarki:</p>
            <p>{{verification_link}}</p>
            <p><strong>Ważne:</strong> Link aktywacyjny jest ważny tylko przez 4 godziny.</p>
            <p>Z pozdrowieniami,<br>Zespół {{site_name}}</p>
        '
    ],

    'password_reset_code' => [
        'subject' => 'Twój kod do resetu hasła - {{site_name}}',
        'body'    => '
            <p>Witaj,</p>
            <p>Twój jednorazowy kod do zresetowania hasła to:</p>
            <h2 style="text-align:center; font-size: 32px; letter-spacing: 5px; margin: 20px 0;">{{reset_code}}</h2>
            <p>Kod jest ważny przez 15 minut.</p>
            <p>Jeśli to nie Ty prosiłeś o zmianę, zignoruj tę wiadomość.</p>
            <p>Z pozdrowieniami,<br>Zespół {{site_name}}</p>
        '
    ],
];
