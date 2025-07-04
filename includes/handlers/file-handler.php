<?php
/**
 * Obsługa przesyłania plików z niestandardową nazwą i katalogiem.
 * Wersja ostateczna, bez konwersji do formatu WebP.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class DEvents_File_Uploader {
    private $user_id;
    private $role;

    public function __construct($user_id) {
        $this->user_id = $user_id;
        $user = get_userdata($user_id);
        $this->role = in_array('organizer', (array) $user->roles) ? 'organizer' : 'subscriber';
    }

    /**
     * Główna metoda obsługująca upload.
     */
    public function handle_upload($file_key) {
        if (empty($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('no_file', 'Nie wybrano pliku lub wystąpił błąd przesyłania.');
        }

        // Podpinamy nasze niestandardowe filtry
        add_filter('upload_dir', [$this, 'change_upload_dir']);
        add_filter('wp_handle_upload_prefilter', [$this, 'rename_file']); // Zmieniono nazwę funkcji

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload($file_key, 0);

        // Odpinamy filtry, aby nie wpływały na inne uploady
        remove_filter('upload_dir', [$this, 'change_upload_dir']);
        remove_filter('wp_handle_upload_prefilter', [$this, 'rename_file']);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }
        return wp_get_attachment_url($attachment_id);
    }

    /**
     * Zmienia katalog docelowy dla logotypów.
     */
    public function change_upload_dir($path_data) {
        $custom_dir = '/devents_logos';
        if (!file_exists($path_data['basedir'] . $custom_dir)) {
            wp_mkdir_p($path_data['basedir'] . $custom_dir);
        }
        $path_data['path'] = $path_data['basedir'] . $custom_dir;
        $path_data['url'] = $path_data['baseurl'] . $custom_dir;
        $path_data['subdir'] = $custom_dir;
        return $path_data;
    }

    /**
     * Zmienia nazwę pliku przed zapisem, zachowując oryginalne rozszerzenie.
     */
    public function rename_file($file) {
        $original_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_filename = 'logo_' . $this->role . '_' . $this->user_id . '.' . $original_extension;
        $file['name'] = $new_filename;
        return $file;
    }
}