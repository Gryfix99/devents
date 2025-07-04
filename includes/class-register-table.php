<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class RegisterTable {

    private $db_version = '1.2'; // Zwiększamy wersję po zmianach w strukturze
    private $table_names;

    public function __construct() {
        global $wpdb;
        $this->table_names = [
            'events_list'             => $wpdb->prefix . 'events_list',
            'events_sessions'         => $wpdb->prefix . 'events_sessions',
            'sign_language_materials' => $wpdb->prefix . 'sign_language_materials',
            'events_participants'     => $wpdb->prefix . 'events_participants',
        ];
    }

    /**
     * Tworzy lub aktualizuje strukturę niestandardowych tabel wtyczki.
     */
    public function install() {
        global $wpdb;
        $charset_collate = "DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $sql = [];

        // Tabela z listą wydarzeń
        $sql[] = "CREATE TABLE {$this->table_names['events_list']} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            other_organizer VARCHAR(255) NULL,
            category VARCHAR(100) NOT NULL,
            delivery_mode VARCHAR(50) NOT NULL,
            accessibility TEXT NOT NULL,
            location TEXT NULL,
            image_url TEXT NULL,
            image_alt_text VARCHAR(255) NULL,
            description TEXT NOT NULL,
            video_url VARCHAR(255) NULL,
            start_datetime DATETIME NOT NULL,
            end_datetime DATETIME NULL,
            price DECIMAL(10,2) DEFAULT 0.00,
            ticket VARCHAR(255) NULL,
            action_button_enabled TINYINT(1) DEFAULT 0,
            action_button_text VARCHAR(255) NULL,
            action_button_link VARCHAR(255) NULL,
            verified TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Tabela z materiałami w języku migowym
        $sql[] = "CREATE TABLE {$this->table_names['sign_language_materials']} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            author VARCHAR(255) NOT NULL,
            title VARCHAR(255) NOT NULL,
            category VARCHAR(100) NOT NULL,
            accessibility TEXT NOT NULL,
            description TEXT NOT NULL,
            publish_date DATETIME NOT NULL,
            translator VARCHAR(100) NULL,
            series_name VARCHAR(255) NULL,
            verified TINYINT(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Tabela z zapisami uczestników na wydarzenia
        $sql[] = "CREATE TABLE {$this->table_names['events_participants']} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            event_id BIGINT(20) UNSIGNED NOT NULL,
            registered_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_event (user_id, event_id)
        ) $charset_collate;";

        // Tabela z sesjami logowania (opcjonalna, do audytu)
        $sql[] = "CREATE TABLE {$this->table_names['events_sessions']} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            session_token VARCHAR(255) NOT NULL,
            login_time DATETIME NOT NULL,
            logout_time DATETIME NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";


        foreach ($sql as $query) {
            dbDelta($query);
        }

        update_option('devents_db_version', $this->db_version);
    }


    public function check_db_version() {
        $installed_version = get_option('devents_db_version');

        if ($installed_version != $this->db_version) {
            $this->install();
        }
    }
}
