<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function devents_register_post_types() {
    // Lista wydarzeń
    $event_args = [
        'labels' => ['name' => 'Wpisy z wydarzeniami', 'singular_name' => 'Wpis z wydarzeniem'],
        'public' => true, 'show_ui' => true, 'show_in_menu' => 'devents-manager',
        'show_in_rest' => true, 'supports' => ['title', 'editor', 'custom-fields'],
        'has_archive' => false, 'rewrite' => ['slug' => 'wydarzenia'],
        'show_in_nav_menus' => false,
    ];
    register_post_type( 'wydarzenia', $event_args );

    // Lista materiałów
    $material_args = [
        'labels' => ['name' => 'Wpisy z materiałami', 'singular_name' => 'Wpis z materiałem'],
        'public' => true, 'show_ui' => true, 'show_in_menu' => 'devents-manager',
        'show_in_rest' => true, 'supports' => ['title', 'editor', 'custom-fields', 'thumbnail'],
        'has_archive' => false, 'rewrite' => ['slug' => 'filmy'],
        'show_in_nav_menus' => false, 'menu_icon' => 'dashicons-media-text',
    ];
    register_post_type('materials', $material_args);
}
